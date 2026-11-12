<?php
/**
 * PDO connection for the project's MySQL/MariaDB schema.
 * Credentials and port are supplied by the local environment with safe self-healing fallback.
 */

$dbHost = getenv('DB_HOST') ?: ($_SERVER['DB_HOST'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'));
$dbPort = getenv('DB_PORT') ?: ($_SERVER['DB_PORT'] ?? ($_ENV['DB_PORT'] ?? '3307'));
$dbName = getenv('DB_NAME') ?: ($_SERVER['DB_NAME'] ?? ($_ENV['DB_NAME'] ?? 'ai_notes_summarizer'));
$dbUser = getenv('DB_USER') ?: ($_SERVER['DB_USER'] ?? ($_ENV['DB_USER'] ?? 'root'));
$dbPassword = getenv('DB_PASSWORD');
if ($dbPassword === false || $dbPassword === null || $dbPassword === '') {
    $dbPassword = $_SERVER['DB_PASSWORD'] ?? ($_ENV['DB_PASSWORD'] ?? (getenv('DB_PASS') ?: ($_SERVER['DB_PASS'] ?? ($_ENV['DB_PASS'] ?? 'kartik8007'))));
}
$dbCharset = getenv('DB_CHARSET') ?: ($_SERVER['DB_CHARSET'] ?? ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));

if (!defined('DB_HOST')) define('DB_HOST', (string)$dbHost);
if (!defined('DB_PORT')) define('DB_PORT', (string)$dbPort);
if (!defined('DB_NAME')) define('DB_NAME', (string)$dbName);
if (!defined('DB_USER')) define('DB_USER', (string)$dbUser);
if (!defined('DB_PASS')) define('DB_PASS', (string)$dbPassword);
if (!defined('DB_CHARSET')) define('DB_CHARSET', (string)$dbCharset);

/**
 * Fast probe to verify if a MySQL server on a given port sends a valid handshake packet.
 * Avoids 60-second mysqlnd TCP hangs when a socket is bound but server is unresponsive.
 */
function is_mysql_port_healthy($host, $port, $timeoutMs = 150) {
    $seconds = (int) ($timeoutMs / 1000);
    $uSeconds = ($timeoutMs % 1000) * 1000;
    $fp = @fsockopen($host, (int) $port, $errno, $errstr, 0.15);
    if (!$fp) {
        return false;
    }
    stream_set_timeout($fp, $seconds, $uSeconds);
    $banner = @fread($fp, 20);
    fclose($fp);
    return (strlen($banner) >= 5 && ord($banner[4]) === 0x0a);
}

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        // Prioritize configured port, with automatic fallback between 3307 and 3306
        $configuredPort = (int) DB_PORT;
        $candidatePorts = [$configuredPort];
        if (!in_array(3306, $candidatePorts)) $candidatePorts[] = 3306;
        if (!in_array(3307, $candidatePorts)) $candidatePorts[] = 3307;

        // Find the first responsive port
        $activePort = null;
        foreach ($candidatePorts as $p) {
            if (is_mysql_port_healthy(DB_HOST, $p, 150)) {
                $activePort = $p;
                break;
            }
        }

        $portsToAttempt = $activePort !== null ? [$activePort] : $candidatePorts;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 2,
        ];

        $lastException = null;
        foreach ($portsToAttempt as $port) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $testPdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                // Verify basic query execution
                $testPdo->query("SELECT 1");
                $pdo = $testPdo;
                break;
            } catch (PDOException $e) {
                $lastException = $e;
                continue;
            }
        }

        if ($pdo === null) {
            error_log('Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'No responsive port found.'));
            http_response_code(500);
            exit('Database connection failed. Please ensure MySQL service is running and configured.');
        }
    }

    return $pdo;
}