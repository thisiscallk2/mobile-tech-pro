<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use DateTime;

/**
 * Admin User Management (admin-only). List + search/filter, plus create,
 * edit and activate/deactivate. The edit/create slide-over is
 * driven server-side (a GET to edit/create re-renders the list with the
 * panel open and pre-filled).
 */
class UserController extends Controller
{
    private const PER_PAGE = 8;

    /** Module permission definitions shown as checkboxes in the slide-over. */
    private const MODULES = [
        ['key' => 'sales', 'label' => 'Sales', 'desc' => 'POS & history'],
        ['key' => 'inventory', 'label' => 'Inventory', 'desc' => 'Stock & parts'],
        ['key' => 'repairs', 'label' => 'Repairs', 'desc' => 'Jobs & board'],
        ['key' => 'reports', 'label' => 'Reports', 'desc' => 'Analytics'],
    ];

    /** Default enabled modules per role (used for pre-checking checkboxes). */
    private const ROLE_DEFAULTS = [
        'admin' => ['sales', 'inventory', 'repairs', 'reports'],
        'sales_staff' => ['sales', 'inventory'],
        'technician' => ['repairs'],
    ];

    private const MODULE_KEYS = ['sales', 'inventory', 'repairs', 'reports'];

    public function index(): void
    {
        AuthMiddleware::requireRole('admin');
        $this->renderIndex(null, null);
    }

    public function create(): void
    {
        AuthMiddleware::requireRole('admin');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->store(null);
            return;
        }
        $this->renderIndex(null, 'create');
    }

    public function edit(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        $user = (new User())->find((int) $id);
        if ($user === null) {
            $this->flash('error', 'User not found.');
            $this->redirect('/admin/users');
            return;
        }
        $this->renderIndex($user, 'edit');
    }

    public function update(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        $this->store((int) $id);
    }

    public function toggleStatus(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        $uid = (int) $id;

        if ($uid === AuthMiddleware::id()) {
            $this->flash('error', 'You cannot change the status of your own account.');
            $this->redirect('/admin/users');
            return;
        }

        $model = new User();
        $user = $model->find($uid);
        if ($user === null) {
            $this->flash('error', 'User not found.');
            $this->redirect('/admin/users');
            return;
        }

        $newState = ((int) $user['active']) === 1 ? 0 : 1;
        $model->setActive($uid, $newState);
        $this->flash('success', $newState === 1 ? 'User activated.' : 'User deactivated.');
        $this->redirect('/admin/users');
    }

    /* ---------------------------------------------------------------- */

    /**
     * Validate and persist a create ($id === null) or update.
     */
    private function store(?int $id): void
    {
        $editing = $id !== null;
        $model = new User();

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['user_type'] ?? '');
        $active = !empty($_POST['active']) ? 1 : 0;

        // Only one admin is allowed: preserve the admin role when editing
        // the existing admin, and reject any attempt to assign it otherwise.
        $editingAdmin = false;
        if ($editing) {
            $existing = $model->find($id);
            if ($existing && ($existing['user_type'] ?? '') === 'admin') {
                $role = 'admin';
                $editingAdmin = true;
            }
        }

        $submittedPerms = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
        $perms = array_values(array_intersect(array_map('strval', $submittedPerms), self::MODULE_KEYS));
        $permString = implode(',', $perms);

        $errors = [];
        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (!in_array($role, User::ROLES, true)) {
            $errors[] = 'Please choose a role.';
        }
        if ($role === 'admin' && !$editingAdmin) {
            $errors[] = 'The admin role cannot be assigned.';
        }
        if (!$editing && $password === '') {
            $errors[] = 'A password is required for new users.';
        }
        if ($email !== '' && $model->emailExists($email, $editing ? $id : null)) {
            $errors[] = 'That email address is already in use.';
        }

        if ($errors) {
            $_SESSION['form_error'] = implode(' ', $errors);
            $_SESSION['form_old'] = [
                'full_name' => $fullName,
                'email' => $email,
                'user_type' => $role,
                'active' => $active,
                'permissions' => $perms,
            ];
            $this->redirect($editing ? '/admin/users/edit/' . $id : '/admin/users/create');
            return;
        }

        if ($editing) {
            $model->update($id, [
                'full_name' => $fullName,
                'email' => $email,
                'password' => $password,
                'user_type' => $role,
                'active' => $active,
                'permissions' => $permString,
            ]);
            $this->flash('success', 'User updated successfully.');
        } else {
            $model->create([
                'full_name' => $fullName,
                'email' => $email,
                'password' => $password,
                'user_type' => $role,
                'active' => $active,
                'permissions' => $permString,
                'phone' => null,
            ]);
            $this->flash('success', 'User created successfully.');
        }

        $this->redirect('/admin/users');
    }

    /**
     * Render the list, optionally with the slide-over panel open.
     *
     * @param array<string, mixed>|null $editUser
     */
    private function renderIndex(?array $editUser, ?string $panelMode): void
    {
        $model = new User();

        $q = trim((string) ($_GET['q'] ?? ''));
        $role = (string) ($_GET['role'] ?? '');
        $role = in_array($role, User::ROLES, true) ? $role : '';

        $total = $model->countFiltered($q !== '' ? $q : null, $role !== '' ? $role : null);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $users = $model->allFiltered(
            $q !== '' ? $q : null,
            $role !== '' ? $role : null,
            self::PER_PAGE,
            $offset
        );

        $counts = $model->statusCounts();

        $flash = $_SESSION['flash'] ?? null;
        $formOld = $_SESSION['form_old'] ?? null;
        $formError = $_SESSION['form_error'] ?? null;
        unset($_SESSION['flash'], $_SESSION['form_old'], $_SESSION['form_error']);

        $this->view('admin/users/index', [
            'user' => AuthMiddleware::user(),
            'active' => 'users',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'users' => $users,
            'counts' => $counts,
            'q' => $q,
            'role' => $role,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'shown' => count($users),
            'perPage' => self::PER_PAGE,
            'modules' => self::MODULES,
            'roleDefaults' => self::ROLE_DEFAULTS,
            'editUser' => $editUser,
            'panelMode' => $panelMode,
            'flash' => $flash,
            'formOld' => $formOld,
            'formError' => $formError,
        ]);
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}
