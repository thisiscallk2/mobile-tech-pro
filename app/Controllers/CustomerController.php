<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Customer;
use DateTime;

class CustomerController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');

        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $customerModel = new Customer();
        $customers = $customerModel->allFiltered($q !== '' ? $q : null, self::PER_PAGE, $offset);
        $totalCount = $customerModel->countFiltered($q !== '' ? $q : null);
        $totalPages = (int) max(1, ceil($totalCount / self::PER_PAGE));

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $this->view('sales/customers/index', [
            'user' => $user,
            'active' => 'customers',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'customers' => $customers,
            'q' => $q,
            'page' => $page,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'flash' => $flash,
            'panelOpen' => false,
            'editCustomer' => null,
            'panelMode' => null,
            'formOld' => [],
            'formError' => null,
        ]);
    }

    public function create(): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $this->validateInput();
            if ($data === null) {
                $this->renderIndex(null, 'create');
                return;
            }
            (new Customer())->create($data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Customer created.'];
            $this->redirect('/sales/customers');
            return;
        }

        $this->renderIndex(null, 'create');
    }

    public function edit(string $id): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');
        $customer = (new Customer())->find((int) $id);

        if ($customer === null) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Customer not found.'];
            $this->redirect('/sales/customers');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $this->validateInput();
            if ($data === null) {
                $this->renderIndex($customer, 'edit');
                return;
            }
            (new Customer())->update((int) $id, $data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Customer updated.'];
            $this->redirect('/sales/customers');
            return;
        }

        $this->renderIndex($customer, 'edit');
    }

    public function update(string $id): void
    {
        $this->edit($id);
    }

    public function delete(string $id): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');
        (new Customer())->deleteById((int) $id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Customer deleted.'];
        $this->redirect('/sales/customers');
    }

    public function show(string $id): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');
        $customer = (new Customer())->findWithHistory((int) $id);

        if ($customer === null) {
            $this->redirect('/sales/customers');
            return;
        }

        $this->view('sales/customers/view', [
            'user' => $user,
            'active' => 'customers',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'customer' => $customer,
        ]);
    }

    public function search(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $q = trim((string) ($_GET['q'] ?? ''));
        $results = [];
        if ($q !== '') {
            $results = (new Customer())->search($q);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['customers' => $results], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function quickCreate(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);

        $fullName = trim((string) ($input['full_name'] ?? ''));
        if ($fullName === '') {
            $this->jsonError('Full name is required.');
            return;
        }

        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));

        $customerId = (new Customer())->create([
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'address' => null,
            'notes' => null,
        ]);

        $_SESSION['pos_customer'] = [
            'customer_id' => $customerId,
            'full_name' => $fullName,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'customer' => $_SESSION['pos_customer'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function setCustomer(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $customerId = (int) ($input['customer_id'] ?? 0);

        if ($customerId <= 0) {
            unset($_SESSION['pos_customer']);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'customer' => null], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $customer = (new Customer())->find($customerId);
        if ($customer === null) {
            $this->jsonError('Customer not found.');
            return;
        }

        $_SESSION['pos_customer'] = [
            'customer_id' => $customerId,
            'full_name' => $customer['full_name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'customer' => $_SESSION['pos_customer'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function renderIndex(?array $editCustomer, ?string $panelMode): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');

        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $customerModel = new Customer();
        $customers = $customerModel->allFiltered($q !== '' ? $q : null, self::PER_PAGE, $offset);
        $totalCount = $customerModel->countFiltered($q !== '' ? $q : null);
        $totalPages = (int) max(1, ceil($totalCount / self::PER_PAGE));

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $formOld = $_SESSION['form_old'] ?? [];
        $formError = $_SESSION['form_error'] ?? null;
        unset($_SESSION['form_old'], $_SESSION['form_error']);

        $this->view('sales/customers/index', [
            'user' => $user,
            'active' => 'customers',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'customers' => $customers,
            'q' => $q,
            'page' => $page,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'flash' => $flash,
            'panelOpen' => $panelMode !== null,
            'editCustomer' => $editCustomer,
            'panelMode' => $panelMode,
            'formOld' => $formOld,
            'formError' => $formError,
        ]);
    }

    /**
     * @return array{full_name:string,email:?string,phone:?string,address:?string,notes:?string}|null
     */
    private function validateInput(): ?array
    {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        if ($fullName === '') {
            $_SESSION['form_error'] = 'Full name is required.';
            $_SESSION['form_old'] = $_POST;
            return null;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['form_error'] = 'A valid email address is required.';
            $_SESSION['form_old'] = $_POST;
            return null;
        }

        return [
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'address' => trim((string) ($_POST['address'] ?? '')) ?: null,
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ];
    }

    private function jsonError(string $message): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
