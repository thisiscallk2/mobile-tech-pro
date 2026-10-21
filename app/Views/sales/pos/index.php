<?php
/**
 * Point of Sale — Product Browse & Cart
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $products
 * @var array<int,array{category_id:int,name:string}> $categories
 * @var int|null $categoryId
 * @var string $q
 * @var array<int,array<string,mixed>> $cart
 * @var float $subtotal
 * @var array<string,mixed>|null $coupon
 * @var float $discount
 * @var array<string,mixed>|null $posCustomer
 */

$cartJson = json_encode(array_values($cart), JSON_UNESCAPED_UNICODE);
$subtotalVal = $subtotal;
$discountVal = $discount;
$couponJson = $coupon !== null ? json_encode($coupon, JSON_UNESCAPED_UNICODE) : 'null';
$posCustomerJson = isset($_SESSION['pos_customer']) ? json_encode($_SESSION['pos_customer'], JSON_UNESCAPED_UNICODE) : 'null';

$taxable = $subtotalVal - $discountVal;
if ($taxable < 0) {
    $taxable = 0.0;
}
$taxVal = round($taxable * 0.10, 2);
$totalVal = round($taxable + $taxVal, 2);

$initialProducts = [];
foreach ($products as $p) {
    $initialProducts[] = [
        'product_id' => (int) $p['product_id'],
        'name'       => $p['name'],
        'sku'        => $p['sku'],
        'selling_price' => (float) $p['selling_price'],
        'image_path' => $p['image_path'] ?? '',
        'quantity'   => (int) ($p['quantity'] ?? 0),
    ];
}
$posDataJson = json_encode([
    'searchUrl'  => base_url('/sales/pos/search'),
    'cartAddUrl'    => base_url('/sales/pos/cart/add'),
    'cartUpdateUrl' => base_url('/sales/pos/cart/update'),
    'cartRemoveUrl' => base_url('/sales/pos/cart/remove'),
    'cartGetUrl'    => base_url('/sales/pos/cart'),
    'couponUrl'     => base_url('/sales/pos/coupon'),
    'processUrl'    => base_url('/sales/pos/process'),
    'customerSearchUrl' => base_url('/sales/customers/search'),
    'customerQuickCreateUrl' => base_url('/sales/customers/quick-create'),
    'customerSetUrl' => base_url('/sales/customers/set'),
    'baseUrl'       => rtrim(BASE_URL, '/'),
    'initialProducts' => $initialProducts,
], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/pos.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/pos-print.css')) ?>" media="print">
</head>
<body class="dash-body">
    <div class="dash-layout">

        <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

        <div class="dash-main">

            <header class="topbar">
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <span class="crumb">Sales</span>
                    <span class="crumb-sep">&rsaquo;</span>
                    <span class="crumb crumb-current">Point of Sale</span>
                </nav>
                <div class="topbar-right">
                    <span class="topbar-date"><?= e($today) ?></span>
                    <div class="user-chip">
                        <span class="user-avatar" aria-hidden="true"><?= e(strtoupper(substr((string) ($user['full_name'] ?? 'U'), 0, 1))) ?></span>
                        <span class="user-meta">
                            <span class="user-name"><?= e($user['full_name'] ?? 'User') ?></span>
                            <span class="user-role"><?= e(ucwords(str_replace('_', ' ', (string) ($user['user_type'] ?? '')))) ?></span>
                        </span>
                    </div>
                </div>
            </header>

            <main class="content pos-main">
                <div class="pos-layout">

                    <div class="pos-products">
                        <div class="pos-header">
                            <h1 class="page-greeting">Point of Sale</h1>
                            <div class="pos-search">
                                <svg class="pos-search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                                </svg>
                                <input type="text" id="posSearch" class="pos-search-input" placeholder="Search products by name or SKU..."
                                       value="<?= e($q) ?>" autocomplete="off">
                            </div>
                        </div>

                        <div class="pos-categories">
                            <button class="pos-cat-btn<?= $categoryId === null ? ' is-active' : '' ?>"
                                    data-category="">All Products</button>
                            <?php foreach ($categories as $cat): ?>
                                <button class="pos-cat-btn<?= $categoryId === (int) $cat['category_id'] ? ' is-active' : '' ?>"
                                        data-category="<?= (int) $cat['category_id'] ?>">
                                    <?= e($cat['name']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="pos-grid" id="posGrid">
                            <?php if (!$products): ?>
                                <p class="pos-empty">No products found.</p>
                            <?php endif; ?>
                            <?php foreach ($products as $p):
                                $pid = (int) $p['product_id'];
                                $qty = (int) ($p['quantity'] ?? 0);
                                $outOfStock = $qty <= 0;
                            ?>
                                <div class="pos-card<?= $outOfStock ? ' pos-card--oos' : '' ?>"
                                     data-product-id="<?= $pid ?>"
                                     data-name="<?= e($p['name']) ?>"
                                     data-price="<?= (float) $p['selling_price'] ?>"
                                     data-sku="<?= e($p['sku']) ?>"
                                     data-stock="<?= $qty ?>"
                                     data-image="<?= e($p['image_path'] ?? '') ?>">
                                    <div class="pos-card-img">
                                        <?php if (!empty($p['image_path'])): ?>
                                            <img src="<?= e(base_url($p['image_path'])) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="2" y="2" width="20" height="20" rx="5"/>
                                                <path d="M16 11.37A4 4 0 1 1 12.63 8"/>
                                            </svg>
                                        <?php endif; ?>
                                        <?php if ($outOfStock): ?>
                                            <span class="pos-card-badge">OOS</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pos-card-body">
                                        <span class="pos-card-name"><?= e($p['name']) ?></span>
                                        <span class="pos-card-price">$<?= number_format((float) $p['selling_price'], 2) ?></span>
                                    </div>
                                    <?php if (!$outOfStock): ?>
                                        <button class="pos-card-add" type="button" aria-label="Add <?= e($p['name']) ?> to cart">+</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="pos-sidebar">
                        <div class="pos-panel pos-customer-panel">
                            <div class="pos-panel-head">
                                <h2 class="pos-panel-title">Customer</h2>
                                <button class="pos-link" type="button" id="newCustomerBtn">+ New Customer</button>
                            </div>
                            <div class="pos-customer-search">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                                </svg>
                                <input type="text" class="pos-customer-input" id="customerSearch" placeholder="Search by name, phone, or email..." autocomplete="off">
                            </div>
                            <div class="pos-customer-results" id="customerResults" hidden></div>
                            <div class="pos-customer-selected" id="customerSelected"<?= isset($_SESSION['pos_customer']) ? '' : ' hidden' ?>>
                                <div class="pos-customer-card">
                                    <span class="pos-customer-name" id="selectedCustomerName"><?= e($_SESSION['pos_customer']['full_name'] ?? '') ?></span>
                                    <span class="pos-customer-info" id="selectedCustomerInfo"><?= e(($_SESSION['pos_customer']['phone'] ?? '') ?: ($_SESSION['pos_customer']['email'] ?? 'No contact info')) ?></span>
                                    <button class="pos-customer-remove" type="button" id="removeCustomerBtn" aria-label="Remove customer">&times;</button>
                                </div>
                            </div>
                        </div>

                        <div class="pos-panel pos-cart-panel">
                            <div class="pos-panel-head">
                                <h2 class="pos-panel-title">Shopping Cart</h2>
                                <span class="pos-cart-count" id="cartCount"><?= count($cart) ?> items</span>
                            </div>

                            <div class="pos-cart-items" id="cartItems">
                                <?php if (!$cart): ?>
                                    <p class="pos-cart-empty" id="cartEmpty">Your cart is empty.</p>
                                <?php endif; ?>
                                <?php foreach ($cart as $item):
                                    $itemPid = (int) $item['product_id'];
                                    $itemQty = (int) ($item['quantity'] ?? 1);
                                    $itemPrice = (float) ($item['selling_price'] ?? 0);
                                    $itemLine = $itemPrice * $itemQty;
                                    $itemStock = (int) ($item['max_stock'] ?? 999);
                                ?>
                                    <div class="pos-cart-item" data-product-id="<?= $itemPid ?>">
                                        <div class="pos-cart-item-img">
                                            <?php if (!empty($item['image_path'])): ?>
                                                <img src="<?= e(base_url($item['image_path'])) ?>" alt="" loading="lazy">
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="2" y="2" width="20" height="20" rx="5"/>
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pos-cart-item-info">
                                            <span class="pos-cart-item-name"><?= e($item['name']) ?></span>
                                            <span class="pos-cart-item-price">$<?= number_format($itemPrice, 2) ?></span>
                                            <div class="pos-cart-item-qty">
                                                <button class="qty-btn qty-minus" type="button" aria-label="Decrease quantity">&minus;</button>
                                                <span class="qty-val"><?= $itemQty ?></span>
                                                <button class="qty-btn qty-plus" type="button" aria-label="Increase quantity">+</button>
                                            </div>
                                        </div>
                                        <div class="pos-cart-item-right">
                                            <span class="pos-cart-item-total">$<?= number_format($itemLine, 2) ?></span>
                                            <button class="pos-cart-item-remove" type="button" aria-label="Remove item" title="Remove">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="pos-cart-foot">
                                <div class="pos-order-summary">
                                    <div class="pos-summary-row">
                                        <span>Subtotal</span>
                                        <span id="summarySubtotal">$<?= number_format($subtotalVal, 2) ?></span>
                                    </div>

                                    <div class="pos-coupon-row">
                                        <input type="text" id="couponInput" class="pos-coupon-input" placeholder="Coupon code" autocomplete="off">
                                        <button class="btn-outline btn-sm pos-coupon-btn" type="button" id="applyCouponBtn">Apply</button>
                                    </div>
                                    <p class="pos-coupon-msg" id="couponMsg"></p>

                                    <div class="pos-summary-row pos-summary-discount" id="discountRow"<?= $discountVal > 0 ? '' : ' hidden' ?>>
                                        <span>Discount</span>
                                        <span id="summaryDiscount">- $<?= number_format($discountVal, 2) ?></span>
                                    </div>

                                    <div class="pos-summary-row">
                                        <span>Tax (10%)</span>
                                        <span id="summaryTax">$<?= number_format($taxVal, 2) ?></span>
                                    </div>

                                    <div class="pos-summary-row pos-summary-total">
                                        <span>Total</span>
                                        <span id="summaryTotal">$<?= number_format($totalVal, 2) ?></span>
                                    </div>
                                </div>

                                <div class="pos-payment-methods" id="paymentMethods">
                                    <button class="pos-pay-btn is-active" data-method="cash" type="button">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                        <span>Cash</span>
                                    </button>
                                    <button class="pos-pay-btn" data-method="card" type="button">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                        <span>Card</span>
                                    </button>
                                    <button class="pos-pay-btn" data-method="bank_transfer" type="button">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M7 15h3"/></svg>
                                        <span>Bank Transfer</span>
                                    </button>
                                    <button class="pos-pay-btn" data-method="mobile" type="button">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <span>Mobile</span>
                                    </button>
                                </div>

                                <button class="btn-cyan pos-process-btn" type="button" id="processPaymentBtn"<?= !$cart ? ' disabled' : '' ?>>
                                    Process Payment &mdash; $<span id="processTotal"><?= number_format($totalVal, 2) ?></span>
                                </button>

                                <div class="pos-secondary-actions">
                                    <button class="btn-outline btn-sm" type="button" id="holdOrderBtn">Hold Order</button>
                                    <button class="btn-outline btn-sm" type="button" id="printQuoteBtn">Print Quote</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <div class="pos-toast" id="posToast" aria-live="polite"></div>

    <div class="receipt-overlay" id="receiptOverlay" hidden>
        <div class="receipt-modal" id="receiptContent"></div>
    </div>

    <div class="customer-modal-overlay" id="customerModal" hidden>
        <div class="customer-modal">
            <div class="customer-modal-head">
                <h3>New Customer</h3>
                <button class="customer-modal-close" type="button" id="closeCustomerModal" aria-label="Close">&times;</button>
            </div>
            <div class="customer-modal-body">
                <p class="customer-modal-error" id="customerModalError" hidden></p>
                <label class="slide-label">Full Name <span class="required">*</span></label>
                <input type="text" id="newCustomerName" class="slide-input" maxlength="120">
                <label class="slide-label">Phone</label>
                <input type="text" id="newCustomerPhone" class="slide-input" maxlength="30">
                <label class="slide-label">Email</label>
                <input type="email" id="newCustomerEmail" class="slide-input" maxlength="150">
            </div>
            <div class="customer-modal-foot">
                <button class="btn-outline btn-sm" type="button" id="cancelCustomerBtn">Cancel</button>
                <button class="btn-cyan btn-sm" type="button" id="saveCustomerBtn">Save Customer</button>
            </div>
        </div>
    </div>

    <script>
        window.__pos = {
            cart: <?= $cartJson ?>,
            subtotal: <?= (float) $subtotalVal ?>,
            coupon: <?= $couponJson ?>,
            discount: <?= (float) $discountVal ?>,
            tax: <?= (float) $taxVal ?>,
            total: <?= (float) $totalVal ?>,
            customer: <?= $posCustomerJson ?>
        };
        window.__posData = <?= $posDataJson ?>;
    </script>
    <script src="<?= e(base_url('js/pos-cart.js')) ?>"></script>
</body>
</html>
