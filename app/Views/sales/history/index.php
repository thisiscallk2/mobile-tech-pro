<?php
/**
 * Sales History — list of completed sales
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $sales
 * @var array<int,array<string,mixed>> $staff
 * @var string $from
 * @var string $to
 * @var int|null $userId
 * @var int $page
 * @var int $totalCount
 * @var int $totalPages
 * @var bool $hideStaffFilter
 */

function paymentLabel(string $method): string {
    return ['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'mobile' => 'Mobile'][$method] ?? $method;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History &mdash; MobileTech Pro</title>
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
                <span class="crumb crumb-current">Sales History</span>
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
                    <h1 class="page-greeting">Sales History</h1>
                    <p class="page-sub">Completed transactions</p>
                </div>
            </div>

            <form method="get" class="history-filters" autocomplete="off">
                <div class="history-filter-group">
                    <label class="history-label" for="filterFrom">From</label>
                    <input type="date" id="filterFrom" name="from" value="<?= e($from) ?>" class="history-input">
                </div>
                <div class="history-filter-group">
                    <label class="history-label" for="filterTo">To</label>
                    <input type="date" id="filterTo" name="to" value="<?= e($to) ?>" class="history-input">
                </div>
                <?php if (!$hideStaffFilter): ?>
                <div class="history-filter-group">
                    <label class="history-label" for="filterStaff">Staff</label>
                    <select id="filterStaff" name="user_id" class="history-input">
                        <option value="">All Staff</option>
                        <?php foreach ($staff as $s): ?>
                            <option value="<?= (int) $s['user_id'] ?>"<?= $userId === (int) $s['user_id'] ? ' selected' : '' ?>><?= e($s['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn-outline btn-sm">Filter</button>
                <?php if ($from !== '' || $to !== '' || $userId !== null): ?>
                    <a href="<?= e(base_url('/sales/history')) ?>" class="btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (!$sales): ?>
                <p class="pos-empty" style="margin-top:30px">No sales found for this period.</p>
            <?php else: ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Cashier</th>
                            <th>Payment</th>
                            <th class="r-amount">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $s): ?>
                        <tr>
                            <td><span class="mono"><?= e($s['invoice_no']) ?></span></td>
                            <td><?= e(date('M j, Y g:i A', strtotime((string) $s['created_at']))) ?></td>
                            <td><?= e($s['customer_name'] ?? 'Walk-in') ?></td>
                            <td><?= e($s['cashier_name'] ?? '—') ?></td>
                            <td><?= e(paymentLabel((string) $s['payment_method'])) ?></td>
                            <td class="r-amount">$<?= number_format((float) $s['total'], 2) ?></td>
                            <td><a class="btn-outline btn-sm" href="<?= e(base_url('/sales/history/view/' . $s['sale_id'])) ?>">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <span class="pagination-info">Showing <?= (($page - 1) * 20 + 1) ?>–<?= min($page * 20, $totalCount) ?> of <?= $totalCount ?> sales</span>
                    <div class="pagination-links">
                        <?php if ($page > 1): ?>
                            <a href="?from=<?= urlencode($from) ?>&amp;to=<?= urlencode($to) ?>&amp;user_id=<?= $userId ?? '' ?>&amp;page=<?= $page - 1 ?>" class="btn-outline btn-sm">&laquo; Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?from=<?= urlencode($from) ?>&amp;to=<?= urlencode($to) ?>&amp;user_id=<?= $userId ?? '' ?>&amp;page=<?= $page + 1 ?>" class="btn-outline btn-sm">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
