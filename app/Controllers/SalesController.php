<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;

/**
 * Sales / POS area. Placeholder for now, protected by role server-side.
 */
class SalesController extends Controller
{
    public function pos(): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');

        $this->view('placeholder', [
            'user' => $user,
            'title' => 'Point of Sale',
            'round' => 'a later round',
        ]);
    }
}
