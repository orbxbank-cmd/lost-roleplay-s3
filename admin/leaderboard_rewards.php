<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/includes/header.php';

$db = \Core\Database::getInstance();
$message = '';
$messageType = 'success';

// Get game DB
$gameDb = null;
$gameConfigPath = __DIR__ . '/../config/game_database.php';
if (file_exists($gameConfigPath)) {
    $cfg = require $gameConfigPath;
    if (!empty($cfg['host'])) {
        try {
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
            $gameDb = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\Exception $e) {}
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute'])) {
    $metric = $_POST['metric'] ?? 'level';
    $top = max(1, min(50, (int)($_POST['top'] ?? 10)));
    $coinsFirst = max(1, (int)($_POST['coins_first'] ?? 100));
    $coinsLast = max(1, (int)($_POST['coins_last'] ?? 10));

    if ($gameDb) {
        try {
            $tables = $gameDb->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            if (in_array('players', $tables)) {
                $orderBy = match($metric) {
                    'hours' => 'pl.hours DESC',
                    'respect' => 'pl.respect DESC',
                    'kills' => 'pl.kills DESC',
                    default => 'pl.level DESC',
                };
                $topPlayers = $gameDb->query("SELECT u.username, u.uid, pl.{$metric} FROM users u INNER JOIN players pl ON u.uid = pl.uid ORDER BY {$orderBy} LIMIT {$top}")->fetchAll();

                $distributed = 0;
                $count = count($topPlayers);
                foreach ($topPlayers as $i => $p) {
                    $position = $i + 1;
                    if ($count > 1) {
                        $reward = $coinsLast + (($coinsFirst - $coinsLast) * ($count - $position) / ($count - 1));
                    } else {
                        $reward = $coinsFirst;
                    }
                    $reward = (int)round($reward);

                    // Check if user exists in local shop DB
                    $local = $db->fetch("SELECT id FROM users WHERE game_uid = ?", [$p['uid']]);
                    if ($local) {
                        $db->query("UPDATE users SET coins = coins + ? WHERE id = ?", [$reward, $local['id']]);
                        $db->insert('coin_transactions', [
                            'user_id' => $local['id'],
                            'amount' => $reward,
                            'type' => 'reward',
                            'description' => "Leaderboard reward: #{$position} in {$metric}",
                        ]);
                        $distributed++;
                    }
                }
                $message = "Distributed rewards to {$distributed} players (top {$count} by {$metric}).";
                \Core\Logger::info('Leaderboard rewards distributed', ['metric' => $metric, 'players' => $distributed]);
            } else {
                $message = 'Players table not found in game DB.';
                $messageType = 'danger';
            }
        } catch (\Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = 'Game DB is offline.';
        $messageType = 'danger';
    }
}

// Show current top players for preview
$preview = [];
if ($gameDb) {
    try {
        $tables = $gameDb->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        if (in_array('players', $tables)) {
            $preview = $gameDb->query("SELECT u.username, pl.level, pl.hours, pl.respect, pl.kills FROM users u INNER JOIN players pl ON u.uid = pl.uid ORDER BY pl.level DESC LIMIT 20")->fetchAll();
        }
    } catch (\Exception $e) {}
}
?>
<div class="admin-header">
    <h2><i class="fas fa-trophy"></i> Leaderboard Rewards</h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem;">
    <div>
        <h3 style="margin-bottom: 1rem;">Distribute Rewards</h3>
        <form method="POST" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem;">
            <div class="form-group">
                <label>Metric</label>
                <select name="metric" class="form-control">
                    <option value="level">Level</option>
                    <option value="hours">Hours</option>
                    <option value="respect">Respect</option>
                    <option value="kills">Kills</option>
                </select>
            </div>
            <div class="form-group">
                <label>Top Players</label>
                <input type="number" name="top" class="form-control" value="10" min="1" max="50">
            </div>
            <div class="form-group">
                <label>Coins (1st Place)</label>
                <input type="number" name="coins_first" class="form-control" value="100" min="1">
            </div>
            <div class="form-group">
                <label>Coins (Last Place)</label>
                <input type="number" name="coins_last" class="form-control" value="10" min="1">
            </div>
            <button type="submit" name="distribute" value="1" class="btn btn-primary" style="width: 100%;" onclick="return confirm('Distribute rewards to top players?')">
                <i class="fas fa-gift"></i> Distribute Rewards
            </button>
        </form>
    </div>

    <div>
        <h3 style="margin-bottom: 1rem;">Current Top 20 (by Level)</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Level</th>
                        <th>Hours</th>
                        <th>Respect</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($preview)): ?>
                        <tr><td colspan="5" style="text-align: center;">Game DB offline or no data</td></tr>
                    <?php else: ?>
                        <?php foreach ($preview as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($p['username']) ?></td>
                                <td><?= $p['level'] ?></td>
                                <td><?= number_format($p['hours'], 1) ?></td>
                                <td><?= number_format($p['respect']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
