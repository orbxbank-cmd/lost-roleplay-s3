<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();

$db = \Core\Database::getInstance();
$loggedIn = \Core\Auth::isLoggedIn();
$user = $loggedIn ? \Core\Auth::user() : null;

function updateDaily($db, $userId, $field, $inc = 1) {
    $today = date('Y-m-d');
    $db->query("INSERT INTO shop_shop_user_daily (user_id, date, $field) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $field = $field + ?",
        [$userId, $today, $inc, $inc]);
}

function checkMissions($db, $userId) {
    $today = date('Y-m-d');
    $missions = $db->fetchAll("SELECT * FROM shop_daily_missions WHERE active = 1");
    $daily = $db->fetch("SELECT * FROM shop_user_daily WHERE user_id = ? AND date = ?", [$userId, $today]);
    if (!$daily) {
        $db->query("INSERT INTO shop_user_daily (user_id, date) VALUES (?, ?)", [$userId, $today]);
        $daily = $db->fetch("SELECT * FROM shop_user_daily WHERE user_id = ? AND date = ?", [$userId, $today]);
    }
    $results = [];
    foreach ($missions as $m) {
        $progress = (int)($daily[$m['type']] ?? 0);
        $done = $progress >= $m['target'];
        try {
            $claimed = $db->fetch("SELECT id FROM shop_user_daily WHERE user_id = ? AND date = ? AND {$m['type']}_claimed = 1", [$userId, $today]);
            $isClaimed = (bool)$claimed;
        } catch (\Exception $e) {
            $isClaimed = false;
        }
        $results[] = [
            'id' => $m['id'],
            'title' => $m['title'],
            'desc' => $m['description'],
            'progress' => $progress,
            'target' => (int)$m['target'],
            'reward' => (int)$m['reward'],
            'done' => $done,
            'claimed' => $isClaimed,
        ];
    }
    return $results;
}

function autoClaimMissions($db, $userId) {
    $today = date('Y-m-d');
    // Check if shop_user_daily has claimed columns, add them if not
    try {
        $missions = $db->fetchAll("SELECT * FROM shop_daily_missions WHERE active = 1");
        foreach ($missions as $m) {
            $type = $m['type'];
            $needsClaim = $type . '_claimed';
            // Check if the column exists
            $cols = $db->fetchAll("SHOW COLUMNS FROM shop_user_daily LIKE ?", [$needsClaim]);
            if (empty($cols)) {
                $db->query("ALTER TABLE shop_user_daily ADD COLUMN $needsClaim TINYINT DEFAULT 0");
            }
            $daily = $db->fetch("SELECT $type, $needsClaim FROM shop_user_daily WHERE user_id = ? AND date = ?", [$userId, $today]);
            if ($daily && (int)$daily[$type] >= (int)$m['target'] && !(int)$daily[$needsClaim]) {
                $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$m['reward'], $userId]);
                $db->insert('coin_transactions', ['user_id' => $userId, 'amount' => $m['reward'], 'type' => 'bonus', 'description' => 'Daily Mission: ' . $m['title']]);
                $db->query("UPDATE shop_user_daily SET $needsClaim = 1 WHERE user_id = ? AND date = ?", [$userId, $today]);
            }
        }
    } catch (\Exception $e) {}
}

$segments = [
    ['label' => 'Nothing', 'coins' => 0, 'color' => '#636e72'],
    ['label' => '20', 'coins' => 20, 'color' => '#e17055'],
    ['label' => '30', 'coins' => 30, 'color' => '#fdcb6e'],
    ['label' => '50', 'coins' => 50, 'color' => '#00b894'],
    ['label' => '75', 'coins' => 75, 'color' => '#0984e3'],
    ['label' => '100', 'coins' => 100, 'color' => '#6c5ce7'],
    ['label' => '150', 'coins' => 150, 'color' => '#e84393'],
    ['label' => '300', 'coins' => 300, 'color' => '#ffd700'],
];

