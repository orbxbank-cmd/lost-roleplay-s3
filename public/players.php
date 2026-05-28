<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();

$app = require __DIR__ . '/../config/app.php';
$db = \Core\Database::getInstance();

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
        } catch (\Exception $e) {
            \Core\Logger::info('Game DB unavailable: ' . $e->getMessage());
        }
    }
}

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'level';
$playerData = null;
$topPlayers = [];
$columns = [];

if ($gameDb) {
    try {
        // Get columns from users table
        $cols = $gameDb->query("SHOW COLUMNS FROM users")->fetchAll(\PDO::FETCH_COLUMN, 0);
        $columns['users'] = $cols;
    } catch (\Exception $e) {}

    // Fallback to local users if game DB has no data
    if (empty($tables) || empty($topPlayers)) {
        try {
            $localUsers = $db->fetchAll("SELECT username, level, coins, last_activity_hours FROM shop_users ORDER BY COALESCE(last_activity_hours, 0) DESC LIMIT 50");
            if (!empty($localUsers)) {
                $topPlayers = $localUsers;
            }
        } catch (\Exception $e2) {}
    }

    // Search local DB if game DB failed and search is requested
    if (!empty($search) && !$playerData) {
        try {
            $localPlayer = $db->fetch("SELECT username, level, coins, last_activity_hours FROM shop_users WHERE username = ?", [$search]);
            if ($localPlayer) {
                $playerData = $localPlayer;
            }
        } catch (\Exception $e2) {}
    }

    // Try to find player-specific tables
    $tables = [];
    try {
        $result = $gameDb->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $tables = $result;
    } catch (\Exception $e) {}

    // Search for specific player
    if (!empty($search)) {
        try {
            // Get user info from game DB (try different column names)
            $userStmt = $gameDb->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $userStmt->execute([$search]);
            $playerData = $userStmt->fetch();

            if ($playerData) {
                $uidCol = in_array('uid', $columns['users'] ?? []) ? 'uid' : (in_array('id', $columns['users'] ?? []) ? 'id' : null);
                $uid = $uidCol ? $playerData[$uidCol] : null;

                // Try additional stats from common tables
                if ($uid && in_array('players', $tables)) {
                    try {
                        $playerCols = $gameDb->query("SHOW COLUMNS FROM players")->fetchAll(\PDO::FETCH_COLUMN, 0);
                        $uidFk = in_array('uid', $playerCols) ? 'uid' : (in_array('user_id', $playerCols) ? 'user_id' : (in_array('player_id', $playerCols) ? 'player_id' : null));
                        if ($uidFk) {
                            $stmt = $gameDb->prepare("SELECT * FROM players WHERE $uidFk = ? LIMIT 1");
                            $stmt->execute([$uid]);
                            $stats = $stmt->fetch();
                            if ($stats) $playerData = array_merge($playerData, $stats);
                        }
                    } catch (\Exception $e2) {}
                }

                // Show user columns as stats fallback
                if (!$stats && $columns['users']) {
                    $statKeys = ['level', 'admin_level', 'hours', 'playtime', 'respect', 'kills', 'deaths', 'money', 'cash', 'bank', 'score', 'wanted', 'faction_id', 'faction', 'faction_name', 'phone', 'job', 'job_id'];
                    $foundStats = [];
                    foreach ($statKeys as $k) {
                        if (in_array($k, $columns['users'])) {
                            $foundStats[$k] = $playerData[$k] ?? null;
                        }
                    }
                    if (!empty($foundStats)) {
                        $stats = $foundStats;
                        $playerData = array_merge($playerData, $stats);
                    }
                }

                // Try faction info
                $factionCol = in_array('faction_id', $columns['users'] ?? []) ? 'faction_id' : (in_array('faction', $columns['users'] ?? []) ? 'faction' : null);
                if ($factionCol && !empty($playerData[$factionCol]) && in_array('factions', $tables)) {
                    try {
                        $factionCols = $gameDb->query("SHOW COLUMNS FROM factions")->fetchAll(\PDO::FETCH_COLUMN, 0);
                        $fkCol = in_array('id', $factionCols) ? 'id' : (in_array('faction_id', $factionCols) ? 'faction_id' : null);
                        $nameCol = in_array('name', $factionCols) ? 'name' : (in_array('faction_name', $factionCols) ? 'faction_name' : null);
                        if ($fkCol && $nameCol) {
                            $stmt = $gameDb->prepare("SELECT * FROM factions WHERE $fkCol = ? LIMIT 1");
                            $stmt->execute([$playerData[$factionCol]]);
                            $faction = $stmt->fetch();
                            if ($faction && isset($faction[$nameCol])) {
                                $playerData['faction_name'] = $faction[$nameCol];
                            }
                        }
                    } catch (\Exception $e2) {}
                }
            }
        } catch (\Exception $e) {}
    }

    // Top players
    try {
        $uidCol = in_array('uid', $columns['users'] ?? []) ? 'u.uid' : (in_array('id', $columns['users'] ?? []) ? 'u.id' : null);
        if (in_array('players', $tables) && $uidCol) {
            try {
                $playerCols = $gameDb->query("SHOW COLUMNS FROM players")->fetchAll(\PDO::FETCH_COLUMN, 0);
                $uidFk = in_array('uid', $playerCols) ? 'pl.uid' : (in_array('user_id', $playerCols) ? 'pl.user_id' : null);
                $levelCol = in_array('level', $playerCols) ? 'pl.level' : (in_array('admin_level', $playerCols) ? 'pl.admin_level' : null);
                $hoursCol = in_array('hours', $playerCols) ? 'pl.hours' : (in_array('playtime', $playerCols) ? 'pl.playtime' : null);
                $respectCol = in_array('respect', $playerCols) ? 'pl.respect' : null;
                $killsCol = in_array('kills', $playerCols) ? 'pl.kills' : null;
                $moneyCol = in_array('money', $playerCols) ? 'pl.money' : (in_array('cash', $playerCols) ? 'pl.cash' : null);

                $selectCols = array_filter([$levelCol, $hoursCol, $respectCol, $killsCol, $moneyCol]);
                if (!empty($selectCols) && $uidFk) {
                    $orderBy = match($sort) {
                        'hours' => $hoursCol ? "$hoursCol DESC" : null,
                        'respect' => $respectCol ? "$respectCol DESC" : null,
                        'level' => $levelCol ? "$levelCol DESC" : null,
                        'kills' => $killsCol ? "$killsCol DESC" : null,
                        'money' => $moneyCol ? "$moneyCol DESC" : null,
                        default => $levelCol ? "$levelCol DESC" : null,
                    };
                    if ($orderBy) {
                        $plCols = implode(', ', $selectCols);
                        $topQ = $gameDb->query("SELECT u.username, $uidCol as uid, $plCols FROM users u INNER JOIN players pl ON $uidFk = $uidCol ORDER BY $orderBy LIMIT 50");
                        $topPlayers = $topQ->fetchAll();
                    }
                }
            } catch (\Exception $e2) {}
        }

        // Fallback: show users from game DB with whatever columns we have
        if (empty($topPlayers)) {
            $availableCols = $columns['users'] ?? [];
            $orderCol = in_array('admin_level', $availableCols) ? 'admin_level DESC' : (in_array('level', $availableCols) ? 'level DESC' : 'uid ASC');
            $topQ = $gameDb->query("SELECT * FROM users ORDER BY {$orderCol} LIMIT 50");
            $topPlayers = $topQ->fetchAll();
        }
    } catch (\Exception $e) {}
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
    <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-trophy"></i> Players</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Browse players and view in-game stats</p>

    <!-- Search -->
    <form method="GET" style="display: flex; gap: 0.5rem; margin-bottom: 2rem; max-width: 500px;">
        <input type="text" name="search" class="form-control" placeholder="Search by username..." value="<?= htmlspecialchars($search) ?>" style="flex: 1;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
    </form>

    <?php if (!empty($search) && $playerData): ?>
    <!-- Player Profile -->
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; color: white;">
                <?= strtoupper(substr($playerData['username'], 0, 1)) ?>
            </div>
            <div style="flex: 1;">
                <h2 style="margin: 0;"><?= htmlspecialchars($playerData['username']) ?></h2>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.5rem;">
                    <?php if (isset($playerData['faction_name'])): ?>
                        <span style="background: rgba(233,30,99,0.15); padding: 0.2rem 0.8rem; border-radius: 50px; font-size: 0.85rem;">
                            <i class="fas fa-building"></i> <?= htmlspecialchars($playerData['faction_name']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (isset($playerData['admin_level']) && $playerData['admin_level'] > 0): ?>
                        <span style="background: rgba(255,214,0,0.15); color: var(--accent); padding: 0.2rem 0.8rem; border-radius: 50px; font-size: 0.85rem;">
                            <i class="fas fa-shield-alt"></i> Admin Lv.<?= $playerData['admin_level'] ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // Show ALL available columns from the game DB result, excluding meta columns
        $excludeCols = ['username', 'password', 'email', 'ip', 'last_ip', 'register_ip', 'uid', 'id', 'user_id',
                        'registered_date', 'register_date', 'created_at', 'last_login', 'last_seen',
                        'referral_code', 'referred_by', 'avatar', 'discord', 'phone', 'whatsapp',
                        'serial', 'hwid', 'unique_id', 'social', 'auth_token', 'remember_token',
                        'faction_id', 'faction', 'notes', 'memo', 'settings', 'data', 'props'];
        $displayedStats = false;
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 2rem;">
            <?php foreach ($playerData as $col => $val):
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
                <div style="text-align: center; padding: 1rem; background: var(--bg-dark); border-radius: 8px;">
                    <div style="font-size: 1.8rem; font-weight: 800; color: <?= $color ?>;"><?= $display ?></div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= $label ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$displayedStats): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">
                    All stat values are empty or zero.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (!empty($search) && !$playerData): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Player "<?= htmlspecialchars($search) ?>" not found in game database.</div>
    <?php endif; ?>

    <?php if (!$gameDb): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Game DB is offline. Player stats unavailable.</div>
    <?php endif; ?>
    <?php if ($playerData && $app['debug']): ?>
        <details style="margin-bottom: 1rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem;">
            <summary style="cursor: pointer; font-weight: 600; color: var(--text-muted);">🔍 Debug: Raw player data keys</summary>
            <pre style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars(print_r(array_keys($playerData), true)) ?></pre>
        </details>
    <?php endif; ?>

    <!-- Top Players -->
    <?php
    // Dynamically detect available numeric columns for sorting
    $excludeSortCols = ['username', 'password', 'email', 'ip', 'uid', 'id', 'user_id', 'registered_date', 'register_date', 'created_at', 'last_login'];
    $availableSortCols = [];
    if (!empty($topPlayers)) {
        foreach ($topPlayers[0] as $col => $val) {
            if (in_array($col, $excludeSortCols)) continue;
            if (is_numeric($val) || $val === null) $availableSortCols[] = $col;
        }
    }
    $sortLabels = ['level' => 'Level', 'admin_level' => 'Admin', 'hours' => 'Hours', 'playtime' => 'Playtime', 'respect' => 'Respect', 'kills' => 'Kills', 'deaths' => 'Deaths', 'money' => 'Money', 'cash' => 'Cash', 'bank' => 'Bank', 'score' => 'Score'];
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="margin: 0;"><i class="fas fa-list"></i> Top Players</h2>
        <?php if (!empty($availableSortCols)): ?>
            <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                <?php foreach ($availableSortCols as $sc):
                    if (in_array($sc, ['username','password','email','ip'])) continue; ?>
                    <a href="?sort=<?= $sc ?>" class="btn btn-sm <?= $sort === $sc ? 'btn-primary' : 'btn-secondary' ?>"><?= $sortLabels[$sc] ?? ucwords(str_replace('_', ' ', $sc)) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <?php foreach ($availableSortCols as $sc): ?>
                        <th><?= $sortLabels[$sc] ?? ucwords(str_replace('_', ' ', $sc)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topPlayers)): ?>
                    <tr><td colspan="10" style="text-align: center;">No player data available in game database.</td></tr>
                <?php else: ?>
                    <?php foreach ($topPlayers as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><a href="player.php?username=<?= urlencode($p['username']) ?>" style="font-weight: 600;"><?= htmlspecialchars($p['username']) ?></a></td>
                            <?php foreach ($availableSortCols as $sc):
                                if (isset($p[$sc]) && $p[$sc] !== ''): ?>
                                    <td><?php
                                        if (is_numeric($p[$sc])):
                                            if (stripos($sc, 'hour') !== false || stripos($sc, 'time') !== false):
                                                echo number_format((float)$p[$sc], 1);
                                            elseif (stripos($sc, 'money') !== false || stripos($sc, 'cash') !== false || stripos($sc, 'bank') !== false):
                                                echo '$' . number_format((float)$p[$sc]);
                                            else:
                                                echo number_format((int)$p[$sc]);
                                            endif;
                                        else:
                                            echo htmlspecialchars($p[$sc]);
                                        endif;
                                    ?></td>
                            <?php endif; endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
