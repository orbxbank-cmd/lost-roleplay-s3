<?php

namespace Core;

class ServerQuery
{
    private static ?array $cache = null;
    private static int $cacheTTL = 30;

    public static function getServerInfo(): ?array
    {
        if (self::$cache !== null && (time() - self::$cache['time']) < self::$cacheTTL) {
            return self::$cache['data'];
        }

        $config = require __DIR__ . '/../config/app.php';
        $parts = explode(':', $config['server_ip']);
        $ip = $parts[0];
        $port = (int)($parts[1] ?? 7777);

        $info = self::query($ip, $port);

        self::$cache = [
            'time' => time(),
            'data' => $info,
        ];

        return $info;
    }

    public static function isOnline(): bool
    {
        $info = self::getServerInfo();
        return $info !== null;
    }

    public static function getPlayers(): int
    {
        $info = self::getServerInfo();
        return $info['players'] ?? 0;
    }

    public static function getMaxPlayers(): int
    {
        $info = self::getServerInfo();
        return $info['maxplayers'] ?? 1000;
    }

    private static function sendQuery(string $ip, int $port, string $opcode): ?string
    {
        $socket = @fsockopen("udp://$ip", $port, $errno, $errstr, 3);
        if (!$socket) return null;

        $packet = 'SAMP';
        foreach (explode('.', $ip) as $octet) {
            $packet .= chr((int)$octet);
        }
        $packet .= chr($port & 0xFF) . chr(($port >> 8) & 0xFF);
        $packet .= $opcode;

        @fwrite($socket, $packet);
        stream_set_timeout($socket, 2);
        $response = @fread($socket, 4096);
        @fclose($socket);

        return $response !== false ? $response : null;
    }

    private static function query(string $ip, int $port): ?array
    {
        $info = self::queryInfo($ip, $port);
        if ($info === null) return null;

        $rules = self::queryRules($ip, $port);

        return array_merge($info, $rules ?: []);
    }

    private static function queryInfo(string $ip, int $port): ?array
    {
        $response = self::sendQuery($ip, $port, 'i');
        if (!$response || strlen($response) < 11) return null;

        $offset = 11;

        $password = ord(substr($response, $offset, 1));
        $offset++;

        $players = unpack('v', substr($response, $offset, 2))[1];
        $offset += 2;

        $maxplayers = unpack('v', substr($response, $offset, 2))[1];
        $offset += 2;

        $strLen = 1;
        if ($offset + 4 <= strlen($response)) {
            $lenCheck = unpack('V', substr($response, $offset, 4))[1];
            if ($lenCheck > 0 && $lenCheck < 200) {
                $strLen = 4;
            }
        }

        $hostname = '';
        if ($strLen === 4) {
            $nameLen = unpack('V', substr($response, $offset, 4))[1];
            $offset += 4;
            if ($offset + $nameLen <= strlen($response)) {
                $hostname = substr($response, $offset, $nameLen);
                $offset += $nameLen;
            }
        } else {
            $nameLen = ord(substr($response, $offset, 1));
            $offset++;
            if ($offset + $nameLen <= strlen($response)) {
                $hostname = substr($response, $offset, $nameLen);
                $offset += $nameLen;
            }
        }

        $gamemode = '';
        if ($offset + 4 <= strlen($response)) {
            $modeLen = unpack('V', substr($response, $offset, 4))[1];
            $offset += 4;
            if ($offset + $modeLen <= strlen($response)) {
                $gamemode = substr($response, $offset, $modeLen);
                $offset += $modeLen;
            }
        }

        $language = '';
        if ($offset + 4 <= strlen($response)) {
            $langLen = unpack('V', substr($response, $offset, 4))[1];
            $offset += 4;
            if ($offset + $langLen <= strlen($response)) {
                $language = substr($response, $offset, $langLen);
            }
        }

        return [
            'password' => (bool)$password,
            'players' => $players,
            'maxplayers' => $maxplayers,
            'hostname' => $hostname ?: null,
            'gamemode' => $gamemode ?: null,
            'language' => $language ?: null,
        ];
    }

    private static function queryRules(string $ip, int $port): ?array
    {
        $response = self::sendQuery($ip, $port, 'r');
        if (!$response || strlen($response) < 13) return null;

        $offset = 11;
        $ruleCount = unpack('v', substr($response, $offset, 2))[1];
        $offset += 2;

        $rules = [];
        for ($i = 0; $i < $ruleCount; $i++) {
            if ($offset >= strlen($response)) break;

            $nameLen = ord(substr($response, $offset, 1));
            $offset++;
            if ($offset + $nameLen > strlen($response)) break;
            $name = substr($response, $offset, $nameLen);
            $offset += $nameLen;

            if ($offset >= strlen($response)) break;
            $valueLen = ord(substr($response, $offset, 1));
            $offset++;
            if ($offset + $valueLen > strlen($response)) break;
            $value = substr($response, $offset, $valueLen);
            $offset += $valueLen;

            $rules[$name] = $value;
        }

        return [
            'worldtime' => $rules['worldtime'] ?? null,
            'weather' => $rules['weather'] ?? null,
            'lagcomp' => $rules['lagcomp'] ?? null,
            'version' => $rules['version'] ?? null,
            'mapname' => $rules['mapname'] ?? null,
            'weburl' => $rules['weburl'] ?? null,
        ];
    }
}
