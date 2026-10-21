<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DateTime;

class Report extends Model
{
    public function getTotalRevenue(?string $from, ?string $to): float
    {
        [$where, $types, $params] = $this->dateRange($from, $to);

        $sql = "SELECT COALESCE(SUM(total), 0) AS v FROM sales WHERE status = 'completed'" . $where;
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float) ($row['v'] ?? 0);
    }

    public function getTotalTransactions(?string $from, ?string $to): int
    {
        [$where, $types, $params] = $this->dateRange($from, $to);

        $sql = "SELECT COUNT(*) AS c FROM sales WHERE status = 'completed'" . $where;
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    public function getAvgOrderValue(?string $from, ?string $to): float
    {
        [$where, $types, $params] = $this->dateRange($from, $to);

        $sql = "SELECT COALESCE(AVG(total), 0) AS v FROM sales WHERE status = 'completed'" . $where;
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float) ($row['v'] ?? 0);
    }

    public function getRevenueSeries(string $granularity, ?string $from, ?string $to): array
    {
        $fmt = match ($granularity) {
            'daily' => '%Y-%m-%d',
            'monthly' => '%Y-%m',
            default => '%Y-%u',
        };
        $labelFmt = match ($granularity) {
            'daily' => 'M j',
            'monthly' => 'M Y',
            default => '\WW',
        };

        [$where, $types, $params] = $this->dateRange($from, $to);

        $sql = "SELECT DATE_FORMAT(created_at, ?) AS bucket, COALESCE(SUM(total), 0) AS v
                FROM sales
                WHERE status = 'completed'" . $where . "
                GROUP BY bucket
                ORDER BY MIN(created_at) ASC";

        $stmtTypes = 's' . $types;
        $stmtParams = array_merge([$fmt], $params);

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($stmtTypes, ...$stmtParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $series = [];
        while ($row = $result->fetch_assoc()) {
            $series[] = [
                'label' => $row['bucket'],
                'value' => (float) $row['v'],
            ];
        }
        $stmt->close();

        $fromObj = $from ? new DateTime($from) : new DateTime('-30 days');
        $toObj = $to ? new DateTime($to) : new DateTime('now');

        $filled = $this->fillGaps($series, $granularity, $fromObj, $toObj, $labelFmt);

        return $filled;
    }

    public function getSalesByStaff(?string $from, ?string $to, ?int $staffId = null): array
    {
        [$where, $types, $params] = $this->dateRange($from, $to, 's');

        $staffCondition = '';
        if ($staffId !== null) {
            $staffCondition = ' AND s.user_id = ?';
            $types .= 'i';
            $params[] = $staffId;
        }

        $sql = "SELECT s.user_id, u.full_name,
                       COUNT(*) AS transaction_count,
                       COALESCE(SUM(s.total), 0) AS total_revenue
                FROM sales s
                JOIN users u ON u.user_id = s.user_id
                WHERE s.status = 'completed'" . $where . $staffCondition . "
                GROUP BY s.user_id, u.full_name
                ORDER BY total_revenue DESC";

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $name = trim((string) $row['full_name']);
            $parts = explode(' ', $name);
            $row['initials'] = '';
            foreach (array_slice($parts, 0, 2) as $p) {
                if ($p !== '') {
                    $row['initials'] .= strtoupper($p[0]);
                }
            }
            $row['total_revenue'] = (float) $row['total_revenue'];
            $row['transaction_count'] = (int) $row['transaction_count'];
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    public function getTopSellingProducts(?string $from, ?string $to, int $limit, int $offset): array
    {
        [$where, $types, $params] = $this->dateRange($from, $to, 's');

        if ($where !== '') {
            $where = ' WHERE s.status = \'completed\'' . $where;
        } else {
            $where = " WHERE s.status = 'completed'";
        }

        $sql = "SELECT p.product_id, p.name, p.sku, p.selling_price,
                       c.name AS category_name,
                       COALESCE(SUM(si.quantity), 0) AS units_sold,
                       COALESCE(SUM(si.line_total), 0) AS total_revenue
                FROM sale_items si
                JOIN sales s ON s.sale_id = si.sale_id
                JOIN products p ON p.product_id = si.product_id
                LEFT JOIN categories c ON c.category_id = p.category_id" . $where . "
                GROUP BY p.product_id, p.name, p.sku, p.selling_price, c.name
                ORDER BY total_revenue DESC
                LIMIT ? OFFSET ?";

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $row['units_sold'] = (int) $row['units_sold'];
            $row['total_revenue'] = (float) $row['total_revenue'];
            $row['selling_price'] = (float) $row['selling_price'];
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    public function countTopSellingProducts(?string $from, ?string $to): int
    {
        [$where, $types, $params] = $this->dateRange($from, $to, 's');

        if ($where !== '') {
            $where = ' WHERE s.status = \'completed\'' . $where;
        } else {
            $where = " WHERE s.status = 'completed'";
        }

        $sql = "SELECT COUNT(DISTINCT p.product_id) AS c
                FROM sale_items si
                JOIN sales s ON s.sale_id = si.sale_id
                JOIN products p ON p.product_id = si.product_id" . $where;

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
     * @return array<int, array<string, mixed>>
     */
    public function getStaff(): array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, full_name, user_type FROM users WHERE active = 1 ORDER BY full_name ASC'
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTechnicians(): array
    {
        $stmt = $this->db->prepare(
            "SELECT user_id, full_name FROM users WHERE active = 1 AND user_type = 'technician' ORDER BY full_name ASC"
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    /**
     * Aggregate repair-job counts and revenue within a date window.
     *
     * @return array{total:int,pending:int,in_progress:int,completed:int,overdue:int,revenue:float}
     */
    public function getRepairOverview(?string $from, ?string $to, ?int $technicianId = null): array
    {
        [$where, $types, $params] = $this->repairRange($from, $to, $technicianId);

        $sql = "SELECT COUNT(*) AS total,
                       COALESCE(SUM(CASE WHEN rj.status IN ('received','diagnosing') THEN 1 ELSE 0 END), 0) AS pending,
                       COALESCE(SUM(CASE WHEN rj.status = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress,
                       COALESCE(SUM(CASE WHEN rj.status IN ('completed','delivered') THEN 1 ELSE 0 END), 0) AS completed,
                       COALESCE(SUM(CASE WHEN rj.due_date IS NOT NULL AND rj.due_date < NOW() AND rj.status NOT IN ('completed','delivered','cancelled') THEN 1 ELSE 0 END), 0) AS overdue,
                       COALESCE(SUM(CASE WHEN rj.status IN ('completed','delivered') THEN rj.final_cost ELSE 0 END), 0) AS revenue
                FROM repair_jobs rj
                WHERE 1 = 1" . $where;

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'revenue' => (float) ($row['revenue'] ?? 0),
        ];
    }

    /**
     * Repair jobs grouped by assigned technician.
     *
     * @return array<int, array{user_id:int,full_name:?string,job_count:int,completed:int,revenue:float}>
     */
    public function getRepairsByTechnician(?string $from, ?string $to): array
    {
        [$where, $types, $params] = $this->repairRange($from, $to, null);

        $sql = "SELECT u.user_id, u.full_name,
                       COUNT(rj.repair_id) AS job_count,
                       COALESCE(SUM(CASE WHEN rj.status IN ('completed','delivered') THEN 1 ELSE 0 END), 0) AS completed,
                       COALESCE(SUM(CASE WHEN rj.status IN ('completed','delivered') THEN rj.final_cost ELSE 0 END), 0) AS revenue
                FROM repair_jobs rj
                LEFT JOIN users u ON u.user_id = rj.technician_id
                WHERE 1 = 1" . $where . "
                GROUP BY u.user_id, u.full_name
                ORDER BY job_count DESC";

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'user_id' => (int) $row['user_id'],
                'full_name' => $row['full_name'],
                'job_count' => (int) $row['job_count'],
                'completed' => (int) $row['completed'],
                'revenue' => (float) $row['revenue'],
            ];
        }
        $stmt->close();

        return $rows;
    }

    /**
     * Recent repair jobs within a date window (and optional technician).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentRepairs(?string $from, ?string $to, ?int $technicianId = null, int $limit = 15): array
    {
        [$where, $types, $params] = $this->repairRange($from, $to, $technicianId);
        $types .= 'i';
        $params[] = $limit;

        $sql = "SELECT rj.repair_id, rj.ticket_no, rj.device_brand, rj.device_model,
                       rj.urgency, rj.status, rj.estimated_cost, rj.final_cost, rj.received_at,
                       cust.full_name AS customer_name,
                       tech.full_name AS technician_name
                FROM repair_jobs rj
                LEFT JOIN customers cust ON cust.customer_id = rj.customer_id
                LEFT JOIN users tech ON tech.user_id = rj.technician_id
                WHERE 1 = 1" . $where . "
                ORDER BY rj.received_at DESC
                LIMIT ?";

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

    /**
     * Completed-sales revenue grouped by payment method.
     *
     * @return array<int, array{payment_method:string,transaction_count:int,revenue:float}>
     */
    public function getRevenueByPaymentMethod(?string $from, ?string $to): array
    {
        [$where, $types, $params] = $this->dateRange($from, $to);

        $sql = "SELECT payment_method, COUNT(*) AS transaction_count, COALESCE(SUM(total), 0) AS revenue
                FROM sales
                WHERE status = 'completed'" . $where . "
                GROUP BY payment_method
                ORDER BY revenue DESC";

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'payment_method' => $row['payment_method'],
                'transaction_count' => (int) $row['transaction_count'],
                'revenue' => (float) $row['revenue'],
            ];
        }
        $stmt->close();

        return $rows;
    }

    /**
     * @return array{products:int,units:int,stock_value:float,low_stock:int}
     */
    public function getInventoryStats(): array
    {
        $sql = "SELECT COUNT(*) AS products,
                       COALESCE(SUM(i.quantity), 0) AS units,
                       COALESCE(SUM(i.quantity * p.cost_price), 0) AS stock_value,
                       COALESCE(SUM(CASE WHEN i.quantity <= i.reorder_level THEN 1 ELSE 0 END), 0) AS low_stock
                FROM inventory i
                JOIN products p ON p.product_id = i.product_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'products' => (int) ($row['products'] ?? 0),
            'units' => (int) ($row['units'] ?? 0),
            'stock_value' => (float) ($row['stock_value'] ?? 0),
            'low_stock' => (int) ($row['low_stock'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{category:string,products:int,units:int,stock_value:float}>
     */
    public function getInventoryByCategory(): array
    {
        $sql = "SELECT COALESCE(c.name, 'Uncategorized') AS category,
                       COUNT(*) AS products,
                       COALESCE(SUM(i.quantity), 0) AS units,
                       COALESCE(SUM(i.quantity * p.cost_price), 0) AS stock_value
                FROM inventory i
                JOIN products p ON p.product_id = i.product_id
                LEFT JOIN categories c ON c.category_id = p.category_id
                GROUP BY c.category_id, c.name
                ORDER BY units DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'category' => $row['category'],
                'products' => (int) $row['products'],
                'units' => (int) $row['units'],
                'stock_value' => (float) $row['stock_value'],
            ];
        }
        $stmt->close();

        return $rows;
    }

    /**
     * Products at or below their reorder level.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLowStockItems(): array
    {
        $sql = "SELECT i.product_id, p.name, p.sku, i.quantity, i.reorder_level
                FROM inventory i
                JOIN products p ON p.product_id = i.product_id
                WHERE i.quantity <= i.reorder_level
                ORDER BY i.quantity ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    /**
     * @return array{0:string, 1:string, 2:array<int, string|int>}
     */
    private function repairRange(?string $from, ?string $to, ?int $technicianId = null): array
    {
        $where = '';
        $types = '';
        $params = [];

        if ($from !== null && $from !== '') {
            $where .= ' AND rj.received_at >= ?';
            $types .= 's';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $where .= ' AND rj.received_at <= ?';
            $types .= 's';
            $params[] = $to . ' 23:59:59';
        }
        if ($technicianId !== null) {
            $where .= ' AND rj.technician_id = ?';
            $types .= 'i';
            $params[] = $technicianId;
        }

        return [$where, $types, $params];
    }

    private function dateRange(?string $from, ?string $to, string $table = ''): array
    {
        $prefix = $table !== '' ? $table . '.' : '';
        $where = '';
        $types = '';
        $params = [];

        if ($from !== null && $from !== '') {
            $where .= " AND {$prefix}created_at >= ?";
            $types .= 's';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $where .= " AND {$prefix}created_at <= ?";
            $types .= 's';
            $params[] = $to . ' 23:59:59';
        }

        return [$where, $types, $params];
    }

    private function fillGaps(array $series, string $granularity, DateTime $from, DateTime $to, string $labelFmt): array
    {
        $interval = match ($granularity) {
            'daily' => '+1 day',
            'monthly' => '+1 month',
            default => '+1 week',
        };
        $bucketKey = match ($granularity) {
            'daily' => 'Y-m-d',
            'monthly' => 'Y-m',
            default => 'o-W',
        };

        $lookup = [];
        foreach ($series as $point) {
            $lookup[$point['label']] = $point['value'];
        }

        $filled = [];
        $cursor = clone $from;
        $end = clone $to;

        while ($cursor <= $end) {
            $key = $cursor->format($bucketKey);
            $filled[] = [
                'label' => $cursor->format($labelFmt),
                'value' => $lookup[$key] ?? 0.0,
            ];
            $cursor->modify($interval);
        }

        return $filled;
    }
}
