<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * User model -- staff accounts and full CRUD for User Management.
 *
 * Role is stored in users.user_type ('admin'|'sales_staff'|'technician');
 * there are no separate role tables in this schema. Account state is the
 * users.active flag. All writes use mysqli prepared statements.
 */
class User extends Model
{
    /** Allowed role values (the user_type ENUM). */
    public const ROLES = ['admin', 'sales_staff', 'technician'];

    /* ---------------------------------------------------------------- */
    /*  Authentication                                                   */
    /* ---------------------------------------------------------------- */

    /**
     * Active user by email OR username (login accepts either).
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $identifier): ?array
    {
        $sql = 'SELECT user_id, username, email, password_hash, full_name, '
             . 'phone, user_type, active, permissions '
             . 'FROM users '
             . 'WHERE (email = ? OR username = ?) AND active = 1 '
             . 'LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Replace a user's password with a freshly hashed one.
     */
    public function updatePassword(int $id, string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $stmt->close();
    }

    /* ---------------------------------------------------------------- */
    /*  Reads                                                            */
    /* ---------------------------------------------------------------- */

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, username, email, full_name, phone, user_type, '
            . 'active, last_login_at, permissions, created_at '
            . 'FROM users WHERE user_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, username, full_name, user_type, active
             FROM users WHERE active = 1 ORDER BY full_name ASC'
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
     * Active users of a single role (e.g. technicians).
     *
     * @return array<int, array<string, mixed>>
     */
    public function byRole(string $role): array
    {
        if (!in_array($role, self::ROLES, true)) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT user_id, username, full_name, user_type, active
             FROM users WHERE user_type = ? AND active = 1 ORDER BY full_name ASC'
        );
        $stmt->bind_param('s', $role);
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
     * Filtered, paginated list of users.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allFiltered(?string $q, ?string $role, int $limit, int $offset): array
    {
        [$where, $types, $params] = $this->buildFilter($q, $role);

        $sql = 'SELECT user_id, username, email, full_name, user_type, active, last_login_at '
             . 'FROM users ' . $where . ' ORDER BY created_at DESC, user_id DESC LIMIT ? OFFSET ?';
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

    public function countFiltered(?string $q, ?string $role): int
    {
        [$where, $types, $params] = $this->buildFilter($q, $role);

        $sql = 'SELECT COUNT(*) AS c FROM users ' . $where;
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
     * @return array{total:int, active:int, inactive:int}
     */
    public function statusCounts(): array
    {
        $sql = 'SELECT '
             . 'COUNT(*) AS total, '
             . 'SUM(active = 1) AS active_count, '
             . 'SUM(active = 0) AS inactive_count '
             . 'FROM users';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active_count'] ?? 0),
            'inactive' => (int) ($row['inactive_count'] ?? 0),
        ];
    }

    /**
     * True when the email already belongs to a user (optionally excluding one).
     */
    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
            $stmt->bind_param('si', $email, $exceptId);
        } else {
            $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /* ---------------------------------------------------------------- */
    /*  Writes                                                           */
    /* ---------------------------------------------------------------- */

    /**
     * @param array{full_name:string,email:string,password:string,user_type:string,active:int,permissions:string,phone?:?string} $data
     */
    public function create(array $data): int
    {
        $username = $this->uniqueUsername($data['email']);
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $phone = $data['phone'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, full_name, phone, user_type, active, permissions) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssssis',
            $username,
            $data['email'],
            $hash,
            $data['full_name'],
            $phone,
            $data['user_type'],
            $data['active'],
            $data['permissions']
        );
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Update a user. When $data['password'] is a non-empty string the hash is
     * changed; otherwise the current password is kept.
     *
     * @param array{full_name:string,email:string,password:string,user_type:string,active:int,permissions:string} $data
     */
    public function update(int $id, array $data): void
    {
        if ($data['password'] !== '') {
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $this->db->prepare(
                'UPDATE users SET full_name = ?, email = ?, user_type = ?, active = ?, '
                . 'permissions = ?, password_hash = ? WHERE user_id = ?'
            );
            $stmt->bind_param(
                'sssissi',
                $data['full_name'],
                $data['email'],
                $data['user_type'],
                $data['active'],
                $data['permissions'],
                $hash,
                $id
            );
        } else {
            $stmt = $this->db->prepare(
                'UPDATE users SET full_name = ?, email = ?, user_type = ?, active = ?, '
                . 'permissions = ? WHERE user_id = ?'
            );
            $stmt->bind_param(
                'sssisi',
                $data['full_name'],
                $data['email'],
                $data['user_type'],
                $data['active'],
                $data['permissions'],
                $id
            );
        }
        $stmt->execute();
        $stmt->close();
    }

    public function setActive(int $id, int $active): void
    {
        $stmt = $this->db->prepare('UPDATE users SET active = ? WHERE user_id = ?');
        $stmt->bind_param('ii', $active, $id);
        $stmt->execute();
        $stmt->close();
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers                                                          */
    /* ---------------------------------------------------------------- */

    /**
     * Build a WHERE clause for search (name/email/username) and role filter.
     *
     * @return array{0:string, 1:string, 2:array<int, mixed>}
     */
    private function buildFilter(?string $q, ?string $role): array
    {
        $conditions = [];
        $types = '';
        $params = [];

        if ($q !== null && $q !== '') {
            $conditions[] = '(full_name LIKE ? OR email LIKE ? OR username LIKE ?)';
            $like = '%' . $q . '%';
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($role !== null && in_array($role, self::ROLES, true)) {
            $conditions[] = 'user_type = ?';
            $types .= 's';
            $params[] = $role;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $types, $params];
    }

    /**
     * Derive a unique username from an email local-part.
     */
    private function uniqueUsername(string $email): string
    {
        $base = strtolower((string) strstr($email, '@', true));
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?? '';
        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;
        $suffix = 1;
        while ($this->usernameExists($candidate)) {
            $suffix++;
            $candidate = $base . $suffix;
        }

        return $candidate;
    }

    private function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}
