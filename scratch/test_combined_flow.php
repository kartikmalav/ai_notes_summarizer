<?php
/**
 * Test script for end-to-end Important Questions generation logic
 */

require_once __DIR__ . '/../includes/markdown.php';

function parse_section_questions($sectionText, $expectedCount) {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $sectionText));
    $questions = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^(?:\*{0,2}(?:Q\s*)?\d+[\.\)]\*{0,2}|\-|\*)\s*(.+)$/iu', $trimmed, $m)) {
            $q = trim($m[1]);
            $q = preg_replace('/^\*{0,2}(?:Question|Q)\s*\d*\s*[:\.]?\*{0,2}\s*/iu', '', $q);
            $q = trim($q);
            if (strlen($q) > 10 && !preg_match('/^(Section|Part|Unit|Chapter|Note|Answer)\b/i', $q)) {
                $questions[] = $q;
            }
        }
    }

    if (count($questions) >= $expectedCount) {
        return [
            'valid' => true,
            'count' => $expectedCount,
            'questions' => array_slice($questions, 0, $expectedCount)
        ];
    }

    return [
        'valid' => false,
        'count' => count($questions),
        'questions' => $questions
    ];
}

function split_and_validate_important_questions($text) {
    if (empty($text)) return false;

    $patternA = '/###\s*(?:📌\s*)?Section A[^\n]*/i';
    $patternB = '/###\s*(?:💡\s*)?Section B[^\n]*/i';
    $patternC = '/###\s*(?:🔑\s*)?Section C[^\n]*/i';

    if (!preg_match($patternA, $text, $matchA, PREG_OFFSET_CAPTURE)) return false;
    if (!preg_match($patternB, $text, $matchB, PREG_OFFSET_CAPTURE)) return false;
    if (!preg_match($patternC, $text, $matchC, PREG_OFFSET_CAPTURE)) return false;

    $posA = $matchA[0][1] + strlen($matchA[0][0]);
    $posB = $matchB[0][1];
    $secAText = substr($text, $posA, $posB - $posA);

    $posBEnd = $matchB[0][1] + strlen($matchB[0][0]);
    $posC = $matchC[0][1];
    $secBText = substr($text, $posBEnd, $posC - $posBEnd);

    $posCEnd = $matchC[0][1] + strlen($matchC[0][0]);
    $secCText = substr($text, $posCEnd);

    $qA = parse_section_questions($secAText, 5);
    $qB = parse_section_questions($secBText, 3);
    $qC = parse_section_questions($secCText, 2);

    if ($qA['valid'] && $qB['valid'] && $qC['valid']) {
        return [
            'valid' => true,
            'secA' => $qA['questions'],
            'secB' => $qB['questions'],
            'secC' => $qC['questions']
        ];
    }

    return false;
}

function format_final_important_questions(array $secA, array $secB, array $secC) {
    $out = "### Section A: 2-Mark Short Answer Questions (5 Questions)\n";
    foreach ($secA as $i => $q) {
        $num = $i + 1;
        $out .= "{$num}. {$q}\n";
    }

    $out .= "\n### Section B: 5-Mark Medium Answer Questions (3 Questions)\n";
    foreach ($secB as $i => $q) {
        $num = $i + 1;
        $out .= "{$num}. {$q}\n";
    }

    $out .= "\n### Section C: 10-Mark Long Answer Questions (2 Questions)\n";
    foreach ($secC as $i => $q) {
        $num = $i + 1;
        $out .= "{$num}. {$q}\n";
    }

    return trim($out);
}

// Test sample
$sample = <<<EOD
### Section A: 2-Mark Short Answer Questions
1. Define an operating system and state its primary goals.
2. What is the difference between multiprogramming and multitasking?
3. List the states in a five-state process model.
4. Define context switching and identify why it introduces overhead.
5. What is mutual exclusion in concurrency control?

### Section B: 5-Mark Medium Answer Questions
1. Explain the Round Robin CPU scheduling algorithm with an example.
2. Describe the Banker's Algorithm for deadlock avoidance.
3. Compare contiguous memory allocation with non-contiguous memory allocation.

### Section C: 10-Mark Long Answer Questions
1. Discuss the Critical Section Problem, Peterson's algorithm, and hardware synchronization solutions in detail.
2. Explain the concept of Virtual Memory, demand paging, and page fault handling mechanism with diagrams.
EOD;

$parsed = split_and_validate_important_questions($sample);
if ($parsed && $parsed['valid']) {
    $formatted = format_final_important_questions($parsed['secA'], $parsed['secB'], $parsed['secC']);
    echo "=== FORMATTED FINAL OUTPUT ===\n";
    echo $formatted . "\n\n";

    echo "=== RENDERED HTML PREVIEW ===\n";
    echo render_markdown($formatted) . "\n";
} else {
    echo "FAILED to parse sample\n";
}
