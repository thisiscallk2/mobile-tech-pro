<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Supplier;
use DateTime;

/**
 * Admin Suppliers (admin-only). Basic CRUD table.
 */
class SupplierController extends Controller
{
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
        $supplier = (new Supplier())->find((int) $id);
        if ($supplier === null) {
            $this->flash('error', 'Supplier not found.');
            $this->redirect('/admin/suppliers');
            return;
        }
        $this->renderIndex($supplier, 'edit');
    }

    public function update(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        $this->store((int) $id);
    }

    public function delete(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        (new Supplier())->deleteById((int) $id);
        $this->flash('success', 'Supplier deleted.');
        $this->redirect('/admin/suppliers');
    }

    /* ---------------------------------------------------------------- */

    private function store(?int $id): void
    {
        $editing = $id !== null;
        $model = new Supplier();

        $name = trim((string) ($_POST['name'] ?? ''));
        $contact = trim((string) ($_POST['contact_person'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors[] = 'Supplier name is required.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($errors) {
            $_SESSION['form_error'] = implode(' ', $errors);
            $_SESSION['form_old'] = [
                'name' => $name,
                'contact_person' => $contact,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
            ];
            $this->redirect($editing ? '/admin/suppliers/edit/' . $id : '/admin/suppliers/create');
            return;
        }

        $data = [
            'name' => $name,
            'contact_person' => $contact !== '' ? $contact : null,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'address' => $address !== '' ? $address : null,
        ];

        if ($editing) {
            $model->update($id, $data);
            $this->flash('success', 'Supplier updated.');
        } else {
            $model->create($data);
            $this->flash('success', 'Supplier added.');
        }

        $this->redirect('/admin/suppliers');
    }

    /**
     * @param array<string, mixed>|null $editSupplier
     */
    private function renderIndex(?array $editSupplier, ?string $panelMode): void
    {
        $flash = $_SESSION['flash'] ?? null;
        $formOld = $_SESSION['form_old'] ?? null;
        $formError = $_SESSION['form_error'] ?? null;
        unset($_SESSION['flash'], $_SESSION['form_old'], $_SESSION['form_error']);

        $q = trim((string) ($_GET['q'] ?? ''));

        $this->view('admin/suppliers/index', [
            'user' => AuthMiddleware::user(),
            'active' => 'suppliers',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'suppliers' => (new Supplier())->search($q !== '' ? $q : null),
            'q' => $q,
            'editSupplier' => $editSupplier,
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
