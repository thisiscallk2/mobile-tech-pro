<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use DateTime;
use finfo;

/**
 * Admin Inventory Management (admin-only). Product list with search/category
 * filter, low-stock banner, summary cards, and an Add/Edit Product slide-over
 * with a validated image upload.
 */
class InventoryController extends Controller
{
    private const PER_PAGE = 8;
    private const MAX_UPLOAD = 5 * 1024 * 1024; // 5MB

    /** Allowed image MIME types mapped to canonical extensions. */
    private const ALLOWED_IMAGES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

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
        $product = (new Product())->find((int) $id);
        if ($product === null) {
            $this->flash('error', 'Product not found.');
            $this->redirect('/admin/inventory');
            return;
        }
        $this->renderIndex($product, 'edit');
    }

    public function update(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        $this->store((int) $id);
    }

    public function delete(string $id): void
    {
        AuthMiddleware::requireRole('admin');
        (new Product())->deleteById((int) $id);
        $this->flash('success', 'Product deleted.');
        $this->redirect('/admin/inventory');
    }

    /* ---------------------------------------------------------------- */

    private function store(?int $id): void
    {
        $editing = $id !== null;
        $model = new Product();

        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null;
        $brandId = ($_POST['brand_id'] ?? '') !== '' ? (int) $_POST['brand_id'] : null;
        $supplierId = ($_POST['supplier_id'] ?? '') !== '' ? (int) $_POST['supplier_id'] : null;
        $priceRaw = (string) ($_POST['selling_price'] ?? '');
        $qtyRaw = (string) ($_POST['quantity'] ?? '');
        $reorderRaw = (string) ($_POST['reorder_level'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors[] = 'Product name is required.';
        }
        if (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
            $errors[] = 'A valid price is required.';
        }
        if ($qtyRaw === '' || !ctype_digit($qtyRaw)) {
            $errors[] = 'Stock quantity must be a whole number.';
        }
        if ($reorderRaw !== '' && !ctype_digit($reorderRaw)) {
            $errors[] = 'Low stock threshold must be a whole number.';
        }

        $upload = $this->handleUpload();
        if ($upload['error'] !== null) {
            $errors[] = $upload['error'];
        }

        if ($errors) {
            $_SESSION['form_error'] = implode(' ', $errors);
            $_SESSION['form_old'] = [
                'name' => $name,
                'description' => $description,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'supplier_id' => $supplierId,
                'selling_price' => $priceRaw,
                'quantity' => $qtyRaw,
                'reorder_level' => $reorderRaw,
            ];
            $this->redirect($editing ? '/admin/inventory/edit/' . $id : '/admin/inventory/create');
            return;
        }

        $price = (float) $priceRaw;
        $quantity = (int) $qtyRaw;
        $reorder = $reorderRaw === '' ? 5 : (int) $reorderRaw;

        if ($editing) {
            $ok = $model->update($id, [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'supplier_id' => $supplierId,
                'selling_price' => $price,
                'quantity' => $quantity,
                'reorder_level' => $reorder,
                'image_path' => $upload['path'],
            ]);
            $this->flash($ok ? 'success' : 'error', $ok ? 'Product updated.' : 'Could not update product.');
        } else {
            $newId = $model->create([
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'supplier_id' => $supplierId,
                'cost_price' => 0.0,
                'selling_price' => $price,
                'image_path' => $upload['path'],
                'quantity' => $quantity,
                'reorder_level' => $reorder,
                'location' => null,
            ]);
            $this->flash($newId > 0 ? 'success' : 'error', $newId > 0 ? 'Product added to inventory.' : 'Could not add product.');
        }

        $this->redirect('/admin/inventory');
    }

    /**
     * Validate and store an uploaded product image.
     * Type is detected from file contents (never trusted from the extension).
     *
     * @return array{path:?string, error:?string}
     */
    private function handleUpload(): array
    {
        if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
            return ['path' => null, 'error' => null];
        }

        $file = $_FILES['image'];
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Image upload failed. Please try again.'];
        }
        if (($file['size'] ?? 0) > self::MAX_UPLOAD) {
            return ['path' => null, 'error' => 'Image is larger than the 5MB limit.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['path' => null, 'error' => 'Invalid upload.'];
        }

        // Detect the real MIME type from the file contents.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);

        if (!isset(self::ALLOWED_IMAGES[$mime])) {
            return ['path' => null, 'error' => 'Only PNG, JPG or WEBP images are allowed.'];
        }
        // Double-check it is really a decodable image.
        if (getimagesize($file['tmp_name']) === false) {
            return ['path' => null, 'error' => 'The uploaded file is not a valid image.'];
        }

        $ext = self::ALLOWED_IMAGES[$mime];
        $dir = APP_ROOT . '/public/uploads/products';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['path' => null, 'error' => 'Could not save the uploaded image.'];
        }

        return ['path' => 'uploads/products/' . $filename, 'error' => null];
    }

    /**
     * @param array<string, mixed>|null $editProduct
     */
    private function renderIndex(?array $editProduct, ?string $panelMode): void
    {
        $model = new Product();

        $q = trim((string) ($_GET['q'] ?? ''));
        $categoryId = ($_GET['category'] ?? '') !== '' ? (int) $_GET['category'] : null;

        $total = $model->countFiltered($q !== '' ? $q : null, $categoryId);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $products = $model->allFiltered($q !== '' ? $q : null, $categoryId, self::PER_PAGE, $offset);

        $flash = $_SESSION['flash'] ?? null;
        $formOld = $_SESSION['form_old'] ?? null;
        $formError = $_SESSION['form_error'] ?? null;
        unset($_SESSION['flash'], $_SESSION['form_old'], $_SESSION['form_error']);

        $this->view('admin/inventory/index', [
            'user' => AuthMiddleware::user(),
            'active' => 'inventory',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'products' => $products,
            'counts' => $model->summaryCounts(),
            'lowStockNames' => $model->lowStockNames(),
            'categories' => (new Category())->all(),
            'brands' => (new Brand())->all(),
            'suppliers' => (new Supplier())->all(),
            'q' => $q,
            'categoryId' => $categoryId,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'shown' => count($products),
            'editProduct' => $editProduct,
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
