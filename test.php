<?php
$host = '45.8.187.109';
$port = 3306;
$timeout = 5;

echo "<h2>Testing connection to game server DB...</h2>";

$fp = @fsockopen($host, $port, $errno, $errstr, $timeout);

if (!$fp) {
    echo "<p style='color:red;'><b>BLOCKED:</b> Cannot connect to $host:$port ($errstr)</p>";
    echo "<p>Outbound MySQL port 3306 is blocked by InfinityFree.</p>";
} else {
    fclose($fp);
    echo "<p style='color:green;'><b>OK:</b> Connection to $host:$port works!</p>";
}

// Test HTTP
$fp2 = @fsockopen($host, 80, $errno2, $errstr2, $timeout);
if (!$fp2) {
    echo "<p style='color:red;'>HTTP (80) BLOCKED</p>";
} else {
    fclose($fp2);
    echo "<p style='color:green;'>HTTP (80) OK</p>";
}
?>