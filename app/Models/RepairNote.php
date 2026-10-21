<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class RepairNote extends Model
{
    /**
     * Chronological notes/updates for a repair job, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByJob(int $jobId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ru.update_id, ru.repair_id, ru.user_id, ru.status, ru.note, ru.created_at,
                    u.full_name AS user_name
             FROM repair_updates ru
             LEFT JOIN users u ON u.user_id = ru.user_id
             WHERE ru.repair_id = ?
             ORDER BY ru.created_at ASC'
        );
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    public function create(int $jobId, string $note, ?int $userId, ?string $status = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO repair_updates (repair_id, user_id, status, note)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('iiss', $jobId, $userId, $status, $note);
        $stmt->execute();
        $stmt->close();
    }
}
