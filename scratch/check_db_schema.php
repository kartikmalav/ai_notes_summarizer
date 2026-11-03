<?php
require 'config/config.php';
require 'config/database.php';
$pdo = getDBConnection();
$col = $pdo->query("SHOW COLUMNS FROM generated_content WHERE Field = 'content_type'")->fetch();
echo "Live MySQL content_type: " . $col['Type'] . PHP_EOL;
