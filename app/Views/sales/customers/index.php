<?php
/**
 * Customers — list with create/edit slide-over panel
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $customers
 * @var string $q
 * @var int $page
 * @var int $totalCount
 * @var int $totalPages
 * @var array{type:string,message:string}|null $flash
 * @var bool $panelOpen
 * @var array<string,mixed>|null $editCustomer
 * @var string|null $panelMode
 * @var array<string,mixed> $formOld
 * @var string|null $formError
 */

$isCreate = $panelMode === 'create';
$panelTitle = $isCreate ? 'Add Customer' : 'Edit Customer';
$formAction = $isCreate
    ? base_url('/sales/customers/create')
    : base_url('/sales/customers/update/' . (int) ($editCustomer['customer_id'] ?? 0));

$fv = function(string $key, string $default = '') use ($formOld, $editCustomer): string {
    if (array_key_exists($key, $formOld) && is_scalar($formOld[$key])) {
        return e((string) $formOld[$key]);
    }
    if ($editCustomer !== null && array_key_exists($key, $editCustomer)) {
        return e((string) ($editCustomer[$key] ?? ''));
    }
    return $default;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
</head>
<body class="dash-body">
<div class="dash-layout">

    <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

    <div class="dash-main">
        <header class="topbar">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <span class="crumb">Sales</span>
                <span class="crumb-sep">&rsaquo;</span>
                <span class="crumb crumb-current">Customers</span>
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
                    <h1 class="page-greeting">Customers</h1>
                    <p class="page-sub">Contact records for POS and repairs</p>
                </div>
                <div class="content-actions">
                    <a class="btn-cyan" href="<?= e(base_url('/sales/customers/create')) ?>">+ Add Customer</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash flash--<?= e($flash['type'] ?? 'info') ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <form method="get" class="history-filters" autocomplete="off" style="margin-bottom:16px">
                <div class="history-filter-group" style="flex:1;max-width:360px">
                    <input type="text" name="q" placeholder="Search by name, phone, or email..." value="<?= e($q) ?>" class="history-input" style="width:100%">
                </div>
                <button type="submit" class="btn-outline btn-sm">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="<?= e(base_url('/sales/customers')) ?>" class="btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (!$customers): ?>
                <p class="pos-empty" style="margin-top:20px">No customers found.</p>
            <?php else: ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><a href="<?= e(base_url('/sales/customers/view/' . (int) $c['customer_id'])) ?>" style="font-weight:600;color:var(--cyan-400);text-decoration:none"><?= e($c['full_name']) ?></a></td>
                            <td><?= e($c['phone'] ?? '—') ?></td>
                            <td><?= e($c['email'] ?? '—') ?></td>
                            <td><?= e(date('M j, Y', strtotime((string) $c['created_at']))) ?></td>
                            <td style="text-align:right;white-space:nowrap">
                                <a href="<?= e(base_url('/sales/customers/edit/' . (int) $c['customer_id'])) ?>" class="btn-outline btn-sm">Edit</a>
                                <form method="post" action="<?= e(base_url('/sales/customers/delete/' . (int) $c['customer_id'])) ?>" style="display:inline" onsubmit="return confirm('Delete this customer?')">
                                    <button type="submit" class="btn-outline btn-sm" style="color:var(--red-500);border-color:var(--red-500)">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <span class="pagination-info">Showing <?= (($page - 1) * 20 + 1) ?>–<?= min($page * 20, $totalCount) ?> of <?= $totalCount ?> customers</span>
                    <div class="pagination-links">
                        <?php if ($page > 1): ?>
                            <a href="?q=<?= urlencode($q) ?>&amp;page=<?= $page - 1 ?>" class="btn-outline btn-sm">&laquo; Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?q=<?= urlencode($q) ?>&amp;page=<?= $page + 1 ?>" class="btn-outline btn-sm">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php if ($panelOpen): ?>
<a class="slideover-overlay is-open" href="<?= e(base_url('/sales/customers')) ?>"></a>
<aside class="slideover is-open">
    <form method="post" action="<?= e($formAction) ?>">
        <div class="slideover-head">
            <div>
                <h2 class="slideover-title"><?= e($panelTitle) ?></h2>
                <p class="slideover-sub"><?= $isCreate ? 'Register a new customer' : 'Update customer details' ?></p>
            </div>
            <a class="slideover-close" href="<?= e(base_url('/sales/customers')) ?>" aria-label="Close">&times;</a>
        </div>
        <div class="slideover-body">
            <?php if ($formError): ?>
                <div class="flash flash--error"><?= e($formError) ?></div>
            <?php endif; ?>

            <label class="slide-label">Full Name <span class="required">*</span></label>
            <input type="text" name="full_name" class="slide-input" value="<?= $fv('full_name') ?>" required maxlength="120">

            <label class="slide-label">Email</label>
            <input type="email" name="email" class="slide-input" value="<?= $fv('email') ?>" maxlength="150">

            <label class="slide-label">Phone</label>
            <input type="text" name="phone" class="slide-input" value="<?= $fv('phone') ?>" maxlength="30">

            <label class="slide-label">Address</label>
            <input type="text" name="address" class="slide-input" value="<?= $fv('address') ?>" maxlength="255">

            <label class="slide-label">Notes</label>
            <textarea name="notes" class="slide-input" rows="2" maxlength="1000"><?= $fv('notes') ?></textarea>
        </div>
        <div class="slideover-foot">
            <a class="btn-outline" href="<?= e(base_url('/sales/customers')) ?>">Cancel</a>
            <button class="btn-cyan" type="submit"><?= $isCreate ? 'Create Customer' : 'Save Changes' ?></button>
        </div>
    </form>
</aside>
<?php endif; ?>
</body>
</html>
