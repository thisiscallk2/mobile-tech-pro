<?php
/**
 * Admin sidebar navigation.
 *
 * @var array<string, mixed> $user    logged-in session user
 * @var string               $active  active nav key (e.g. 'dashboard')
 */

$active = $active ?? '';
$user = $user ?? [];

if (!function_exists('dash_icon')) {
    /**
     * Return an inline SVG icon (markup only -- no inline CSS/JS).
     */
    function dash_icon(string $name): string
    {
        $paths = [
            'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'inventory' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>',
            'pos' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
            'sales' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h8"/>',
            'repair' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
            'jobs' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
            'reports' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            'suppliers' => '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
            'pricing' => '<path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
            'customers' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        ];
        $inner = $paths[$name] ?? '';
        return '<svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" '
            . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
            . 'aria-hidden="true">' . $inner . '</svg>';
    }
}

$groups = [
    'Main' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => base_url('/admin/dashboard')],
        ['key' => 'users', 'label' => 'User Management', 'icon' => 'users', 'href' => base_url('/admin/users')],
        ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'inventory', 'href' => base_url('/admin/inventory')],
    ],
    'Sales' => [
        ['key' => 'pos', 'label' => 'Point of Sale', 'icon' => 'pos', 'href' => base_url('/sales/pos')],
        ['key' => 'sales-history', 'label' => 'Sales History', 'icon' => 'sales', 'href' => base_url('/sales/history')],
    ],
    'Repairs' => [
        ['key' => 'repair-board', 'label' => 'Repair Board', 'icon' => 'repair', 'href' => base_url('/technician/board')],
        ['key' => 'my-tasks', 'label' => 'My Tasks', 'icon' => 'jobs', 'href' => base_url('/technician/my-tasks')],
        ['key' => 'all-jobs', 'label' => 'All Jobs', 'icon' => 'jobs', 'href' => base_url('/technician/all-jobs')],
    ],
    'Business' => [
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'reports', 'href' => base_url('/admin/reports')],
        ['key' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'suppliers', 'href' => base_url('/admin/suppliers')],
        ['key' => 'pricing', 'label' => 'Pricing', 'icon' => 'pricing', 'href' => base_url('/admin/pricing')],
        ['key' => 'coupons', 'label' => 'Coupons', 'icon' => 'pricing', 'href' => base_url('/admin/coupons')],
        ['key' => 'customers', 'label' => 'Customers', 'icon' => 'customers', 'href' => base_url('/sales/customers')],
    ],
];

// Role-aware navigation: show only the sections the current user may open.
$userType = (string) ($user['user_type'] ?? '');
if ($userType !== 'admin') {
    $allowedByRole = [
        'sales_staff' => ['dashboard', 'pos', 'sales-history', 'customers'],
        'technician' => ['dashboard', 'repair-board', 'my-tasks', 'all-jobs'],
    ];
    $allowed = $allowedByRole[$userType] ?? [];
    foreach ($groups as $groupName => $items) {
        $groups[$groupName] = array_values(array_filter(
            $items,
            static fn(array $item): bool => in_array($item['key'], $allowed, true)
        ));
    }
    $groups = array_filter($groups, static fn(array $items): bool => $items !== []);
}

$displayName = trim((string) ($user['full_name'] ?? 'User'));
$roleLabel = ucwords(str_replace('_', ' ', (string) ($user['user_type'] ?? '')));
$initials = '';
foreach (array_slice(explode(' ', $displayName), 0, 2) as $part) {
    if ($part !== '') {
        $initials .= strtoupper($part[0]);
    }
}
$initials = $initials !== '' ? $initials : 'U';
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-logo" aria-hidden="true"></span>
        <span class="brand-name">MobileTechPro</span>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($groups as $groupName => $items): ?>
            <div class="nav-group">
                <p class="nav-group-title"><?= e(strtoupper($groupName)) ?></p>
                <ul class="nav-list">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <a
                                class="nav-link<?= $item['key'] === $active ? ' is-active' : '' ?>"
                                href="<?= e($item['href']) ?>">
                                <?= dash_icon($item['icon']) ?>
                                <span class="nav-label"><?= e($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-bottom">
        <a class="nav-link nav-settings" href="#">
            <?= dash_icon('settings') ?>
            <span class="nav-label">Settings</span>
        </a>
        <div class="profile-card">
            <span class="profile-avatar"><?= e($initials) ?></span>
            <span class="profile-meta">
                <span class="profile-name"><?= e($displayName) ?></span>
                <span class="profile-role"><?= e($roleLabel) ?></span>
            </span>
            <a class="profile-logout" href="<?= e(base_url('/logout')) ?>" aria-label="Log out">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </a>
        </div>
    </div>
</aside>
