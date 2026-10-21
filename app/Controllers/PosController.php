<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Sale;
use DateTime;
use Throwable;

class PosController extends Controller
{
    private const TAX_RATE = 0.10;

    public function pos(): void
    {
        $user = AuthMiddleware::requireRole('sales_staff', 'admin');

        $q = trim((string) ($_GET['q'] ?? ''));
        $categoryId = ($_GET['category'] ?? '') !== '' ? (int) $_GET['category'] : null;

        $productModel = new Product();
        $products = $productModel->allFiltered($q !== '' ? $q : null, $categoryId, 50, 0);

        if (!isset($_SESSION['pos_cart'])) {
            $_SESSION['pos_cart'] = [];
        }

        $coupon = $_SESSION['pos_coupon'] ?? null;
        $discount = $_SESSION['pos_discount'] ?? 0.0;

        $this->view('sales/pos/index', [
            'user' => $user,
            'active' => 'pos',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'products' => $products,
            'categories' => (new Category())->all(),
            'categoryId' => $categoryId,
            'q' => $q,
            'cart' => $_SESSION['pos_cart'],
            'subtotal' => $this->calcSubtotal($_SESSION['pos_cart']),
            'coupon' => $coupon,
            'discount' => $discount,
        ]);
    }

    public function searchProducts(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $q = trim((string) ($_GET['q'] ?? ''));
        $categoryId = ($_GET['category'] ?? '') !== '' ? (int) $_GET['category'] : null;

        $productModel = new Product();
        $products = $productModel->allFiltered($q !== '' ? $q : null, $categoryId, 50, 0);

        $result = [];
        foreach ($products as $p) {
            $result[] = [
                'product_id' => (int) $p['product_id'],
                'name' => $p['name'],
                'sku' => $p['sku'],
                'selling_price' => (float) $p['selling_price'],
                'image_path' => $p['image_path'],
                'category_name' => $p['category_name'],
                'quantity' => (int) ($p['quantity'] ?? 0),
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['products' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function cartAdd(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $productId = (int) ($input['product_id'] ?? 0);

        if ($productId <= 0) {
            $this->jsonError('Invalid product.');
            return;
        }

        $product = (new Product())->find($productId);
        if ($product === null) {
            $this->jsonError('Product not found.');
            return;
        }

        $stock = (int) ($product['quantity'] ?? 0);
        if ($stock <= 0) {
            $this->jsonError('This product is out of stock.');
            return;
        }

        if (!isset($_SESSION['pos_cart'])) {
            $_SESSION['pos_cart'] = [];
        }

        $found = false;
        foreach ($_SESSION['pos_cart'] as &$item) {
            if ((int) $item['product_id'] === $productId) {
                $newQty = $item['quantity'] + 1;
                if ($newQty > $stock) {
                    $this->jsonError('Only ' . $stock . ' in stock (cart has ' . $item['quantity'] . ').');
                    return;
                }
                $item['quantity'] = $newQty;
                $item['max_stock'] = $stock;
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $_SESSION['pos_cart'][] = [
                'product_id' => $productId,
                'name' => $product['name'],
                'sku' => $product['sku'],
                'selling_price' => (float) $product['selling_price'],
                'image_path' => $product['image_path'],
                'quantity' => 1,
                'max_stock' => $stock,
            ];
        }

        $this->jsonCart();
    }

    public function cartUpdate(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $productId = (int) ($input['product_id'] ?? 0);
        $qty = (int) ($input['quantity'] ?? 1);

        if ($productId <= 0 || $qty < 1) {
            $this->jsonError('Invalid request.');
            return;
        }

        $product = (new Product())->find($productId);
        $stock = $product ? (int) ($product['quantity'] ?? 0) : 0;

        if ($qty > $stock) {
            $this->jsonError('Only ' . $stock . ' in stock.');
            return;
        }

        if (!isset($_SESSION['pos_cart'])) {
            $_SESSION['pos_cart'] = [];
        }

        foreach ($_SESSION['pos_cart'] as &$item) {
            if ((int) $item['product_id'] === $productId) {
                $item['quantity'] = $qty;
                $item['max_stock'] = $stock;
                break;
            }
        }
        unset($item);

        $this->jsonCart();
    }

    public function cartRemove(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $productId = (int) ($input['product_id'] ?? 0);

        if (!isset($_SESSION['pos_cart'])) {
            $_SESSION['pos_cart'] = [];
        }

        $_SESSION['pos_cart'] = array_values(array_filter(
            $_SESSION['pos_cart'],
            static fn(array $item): bool => (int) $item['product_id'] !== $productId
        ));

        $this->jsonCart();
    }

    public function cartGet(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        if (!isset($_SESSION['pos_cart'])) {
            $_SESSION['pos_cart'] = [];
        }

        $this->jsonCart();
    }

    public function applyCoupon(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $code = trim((string) ($input['code'] ?? ''));

        if ($code === '') {
            unset($_SESSION['pos_coupon'], $_SESSION['pos_discount']);
            $this->jsonCart();
            return;
        }

        $coupon = (new Coupon())->findByCode($code);
        if ($coupon === null) {
            $this->jsonError('Invalid, expired, or inactive coupon code.');
            return;
        }

        $cart = $_SESSION['pos_cart'] ?? [];
        $subtotal = $this->calcSubtotal($cart);

        if ($coupon['discount_type'] === 'percent') {
            $discount = round($subtotal * ((float) $coupon['value'] / 100.0), 2);
        } else {
            $discount = min((float) $coupon['value'], $subtotal);
        }

        $_SESSION['pos_coupon'] = [
            'coupon_id' => (int) $coupon['coupon_id'],
            'code' => $coupon['code'],
            'discount_type' => $coupon['discount_type'],
            'value' => (float) $coupon['value'],
        ];
        $_SESSION['pos_discount'] = $discount;

        $this->jsonCart();
    }

    public function processPayment(): void
    {
        AuthMiddleware::requireRole('sales_staff', 'admin');

        $cart = $_SESSION['pos_cart'] ?? [];
        if (!$cart) {
            $this->jsonError('Cart is empty.');
            return;
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        $paymentMethod = (string) ($input['payment_method'] ?? 'cash');

        $allowedMethods = ['cash', 'card', 'bank_transfer', 'mobile'];
        if (!in_array($paymentMethod, $allowedMethods, true)) {
            $this->jsonError('Invalid payment method.');
            return;
        }

        $subtotal = $this->calcSubtotal($cart);
        $discount = (float) ($_SESSION['pos_discount'] ?? 0.0);
        $taxable = $subtotal - $discount;
        if ($taxable < 0) {
            $taxable = 0.0;
        }
        $tax = round($taxable * self::TAX_RATE, 2);
        $total = round($taxable + $tax, 2);

        $couponId = null;
        if (isset($_SESSION['pos_coupon'])) {
            $couponId = (int) $_SESSION['pos_coupon']['coupon_id'];
        }

        $userId = (int) AuthMiddleware::id();
        $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
        if ($customerId === 0) {
            $customerId = null;
        }

        $db = Database::connection();
        $db->begin_transaction();

        try {
            $saleModel = new Sale();
            $invoiceNo = $saleModel->generateInvoiceNo();
            $receiptNo = $saleModel->generateReceiptNo();

            // Re-validate stock for every cart item
            $productModel = new Product();
            $items = [];
            foreach ($cart as $item) {
                $product = $productModel->find((int) $item['product_id']);
                if ($product === null) {
                    throw new \RuntimeException('Product "' . ($item['name'] ?? 'unknown') . '" no longer exists.');
                }
                $stock = (int) ($product['quantity'] ?? 0);
                $qty = (int) ($item['quantity'] ?? 1);
                if ($stock < $qty) {
                    throw new \RuntimeException('Only ' . $stock . ' of "' . ($item['name'] ?? 'unknown') . '" in stock (requested ' . $qty . ').');
                }
                $items[] = [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => $qty,
                    'selling_price' => (float) ($item['selling_price'] ?? 0),
                ];
            }

            $saleId = $saleModel->create([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'coupon_id' => $couponId,
            ], $items, $invoiceNo);

            $paymentModel = new Payment();
            $paymentModel->create([
                'sale_id' => $saleId,
                'payment_method' => $paymentMethod,
                'amount' => $total,
                'amount_tendered' => $input['amount_tendered'] ?? null,
                'change_due' => $input['change_due'] ?? null,
                'reference' => $input['reference'] ?? null,
            ]);

            $receiptModel = new Receipt();
            $receiptModel->create([
                'sale_id' => $saleId,
                'receipt_number' => $receiptNo,
            ]);

            $db->commit();

            $sale = $saleModel->findWithItems($saleId);
            $payment = $paymentModel->findBySale($saleId);
            $receipt = $receiptModel->findBySale($saleId);

            unset($_SESSION['pos_cart'], $_SESSION['pos_coupon'], $_SESSION['pos_discount'], $_SESSION['pos_customer']);
            $_SESSION['pos_cart'] = [];

            $this->jsonSuccess([
                'sale' => $sale,
                'payment' => $payment,
                'receipt' => $receipt,
                'store' => $this->storeInfo(),
            ]);
        } catch (Throwable $e) {
            $db->rollback();
            $this->jsonError($e->getMessage());
        }
    }

    private function jsonCart(): void
    {
        $cart = $_SESSION['pos_cart'] ?? [];
        $subtotal = $this->calcSubtotal($cart);
        $coupon = $_SESSION['pos_coupon'] ?? null;
        $discount = (float) ($_SESSION['pos_discount'] ?? 0.0);
        $taxable = $subtotal - $discount;
        if ($taxable < 0) {
            $taxable = 0.0;
        }
        $tax = round($taxable * self::TAX_RATE, 2);
        $total = round($taxable + $tax, 2);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'cart' => $cart,
            'subtotal' => $subtotal,
            'count' => count($cart),
            'coupon' => $coupon,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonSuccess(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonError(string $message): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @param array<int, array<string, mixed>> $cart
     */
    private function calcSubtotal(array $cart): float
    {
        $sum = 0.0;
        foreach ($cart as $item) {
            $sum += (float) ($item['selling_price'] ?? 0) * (int) ($item['quantity'] ?? 0);
        }
        return round($sum, 2);
    }

    /**
     * @return array{name:string,address:string,phone:string,email:string}
     */
    private function storeInfo(): array
    {
        return [
            'name' => 'MobileTech Pro',
            'address' => 'No. 12, Yangon, Myanmar',
            'phone' => '0100000001',
            'email' => 'info@mobiletechpro.test',
        ];
    }
}
