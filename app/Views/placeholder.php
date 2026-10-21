<?php
/**
 * Simple protected placeholder page used until each role's real screen is
 * built in a later round.
 *
 * @var array<string, mixed> $user
 * @var string               $title
 * @var string               $round
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/app.css')) ?>">
</head>
<body class="app-body">
    <header class="app-topbar">
        <div class="app-brand">
            <span class="brand-logo" aria-hidden="true"></span>
            <span class="brand-name">MobileTechPro</span>
        </div>
        <div class="app-user">
            <span class="app-user-name"><?= e($user['full_name'] ?? 'User') ?></span>
            <span class="app-user-role"><?= e(str_replace('_', ' ', (string) ($user['user_type'] ?? ''))) ?></span>
            <a class="app-logout" href="<?= e(base_url('/logout')) ?>">Log out</a>
        </div>
    </header>

    <main class="app-main">
        <div class="app-card">
            <h1 class="app-card-title"><?= e($title) ?></h1>
            <p class="app-card-sub">
                Welcome, <?= e($user['full_name'] ?? 'User') ?>. This screen is
                coming in <?= e($round) ?>.
            </p>
            <p class="app-badge">Placeholder</p>
        </div>
    </main>
</body>
</html>
