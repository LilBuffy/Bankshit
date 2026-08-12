<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ratelimit.php';
require_login();

$user = current_user();
$errors = [];
$old = ['amount' => '', 'purpose' => '', 'term' => 12];
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $old['amount'] = $_POST['amount'] ?? '';
    $old['purpose'] = trim($_POST['purpose'] ?? '');
    $old['term'] = (int)($_POST['term'] ?? 0);
    $action = $_POST['form_action'] ?? 'calculate';

    if ($user['account_status'] !== 'active') {
        $errors[] = 'Your account is locked. Unlock it in the Security Center to continue.';
    }
    if (!is_valid_amount($old['amount'], LOAN_MIN_AMOUNT, LOAN_MAX_AMOUNT)) {
        $errors[] = 'Loan amount must be between ' . format_money(LOAN_MIN_AMOUNT) . ' and ' . format_money(LOAN_MAX_AMOUNT) . '.';
    }
    if ($old['term'] < LOAN_MIN_TERM_MONTHS || $old['term'] > LOAN_MAX_TERM_MONTHS) {
        $errors[] = 'Term must be between ' . LOAN_MIN_TERM_MONTHS . ' and ' . LOAN_MAX_TERM_MONTHS . ' months.';
    }
    if ($old['purpose'] === '' || mb_strlen($old['purpose']) > 255) {
        $errors[] = 'Please describe the loan purpose.';
    }

    if (empty($errors)) {
        $preview = calculate_loan((float)$old['amount'], LOAN_INTEREST_RATE, $old['term']);

        if ($action === 'submit') {
            if (is_action_rate_limited((int)$user['id'], 'loan_application', RATE_LIMIT_LOAN_APPLICATIONS_PER_DAY, 1440)) {
                $errors[] = 'You have reached the daily loan application limit.';
            } else {
                $pdo = db();
                try {
                    $ref = generate_reference('LOAN');
                    $ins = $pdo->prepare(
                        'INSERT INTO loans (loan_ref, user_id, principal, interest_rate, term_months, monthly_payment, total_repayment, remaining_balance, purpose, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
                    );
                    $ins->execute([
                        $ref, $user['id'], $preview['principal'], $preview['interest_rate'], $preview['term_months'],
                        $preview['monthly_payment'], $preview['total_repayment'], $preview['total_repayment'], $old['purpose'],
                    ]);
                    create_notification($pdo, (int)$user['id'], 'loan_applied', 'Loan application submitted', 'Your loan application ' . $ref . ' is pending review.');
                    log_security_event((int)$user['id'], 'loan_application', $ref);

                    flash_set('success', 'Loan application submitted successfully.');
                    redirect('/loans.php');
                } catch (Throwable $e) {
                    error_log('Loan application failed: ' . $e->getMessage());
                    $errors[] = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

$pageTitle = t('loans_apply');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('loans_apply')) ?></h1>
<p class="text-faint"><?= e(t('loans_demo_notice')) ?> Fixed demo interest rate: <?= LOAN_INTEREST_RATE ?>% flat.</p>

<div class="card" style="max-width:560px;">
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" data-validate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="amount"><?= e(t('loans_amount')) ?></label>
            <div class="input-prefix-group">
                <span class="input-prefix"><?= e(APP_CURRENCY) ?></span>
                <input type="number" id="amount" name="amount" step="0.01" min="<?= LOAN_MIN_AMOUNT ?>" max="<?= LOAN_MAX_AMOUNT ?>" required value="<?= e((string)$old['amount']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="term"><?= e(t('loans_term')) ?></label>
            <select id="term" name="term" required>
                <?php foreach ([3, 6, 12, 18, 24, 36] as $m): ?>
                    <option value="<?= $m ?>" <?= (int)$old['term'] === $m ? 'selected' : '' ?>><?= $m ?> months</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="purpose"><?= e(t('loans_purpose')) ?></label>
            <textarea id="purpose" name="purpose" required maxlength="255"><?= e($old['purpose']) ?></textarea>
        </div>

        <div class="flex-gap">
            <button type="submit" name="form_action" value="calculate" class="btn btn-secondary"><?= e(t('loans_calculate')) ?></button>
            <?php if ($preview): ?>
                <button type="submit" name="form_action" value="submit" class="btn btn-primary"><?= e(t('loans_submit')) ?></button>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($preview): ?>
    <div class="card" style="margin-top:18px;background:var(--color-surface-2);">
        <h3>Loan Preview <span class="badge badge-neutral">DEMO</span></h3>
        <table>
            <tr><td class="text-faint">Principal</td><td><?= e(format_money($preview['principal'])) ?></td></tr>
            <tr><td class="text-faint">Interest Rate</td><td><?= e((string)$preview['interest_rate']) ?>%</td></tr>
            <tr><td class="text-faint">Term</td><td><?= (int)$preview['term_months'] ?> months</td></tr>
            <tr><td class="text-faint"><?= e(t('loans_estimated_interest')) ?></td><td><?= e(format_money($preview['total_interest'])) ?></td></tr>
            <tr><td class="text-faint"><?= e(t('loans_total_repayment')) ?></td><td><strong><?= e(format_money($preview['total_repayment'])) ?></strong></td></tr>
            <tr><td class="text-faint"><?= e(t('loans_monthly_payment')) ?></td><td><strong><?= e(format_money($preview['monthly_payment'])) ?></strong></td></tr>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
