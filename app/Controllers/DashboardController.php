<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\Inventory;
use App\Models\RepairJob;
use App\Models\Sale;
use DateTime;

/**
 * Admin dashboard -- role-guarded (admin only). All headline numbers come
 * from live SUM/COUNT queries so a manual DB insert changes them on reload.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        $user = AuthMiddleware::requireRole('admin', 'sales_staff', 'technician');

        $userType = (string) ($user['user_type'] ?? '');
        $userId = (int) ($user['user_id'] ?? 0);
        $isAdmin = $userType === 'admin';
        $isSales = $userType === 'sales_staff';
        $isTechnician = $userType === 'technician';

        $sale = new Sale();
        $repair = new RepairJob();
        $inventory = new Inventory();

        // --- Month windows for month-over-month comparisons ---------------
        $thisMonthStart = (new DateTime('first day of this month 00:00'))->format('Y-m-d H:i:s');
        $nextMonthStart = (new DateTime('first day of next month 00:00'))->format('Y-m-d H:i:s');
        $lastMonthStart = (new DateTime('first day of last month 00:00'))->format('Y-m-d H:i:s');

        $scopeUserId = $isSales ? $userId : null;

        // --- Role-scoped financials --------------------------------------
        if ($isTechnician) {
            $revenueThis = $repair->getRepairRevenue($userId);
            $costThis = 0.0;
            $profitThis = $revenueThis;
            $revenueLast = 0.0;
            $profitLast = 0.0;
            $salesThis = 0;
            $salesLast = 0;
        } else {
            $revenueThis = $sale->getTotalRevenue($thisMonthStart, $nextMonthStart, $scopeUserId);
            $revenueLast = $sale->getTotalRevenue($lastMonthStart, $thisMonthStart, $scopeUserId);
            $costThis = $sale->getCostPrice($thisMonthStart, $nextMonthStart, $scopeUserId);
            $profitThis = $revenueThis - $costThis;
            $profitLast = $revenueLast - $sale->getCostPrice($lastMonthStart, $thisMonthStart, $scopeUserId);
            $salesThis = $sale->getSalesCount($thisMonthStart, $nextMonthStart, $scopeUserId);
            $salesLast = $sale->getSalesCount($lastMonthStart, $thisMonthStart, $scopeUserId);
        }

        $pendingRepairs = $repair->getPendingRepairsCount();
        $lowStock = $inventory->getLowStockCount();

        // --- Stat cards ---------------------------------------------------
        $stats = [];

        $stats[] = [
            'key' => 'revenue',
            'label' => $isTechnician ? 'Repair Revenue' : ($isSales ? 'My Revenue' : 'Total Revenue'),
            'value' => '$' . number_format($revenueThis),
            'chip' => $isTechnician
                ? ['text' => 'Repairs', 'tone' => 'neutral']
                : $this->deltaChip($revenueThis, $revenueLast),
            'caption' => $isTechnician
                ? 'from completed repairs'
                : 'vs $' . number_format($revenueLast) . ' last month',
        ];

        if ($isAdmin) {
            $stats[] = [
                'key' => 'cost',
                'label' => 'Cost Price',
                'value' => '$' . number_format($costThis),
                'chip' => ['text' => 'COGS', 'tone' => 'neutral'],
                'caption' => 'cost of goods sold this month',
            ];
        }

        $stats[] = [
            'key' => 'profit',
            'label' => 'Profit',
            'value' => '$' . number_format($profitThis),
            'chip' => $isTechnician
                ? ['text' => 'Net', 'tone' => $profitThis >= 0 ? 'up' : 'down']
                : $this->deltaChip($profitThis, $profitLast),
            'caption' => $isTechnician
                ? 'repair revenue (no parts cost tracked)'
                : 'revenue minus cost',
        ];

        if (!$isTechnician) {
            $stats[] = [
                'key' => 'sales',
                'label' => 'Sales Count',
                'value' => number_format($salesThis),
                'chip' => $this->deltaChip((float) $salesThis, (float) $salesLast),
                'caption' => 'vs ' . number_format($salesLast) . ' last month',
            ];
        }

        if (!$isSales) {
            $stats[] = [
                'key' => 'repairs',
                'label' => 'Pending Repairs',
                'value' => number_format($pendingRepairs),
                'chip' => $pendingRepairs > 0
                    ? ['text' => 'Active', 'tone' => 'neutral']
                    : ['text' => 'Clear', 'tone' => 'up'],
                'caption' => 'currently in the workshop',
            ];
        }

        if ($isAdmin) {
            $stats[] = [
                'key' => 'lowstock',
                'label' => 'Low Stock Items',
                'value' => number_format($lowStock),
                'chip' => $lowStock > 0
                    ? ['text' => 'Critical', 'tone' => 'critical']
                    : ['text' => 'OK', 'tone' => 'up'],
                'caption' => 'at or below reorder level',
            ];
        }

        // --- Repair donut breakdown --------------------------------------
        $breakdown = $repair->getRepairStatusBreakdown();
        $repairSegments = [
            ['label' => 'Pending', 'value' => $breakdown['pending'], 'color' => '#f59e0b'],
            ['label' => 'In Progress', 'value' => $breakdown['in_progress'], 'color' => '#22d3ee'],
            ['label' => 'Completed', 'value' => $breakdown['completed'], 'color' => '#22c55e'],
        ];

        // --- Revenue chart series (sales staff sees only their own) -------
        if ($isTechnician) {
            $revenueSeries = ['daily' => [], 'weekly' => [], 'monthly' => []];
        } else {
            $chartUserId = $isSales ? $userId : null;
            $revenueSeries = [
                'daily' => $sale->getRevenueByDay(7, $chartUserId),
                'weekly' => $sale->getRevenueByWeek(7, $chartUserId),
                'monthly' => $sale->getRevenueByMonth(7, $chartUserId),
            ];
        }

        // --- Admin drill-down: per-cashier revenue / cost / profit --------
        $salesByStaff = $isAdmin
            ? $sale->getSalesSummaryByUser($thisMonthStart, $nextMonthStart)
            : [];

        $this->view('admin/dashboard', [
            'user' => $user,
            'active' => 'dashboard',
            'today' => (new DateTime('now'))->format('M j, Y'),
            'greeting' => $this->greeting() . ', ' . $this->firstName($user),
            'badgeCount' => $pendingRepairs + $lowStock,
            'stats' => $stats,
            'repairSegments' => $repairSegments,
            'repairTotal' => $breakdown['total'],
            'revenueSeries' => $revenueSeries,
            'salesByStaff' => $salesByStaff,
            'userType' => $userType,
        ]);
    }

    /**
     * Percentage-change chip comparing a current value to a previous one.
     *
     * @return array{text:string, tone:string}
     */
    private function deltaChip(float $current, float $previous): array
    {
        if ($previous <= 0.0) {
            return $current > 0.0
                ? ['text' => 'New', 'tone' => 'up']
                : ['text' => '0%', 'tone' => 'neutral'];
        }

        $pct = (($current - $previous) / $previous) * 100;
        $rounded = (int) round($pct);
        $sign = $rounded >= 0 ? '+' : '';

        return [
            'text' => $sign . $rounded . '%',
            'tone' => $rounded >= 0 ? 'up' : 'down',
        ];
    }

    private function greeting(): string
    {
        $hour = (int) (new DateTime('now'))->format('G');
        if ($hour < 12) {
            return 'Good morning';
        }
        if ($hour < 18) {
            return 'Good afternoon';
        }
        return 'Good evening';
    }

    /**
     * @param array<string, mixed> $user
     */
    private function firstName(array $user): string
    {
        $full = trim((string) ($user['full_name'] ?? ''));
        if ($full === '') {
            return (string) ($user['username'] ?? 'there');
        }
        $parts = explode(' ', $full);
        return $parts[0];
    }
}
