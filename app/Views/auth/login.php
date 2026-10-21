<?php
/**
 * @var string|null $error
 * @var string|null $success
 * @var string      $old
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In &mdash; MobileTech Pro</title>
    <link rel="stylesheet" href="<?= e(base_url('css/auth.css')) ?>">
</head>
<body class="auth-body">
    <main class="auth-split">

        <!-- LEFT : brand panel -->
        <section class="auth-brand">
            <div class="brand-top">
                <span class="brand-logo" aria-hidden="true"></span>
                <span class="brand-name">MobileTechPro</span>
            </div>

            <div class="brand-center">
                <div class="phone-mock" aria-hidden="true">
                    <div class="phone-notch"></div>
                    <div class="phone-screen">
                        <div class="widget-charging">
                            <span class="charge-bolt"></span>
                            <span class="charge-pct">82%</span>
                            <span class="charge-label">Charging</span>
                        </div>
                        <div class="mini-chart">
                            <span class="bar bar-1"></span>
                            <span class="bar bar-2"></span>
                        </div>
                    </div>
                </div>

                <p class="tagline">
                    <span class="t-line">Sell Smart.</span>
                    <span class="t-line t-accent">Repair Fast.</span>
                    <span class="t-line">Manage Everything.</span>
                </p>

                <p class="brand-sub">
                    MobileTech Pro is the all-in-one operating system for repair
                    shops and retailers &mdash; point of sale, inventory, repair
                    tracking and customer management in one place.
                </p>
            </div>
        </section>

        <!-- RIGHT : form panel -->
        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <h1 class="form-title">Welcome Back</h1>
                <p class="form-sub">Enter your credentials to access your dashboard</p>

                <?php if (!empty($error)): ?>
                    <div class="form-error" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="form-success" role="status"><?= e($success) ?></div>
                <?php endif; ?>

                <form class="auth-form" method="post" action="<?= e(base_url('/login')) ?>" novalidate>
                    <label class="field">
                        <span class="field-label">Email or Username</span>
                        <input
                            type="text"
                            name="identifier"
                            class="field-input"
                            value="<?= e($old ?? '') ?>"
                            autocomplete="username"
                            autofocus
                            required>
                    </label>
                        
                    <label class="field">
                        <span class="field-label">Password</span>
                        <span class="field-password">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="field-input"
                                autocomplete="current-password"
                                required>
                            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">
                                <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </span>
                    </label>

                    <div class="form-row">
                        <label class="remember">
                            <input type="checkbox" name="remember" value="1">
                            <span>Remember me</span>
                        </label>
                        <a class="link-accent" href="<?= e(base_url('/forgot-password')) ?>">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-primary">Log In</button>
                </form>

                <p class="form-footer">
                    By logging in, you agree to our
                    <a class="link-accent" href="#">Terms of Service</a> and
                    <a class="link-accent" href="#">Privacy Policy</a>
                </p>
            </div>
        </section>

    </main>

    <script src="<?= e(base_url('js/auth.js')) ?>"></script>
</body>
</html>
