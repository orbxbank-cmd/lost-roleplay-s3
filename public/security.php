<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();
\Core\Auth::requireAuth();

$db = \Core\Database::getInstance();
$config = require __DIR__ . '/../config/app.php';
$user = \Core\Auth::user();
$error = '';
$success = '';

// Fetch game info
$gameUser = null;
$gameConfigPath = __DIR__ . '/../config/game_database.php';
if (file_exists($gameConfigPath)) {
    $cfg = require $gameConfigPath;
    if (!empty($cfg['host'])) {
        try {
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
            $gameDb = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $stmt = $gameDb->prepare("SELECT uid, username, ip, lastlogin FROM users WHERE uid = ? LIMIT 1");
            $stmt->execute([$user['game_uid']]);
            $gameUser = $stmt->fetch();
        } catch (\Exception $e) {
            $error = 'Game DB connection failed';
        }
    }
}

// Change game password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPass = trim($_POST['current_password'] ?? '');
    $newPass = trim($_POST['new_password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $error = 'Please fill all fields';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New passwords do not match';
    } elseif (strlen($newPass) < 4 || strlen($newPass) > 20) {
        $error = 'New password must be 4-20 characters';
    } else {
        try {
            $cfg = require $gameConfigPath;
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
            $gameDb = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $stmt = $gameDb->prepare("SELECT uid, username, password FROM users WHERE uid = ? LIMIT 1");
            $stmt->execute([$user['game_uid']]);
            $player = $stmt->fetch();

            if (!$player) {
                $error = 'Game account not found';
            } elseif (strtoupper(hash('whirlpool', $currentPass)) !== strtoupper(trim($player['password']))) {
                $error = 'Current password is incorrect';
            } else {
                $newHash = strtoupper(hash('whirlpool', $newPass));
                $update = $gameDb->prepare("UPDATE users SET password = ? WHERE uid = ?");
                $update->execute([$newHash, $player['uid']]);

                // Log the change
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $db->query("INSERT INTO shop_login_logs (user_id, ip, user_agent, action, success) VALUES (?, ?, ?, 'password_change', 1)",
                    [$user['id'], $ip, $ua]);

                $success = 'Password changed successfully! Use your new password to login to the server.';
                // Refresh game user info
                $gameUser['password'] = $newHash;
            }
        } catch (\Exception $e) {
            $error = 'Error changing password: ' . $e->getMessage();
        }
    }
}

