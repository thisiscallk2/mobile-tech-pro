<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Customer extends Model
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->prepare(
            'SELECT customer_id, full_name, email, phone, address, notes, created_at, updated_at
             FROM customers ORDER BY full_name ASC'
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
    public function allFiltered(?string $q, int $limit, int $offset): array
    {
        [$where, $types, $params] = $this->buildFilter($q);
        $sql = 'SELECT customer_id, full_name, email, phone, address, notes, created_at, updated_at
                FROM customers ' . $where . ' ORDER BY full_name ASC LIMIT ? OFFSET ?';
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

    public function countFiltered(?string $q): int
    {
        [$where, $types, $params] = $this->buildFilter($q);
        $sql = 'SELECT COUNT(*) AS c FROM customers ' . $where;
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
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT customer_id, full_name, email, phone, address, notes, created_at, updated_at
             FROM customers WHERE customer_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>  Matches for POS lookup (phone or email)
     */
    public function search(string $query): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->db->prepare(
            'SELECT customer_id, full_name, email, phone
             FROM customers
             WHERE full_name LIKE ? OR phone LIKE ? OR email LIKE ?
             ORDER BY full_name ASC LIMIT 8'
        );
        $stmt->bind_param('sss', $like, $like, $like);
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
     * @param array{full_name:string,email:?string,phone:?string,address:?string,notes:?string} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO customers (full_name, email, phone, address, notes) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sssss',
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['notes']
        );
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * @param array{full_name:string,email:?string,phone:?string,address:?string,notes:?string} $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE customers SET full_name = ?, email = ?, phone = ?, address = ?, notes = ? WHERE customer_id = ?'
        );
        $stmt->bind_param(
            'sssssi',
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['notes'],
            $id
        );
        $stmt->execute();
        $stmt->close();
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM customers WHERE customer_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $stmt = $this->db->prepare('SELECT 1 FROM customers WHERE email = ? AND customer_id <> ? LIMIT 1');
            $stmt->bind_param('si', $email, $exceptId);
        } else {
            $stmt = $this->db->prepare('SELECT 1 FROM customers WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithHistory(int $id): ?array
    {
        $customer = $this->find($id);
        if ($customer === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT s.sale_id, s.invoice_no, s.total, s.payment_method, s.created_at
             FROM sales s WHERE s.customer_id = ? ORDER BY s.created_at DESC LIMIT 50'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $purchases = [];
        while ($row = $result->fetch_assoc()) {
            $purchases[] = $row;
        }
        $stmt->close();

        $stmt = $this->db->prepare(
            'SELECT rj.repair_id, rj.ticket_no, rj.device_brand, rj.device_model, rj.status, rj.created_at
             FROM repair_jobs rj WHERE rj.customer_id = ? ORDER BY rj.created_at DESC LIMIT 50'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $repairs = [];
        while ($row = $result->fetch_assoc()) {
            $repairs[] = $row;
        }
        $stmt->close();

        $customer['purchases'] = $purchases;
        $customer['repairs'] = $repairs;

        return $customer;
    }

    /**
     * @return array{0:string, 1:string, 2:array<int, mixed>}
     */
    private function buildFilter(?string $q): array
    {
        $types = '';
        $params = [];

        if ($q !== null && $q !== '') {
            $like = '%' . $q . '%';
            $where = 'WHERE (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)';
            $types = 'sss';
            $params = [$like, $like, $like];
        } else {
            $where = '';
        }

        return [$where, $types, $params];
    }
}
