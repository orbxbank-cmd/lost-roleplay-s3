<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$requests = [];
$tableExists = true;

try {
    $db->fetch("SELECT 1 FROM unban_requests LIMIT 1");
} catch (\Exception $e) {
    $tableExists = false;
    $error = 'The unban_requests table does not exist. Run the migration SQL to create it.';
}

if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    try {
        $requestId = (int) $_POST['request_id'];
        $action = $_POST['action'];
        $adminNote = trim($_POST['admin_note'] ?? '');

        if ($action === 'approved' || $action === 'rejected') {
            $db->query(
                "UPDATE unban_requests SET status = ?, admin_note = ? WHERE id = ?",
                [$action, $adminNote, $requestId]
            );
            $success = "Request #$requestId has been $action.";
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

if ($tableExists) {
    try {
        $requests = $db->fetchAll("SELECT ur.*, u.username as local_username FROM unban_requests ur LEFT JOIN users u ON ur.user_id = u.id ORDER BY FIELD(ur.status, 'pending', 'approved', 'rejected'), ur.created_at DESC");
    } catch (\Exception $e) {
        $error = 'Error loading requests: ' . $e->getMessage();
    }
}
?>
<div class="admin-header">
    <h2><i class="fas fa-gavel"></i> Unban Requests</h2>
    <div>
        <a href="../public/logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Username</th>
                <th>Local User</th>
                <th>Ban Reason</th>
                <th>Their Defense</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="8" style="text-align: center;">No unban requests yet</td></tr>
            <?php else: ?>
                <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><?= $req['id'] ?></td>
                        <td><strong><?= htmlspecialchars($req['username']) ?></strong></td>
                        <td><?= $req['user_id'] ? htmlspecialchars($req['local_username']) : '<span style="color: var(--text-muted);">Guest</span>' ?></td>
                        <td style="max-width: 150px;"><?= htmlspecialchars($req['ban_reason'] ?? 'N/A') ?></td>
                        <td style="max-width: 250px;"><?= nl2br(htmlspecialchars($req['reason'])) ?></td>
                        <td>
                            <span class="status status-<?= $req['status'] === 'pending' ? 'processing' : $req['status'] ?>">
                                <?= $req['status'] ?>
                            </span>
                        </td>
                        <td><?= date('Y-m-d H:i', strtotime($req['created_at'])) ?></td>
                        <td>
                            <?php if ($req['status'] === 'pending'): ?>
                                <form method="POST" style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <input type="text" name="admin_note" placeholder="Note..." class="form-control" style="width: 100%; margin-bottom: 0.3rem; padding: 0.3rem 0.5rem; font-size: 0.8rem;">
                                    <button type="submit" name="action" value="approved" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                                    <button type="submit" name="action" value="rejected" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Reject</button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">
                                    <?= htmlspecialchars($req['admin_note'] ?? 'No note') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
