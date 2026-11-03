<?php
/**
 * Test script for Important Questions parsing and section validation
 */

function parse_section_questions($sectionText, $expectedCount) {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $sectionText));
    $questions = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^(?:\d+[\.\)]|\-|\*)\s*(.+)$/u', $trimmed, $m)) {
            $q = trim($m[1]);
            // Remove any bold markdown prefix like "**Question 1:**"
            $q = preg_replace('/^\*\*Question\s*\d+\s*[:\.]?\*\*\s*/i', '', $q);
            $q = trim($q);
            if (strlen($q) > 10 && !preg_match('/^(Section|Part|Unit|Chapter|Note|Answer)\b/i', $q)) {
                $questions[] = $q;
            }
        }
    }

    return [
        'valid' => (count($questions) === $expectedCount),
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

// Test sample 1: Standard combined output without answers
$sample1 = <<<EOD
### Section A: 2-Mark Short Answer Questions
1. Define an operating system and state its two main objectives.
2. What is the difference between a program and a process?
3. List the five primary states of a process.
4. Define context switching in CPU scheduling.
5. What is a race condition in concurrent programming?

### Section B: 5-Mark Medium Answer Questions
1. Explain the Round Robin CPU scheduling algorithm with its advantages.
2. Describe the four necessary conditions for a deadlock to occur.
3. Differentiate between paging and segmentation memory management schemes.

### Section C: 10-Mark Long Answer Questions
1. Explain process synchronization mechanisms including semaphores, critical section problems, and mutex locks in detail.
2. Describe virtual memory management, demand paging, and page replacement policies with suitable illustrations.
EOD;

$res1 = split_and_validate_important_questions($sample1);
echo "Sample 1 Valid: " . ($res1 ? 'YES' : 'NO') . "\n";
if ($res1) {
    echo "Sec A count: " . count($res1['secA']) . "\n";
    echo "Sec B count: " . count($res1['secB']) . "\n";
    echo "Sec C count: " . count($res1['secC']) . "\n";
}

// Test sample 2: Incomplete output (truncated before Section C)
$sample2 = <<<EOD
### Section A: 2-Mark Short Answer Questions
1. Define an operating system and state its two main objectives.
2. What is the difference between a program and a process?
3. List the five primary states of a process.
4. Define context switching in CPU scheduling.
5. What is a race condition in concurrent programming?

### Section B: 5-Mark Medium Answer Questions
1. Explain the Round Robin CPU scheduling algorithm with its advantages.
2. Describe the four necessary conditions for a deadlock to occur.
3. Differentiate between paging and segmentation memory management schemes.
EOD;

$res2 = split_and_validate_important_questions($sample2);
echo "Sample 2 (Truncated) Valid: " . ($res2 ? 'YES' : 'NO (Correctly detected incomplete)') . "\n";
