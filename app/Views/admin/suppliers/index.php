<?php
/**
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $suppliers
 * @var string $q
 * @var array<string,mixed>|null $editSupplier
 * @var string|null $panelMode
 * @var array{type:string,message:string}|null $flash
 * @var array<string,mixed>|null $formOld
 * @var string|null $formError
 */

$panelOpen = $panelMode !== null;
$isCreate = ($panelMode ?? 'edit') === 'create';

$fv = static function (string $key) use ($formOld, $editSupplier) {
    if (is_array($formOld) && array_key_exists($key, $formOld) && $formOld[$key] !== null) {
        return $formOld[$key];
    }
    if (is_array($editSupplier) && array_key_exists($key, $editSupplier) && $editSupplier[$key] !== null) {
        return $editSupplier[$key];
    }
    return '';
};

$formAction = $isCreate
    ? base_url('/admin/suppliers/create')
    : base_url('/admin/suppliers/update/' . (int) ($editSupplier['supplier_id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
</head>
<body class="dash-body">
    <div class="dash-layout">

        <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

        <div class="dash-main">

            <header class="topbar">
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <span class="crumb">Admin</span>
                    <span class="crumb-sep">&rsaquo;</span>
                    <span class="crumb crumb-current">Suppliers</span>
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
                        <h1 class="page-greeting">Suppliers</h1>
                        <p class="page-sub">Manage the vendors you purchase stock from.</p>
                    </div>
                    <div class="content-actions">
                        <a class="btn-cyan" href="<?= e(base_url('/admin/suppliers/create')) ?>">+ Add Supplier</a>
                    </div>
                </div>

                <?php if ($flash !== null): ?>
                    <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <form class="user-toolbar" method="get" action="<?= e(base_url('/admin/suppliers')) ?>">
                    <label class="toolbar-search">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="search" name="q" class="search-input" placeholder="Search by name, contact, email or phone..." value="<?= e($q) ?>">
                    </label>
                    <button type="submit" class="btn-outline">Search</button>
                </form>

                <div class="table-wrap">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$suppliers): ?>
                                <tr><td colspan="6" class="empty-row">No suppliers yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($suppliers as $s):
                                $sid = (int) $s['supplier_id'];
                            ?>
                                <tr>
                                    <td><a class="user-cell-name" href="<?= e(base_url('/admin/suppliers/edit/' . $sid)) ?>"><?= e($s['name']) ?></a></td>
                                    <td><?= e((string) ($s['contact_person'] ?? '—')) ?></td>
                                    <td><?= e((string) ($s['phone'] ?? '—')) ?></td>
                                    <td class="cell-email"><?= e((string) ($s['email'] ?? '—')) ?></td>
                                    <td><?= e((string) ($s['address'] ?? '—')) ?></td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <a class="icon-btn" href="<?= e(base_url('/admin/suppliers/edit/' . $sid)) ?>" aria-label="Edit supplier" title="Edit">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                            </a>
                                            <form method="post" action="<?= e(base_url('/admin/suppliers/delete/' . $sid)) ?>" data-confirm="Delete <?= e($s['name']) ?>?">
                                                <button type="submit" class="icon-btn icon-btn--danger" aria-label="Delete supplier" title="Delete">
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

    <a class="slideover-overlay<?= $panelOpen ? ' is-open' : '' ?>" href="<?= e(base_url('/admin/suppliers')) ?>" aria-label="Close panel"></a>
    <aside class="slideover<?= $panelOpen ? ' is-open' : '' ?>" aria-hidden="<?= $panelOpen ? 'false' : 'true' ?>">
        <form method="post" action="<?= e($formAction) ?>" class="slideover-form">
            <div class="slideover-head">
                <div>
                    <h2 class="slideover-title"><?= $isCreate ? 'Add Supplier' : 'Edit Supplier' ?></h2>
                    <p class="slideover-sub">Vendor contact details</p>
                </div>
                <a class="slideover-close" href="<?= e(base_url('/admin/suppliers')) ?>" aria-label="Close">&times;</a>
            </div>
            <div class="slideover-body">
                <?php if ($formError !== null): ?>
                    <div class="flash flash--error"><?= e($formError) ?></div>
                <?php endif; ?>
                <label class="field">
                    <span class="field-label">Name</span>
                    <input type="text" name="name" class="field-input" value="<?= e((string) $fv('name')) ?>" required>
                </label>
                <label class="field">
                    <span class="field-label">Contact Person</span>
                    <input type="text" name="contact_person" class="field-input" value="<?= e((string) $fv('contact_person')) ?>">
                </label>
                <label class="field">
                    <span class="field-label">Phone</span>
                    <input type="text" name="phone" class="field-input" value="<?= e((string) $fv('phone')) ?>">
                </label>
                <label class="field">
                    <span class="field-label">Email</span>
                    <input type="email" name="email" class="field-input" value="<?= e((string) $fv('email')) ?>">
                </label>
                <label class="field">
                    <span class="field-label">Address</span>
                    <textarea name="address" class="field-input" rows="3"><?= e((string) $fv('address')) ?></textarea>
                </label>
            </div>
            <div class="slideover-foot">
                <a class="btn-outline" href="<?= e(base_url('/admin/suppliers')) ?>">Cancel</a>
                <button type="submit" class="btn-cyan"><?= $isCreate ? 'Add Supplier' : 'Save Changes' ?></button>
            </div>
        </form>
    </aside>

    <script src="<?= e(base_url('js/users.js')) ?>"></script>
</body>
</html>
