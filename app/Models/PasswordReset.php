<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PasswordReset extends Model
{
    /**
     * Store a reset token for an email, replacing any previous token.
     * Expiry is computed in SQL (NOW() + 1 hour) to avoid PHP/MySQL
     * timezone mismatches.
     */
    public function create(string $email, string $token): void
    {
        $stmt = $this->db->prepare('DELETE FROM password_resets WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->close();

        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (email, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        );
        $stmt->bind_param('ss', $email, $token);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Email for a token that exists and has not expired.
     *
     * @return array{email:string}|null
     */
    public function findValid(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public function delete(string $token): void
    {
        $stmt = $this->db->prepare('DELETE FROM password_resets WHERE token = ?');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
    }
}
