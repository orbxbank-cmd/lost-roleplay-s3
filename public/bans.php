<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();

$app = require __DIR__ . '/../config/app.php';
$db = \Core\Database::getInstance();

$error = '';
$success = '';
$bans = [];

// Try to get bans from game DB
$gameDbConfigPath = __DIR__ . '/../config/game_database.php';
if (file_exists($gameDbConfigPath)) {
    $gameConfig = require $gameDbConfigPath;
    if (!empty($gameConfig['host'])) {
        try {
            $dsn = "mysql:host={$gameConfig['host']};port={$gameConfig['port']};dbname={$gameConfig['dbname']};charset={$gameConfig['charset']}";
            $gamePdo = new \PDO($dsn, $gameConfig['username'], $gameConfig['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 3,
            ]);
            $stmt = $gamePdo->query("SELECT b.*, u.username FROM bans b LEFT JOIN shop_users u ON b.user_id = u.uid ORDER BY b.ban_date DESC LIMIT 100");
            $bans = $stmt->fetchAll();
        } catch (\Exception $e) {
            \Core\Logger::info('Game DB bans query failed: ' . $e->getMessage());
        }
    }
}

// Handle unban request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $ban_reason = trim($_POST['ban_reason'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($username) || empty($reason)) {
        $error = 'Please fill in all required fields.';
    } else {
        $userId = null;
        if (\Core\Auth::isLoggedIn()) {
            $user = \Core\Auth::user();
            $userId = $user['id'];
        }

        // Check if already has a pending request
        $existing = $db->fetch("SELECT id FROM shop_unban_requests WHERE username = ? AND status = 'pending'", [$username]);
        if ($existing) {
            $error = 'You already have a pending unban request for this username.';
        } else {
            $db->query(
                "INSERT INTO shop_unban_requests (username, user_id, ban_reason, reason) VALUES (?, ?, ?, ?)",
                [$username, $userId, $ban_reason, $reason]
            );
            $success = 'Unban request submitted successfully! An admin will review it shortly.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
    <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-gavel"></i> Bans & Unban Requests</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Check if you're banned and submit an unban request</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <!-- Bans List -->
        <div>
            <h2 style="margin-bottom: 1rem;"><i class="fas fa-ban"></i> Banned Players</h2>
            <?php if (empty($bans)): ?>
                <div class="empty-state">
                    <div class="icon">🔨</div>
                    <h3>No bans to display</h3>
                    <p>Game server database is offline or no bans exist.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Admin</th>
                                <th>Reason</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bans as $ban): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ban['username'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($ban['admin_name'] ?? 'Server') ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($ban['reason'] ?? 'N/A') ?></td>
                                    <td><?= date('Y-m-d', strtotime($ban['ban_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Unban Request Form -->
        <div>
            <h2 style="margin-bottom: 1rem;"><i class="fas fa-envelope-open-text"></i> Request Unban</h2>
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Your In-Game Username *</label>
                        <input type="text" name="username" class="form-control" required placeholder="Enter your server username" value="<?= htmlspecialchars($_POST['username'] ?? (\Core\Auth::isLoggedIn() ? \Core\Auth::username() : '')) ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-info-circle"></i> Ban Reason (optional)</label>
                        <input type="text" name="ban_reason" class="form-control" placeholder="Why were you banned? (if known)" value="<?= htmlspecialchars($_POST['ban_reason'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-pen"></i> Your Defense / Reason for Unban *</label>
                        <textarea name="reason" class="form-control" required placeholder="Explain why you should be unbanned..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;"><i class="fas fa-paper-plane"></i> Submit Unban Request</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
