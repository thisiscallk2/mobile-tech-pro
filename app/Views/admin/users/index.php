<?php
/**
 * @var array<string, mixed>            $user
 * @var string                          $active
 * @var string                          $today
 * @var array<int, array<string,mixed>> $users
 * @var array{total:int,active:int,inactive:int} $counts
 * @var string $q
 * @var string $role
 * @var int    $page
 * @var int    $pages
 * @var int    $total
 * @var int    $shown
 * @var array<int, array{key:string,label:string,desc:string}> $modules
 * @var array<string, array<int,string>> $roleDefaults
 * @var array<string, mixed>|null $editUser
 * @var string|null $panelMode
 * @var array{type:string,message:string}|null $flash
 * @var array<string,mixed>|null $formOld
 * @var string|null $formError
 */

$roleMeta = [
    'admin' => ['pill' => 'Admin', 'subtitle' => 'Super Admin', 'cls' => 'role--admin'],
    'sales_staff' => ['pill' => 'Sales', 'subtitle' => 'Sales Staff', 'cls' => 'role--sales'],
    'technician' => ['pill' => 'Technician', 'subtitle' => 'Technician', 'cls' => 'role--tech'],
];
$moduleIcons = ['sales' => 'pos', 'inventory' => 'inventory', 'repairs' => 'repair', 'reports' => 'reports'];

$initialsOf = static function (string $name): string {
    $out = '';
    foreach (array_slice(array_filter(explode(' ', $name)), 0, 2) as $p) {
        $out .= strtoupper($p[0]);
    }
    return $out !== '' ? $out : 'U';
};

$panelOpen = $panelMode !== null;
$mode = $panelMode ?? 'edit';
$isCreate = $mode === 'create';

$fvName = $formOld['full_name'] ?? $editUser['full_name'] ?? '';
$fvEmail = $formOld['email'] ?? $editUser['email'] ?? '';
$fvRole = $formOld['user_type'] ?? $editUser['user_type'] ?? 'sales_staff';
if (isset($formOld['active'])) {
    $fvActive = (int) $formOld['active'];
} else {
    $fvActive = (int) ($editUser['active'] ?? 1);
}
if (is_array($formOld['permissions'] ?? null)) {
    $fvPerms = $formOld['permissions'];
} elseif ($editUser !== null) {
    $stored = (string) ($editUser['permissions'] ?? '');
    $fvPerms = $stored !== '' ? explode(',', $stored) : ($roleDefaults[$fvRole] ?? []);
} else {
    $fvPerms = $roleDefaults[$fvRole] ?? [];
}

$formAction = $isCreate
    ? base_url('/admin/users/create')
    : base_url('/admin/users/update/' . (int) ($editUser['user_id'] ?? 0));
$panelName = $isCreate ? 'New Team Member' : (string) ($fvName ?: ($editUser['full_name'] ?? 'User'));
$panelIdLabel = $isCreate ? 'New user' : sprintf('USR-%05d', (int) ($editUser['user_id'] ?? 0));

