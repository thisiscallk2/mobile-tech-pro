<?php
/**
 * Reports & Analytics
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var string $tab
 * @var string $granularity
 * @var string $from
 * @var string $to
 * @var int|null $staffId
 * @var bool $compare
 * @var array<int,array<string,mixed>> $stats
 * @var array<int,array<string,mixed>> $allStaff
 * @var string $subtextMonth
 * @var array<int,array{label:string,value:float}> $revenueSeries
 * @var array<int,array{label:string,value:float}> $previousRevenueSeries
 * @var array<int,array<string,mixed>> $salesByStaff
 * @var float $staffMaxRevenue
 * @var array<int,array<string,mixed>> $topProducts
 * @var int $page
 * @var int $totalPages
 * @var int $totalProducts
 * @var int $shown
 * @var array<string,int|float> $repairOverview
 * @var array{pending:int,in_progress:int,completed:int,total:int} $statusBreakdown
 * @var array<int,array<string,mixed>> $repairsByTechnician
 * @var int $repairTechMax
 * @var array<int,array<string,mixed>> $recentRepairs
 * @var array<int,array<string,mixed>> $revenueByPayment
 * @var float $paymentMax
 * @var array<string,int|float> $inventoryStats
 * @var array<int,array<string,mixed>> $inventoryByCategory
 * @var int $categoryMaxUnits
 * @var array<int,array<string,mixed>> $lowStockItems
 */

$tabLabels = [
    'sales' => 'Sales Report',
    'repair' => 'Repair Report',
    'revenue' => 'Revenue Report',
    'inventory' => 'Inventory Report',
];

$queryParams = function (array $overrides = []) use ($tab, $granularity, $from, $to, $staffId, $compare): string {
    $params = array_merge([
        'tab' => $tab,
        'period' => $granularity,
        'from' => $from,
        'to' => $to,
        'staff' => $staffId,
        'compare' => $compare ? '1' : '0',
    ], $overrides);
    $filtered = array_filter($params, static fn($v) => $v !== null && $v !== '');
    return http_build_query($filtered);
};

$initialsOf = function (?string $name): string {
    $name = trim((string) $name);
    $out = '';
    foreach (array_slice(explode(' ', $name), 0, 2) as $p) {
        if ($p !== '') {
            $out .= strtoupper($p[0]);
        }
    }
    return $out !== '' ? $out : '?';
};

$statusLabel = static function (?string $status): string {
    return ucwords(str_replace('_', ' ', (string) $status));
};