$mbRarities = [
    ['name' => 'Common', 'color' => '#636e72', 'min' => 10, 'max' => 25, 'weight' => 45],
    ['name' => 'Uncommon', 'color' => '#00b894', 'min' => 30, 'max' => 50, 'weight' => 25],
    ['name' => 'Rare', 'color' => '#0984e3', 'min' => 60, 'max' => 100, 'weight' => 17],
    ['name' => 'Epic', 'color' => '#6c5ce7', 'min' => 110, 'max' => 200, 'weight' => 10],
    ['name' => 'Legendary', 'color' => '#ffd700', 'min' => 300, 'max' => 500, 'weight' => 3],
];

// --- Lucky Wheel ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spin']) && $user) {
    $error = '';
    if ($user['coins'] < 25) { $error = 'Need 25 coins'; }
    if (!$error) {
        $db->query("UPDATE shop_users SET coins = coins - 25 WHERE id = ?", [$user['id']]);
        $db->insert('coin_transactions', ['user_id' => $user['id'], 'amount' => -25, 'type' => 'spend', 'description' => 'Lucky Wheel spin']);
        $weights = [80, 60, 24, 16, 10, 6, 3, 1];
        $tw = array_sum($weights); $r = mt_rand(1, $tw); $c = 0; $idx = 0;
        foreach ($weights as $i => $w) { $c += $w; if ($r <= $c) { $idx = $i; break; } }
        $reward = $segments[$idx];
        if ($reward['coins'] > 0) {
            $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$reward['coins'], $user['id']]);
            $db->insert('coin_transactions', ['user_id' => $user['id'], 'amount' => $reward['coins'], 'type' => 'bonus', 'description' => 'Lucky Wheel win']);
        }
        \Core\Logger::info('Wheel', ['user_id' => $user['id'], 'result' => $reward['coins']]);
        $user['coins'] = $user['coins'] - 25 + $reward['coins'];
        updateDaily($db, $user['id'], 'wheel_spins');
        if ($reward['coins'] > 0) updateDaily($db, $user['id'], 'coins_won', $reward['coins']);
        autoClaimMissions($db, $user['id']);
        if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => true, 'game' => 'wheel', 'index' => $idx, 'coins' => $reward['coins'], 'label' => $reward['label'], 'balance' => $user['coins'], 'missions' => checkMissions($db, $user['id'])]); exit; }
    }
    if ($error && isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $error]); exit; }
}

// --- Double or Nothing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dn']) && $user) {
    $bet = (int)($_POST['bet'] ?? 0); $pick = $_POST['pick'] ?? ''; $error = '';
    if ($bet < 10) { $error = 'Min 10 coins'; } elseif ($bet > $user['coins']) { $error = 'Not enough coins'; } elseif (!in_array($pick, ['red','black'])) { $error = 'Pick Red or Black'; }
    if (!$error) {
        $result = mt_rand(0,1) ? 'red' : 'black'; $won = $pick === $result; $payout = $won ? $bet * 2 : 0;
        $db->query("UPDATE shop_users SET coins = coins - ? WHERE id = ?", [$bet, $user['id']]);
        $db->insert('coin_transactions', ['user_id' => $user['id'], 'amount' => -$bet, 'type' => 'spend', 'description' => 'DN bet']);
        if ($payout > 0) {
            $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$payout, $user['id']]);
            $db->insert('coin_transactions', ['user_id' => $user['id'], 'amount' => $payout, 'type' => 'bonus', 'description' => 'DN win']);
        }
        $user['coins'] = $user['coins'] - $bet + $payout;
        updateDaily($db, $user['id'], 'dn_plays');
        if ($payout > $bet) updateDaily($db, $user['id'], 'coins_won', $payout - $bet);
        autoClaimMissions($db, $user['id']);
        if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => true, 'game' => 'dn', 'result' => $result, 'won' => $won, 'payout' => $payout, 'balance' => $user['coins'], 'missions' => checkMissions($db, $user['id'])]); exit; }
    }
    if ($error && isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $error]); exit; }
}

