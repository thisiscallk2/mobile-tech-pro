<?php
/**
 * My Assigned Tasks — list view
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $jobs
 * @var array{type:string,message:string}|null $flash
 */

$urgencyClass = static function (string $u): string {
    return ['urgent' => 'urgency--urgent', 'normal' => 'urgency--normal', 'low' => 'urgency--low'][$u] ?? 'urgency--normal';
};
$statusLabel = static function (string $s): string {
    return ucwords(str_replace('_', ' ', $s));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/repair-board.css')) ?>">
</head>
<body class="dash-body">
<div class="dash-layout">

    <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

    <div class="dash-main">
        <header class="topbar">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <span class="crumb">Repairs</span>
                <span class="crumb-sep">&rsaquo;</span>
                <span class="crumb crumb-current">My Tasks</span>
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
                    <h1 class="page-greeting">My Tasks</h1>
                    <p class="page-sub">Repair jobs assigned to you</p>
                </div>
                <div class="content-actions">
                    <a class="btn-cyan" href="<?= e(base_url('/technician/board/create')) ?>">+ New Repair Job</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash flash--<?= e($flash['type'] ?? 'info') ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Device</th>
                            <th>Customer</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Final Cost</th>
                            <th>Due</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$jobs): ?>
                            <tr><td colspan="8" class="empty-row">No tasks assigned to you yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><strong><?= e($job['ticket_no']) ?></strong></td>
                                <td><?= e(trim((string) ($job['device_brand'] ?? '') . ' ' . ($job['device_model'] ?? '')) ?: 'Unknown device') ?></td>
                                <td><?= e($job['customer_name'] ?? 'Walk-in') ?></td>
                                <td><span class="job-card-urgency <?= e($urgencyClass($job['urgency'])) ?>"><?= e(ucfirst($job['urgency'])) ?></span></td>
                                <td><?= e($statusLabel($job['status'])) ?></td>
                                <td>$<?= number_format((float) ($job['final_cost'] ?? 0), 2) ?></td>
                                <td><?= e($job['due_date'] ? date('M j, Y', strtotime((string) $job['due_date'])) : '—') ?></td>
                                <td style="text-align:right">
                                    <a class="btn-outline btn-sm" href="<?= e(base_url('/technician/board/view/' . (int) $job['repair_id'])) ?>">View</a>
                                </td>
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
