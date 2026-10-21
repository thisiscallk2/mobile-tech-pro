<?php
/**
 * @var array<string,mixed> $user
 * @var string $active
 * @var string $today
 * @var array<int,array<string,mixed>> $products
 * @var array{total:int,in_stock:int,low_stock:int} $counts
 * @var array<int,string> $lowStockNames
 * @var array<int,array{category_id:int,name:string}> $categories
 * @var array<int,array{brand_id:int,name:string}> $brands
 * @var array<int,array<string,mixed>> $suppliers
 * @var string $q
 * @var int|null $categoryId
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $shown
 * @var array<string,mixed>|null $editProduct
 * @var string|null $panelMode
 * @var array{type:string,message:string}|null $flash
 * @var array<string,mixed>|null $formOld
 * @var string|null $formError
 */

$stockStatus = static function (int $qty, int $reorder): array {
    if ($qty <= 0) {
        return ['label' => 'Out of Stock', 'cls' => 'status--out', 'bar' => 'bar--red'];
    }
    if ($qty <= $reorder) {
        return ['label' => 'Low Stock', 'cls' => 'status--low', 'bar' => 'bar--amber'];
    }
    return ['label' => 'In Stock', 'cls' => 'status--in', 'bar' => 'bar--green'];
};
$stockPct = static function (int $qty, int $reorder): int {
    $target = max($reorder * 2, 10);
    return (int) max(4, min(100, round($qty / $target * 100)));
};

$panelOpen = $panelMode !== null;
$mode = $panelMode ?? 'edit';
$isCreate = $mode === 'create';

$fv = static function (string $key, $default = '') use ($formOld, $editProduct) {
    if (is_array($formOld) && array_key_exists($key, $formOld) && $formOld[$key] !== null) {
        return $formOld[$key];
    }
    if (is_array($editProduct) && array_key_exists($key, $editProduct) && $editProduct[$key] !== null) {
        return $editProduct[$key];
    }
    return $default;
};

$existingImage = is_array($editProduct) ? (string) ($editProduct['image_path'] ?? '') : '';
$formAction = $isCreate
    ? base_url('/admin/inventory/create')
    : base_url('/admin/inventory/update/' . (int) ($editProduct['product_id'] ?? 0));

