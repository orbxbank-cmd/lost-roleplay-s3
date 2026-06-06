<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$message = '';
$messageType = 'success';

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
                'coins' => 0,
            ]);
            \Core\Logger::info('User added', ['username' => $username]);
            $message = 'User added successfully';
        } catch (\Exception $e) {
            $message = 'Error: Username already exists';
            $messageType = 'danger';
        }
    }

    if ($action === 'delete') {
        $userId = (int)$_POST['user_id'];
        if ($userId === \Core\Auth::userId()) {
            $message = 'Cannot delete your own account';
            $messageType = 'danger';
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
            $message = $newAdmin ? 'User promoted to admin' : 'Admin rights removed';
        }
    }

    if ($action === 'add_coins') {
        $userId = (int)$_POST['user_id'];
        $amount = (int)$_POST['amount'];
        $reason = trim($_POST['reason'] ?? '');

        if ($amount > 0) {
            $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$amount, $userId]);
            $db->insert('coin_transactions', [
                'user_id' => $userId,
                'amount' => $amount,
                'type' => 'admin',
                'description' => $reason ?: 'Admin added coins',
            ]);
            $user = $db->fetch("SELECT username FROM shop_users WHERE id = ?", [$userId]);
            \Core\Logger::info("Added $amount coins to {$user['username']}");
            $message = "Added $amount coins to {$user['username']}";
        }
    }

    if ($action === 'remove_coins') {
        $userId = (int)$_POST['user_id'];
        $amount = (int)$_POST['amount'];
        $reason = trim($_POST['reason'] ?? '');

        if ($amount > 0) {
            $db->query("UPDATE shop_users SET coins = GREATEST(coins - ?, 0) WHERE id = ?", [$amount, $userId]);
            $db->insert('coin_transactions', [
                'user_id' => $userId,
                'amount' => -$amount,
                'type' => 'admin',
                'description' => $reason ?: 'Admin removed coins',
            ]);
            $user = $db->fetch("SELECT username FROM shop_users WHERE id = ?", [$userId]);
            \Core\Logger::info("Removed $amount coins from {$user['username']}");
            $message = "Removed $amount coins from {$user['username']}";
        }
    }

    if ($action === 'set_coins') {
        $userId = (int)$_POST['user_id'];
        $newBalance = (int)$_POST['coins'];
        $reason = trim($_POST['reason'] ?? '');

        $user = $db->fetch("SELECT username, coins FROM shop_users WHERE id = ?", [$userId]);
        if ($user) {
            $diff = $newBalance - $user['coins'];
            $db->update('users', ['coins' => $newBalance], 'id = :id', ['id' => $userId]);
            if ($diff != 0) {
                $db->insert('coin_transactions', [
                    'user_id' => $userId,
                    'amount' => $diff,
                    'type' => 'admin',
                    'description' => $reason ?: "Admin set balance to $newBalance",
                ]);
            }
            \Core\Logger::info("Set {$user['username']} coins to $newBalance");
            $message = "Set {$user['username']} coins to $newBalance";
        }
    }

    if ($action === 'edit_user') {
        $userId = (int)$_POST['user_id'];
        $fields = [];
        $params = [];

        $u = $db->fetch("SELECT game_uid FROM shop_users WHERE id = ?", [$userId]);

        if (isset($_POST['username']) && trim($_POST['username']) !== '') {
            $newUsername = trim($_POST['username']);
            $fields[] = "username = ?";
            $params[] = $newUsername;
            if ($u && $u['game_uid']) {
                try {
                    $cfg = require __DIR__ . '/../config/game_database.php';
                    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
                    $gameDb = new \PDO($dsn, $cfg['username'], $cfg['password']);
                    $gameDb->prepare("UPDATE users SET username = ? WHERE uid = ?")->execute([$newUsername, $u['game_uid']]);
                } catch (\Exception $e) {}
            }
        }

        if (isset($_POST['password']) && trim($_POST['password']) !== '') {
            $newPass = trim($_POST['password']);
            if ($u && $u['game_uid']) {
                try {
                    $cfg = require __DIR__ . '/../config/game_database.php';
                    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
                    $gameDb = new \PDO($dsn, $cfg['username'], $cfg['password']);
                    $whirlpool = strtoupper(hash('whirlpool', $newPass));
                    $gameDb->prepare("UPDATE users SET password = ? WHERE uid = ?")->execute([$whirlpool, $u['game_uid']]);
                } catch (\Exception $e) {}
            }
            $bcrypt = password_hash($newPass, PASSWORD_DEFAULT);
            $fields[] = "password = ?";
            $params[] = $bcrypt;
        }

        foreach (['email', 'phone', 'ingame_name'] as $field) {
            if (isset($_POST[$field])) {
                $fields[] = "$field = ?";
                $params[] = trim($_POST[$field]) ?: null;
            }
        }

        if (!empty($fields)) {
            $params[] = $userId;
            $db->query("UPDATE shop_users SET " . implode(', ', $fields) . " WHERE id = ?", $params);
            $message = 'User updated — synced to game DB (Whirlpool)';
        }
    }
}

