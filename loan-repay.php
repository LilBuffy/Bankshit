<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$ref = trim($_GET['ref'] ?? $_POST['ref'] ?? '');
$errors = [];

$stmt = db()->prepare('SELECT * FROM loans WHERE loan_ref = ? AND user_id = ?');
$stmt->execute([$ref, $user['id']]);
$loan = $stmt->fetch();

if (!$loan) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="card empty-state">Loan not found.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if ($loan['status'] !== 'active') {
        $errors[] = 'This loan is not active and cannot accept payments.';
    } elseif ($user['account_status'] !== 'active') {
        $errors[] = 'Your account is locked. Unlock it in the Security Center to continue.';
    } else {
        $amount = $_POST['amount'] ?? '';
        if (!is_valid_amount($amount, MIN_TRANSACTION_AMOUNT, (float)$loan['remaining_balance'])) {
            $errors[] = 'Enter a valid payment amount up to the remaining balance.';
        } else {
            $amount = round((float)$amount, 2);
            $pdo = db();
            try {
                $pdo->beginTransaction();

                $accStmt = $pdo->prepare('SELECT balance, status FROM accounts WHERE id = ? FOR UPDATE');
                $accStmt->execute([$user['account_id']]);
                $account = $accStmt->fetch();

                $loanStmt = $pdo->prepare('SELECT * FROM loans WHERE id = ? FOR UPDATE');
                $loanStmt->execute([$loan['id']]);
                $lockedLoan = $loanStmt->fetch();

                if (!$account || $account['status'] !== 'active') {
                    throw new RuntimeException('Account not active.');
                }
                if ($lockedLoan['status'] !== 'active') {
                    throw new RuntimeException('Loan no longer active.');
                }
                if ($amount > (float)$account['balance']) {
                    $pdo->rollBack();
                    $errors[] = 'Insufficient balance for this payment.';
                } elseif ($amount > (float)$lockedLoan['remaining_balance']) {
                    $pdo->rollBack();
                    $errors[] = 'Payment exceeds remaining loan balance.';
                } else {
                    $newAccountBalance = round((float)$account['balance'] - $amount, 2);
                    $newLoanRemaining = round((float)$lockedLoan['remaining_balance'] - $amount, 2);
                    $newLoanStatus = $newLoanRemaining <= 0.005 ? 'fully_paid' : 'active';

                    $pdo->prepare('UPDATE accounts SET balance = ? WHERE id = ?')->execute([$newAccountBalance, $user['account_id']]);
                    $pdo->prepare('UPDATE loans SET remaining_balance = ?, status = ? WHERE id = ?')->execute([$newLoanRemaining, $newLoanStatus, $loan['id']]);

                    $txnRef = generate_reference('TXN');
                    $insTxn = $pdo->prepare(
                        'INSERT INTO transactions (transaction_ref, account_id, type_id, amount, balance_after, description, status)
                         VALUES (?, ?, (SELECT id FROM transaction_types WHERE code = "loan_repayment"), ?, ?, ?, "completed")'
                    );
                    $insTxn->execute([$txnRef, $user['account_id'], $amount, $newAccountBalance, 'Loan repayment ' . $loan['loan_ref']]);
                    $txnId = (int)$pdo->lastInsertId();

                    $insPayment = $pdo->prepare(
                        'INSERT INTO loan_payments (loan_id, amount, remaining_balance, transaction_id) VALUES (?, ?, ?, ?)'
                    );
                    $insPayment->execute([$loan['id'], $amount, $newLoanRemaining, $txnId]);

                    $notifMsg = $newLoanStatus === 'fully_paid'
                        ? 'Your loan ' . $loan['loan_ref'] . ' has been fully paid off.'
                        : 'Payment of ' . format_money($amount) . ' applied to loan ' . $loan['loan_ref'] . '.';
                    create_notification($pdo, (int)$user['id'], 'loan_payment', 'Loan payment recorded', $notifMsg);

                    $pdo->commit();
                    log_security_event((int)$user['id'], 'loan_repayment', $txnRef);

                    flash_set('success', 'Payment of ' . format_money($amount) . ' applied successfully.');
                    redirect('/loan-detail.php?ref=' . urlencode($loan['loan_ref']));
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Loan repayment failed: ' . $e->getMessage());
                $errors[] = 'Payment could not be completed. Please try again.';
            }
        }
    }
}

$pageTitle = t('loans_repay');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('loans_repay')) ?> — <?= e($loan['loan_ref']) ?></h1>

<div class="card" style="max-width:480px;">
    <div class="mb-16 flex-gap">
        <div><span class="text-faint"><?= e(t('loans_remaining')) ?>:</span> <strong><?= e(format_money($loan['remaining_balance'])) ?></strong></div>
        <div><span class="text-faint"><?= e(t('dashboard_available_balance')) ?>:</span> <strong><?= e(format_money($user['balance'])) ?></strong></div>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" data-validate data-confirm="Confirm this loan payment?">
        <?= csrf_field() ?>
        <input type="hidden" name="ref" value="<?= e($loan['loan_ref']) ?>">
        <div class="form-group">
            <label for="amount"><?= e(t('deposit_amount')) ?></label>
            <div class="input-prefix-group">
                <span class="input-prefix"><?= e(APP_CURRENCY) ?></span>
                <input type="number" id="amount" name="amount" step="0.01" min="<?= MIN_TRANSACTION_AMOUNT ?>" max="<?= e($loan['remaining_balance']) ?>" required data-min-amount="<?= MIN_TRANSACTION_AMOUNT ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= e(t('loans_repay')) ?></button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
