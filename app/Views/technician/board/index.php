<?php
/**
 * Repair Job Board — Kanban view
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $pending
 * @var array<int,array<string,mixed>> $inProgress
 * @var array<int,array<string,mixed>> $completed
 * @var int $activeCount
 * @var int $overdue
 * @var int $dueToday
 * @var array<int,array<string,mixed>> $techs
 * @var int|null $technicianId
 * @var string $urgency
 * @var string $search
 * @var array{type:string,message:string}|null $flash
 * @var bool $hideTechnicianFilter
 * @var string $pageTitle
 * @var string $boardUrl
 */

function urgencyClass(string $urgency): string {
    return ['urgent' => 'urgency--urgent', 'normal' => 'urgency--normal', 'low' => 'urgency--low'][$urgency] ?? 'urgency--normal';
}

function avatarInitials(string $name): string {
    $parts = explode(' ', $name);
    $init = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if ($p !== '') $init .= strtoupper($p[0]);
    }
    return $init !== '' ? $init : '?';
}

function isOverdue(?string $dueDate, string $status): bool {
    if ($dueDate === null) return false;
    if (in_array($status, ['completed', 'delivered', 'cancelled'], true)) return false;
    return strtotime($dueDate) < time();
}

function dueLabel(?string $dueDate, string $status): string {
    if ($dueDate === null) return 'No due date';
    if (in_array($status, ['completed', 'delivered'], true)) return 'Done';
    if (isOverdue($dueDate, $status)) return 'Overdue';
    $ts = strtotime($dueDate);
    $today = strtotime('today');
    $diff = (int) round(($ts - $today) / 86400);
    if ($diff === 0) return 'Due today';
    if ($diff === 1) return 'Due tomorrow';
    return 'Due ' . date('M j', $ts);
}

