<?php
foreach ([3306, 3307] as $port) {
    echo "=== PORT $port ===\n";
    foreach (["kartik8007", ""] as $pwd) {
        try {
            $pdo = new PDO("mysql:host=127.0.0.1;port=$port", "root", $pwd, [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo "Connected with pwd=" . ($pwd ? "SET" : "EMPTY") . "\n";
            echo "Version: " . $pdo->query("SELECT VERSION()")->fetchColumn() . "\n";
            $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
            echo "Databases: " . implode(", ", $dbs) . "\n";
            if (in_array("ai_notes_summarizer", $dbs)) {
                $pdo->exec("USE ai_notes_summarizer");
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $t) {
                    $cnt = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
                    echo "  Table $t: $cnt rows\n";
                }
                
                if (in_array("users", $tables)) {
                    $users = $pdo->query("SELECT id, full_name, email, created_at FROM users")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($users as $u) {
                        $masked = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $u['email']);
                        echo "    User {$u['id']}: {$u['full_name']} ($masked) | {$u['created_at']}\n";
                    }
                }
            }
            break;
        } catch (Exception $e) {
            echo "Failed pwd=" . ($pwd ? "SET" : "EMPTY") . ": " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}
