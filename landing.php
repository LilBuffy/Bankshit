<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$pageTitle = t('tagline');
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(t('app_name')) ?> · <?= e($pageTitle) ?></title>
<meta name="description" content="<?= e(t('secondary_tagline')) ?>">
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/style.css">
</head>
<body class="landing-body">

<!-- ================= NAV ================= -->
<input type="checkbox" id="landing-nav-toggle" class="landing-nav-checkbox">
<header class="landing-nav">
    <div class="landing-nav-inner">
        <a href="#top" class="landing-nav-brand">
            <span class="landing-brand-mark">NB</span>
            <span class="landing-brand-name"><?= e(t('app_name')) ?></span>
        </a>

        <label for="landing-nav-toggle" class="landing-nav-burger" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </label>

        <nav class="landing-nav-links">
            <a href="#top">Home</a>
            <a href="#about">About</a>
            <a href="#services">Services</a>
            <a href="<?= APP_BASE_URL ?>/login.php" class="landing-nav-cta-ghost"><?= e(t('login_button')) ?></a>
            <a href="<?= APP_BASE_URL ?>/register.php" class="landing-nav-cta"><?= e(t('register_button')) ?></a>
        </nav>
    </div>
</header>

<main id="top">
    <!-- ================= HERO ================= -->
    <section class="landing-hero">
        <div class="landing-hero-inner">
            <div class="landing-hero-copy">
                <span class="landing-eyebrow" style="display: none;">Educational Digital Banking Demo</span>
                <h1 class="landing-hero-title">Your money. Your decisions. Your future regret.</h1>
                <p class="landing-hero-desc">
                    <?= e(t('app_name')) ?> is a fictional digital banking sandbox. Manage your money, track your transactions, send transfers, and make questionable financial decisions in one place. Your balance is your problem. We just display the damage. <?= e(t('secondary_tagline')) ?>
                </p>
                <div class="landing-hero-actions">
                    <a href="<?= APP_BASE_URL ?>/register.php" class="btn-landing btn-landing-primary">Open an Account</a>
                    <a href="<?= APP_BASE_URL ?>/login.php" class="btn-landing btn-landing-outline"><?= e(t('login_button')) ?></a>
                </div>
                <div class="landing-hero-note">No real money. No real banks. 100% demo data, for learning only.</div>
            </div>

            <div class="landing-hero-art" aria-hidden="true">
                <svg viewBox="0 0 420 420" xmlns="http://www.w3.org/2000/svg" class="landing-vault-svg">
                    <circle cx="210" cy="210" r="188" fill="none" stroke="currentColor" stroke-opacity="0.14" stroke-width="1"/>
                    <circle cx="210" cy="210" r="150" fill="none" stroke="currentColor" stroke-opacity="0.22" stroke-width="1"/>
                    <circle cx="210" cy="210" r="112" fill="none" stroke="currentColor" stroke-opacity="0.35" stroke-width="1"/>
                    <circle cx="210" cy="210" r="112" fill="#12181f" stroke="currentColor" stroke-width="2.5"/>
                    <circle cx="210" cy="210" r="34" fill="none" stroke="currentColor" stroke-width="2.5"/>
                    <circle cx="210" cy="210" r="8" fill="currentColor"/>
                    <rect x="204" y="216" width="12" height="34" rx="2" fill="currentColor"/>
                    <g stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <line x1="210" y1="98" x2="210" y2="118"/>
                        <line x1="210" y1="302" x2="210" y2="322"/>
                        <line x1="98" y1="210" x2="118" y2="210"/>
                        <line x1="302" y1="210" x2="322" y2="210"/>
                    </g>
                    <g fill="currentColor">
                        <circle cx="210" cy="40" r="5"/>
                        <circle cx="70" cy="120" r="5"/>
                        <circle cx="350" cy="120" r="5"/>
                        <circle cx="70" cy="300" r="5"/>
                        <circle cx="350" cy="300" r="5"/>
                        <circle cx="210" cy="380" r="5"/>
                    </g>
                    <g stroke="currentColor" stroke-opacity="0.4" stroke-width="1.5">
                        <line x1="210" y1="40" x2="210" y2="98"/>
                        <line x1="70" y1="120" x2="160" y2="176"/>
                        <line x1="350" y1="120" x2="260" y2="176"/>
                        <line x1="70" y1="300" x2="160" y2="244"/>
                        <line x1="350" y1="300" x2="260" y2="244"/>
                        <line x1="210" y1="380" x2="210" y2="322"/>
                    </g>
                </svg>
            </div>
        </div>
    </section>

    <!-- ================= SERVICES ================= -->
    <section id="services" class="landing-section">
        <div class="landing-section-inner">
            <span class="landing-eyebrow">Services</span>
            <h2 class="landing-section-title">Everything the demo bank offers</h2>

            <div class="landing-feature-grid">
                <div class="landing-feature-card">
                    <div class="landing-feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/></svg>
                    </div>
                    <h3>Transfers &amp; Payments</h3>
                    <p>Move funds between accounts, deposit, and withdraw with a clear, itemized transaction history.</p>
                </div>
                <div class="landing-feature-card">
                    <div class="landing-feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/></svg>
                    </div>
                    <h3>Loans</h3>
                    <p>Apply for a loan, review terms and interest, and repay on your own schedule.</p>
                </div>
                <div class="landing-feature-card">
                    <div class="landing-feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2l8 3v6c0 5-3.4 8.5-8 11-4.6-2.5-8-6-8-11V5z"/><path d="M9 12l2 2 4-4"/></svg>
                    </div>
                    <h3>Account Security</h3>
                    <p>Login alerts, rate‑limited sign‑ins, and a full security log for every account.</p>
                </div>
                <div class="landing-feature-card">
                    <div class="landing-feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg>
                    </div>
                    <h3>Dashboard &amp; Insights</h3>
                    <p>See balances, recent activity, and notifications at a glance from one dashboard.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= ABOUT ================= -->
    <section id="about" class="landing-section landing-section-alt">
        <div class="landing-section-inner landing-about-grid">
            <div>
                <span class="landing-eyebrow">About</span>
                <h2 class="landing-section-title">A safe place to learn how banking software works</h2>
                <p class="landing-about-desc">
                    <?= e(t('app_name')) ?> ("<?= e(t('tagline')) ?>") is a fictional, educational banking
                    demo. It exists to show how account management, transfers, and loans come together in a
                    real product &mdash; without touching a single real bank account.
                </p>
                <div class="landing-stats">
                    <div class="landing-stat">
                        <span class="landing-stat-num">100%</span>
                        <span class="landing-stat-label">Demo Data</span>
                    </div>
                    <div class="landing-stat">
                        <span class="landing-stat-num">0</span>
                        <span class="landing-stat-label">Real Transactions</span>
                    </div>
                    <div class="landing-stat">
                        <span class="landing-stat-num">24/7</span>
                        <span class="landing-stat-label">Sandbox Access</span>
                    </div>
                </div>
            </div>
            <div class="landing-about-card">
                <h3>Not a real bank</h3>
                <p>No real money, payment processors, or financial institutions are involved anywhere in this
                project. All balances and transactions are fictional.</p>
            </div>
        </div>
    </section>

    <!-- ================= CTA ================= -->
    <section class="landing-cta-band">
        <div class="landing-section-inner landing-cta-inner">
            <h2>Ready to see it in action?</h2>
            <p>Create a free demo account or sign in to an existing one.</p>
            <div class="landing-hero-actions">
                <a href="<?= APP_BASE_URL ?>/register.php" class="btn-landing btn-landing-primary"><?= e(t('register_button')) ?></a>
                <a href="<?= APP_BASE_URL ?>/login.php" class="btn-landing btn-landing-outline"><?= e(t('login_button')) ?></a>
            </div>
        </div>
    </section>
</main>

<footer class="landing-footer">
    <div class="landing-section-inner landing-footer-inner">
        <div class="landing-nav-brand">
            <span class="landing-brand-mark">NB</span>
            <span class="landing-brand-name"><?= e(t('app_name')) ?></span>
        </div>
        <div class="landing-footer-links">
            <a href="#top">Home</a>
            <a href="#about">About</a>
            <a href="#services">Services</a>
            <a href="<?= APP_BASE_URL ?>/login.php"><?= e(t('login_button')) ?></a>
            <a href="<?= APP_BASE_URL ?>/register.php"><?= e(t('register_button')) ?></a>
        </div>
        <div class="landing-footer-copy">&copy; <?= date('Y') ?> <?= e(t('app_name')) ?>. Fictional demo project — not a real financial institution.</div>
    </div>
</footer>

</body>
</html>
