<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Supplier model -- lookup + full CRUD for the suppliers page.
 */
class Supplier extends Model
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->prepare(
            'SELECT supplier_id, name, contact_person, phone, email, address FROM suppliers ORDER BY name ASC'
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
     * Search suppliers by name, contact person, email or phone.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(?string $q): array
    {
        if ($q === null || $q === '') {
            return $this->all();
        }

        $like = '%' . $q . '%';
        $stmt = $this->db->prepare(
            'SELECT supplier_id, name, contact_person, phone, email, address FROM suppliers
             WHERE name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?
             ORDER BY name ASC'
        );
        $stmt->bind_param('ssss', $like, $like, $like, $like);
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
            'SELECT supplier_id, name, contact_person, phone, email, address FROM suppliers WHERE supplier_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * @param array{name:string,contact_person:?string,phone:?string,email:?string,address:?string} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sssss',
            $data['name'],
            $data['contact_person'],
            $data['phone'],
            $data['email'],
            $data['address']
        );
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * @param array{name:string,contact_person:?string,phone:?string,email:?string,address:?string} $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE supplier_id = ?'
        );
        $stmt->bind_param(
            'sssssi',
            $data['name'],
            $data['contact_person'],
            $data['phone'],
            $data['email'],
            $data['address'],
            $id
        );
        $stmt->execute();
        $stmt->close();
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM suppliers WHERE supplier_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}
