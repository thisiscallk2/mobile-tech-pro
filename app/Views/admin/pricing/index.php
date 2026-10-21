<?php
/**
 * Pricing Management
 *
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $products
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array<string,mixed>> $brands
 * @var string $q
 * @var int|null $brandId
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $shown
 * @var array{type:string,message:string}|null $flash
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing &mdash; MobileTech Pro</title>
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
                    <span class="crumb crumb-current">Pricing</span>
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
                        <h1 class="page-greeting">Pricing</h1>
                        <p class="page-sub">Manage product cost and selling prices.</p>
                    </div>
                </div>

                <?php if ($flash !== null): ?>
                    <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <div class="toolbar">
                    <form method="get" action="<?= e(base_url('/admin/pricing')) ?>" class="toolbar-search">
                        <input type="text" name="q" class="search-input" placeholder="Search products..."
                               value="<?= e($q) ?>">
                        <button type="submit" class="btn-cyan btn-sm">Search</button>
                    </form>
                </div>

                <div class="table-wrap">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="cell-cost">Cost Price</th>
                                <th class="cell-selling">Selling Price</th>
                                <th class="cell-margin">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$products): ?>
                                <tr><td colspan="6" class="empty-row">No products found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($products as $p):
                                $pid = (int) $p['product_id'];
                                $cost = (float) ($p['cost_price'] ?? 0);
                                $selling = (float) ($p['selling_price'] ?? 0);
                                $marginPct = $selling > 0 ? round((($selling - $cost) / $selling) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <div class="product-cell">
                                            <?php if (!empty($p['image_path'])): ?>
                                                <img class="product-thumb" src="<?= e(base_url($p['image_path'])) ?>"
                                                     alt="" width="36" height="36" loading="lazy">
                                            <?php else: ?>
                                                <span class="product-icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8"/>
                                                    </svg>
                                                </span>
                                            <?php endif; ?>
                                            <span class="product-name"><?= e($p['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="cell-mono"><?= e($p['sku']) ?></td>
                                    <td>
                                        <?php if (!empty($p['category_name'])): ?>
                                            <span class="category-pill"><?= e($p['category_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-cost">
                                        <form method="post" action="<?= e(base_url('/admin/pricing/update-price')) ?>" class="price-form">
                                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                                            <input type="number" name="cost_price" class="price-input"
                                                   value="<?= $cost ?>" step="0.01" min="0">
                                            <span class="price-divider">/</span>
                                            <input type="number" name="selling_price" class="price-input"
                                                   value="<?= $selling ?>" step="0.01" min="0">
                                            <button type="submit" class="price-save-btn">Save</button>
                                        </form>
                                    </td>
                                    <td class="cell-selling">$<?= number_format($selling, 2) ?></td>
                                    <td class="cell-margin">
                                        <span class="<?= $marginPct >= 0 ? 'margin--positive' : 'margin--negative' ?>">
                                            <?= $marginPct >= 0 ? '+' : '' ?><?= $marginPct ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a class="page-btn" href="<?= e(base_url('/admin/pricing?q=' . urlencode($q) . '&page=' . ($page - 1))) ?>">&laquo; Prev</a>
                        <?php endif; ?>
                        <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>
                        <?php if ($page < $pages): ?>
                            <a class="page-btn" href="<?= e(base_url('/admin/pricing?q=' . urlencode($q) . '&page=' . ($page + 1))) ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>
</body>
</html>