$seriesJson = json_encode($revenueSeries ?? [], JSON_UNESCAPED_UNICODE);
$prevSeriesJson = json_encode($previousRevenueSeries ?? [], JSON_UNESCAPED_UNICODE);
$staffJson = json_encode($salesByStaff ?? [], JSON_UNESCAPED_UNICODE);
$staffMax = ($staffMaxRevenue ?? 0) > 0 ? $staffMaxRevenue : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/reports.css')) ?>">
</head>
<body class="dash-body">
    <div class="dash-layout">

        <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

        <div class="dash-main">

            <header class="topbar">
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <span class="crumb">Business</span>
                    <span class="crumb-sep">&rsaquo;</span>
                    <span class="crumb crumb-current">Reports &amp; Analytics</span>
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
                        <h1 class="page-greeting"><?= e($tabLabels[$tab]) ?></h1>
                        <p class="page-sub">Insights and performance metrics &middot; <?= e($subtextMonth) ?></p>
                    </div>
                    <div class="content-actions">
                        <a class="btn-outline btn-sm" href="<?= e(base_url('/admin/reports?' . $queryParams(['print' => '1']))) ?>" target="_blank" rel="noopener">Download PDF</a>
                        <?php if ($tab === 'sales'): ?>
                            <a class="btn-outline btn-sm" href="<?= e(base_url('/admin/reports/export-csv?' . $queryParams())) ?>">Export CSV</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="report-tabs">
                    <?php foreach (['sales', 'repair', 'revenue', 'inventory'] as $t): ?>
                        <a class="report-tab<?= $tab === $t ? ' is-active' : '' ?>"
                           href="<?= e(base_url('/admin/reports?' . $queryParams(['tab' => $t]))) ?>">
                            <?= e($tabLabels[$t]) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form method="get" action="<?= e(base_url('/admin/reports')) ?>" class="report-filters" id="reportFilters">
                    <input type="hidden" name="tab" value="<?= e($tab) ?>">

                    <?php if ($tab !== 'inventory'): ?>
                        <div class="filter-group">
                            <label class="filter-label" for="filterFrom">From</label>
                            <input type="date" id="filterFrom" name="from" class="filter-input" value="<?= e($from) ?>">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label" for="filterTo">To</label>
                            <input type="date" id="filterTo" name="to" class="filter-input" value="<?= e($to) ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($tab === 'sales' || $tab === 'revenue'): ?>
                        <div class="chart-toggle" id="periodToggle">
                            <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $gv => $gl): ?>
                                <button type="button" class="toggle-btn<?= $granularity === $gv ? ' is-active' : '' ?>"
                                        data-value="<?= e($gv) ?>"><?= e($gl) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="period" id="periodInput" value="<?= e($granularity) ?>">
                    <?php endif; ?>

                    <?php if ($tab === 'sales' || $tab === 'repair'): ?>
                        <div class="filter-group">
                            <label class="filter-label" for="filterStaff">Staff</label>
                            <select id="filterStaff" name="staff" class="filter-input">
                                <option value="">All Staff Members</option>
                                <?php foreach ($allStaff as $s): ?>
                                    <option value="<?= (int) $s['user_id'] ?>"<?= $staffId === (int) $s['user_id'] ? ' selected' : '' ?>>
                                        <?= e($s['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($tab === 'sales'): ?>
                        <label class="compare-link">
                            <input type="checkbox" name="compare" value="1"<?= $compare ? ' checked' : '' ?> class="compare-checkbox">
                            <span>Compare to last period</span>
                        </label>
                    <?php endif; ?>

                    <?php if ($tab !== 'inventory'): ?>
                        <button type="submit" class="btn-cyan btn-sm">Apply</button>
                    <?php endif; ?>
                </form>

                <div class="stat-grid report-stat-grid">
                    <?php foreach ($stats as $stat): ?>
                        <div class="stat-card">
                            <div class="stat-top">
                                <span class="stat-label"><?= e($stat['label']) ?></span>
                                <?php if (!empty($stat['delta'])): ?>
                                    <span class="chip chip--<?= e($stat['delta']['tone']) ?>">
                                        <?= e($stat['delta']['text']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="stat-value"><?= e($stat['value']) ?></p>
                            <?php if (!empty($stat['targetLabel'])): ?>
                                <div class="stat-progress">
                                    <div class="stat-progress-bar">
                                        <div class="stat-progress-fill" style="width:<?= (int) $stat['progress'] ?>%"></div>
                                    </div>
                                    <span class="stat-progress-label"><?= e($stat['targetLabel']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($tab === 'sales'): ?>

                    <div class="panel-grid report-panel-grid">
                        <div class="panel">
                            <div class="panel-head">
                                <h2 class="panel-title">Revenue Over Time</h2>
                            </div>
                            <div class="revenue-chart report-revenue-chart" id="revenueChart"></div>
                            <div class="chart-legend-line">
                                <span class="legend-line"><span class="legend-line-dot legend-line-dot--cyan"></span>This Period</span>
                                <?php if ($compare): ?>
                                    <span class="legend-line"><span class="legend-line-dot legend-line-dot--amber"></span>Last Period</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-head">
                                <h2 class="panel-title">Sales by Staff</h2>
                            </div>
                            <div class="staff-bars" id="staffBars"></div>
                        </div>
                    </div>

                    <div class="panel report-products-panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Top-Selling Products</h2>
                            <span class="panel-sub">Showing <?= $shown ?> of <?= $totalProducts ?> products</span>
                        </div>
                        <div class="table-wrap">
                            <table class="user-table report-table">
                                <thead>
                                    <tr>
                                        <th class="col-rank">RANK</th>
                                        <th>PRODUCT NAME</th>
                                        <th>CATEGORY</th>
                                        <th>UNITS SOLD</th>
                                        <th>REVENUE</th>
                                        <th>TREND</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$topProducts): ?>
                                        <tr><td colspan="6" class="empty-row">No sales data for this period.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($topProducts as $p): ?>
                                        <tr>
                                            <td class="col-rank">
                                                <span class="rank-badge"><?= (int) $p['rank'] ?></span>
                                            </td>
                                            <td>
                                                <div class="product-cell">
                                                    <span class="product-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8"/>
                                                        </svg>
                                                    </span>
                                                    <span class="product-info">
                                                        <span class="product-name"><?= e($p['name']) ?></span>
                                                        <span class="product-sku"><?= e($p['sku']) ?></span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($p['category_name'])): ?>
                                                    <span class="category-pill"><?= e($p['category_name']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= number_format((int) $p['units_sold']) ?></td>
                                            <td class="cell-revenue">$<?= number_format((float) $p['total_revenue']) ?></td>
                                            <td>
                                                <?php if ($p['trend'] !== null): ?>
                                                    <span class="trend trend--<?= $p['trend']['direction'] ?>">
                                                        <?= $p['trend']['direction'] === 'up' ? '&#9650;' : '&#9660;' ?>
                                                        <?= $p['trend']['value'] ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="trend trend--neutral">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a class="page-btn" href="<?= e(base_url('/admin/reports?' . $queryParams(['page' => $page - 1]))) ?>">&laquo; Prev</a>
                                <?php endif; ?>
                                <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
                                <?php if ($page < $totalPages): ?>
                                    <a class="page-btn" href="<?= e(base_url('/admin/reports?' . $queryParams(['page' => $page + 1]))) ?>">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($tab === 'repair'): ?>

                    <div class="panel-grid report-panel-grid">
                        <div class="panel">
                            <div class="panel-head">
                                <h2 class="panel-title">Jobs by Technician</h2>
                            </div>
                            <div class="staff-bars">
                                <?php if (!$repairsByTechnician): ?>
                                    <p style="color:var(--text-500);padding:20px;text-align:center;font-size:0.88rem;">No repair data for this period.</p>
                                <?php else: ?>
                                    <?php foreach ($repairsByTechnician as $t): ?>
                                        <?php
                                        $pct = $repairTechMax > 0 ? max(4, ($t['job_count'] / $repairTechMax) * 100) : 4;
                                        ?>
                                        <div class="staff-bar-row">
                                            <span class="staff-bar-avatar"><?= e($initialsOf($t['full_name'])) ?></span>
                                            <div class="staff-bar-info">
                                                <span class="staff-bar-name"><?= e($t['full_name'] ?: 'Unassigned') ?></span>
                                                <div class="staff-bar-track"><div class="staff-bar-fill" style="width:<?= $pct ?>%"></div></div>
                                            </div>
                                            <span class="staff-bar-value"><?= (int) $t['job_count'] ?> jobs</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-head">
                                <h2 class="panel-title">Status Breakdown</h2>
                            </div>
                            <div class="staff-bars">
                                <?php
                                $statusSegments = [
                                    ['label' => 'Pending', 'value' => (int) $statusBreakdown['pending'], 'color' => '#f59e0b'],
                                    ['label' => 'In Progress', 'value' => (int) $statusBreakdown['in_progress'], 'color' => '#22d3ee'],
                                    ['label' => 'Completed', 'value' => (int) $statusBreakdown['completed'], 'color' => '#22c55e'],
                                ];
                                $statusTotal = array_sum(array_column($statusSegments, 'value'));
                                $statusMax = max(1, $statusTotal);
                                ?>
                                <?php if ($statusTotal === 0): ?>
                                    <p style="color:var(--text-500);padding:20px;text-align:center;font-size:0.88rem;">No repair data for this period.</p>
                                <?php endif; ?>
                                <?php foreach ($statusSegments as $seg): ?>
                                    <?php $spct = ($seg['value'] / $statusMax) * 100; ?>
                                    <div class="staff-bar-row">
                                        <span class="staff-bar-avatar" style="background:<?= e($seg['color']) ?>;color:#04222b"><?= e(substr($seg['label'], 0, 1)) ?></span>
                                        <div class="staff-bar-info">
                                            <span class="staff-bar-name"><?= e($seg['label']) ?></span>
                                            <div class="staff-bar-track"><div class="staff-bar-fill" style="width:<?= $spct ?>%;background:<?= e($seg['color']) ?>"></div></div>
                                        </div>
                                        <span class="staff-bar-value"><?= (int) $seg['value'] ?> &middot; <?= round($spct) ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="panel report-products-panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Recent Repair Jobs</h2>
                            <span class="panel-sub">Repair revenue in period: $<?= number_format((float) $repairOverview['revenue']) ?></span>
                        </div>
                        <div class="table-wrap">
                            <table class="user-table report-table">
                                <thead>
                                    <tr>
                                        <th>TICKET</th>
                                        <th>DEVICE</th>
                                        <th>CUSTOMER</th>
                                        <th>TECHNICIAN</th>
                                        <th>STATUS</th>
                                        <th>COST</th>
                                        <th>RECEIVED</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$recentRepairs): ?>
                                        <tr><td colspan="7" class="empty-row">No repair jobs for this period.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($recentRepairs as $r): ?>
                                        <tr>
                                            <td><span class="product-sku"><?= e($r['ticket_no']) ?></span></td>
                                            <td><?= e(trim((string) ($r['device_brand'] . ' ' . $r['device_model']))) ?></td>
                                            <td><?= e($r['customer_name'] ?: '&mdash;') ?></td>
                                            <td><?= e($r['technician_name'] ?: '&mdash;') ?></td>
                                            <td><span class="category-pill"><?= e($statusLabel($r['status'])) ?></span></td>
                                            <td class="cell-revenue">$<?= number_format((float) ($r['final_cost'] ?? $r['estimated_cost'] ?? 0)) ?></td>
                                            <td><?= e(relative_time($r['received_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($tab === 'revenue'): ?>

                    <div class="panel-grid report-panel-grid">
                        <div class="panel">
                            <div class="panel-head">
                                <h2 class="panel-title">Revenue Over Time</h2>
                            </div>
                            <div class="revenue-chart report-revenue-chart" id="revenueChart"></div>
                        </div>
                        <div class="panel">
                            <div class="panel-head">
                                <h2 class="panel-title">Revenue by Payment Method</h2>
                            </div>
                            <div class="staff-bars">
                                <?php if (!$revenueByPayment): ?>
                                    <p style="color:var(--text-500);padding:20px;text-align:center;font-size:0.88rem;">No completed sales in this period.</p>
                                <?php else: ?>
                                    <?php foreach ($revenueByPayment as $pm): ?>
                                        <?php $ppct = $paymentMax > 0 ? max(4, ($pm['revenue'] / $paymentMax) * 100) : 4; ?>
                                        <div class="staff-bar-row">
                                            <span class="staff-bar-avatar"><?= e(strtoupper(substr((string) $pm['payment_method'], 0, 1))) ?></span>
                                            <div class="staff-bar-info">
                                                <span class="staff-bar-name"><?= e(ucfirst((string) $pm['payment_method'])) ?></span>
                                                <div class="staff-bar-track"><div class="staff-bar-fill" style="width:<?= $ppct ?>%"></div></div>
                                            </div>
                                            <span class="staff-bar-value">$<?= number_format((float) $pm['revenue']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php else: ?>

                    <div class="panel-grid report-panel-grid">
                        <div class="panel">
                            <div class="panel-head">
                                <h2 class="panel-title">Stock by Category</h2>
                            </div>
                            <div class="staff-bars">
                                <?php if (!$inventoryByCategory): ?>
                                    <p style="color:var(--text-500);padding:20px;text-align:center;font-size:0.88rem;">No inventory data.</p>
                                <?php else: ?>
                                    <?php foreach ($inventoryByCategory as $c): ?>
                                        <?php $cpct = $categoryMaxUnits > 0 ? max(4, ($c['units'] / $categoryMaxUnits) * 100) : 4; ?>
                                        <div class="staff-bar-row">
                                            <span class="staff-bar-avatar"><?= e(strtoupper(substr((string) $c['category'], 0, 1))) ?></span>
                                            <div class="staff-bar-info">
                                                <span class="staff-bar-name"><?= e($c['category']) ?></span>
                                                <div class="staff-bar-track"><div class="staff-bar-fill" style="width:<?= $cpct ?>%"></div></div>
                                            </div>
                                            <span class="staff-bar-value"><?= (int) $c['units'] ?> units</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-head">
                                <h2 class="panel-title">Low Stock Items</h2>
                                <span class="panel-sub">At or below reorder level</span>
                            </div>
                            <div class="table-wrap">
                                <table class="user-table report-table">
                                    <thead>
                                        <tr>
                                            <th>PRODUCT</th>
                                            <th>SKU</th>
                                            <th>QTY</th>
                                            <th>REORDER LEVEL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$lowStockItems): ?>
                                            <tr><td colspan="4" class="empty-row">No low-stock items.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($lowStockItems as $li): ?>
                                            <tr>
                                                <td><span class="product-name"><?= e($li['name']) ?></span></td>
                                                <td><span class="product-sku"><?= e($li['sku']) ?></span></td>
                                                <td><?= (int) $li['quantity'] ?></td>
                                                <td><?= (int) $li['reorder_level'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </main>
        </div>
    </div>

    <?php if ($tab === 'sales' || $tab === 'revenue'): ?>
        <script>
            window.__reportData = {
                revenueSeries: <?= $seriesJson ?>,
                prevSeries: <?= $prevSeriesJson ?>,
                staffData: <?= $staffJson ?>,
                staffMax: <?= (float) $staffMax ?>,
                compare: <?= $compare ? 'true' : 'false' ?>
            };
        </script>
        <script src="<?= e(base_url('js/reports.js')) ?>"></script>
    <?php endif; ?>
</body>
</html>