$filterQuery = static function (array $overrides) use ($q, $categoryId): string {
    $base = ['q' => $q, 'category' => $categoryId !== null ? (string) $categoryId : ''];
    $params = array_filter(array_merge($base, $overrides), static fn ($v) => $v !== '' && $v !== null);
    return $params ? '?' . http_build_query($params) : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/users.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/inventory.css')) ?>">
</head>
<body class="dash-body">
    <div class="dash-layout">

        <?php require APP_ROOT . '/app/Views/partials/sidebar.php'; ?>

        <div class="dash-main">

            <header class="topbar">
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <span class="crumb">Admin</span>
                    <span class="crumb-sep">&rsaquo;</span>
                    <span class="crumb crumb-current">Inventory</span>
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
                        <h1 class="page-greeting">Inventory Management</h1>
                        <p class="page-sub">Track products, stock levels, and manage supplies.</p>
                    </div>
                    <div class="content-actions">
                        <a class="btn-cyan" href="<?= e(base_url('/admin/inventory/create')) ?>">+ Add Product</a>
                    </div>
                </div>

                <?php if ($flash !== null): ?>
                    <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <?php if ($lowStockNames): ?>
                    <div class="alert-banner" role="alert">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>
                            <strong><?= e((string) count($lowStockNames)) ?> product<?= count($lowStockNames) === 1 ? '' : 's' ?> need restocking:</strong>
                            <?= e(implode(', ', array_slice($lowStockNames, 0, 8))) ?><?= count($lowStockNames) > 8 ? '&hellip;' : '' ?>
                        </span>
                    </div>
                <?php endif; ?>

                <section class="summary-grid">
                    <article class="summary-card">
                        <p class="summary-label">Total Products</p>
                        <p class="summary-value"><?= e((string) $counts['total']) ?></p>
                    </article>
                    <article class="summary-card">
                        <p class="summary-label"><span class="dot dot--green"></span> In Stock</p>
                        <p class="summary-value"><?= e((string) $counts['in_stock']) ?></p>
                    </article>
                    <article class="summary-card">
                        <p class="summary-label"><span class="dot dot--amber"></span> Low Stock</p>
                        <p class="summary-value"><?= e((string) $counts['low_stock']) ?></p>
                    </article>
                </section>

                <form class="user-toolbar" method="get" action="<?= e(base_url('/admin/inventory')) ?>">
                    <label class="toolbar-search">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="search" name="q" class="search-input" placeholder="Search by name or SKU..." value="<?= e($q) ?>">
                    </label>
                    <select name="category" class="toolbar-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e((string) $cat['category_id']) ?>"<?= $categoryId === $cat['category_id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-outline">Apply</button>
                </form>

                <div class="table-wrap">
                    <table class="user-table inv-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock Qty</th>
                                <th>Status</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$products): ?>
                                <tr><td colspan="6" class="empty-row">No products found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($products as $p):
                                $qty = (int) ($p['quantity'] ?? 0);
                                $reorder = (int) ($p['reorder_level'] ?? 0);
                                $st = $stockStatus($qty, $reorder);
                                $pid = (int) $p['product_id'];
                                $catClass = 'cat-color-' . ((int) ($p['category_id'] ?? 0) % 5);
                            ?>
                                <tr>
                                    <td>
                                        <div class="prod-cell">
                                            <?php if (!empty($p['image_path'])): ?>
                                                <img class="prod-thumb" src="<?= e(base_url((string) $p['image_path'])) ?>" alt="<?= e($p['name']) ?>">
                                            <?php else: ?>
                                                <span class="prod-thumb prod-thumb--placeholder"><?= e(strtoupper(substr((string) $p['name'], 0, 1))) ?></span>
                                            <?php endif; ?>
                                            <span class="prod-meta">
                                                <a class="prod-name" href="<?= e(base_url('/admin/inventory/edit/' . $pid)) ?>"><?= e($p['name']) ?></a>
                                                <span class="prod-sku"><?= e((string) $p['sku']) ?></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td><span class="cat-pill <?= e($catClass) ?>"><?= e((string) ($p['category_name'] ?? '—')) ?></span></td>
                                    <td class="cell-price">$<?= e(number_format((float) $p['selling_price'], 2)) ?></td>
                                    <td class="cell-stock">
                                        <span class="stock-qty"><?= e((string) $qty) ?></span>
                                        <span class="stock-bar"><span class="stock-bar-fill <?= e($st['bar']) ?>" data-pct="<?= e((string) $stockPct($qty, $reorder)) ?>"></span></span>
                                    </td>
                                    <td><span class="status-pill <?= e($st['cls']) ?>"><?= e($st['label']) ?></span></td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <a class="icon-btn" href="<?= e(base_url('/admin/inventory/edit/' . $pid)) ?>" aria-label="Edit product" title="Edit">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                            </a>
                                            <form method="post" action="<?= e(base_url('/admin/inventory/delete/' . $pid)) ?>" data-confirm="Delete <?= e($p['name']) ?>? This cannot be undone.">
                                                <button type="submit" class="icon-btn icon-btn--danger" aria-label="Delete product" title="Delete">
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

                <div class="pagination">
                    <span class="pagination-info">Showing <?= e((string) $shown) ?> of <?= e((string) $total) ?> products.</span>
                    <div class="pagination-nav">
                        <?php if ($page > 1): ?>
                            <a class="btn-outline btn-sm" href="<?= e(base_url('/admin/inventory') . $filterQuery(['page' => $page - 1])) ?>">Previous</a>
                        <?php endif; ?>
                        <span class="pagination-page">Page <?= e((string) $page) ?> / <?= e((string) $pages) ?></span>
                        <?php if ($page < $pages): ?>
                            <a class="btn-outline btn-sm" href="<?= e(base_url('/admin/inventory') . $filterQuery(['page' => $page + 1])) ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Slide-over: Add / Edit product -->
    <a class="slideover-overlay<?= $panelOpen ? ' is-open' : '' ?>" href="<?= e(base_url('/admin/inventory')) ?>" aria-label="Close panel"></a>
    <aside class="slideover slideover--wide<?= $panelOpen ? ' is-open' : '' ?>" aria-hidden="<?= $panelOpen ? 'false' : 'true' ?>">
        <form method="post" action="<?= e($formAction) ?>" class="slideover-form" enctype="multipart/form-data" id="productForm">
            <div class="slideover-head">
                <div>
                    <h2 class="slideover-title"><?= $isCreate ? 'Add New Product' : 'Edit Product' ?></h2>
                    <p class="slideover-sub">Fill in the details to add to inventory.</p>
                </div>
                <a class="slideover-close" href="<?= e(base_url('/admin/inventory')) ?>" aria-label="Close">&times;</a>
            </div>

            <div class="slideover-body">
                <?php if ($formError !== null): ?>
                    <div class="flash flash--error"><?= e($formError) ?></div>
                <?php endif; ?>

                <!-- Image drop zone -->
                <div class="dropzone" id="dropzone" data-existing="<?= e($existingImage !== '' ? base_url($existingImage) : '') ?>">
                    <input type="file" name="image" id="imageInput" accept="image/png,image/jpeg,image/webp" class="dropzone-input">
                    <img class="dropzone-preview" id="dropPreview" alt="Preview"<?= $existingImage !== '' ? ' src="' . e(base_url($existingImage)) . '"' : '' ?>>
                    <div class="dropzone-prompt" id="dropPrompt">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p class="dropzone-text">Drop image here or <span class="dropzone-browse">Browse</span></p>
                        <p class="dropzone-hint">PNG, JPG, WEBP &middot; Max 5MB</p>
                    </div>
                </div>

                <label class="field">
                    <span class="field-label">Product Name</span>
                    <input type="text" name="name" id="pName" class="field-input" value="<?= e((string) $fv('name')) ?>" required>
                </label>

                <div class="field-row">
                    <label class="field">
                        <span class="field-label">Category</span>
                        <select name="category_id" id="pCategory" class="field-input toolbar-select">
                            <option value="">Select&hellip;</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e((string) $cat['category_id']) ?>"<?= (string) $fv('category_id') === (string) $cat['category_id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span class="field-label">Brand</span>
                        <select name="brand_id" id="pBrand" class="field-input toolbar-select">
                            <option value="">Select&hellip;</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= e((string) $b['brand_id']) ?>"<?= (string) $fv('brand_id') === (string) $b['brand_id'] ? ' selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <label class="field">
                    <span class="field-label">Description</span>
                    <textarea name="description" class="field-input" rows="3"><?= e((string) $fv('description')) ?></textarea>
                </label>

                <div class="field-row">
                    <label class="field">
                        <span class="field-label">Price (USD)</span>
                        <input type="number" name="selling_price" id="pPrice" class="field-input" step="0.01" min="0" value="<?= e((string) $fv('selling_price')) ?>" required>
                    </label>
                    <label class="field">
                        <span class="field-label">Stock Quantity</span>
                        <input type="number" name="quantity" id="pQty" class="field-input" min="0" value="<?= e((string) $fv('quantity', '0')) ?>" required>
                    </label>
                </div>

                <div class="field-row">
                    <label class="field">
                        <span class="field-label">Low Stock Threshold</span>
                        <input type="number" name="reorder_level" class="field-input" min="0" value="<?= e((string) $fv('reorder_level', '5')) ?>">
                    </label>
                    <label class="field">
                        <span class="field-label">Supplier</span>
                        <select name="supplier_id" class="field-input toolbar-select">
                            <option value="">Select&hellip;</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= e((string) $s['supplier_id']) ?>"<?= (string) $fv('supplier_id') === (string) $s['supplier_id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <!-- Live preview -->
                <div class="preview-card" id="previewCard">
                    <span class="preview-check" id="previewCheck" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <img class="preview-img" id="previewImg" alt=""<?= $existingImage !== '' ? ' src="' . e(base_url($existingImage)) . '"' : '' ?>>
                    <div class="preview-meta">
                        <span class="preview-name" id="previewName">Product name</span>
                        <span class="preview-sub" id="previewSub">Brand &middot; Category</span>
                    </div>
                    <div class="preview-figures">
                        <span class="preview-price" id="previewPrice">$0.00</span>
                        <span class="preview-stock" id="previewStock">0 in stock</span>
                    </div>
                </div>
            </div>

            <div class="slideover-foot">
                <a class="btn-outline" href="<?= e(base_url('/admin/inventory')) ?>">Cancel</a>
                <button type="submit" class="btn-cyan"><?= $isCreate ? 'Add Product' : 'Save Changes' ?></button>
            </div>
        </form>
    </aside>

    <script src="<?= e(base_url('js/inventory.js')) ?>"></script>
</body>
</html>
