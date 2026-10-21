<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Coupon extends Model
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->prepare(
            'SELECT coupon_id, code, discount_type, value, valid_from, valid_to, active, created_at
             FROM coupons
             ORDER BY created_at DESC'
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
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT coupon_id, code, discount_type, value, valid_from, valid_to, active
             FROM coupons WHERE coupon_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Find an active, valid coupon by its code.
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare(
            'SELECT coupon_id, code, discount_type, value, valid_from, valid_to, active
             FROM coupons
             WHERE code = ? AND active = 1
               AND (valid_from IS NULL OR valid_from <= ?)
               AND (valid_to IS NULL OR valid_to >= ?)
             LIMIT 1'
        );
        $stmt->bind_param('sss', $code, $today, $today);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * @param array{code:string,discount_type:string,value:float,valid_from:?string,valid_to:?string,active:int} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO coupons (code, discount_type, value, valid_from, valid_to, active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssdssi',
            $data['code'],
            $data['discount_type'],
            $data['value'],
            $data['valid_from'],
            $data['valid_to'],
            $data['active']
        );
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * @param array{code:string,discount_type:string,value:float,valid_from:?string,valid_to:?string,active:int} $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE coupons SET code = ?, discount_type = ?, value = ?, valid_from = ?, valid_to = ?, active = ?
             WHERE coupon_id = ?'
        );
        $stmt->bind_param(
            'ssdssii',
            $data['code'],
            $data['discount_type'],
            $data['value'],
            $data['valid_from'],
            $data['valid_to'],
            $data['active'],
            $id
        );
        $stmt->execute();
        $stmt->close();
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM coupons WHERE coupon_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $stmt = $this->db->prepare('SELECT 1 FROM coupons WHERE code = ? AND coupon_id <> ? LIMIT 1');
            $stmt->bind_param('si', $code, $exceptId);
        } else {
            $stmt = $this->db->prepare('SELECT 1 FROM coupons WHERE code = ? LIMIT 1');
            $stmt->bind_param('s', $code);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}
