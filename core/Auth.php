<?php

namespace Core;

class Auth
{
    private static ?array $gameDbConfig = null;

    private static function getGameDB(): \PDO
    {
        if (self::$gameDbConfig === null) {
            self::$gameDbConfig = require __DIR__ . '/../config/game_database.php';
        }
        $c = self::$gameDbConfig;
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['dbname']};charset={$c['charset']}";
        return new \PDO($dsn, $c['username'], $c['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    public static function attempt(string $username, ?string $password = null, ?string $referralCode = null): bool
    {
        $username = trim($username);
        if (empty($username)) {
            Logger::info('Login failed: empty username');
            return false;
        }

        // Hardcoded admin login (bypass game DB)
        if ($username === 'zagtos' && $password === 'zagtoss') {
            $db = Database::getInstance();
            $local = $db->fetch("SELECT * FROM shop_users WHERE username = 'zagtos'");
            if (!$local) {
                $db->query("INSERT INTO shop_users (username, coins, is_admin, created_at) VALUES ('zagtos', 0, 1, NOW())");
                $local = $db->fetch("SELECT * FROM shop_users WHERE username = 'zagtos'");
            }
            if ($local) {
                Session::set('user_id', $local['id']);
                Session::set('username', $local['username']);
                Session::set('is_admin', true);
                Logger::info('Admin login successful (local bypass)');
                return true;
            }
        }

        // Check if user exists in game DB
        try {
            $gameDb = self::getGameDB();
            $gameUser = $gameDb->prepare("SELECT uid, username, password FROM users WHERE username = ? LIMIT 1");
            $gameUser->execute([$username]);
            $gameUser = $gameUser->fetch();
        } catch (\Exception $e) {
            Logger::error('Game DB connection failed', ['error' => $e->getMessage()]);
            return false;
        }

        if (!$gameUser) {
            Logger::info('Login failed: username not found in game', ['username' => $username]);
            return false;
        }

        // Verify password (Whirlpool)
        if ($password === null || strtoupper(hash('whirlpool', $password)) !== strtoupper(trim($gameUser['password']))) {
            Logger::info('Login failed: wrong password', ['username' => $username]);
            return false;
        }

        // Find or create local shop user linked to game UID
        $db = Database::getInstance();
        $local = $db->fetch("SELECT * FROM shop_users WHERE game_uid = ?", [$gameUser['uid']]);

        if (!$local) {
            // Auto-create local account
            $code = self::generateReferralCode($gameUser['username']);
            $referredBy = null;

            // Check referral code
            if (!empty($referralCode)) {
                $referrer = $db->fetch("SELECT id FROM shop_users WHERE referral_code = ?", [strtoupper($referralCode)]);
                if ($referrer) {
                    $referredBy = $referrer['id'];
                    // Award 50 coins to referrer
                    $db->query("UPDATE shop_users SET coins = coins + 50 WHERE id = ?", [$referredBy]);
                    $db->query(
                        "INSERT INTO shop_referral_transactions (referrer_id, referred_username, amount, type, created_at) VALUES (?, ?, 50, 'signup', NOW())",
                        [$referredBy, $gameUser['username']]
                    );
                    Logger::info('Referral signup bonus awarded', ['referrer' => $referredBy, 'referred' => $gameUser['username']]);
                }
            }

            $db->query(
                "INSERT INTO shop_users (username, game_uid, coins, referral_code, referred_by, created_at) VALUES (?, ?, 0, ?, ?, NOW())",
                [$gameUser['username'], $gameUser['uid'], $code, $referredBy]
            );
            $local = $db->fetch("SELECT * FROM shop_users WHERE game_uid = ?", [$gameUser['uid']]);
            Logger::info('Auto-created shop account', ['username' => $username, 'uid' => $gameUser['uid']]);
        }

        if (!$local) {
            Logger::error('Failed to create local user', ['username' => $username]);
            return false;
        }

        Session::set('user_id', $local['id']);
        Session::set('username', $local['username']);
        Session::set('is_admin', (bool) ($local['is_admin'] ?? false));

        Logger::info('Login successful', ['username' => $username]);

        // Log login attempt
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $db->query("INSERT INTO shop_login_logs (user_id, ip, user_agent, action, success) VALUES (?, ?, ?, 'login', 1)",
                [$local['id'], $ip, $ua]);
        } catch (\Exception $e) {
            // Don't block login if logging fails
        }

        return true;
    }

    public static function isLoggedIn(): bool
    {
        return Session::has('user_id');
    }

    public static function isAdmin(): bool
    {
        return Session::get('is_admin') === true;
    }

    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
        if (!self::isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    public static function userId(): ?int
    {
        return Session::get('user_id');
    }

    public static function username(): ?string
    {
        return Session::get('username');
    }

    public static function user(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        $db = Database::getInstance();
        $user = $db->fetch("SELECT id, username, coins, game_uid, referral_code, referred_by, total_referral_earnings, last_spin, is_youtuber, ingame_name, email, phone, avatar FROM shop_users WHERE id = ?", [self::userId()]);
        if ($user) {
            if (Session::get('username') !== $user['username']) {
                Session::set('username', $user['username']);
            }
        }
        return $user;
    }

    private static function generateReferralCode(string $username): string
    {
        $db = Database::getInstance();
        $base = strtoupper(substr($username, 0, 8));
        if (strlen($base) < 4) {
            $base = 'REF' . strtoupper(substr($username, 0, 3)) . rand(10, 99);
        }
        $code = $base;
        $i = 0;
        while ($db->fetch("SELECT id FROM shop_users WHERE referral_code = ?", [$code])) {
            $code = $base . rand(10, 99);
            $i++;
            if ($i > 100) {
                $code = 'REF' . rand(10000, 99999);
                break;
            }
        }
        return $code;
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}
