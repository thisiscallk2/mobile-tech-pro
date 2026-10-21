<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class RepairJob extends Model
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function allFiltered(?int $technicianId, ?string $urgency, ?string $search, int $limit, int $offset): array
    {
        [$where, $types, $params] = $this->buildFilter($technicianId, $urgency, $search);
        $sql = $this->selectColumns() . $where . ' ORDER BY rj.due_date ASC, rj.created_at DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        return $this->fetchAll($stmt);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function all(?int $technicianId, ?string $urgency, ?string $search): array
    {
        [$where, $types, $params] = $this->buildFilter($technicianId, $urgency, $search);
        $sql = $this->selectColumns() . $where . ' ORDER BY rj.due_date ASC, rj.created_at DESC';

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();

        return $this->fetchAll($stmt);
    }

    public function countFiltered(?int $technicianId, ?string $urgency, ?string $search): int
    {
        [$where, $types, $params] = $this->buildFilter($technicianId, $urgency, $search);
        $sql = 'SELECT COUNT(*) AS c FROM repair_jobs rj ' . $where;
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
     * @return array<int, array<string,mixed>>
     */
    public function findByStatus(string $status): array
    {
        if ($status === 'pending') {
            $sql = $this->selectColumns() . "WHERE rj.status IN ('received','diagnosing') ORDER BY rj.due_date ASC, rj.created_at DESC";
            $stmt = $this->db->prepare($sql);
        } else {
            $sql = $this->selectColumns() . 'WHERE rj.status = ? ORDER BY rj.due_date ASC, rj.created_at DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('s', $status);
        }
        $stmt->execute();

        return $this->fetchAll($stmt);
    }

    public function countOverdue(): int
    {
        $sql = "SELECT COUNT(*) AS c FROM repair_jobs
                WHERE due_date IS NOT NULL AND due_date < NOW()
                AND status NOT IN ('completed','delivered','cancelled')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    public function countDueToday(): int
    {
        $sql = "SELECT COUNT(*) AS c FROM repair_jobs
                WHERE due_date IS NOT NULL AND DATE(due_date) = CURDATE()
                AND status NOT IN ('completed','delivered','cancelled')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = $this->selectColumns() . 'WHERE rj.repair_id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): void
    {
        $this->db->begin_transaction();
        try {
            $updates = ['received', 'diagnosing', 'in_progress', 'completed', 'delivered', 'cancelled'];
            if (!in_array($status, $updates, true)) {
                throw new \RuntimeException('Invalid status: ' . $status);
            }

            $completedAt = in_array($status, ['completed', 'delivered'], true) ? date('Y-m-d H:i:s') : null;

            $stmt = $this->db->prepare(
                'UPDATE repair_jobs SET status = ?, completed_at = ? WHERE repair_id = ?'
            );
            $stmt->bind_param('ssi', $status, $completedAt, $id);
            $stmt->execute();
            $stmt->close();

            $noteMap = [
                'received' => 'Job marked as received.',
                'diagnosing' => 'Job moved to diagnosing.',
                'in_progress' => 'Work in progress.',
                'completed' => 'Repair completed, ready for pickup.',
                'delivered' => 'Device delivered to customer.',
                'cancelled' => 'Job cancelled.',
            ];
            $note = $noteMap[$status] ?? 'Status changed to ' . $status;

            $upd = $this->db->prepare(
                'INSERT INTO repair_updates (repair_id, user_id, status, note) VALUES (?, ?, ?, ?)'
            );
            $upd->bind_param('iiss', $id, $userId, $status, $note);
            $upd->execute();
            $upd->close();

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function addNote(int $id, string $note, ?int $userId = null): void
    {
        $currentStatus = null;
        $stmt = $this->db->prepare('SELECT status FROM repair_jobs WHERE repair_id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $currentStatus = $row['status'];
        }
        $stmt->close();

        $stmt = $this->db->prepare(
            'INSERT INTO repair_updates (repair_id, user_id, status, note) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('iiss', $id, $userId, $currentStatus, $note);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Kanban column → DB status mapping.
     * @return array<string, string|array<int,string>>
     */
    public static function kanbanStatuses(): array
    {
        return [
            'pending' => ['received', 'diagnosing'],
            'in_progress' => 'in_progress',
            'completed' => 'completed',
        ];
    }

    /**
     * Map a DB status to a Kanban column.
     */
    public static function statusToColumn(string $status): string
    {
        return match ($status) {
            'received', 'diagnosing' => 'pending',
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            'delivered' => 'ready',
            default => 'pending',
        };
    }

    /**
     * Next status in the workflow from a Kanban column.
     */
    public static function nextStatus(string $column): ?string
    {
        return match ($column) {
            'pending' => 'in_progress',
            'in_progress' => 'completed',
            default => null,
        };
    }

    public function getPendingRepairsCount(): int
    {
        $sql = "SELECT COUNT(*) AS c FROM repair_jobs
                WHERE status IN ('received', 'diagnosing', 'in_progress')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Revenue earned from completed/delivered repairs, optionally scoped to
     * a single technician.
     */
    public function getRepairRevenue(?int $technicianId = null): float
    {
        $sql = "SELECT COALESCE(SUM(final_cost), 0) AS revenue FROM repair_jobs
                WHERE status IN ('completed', 'delivered')";
        $types = '';
        $params = [];

        if ($technicianId !== null) {
            $sql .= ' AND technician_id = ?';
            $types = 'i';
            $params[] = $technicianId;
        }

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
     * @return array{pending:int, in_progress:int, completed:int, total:int}
     */
    public function getRepairStatusBreakdown(): array
    {
        $sql = "SELECT status, COUNT(*) AS c FROM repair_jobs GROUP BY status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $raw = [
            'received' => 0, 'diagnosing' => 0, 'in_progress' => 0,
            'completed' => 0, 'delivered' => 0, 'cancelled' => 0,
        ];
        while ($row = $result->fetch_assoc()) {
            $raw[$row['status']] = (int) $row['c'];
        }
        $stmt->close();

        $pending = $raw['received'] + $raw['diagnosing'];
        $inProgress = $raw['in_progress'];
        $completed = $raw['completed'];

        return [
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'total' => $pending + $inProgress + $completed,
        ];
    }

    /* ---------------------------------------------------------------- */

    private function selectColumns(): string
    {
        return 'SELECT rj.repair_id, rj.ticket_no, rj.customer_id, rj.technician_id,
                       rj.device_brand, rj.device_model, rj.imei, rj.urgency,
                       rj.reported_issue, rj.diagnosis, rj.status,
                       rj.estimated_cost, rj.final_cost, rj.due_date,
                       rj.received_at, rj.completed_at, rj.created_at, rj.updated_at,
                       cust.full_name AS customer_name, cust.phone AS customer_phone,
                       tech.full_name AS technician_name,
                       (SELECT 1 FROM notifications n WHERE n.repair_id = rj.repair_id AND n.status = \'sent\' LIMIT 1) AS has_notification
                FROM repair_jobs rj
                LEFT JOIN customers cust ON cust.customer_id = rj.customer_id
                LEFT JOIN users tech ON tech.user_id = rj.technician_id ';
    }

    /**
     * @return array{0:string, 1:string, 2:array<int,string|int>}
     */
    private function buildFilter(?int $technicianId, ?string $urgency, ?string $search): array
    {
        $conditions = [];
        $types = '';
        $params = [];

        if ($technicianId !== null) {
            $conditions[] = 'rj.technician_id = ?';
            $types .= 'i';
            $params[] = $technicianId;
        }
        if ($urgency !== null && $urgency !== '') {
            $conditions[] = 'rj.urgency = ?';
            $types .= 's';
            $params[] = $urgency;
        }
        if ($search !== null && $search !== '') {
            $conditions[] = '(rj.ticket_no LIKE ? OR cust.full_name LIKE ?)';
            $like = '%' . $search . '%';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $types, $params];
    }

    /**
     * @param \mysqli_stmt $stmt
     * @return array<int, array<string,mixed>>
     */
    private function fetchAll($stmt): array
    {
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}
