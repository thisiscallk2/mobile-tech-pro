<?php
/**
 * Reset Password — choose a new password from a valid token
 *
 * @var string      $token
 * @var string      $email
 * @var string|null $error
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/auth.css')) ?>">
</head>
<body class="auth-body">
    <main class="auth-split">

        <section class="auth-brand">
            <div class="brand-top">
                <span class="brand-logo" aria-hidden="true"></span>
                <span class="brand-name">MobileTechPro</span>
            </div>
            <div class="brand-center">
                <p class="tagline">
                    <span class="t-line">Sell Smart.</span>
                    <span class="t-line t-accent">Repair Fast.</span>
                    <span class="t-line">Manage Everything.</span>
                </p>
                <p class="brand-sub">Choose a new password for your account.</p>
            </div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <h1 class="form-title">Reset Password</h1>
                <p class="form-sub">Set a new password for <?= e($email) ?></p>

                <?php if (!empty($error)): ?>
                    <div class="form-error" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form class="auth-form" method="post" action="<?= e(base_url('/reset-password')) ?>" novalidate>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <label class="field">
                        <span class="field-label">New Password</span>
                        <input
                            type="password"
                            name="password"
                            class="field-input"
                            autocomplete="new-password"
                            minlength="8"
                            autofocus
                            required>
                    </label>

                    <label class="field">
                        <span class="field-label">Confirm New Password</span>
                        <input
                            type="password"
                            name="password_confirm"
                            class="field-input"
                            autocomplete="new-password"
                            minlength="8"
                            required>
                    </label>

                    <button type="submit" class="btn-primary">Update Password</button>
                </form>

                <p class="form-footer">
                    <a class="link-accent" href="<?= e(base_url('/login')) ?>">Back to login</a>
                </p>
            </div>
        </section>

    </main>
</body>
</html>
