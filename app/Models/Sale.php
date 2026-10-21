<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DateTime;
use RuntimeException;

/**
 * Sale model -- POS transaction aggregates for reporting/dashboard,
 * plus transactional sale creation for checkout.
 */
class Sale extends Model
{
    /**
     * Create a sale, its line items, and decrement inventory inside a
     * single mysqli transaction. Returns the new sale_id on success.
     *
     * @param array{subtotal:float,discount:float,tax:float,total:float,payment_method:string,customer_id:?int,user_id:int,coupon_id:?int} $header
     * @param array<int, array{product_id:int,quantity:int,selling_price:float}> $items
     * @param string $invoiceNo
     * @return int sale_id
     * @throws RuntimeException on failure (caller should roll back)
     */
    public function create(array $header, array $items, string $invoiceNo): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sales (invoice_no, customer_id, user_id, coupon_id, subtotal, discount, tax, total, payment_method, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $status = 'completed';
        $stmt->bind_param(
            'siiiddddss',
            $invoiceNo,
            $header['customer_id'],
            $header['user_id'],
            $header['coupon_id'],
            $header['subtotal'],
            $header['discount'],
            $header['tax'],
            $header['total'],
            $header['payment_method'],
            $status
        );

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to insert sale: ' . $stmt->error);
        }
        $saleId = (int) $stmt->insert_id;
        $stmt->close();

        $itemStmt = $this->db->prepare(
            'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price)
             VALUES (?, ?, ?, ?)'
        );
        $invStmt = $this->db->prepare(
            'UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND quantity >= ?'
        );

        foreach ($items as $item) {
            $qty = $item['quantity'];
            $price = $item['selling_price'];
            $pid = $item['product_id'];

            $itemStmt->bind_param('iiid', $saleId, $pid, $qty, $price);
            if (!$itemStmt->execute()) {
                throw new RuntimeException('Failed to insert sale item: ' . $itemStmt->error);
            }

            $invStmt->bind_param('iii', $qty, $pid, $qty);
            if (!$invStmt->execute()) {
                throw new RuntimeException('Failed to decrement inventory for product ' . $pid);
            }
            if ($invStmt->affected_rows === 0) {
                throw new RuntimeException('Insufficient stock for product ' . $pid);
            }
        }

        $itemStmt->close();
        $invStmt->close();

        return $saleId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithItems(int $saleId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.sale_id, s.invoice_no, s.customer_id, s.user_id, s.coupon_id,
                    s.subtotal, s.discount, s.tax, s.total, s.payment_method, s.status, s.created_at,
                    u.full_name AS cashier_name,
                    c.code AS coupon_code, c.discount_type, c.value AS coupon_value,
                    cust.full_name AS customer_name, cust.email AS customer_email, cust.phone AS customer_phone
             FROM sales s
             LEFT JOIN users u ON u.user_id = s.user_id
             LEFT JOIN coupons c ON c.coupon_id = s.coupon_id
             LEFT JOIN customers cust ON cust.customer_id = s.customer_id
             WHERE s.sale_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $sale = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$sale) {
            return null;
        }

        $itemStmt = $this->db->prepare(
            'SELECT si.sale_item_id, si.product_id, si.quantity, si.unit_price, si.line_total,
                    p.name AS product_name, p.sku
             FROM sale_items si
             LEFT JOIN products p ON p.product_id = si.product_id
             WHERE si.sale_id = ?'
        );
        $itemStmt->bind_param('i', $saleId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();

        $items = [];
        while ($row = $itemResult->fetch_assoc()) {
            $items[] = $row;
        }
        $itemStmt->close();

        $sale['items'] = $items;

        return $sale;
    }

