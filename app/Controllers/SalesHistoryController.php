<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Sale;
use App\Models\User;
use DateTime;

class SalesHistoryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');

        $isSalesStaff = ($user['user_type'] ?? '') === 'sales_staff';

        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $userId = ($_GET['user_id'] ?? '') !== '' ? (int) $_GET['user_id'] : null;

        // Sales staff only see their own sales; admins can filter by cashier.
        if ($isSalesStaff) {
            $userId = (int) ($user['user_id'] ?? 0);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $saleModel = new Sale();
        $sales = $saleModel->allHistory($from !== '' ? $from : null, $to !== '' ? $to : null, $userId, self::PER_PAGE, $offset);
        $totalCount = $saleModel->countHistory($from !== '' ? $from : null, $to !== '' ? $to : null, $userId);
        $totalPages = (int) max(1, ceil($totalCount / self::PER_PAGE));

        $staff = (new User())->byRole('sales_staff');

        $this->view('sales/history/index', [
            'user' => $user,
            'active' => 'sales-history',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'sales' => $sales,
            'staff' => $staff,
            'from' => $from,
            'to' => $to,
            'userId' => $userId,
            'page' => $page,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'hideStaffFilter' => $isSalesStaff,
        ]);
    }

    public function show(string $saleId): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');
        $sale = (new Sale())->findWithItems((int) $saleId);

        if ($sale === null) {
            $this->redirect('/sales/history');
            return;
        }

        $this->view('sales/history/view', [
            'user' => $user,
            'active' => 'sales-history',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'sale' => $sale,
        ]);
    }
}
