<?php
/**
 * Reports & Analytics — Printable view
 * Open this page, then print via Ctrl+P / window.print().
 *
 * @var array<string,mixed> $user
 * @var string $tab
 * @var string $granularity
 * @var string $from
 * @var string $to
 * @var array<int,array<string,mixed>> $stats
 * @var array<int,array{label:string,value:float}> $revenueSeries
 * @var array<int,array{label:string,value:float}> $previousRevenueSeries
 * @var array<int,array<string,mixed>> $salesByStaff
 * @var float $staffMaxRevenue
 * @var array<int,array<string,mixed>> $topProducts
 * @var string $subtextMonth
 * @var array<string,int|float> $repairOverview
 * @var array<int,array<string,mixed>> $repairsByTechnician
 * @var array<int,array<string,mixed>> $recentRepairs
 * @var array<int,array<string,mixed>> $revenueByPayment
 * @var array<int,array<string,mixed>> $inventoryByCategory
 * @var array<int,array<string,mixed>> $lowStockItems
 */

$tabLabels = [
    'sales' => 'Sales Report',
    'repair' => 'Repair Report',
    'revenue' => 'Revenue Report',
    'inventory' => 'Inventory Report',
];

$statusLabel = static function (?string $status): string {
    return ucwords(str_replace('_', ' ', (string) $status));
};

$revenueSeriesJson = json_encode($revenueSeries ?? [], JSON_UNESCAPED_UNICODE);
$prevSeriesJson = json_encode($previousRevenueSeries ?? [], JSON_UNESCAPED_UNICODE);
$staffJson = json_encode($salesByStaff ?? [], JSON_UNESCAPED_UNICODE);
$staffMax = ($staffMaxRevenue ?? 0) > 0 ? $staffMaxRevenue : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Print &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/reports.css')) ?>">
    <style>
        @media print {
            .dash-layout { display: block; }
            .sidebar, .topbar, .report-tabs, .report-filters, .content-actions { display: none !important; }
            .dash-main { padding: 0; }
            .content { padding: 20px; }
            .stat-grid { grid-template-columns: repeat(3, 1fr); }
            .report-panel-grid { page-break-inside: avoid; }
            .report-products-panel { page-break-inside: avoid; }
            body { background: #fff; color: #000; }
            .stat-card, .panel { background: #f8f9fa; border: 1px solid #ddd; color: #000; }
            .stat-value, .panel-title, .product-name { color: #000; }
            .stat-label, .page-sub, .category-pill, .staff-bar-name { color: #555; }
            .staff-bar-track { background: #e9ecef; }
            .rank-badge { background: #e9ecef; color: #000; border-color: #ccc; }
        }
    </style>
</head>
<body class="dash-body" onload="window.print();window.onafterprint=function(){window.close();}">
    <div class="dash-layout">
        <div class="dash-main">
            <main class="content">
                <div class="content-head">
                    <div>
                        <h1 class="page-greeting"><?= e($tabLabels[$tab] ?? 'Reports') ?></h1>
                        <p class="page-sub">Insights and performance metrics &middot; <?= e($subtextMonth) ?></p>
                    </div>
                </div>

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
                        </div>
                        <div class="table-wrap">
                            <table class="user-table report-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>RANK</th>
                                        <th>PRODUCT</th>
                                        <th>CATEGORY</th>
                                        <th>UNITS SOLD</th>
                                        <th>REVENUE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$topProducts): ?>
                                        <tr><td colspan="5" class="empty-row">No sales data.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($topProducts as $p): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge"><?= (int) $p['rank'] ?></span>
                                            </td>
                                            <td>
                                                <span class="product-name"><?= e($p['name']) ?></span>
                                                <br><small style="color:#888"><?= e($p['sku']) ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($p['category_name'])): ?>
                                                    <span class="category-pill"><?= e($p['category_name']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= number_format((int) $p['units_sold']) ?></td>
                                            <td>$<?= number_format((float) $p['total_revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($tab === 'repair'): ?>

                    <div class="panel report-products-panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Repair Jobs</h2>
                        </div>
                        <div class="table-wrap">
                            <table class="user-table report-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>TICKET</th>
                                        <th>DEVICE</th>
                                        <th>CUSTOMER</th>
                                        <th>TECHNICIAN</th>
                                        <th>STATUS</th>
                                        <th>COST</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$recentRepairs): ?>
                                        <tr><td colspan="6" class="empty-row">No repair jobs.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($recentRepairs as $r): ?>
                                        <tr>
                                            <td><?= e($r['ticket_no']) ?></td>
                                            <td><?= e(trim((string) ($r['device_brand'] . ' ' . $r['device_model']))) ?></td>
                                            <td><?= e($r['customer_name'] ?: '—') ?></td>
                                            <td><?= e($r['technician_name'] ?: '—') ?></td>
                                            <td><?= e($statusLabel($r['status'])) ?></td>
                                            <td>$<?= number_format((float) ($r['final_cost'] ?? $r['estimated_cost'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($tab === 'revenue'): ?>

                    <div class="panel report-products-panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Revenue by Payment Method</h2>
                        </div>
                        <div class="table-wrap">
                            <table class="user-table report-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>PAYMENT METHOD</th>
                                        <th>TRANSACTIONS</th>
                                        <th>REVENUE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$revenueByPayment): ?>
                                        <tr><td colspan="3" class="empty-row">No completed sales.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($revenueByPayment as $pm): ?>
                                        <tr>
                                            <td><?= e(ucfirst((string) $pm['payment_method'])) ?></td>
                                            <td><?= number_format((int) $pm['transaction_count']) ?></td>
                                            <td>$<?= number_format((float) $pm['revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php else: ?>

                    <div class="panel report-products-panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Low Stock Items</h2>
                        </div>
                        <div class="table-wrap">
                            <table class="user-table report-table" style="width:100%">
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
                                            <td><?= e($li['name']) ?></td>
                                            <td><?= e($li['sku']) ?></td>
                                            <td><?= (int) $li['quantity'] ?></td>
                                            <td><?= (int) $li['reorder_level'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php if ($tab === 'sales'): ?>
        <script>
            window.__reportData = {
                revenueSeries: <?= $revenueSeriesJson ?>,
                prevSeries: <?= $prevSeriesJson ?>,
                staffData: <?= $staffJson ?>,
                staffMax: <?= (float) $staffMax ?>,
                compare: false
            };
        </script>
        <script src="<?= e(base_url('js/reports.js')) ?>"></script>
    <?php endif; ?>
</body>
</html>