$users = $db->fetchAll("SELECT id, username, email, phone, ingame_name, coins, is_admin, is_active, created_at FROM shop_users ORDER BY is_admin DESC, id");
?>
<div class="admin-header">
    <h2><i class="fas fa-users-cog"></i> User Management</h2>
    <div>
        <a href="../public/logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Add User -->
<div style="margin-bottom: 2rem;">
    <h3 style="margin-bottom: 1rem;"><i class="fas fa-user-plus"></i> Add New User</h3>
    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
        <input type="hidden" name="action" value="add">
        <div class="form-group" style="margin: 0;">
            <label>Username *</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="form-group" style="margin: 0;">
            <label>Password *</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="form-group" style="margin: 0;">
            <label>Email</label>
            <input type="email" name="email" class="form-control">
        </div>
        <div class="form-group" style="margin: 0;">
            <label>Phone</label>
            <input type="tel" name="phone" class="form-control">
        </div>
        <div class="form-group" style="margin: 0;">
            <label>In-Game Name</label>
            <input type="text" name="ingame_name" class="form-control">
        </div>
        <div class="form-group" style="margin: 0; display: flex; align-items: flex-end;">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="is_admin" value="1">
                Admin
            </label>
        </div>
        <div class="form-group" style="margin: 0; display: flex; align-items: flex-end;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add User</button>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Username</th>
                <th>Coins</th>
                <th>Email</th>
                <th>Phone</th>
                <th>In-Game</th>
                <th>Type</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="10" style="text-align: center;">No users found</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                        <td>
                            <span style="color: var(--accent); font-weight: 700;">
                                <i class="fas fa-coins"></i> <?= number_format($user['coins']) ?>
                            </span>
                            <button class="btn btn-sm btn-secondary" onclick="toggleCoinForm(<?= $user['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                        <td><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($user['phone'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($user['ingame_name'] ?: '-') ?></td>
                        <td><?= $user['is_admin'] ? '<span style="color: var(--primary);">Admin</span>' : 'User' ?></td>
                        <td><?= $user['is_active'] ? '<span style="color: var(--success);">Active</span>' : '<span style="color: var(--danger);">Banned</span>' ?></td>
                        <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $user['is_admin'] ? 'btn-secondary' : 'btn-primary' ?>">
                                        <?= $user['is_admin'] ? '<i class="fas fa-user"></i>' : '<i class="fas fa-shield"></i>' ?>
                                    </button>
                                </form>
                                <?php if ($user['id'] !== \Core\Auth::userId()): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars($user['username']) ?>?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Coin Form (hidden) -->
                            <div id="coin-form-<?= $user['id'] ?>" style="display: none; margin-top: 0.5rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.8rem;">
                                <form method="POST" style="display: flex; flex-direction: column; gap: 0.3rem;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <div style="display: flex; gap: 0.3rem;">
                                        <input type="number" name="amount" class="form-control" placeholder="Amount" style="width: 80px; padding: 0.3rem;" min="1">
                                        <input type="text" name="reason" class="form-control" placeholder="Reason" style="flex: 1; padding: 0.3rem;">
                                    </div>
                                    <div style="display: flex; gap: 0.3rem;">
                                        <button type="submit" name="action" value="add_coins" class="btn btn-success btn-sm" style="flex: 1;"><i class="fas fa-plus-circle"></i> Add</button>
                                        <button type="submit" name="action" value="remove_coins" class="btn btn-danger btn-sm" style="flex: 1;"><i class="fas fa-minus-circle"></i> Remove</button>
                                        <button type="submit" name="action" value="set_coins" class="btn btn-secondary btn-sm" style="flex: 1;"><i class="fas fa-equals"></i> Set</button>
                                    </div>
                                    <div style="display: flex; gap: 0.3rem;">
                                        <input type="number" name="coins" class="form-control" placeholder="New balance" style="width: 100%; padding: 0.3rem;" value="<?= $user['coins'] ?>">
                                        <button type="submit" name="action" value="set_coins" class="btn btn-primary btn-sm">Set Balance</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Edit Form (visible by default) -->
                            <div id="edit-form-<?= $user['id'] ?>" style="margin-top: 0.5rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.8rem;">
                                <form method="POST" style="display: flex; flex-direction: column; gap: 0.3rem;">
                                    <input type="hidden" name="action" value="edit_user">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <div style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                                        <input type="text" name="username" class="form-control" placeholder="Username (synced to game)" value="<?= htmlspecialchars($user['username']) ?>" style="flex:1;min-width:120px;padding:0.3rem;">
                                        <input type="password" name="password" class="form-control" placeholder="New password" style="flex:1;min-width:120px;padding:0.3rem;">
                                    </div>
                                    <div style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                                        <input type="text" name="email" class="form-control" placeholder="Email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" style="flex:1;min-width:100px;padding:0.3rem;">
                                        <input type="text" name="phone" class="form-control" placeholder="Phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" style="flex:1;min-width:100px;padding:0.3rem;">
                                        <input type="text" name="ingame_name" class="form-control" placeholder="In-Game Name" value="<?= htmlspecialchars($user['ingame_name'] ?? '') ?>" style="flex:1;min-width:100px;padding:0.3rem;">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save (synced to game)</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
