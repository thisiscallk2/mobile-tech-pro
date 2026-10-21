<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Payment extends Model
{
    /**
     * @param array{sale_id:int,payment_method:string,amount:float,amount_tendered:?float,change_due:?float,reference:?string} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (sale_id, payment_method, amount, amount_tendered, change_due, reference)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'isdids',
            $data['sale_id'],
            $data['payment_method'],
            $data['amount'],
            $data['amount_tendered'],
            $data['change_due'],
            $data['reference']
        );
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySale(int $saleId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT payment_id, sale_id, payment_method, amount, amount_tendered, change_due, reference, created_at
             FROM payments WHERE sale_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }
}
