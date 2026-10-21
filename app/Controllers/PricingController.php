<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use DateTime;

class PricingController extends Controller
{
    private const PER_PAGE = 10;

    public function index(): void
    {
        $user = AuthMiddleware::requireRole('admin');

        $q = trim((string) ($_GET['q'] ?? ''));
        $brandId = ($_GET['brand'] ?? '') !== '' ? (int) $_GET['brand'] : null;

        $productModel = new Product();

        $total = $productModel->countFiltered($q !== '' ? $q : null, null);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $products = $productModel->allFiltered($q !== '' ? $q : null, null, self::PER_PAGE, $offset);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $now = new DateTime('now');

        $this->view('admin/pricing/index', [
            'user' => $user,
            'active' => 'pricing',
            'today' => $now->format('M j, Y'),
            'products' => $products,
            'categories' => (new Category())->all(),
            'brands' => (new Brand())->all(),
            'q' => $q,
            'brandId' => $brandId,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'shown' => count($products),
            'flash' => $flash,
        ]);
    }

    public function updatePrice(): void
    {
        AuthMiddleware::requireRole('admin');

        $id = (int) ($_POST['product_id'] ?? 0);
        $costRaw = (string) ($_POST['cost_price'] ?? '');
        $sellingRaw = (string) ($_POST['selling_price'] ?? '');

        $errors = [];
        if ($id <= 0) {
            $errors[] = 'Invalid product.';
        }
        if (!is_numeric($costRaw) || (float) $costRaw < 0) {
            $errors[] = 'A valid cost price is required.';
        }
        if (!is_numeric($sellingRaw) || (float) $sellingRaw < 0) {
            $errors[] = 'A valid selling price is required.';
        }

        if ($errors) {
            $this->flash('error', implode(' ', $errors));
        } else {
            $model = new Product();
            $model->updatePricing($id, (float) $costRaw, (float) $sellingRaw);
            $this->flash('success', 'Price updated.');
        }

        $this->redirect('/admin/pricing');
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}
