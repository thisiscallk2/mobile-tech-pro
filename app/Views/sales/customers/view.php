<?php
/**
 * Customer detail — profile with purchase and repair history
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<string,mixed> $customer
 */

function payLabel(string $method): string {
    return ['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'mobile' => 'Mobile'][$method] ?? $method;
}
function repairStatusBadge(string $status): string {
    $labels = ['received' => 'Received', 'diagnosing' => 'Diagnosing', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
    $colors = ['received' => 'status-pill--neutral', 'diagnosing' => 'status-pill--neutral', 'in_progress' => 'status-pill--info', 'completed' => 'status-pill--success', 'delivered' => 'status-pill--success', 'cancelled' => 'status-pill--danger'];
    $cls = $colors[$status] ?? '';
    return '<span class="status-pill ' . $cls . '">' . e($labels[$status] ?? $status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($customer['full_name']) ?> &mdash; MobileTech Pro</title>
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
                <a class="crumb" href="<?= e(base_url('/sales/customers')) ?>">Customers</a>
                <span class="crumb-sep">&rsaquo;</span>
                <span class="crumb crumb-current"><?= e($customer['full_name']) ?></span>
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
                    <h1 class="page-greeting"><?= e($customer['full_name']) ?></h1>
                    <p class="page-sub">Customer Profile &amp; History</p>
                </div>
                <div class="content-actions">
                    <a href="<?= e(base_url('/sales/customers')) ?>" class="btn-outline">&larr; Back to Customers</a>
                    <a href="<?= e(base_url('/sales/customers/edit/' . (int) $customer['customer_id'])) ?>" class="btn-cyan">Edit</a>
                </div>
            </div>

            <div class="panel-grid" style="grid-template-columns:1fr 1fr">
                <div class="panel">
                    <div class="panel-head"><h2 class="panel-title">Contact Details</h2></div>
                    <table class="detail-table">
                        <tr><td class="detail-label">Name</td><td><?= e($customer['full_name']) ?></td></tr>
                        <tr><td class="detail-label">Phone</td><td><?= e($customer['phone'] ?? '—') ?></td></tr>
                        <tr><td class="detail-label">Email</td><td><?= e($customer['email'] ?? '—') ?></td></tr>
                        <tr><td class="detail-label">Address</td><td><?= e($customer['address'] ?? '—') ?></td></tr>
                        <tr><td class="detail-label">Joined</td><td><?= e(date('M j, Y', strtotime((string) $customer['created_at']))) ?></td></tr>
                    </table>
                </div>
                <div class="panel">
                    <div class="panel-head"><h2 class="panel-title">Notes</h2></div>
                    <p style="color:var(--text-500);margin:0"><?= e($customer['notes'] ?? 'No notes.') ?></p>
                </div>
            </div>

            <div class="panel" style="margin-top:16px">
                <div class="panel-head"><h2 class="panel-title">Purchase History (<?= count($customer['purchases']) ?>)</h2></div>
                <?php if (!$customer['purchases']): ?>
                    <p style="color:var(--text-600);margin:0">No purchases yet.</p>
                <?php else: ?>
                <table class="user-table">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Payment</th><th class="r-amount">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($customer['purchases'] as $p): ?>
                        <tr>
                            <td><a href="<?= e(base_url('/sales/history/view/' . (int) $p['sale_id'])) ?>" class="mono" style="color:var(--cyan-400)"><?= e($p['invoice_no']) ?></a></td>
                            <td><?= e(date('M j, Y g:i A', strtotime((string) $p['created_at']))) ?></td>
                            <td><?= e(payLabel((string) $p['payment_method'])) ?></td>
                            <td class="r-amount">$<?= number_format((float) $p['total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="panel" style="margin-top:16px">
                <div class="panel-head"><h2 class="panel-title">Repair History (<?= count($customer['repairs']) ?>)</h2></div>
                <?php if (!$customer['repairs']): ?>
                    <p style="color:var(--text-600);margin:0">No repairs yet.</p>
                <?php else: ?>
                <table class="user-table">
                    <thead><tr><th>Ticket</th><th>Device</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($customer['repairs'] as $r): ?>
                        <tr>
                            <td><span class="mono"><?= e($r['ticket_no']) ?></span></td>
                            <td><?= e(trim(($r['device_brand'] ?? '') . ' ' . ($r['device_model'] ?? '')) ?: '—') ?></td>
                            <td><?= e(date('M j, Y', strtotime((string) $r['created_at']))) ?></td>
                            <td><?= repairStatusBadge((string) $r['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>