$filterQuery = static function (array $overrides) use ($q, $role): string {
    $params = array_filter(array_merge(['q' => $q, 'role' => $role], $overrides), static fn ($v) => $v !== '' && $v !== null);
    return $params ? '?' . http_build_query($params) : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management &mdash; MobileTech Pro</title>
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
                    <span class="crumb crumb-current">User Management</span>
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
                        <h1 class="page-greeting">User Management</h1>
                        <p class="page-sub">Manage team members, roles, and permissions.</p>
                    </div>
                    <div class="content-actions">
                        <a class="btn-cyan" href="<?= e(base_url('/admin/users/create')) ?>">+ Add User</a>
                    </div>
                </div>

                <?php if ($flash !== null): ?>
                    <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <!-- Summary cards -->
                <section class="summary-grid">
                    <article class="summary-card">
                        <p class="summary-label">Total Users</p>
                        <p class="summary-value"><?= e((string) $counts['total']) ?></p>
                    </article>
                    <article class="summary-card">
                        <p class="summary-label"><span class="dot dot--green"></span> Active</p>
                        <p class="summary-value"><?= e((string) $counts['active']) ?></p>
                    </article>
                    <article class="summary-card">
                        <p class="summary-label"><span class="dot dot--grey"></span> Inactive</p>
                        <p class="summary-value"><?= e((string) $counts['inactive']) ?></p>
                    </article>
                </section>

                <!-- Toolbar -->
                <form class="user-toolbar" method="get" action="<?= e(base_url('/admin/users')) ?>">
                    <label class="toolbar-search">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="search" name="q" class="search-input" placeholder="Search by name or email..." value="<?= e($q) ?>">
                    </label>
                    <select name="role" class="toolbar-select">
                        <option value=""<?= $role === '' ? ' selected' : '' ?>>All Roles</option>
                        <option value="admin"<?= $role === 'admin' ? ' selected' : '' ?>>Admin</option>
                        <option value="sales_staff"<?= $role === 'sales_staff' ? ' selected' : '' ?>>Sales</option>
                        <option value="technician"<?= $role === 'technician' ? ' selected' : '' ?>>Technician</option>
                    </select>
                    <button type="submit" class="btn-outline">Apply</button>
                </form>

                <!-- Table -->
                <div class="table-wrap">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$users): ?>
                                <tr><td colspan="6" class="empty-row">No users match your search.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($users as $row):
                                $meta = $roleMeta[$row['user_type']] ?? ['pill' => $row['user_type'], 'subtitle' => '', 'cls' => 'role--sales'];
                                $isActive = ((int) $row['active']) === 1;
                                $rid = (int) $row['user_id'];
                            ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <span class="row-avatar"><?= e($initialsOf((string) $row['full_name'])) ?></span>
                                            <span class="user-cell-meta">
                                                <a class="user-cell-name" href="<?= e(base_url('/admin/users/edit/' . $rid)) ?>"><?= e($row['full_name']) ?></a>
                                                <span class="user-cell-sub"><?= e($meta['subtitle']) ?></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="cell-email"><?= e($row['email']) ?></td>
                                    <td><span class="role-pill <?= e($meta['cls']) ?>"><?= e($meta['pill']) ?></span></td>
                                    <td>
                                        <span class="status-pill <?= $isActive ? 'status--active' : 'status--inactive' ?>">
                                            <span class="dot <?= $isActive ? 'dot--green' : 'dot--grey' ?>"></span>
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="cell-login"><?= e(relative_time($row['last_login_at'] ?? null)) ?></td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <a class="icon-btn" href="<?= e(base_url('/admin/users/edit/' . $rid)) ?>" aria-label="Edit user" title="Edit">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                            </a>
                                            <form method="post" action="<?= e(base_url('/admin/users/toggle/' . $rid)) ?>">
                                                <button type="submit" class="icon-btn" aria-label="<?= $isActive ? 'Deactivate' : 'Activate' ?>" title="<?= $isActive ? 'Deactivate' : 'Activate' ?>">
                                                    <?php if ($isActive): ?>
                                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                                                    <?php else: ?>
                                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info">Showing <?= e((string) $shown) ?> of <?= e((string) $total) ?> users.</span>
                    <div class="pagination-nav">
                        <?php if ($page > 1): ?>
                            <a class="btn-outline btn-sm" href="<?= e(base_url('/admin/users') . $filterQuery(['page' => $page - 1])) ?>">Previous</a>
                        <?php endif; ?>
                        <span class="pagination-page">Page <?= e((string) $page) ?> / <?= e((string) $pages) ?></span>
                        <?php if ($page < $pages): ?>
                            <a class="btn-outline btn-sm" href="<?= e(base_url('/admin/users') . $filterQuery(['page' => $page + 1])) ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Slide-over: Edit / Add user -->
    <a class="slideover-overlay<?= $panelOpen ? ' is-open' : '' ?>" href="<?= e(base_url('/admin/users')) ?>" aria-label="Close panel"></a>
    <aside class="slideover<?= $panelOpen ? ' is-open' : '' ?>" id="userPanel" aria-hidden="<?= $panelOpen ? 'false' : 'true' ?>">
        <form method="post" action="<?= e($formAction) ?>" class="slideover-form" data-role-defaults="<?= e(json_encode($roleDefaults)) ?>">
            <div class="slideover-head">
                <div>
                    <h2 class="slideover-title"><?= $isCreate ? 'Add User' : 'Edit User' ?></h2>
                    <p class="slideover-sub"><?= $isCreate ? 'Create a new team member and set permissions' : 'Update account details and permissions' ?></p>
                </div>
                <a class="slideover-close" href="<?= e(base_url('/admin/users')) ?>" aria-label="Close">&times;</a>
            </div>

            <div class="slideover-body">
                <?php if ($formError !== null): ?>
                    <div class="flash flash--error"><?= e($formError) ?></div>
                <?php endif; ?>

                <div class="panel-identity">
                    <span class="identity-avatar">
                        <?= e($initialsOf($panelName)) ?>
                        <span class="avatar-edit" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </span>
                    </span>
                    <span class="identity-meta">
                        <span class="identity-name"><?= e($panelName) ?></span>
                        <span class="identity-id"><?= e($panelIdLabel) ?></span>
                    </span>
                </div>

                <label class="field">
                    <span class="field-label">Full Name</span>
                    <input type="text" name="full_name" class="field-input" value="<?= e($fvName) ?>" required>
                </label>

                <label class="field">
                    <span class="field-label">Email Address</span>
                    <input type="email" name="email" class="field-input" value="<?= e($fvEmail) ?>" required>
                </label>

                <label class="field">
                    <span class="field-label">New Password</span>
                    <span class="field-password">
                        <input type="password" name="password" id="panelPassword" class="field-input" <?= $isCreate ? 'required' : '' ?>>
                        <button type="button" class="pw-toggle" id="panelPwToggle" aria-label="Show password">
                            <svg class="icon-eye" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </span>
                    <span class="field-help"><?= $isCreate ? 'Set an initial password for this user' : 'Leave blank to keep current' ?></span>
                </label>

                <div class="field">
                    <span class="field-label">Role</span>
                    <?php if ($fvRole === 'admin'): ?>
                        <input type="hidden" name="user_type" value="admin">
                        <span class="role-pill role--admin">Admin</span>
                        <span class="field-help">The admin role is fixed and cannot be changed.</span>
                    <?php else: ?>
                        <div class="role-pills" role="radiogroup">
                            <?php foreach (['sales_staff' => 'Sales', 'technician' => 'Technician'] as $rk => $rl): ?>
                                <label class="role-choice">
                                    <input type="radio" name="user_type" value="<?= e($rk) ?>"<?= $fvRole === $rk ? ' checked' : '' ?>>
                                    <span class="role-choice-pill"><?= e($rl) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <span class="field-label">Module Permissions</span>
                    <div class="perm-list">
                        <?php foreach ($modules as $mod): ?>
                            <label class="perm-item">
                                <input type="checkbox" name="permissions[]" value="<?= e($mod['key']) ?>"<?= in_array($mod['key'], $fvPerms, true) ? ' checked' : '' ?>>
                                <span class="perm-icon"><?= dash_icon($moduleIcons[$mod['key']] ?? 'dashboard') ?></span>
                                <span class="perm-meta">
                                    <span class="perm-label"><?= e($mod['label']) ?></span>
                                    <span class="perm-desc"><?= e($mod['desc']) ?></span>
                                </span>
                                <span class="perm-check" aria-hidden="true"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="field status-field">
                    <span class="status-meta">
                        <span class="field-label">Account Status</span>
                        <span class="field-help">Inactive users cannot log in</span>
                    </span>
                    <label class="switch">
                        <input type="checkbox" name="active" value="1"<?= $fvActive === 1 ? ' checked' : '' ?>>
                        <span class="switch-track" aria-hidden="true"></span>
                    </label>
                </div>
            </div>

            <div class="slideover-foot">
                <a class="btn-outline" href="<?= e(base_url('/admin/users')) ?>">Cancel</a>
                <button type="submit" class="btn-cyan"><?= $isCreate ? 'Create User' : 'Save Changes' ?></button>
            </div>
        </form>
    </aside>

    <script src="<?= e(base_url('js/users.js')) ?>"></script>
</body>
</html>
