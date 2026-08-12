<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$stmt = db()->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY applied_at DESC');
$stmt->execute([$user['id']]);
$loans = $stmt->fetchAll();

$statusBadge = [
    'pending' => 'badge-warning', 'under_review' => 'badge-warning',
    'approved' => 'badge-success', 'active' => 'badge-success',
    'fully_paid' => 'badge-neutral', 'rejected' => 'badge-danger', 'defaulted' => 'badge-danger',
];

$pageTitle = t('loans_title');
require __DIR__ . '/includes/header.php';
?>

<div class="flex-between mb-16">
    <h1><?= e(t('loans_title')) ?></h1>
    <a href="<?= APP_BASE_URL ?>/loan-apply.php" class="btn btn-primary"><?= e(t('loans_apply')) ?></a>
</div>
<p class="text-faint"><?= e(t('loans_demo_notice')) ?></p>

<div class="card">
    <?php if (empty($loans)): ?>
        <div class="empty-state"><?= e(t('loans_no_loans')) ?></div>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Reference</th><th><?= e(t('loans_amount')) ?></th><th><?= e(t('loans_term')) ?></th>
                <th><?= e(t('loans_monthly_payment')) ?></th><th><?= e(t('loans_remaining')) ?></th><th><?= e(t('loans_status')) ?></th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($loans as $loan): ?>
                <tr>
                    <td><?= e($loan['loan_ref']) ?></td>
                    <td><?= e(format_money($loan['principal'])) ?></td>
                    <td><?= (int)$loan['term_months'] ?> mo</td>
                    <td><?= e(format_money($loan['monthly_payment'])) ?></td>
                    <td><?= e(format_money($loan['remaining_balance'])) ?></td>
                    <td><span class="badge <?= $statusBadge[$loan['status']] ?? 'badge-neutral' ?>"><?= e(t('status_' . $loan['status'])) ?></span></td>
                    <td><a href="<?= APP_BASE_URL ?>/loan-detail.php?ref=<?= urlencode($loan['loan_ref']) ?>" class="btn btn-ghost btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
