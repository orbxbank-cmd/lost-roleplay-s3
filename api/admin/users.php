<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $ingameName = trim($_POST['ingame_name'] ?? '');
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;

        try {
            $db->insert('users', [
                'username' => $username,
                'password' => $password,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'ingame_name' => $ingameName ?: null,
                'is_admin' => $isAdmin,
            ]);
            \Core\Logger::info('User added', ['username' => $username]);
            $message = 'User added successfully';
        } catch (\Exception $e) {
            $message = 'Error: Username already exists';
        }
    }

    if ($action === 'delete') {
        $userId = (int)$_POST['user_id'];
        if ($userId === \Core\Auth::userId()) {
            $message = 'Cannot delete your own account';
        } else {
            $db->delete('users', 'id = ?', [$userId]);
            \Core\Logger::info('User deleted', ['id' => $userId]);
            $message = 'User deleted';
        }
    }

    if ($action === 'toggle_admin') {
        $userId = (int)$_POST['user_id'];
        $user = $db->fetch("SELECT is_admin FROM shop_users WHERE id = ?", [$userId]);
        if ($user) {
            $newAdmin = $user['is_admin'] ? 0 : 1;
            $db->update('users', ['is_admin' => $newAdmin], 'id = :id', ['id' => $userId]);
            \Core\Logger::info('Admin toggled', ['user_id' => $userId, 'is_admin' => $newAdmin]);
            $message = $newAdmin ? 'User promoted to admin' : 'Admin rights removed';
        }
    }

    if ($action === 'toggle_youtuber') {
        $userId = (int)$_POST['user_id'];
        $user = $db->fetch("SELECT is_youtuber FROM shop_users WHERE id = ?", [$userId]);
        if ($user) {
            $newYT = $user['is_youtuber'] ? 0 : 1;
            $db->update('users', ['is_youtuber' => $newYT], 'id = :id', ['id' => $userId]);
            \Core\Logger::info('YouTuber toggled', ['user_id' => $userId, 'is_youtuber' => $newYT]);
            $message = $newYT ? 'User marked as YouTuber' : 'YouTuber status removed';
        }
    }

    if ($action === 'add_coins') {
        $userId = (int)$_POST['user_id'];
        $amount = (int)$_POST['coin_amount'];
        $description = trim($_POST['coin_description'] ?? 'Admin adjustment');
        if ($amount !== 0) {
            $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$amount, $userId]);
            $db->insert('coin_transactions', [
                'user_id' => $userId,
                'amount' => $amount,
                'type' => 'admin',
                'description' => $description,
            ]);
            \Core\Logger::info('Coins adjusted', ['user_id' => $userId, 'amount' => $amount]);
            $message = $amount > 0 ? "Added $amount coins" : "Removed " . abs($amount) . " coins";
        }
    }
}

$users = $db->fetchAll("SELECT id, username, email, phone, ingame_name, coins, is_admin, is_youtuber, is_active, created_at FROM shop_users ORDER BY id");
?>
<div class="admin-header">
    <h2><i class="fas fa-users" style="color: var(--info);"></i> Manage Users</h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 2rem;">
    <div>
        <h3 style="margin-bottom: 1rem; font-family: var(--header-font);"><i class="fas fa-user-plus"></i> Add User</h3>
        <form method="POST" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username *</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password *</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone</label>
                <input type="tel" name="phone" class="form-control">
            </div>
            <div class="form-group">
                <label><i class="fas fa-gamepad"></i> In-game Name</label>
                <input type="text" name="ingame_name" class="form-control">
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_admin" value="1">
                    <i class="fas fa-shield-alt" style="color: var(--info);"></i> Admin
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-plus"></i> Add User</button>
        </form>
    </div>

    <div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>In-game</th>
                        <th>Coins</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($user['phone'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($user['ingame_name'] ?: '-') ?></td>
                            <td><strong style="color:var(--accent);"><?= number_format($user['coins']) ?></strong></td>
                            <td>
                                <?php if ($user['is_admin']): ?>
                                    <i class="fas fa-shield-alt" style="color: var(--info);"></i> Admin
                                <?php elseif ($user['is_youtuber']): ?>
                                    <span style="color:#ff4444;"><i class="fab fa-youtube"></i> YouTuber</span>
                                <?php else: ?>
                                    <i class="fas fa-user" style="color: var(--text-muted);"></i> User
                                <?php endif; ?>
                            </td>
                            <td><?= $user['is_active'] ? '<i class="fas fa-check-circle" style="color: var(--success);"></i>' : '<i class="fas fa-times-circle" style="color: var(--danger);"></i>' ?></td>
                            <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $user['is_admin'] ? 'btn-secondary' : 'btn-primary' ?>" title="Toggle admin">
                                        <i class="fas fa-<?= $user['is_admin'] ? 'user' : 'shield-alt' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_youtuber">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $user['is_youtuber'] ? 'btn-danger' : 'btn-secondary' ?>" title="Toggle YouTuber">
                                        <i class="fab fa-youtube"></i>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-gold" onclick="showCoinsModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')" title="Manage coins">
                                    <i class="fas fa-coins"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars($user['username']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Coins Modal -->
<div id="coins-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;max-width:420px;width:90%;">
        <h3 style="margin-bottom:0.5rem;"><i class="fas fa-coins" style="color:var(--accent);"></i> Manage Coins</h3>
        <p style="color:var(--text-secondary);margin-bottom:1.5rem;" id="coins-modal-user">User: </p>
        <form method="POST">
            <input type="hidden" name="action" value="add_coins">
            <input type="hidden" name="user_id" id="coins-user-id">
            <div class="form-group">
                <label>Amount (+ to add, - to remove)</label>
                <input type="number" name="coin_amount" class="form-control" required placeholder="e.g. 500">
            </div>
            <div class="form-group">
                <label>Reason</label>
                <input type="text" name="coin_description" class="form-control" placeholder="e.g. Payment received" value="Admin adjustment">
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Confirm</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('coins-modal').style.display='none'"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCoinsModal(userId, username) {
    document.getElementById('coins-user-id').value = userId;
    document.getElementById('coins-modal-user').textContent = 'User: ' + username;
    document.getElementById('coins-modal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
