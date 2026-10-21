<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Coupon;
use DateTime;

class CouponController extends Controller
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
        $coupon = (new Coupon())->find((int) $id);
        if ($coupon === null) {
            $this->flash('error', 'Coupon not found.');
            $this->redirect('/admin/coupons');
            return;
        }
        $this->renderIndex($coupon, 'edit');
    }

    public function update(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        $this->store((int) $id);
    }

    public function delete(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        (new Coupon())->deleteById((int) $id);
        $this->flash('success', 'Coupon deleted.');
        $this->redirect('/admin/coupons');
    }

    /* ---------------------------------------------------------------- */

    private function store(?int $id): void
    {
        $editing = $id !== null;
        $model = new Coupon();

        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $discountType = $_POST['discount_type'] ?? 'percent';
        if (!in_array($discountType, ['percent', 'fixed'], true)) {
            $discountType = 'percent';
        }
        $valueRaw = (string) ($_POST['value'] ?? '');
        $validFrom = trim((string) ($_POST['valid_from'] ?? ''));
        $validTo = trim((string) ($_POST['valid_to'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        $errors = [];
        if ($code === '') {
            $errors[] = 'Coupon code is required.';
        }
        if ($model->codeExists($code, $id)) {
            $errors[] = 'This coupon code already exists.';
        }
        if (!is_numeric($valueRaw) || (float) $valueRaw <= 0) {
            $errors[] = 'A positive discount value is required.';
        }
        if ($discountType === 'percent' && (float) $valueRaw > 100) {
            $errors[] = 'Percent discount cannot exceed 100%.';
        }

        if ($errors) {
            $_SESSION['form_error'] = implode(' ', $errors);
            $_SESSION['form_old'] = [
                'code' => $code,
                'discount_type' => $discountType,
                'value' => $valueRaw,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'active' => $active,
            ];
            $this->redirect($editing ? '/admin/coupons/edit/' . $id : '/admin/coupons/create');
            return;
        }

        $data = [
            'code' => $code,
            'discount_type' => $discountType,
            'value' => (float) $valueRaw,
            'valid_from' => $validFrom !== '' ? $validFrom : null,
            'valid_to' => $validTo !== '' ? $validTo : null,
            'active' => $active,
        ];

        if ($editing) {
            $model->update($id, $data);
            $this->flash('success', 'Coupon updated.');
        } else {
            $model->create($data);
            $this->flash('success', 'Coupon created.');
        }

        $this->redirect('/admin/coupons');
    }

    /**
     * @param array<string, mixed>|null $editCoupon
     */
    private function renderIndex(?array $editCoupon, ?string $panelMode): void
    {
        $flash = $_SESSION['flash'] ?? null;
        $formOld = $_SESSION['form_old'] ?? null;
        $formError = $_SESSION['form_error'] ?? null;
        unset($_SESSION['flash'], $_SESSION['form_old'], $_SESSION['form_error']);

        $this->view('admin/coupons/index', [
            'user' => AuthMiddleware::user(),
            'active' => 'coupons',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'coupons' => (new Coupon())->all(),
            'editCoupon' => $editCoupon,
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