// --- Dice ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dice']) && $user) {
    $bet = (int)($_POST['bet'] ?? 0); $pickNum = (int)($_POST['number'] ?? 0); $error = '';
    if ($bet < 10) { $error = 'Min 10 coins'; } elseif ($bet > $user['coins']) { $error = 'Not enough coins'; } elseif ($pickNum < 1 || $pickNum > 6) { $error = 'Pick 1-6'; }
    if (!$error) {
        $rolled = mt_rand(1,6); $won = $pickNum === $rolled; $payout = $won ? $bet * 6 : 0;
        $db->query("UPDATE shop_users SET coins = coins - ? WHERE id = ?", [$bet, $user['id']]);
        $db->insert('coin_transactions', ['user_id' => $user['id'], 'amount' => -$bet, 'type' => 'spend', 'description' => 'Dice bet']);
        if ($payout > 0) {
            $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$payout, $user['id']]);
            $db->insert('coin_transactions', ['user_id' => $user['id'], 'amount' => $payout, 'type' => 'bonus', 'description' => 'Dice win']);
        }
        $user['coins'] = $user['coins'] - $bet + $payout;
        updateDaily($db, $user['id'], 'dice_plays');
        if ($payout > $bet) updateDaily($db, $user['id'], 'coins_won', $payout - $bet);
        autoClaimMissions($db, $user['id']);
        if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => true, 'game' => 'dice', 'rolled' => $rolled, 'won' => $won, 'payout' => $payout, 'balance' => $user['coins'], 'missions' => checkMissions($db, $user['id'])]); exit; }
    }
    if ($error && isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $error]); exit; }
}

// --- Mystery Box ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mb']) && $user) {
    $error = '';
    if ($user['coins'] < 50) { $error = 'Need 50 coins'; }
    if (!$error) {
        $db->query("UPDATE shop_users SET coins = coins - 50 WHERE id = ?", [$user['id']]);
        $db->insert('coin_transactions', ['user_id' => $user['id'], 'amount' => -50, 'type' => 'spend', 'description' => 'Mystery Box']);
        $tw = 0; foreach ($mbRarities as $r) { $tw += $r['weight']; }
        $rr = mt_rand(1, $tw); $rc = 0; $rIdx = 0;
        foreach ($mbRarities as $i => $r) { $rc += $r['weight']; if ($rr <= $rc) { $rIdx = $i; break; } }
        $rarity = $mbRarities[$rIdx];
        $coins = mt_rand($rarity['min'], $rarity['max']);
        $db->query("UPDATE shop_users SET coins = coins + ? WHERE id = ?", [$coins, $user['id']]);
        $db->insert('coin_transactions', ['user_id' => $user['id'], 'amount' => $coins, 'type' => 'bonus', 'description' => 'Mystery Box - ' . $rarity['name']]);
        \Core\Logger::info('Mystery Box', ['user_id' => $user['id'], 'rarity' => $rarity['name'], 'coins' => $coins]);
        $user['coins'] = $user['coins'] - 50 + $coins;
        updateDaily($db, $user['id'], 'mystery_boxes');
        updateDaily($db, $user['id'], 'coins_won', $coins);
        autoClaimMissions($db, $user['id']);
        if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => true, 'game' => 'mb', 'rarity' => $rarity['name'], 'color' => $rarity['color'], 'coins' => $coins, 'balance' => $user['coins'], 'missions' => checkMissions($db, $user['id'])]); exit; }
    }
    if ($error && isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $error]); exit; }
}

