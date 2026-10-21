<?php
/**
 * @var array<string, mixed> $user
 * @var string $active
 * @var string $today
 * @var string $greeting
 * @var array<int, array<string, mixed>> $stats
 * @var array<int, array{label:string,value:int,color:string}> $repairSegments
 * @var int    $repairTotal
 * @var array<string, array<int, array{label:string,value:float}>> $revenueSeries
 * @var array<int, array{user_id:int,full_name:?string,user_type:?string,sales_count:int,revenue:float,cost:float,profit:float}> $salesByStaff
 * @var string $userType
 */

$statIcons = [
    'revenue' => '<path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    'cost' => '<circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 0 0 0 4h4a2 2 0 0 1 0 4H8"/><path d="M12 6v12"/>',
    'profit' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
    'sales' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
    'repairs' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    'lowstock' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
];

$isAdmin = (($user['user_type'] ?? '') === 'admin');
$canSell = $isAdmin || (($user['user_type'] ?? '') === 'sales_staff');
$canRepair = $isAdmin || (($user['user_type'] ?? '') === 'technician');
$isTechnician = (($user['user_type'] ?? '') === 'technician');
$isSales = (($user['user_type'] ?? '') === 'sales_staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
</head>
<body class="dash-body">
    <div class="dash-layout">

        <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

        <div class="dash-main">

            <!-- Top bar -->
            <header class="topbar">
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <span class="crumb">Admin</span>
                    <span class="crumb-sep">&rsaquo;</span>
                    <span class="crumb crumb-current">Dashboard</span>
                </nav>

                <div class="topbar-right">
                    <form class="topbar-search" method="get" action="<?= e(base_url('/admin/inventory')) ?>" role="search">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="search" name="q" class="search-input" placeholder="Search products..." aria-label="Search">
                    </form>

                    <span class="topbar-date"><?= e($today) ?></span>

                    <div class="user-chip">
                        <span class="user-avatar" aria-hidden="true">
                            <?= e(strtoupper(substr((string) ($user['full_name'] ?? 'U'), 0, 1))) ?>
                        </span>
                        <span class="user-meta">
                            <span class="user-name"><?= e($user['full_name'] ?? 'User') ?></span>
                            <span class="user-role"><?= e(ucwords(str_replace('_', ' ', (string) ($user['user_type'] ?? '')))) ?></span>
                        </span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="content">

                <div class="content-head">
                    <div>
                        <h1 class="page-greeting"><?= e($greeting) ?> &#128075;</h1>
                        <p class="page-sub">Here's what's happening at your shop today</p>
                    </div>
                    <div class="content-actions">
                        <?php if ($isAdmin): ?>
                            <a class="btn-outline" href="<?= e(base_url('/admin/reports/export-csv')) ?>" target="_blank" rel="noopener">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Export
                            </a>
                        <?php endif; ?>
                        <?php if ($canSell): ?>
                            <a class="btn-cyan" href="<?= e(base_url('/sales/pos')) ?>">+ Quick Add</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stat cards -->
                <section class="stat-grid">
                    <?php foreach ($stats as $stat): ?>
                        <article class="stat-card">
                            <div class="stat-top">
                                <span class="stat-icon stat-icon--<?= e($stat['key']) ?>">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $statIcons[$stat['key']] ?? '' ?></svg>
                                </span>
                                <span class="chip chip--<?= e($stat['chip']['tone']) ?>"><?= e($stat['chip']['text']) ?></span>
                            </div>
                            <p class="stat-label"><?= e(strtoupper($stat['label'])) ?></p>
                            <p class="stat-value"><?= e($stat['value']) ?></p>
                            <p class="stat-caption"><?= e($stat['caption']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </section>

                <!-- Panels -->
                <section class="panel-grid">

                    <?php if (!$isTechnician): ?>
                    <article class="panel panel-revenue">
                        <div class="panel-head">
                            <h2 class="panel-title">Revenue Overview</h2>
                            <div class="chart-toggle" role="group" aria-label="Revenue range">
                                <button type="button" class="toggle-btn is-active" data-range="daily">Daily</button>
                                <button type="button" class="toggle-btn" data-range="weekly">Weekly</button>
                                <button type="button" class="toggle-btn" data-range="monthly">Monthly</button>
                            </div>
                        </div>
                        <div
                            id="revenueChart"
                            class="revenue-chart"
                            data-daily="<?= e(json_encode($revenueSeries['daily'])) ?>"
                            data-weekly="<?= e(json_encode($revenueSeries['weekly'])) ?>"
                            data-monthly="<?= e(json_encode($revenueSeries['monthly'])) ?>">
                        </div>
                    </article>
                    <?php endif; ?>

                    <?php if (!$isSales): ?>
                    <article class="panel panel-repairs">
                        <div class="panel-head">
                            <h2 class="panel-title">Repair Jobs</h2>
                        </div>
                        <div
                            id="repairDonut"
                            class="repair-donut"
                            data-total="<?= e((string) $repairTotal) ?>"
                            data-segments="<?= e(json_encode($repairSegments)) ?>">
                        </div>
                        <ul class="donut-legend">
                            <?php foreach ($repairSegments as $seg): ?>
                                <li class="legend-item">
                                    <span class="legend-dot" data-color="<?= e($seg['color']) ?>"></span>
                                    <span class="legend-label"><?= e($seg['label']) ?></span>
                                    <span class="legend-value"><?= e((string) $seg['value']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="repair-actions">
                            <?php if ($canRepair): ?>
                                <a class="btn-outline btn-sm" href="<?= e(base_url('/technician/board/create')) ?>">New Repair</a>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                                <a class="btn-cyan btn-sm" href="<?= e(base_url('/admin/inventory/create')) ?>">+ Add Product</a>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endif; ?>

                </section>

                <?php if ($isAdmin): ?>
                <section class="panel" style="margin-top:16px">
                    <div class="panel-head">
                        <h2 class="panel-title">Sales by Staff</h2>
                        <span style="color:var(--text-600);font-size:0.85rem">Revenue, cost and profit per cashier this month</span>
                    </div>
                    <div class="table-wrap">
                        <table class="user-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Role</th>
                                    <th>Sales</th>
                                    <th>Revenue</th>
                                    <th>Cost Price</th>
                                    <th>Profit</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$salesByStaff): ?>
                                    <tr><td colspan="7" class="empty-row">No sales recorded this month.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($salesByStaff as $s): ?>
                                    <tr>
                                        <td><?= e($s['full_name'] ?: 'Unassigned') ?></td>
                                        <td><?= e(ucwords(str_replace('_', ' ', (string) ($s['user_type'] ?? '')))) ?></td>
                                        <td><?= number_format((int) $s['sales_count']) ?></td>
                                        <td>$<?= number_format((float) $s['revenue']) ?></td>
                                        <td>$<?= number_format((float) $s['cost']) ?></td>
                                        <td>$<?= number_format((float) $s['profit']) ?></td>
                                        <td style="text-align:right">
                                            <a class="btn-outline btn-sm" href="<?= e(base_url('/sales/history?user_id=' . (int) $s['user_id'])) ?>">View Sales</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="<?= e(base_url('js/dashboard-charts.js')) ?>"></script>
</body>
</html>
