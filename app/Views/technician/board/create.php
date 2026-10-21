<?php
/**
 * New Repair Job — intake form
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $techs
 * @var array<string,mixed> $formOld
 * @var string|null $formError
 */

$fv = function(string $key, string $default = '') use ($formOld): string {
    return e((string) ($formOld[$key] ?? $default));
};
$sel = function(string $key, string $value) use ($formOld): string {
    return ((string) ($formOld[$key] ?? '') === $value) ? ' selected' : '';
};
$checked = function(string $value) use ($formOld): string {
    return ((string) ($formOld['urgency'] ?? 'normal') === $value) ? ' checked' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Repair Job &mdash; MobileTech Pro</title>
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
                <span class="crumb crumb-current">New Repair Job</span>
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
                    <h1 class="page-greeting">New Repair Job</h1>
                    <p class="page-sub">Capture customer, device, and issue details</p>
                </div>
                <div class="content-actions">
                    <a href="<?= e(base_url('/technician/board')) ?>" class="btn-outline">&larr; Back to Board</a>
                </div>
            </div>

            <?php if ($formError): ?>
                <div class="flash flash--error" style="margin-bottom:16px"><?= e($formError) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(base_url('/technician/board/create')) ?>" class="create-job-form" autocomplete="off">
                <input type="hidden" name="customer_id" id="customerIdInput" value="<?= $fv('customer_id') ?>">

                <div class="cj-section">
                    <h2 class="cj-section-title">Customer</h2>
                    <div class="cj-customer-search">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" class="cj-input" id="custSearch" placeholder="Search existing customer by name, phone, or email...">
                    </div>
                    <div class="cj-search-results" id="custResults" hidden></div>
                    <div class="cj-selected-customer<?= ($formOld['customer_id'] ?? 0) > 0 ? ' is-visible' : '' ?>" id="selectedCust">
                        <span id="selectedCustName"><?= $fv('customer_name') ?></span>
                        <button type="button" id="clearCustBtn" class="cj-remove">&times;</button>
                    </div>
                    <button type="button" class="btn-outline btn-sm" id="newCustToggle">+ Register New Customer</button>
                    <div class="cj-new-customer" id="newCustFields" hidden>
                        <label class="slide-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="customer_name" class="slide-input" id="custNameField" value="<?= $fv('customer_name') ?>" maxlength="120">
                        <label class="slide-label">Phone</label>
                        <input type="text" name="customer_phone" class="slide-input" id="custPhoneField" value="<?= $fv('customer_phone') ?>" maxlength="30">
                        <label class="slide-label">Email</label>
                        <input type="email" name="customer_email" class="slide-input" id="custEmailField" value="<?= $fv('customer_email') ?>" maxlength="150">
                    </div>
                </div>

                <div class="cj-grid">
                    <div class="cj-section">
                        <h2 class="cj-section-title">Device Details</h2>
                        <label class="slide-label">Brand <span class="required">*</span></label>
                        <input type="text" name="device_brand" class="slide-input" value="<?= $fv('device_brand') ?>" required maxlength="80" placeholder="e.g. Apple, Samsung">
                        <label class="slide-label">Model <span class="required">*</span></label>
                        <input type="text" name="device_model" class="slide-input" value="<?= $fv('device_model') ?>" required maxlength="120" placeholder="e.g. iPhone 13, Galaxy A54">
                        <label class="slide-label">IMEI / Serial</label>
                        <input type="text" name="imei" class="slide-input" value="<?= $fv('imei') ?>" maxlength="40" placeholder="Optional">
                    </div>

                    <div class="cj-section">
                        <h2 class="cj-section-title">Job Details</h2>
                        <label class="slide-label">Issue Description <span class="required">*</span></label>
                        <textarea name="reported_issue" class="slide-input" rows="3" required maxlength="2000" placeholder="Describe the problem..."><?= $fv('reported_issue') ?></textarea>
                        <label class="slide-label">Cost Estimate ($)</label>
                        <input type="number" name="estimated_cost" class="slide-input" value="<?= $fv('estimated_cost', '0.00') ?>" min="0" step="0.01" style="max-width:180px">
                        <label class="slide-label">Due Date</label>
                        <input type="date" name="due_date" class="slide-input" value="<?= $fv('due_date') ?>" style="max-width:200px">
                    </div>

                    <div class="cj-section">
                        <h2 class="cj-section-title">Assignment</h2>
                        <label class="slide-label">Urgency</label>
                        <div class="urgency-radio-group">
                            <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'urgent' => 'Urgent'] as $uv => $ul): ?>
                                <label class="urgency-radio-label">
                                    <input type="radio" name="urgency" value="<?= e($uv) ?>"<?= $checked($uv) ?>>
                                    <span class="urgency-radio-pill urgency--<?= e($uv) ?>"><?= e($ul) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <label class="slide-label">Assign Technician</label>
                        <select name="technician_id" class="slide-input">
                            <option value="">Unassigned</option>
                            <?php foreach ($techs as $t): ?>
                                <option value="<?= (int) $t['user_id'] ?>"<?= $sel('technician_id', (string) $t['user_id']) ?>><?= e($t['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="cj-actions">
                    <a href="<?= e(base_url('/technician/board')) ?>" class="btn-outline">Cancel</a>
                    <button type="submit" class="btn-cyan">Create Repair Job</button>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
    window.__createJob = {
        searchUrl: <?= json_encode(base_url('/technician/board/search-customer'), JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= e(base_url('js/repair-board.js')) ?>"></script>
</body>
</html>