require_once __DIR__ . '/includes/header.php';
$activeGame = $_GET['game'] ?? 'wheel';
if ($loggedIn) { autoClaimMissions($db, $user['id']); }
$missions = $loggedIn ? checkMissions($db, $user['id']) : [];
?>
<section class="section">
    <div class="container" style="max-width: 800px;">
        <h2 class="section-title"><i class="fas fa-gamepad title-accent"></i> Games</h2>
        <p class="section-subtitle">Try your luck and complete daily missions!</p>

        <?php if (!$loggedIn): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
                <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;text-align:center;">
                    <div style="font-size:2.5rem;color:var(--accent);margin-bottom:0.5rem;"><i class="fas fa-spinner"></i></div>
                    <h4 style="margin:0 0 0.3rem;">Lucky Wheel</h4>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.5rem;">Cost: 25 coins<br>Win up to 300 coins!</p>
                    <a href="login.php" class="btn btn-primary btn-sm" style="font-size:0.7rem;padding:0.3rem 1rem;"><i class="fas fa-sign-in-alt"></i> Play</a>
                </div>
                <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;text-align:center;">
                    <div style="font-size:2.5rem;color:var(--accent);margin-bottom:0.5rem;"><i class="fas fa-adjust"></i></div>
                    <h4 style="margin:0 0 0.3rem;">Double or Nothing</h4>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.5rem;">Bet coins, pick Red/Black<br>50% chance to win double!</p>
                    <a href="login.php" class="btn btn-primary btn-sm" style="font-size:0.7rem;padding:0.3rem 1rem;"><i class="fas fa-sign-in-alt"></i> Play</a>
                </div>
                <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;text-align:center;">
                    <div style="font-size:2.5rem;color:var(--accent);margin-bottom:0.5rem;"><i class="fas fa-dice"></i></div>
                    <h4 style="margin:0 0 0.3rem;">Dice</h4>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.5rem;">Bet coins, pick 1-6<br>Win 6x your bet!</p>
                    <a href="login.php" class="btn btn-primary btn-sm" style="font-size:0.7rem;padding:0.3rem 1rem;"><i class="fas fa-sign-in-alt"></i> Play</a>
                </div>
                <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;text-align:center;">
                    <div style="font-size:2.5rem;color:var(--accent);margin-bottom:0.5rem;"><i class="fas fa-gift"></i></div>
                    <h4 style="margin:0 0 0.3rem;">Mystery Box</h4>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.5rem;">Cost: 50 coins<br>Find Legendary 300-500!</p>
                    <a href="login.php" class="btn btn-primary btn-sm" style="font-size:0.7rem;padding:0.3rem 1rem;"><i class="fas fa-sign-in-alt"></i> Play</a>
                </div>
            </div>
            <div style="text-align:center;padding:1rem;">
                <p style="color:var(--text-secondary);margin-bottom:0.5rem;">Complete daily missions for extra rewards!</p>
                <a href="login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt"></i> Login to Play</a>
            </div>
        <?php else: ?>
        
        <!-- Daily Missions -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;margin-bottom:1.5rem;" id="missions-widget">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.8rem;">
                <i class="fas fa-tasks" style="color:var(--accent);"></i>
                <span style="font-family:var(--header-font);font-weight:600;font-size:0.9rem;">Daily Missions</span>
                <span style="font-size:0.7rem;color:var(--text-muted);margin-left:auto;">Reset in <span id="mission-reset">24h</span></span>
            </div>
            <div id="missions-list" style="display:grid;gap:0.5rem;">
                <?php foreach ($missions as $m): ?>
                <div class="mission-item" data-id="<?= $m['id'] ?>" style="display:flex;align-items:center;gap:0.5rem;padding:0.4rem 0.6rem;background:<?= $m['done'] && !$m['claimed'] ? 'rgba(255,193,7,0.1)' : 'var(--bg-input)' ?>;border-radius:6px;border:1px solid <?= $m['done'] && !$m['claimed'] ? 'var(--accent)' : 'var(--border)' ?>;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:0.78rem;font-weight:600;"><?= $m['title'] ?> <?php if ($m['claimed']): ?><span style="color:var(--success);font-size:0.65rem;"><i class="fas fa-check-circle"></i> Done</span><?php elseif ($m['done']): ?><span style="color:var(--accent);font-size:0.65rem;"><i class="fas fa-star"></i> Claimed!</span><?php endif; ?></div>
                        <div style="font-size:0.68rem;color:var(--text-muted);"><?= $m['desc'] ?></div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:0.7rem;color:var(--accent);font-weight:600;">+<?= $m['reward'] ?></div>
                        <div style="font-size:0.65rem;color:var(--text-muted);"><?= min($m['progress'], $m['target']) ?>/<?= $m['target'] ?></div>
                    </div>
                    <div style="width:50px;height:4px;background:var(--bg-input);border-radius:2px;flex-shrink:0;">
                        <div style="height:4px;border-radius:2px;background:<?= $m['done'] ? 'var(--success)' : 'var(--accent)' ?>;width:<?= min(100, ($m['progress']/$m['target'])*100) ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="result-area"></div>

        <!-- Game Tabs -->
        <div style="display:flex;gap:0.4rem;justify-content:center;margin-bottom:1.5rem;flex-wrap:wrap;">
            <a href="?game=wheel" class="btn <?= $activeGame === 'wheel' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.8rem;"><i class="fas fa-spinner"></i> Wheel</a>
            <a href="?game=dn" class="btn <?= $activeGame === 'dn' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.8rem;"><i class="fas fa-adjust"></i> 50/50</a>
            <a href="?game=dice" class="btn <?= $activeGame === 'dice' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.8rem;"><i class="fas fa-dice"></i> Dice</a>
            <a href="?game=mb" class="btn <?= $activeGame === 'mb' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.8rem;"><i class="fas fa-gift"></i> Mystery Box</a>
        </div>

        <!-- Wheel -->
        <?php if ($activeGame === 'wheel'): ?>
        <div style="text-align:center;">
            <div id="wheel-wrap" style="position:relative;display:inline-block;margin:0 auto;">
                <div id="wheel-pointer" style="position:absolute;top:-15px;left:50%;transform:translateX(-50%);z-index:10;font-size:2rem;color:var(--accent);"><i class="fas fa-caret-down"></i></div>
                <div id="wheel" style="width:280px;height:280px;border-radius:50%;position:relative;overflow:hidden;border:4px solid var(--accent);box-shadow:0 0 30px rgba(255,193,7,0.3);transition:transform 4s cubic-bezier(0.17,0.67,0.12,0.99);">
                    <?php $nS = count($segments); $sA = 360 / $nS; foreach ($segments as $i => $seg):
                        $sa = $i * $sA; $ea = ($i + 1) * $sA; $rad = ($sa + $sA/2 - 90) * M_PI / 180; $lx = 140 + 95 * cos($rad); $ly = 140 + 95 * sin($rad);
                    ?>
                        <div style="position:absolute;top:0;left:0;width:100%;height:100%;clip-path:polygon(50% 50%, <?= 50 + 50*cos(($sa-90)*M_PI/180) ?>% <?= 50 + 50*sin(($sa-90)*M_PI/180) ?>%, <?= 50 + 50*cos(($ea-90)*M_PI/180) ?>% <?= 50 + 50*sin(($ea-90)*M_PI/180) ?>%);background:<?= $seg['color'] ?>;"></div>
                        <div style="position:absolute;top:<?= $ly ?>px;left:<?= $lx ?>px;transform:translate(-50%,-50%);color:#fff;font-weight:800;font-size:0.7rem;text-shadow:0 1px 3px rgba(0,0,0,0.5);z-index:2;"><?= $seg['label'] ?></div>
                    <?php endforeach; ?>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:45px;height:45px;border-radius:50%;background:var(--bg-card);border:3px solid var(--accent);z-index:5;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.65rem;color:var(--accent);"><i class="fas fa-coins"></i></div>
                </div>
            </div>
            <div style="margin-top:1.2rem;"><button class="btn btn-primary btn-lg" onclick="spinWheel()" id="spin-btn" style="font-size:1rem;padding:0.8rem 2.5rem;"><i class="fas fa-spinner"></i> Spin (25 🪙)</button></div>
            <div style="margin-top:0.8rem;display:grid;grid-template-columns:repeat(4,1fr);gap:0.3rem;max-width:400px;margin-inline:auto;">
                <?php foreach ($segments as $s): ?>
                <div style="background:<?= $s['color'] ?>20;border:1px solid <?= $s['color'] ?>;border-radius:4px;padding:0.25rem;text-align:center;font-size:0.65rem;"><span style="font-weight:700;color:<?= $s['color'] ?>;"><?= $s['coins'] > 0 ? '+' . $s['coins'] : $s['label'] ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Double or Nothing -->
        <?php elseif ($activeGame === 'dn'): ?>
        <div style="text-align:center;">
            <div id="dn-display" style="font-size:4rem;margin:0.5rem 0;min-height:80px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-question-circle" style="color:var(--text-muted);"></i></div>
            <div style="margin-bottom:0.8rem;">
                <label style="color:var(--text-secondary);font-size:0.8rem;">Bet:</label>
                <input type="number" id="dn-bet" value="25" min="10" style="width:90px;text-align:center;margin-left:0.5rem;">
                <div style="display:flex;gap:0.3rem;justify-content:center;margin-top:0.5rem;">
                    <button class="btn btn-sm btn-secondary" onclick="document.getElementById('dn-bet').value=25">25</button>
                    <button class="btn btn-sm btn-secondary" onclick="document.getElementById('dn-bet').value=50">50</button>
                    <button class="btn btn-sm btn-secondary" onclick="document.getElementById('dn-bet').value=100">100</button>
                    <button class="btn btn-sm btn-secondary" onclick="document.getElementById('dn-bet').value=200">200</button>
                </div>
            </div>
            <div style="display:flex;gap:0.8rem;justify-content:center;">
                <button class="btn btn-lg" style="background:#d32f2f;color:#fff;padding:0.8rem 1.5rem;font-size:1rem;border-radius:8px;" onclick="playDN('red')"><i class="fas fa-circle" style="color:#fff;"></i> RED</button>
                <button class="btn btn-lg" style="background:#333;color:#fff;padding:0.8rem 1.5rem;font-size:1rem;border-radius:8px;" onclick="playDN('black')"><i class="fas fa-circle" style="color:#fff;"></i> BLACK</button>
            </div>
        </div>

        <!-- Dice -->
        <?php elseif ($activeGame === 'dice'): ?>
        <div style="text-align:center;">
            <div id="dice-display" style="font-size:4rem;margin:0.5rem 0;min-height:80px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-dice" style="color:var(--text-muted);"></i></div>
            <div style="margin-bottom:0.8rem;">
                <label style="color:var(--text-secondary);font-size:0.8rem;">Bet:</label>
                <input type="number" id="dice-bet" value="25" min="10" style="width:90px;text-align:center;">
            </div>
            <div style="display:flex;gap:0.4rem;justify-content:center;flex-wrap:wrap;">
                <?php for ($n=1; $n<=6; $n++): ?>
                <button class="btn btn-lg" style="width:55px;height:55px;font-size:1.3rem;padding:0;border-radius:8px;" onclick="playDice(<?= $n ?>)"><?= $n ?></button>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Mystery Box -->
        <?php elseif ($activeGame === 'mb'): ?>
        <div style="text-align:center;">
            <div id="mb-display" style="font-size:5rem;margin:0.5rem 0;min-height:100px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:0.5rem;cursor:pointer;" onclick="openMB()">
                <div id="mb-icon"><i class="fas fa-gift" style="color:var(--accent);font-size:5rem;"></i></div>
                <div id="mb-label" style="font-size:0.85rem;color:var(--text-secondary);">Click to open (50 🪙)</div>
            </div>
            <div style="margin-top:0.5rem;">
                <button class="btn btn-primary btn-lg" onclick="openMB()" id="mb-btn" style="font-size:1rem;padding:0.8rem 2.5rem;"><i class="fas fa-gift"></i> Open Mystery Box (50 🪙)</button>
            </div>
            <div style="margin-top:1rem;display:grid;grid-template-columns:repeat(5,1fr);gap:0.3rem;">
                <?php foreach ($mbRarities as $r): ?>
                <div style="background:<?= $r['color'] ?>20;border:1px solid <?= $r['color'] ?>;border-radius:4px;padding:0.3rem;text-align:center;">
                    <div style="font-size:0.65rem;font-weight:700;color:<?= $r['color'] ?>;"><?= $r['name'] ?></div>
                    <div style="font-size:0.6rem;color:var(--text-muted);"><?= $r['min'] ?>-<?= $r['max'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top:1.5rem;padding:1rem;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);text-align:center;">
            <div style="color:var(--text-secondary);font-size:0.8rem;">Balance</div>
            <div style="font-size:1.8rem;font-weight:800;color:var(--accent);" id="balance-display"><?= number_format($user['coins']) ?></div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
