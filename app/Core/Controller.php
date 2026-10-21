<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller: view rendering and redirect helpers shared by all
 * controllers.
 */
abstract class Controller
{
    /**
     * Render a view from app/Views. $data keys become local variables.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $path, array $data = []): void
    {
        $file = APP_ROOT . '/app/Views/' . $path . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            exit('View not found: ' . e($path));
        }

        extract($data, EXTR_SKIP);
        require $file;
    }

    /**
     * Redirect to an internal route (prefixed with BASE_URL) and stop.
     */
    protected function redirect(string $to): void
    {
        header('Location: ' . base_url($to));
        exit;
    }
}
