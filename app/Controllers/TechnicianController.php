<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;

/**
 * Technician repair board. Placeholder for now, protected by role server-side.
 */
class TechnicianController extends Controller
{
    public function board(): void
    {
        $user = AuthMiddleware::requireRole('technician', 'admin');

        $this->view('placeholder', [
            'user' => $user,
            'title' => 'Repair Board',
            'round' => 'a later round',
        ]);
    }
}
