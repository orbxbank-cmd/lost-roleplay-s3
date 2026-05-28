<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appId = (int)$_POST['app_id'];

    if ($_POST['action'] === 'update_status') {
        $status = $_POST['status'];
        $db->update('staff_applications', ['status' => $status], 'id = :id', ['id' => $appId]);
        \Core\Logger::info('Application status updated', ['app_id' => $appId, 'status' => $status]);
        header("Location: applications.php?msg=updated");
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $db->delete('staff_applications', 'id = ?', [$appId]);
        header("Location: applications.php?msg=deleted");
        exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT * FROM shop_staff_applications";
$params = [];
if ($statusFilter) {
    $sql .= " WHERE status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY created_at DESC";
$apps = $db->fetchAll($sql, $params);
$message = $_GET['msg'] ?? '';
?>
<div class="admin-header">
    <h2><i class="fas fa-clipboard-list" style="color: var(--info);"></i> Staff Applications</h2>
</div>

<?php if ($message === 'updated'): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Application updated</div>
<?php elseif ($message === 'deleted'): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Application deleted</div>
<?php endif; ?>

<div class="action-bar">
    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
        <a href="applications.php" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-list"></i> All</a>
        <a href="applications.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-clock"></i> Pending</a>
        <a href="applications.php?status=accepted" class="btn btn-sm <?= $statusFilter === 'accepted' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-check"></i> Accepted</a>
        <a href="applications.php?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-times"></i> Rejected</a>
    </div>
</div>

<?php if (empty($apps)): ?>
    <div class="empty-state">
        <div class="icon"><i class="fas fa-clipboard-list"></i></div>
        <h3>No applications</h3>
        <p>No staff applications yet</p>
    </div>
<?php else: ?>
    <?php foreach ($apps as $app): ?>
        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="font-family: var(--header-font);"><?= htmlspecialchars($app['ingame_name']) ?></h3>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.3rem;">
                        <i class="fas fa-calendar"></i> Age: <?= $app['age'] ?> &nbsp;|&nbsp;
                        <i class="fas fa-globe"></i> <?= htmlspecialchars($app['country'] ?: 'N/A') ?> &nbsp;|&nbsp;
                        <i class="fas fa-clock"></i> <?= $app['play_hours'] ?: '?' ?>h/day &nbsp;|&nbsp;
                        <i class="fas fa-calendar-day"></i> <?= date('Y-m-d H:i', strtotime($app['created_at'])) ?>
                    </div>
                </div>
                <div>
                    <span class="status status-<?= $app['status'] === 'accepted' ? 'confirmed' : ($app['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                        <?= ucfirst($app['status']) ?>
                    </span>
                </div>
            </div>

            <?php if ($app['experience']): ?>
                <div style="margin-top: 1rem;">
                    <strong><i class="fas fa-history"></i> Experience:</strong>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.2rem;"><?= nl2br(htmlspecialchars($app['experience'])) ?></p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 0.8rem;">
                <strong><i class="fas fa-question-circle"></i> Why staff?</strong>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.2rem;"><?= nl2br(htmlspecialchars($app['why_staff'])) ?></p>
            </div>

            <?php if ($app['strengths'] || $app['weaknesses']): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.8rem;">
                    <?php if ($app['strengths']): ?>
                        <div>
                            <strong style="color: var(--success);"><i class="fas fa-plus-circle"></i> Strengths:</strong>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.2rem;"><?= nl2br(htmlspecialchars($app['strengths'])) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($app['weaknesses']): ?>
                        <div>
                            <strong style="color: var(--danger);"><i class="fas fa-minus-circle"></i> Weaknesses:</strong>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.2rem;"><?= nl2br(htmlspecialchars($app['weaknesses'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="margin-top: 0.8rem; font-size: 0.85rem; color: var(--text-muted);">
                <?php if ($app['discord']): ?>
                    <i class="fab fa-discord"></i> <?= htmlspecialchars($app['discord']) ?> &nbsp;
                <?php endif; ?>
                <?php if ($app['whatsapp']): ?>
                    <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($app['whatsapp']) ?>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                    <select name="status" onchange="this.form.submit()" class="form-control" style="width:auto;padding:4px 10px;font-size:0.8rem;">
                        <option value="pending" <?= $app['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="accepted" <?= $app['status'] === 'accepted' ? 'selected' : '' ?>>Accept</option>
                        <option value="rejected" <?= $app['status'] === 'rejected' ? 'selected' : '' ?>>Reject</option>
                    </select>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete application from <?= htmlspecialchars($app['ingame_name']) ?>?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
