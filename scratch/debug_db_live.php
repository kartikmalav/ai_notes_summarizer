<?php
foreach (['127.0.0.1', 'localhost'] as $host) {
    foreach ([3306, 3307] as $port) {
        echo "=== $host : $port ===\n";
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=ai_notes_summarizer", "root", "kartik8007", [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $info = $pdo->query("SELECT VERSION() as v, @@datadir as d, @@port as p")->fetch(PDO::FETCH_ASSOC);
            echo "  Version: {$info['v']} | Port: {$info['p']} | Datadir: {$info['d']}\n";
            $cnt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            echo "  users count: $cnt\n";
            foreach ($pdo->query("SELECT id, full_name, email, created_at FROM users") as $u) {
                echo "    - {$u['id']}: {$u['full_name']} ({$u['email']}) | {$u['created_at']}\n";
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
        }
    }
}


