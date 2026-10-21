<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\RepairJob;
use App\Models\Report;
use DateTime;

class ReportController extends Controller
{
    public function index(): void
    {
        $user = AuthMiddleware::requireRole('admin');

        $isPrint = ($_GET['print'] ?? '0') === '1';

        $tab = $_GET['tab'] ?? 'sales';
        $tabs = ['sales', 'repair', 'revenue', 'inventory'];
        if (!in_array($tab, $tabs, true)) {
            $tab = 'sales';
        }

        $granularity = $_GET['period'] ?? 'weekly';
        if (!in_array($granularity, ['daily', 'weekly', 'monthly'], true)) {
            $granularity = 'weekly';
        }

        $from = ($_GET['from'] ?? '') !== '' ? $_GET['from'] : null;
        $to = ($_GET['to'] ?? '') !== '' ? $_GET['to'] : null;
        $staffId = ($_GET['staff'] ?? '') !== '' ? (int) $_GET['staff'] : null;
        $compare = ($_GET['compare'] ?? '0') === '1';

        $reportModel = new Report();

        $now = new DateTime('now');
        if ($from === null && $to === null) {
            $from = $now->format('Y-m-01');
            $to = $now->format('Y-m-t');
        }
        $subtextMonth = ($from !== null ? (new DateTime($from))->format('F Y') : $now->format('F Y'));

        $data = [
            'user' => $user,
            'active' => 'reports',
            'today' => $now->format('M j, Y'),
            'tab' => $tab,
            'tabs' => $tabs,
            'granularity' => $granularity,
            'from' => $from ?? '',
            'to' => $to ?? '',
            'staffId' => $staffId,
            'compare' => $compare,
            'allStaff' => $reportModel->getStaff(),
            'subtextMonth' => $subtextMonth,
        ];

        switch ($tab) {
            case 'repair':
                $this->buildRepairData($data, $reportModel, $from, $to, $staffId);
                break;
            case 'revenue':
                $this->buildRevenueData($data, $reportModel, $from, $to, $granularity, $compare);
                break;
            case 'inventory':
                $this->buildInventoryData($data, $reportModel);
                break;
            case 'sales':
            default:
                $this->buildSalesData($data, $reportModel, $from, $to, $staffId, $granularity, $compare);
                break;
        }

        $this->view($isPrint ? 'admin/reports/print' : 'admin/reports/index', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildSalesData(array &$data, Report $reportModel, ?string $from, ?string $to, ?int $staffId, string $granularity, bool $compare): void
    {
        $revenue = $reportModel->getTotalRevenue($from, $to);
        $transactions = $reportModel->getTotalTransactions($from, $to);
        $avgOrder = $reportModel->getAvgOrderValue($from, $to);

        $prevFrom = null;
        $prevTo = null;
        $prevRevenue = 0;
        $prevTransactions = 0;
        $prevAvgOrder = 0;

        if ($compare && $from !== null && $to !== null) {
            $fromDt = new DateTime($from);
            $toDt = new DateTime($to);
            $diff = $fromDt->diff($toDt);
            $prevTo = clone $fromDt;
            $prevTo->modify('-1 day');
            $prevFrom = clone $prevTo;
            $prevFrom->modify('-' . $diff->days . ' days');
            $prevFromStr = $prevFrom->format('Y-m-d');
            $prevToStr = $prevTo->format('Y-m-d');

            $prevRevenue = $reportModel->getTotalRevenue($prevFromStr, $prevToStr);
            $prevTransactions = $reportModel->getTotalTransactions($prevFromStr, $prevToStr);
            $prevAvgOrder = $reportModel->getAvgOrderValue($prevFromStr, $prevToStr);
        }

        $currentRevenueSeries = $reportModel->getRevenueSeries($granularity, $from, $to);
        $previousRevenueSeries = [];
        if ($compare && $prevFrom !== null) {
            $previousRevenueSeries = $reportModel->getRevenueSeries(
                $granularity,
                $prevFrom->format('Y-m-d'),
                $prevTo->format('Y-m-d')
            );
        }

        $salesByStaff = $reportModel->getSalesByStaff($from, $to, $staffId);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 6;
        $totalProducts = $reportModel->countTopSellingProducts($from, $to);
        $totalPages = max(1, (int) ceil($totalProducts / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $topProducts = $reportModel->getTopSellingProducts($from, $to, $perPage, $offset);

        $topProductsWithRank = [];
        $prevRankRevenue = [];
        if ($compare && $prevFrom !== null) {
            $prevProducts = $reportModel->getTopSellingProducts(
                $prevFrom->format('Y-m-d'),
                $prevTo->format('Y-m-d'),
                100,
                0
            );
            foreach ($prevProducts as $pp) {
                $prevRankRevenue[(int) $pp['product_id']] = (float) $pp['total_revenue'];
            }
        }

        foreach ($topProducts as $i => $p) {
            $p['rank'] = $offset + $i + 1;
            $pid = (int) $p['product_id'];
            $prevRev = $prevRankRevenue[$pid] ?? null;
            $curRev = (float) $p['total_revenue'];
            $p['trend'] = null;
            if ($prevRev !== null && $prevRev > 0) {
                $pct = round((($curRev - $prevRev) / $prevRev) * 100, 1);
                $p['trend'] = ['value' => abs($pct), 'direction' => $pct >= 0 ? 'up' : 'down'];
            } elseif ($curRev > 0) {
                $p['trend'] = ['value' => 100.0, 'direction' => 'up'];
            }
            $topProductsWithRank[] = $p;
        }

        $revenueTarget = 666000;
        $aovTarget = 5500;
        $transactionsTarget = 1800;

        $data['stats'] = [
            [
                'key' => 'revenue',
                'label' => 'Total Revenue',
                'value' => '$' . number_format($revenue),
                'delta' => $this->deltaPct($revenue, $prevRevenue),
                'progress' => $revenueTarget > 0 ? min(100, round(($revenue / $revenueTarget) * 100)) : 0,
                'targetLabel' => $revenueTarget > 0 ? round(($revenue / $revenueTarget) * 100) . '% of target' : '',
            ],
            [
                'key' => 'aov',
                'label' => 'Avg Order Value',
                'value' => '$' . number_format($avgOrder),
                'delta' => $this->deltaPct($avgOrder, $prevAvgOrder),
                'progress' => $aovTarget > 0 ? min(100, round(($avgOrder / $aovTarget) * 100)) : 0,
                'targetLabel' => $aovTarget > 0 ? round(($avgOrder / $aovTarget) * 100) . '% of target' : '',
            ],
            [
                'key' => 'transactions',
                'label' => 'Total Transactions',
                'value' => number_format($transactions),
                'delta' => $this->deltaPct((float) $transactions, (float) $prevTransactions),
                'progress' => $transactionsTarget > 0 ? min(100, round(($transactions / $transactionsTarget) * 100)) : 0,
                'targetLabel' => $transactionsTarget > 0 ? round(($transactions / $transactionsTarget) * 100) . '% of target' : '',
            ],
        ];
        $data['revenueSeries'] = $currentRevenueSeries;
        $data['previousRevenueSeries'] = $previousRevenueSeries;
        $data['salesByStaff'] = $salesByStaff;
        $data['staffMaxRevenue'] = $this->maxStaffRevenue($salesByStaff);
        $data['topProducts'] = $topProductsWithRank;
        $data['page'] = $page;
        $data['totalPages'] = $totalPages;
        $data['totalProducts'] = $totalProducts;
        $data['shown'] = count($topProductsWithRank);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildRepairData(array &$data, Report $reportModel, ?string $from, ?string $to, ?int $staffId): void
    {
        $overview = $reportModel->getRepairOverview($from, $to, $staffId);
        $byTechnician = $reportModel->getRepairsByTechnician($from, $to);
        $recent = $reportModel->getRecentRepairs($from, $to, $staffId, 15);

        $data['stats'] = [
            ['key' => 'total', 'label' => 'Total Repair Jobs', 'value' => number_format($overview['total']), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
            ['key' => 'completed', 'label' => 'Completed', 'value' => number_format($overview['completed']), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
            ['key' => 'in_progress', 'label' => 'In Progress', 'value' => number_format($overview['in_progress']), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
            ['key' => 'overdue', 'label' => 'Overdue', 'value' => number_format($overview['overdue']), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
        ];

        $maxJobs = 0;
        foreach ($byTechnician as $t) {
            if ($t['job_count'] > $maxJobs) {
                $maxJobs = $t['job_count'];
            }
        }

        $data['repairOverview'] = $overview;
        $data['repairsByTechnician'] = $byTechnician;
        $data['repairTechMax'] = $maxJobs;
        $data['recentRepairs'] = $recent;
        $data['statusBreakdown'] = (new RepairJob())->getRepairStatusBreakdown();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildRevenueData(array &$data, Report $reportModel, ?string $from, ?string $to, string $granularity, bool $compare): void
    {
        $revenue = $reportModel->getTotalRevenue($from, $to);
        $transactions = $reportModel->getTotalTransactions($from, $to);
        $avgOrder = $reportModel->getAvgOrderValue($from, $to);

        $data['stats'] = [
            ['key' => 'revenue', 'label' => 'Total Revenue', 'value' => '$' . number_format($revenue), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
            ['key' => 'transactions', 'label' => 'Total Transactions', 'value' => number_format($transactions), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
            ['key' => 'aov', 'label' => 'Avg Order Value', 'value' => '$' . number_format($avgOrder), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
        ];

        $data['revenueSeries'] = $reportModel->getRevenueSeries($granularity, $from, $to);
        $data['previousRevenueSeries'] = [];
        $data['revenueByPayment'] = $reportModel->getRevenueByPaymentMethod($from, $to);

        $maxPayment = 0.0;
        foreach ($data['revenueByPayment'] as $p) {
            if ($p['revenue'] > $maxPayment) {
                $maxPayment = $p['revenue'];
            }
        }
        $data['paymentMax'] = $maxPayment;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildInventoryData(array &$data, Report $reportModel): void
    {
        $stats = $reportModel->getInventoryStats();

        $data['stats'] = [
            ['key' => 'products', 'label' => 'Total Products', 'value' => number_format($stats['products']), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
            ['key' => 'units', 'label' => 'Units In Stock', 'value' => number_format($stats['units']), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
            ['key' => 'stock_value', 'label' => 'Stock Value', 'value' => '$' . number_format($stats['stock_value']), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
            ['key' => 'low_stock', 'label' => 'Low Stock Items', 'value' => number_format($stats['low_stock']), 'delta' => null, 'progress' => 0, 'targetLabel' => ''],
        ];

        $inventoryByCategory = $reportModel->getInventoryByCategory();
        $maxUnits = 0;
        foreach ($inventoryByCategory as $c) {
            if ($c['units'] > $maxUnits) {
                $maxUnits = $c['units'];
            }
        }

        $data['inventoryStats'] = $stats;
        $data['inventoryByCategory'] = $inventoryByCategory;
        $data['categoryMaxUnits'] = $maxUnits;
        $data['lowStockItems'] = $reportModel->getLowStockItems();
    }

    public function exportCsv(): void
    {
        AuthMiddleware::requireRole('admin');

        $from = ($_GET['from'] ?? '') !== '' ? $_GET['from'] : null;
        $to = ($_GET['to'] ?? '') !== '' ? $_GET['to'] : null;

        $reportModel = new Report();
        $products = $reportModel->getTopSellingProducts($from, $to, 500, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sales-report-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Rank', 'Product Name', 'SKU', 'Category', 'Units Sold', 'Revenue']);

        $rank = 1;
        foreach ($products as $p) {
            fputcsv($out, [
                $rank,
                $p['name'],
                $p['sku'],
                $p['category_name'] ?? '—',
                $p['units_sold'],
                number_format($p['total_revenue'], 2),
            ]);
            $rank++;
        }

        fclose($out);
        exit;
    }

    private function deltaPct(float $current, float $previous): array
    {
        if ($previous <= 0.0) {
            return $current > 0.0
                ? ['text' => 'New', 'tone' => 'up']
                : ['text' => '0%', 'tone' => 'neutral'];
        }
        $pct = round((($current - $previous) / $previous) * 100, 1);
        $sign = $pct >= 0 ? '+' : '';
        return [
            'text' => $sign . $pct . '%',
            'tone' => $pct >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $staff
     */
    private function maxStaffRevenue(array $staff): float
    {
        $max = 0.0;
        foreach ($staff as $s) {
            $v = (float) ($s['total_revenue'] ?? 0);
            if ($v > $max) {
                $max = $v;
            }
        }
        return $max;
    }
}
