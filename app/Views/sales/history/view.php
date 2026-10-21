<?php
/**
 * Sales History — detailed sale view
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<string,mixed> $sale
 */

function paymentLabelV(string $method): string {
    return ['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'mobile' => 'Mobile'][$method] ?? $method;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale <?= e($sale['invoice_no']) ?> &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
</head>
<body class="dash-body">
<div class="dash-layout">

    <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

    <div class="dash-main">
        <header class="topbar">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <span class="crumb">Sales</span>
                <span class="crumb-sep">&rsaquo;</span>
                <a class="crumb" href="<?= e(base_url('/sales/history')) ?>">Sales History</a>
                <span class="crumb-sep">&rsaquo;</span>
                <span class="crumb crumb-current"><?= e($sale['invoice_no']) ?></span>
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

        <main class="content">
            <div class="content-head">
                <div>
                    <h1 class="page-greeting">Sale Detail</h1>
                    <p class="page-sub"><?= e($sale['invoice_no']) ?> &mdash; <?= e(date('M j, Y g:i A', strtotime((string) $sale['created_at']))) ?></p>
                </div>
                <div class="content-actions">
                    <a href="<?= e(base_url('/sales/history')) ?>" class="btn-outline">&larr; Back to History</a>
                </div>
            </div>

            <div class="panel-grid" style="grid-template-columns:1fr 1fr">
                <div class="panel">
                    <div class="panel-head"><h2 class="panel-title">Transaction</h2></div>
                    <table class="detail-table">
                        <tr><td class="detail-label">Invoice</td><td class="mono"><?= e($sale['invoice_no']) ?></td></tr>
                        <tr><td class="detail-label">Date</td><td><?= e(date('M j, Y g:i A', strtotime((string) $sale['created_at']))) ?></td></tr>
                        <tr><td class="detail-label">Cashier</td><td><?= e($sale['cashier_name'] ?? '—') ?></td></tr>
                        <tr><td class="detail-label">Customer</td><td><?= e($sale['customer_name'] ?? 'Walk-in Customer') ?></td></tr>
                        <tr><td class="detail-label">Payment</td><td><?= e(paymentLabelV((string) $sale['payment_method'])) ?></td></tr>
                        <tr><td class="detail-label">Status</td><td><span class="status-pill status-pill--completed"><?= e(ucfirst((string) $sale['status'])) ?></span></td></tr>
                        <?php if ($sale['coupon_code']): ?>
                        <tr><td class="detail-label">Coupon</td><td><span class="mono"><?= e($sale['coupon_code']) ?></span></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="panel">
                    <div class="panel-head"><h2 class="panel-title">Totals</h2></div>
                    <table class="detail-table">
                        <tr><td class="detail-label">Subtotal</td><td>$<?= number_format((float) $sale['subtotal'], 2) ?></td></tr>
                        <?php if ((float) $sale['discount'] > 0): ?>
                        <tr><td class="detail-label">Discount</td><td style="color:var(--green-500)">- $<?= number_format((float) $sale['discount'], 2) ?></td></tr>
                        <?php endif; ?>
                        <tr><td class="detail-label">Tax</td><td>$<?= number_format((float) $sale['tax'], 2) ?></td></tr>
                        <tr><td class="detail-label" style="font-weight:700">Total</td><td style="font-weight:700;font-size:1.2rem">$<?= number_format((float) $sale['total'], 2) ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="panel" style="margin-top:16px">
                <div class="panel-head"><h2 class="panel-title">Items (<?= count($sale['items']) ?>)</h2></div>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="r-qty">Qty</th>
                            <th class="r-price">Unit Price</th>
                            <th class="r-amount">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale['items'] as $item): ?>
                        <tr>
                            <td><?= e($item['product_name'] ?? '—') ?></td>
                            <td><span class="mono"><?= e($item['sku'] ?? '—') ?></span></td>
                            <td class="r-qty"><?= (int) ($item['quantity'] ?? 1) ?></td>
                            <td class="r-price">$<?= number_format((float) ($item['unit_price'] ?? 0), 2) ?></td>
                            <td class="r-amount">$<?= number_format((float) ($item['line_total'] ?? 0), 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>
</body>
</html>