    /**
     * Generate a unique invoice number that is not already in use.
     */
    public function generateInvoiceNo(): string
    {
        do {
            $no = 'INV-' . date('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->invoiceExists($no));

        return $no;
    }

    /**
     * Generate a unique receipt number.
     */
    public function generateReceiptNo(): string
    {
        do {
            $no = 'RCP-' . date('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->receiptExists($no));

        return $no;
    }

    private function invoiceExists(string $invoiceNo): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM sales WHERE invoice_no = ? LIMIT 1');
        $stmt->bind_param('s', $invoiceNo);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    private function receiptExists(string $receiptNo): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM receipts WHERE receipt_number = ? LIMIT 1');
        $stmt->bind_param('s', $receiptNo);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allHistory(?string $from, ?string $to, ?int $userId, int $limit, int $offset): array
    {
        [$where, $types, $params] = $this->buildHistoryFilter($from, $to, $userId);
        $sql = 'SELECT s.sale_id, s.invoice_no, s.total, s.payment_method, s.created_at,
                       u.full_name AS cashier_name,
                       cust.full_name AS customer_name
                FROM sales s
                LEFT JOIN users u ON u.user_id = s.user_id
                LEFT JOIN customers cust ON cust.customer_id = s.customer_id
                ' . $where . ' ORDER BY s.created_at DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    public function countHistory(?string $from, ?string $to, ?int $userId): int
    {
        [$where, $types, $params] = $this->buildHistoryFilter($from, $to, $userId);
        $sql = 'SELECT COUNT(*) AS c FROM sales s ' . $where;
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{0:string, 1:string, 2:array<int, mixed>}
     */
    private function buildHistoryFilter(?string $from, ?string $to, ?int $userId): array
    {
        $conditions = [];
        $types = '';
        $params = [];

        if ($from !== null && $from !== '') {
            $conditions[] = 's.created_at >= ?';
            $types .= 's';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $conditions[] = 's.created_at <= ?';
            $types .= 's';
            $params[] = $to . ' 23:59:59';
        }
        if ($userId !== null) {
            $conditions[] = 's.user_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $types, $params];
    }
    /**
     * Total revenue (SUM of sales.total) for completed sales, optionally
     * bounded by a [from, to) datetime window and/or a cashier.
     */
    public function getTotalRevenue(?string $from = null, ?string $to = null, ?int $userId = null): float
    {
        $sql = "SELECT COALESCE(SUM(total), 0) AS revenue FROM sales WHERE status = 'completed'";
        [$sql, $types, $params] = $this->applyRange($sql, $from, $to, $userId);

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float) ($row['revenue'] ?? 0);
    }

    /**
     * Count of completed sales, optionally bounded by a [from, to) window
     * and/or a cashier.
     */
    public function getSalesCount(?string $from = null, ?string $to = null, ?int $userId = null): int
    {
        $sql = "SELECT COUNT(*) AS c FROM sales WHERE status = 'completed'";
        [$sql, $types, $params] = $this->applyRange($sql, $from, $to, $userId);

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Total cost of goods sold (SUM of item quantity * product cost price)
     * for completed sales, optionally bounded by a window and/or cashier.
     */
    public function getCostPrice(?string $from = null, ?string $to = null, ?int $userId = null): float
    {
        $sql = "SELECT COALESCE(SUM(si.quantity * p.cost_price), 0) AS cost
                FROM sales s
                JOIN sale_items si ON si.sale_id = s.sale_id
                LEFT JOIN products p ON p.product_id = si.product_id
                WHERE s.status = 'completed'";
        $types = '';
        $params = [];

        if ($from !== null) {
            $sql .= ' AND s.created_at >= ?';
            $types .= 's';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND s.created_at < ?';
            $types .= 's';
            $params[] = $to;
        }
        if ($userId !== null) {
            $sql .= ' AND s.user_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float) ($row['cost'] ?? 0);
    }

    /**
     * Gross profit (revenue minus cost of goods sold) for completed sales.
     */
    public function getProfit(?string $from = null, ?string $to = null, ?int $userId = null): float
    {
        return $this->getTotalRevenue($from, $to, $userId) - $this->getCostPrice($from, $to, $userId);
    }

    /**
     * Per-cashier sales summary: revenue, cost and profit for completed
     * sales, newest/highest revenue first. Used for the admin drill-down.
     *
     * @return array<int, array{user_id:int,full_name:?string,user_type:?string,sales_count:int,revenue:float,cost:float,profit:float}>
     */
    public function getSalesSummaryByUser(?string $from = null, ?string $to = null): array
    {
        $rangeSql = '';
        $types = '';
        $params = [];
        if ($from !== null) {
            $rangeSql .= ' AND s.created_at >= ?';
            $types .= 's';
            $params[] = $from;
        }
        if ($to !== null) {
            $rangeSql .= ' AND s.created_at < ?';
            $types .= 's';
            $params[] = $to;
        }

        $revSql = "SELECT u.user_id, u.full_name, u.user_type,
                          COUNT(s.sale_id) AS sales_count,
                          COALESCE(SUM(s.total), 0) AS revenue
                   FROM sales s
                   LEFT JOIN users u ON u.user_id = s.user_id
                   WHERE s.status = 'completed'" . $rangeSql . "
                   GROUP BY u.user_id, u.full_name, u.user_type";

        $stmt = $this->db->prepare($revSql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $uid = $row['user_id'] !== null ? (int) $row['user_id'] : 0;
            $rows[$uid] = [
                'user_id' => $uid,
                'full_name' => $row['full_name'],
                'user_type' => $row['user_type'],
                'sales_count' => (int) $row['sales_count'],
                'revenue' => (float) $row['revenue'],
                'cost' => 0.0,
                'profit' => 0.0,
            ];
        }
        $stmt->close();

        if ($rows) {
            $costSql = "SELECT s.user_id, COALESCE(SUM(si.quantity * p.cost_price), 0) AS cost
                        FROM sales s
                        JOIN sale_items si ON si.sale_id = s.sale_id
                        LEFT JOIN products p ON p.product_id = si.product_id
                        WHERE s.status = 'completed'" . $rangeSql . "
                        GROUP BY s.user_id";
            $stmt = $this->db->prepare($costSql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $costResult = $stmt->get_result();
            while ($row = $costResult->fetch_assoc()) {
                $uid = $row['user_id'] !== null ? (int) $row['user_id'] : 0;
                if (isset($rows[$uid])) {
                    $rows[$uid]['cost'] = (float) $row['cost'];
                }
            }
            $stmt->close();
        }

        foreach ($rows as &$r) {
            $r['profit'] = $r['revenue'] - $r['cost'];
        }
        unset($r);

        usort($rows, static fn(array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return array_values($rows);
    }

    /**
     * Revenue for each of the last $days days (oldest first).
     * Missing days are filled with 0 so the chart always has a full week.
     *
     * @return array<int, array{label:string, value:float}>
     */
    public function getRevenueByDay(int $days = 7, ?int $userId = null): array
    {
        $start = (new DateTime('today'))->modify('-' . ($days - 1) . ' days');
        $startStr = $start->format('Y-m-d 00:00:00');

        $sql = "SELECT DATE(created_at) AS d, COALESCE(SUM(total), 0) AS v
                FROM sales
                WHERE status = 'completed' AND created_at >= ?";
        $types = 's';
        $params = [$startStr];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }
        $sql .= ' GROUP BY DATE(created_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $byDate = [];
        while ($row = $result->fetch_assoc()) {
            $byDate[$row['d']] = (float) $row['v'];
        }
        $stmt->close();

        $series = [];
        $cursor = clone $start;
        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->format('Y-m-d');
            $series[] = [
                'label' => $cursor->format('D'),
                'value' => $byDate[$key] ?? 0.0,
            ];
            $cursor->modify('+1 day');
        }

        return $series;
    }

    /**
     * Revenue for each of the last $weeks ISO weeks (oldest first).
     *
     * @return array<int, array{label:string, value:float}>
     */
    public function getRevenueByWeek(int $weeks = 7, ?int $userId = null): array
    {
        $start = (new DateTime('monday this week'))->modify('-' . ($weeks - 1) . ' weeks');
        $startStr = $start->format('Y-m-d 00:00:00');

        $sql = "SELECT YEARWEEK(created_at, 3) AS yw, COALESCE(SUM(total), 0) AS v
                FROM sales
                WHERE status = 'completed' AND created_at >= ?";
        $types = 's';
        $params = [$startStr];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }
        $sql .= ' GROUP BY YEARWEEK(created_at, 3)';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $byWeek = [];
        while ($row = $result->fetch_assoc()) {
            $byWeek[(string) $row['yw']] = (float) $row['v'];
        }
        $stmt->close();

        $series = [];
        $cursor = clone $start;
        for ($i = 0; $i < $weeks; $i++) {
            $key = $cursor->format('oW');
            $series[] = [
                'label' => $cursor->format('M j'),
                'value' => $byWeek[$key] ?? 0.0,
            ];
            $cursor->modify('+1 week');
        }

        return $series;
    }

    /**
     * Revenue for each of the last $months calendar months (oldest first).
     *
     * @return array<int, array{label:string, value:float}>
     */
    public function getRevenueByMonth(int $months = 7, ?int $userId = null): array
    {
        $start = (new DateTime('first day of this month 00:00'))->modify('-' . ($months - 1) . ' months');
        $startStr = $start->format('Y-m-d 00:00:00');

        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total), 0) AS v
                FROM sales
                WHERE status = 'completed' AND created_at >= ?";
        $types = 's';
        $params = [$startStr];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }
        $sql .= " GROUP BY DATE_FORMAT(created_at, '%Y-%m')";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $byMonth = [];
        while ($row = $result->fetch_assoc()) {
            $byMonth[$row['ym']] = (float) $row['v'];
        }
        $stmt->close();

        $series = [];
        $cursor = clone $start;
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $series[] = [
                'label' => $cursor->format('M'),
                'value' => $byMonth[$key] ?? 0.0,
            ];
            $cursor->modify('+1 month');
        }

        return $series;
    }

    /**
     * Append an optional [from, to) window and/or user filter to a WHERE
     * clause that already targets `sales` (unaliased).
     *
     * @return array{0:string, 1:string, 2:array<int,string|int>}
     */
    private function applyRange(string $sql, ?string $from, ?string $to, ?int $userId = null): array
    {
        $types = '';
        $params = [];

        if ($from !== null) {
            $sql .= ' AND created_at >= ?';
            $types .= 's';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND created_at < ?';
            $types .= 's';
            $params[] = $to;
        }
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }

        return [$sql, $types, $params];
    }
}
