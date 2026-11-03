<?php
$c = file_get_contents('C:/xampp/htdocs/ai_notes_summarizer/includes/gemini.php');
preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(([^)]*)\)/', $c, $m, PREG_SET_ORDER);
foreach ($m as $fn) {
    echo $fn[1] . '(' . $fn[2] . ")\n";
}
