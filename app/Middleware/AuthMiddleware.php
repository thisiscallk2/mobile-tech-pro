<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Authentication / authorisation gate.
 *
 * - check()        : require any logged-in user (else redirect to /login)
 * - requireRole()  : require a logged-in user with one of the given roles
 * - user()/id()    : read the current session user
 * - hasRole()      : role-check helper for views and later rounds
 */
class AuthMiddleware
{
    /**
     * Ensure a user is authenticated. Redirects to /login if not.
     *
     * @return array<string, mixed> the session user
     */
    public static function check(): array
    {
        if (empty($_SESSION['user'])) {
            header('Location: ' . base_url('/login'));
            exit;
        }
        return $_SESSION['user'];
    }

    /**
     * Ensure the authenticated user holds one of the allowed roles.
     * Unauthenticated users are redirected to /login; wrong role gets 403.
     *
     * @return array<string, mixed> the session user
     */
    public static function requireRole(string ...$roles): array
    {
        $user = self::check();

        if (!in_array($user['user_type'] ?? '', $roles, true)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
                . '<title>403 Forbidden</title></head><body>'
                . '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>'
                . '<p><a href="' . base_url('/logout') . '">Log out</a></p>'
                . '</body></html>';
            exit;
        }

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['user_id'])
            ? (int) $_SESSION['user']['user_id']
            : null;
    }

    public static function hasRole(string $role): bool
    {
        return (($_SESSION['user']['user_type'] ?? null) === $role);
    }
}
