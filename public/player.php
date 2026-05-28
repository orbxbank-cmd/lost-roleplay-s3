<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();

$app = require __DIR__ . '/../config/app.php';
$db = \Core\Database::getInstance();

$username = trim($_GET['username'] ?? '');
$player = null;
$stats = null;
$faction = null;

if (empty($username)) {
    header('Location: players.php');
    exit;
}

// Get game DB connection
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

if ($gameDb) {
    try {
            $userCols = $gameDb->query("SHOW COLUMNS FROM users")->fetchAll(\PDO::FETCH_COLUMN, 0);
            $stmt = $gameDb->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $player = $stmt->fetch();

        if ($player) {
            $tables = $gameDb->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            $uidCol = in_array('uid', $userCols) ? 'uid' : (in_array('id', $userCols) ? 'id' : null);
            $uid = $uidCol ? $player[$uidCol] : null;

            // Try players table
            if ($uid && in_array('players', $tables)) {
                try {
                    $pCols = $gameDb->query("SHOW COLUMNS FROM players")->fetchAll(\PDO::FETCH_COLUMN, 0);
                    $uidFk = in_array('uid', $pCols) ? 'uid' : (in_array('user_id', $pCols) ? 'user_id' : (in_array('player_id', $pCols) ? 'player_id' : null));
                    if ($uidFk) {
                        $s = $gameDb->prepare("SELECT * FROM players WHERE $uidFk = ? LIMIT 1");
                        $s->execute([$uid]);
                        $stats = $s->fetch();
                    }
                } catch (\Exception $e2) {}
            }

            // Fallback: find stats in users table
            if (!$stats) {
                $statKeys = ['level', 'admin_level', 'hours', 'playtime', 'respect', 'kills', 'deaths', 'money', 'cash', 'bank', 'score', 'wanted', 'job'];
                $foundStats = [];
                foreach ($statKeys as $k) {
                    if (in_array($k, $userCols)) {
                        $foundStats[$k] = $player[$k] ?? null;
                    }
                }
                if (!empty($foundStats)) $stats = $foundStats;
            }

            // Faction
            $factionCol = in_array('faction_id', $userCols) ? 'faction_id' : (in_array('faction', $userCols) ? 'faction' : null);
            if ($factionCol && !empty($player[$factionCol]) && in_array('factions', $tables)) {
                try {
                    $fCols = $gameDb->query("SHOW COLUMNS FROM factions")->fetchAll(\PDO::FETCH_COLUMN, 0);
                    $fkCol = in_array('id', $fCols) ? 'id' : (in_array('faction_id', $fCols) ? 'faction_id' : null);
                    $nameCol = in_array('name', $fCols) ? 'name' : (in_array('faction_name', $fCols) ? 'faction_name' : null);
                    if ($fkCol && $nameCol) {
                        $f = $gameDb->prepare("SELECT * FROM factions WHERE $fkCol = ? LIMIT 1");
                        $f->execute([$player[$factionCol]]);
                        $factionData = $f->fetch();
                        if ($factionData && isset($factionData[$nameCol])) $faction = $factionData;
                    }
                } catch (\Exception $e2) {}
            }

            // Count properties
            $houseCount = 0; $vehicleCount = 0; $businessCount = 0;
            if ($uid) {
                $propTables = ['houses' => 'owner_uid', 'vehicles' => 'owner_uid', 'businesses' => 'owner_uid'];
                $propResults = ['houses' => &$houseCount, 'vehicles' => &$vehicleCount, 'businesses' => &$businessCount];
                foreach ($propResults as $tbl => &$cnt) {
                    if (in_array($tbl, $tables)) {
                        try {
                            $hCols = $gameDb->query("SHOW COLUMNS FROM $tbl")->fetchAll(\PDO::FETCH_COLUMN, 0);
                            $ownerCol = in_array('owner_uid', $hCols) ? 'owner_uid' : (in_array('user_id', $hCols) ? 'user_id' : (in_array('owner', $hCols) ? 'owner' : null));
                            if ($ownerCol) {
                                $h = $gameDb->prepare("SELECT COUNT(*) as cnt FROM $tbl WHERE $ownerCol = ?");
                                $h->execute([$uid]);
                                $cnt = (int)$h->fetch()['cnt'];
                            }
                        } catch (\Exception $e2) {}
                    }
                }
                unset($cnt);
            }
        }
    } catch (\Exception $e) {}
}

// Fallback to local DB if game DB failed
if (!$player) {
    try {
        $localPlayer = $db->fetch("SELECT username, level, coins, last_activity_hours FROM shop_users WHERE username = ?", [$username]);
        if ($localPlayer) {
            $player = $localPlayer;
            $stats = $localPlayer;
            $houseCount = 0; $vehicleCount = 0; $businessCount = 0;
        }
    } catch (\Exception $e2) {}
}

