<?php
echo "Testing DB connections...\n";
$ports = [3307, 3306];
$passwords = ['kartik8007', ''];

foreach ($ports as $port) {
    foreach ($passwords as $pwd) {
        try {
            $dsn = "mysql:host=127.0.0.1;port={$port}";
            $pdo = new PDO($dsn, 'root', $pwd, [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo "SUCCESS: Connected on port $port with password '" . ($pwd ? '***' : 'empty') . "'\n";
            
            // Check databases
            $stmt = $pdo->query("SHOW DATABASES LIKE 'ai_notes_summarizer'");
            $found = $stmt->fetch();
            echo "DB ai_notes_summarizer found: " . ($found ? "YES" : "NO") . "\n";
            exit(0);
        } catch (PDOException $e) {
            echo "Port $port (pwd: " . ($pwd ? '***' : 'empty') . ") failed: " . $e->getMessage() . "\n";
        }
    }
}

