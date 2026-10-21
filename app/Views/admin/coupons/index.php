<?php
/**
 * Coupon Management
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $coupons
 * @var array<string,mixed>|null $editCoupon
 * @var string|null $panelMode
 * @var array{type:string,message:string}|null $flash
 * @var array<string,mixed>|null $formOld
 * @var string|null $formError
 */

$panelOpen = $panelMode !== null;
$isCreate = ($panelMode ?? 'edit') === 'create';

$fv = static function (string $key) use ($formOld, $editCoupon) {
    if (is_array($formOld) && array_key_exists($key, $formOld) && $formOld[$key] !== null && $formOld[$key] !== '') {
        return $formOld[$key];
    }
    if (is_array($editCoupon) && array_key_exists($key, $editCoupon) && $editCoupon[$key] !== null && $editCoupon[$key] !== '') {
        return $editCoupon[$key];
    }
    return '';
};

$formAction = $isCreate
    ? base_url('/admin/coupons/create')
    : base_url('/admin/coupons/update/' . (int) ($editCoupon['coupon_id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/coupons.css')) ?>">
</head>
<body class="dash-body">
    <div class="dash-layout">

        <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

        <div class="dash-main">

            <header class="topbar">
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <span class="crumb">Business</span>
                    <span class="crumb-sep">&rsaquo;</span>
                    <span class="crumb crumb-current">Coupons</span>
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
                        <h1 class="page-greeting">Coupons</h1>
                        <p class="page-sub">Manage discount codes for the point of sale.</p>
                    </div>
                    <div class="content-actions">
                        <a class="btn-cyan" href="<?= e(base_url('/admin/coupons/create')) ?>">+ Add Coupon</a>
                    </div>
                </div>

                <?php if ($flash !== null): ?>
                    <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <div class="table-wrap">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Valid From</th>
                                <th>Valid To</th>
                                <th>Status</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$coupons): ?>
                                <tr><td colspan="7" class="empty-row">No coupons yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($coupons as $c):
                                $cid = (int) $c['coupon_id'];
                                $active = (int) ($c['active'] ?? 1);
                                $typeLabel = $c['discount_type'] === 'percent' ? 'Percentage' : 'Fixed Amount';
                                $valDisplay = $c['discount_type'] === 'percent' ? number_format((float) $c['value'], 0) . '%' : '$' . number_format((float) $c['value'], 2);
                                $validFrom = !empty($c['valid_from']) ? date('M j, Y', strtotime($c['valid_from'])) : '&mdash;';
                                $validTo = !empty($c['valid_to']) ? date('M j, Y', strtotime($c['valid_to'])) : '&mdash;';
                            ?>
                                <tr>
                                    <td>
                                        <span class="coupon-code"><?= e($c['code']) ?></span>
                                    </td>
                                    <td><?= $typeLabel ?></td>
                                    <td><?= $valDisplay ?></td>
                                    <td><?= $validFrom ?></td>
                                    <td><?= $validTo ?></td>
                                    <td>
                                        <span class="status-pill status-pill--<?= $active ? 'active' : 'inactive' ?>">
                                            <?= $active ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <a class="icon-btn" href="<?= e(base_url('/admin/coupons/edit/' . $cid)) ?>" aria-label="Edit coupon" title="Edit">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                            </a>
                                            <form method="post" action="<?= e(base_url('/admin/coupons/delete/' . $cid)) ?>" data-confirm="Delete coupon <?= e($c['code']) ?>?">
                                                <button type="submit" class="icon-btn icon-btn--danger" aria-label="Delete coupon" title="Delete">
                                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </main>
        </div>
    </div>

    <a class="slideover-overlay<?= $panelOpen ? ' is-open' : '' ?>" href="<?= e(base_url('/admin/coupons')) ?>" aria-label="Close panel"></a>
    <aside class="slideover<?= $panelOpen ? ' is-open' : '' ?>" aria-hidden="<?= $panelOpen ? 'false' : 'true' ?>">
        <form method="post" action="<?= e($formAction) ?>" class="slideover-form">
            <div class="slideover-head">
                <div>
                    <h2 class="slideover-title"><?= $isCreate ? 'Add Coupon' : 'Edit Coupon' ?></h2>
                    <p class="slideover-sub">Discount code details</p>
                </div>
                <a class="slideover-close" href="<?= e(base_url('/admin/coupons')) ?>" aria-label="Close">&times;</a>
            </div>
            <div class="slideover-body">
                <?php if ($formError !== null): ?>
                    <div class="flash flash--error"><?= e($formError) ?></div>
                <?php endif; ?>

                <label class="field">
                    <span class="field-label">Coupon Code</span>
                    <input type="text" name="code" class="field-input" value="<?= e((string) $fv('code')) ?>"
                           placeholder="e.g. SAVE20" required maxlength="40">
                </label>

                <div class="field-row">
                    <label class="field" style="flex:1">
                        <span class="field-label">Discount Type</span>
                        <select name="discount_type" class="field-input" required>
                            <option value="percent"<?= $fv('discount_type') === 'percent' ? ' selected' : '' ?>>Percentage (%)</option>
                            <option value="fixed"<?= $fv('discount_type') === 'fixed' ? ' selected' : '' ?>>Fixed Amount ($)</option>
                        </select>
                    </label>
                    <label class="field" style="flex:1">
                        <span class="field-label">Value</span>
                        <input type="number" name="value" class="field-input" value="<?= e((string) $fv('value')) ?>"
                               step="0.01" min="0.01" required>
                    </label>
                </div>

                <div class="field-row">
                    <label class="field" style="flex:1">
                        <span class="field-label">Valid From</span>
                        <input type="date" name="valid_from" class="field-input" value="<?= e((string) $fv('valid_from')) ?>">
                    </label>
                    <label class="field" style="flex:1">
                        <span class="field-label">Valid To</span>
                        <input type="date" name="valid_to" class="field-input" value="<?= e((string) $fv('valid_to')) ?>">
                    </label>
                </div>

                <label class="field-check">
                    <input type="checkbox" name="active" value="1"<?= ($fv('active') === '' && $isCreate) || $fv('active') == 1 ? ' checked' : '' ?>>
                    <span class="field-label">Active</span>
                </label>
            </div>
            <div class="slideover-foot">
                <a class="btn-outline" href="<?= e(base_url('/admin/coupons')) ?>">Cancel</a>
                <button type="submit" class="btn-cyan"><?= $isCreate ? 'Create Coupon' : 'Save Changes' ?></button>
            </div>
        </form>
    </aside>

    <script src="<?= e(base_url('js/users.js')) ?>"></script>
</body>
</html>
