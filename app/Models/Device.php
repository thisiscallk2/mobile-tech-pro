<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use RuntimeException;

class Device extends Model
{
    /**
     * @param array{device_brand:string,device_model:string,imei:?string,reported_issue:string,estimated_cost:float,due_date:?string,urgency:string,technician_id:?int,customer_id:?int,customer_name:?string,customer_phone:?string,customer_email:?string} $data
     * @return int repair_id
     */
    public function create(array $data): int
    {
        $customerId = $this->resolveCustomer($data);

        $ticketNo = $this->generateTicketNo();

        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO repair_jobs (ticket_no, customer_id, technician_id, device_brand, device_model, imei, urgency, reported_issue, estimated_cost, due_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $status = 'received';
            $stmt->bind_param(
                'siisssssdss',
                $ticketNo,
                $customerId,
                $data['technician_id'],
                $data['device_brand'],
                $data['device_model'],
                $data['imei'],
                $data['urgency'],
                $data['reported_issue'],
                $data['estimated_cost'],
                $data['due_date'],
                $status
            );

            if (!$stmt->execute()) {
                throw new RuntimeException('Failed to create repair job: ' . $stmt->error);
            }
            $repairId = (int) $stmt->insert_id;
            $stmt->close();

            $upd = $this->db->prepare(
                'INSERT INTO repair_updates (repair_id, status, note) VALUES (?, ?, ?)'
            );
            $note = 'Job created.';
            $upd->bind_param('iss', $repairId, $status, $note);
            $upd->execute();
            $upd->close();

            $this->db->commit();

            return $repairId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function resolveCustomer(array $data): ?int
    {
        if (!empty($data['customer_id']) && (int) $data['customer_id'] > 0) {
            return (int) $data['customer_id'];
        }

        $name = trim((string) ($data['customer_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $phone = trim((string) ($data['customer_phone'] ?? ''));
        $email = trim((string) ($data['customer_email'] ?? ''));

        $customerModel = new Customer();

        if ($phone !== '') {
            $existing = $customerModel->search($phone);
            foreach ($existing as $c) {
                if ($c['phone'] === $phone) {
                    return (int) $c['customer_id'];
                }
            }
        }

        if ($email !== '') {
            $existing = $customerModel->search($email);
            foreach ($existing as $c) {
                if ($c['email'] === $email) {
                    return (int) $c['customer_id'];
                }
            }
        }

        return $customerModel->create([
            'full_name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'address' => null,
            'notes' => null,
        ]);
    }

    private function generateTicketNo(): string
    {
        do {
            $no = 'JOB-' . date('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->ticketExists($no));

        return $no;
    }

    private function ticketExists(string $ticketNo): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM repair_jobs WHERE ticket_no = ? LIMIT 1');
        $stmt->bind_param('s', $ticketNo);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}