$isOwnProfile = \Core\Auth::isLoggedIn() && \Core\Auth::username() === $username;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
    <?php if (!$player): ?>
        <div class="empty-state">
            <div class="icon">🔍</div>
            <h3>Player not found</h3>
            <p><?= htmlspecialchars($username) ?> was not found in the game database.</p>
            <a href="players.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Players</a>
        </div>
    <?php else: ?>
        <a href="players.php" style="color: var(--text-muted); margin-bottom: 1rem; display: inline-block;"><i class="fas fa-arrow-left"></i> Back to Players</a>

        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                <div style="width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: white;">
                    <?= strtoupper(substr($player['username'], 0, 1)) ?>
                </div>
                <div style="flex: 1;">
                    <h1 style="margin: 0;"><?= htmlspecialchars($player['username']) ?></h1>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                        <?php if ($faction): ?>
                            <span style="background: rgba(233,30,99,0.15); padding: 0.25rem 1rem; border-radius: 50px; font-size: 0.85rem;">
                                <i class="fas fa-building"></i> <?= htmlspecialchars($faction['name'] ?? $faction['faction_name'] ?? 'Faction') ?>
                            </span>
                        <?php endif; ?>
                        <?php if (isset($player['admin_level']) && $player['admin_level'] > 0): ?>
                            <span style="background: rgba(255,214,0,0.15); color: var(--accent); padding: 0.25rem 1rem; border-radius: 50px; font-size: 0.85rem;">
                                <i class="fas fa-shield-alt"></i> Admin Lv.<?= $player['admin_level'] ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($isOwnProfile): ?>
                            <span style="background: rgba(76,175,80,0.15); color: var(--success); padding: 0.25rem 1rem; border-radius: 50px; font-size: 0.85rem;">
                                <i class="fas fa-check"></i> You
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid - shows ALL available columns from game DB -->
        <?php
        $excludeCols = ['username', 'password', 'email', 'ip', 'last_ip', 'register_ip', 'uid', 'id', 'user_id',
                        'registered_date', 'register_date', 'created_at', 'last_login', 'last_seen',
                        'referral_code', 'referred_by', 'avatar', 'discord', 'phone', 'whatsapp',
                        'serial', 'hwid', 'unique_id', 'social', 'auth_token', 'remember_token',
                        'faction_id', 'faction', 'notes', 'memo', 'settings', 'data', 'props'];
        $displayedStats = false;
        $dataSource = $stats ?? $player ?? [];
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <?php foreach ($dataSource as $col => $val):
                if (in_array($col, $excludeCols)) continue;
                if ($val === '' || $val === null || $val === false) continue;
                $displayedStats = true;
                $label = ucwords(str_replace(['_', '-'], ' ', $col));
                $color = match(true) {
                    stripos($col, 'level') !== false || stripos($col, 'score') !== false || stripos($col, 'rank') !== false => 'var(--accent)',
                    stripos($col, 'hour') !== false || stripos($col, 'time') !== false || stripos($col, 'play') !== false => 'var(--primary)',
                    stripos($col, 'respect') !== false || stripos($col, 'reput') !== false => 'var(--success)',
                    stripos($col, 'money') !== false || stripos($col, 'cash') !== false || stripos($col, 'bank') !== false || stripos($col, 'wallet') !== false => 'var(--success)',
                    stripos($col, 'kill') !== false || stripos($col, 'wanted') !== false || stripos($col, 'warn') !== false || stripos($col, 'arrest') !== false => 'var(--danger)',
                    stripos($col, 'death') !== false || stripos($col, 'jail') !== false => 'var(--text-muted)',
                    default => 'var(--primary)',
                };
                if (is_numeric($val)):
                    if (is_float($val + 0) || stripos($col, 'hour') !== false || stripos($col, 'time') !== false):
                        $display = number_format((float)$val, 1);
                    else:
                        $display = number_format((int)$val);
                    endif;
                    if (stripos($col, 'money') !== false || stripos($col, 'cash') !== false || stripos($col, 'bank') !== false || stripos($col, 'wallet') !== false):
                        $display = '$' . $display;
                    endif;
                else:
                    $display = htmlspecialchars($val);
                endif;
            ?>
                <div class="stat-card" style="text-align: center;">
                    <div class="stat-value" style="color: <?= $color ?>;"><?= $display ?></div>
                    <div class="stat-label"><?= $label ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$displayedStats): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">
                    All stat values are empty or zero.
                </div>
            <?php endif; ?>
        </div>
        <?php $stats = $dataSource; // keep stats for later use if needed ?>


        <!-- Properties -->
        <?php
        $propTypes = [
            ['icon' => '🏠', 'label' => 'Houses', 'count' => $houseCount ?? null],
            ['icon' => '🚗', 'label' => 'Vehicles', 'count' => $vehicleCount ?? null],
            ['icon' => '💼', 'label' => 'Businesses', 'count' => $businessCount ?? null],
        ];
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <?php foreach ($propTypes as $pt): ?>
                <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 0.3rem;"><?= $pt['icon'] ?></div>
                    <div style="font-size: 1.5rem; font-weight: 800;"><?= $pt['count'] !== null ? $pt['count'] : '?' ?></div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;"><?= $pt['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