// Submit recovery request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recovery_request'])) {
    $contact = trim($_POST['contact_info'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $ingameName = trim($_POST['ingame_name'] ?? $user['username']);

    if (empty($reason)) {
        $error = 'Please describe your issue';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db->query("INSERT INTO shop_recovery_requests (user_id, ingame_name, contact_info, reason, ip) VALUES (?, ?, ?, ?, ?)",
            [$user['id'], $ingameName, $contact, $reason, $ip]);
        $success = 'Recovery request submitted! An admin will review it and contact you.';
    }
}

// Fetch login logs
$loginLogs = $db->fetchAll(
    "SELECT * FROM shop_login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
    [$user['id']]
);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width:800px;margin-top:2rem;margin-bottom:2rem;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.5rem;">
        <i class="fas fa-shield-alt" style="font-size:2rem;color:var(--info);"></i>
        <div>
            <h1 style="font-family:var(--header-font);text-transform:uppercase;letter-spacing:1px;font-size:1.5rem;">Account Security</h1>
            <p style="color:var(--text-secondary);font-size:0.85rem;">Manage your account security and recover your account</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Warning Alert -->
    <div class="alert alert-warning" style="background:rgba(255,193,7,0.08);border-color:rgba(255,193,7,0.2);margin-bottom:1.5rem;">
        <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;"></i>
        <div>
            <strong style="font-size:0.9rem;">&#x26A0; Beware of Money Loaders &amp; Hack Tools!</strong>
            <p style="font-size:0.8rem;margin-top:4px;">There is NO legitimate money loader. Any file claiming to give free money contains a <strong>keylogger</strong> that steals your password. Only download from trusted sources. Change your password immediately if you ran any suspicious file.</p>
        </div>
    </div>

    <!-- Linked Account Info -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.2rem;">
        <h3 style="font-family:var(--header-font);font-size:1rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:1rem;"><i class="fas fa-gamepad" style="color:var(--info);"></i> Linked Game Account</h3>
        <?php if ($gameUser): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                <div>
                    <span style="color:var(--text-muted);font-size:0.75rem;">Username</span>
                    <div style="font-weight:600;"><?= htmlspecialchars($gameUser['username']) ?></div>
                </div>
                <div>
                    <span style="color:var(--text-muted);font-size:0.75rem;">UID</span>
                    <div style="font-weight:600;">#<?= $gameUser['uid'] ?></div>
                </div>
                <div>
                    <span style="color:var(--text-muted);font-size:0.75rem;">Last Login</span>
                    <div style="font-weight:600;"><?= $gameUser['lastlogin'] ? htmlspecialchars($gameUser['lastlogin']) : 'Never' ?></div>
                </div>
                <div>
                    <span style="color:var(--text-muted);font-size:0.75rem;">Last IP</span>
                    <div style="font-weight:600;font-family:monospace;"><?= htmlspecialchars($gameUser['ip'] ?? 'Unknown') ?></div>
                </div>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);font-size:0.85rem;">No game account linked.</p>
        <?php endif; ?>
    </div>

    <!-- Change Game Password -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.2rem;">
        <h3 style="font-family:var(--header-font);font-size:1rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:1rem;"><i class="fas fa-key" style="color:var(--accent);"></i> Change Game Password</h3>
        <p style="color:var(--text-secondary);font-size:0.8rem;margin-bottom:1rem;">Change your in-game password directly. Use a strong password you don't use elsewhere.</p>
        <form method="POST">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" required placeholder="Enter current game password">
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required placeholder="4-20 characters" minlength="4" maxlength="20">
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
            </div>
            <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-save"></i> Change Password</button>
        </form>
    </div>

    <!-- Login History -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.2rem;">
        <h3 style="font-family:var(--header-font);font-size:1rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:1rem;"><i class="fas fa-history" style="color:var(--info);"></i> Login History</h3>
        <?php if (count($loginLogs) > 0): ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>IP</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loginLogs as $log): ?>
                            <tr>
                                <td style="font-size:0.78rem;"><?= htmlspecialchars($log['created_at']) ?></td>
                                <td style="font-family:monospace;font-size:0.78rem;"><?= htmlspecialchars($log['ip']) ?></td>
                                <td><span class="status <?= $log['action'] === 'login' ? 'status-processing' : 'status-confirmed' ?>"><?= htmlspecialchars($log['action']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:var(--text-secondary);font-size:0.85rem;">No recent login activity.</p>
        <?php endif; ?>
    </div>

    <!-- Recovery Request -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;">
        <h3 style="font-family:var(--header-font);font-size:1rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:1rem;"><i class="fas fa-life-ring" style="color:var(--success);"></i> Request Account Recovery</h3>
        <p style="color:var(--text-secondary);font-size:0.8rem;margin-bottom:1rem;">If your account was hacked and you can't login, submit a recovery request. An admin will review and help you recover your account.</p>
        <form method="POST">
            <div class="form-group">
                <label>In-Game Name</label>
                <input type="text" name="ingame_name" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <div class="form-group">
                <label>Contact Info (Discord / WhatsApp / Email)</label>
                <input type="text" name="contact_info" class="form-control" placeholder="How can we contact you?">
            </div>
            <div class="form-group">
                <label>Describe the Issue</label>
                <textarea name="reason" class="form-control" required placeholder="Explain what happened... Did you run a money loader? When did you lose access?" rows="4"></textarea>
            </div>
            <button type="submit" name="recovery_request" class="btn btn-success"><i class="fas fa-paper-plane"></i> Submit Request</button>
        </form>
    </div>

    <!-- Security Tips -->
    <div style="margin-top:1.5rem;background:rgba(13,110,253,0.04);border:1px solid rgba(13,110,253,0.12);border-radius:var(--radius);padding:1.2rem;">
        <h4 style="font-family:var(--header-font);font-size:0.9rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.6rem;"><i class="fas fa-lightbulb" style="color:var(--accent);"></i> Security Tips</h4>
        <ul style="font-size:0.82rem;color:var(--text-secondary);line-height:1.8;padding-right:1.2rem;">
            <li>Never download "money loaders" or "hack tools" — they are all keyloggers</li>
            <li>Change your password regularly</li>
            <li>Use a unique password for your game account (different from email/social media)</li>
            <li>Logout from the website when using public/shared computers</li>
            <li>If you suspect your account is compromised, change your password immediately</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