const segAngle = <?= 360 / count($segments) ?>;
let spinning = false;

function updateMissions(data) {
    if (data.missions) {
        var html = '';
        data.missions.forEach(function(m) {
            var pct = Math.min(100, (m.progress / m.target) * 100);
            var status = '';
            if (m.claimed) status = '<span style="color:var(--success);font-size:0.65rem;"><i class="fas fa-check-circle"></i> Done</span>';
            else if (m.done) status = '<span style="color:var(--accent);font-size:0.65rem;"><i class="fas fa-star"></i> Claimed!</span>';
            html += '<div class="mission-item" style="display:flex;align-items:center;gap:0.5rem;padding:0.4rem 0.6rem;background:' + (m.done && !m.claimed ? 'rgba(255,193,7,0.1)' : 'var(--bg-input)') + ';border-radius:6px;border:1px solid ' + (m.done && !m.claimed ? 'var(--accent)' : 'var(--border)') + ';">';
            html += '<div style="flex:1;min-width:0;"><div style="font-size:0.78rem;font-weight:600;">' + m.title + ' ' + status + '</div>';
            html += '<div style="font-size:0.68rem;color:var(--text-muted);">' + m.desc + '</div></div>';
            html += '<div style="text-align:right;flex-shrink:0;"><div style="font-size:0.7rem;color:var(--accent);font-weight:600;">+' + m.reward + '</div>';
            html += '<div style="font-size:0.65rem;color:var(--text-muted);">' + Math.min(m.progress, m.target) + '/' + m.target + '</div></div>';
            html += '<div style="width:50px;height:4px;background:var(--bg-input);border-radius:2px;flex-shrink:0;"><div style="height:4px;border-radius:2px;background:' + (m.done ? 'var(--success)' : 'var(--accent)') + ';width:' + pct + '%;"></div></div></div>';
        });
        document.getElementById('missions-list').innerHTML = html;
    }
}

