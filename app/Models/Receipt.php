<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Receipt extends Model
{
    /**
     * @param array{sale_id:int,receipt_number:string} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO receipts (sale_id, receipt_number) VALUES (?, ?)'
        );
        $stmt->bind_param('is', $data['sale_id'], $data['receipt_number']);
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
            'SELECT receipt_id, sale_id, receipt_number, printed_at, created_at
             FROM receipts WHERE sale_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumber(string $receiptNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT receipt_id, sale_id, receipt_number, printed_at, created_at
             FROM receipts WHERE receipt_number = ? LIMIT 1'
        );
        $stmt->bind_param('s', $receiptNumber);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public function markPrinted(int $receiptId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE receipts SET printed_at = NOW() WHERE receipt_id = ?'
        );
        $stmt->bind_param('i', $receiptId);
        $stmt->execute();
        $stmt->close();
    }
}
