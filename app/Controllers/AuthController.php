<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\PasswordReset;
use App\Models\User;

/**
 * Handles the login page, authentication and logout.
 */
class AuthController extends Controller
{
    public function showLogin(): void
    {
        // Already logged in? Go straight to the right dashboard.
        $current = AuthMiddleware::user();
        if ($current !== null) {
            $this->redirectByRole($current);
            return;
        }

        $error = $_SESSION['login_error'] ?? null;
        $old = $_SESSION['login_old'] ?? '';
        $success = $_SESSION['login_success'] ?? null;
        unset($_SESSION['login_error'], $_SESSION['login_old'], $_SESSION['login_success']);

        $this->view('auth/login', [
            'error' => $error,
            'success' => $success,
            'old' => $old,
        ]);
    }

    public function login(): void
    {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        if ($identifier === '' || $password === '') {
            $this->fail('Please enter your email/username and password.', $identifier);
            return;
        }

        $model = new User();
        $user = $model->findByEmail($identifier);

        if ($user === null || !$model->verifyPassword($password, (string) $user['password_hash'])) {
            $this->fail('Invalid credentials. Please check your details and try again.', $identifier);
            return;
        }

        // Prevent session fixation.
        session_regenerate_id(true);

        $model->updateLastLogin((int) $user['user_id']);

        $_SESSION['user'] = [
            'user_id' => (int) $user['user_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
        ];

        // "Remember me" -> extend the session cookie lifetime.
        if ($remember) {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $this->redirectByRole($_SESSION['user']);
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        $this->redirect('/login');
    }

    public function showForgotPassword(): void
    {
        $error = $_SESSION['auth_error'] ?? null;
        unset($_SESSION['auth_error']);

        $this->view('auth/forgot-password', ['error' => $error]);
    }

    public function forgotPassword(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['auth_error'] = 'Please enter a valid email address.';
            $this->redirect('/forgot-password');
            return;
        }

        $user = (new User())->findByEmail($email);
        if ($user === null) {
            $_SESSION['auth_error'] = 'No account found with that email address.';
            $this->redirect('/forgot-password');
            return;
        }

        $token = bin2hex(random_bytes(32));
        (new PasswordReset())->create((string) $user['email'], $token);

        // No mailer in this environment, so forward straight to the reset form.
        $this->redirect('/reset-password?token=' . urlencode($token));
    }

    public function showResetPassword(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $reset = $token !== '' ? (new PasswordReset())->findValid($token) : null;

        if ($reset === null) {
            $_SESSION['auth_error'] = 'This reset link is invalid or has expired.';
            $this->redirect('/forgot-password');
            return;
        }

        $this->view('auth/reset-password', [
            'token' => $token,
            'email' => $reset['email'],
            'error' => $_SESSION['auth_error'] ?? null,
        ]);
        unset($_SESSION['auth_error']);
    }

    public function resetPassword(): void
    {
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $reset = $token !== '' ? (new PasswordReset())->findValid($token) : null;
        if ($reset === null) {
            $_SESSION['auth_error'] = 'This reset link is invalid or has expired.';
            $this->redirect('/forgot-password');
            return;
        }

        if (strlen($password) < 8) {
            $_SESSION['auth_error'] = 'Password must be at least 8 characters.';
            $this->redirect('/reset-password?token=' . urlencode($token));
            return;
        }
        if ($password !== $confirm) {
            $_SESSION['auth_error'] = 'Passwords do not match.';
            $this->redirect('/reset-password?token=' . urlencode($token));
            return;
        }

        $user = (new User())->findByEmail($reset['email']);
        if ($user === null) {
            $_SESSION['auth_error'] = 'Account not found.';
            $this->redirect('/forgot-password');
            return;
        }

        (new User())->updatePassword((int) $user['user_id'], $password);
        (new PasswordReset())->delete($token);

        $_SESSION['login_success'] = 'Your password has been reset. You can now log in.';
        $this->redirect('/login');
    }

    /**
     * Store an error + the entered identifier, then bounce back to /login.
     */
    private function fail(string $message, string $identifier): void
    {
        $_SESSION['login_error'] = $message;
        $_SESSION['login_old'] = $identifier;
        $this->redirect('/login');
    }

    /**
     * @param array<string, mixed> $user
     */
    private function redirectByRole(array $user): void
    {
        switch ($user['user_type'] ?? '') {
            case 'admin':
                $this->redirect('/admin/dashboard');
                return;
            case 'sales_staff':
                $this->redirect('/sales/pos');
                return;
            case 'technician':
                $this->redirect('/technician/board');
                return;
            default:
                $this->redirect('/login');
        }
    }
}