function getBalance() {
    return parseInt(document.getElementById('balance-display').textContent.replace(/,/g,''));
}
function setBalance(n) {
    document.getElementById('balance-display').textContent = n.toLocaleString();
}

async function spinWheel() {
    if (spinning) return false;
    if (getBalance() < 25) { document.getElementById('result-area').innerHTML = '<div class="alert alert-danger">Need 25 coins!</div>'; return; }
    spinning = true;
    var btn = document.getElementById('spin-btn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Spinning...';
    document.getElementById('result-area').innerHTML = '';
    var fd = new FormData(); fd.append('spin','1'); fd.append('ajax','1');
    try {
        var res = await fetch('games.php', { method:'POST', body:fd });
        var data = await res.json();
        if (!data.success) { location.reload(); return; }
        var extra = 5 + Math.floor(Math.random()*3);
        var target = data.index * segAngle + 2 + Math.random()*(segAngle-4);
        document.getElementById('wheel').style.transform = 'rotate(' + (extra*360+target) + 'deg)';
        setTimeout(function() {
            if (data.coins > 0) {
                document.getElementById('result-area').innerHTML = '<div class="alert alert-success" style="text-align:center;font-size:1.2rem;padding:1rem;"><i class="fas fa-trophy"></i> Won <strong style="color:var(--accent);font-size:1.8rem;">+' + data.coins + '</strong>!</div>';
            } else {
                document.getElementById('result-area').innerHTML = '<div class="alert alert-danger" style="text-align:center;font-size:1.2rem;padding:1rem;"><i class="fas fa-sad-tear"></i> Nothing...</div>';
            }
            setBalance(getBalance() - 25 + data.coins);
            btn.innerHTML = '<i class="fas fa-spinner"></i> Spin (25 🪙)'; btn.disabled = false; spinning = false;
            updateMissions(data);
        }, 4200);
    } catch(e) { location.reload(); }
}

async function playDN(pick) {
    var bet = parseInt(document.getElementById('dn-bet').value);
    if (bet < 10 || bet > getBalance()) { document.getElementById('result-area').innerHTML = '<div class="alert alert-danger">Invalid bet!</div>'; return; }
    document.getElementById('result-area').innerHTML = '';
    document.getElementById('dn-display').innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:3rem;color:var(--accent);"></i>';
    var fd = new FormData(); fd.append('dn','1'); fd.append('bet',bet); fd.append('pick',pick); fd.append('ajax','1');
    try {
        var res = await fetch('games.php', { method:'POST', body:fd });
        var data = await res.json();
        if (!data.success) { location.reload(); return; }
        var icon = data.result === 'red' ? '<i class="fas fa-circle" style="color:#d32f2f;font-size:4rem;"></i>' : '<i class="fas fa-circle" style="color:#333;font-size:4rem;"></i>';
        document.getElementById('dn-display').innerHTML = icon;
        setTimeout(function() {
            if (data.won) {
                document.getElementById('result-area').innerHTML = '<div class="alert alert-success" style="text-align:center;font-size:1.2rem;padding:1rem;"><i class="fas fa-trophy"></i> Won <strong style="color:var(--accent);font-size:1.8rem;">+' + data.payout + '</strong>!</div>';
            } else {
                document.getElementById('result-area').innerHTML = '<div class="alert alert-danger" style="text-align:center;font-size:1.2rem;padding:1rem;"><i class="fas fa-sad-tear"></i> Lost ' + bet + ' coins</div>';
            }
            setBalance(getBalance() - bet + data.payout);
            updateMissions(data);
        }, 500);
    } catch(e) { location.reload(); }
}

async function playDice(num) {
    var bet = parseInt(document.getElementById('dice-bet').value);
    if (bet < 10 || bet > getBalance()) { document.getElementById('result-area').innerHTML = '<div class="alert alert-danger">Invalid bet!</div>'; return; }
    document.getElementById('result-area').innerHTML = '';
    document.getElementById('dice-display').innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:3rem;color:var(--accent);"></i>';
    var fd = new FormData(); fd.append('dice','1'); fd.append('bet',bet); fd.append('number',num); fd.append('ajax','1');
    try {
        var res = await fetch('games.php', { method:'POST', body:fd });
        var data = await res.json();
        if (!data.success) { location.reload(); return; }
        var faces = ['one','two','three','four','five','six'];
        document.getElementById('dice-display').innerHTML = '<i class="fas fa-dice-' + faces[data.rolled-1] + '" style="font-size:5rem;color:var(--accent);"></i>';
        setTimeout(function() {
            if (data.won) {
                document.getElementById('result-area').innerHTML = '<div class="alert alert-success" style="text-align:center;font-size:1.2rem;padding:1rem;"><i class="fas fa-trophy"></i> Won <strong style="color:var(--accent);font-size:1.8rem;">+' + data.payout + '</strong>!</div>';
            } else {
                document.getElementById('result-area').innerHTML = '<div class="alert alert-danger" style="text-align:center;font-size:1.2rem;padding:1rem;"><i class="fas fa-sad-tear"></i> Lost ' + bet + ' coins</div>';
            }
            setBalance(getBalance() - bet + data.payout);
            updateMissions(data);
        }, 500);
    } catch(e) { location.reload(); }
}

let mbOpening = false;
async function openMB() {
    if (mbOpening) return;
    if (getBalance() < 50) { document.getElementById('result-area').innerHTML = '<div class="alert alert-danger">Need 50 coins!</div>'; return; }
    mbOpening = true;
    var btn = document.getElementById('mb-btn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Opening...';
    document.getElementById('result-area').innerHTML = '';
    document.getElementById('mb-icon').innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:4rem;color:var(--accent);"></i>';
    document.getElementById('mb-label').textContent = 'Opening...';
    var fd = new FormData(); fd.append('mb','1'); fd.append('ajax','1');
    try {
        var res = await fetch('games.php', { method:'POST', body:fd });
        var data = await res.json();
        if (!data.success) { location.reload(); return; }
        document.getElementById('mb-icon').innerHTML = '<i class="fas fa-star" style="font-size:4rem;color:' + data.color + ';"></i>';
        document.getElementById('mb-label').innerHTML = '<span style="font-size:1.2rem;font-weight:700;color:' + data.color + ';">' + data.rarity + '</span> — <span style="color:var(--accent);font-weight:700;">+' + data.coins + ' 🪙</span>';
        document.getElementById('result-area').innerHTML = '<div class="alert alert-success" style="text-align:center;font-size:1.1rem;padding:0.8rem;"><i class="fas fa-trophy"></i> <strong style="color:' + data.color + ';">' + data.rarity + '</strong>! You got <strong style="color:var(--accent);">+' + data.coins + ' Coins</strong>!</div>';
        setBalance(getBalance() - 50 + data.coins);
        btn.innerHTML = '<i class="fas fa-gift"></i> Open Mystery Box (50 🪙)'; btn.disabled = false; mbOpening = false;
        updateMissions(data);
    } catch(e) { location.reload(); }
}

// Mission countdown
(function() {
    var now = new Date();
    var end = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 0);
    function tick() {
        var diff = end - new Date();
        if (diff <= 0) { location.reload(); return; }
        var h = Math.floor(diff / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        document.getElementById('mission-reset').textContent = h + 'h ' + m + 'm ' + s + 's';
    }
    setInterval(tick, 1000); tick();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