$columns = [
    ['id' => 'pending', 'label' => 'Pending', 'count' => count($pending), 'color' => 'col-red', 'jobs' => $pending],
    ['id' => 'in_progress', 'label' => 'In Progress', 'count' => count($inProgress), 'color' => 'col-cyan', 'jobs' => $inProgress],
    ['id' => 'completed', 'label' => 'Ready for Pickup', 'count' => count($completed), 'color' => 'col-green', 'jobs' => $completed],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Job Board &mdash; MobileTech Pro</title>
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
                <span class="crumb crumb-current">Repair Board</span>
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

        <?php if ($flash): ?>
            <div class="flash flash--<?= e($flash['type'] ?? 'info') ?>" style="margin:0 24px -8px"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <main class="content board-main">
            <div class="board-header">
                <div>
                    <h1 class="page-greeting"><?= e($pageTitle) ?></h1>
                    <p class="page-sub">Drag and drop jobs across stages &middot; <?= $activeCount ?> active jobs today.</p>
                </div>
                <div class="board-header-actions">
                    <button class="btn-outline btn-sm" type="button" title="Refresh" onclick="location.reload()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </button>
                    <button class="btn-cyan btn-sm" type="button" onclick="window.location='<?= e(base_url('/technician/board/create')) ?>'">+ New Repair Job</button>
                </div>
            </div>

            <div class="board-filters" id="boardFilters">
                <?php if (!$hideTechnicianFilter): ?>
                    <select class="board-select" id="filterTechnician">
                        <option value="">All Technicians</option>
                        <?php foreach ($techs as $t): ?>
                            <option value="<?= (int) $t['user_id'] ?>"<?= $technicianId === (int) $t['user_id'] ? ' selected' : '' ?>><?= e($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <div class="board-urgency-pills" id="urgencyPills">
                    <button class="urgency-pill<?= $urgency === '' ? ' is-active' : '' ?>" data-urgency="" type="button">All</button>
                    <button class="urgency-pill<?= $urgency === 'low' ? ' is-active' : '' ?>" data-urgency="low" type="button">Low</button>
                    <button class="urgency-pill<?= $urgency === 'normal' ? ' is-active' : '' ?>" data-urgency="normal" type="button">Normal</button>
                    <button class="urgency-pill<?= $urgency === 'urgent' ? ' is-active' : '' ?>" data-urgency="urgent" type="button">Urgent</button>
                </div>
                <div class="board-search-wrap">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" class="board-search" id="filterSearch" placeholder="Search job ID or customer name..." value="<?= e($search) ?>">
                </div>
                <div class="board-badges">
                    <?php if ($overdue > 0): ?>
                        <span class="board-badge board-badge--overdue"><?= $overdue ?> Overdue</span>
                    <?php endif; ?>
                    <?php if ($dueToday > 0): ?>
                        <span class="board-badge board-badge--due-today"><?= $dueToday ?> Due Today</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="board-columns" id="boardColumns">
                <?php foreach ($columns as $col): ?>
                <div class="board-column <?= $col['color'] ?>" data-column="<?= $col['id'] ?>" id="col-<?= $col['id'] ?>">
                    <div class="board-column-head">
                        <span class="board-column-title"><?= $col['label'] ?></span>
                        <span class="board-column-count"><?= $col['count'] ?></span>
                    </div>
                    <div class="board-column-body">
                        <?php foreach ($col['jobs'] as $job):
                            $rid = (int) $job['repair_id'];
                            $overdue = isOverdue($job['due_date'], $job['status']);
                            $nextCol = \App\Models\RepairJob::nextStatus(\App\Models\RepairJob::statusToColumn($job['status']));
                        ?>
                        <div class="job-card" draggable="true" data-repair-id="<?= $rid ?>" data-column="<?= $col['id'] ?>">
                            <div class="job-card-top">
                                <span class="job-card-id">#<?= e($job['ticket_no']) ?></span>
                                <?php if ($job['has_notification']): ?>
                                    <span class="job-card-sms">SMS sent</span>
                                <?php endif; ?>
                            </div>
                            <div class="job-card-customer"><?= e($job['customer_name'] ?? 'Walk-in') ?></div>
                            <div class="job-card-meta">
                                <span class="job-card-urgency <?= urgencyClass($job['urgency']) ?>"><?= ucfirst($job['urgency']) ?></span>
                                <?php if ($job['customer_phone']): ?>
                                    <span class="job-card-phone"><?= e($job['customer_phone']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="job-card-device">
                                <?= e(trim(($job['device_brand'] ?? '') . ' ' . ($job['device_model'] ?? '')) ?: 'Unknown device') ?>
                            </div>
                            <div class="job-card-issue">
                                <?= e($job['reported_issue'] ?? 'No description') ?>
                            </div>
                            <div class="job-card-foot">
                                <div class="job-card-tech">
                                    <?php if ($job['technician_name']): ?>
                                        <span class="job-card-avatar"><?= e(avatarInitials($job['technician_name'])) ?></span>
                                    <?php endif; ?>
                                    <span class="job-card-due <?= $overdue ? 'job-card-due--overdue' : '' ?>">
                                        <?= e(dueLabel($job['due_date'], $job['status'])) ?>
                                    </span>
                                </div>
                                <div class="job-card-actions">
                                    <button class="job-btn job-btn--view" type="button" title="View" data-action="view" data-repair-id="<?= $rid ?>" onclick="window.location='<?= e(base_url('/technician/board/view/' . $rid)) ?>'">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button class="job-btn job-btn--note" type="button" title="Add Note" data-action="note" data-repair-id="<?= $rid ?>">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    </button>
                                    <?php if ($nextCol !== null): ?>
                                    <button class="job-btn job-btn--done" type="button" title="Mark Done" data-action="done" data-repair-id="<?= $rid ?>" data-next-column="<?= $nextCol ?>">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="job-card-note-form" id="noteForm-<?= $rid ?>" hidden>
                                <textarea class="job-note-input" id="noteInput-<?= $rid ?>" rows="2" placeholder="Add a note..."></textarea>
                                <div class="job-note-actions">
                                    <button class="btn-cyan btn-sm" type="button" data-action="save-note" data-repair-id="<?= $rid ?>">Save</button>
                                    <button class="btn-outline btn-sm" type="button" data-action="cancel-note" data-repair-id="<?= $rid ?>">Cancel</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$col['jobs']): ?>
                            <p class="board-column-empty">No jobs</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<script>
    window.__board = {
        moveUrl: <?= json_encode(base_url('/technician/board/move'), JSON_UNESCAPED_UNICODE) ?>,
        noteUrl: <?= json_encode(base_url('/technician/board/note'), JSON_UNESCAPED_UNICODE) ?>,
        boardUrl: <?= json_encode(base_url($boardUrl), JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= e(base_url('js/repair-board.js')) ?>"></script>
</body>
</html>
