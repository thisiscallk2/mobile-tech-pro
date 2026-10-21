<?php
/**
 * Forgot Password — request a reset link
 *
 * @var string|null $error
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password &mdash; MobileTech Pro</title>
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
                <p class="brand-sub">Recover access to your account in a few steps.</p>
            </div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <h1 class="form-title">Forgot Password</h1>
                <p class="form-sub">Enter your account email to reset your password</p>

                <?php if (!empty($error)): ?>
                    <div class="form-error" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form class="auth-form" method="post" action="<?= e(base_url('/forgot-password')) ?>" novalidate>
                    <label class="field">
                        <span class="field-label">Email Address</span>
                        <input
                            type="email"
                            name="email"
                            class="field-input"
                            autocomplete="email"
                            autofocus
                            required>
                    </label>
                    <button type="submit" class="btn-primary">Send Reset Link</button>
                </form>

                <p class="form-footer">
                    Remembered your password?
                    <a class="link-accent" href="<?= e(base_url('/login')) ?>">Back to login</a>
                </p>
            </div>
        </section>

    </main>
</body>
</html>
