<?php
/**
 * Repair Job Detail — full history, costs, actions
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<string,mixed> $job
 * @var array<int,array<string,mixed>> $notes
 * @var bool $isAssigned
 * @var array{type:string,message:string}|null $flash
 */

function detailUrgencyClass(string $urgency): string {
    return ['urgent' => 'urgency--urgent', 'normal' => 'urgency--normal', 'low' => 'urgency--low'][$urgency] ?? 'urgency--normal';
}
function detailStatusBadge(string $status): string {
    $labels = ['received' => 'Received', 'diagnosing' => 'Diagnosing', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
    $cls = ['received' => 'status-pill--neutral', 'diagnosing' => 'status-pill--neutral', 'in_progress' => 'status-pill--info', 'completed' => 'status-pill--completed', 'delivered' => 'status-pill--completed', 'cancelled' => 'status-pill--danger'];
    return '<span class="status-pill ' . ($cls[$status] ?? '') . '">' . e($labels[$status] ?? $status) . '</span>';
}
$rid = (int) $job['repair_id'];
$canAct = $isAssigned && !in_array($job['status'], ['completed', 'delivered', 'cancelled'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($job['ticket_no']) ?> &mdash; MobileTech Pro</title>
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
                <a class="crumb" href="<?= e(base_url('/technician/board')) ?>">Repair Board</a>
                <span class="crumb-sep">&rsaquo;</span>
                <span class="crumb crumb-current"><?= e($job['ticket_no']) ?></span>
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
            <?php if ($flash): ?>
                <div class="flash flash--<?= e($flash['type'] ?? 'info') ?>" style="margin-bottom:16px"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="content-head">
                <div>
                    <h1 class="page-greeting"><?= e($job['ticket_no']) ?></h1>
                    <p class="page-sub"><?= detailStatusBadge($job['status']) ?> &nbsp; <span class="job-card-urgency <?= detailUrgencyClass($job['urgency']) ?>"><?= ucfirst($job['urgency']) ?></span></p>
                </div>
                <div class="content-actions">
                    <a href="<?= e(base_url('/technician/board')) ?>" class="btn-outline">&larr; Board</a>
                    <?php if ($job['has_notification']): ?>
                        <span class="jd-notify-tag">SMS sent</span>
                    <?php else: ?>
                        <button class="btn-outline btn-sm" type="button" id="notifyBtn" data-repair-id="<?= $rid ?>">Notify Customer</button>
                    <?php endif; ?>
                    <?php if ($canAct): ?>
                        <button class="btn-cyan btn-sm" type="button" id="markDoneBtn" data-repair-id="<?= $rid ?>">Mark Done</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="jd-grid">
                <div class="jd-main">
                    <div class="panel">
                        <div class="panel-head"><h2 class="panel-title">Customer</h2></div>
                        <table class="detail-table">
                            <tr><td class="detail-label">Name</td><td><?= e($job['customer_name'] ?? '—') ?></td></tr>
                            <tr><td class="detail-label">Phone</td><td><?= e($job['customer_phone'] ?? '—') ?></td></tr>
                        </table>
                    </div>

                    <div class="panel" style="margin-top:14px">
                        <div class="panel-head"><h2 class="panel-title">Device &amp; Issue</h2></div>
                        <table class="detail-table">
                            <tr><td class="detail-label">Device</td><td><?= e(trim(($job['device_brand'] ?? '') . ' ' . ($job['device_model'] ?? '')) ?: '—') ?></td></tr>
                            <tr><td class="detail-label">IMEI</td><td><span class="mono"><?= e($job['imei'] ?? '—') ?></span></td></tr>
                            <tr><td class="detail-label">Issue</td><td><?= e($job['reported_issue'] ?? '—') ?></td></tr>
                            <?php if (!empty($job['diagnosis'])): ?>
                            <tr><td class="detail-label">Diagnosis</td><td><?= e($job['diagnosis']) ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="panel" style="margin-top:14px">
                        <div class="panel-head"><h2 class="panel-title">Costs</h2></div>
                        <table class="detail-table">
                            <tr>
                                <td class="detail-label">Estimate</td>
                                <td>
                                    <?php if ($isAssigned && !in_array($job['status'], ['completed', 'delivered', 'cancelled'], true)): ?>
                                        <input type="number" class="slide-input" id="estCostInput" value="<?= number_format((float) ($job['estimated_cost'] ?? 0), 2, '.', '') ?>" min="0" step="0.01" style="max-width:160px">
                                        <button class="btn-outline btn-sm" type="button" id="saveCostBtn" data-repair-id="<?= $rid ?>">Save</button>
                                    <?php else: ?>
                                        $<?= number_format((float) ($job['estimated_cost'] ?? 0), 2) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="detail-label">Final</td>
                                <td>
                                    <?php if ($isAssigned): ?>
                                        <input type="number" class="slide-input" id="finalCostInput" value="<?= number_format((float) ($job['final_cost'] ?? 0), 2, '.', '') ?>" min="0" step="0.01" style="max-width:160px">
                                        <button class="btn-outline btn-sm" type="button" id="saveFinalCostBtn" data-repair-id="<?= $rid ?>">Save</button>
                                    <?php else: ?>
                                        $<?= number_format((float) ($job['final_cost'] ?? 0), 2) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="jd-side">
                    <div class="panel">
                        <div class="panel-head"><h2 class="panel-title">Assignment</h2></div>
                        <table class="detail-table">
                            <tr><td class="detail-label">Technician</td><td><?= e($job['technician_name'] ?? 'Unassigned') ?></td></tr>
                            <tr><td class="detail-label">Due Date</td><td><?= e($job['due_date'] ? date('M j, Y g:i A', strtotime($job['due_date'])) : '—') ?></td></tr>
                            <tr><td class="detail-label">Received</td><td><?= e(date('M j, Y g:i A', strtotime($job['received_at']))) ?></td></tr>
                            <?php if ($job['completed_at']): ?>
                            <tr><td class="detail-label">Completed</td><td><?= e(date('M j, Y g:i A', strtotime($job['completed_at']))) ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="panel" style="margin-top:14px;flex:1;min-height:0;display:flex;flex-direction:column">
                        <div class="panel-head"><h2 class="panel-title">Timeline (<?= count($notes) ?>)</h2></div>
                        <div class="jd-timeline" style="flex:1;overflow-y:auto;min-height:0">
                            <?php if (!$notes): ?>
                                <p style="color:var(--text-600);margin:0;font-size:0.84rem">No updates yet.</p>
                            <?php else: ?>
                                <?php foreach ($notes as $n): ?>
                                <div class="jd-timeline-item">
                                    <div class="jd-timeline-marker"></div>
                                    <div class="jd-timeline-body">
                                        <div class="jd-timeline-head">
                                            <span class="jd-timeline-user"><?= e($n['user_name'] ?? 'System') ?></span>
                                            <?php if ($n['status']): ?>
                                                <?= detailStatusBadge($n['status']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($n['note']): ?>
                                            <p class="jd-timeline-note"><?= e($n['note']) ?></p>
                                        <?php endif; ?>
                                        <span class="jd-timeline-time"><?= e(date('M j, Y g:i A', strtotime($n['created_at']))) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    window.__detail = {
        updateUrl: <?= json_encode(base_url('/technician/board/update'), JSON_UNESCAPED_UNICODE) ?>,
        completeUrl: <?= json_encode(base_url('/technician/board/complete'), JSON_UNESCAPED_UNICODE) ?>,
        notifyUrl: <?= json_encode(base_url('/technician/board/notify'), JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= e(base_url('js/repair-board.js')) ?>"></script>
</body>
</html>
