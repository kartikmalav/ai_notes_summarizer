<?php
/**
 * Test exam prep parsing and fallback logic
 */

require_once __DIR__ . '/../includes/gemini.php';
require_once __DIR__ . '/../includes/markdown.php';

function test_parse_exam_prep_sections($text) {
    if (empty($text)) return false;

    $patternA = '/###\s*(?:📌\s*)?Section A[^\n]*/i';
    $patternB = '/###\s*(?:💡\s*)?Section B[^\n]*/i';
    $patternC = '/###\s*(?:🔑\s*)?Section C[^\n]*/i';
    $patternD = '/###\s*(?:📑\s*)?Section D[^\n]*/i';
    $patternE = '/###\s*(?:📖\s*)?Section E[^\n]*/i';

    if (!preg_match($patternA, $text, $matchA, PREG_OFFSET_CAPTURE)) return false;
    if (!preg_match($patternB, $text, $matchB, PREG_OFFSET_CAPTURE)) return false;
    if (!preg_match($patternC, $text, $matchC, PREG_OFFSET_CAPTURE)) return false;
    if (!preg_match($patternD, $text, $matchD, PREG_OFFSET_CAPTURE)) return false;
    if (!preg_match($patternE, $text, $matchE, PREG_OFFSET_CAPTURE)) return false;

    $posA = $matchA[0][1] + strlen($matchA[0][0]);
    $posB = $matchB[0][1];
    $secAText = substr($text, $posA, $posB - $posA);

    $posBEnd = $matchB[0][1] + strlen($matchB[0][0]);
    $posC = $matchC[0][1];
    $secBText = substr($text, $posBEnd, $posC - $posBEnd);

    $posCEnd = $matchC[0][1] + strlen($matchC[0][0]);
    $posD = $matchD[0][1];
    $secCText = substr($text, $posCEnd, $posD - $posCEnd);

    $posDEnd = $matchD[0][1] + strlen($matchD[0][0]);
    $posE = $matchE[0][1];
    $secDText = substr($text, $posDEnd, $posE - $posDEnd);

    $posEEnd = $matchE[0][1] + strlen($matchE[0][0]);
    $secEText = substr($text, $posEEnd);

    $qA = parse_section_questions($secAText, 5);
    $qB = parse_section_questions($secBText, 3);
    $qC = parse_section_questions($secCText, 2);

    $notesD = trim(clean_markdown_symbols($secDText));
    $defsE = trim(clean_markdown_symbols($secEText));

    return [
        'valid' => ($qA['valid'] && $qB['valid'] && $qC['valid'] && strlen($notesD) > 20 && strlen($defsE) > 20),
        'secA' => $qA['questions'],
        'secB' => $qB['questions'],
        'secC' => $qC['questions'],
        'secD' => $notesD,
        'secE' => $defsE,
        'validA' => $qA['valid'],
        'validB' => $qB['valid'],
        'validC' => $qC['valid'],
        'validD' => strlen($notesD) > 20,
        'validE' => strlen($defsE) > 20,
    ];
}

$sampleText = <<<MD
### Section A: 2-Mark Short Answer Questions
1. What is an operating system?
2. Define kernel and its role.
3. What is a system call?
4. List two types of operating systems.
5. What is thrashing in virtual memory?

### Section B: 5-Mark Medium Answer Questions
1. Explain the differences between process and thread.
2. Describe paging with a neat diagram.
3. Discuss Round Robin scheduling algorithm.

### Section C: 10-Mark Long Answer Questions
1. Explain deadlock conditions and avoidance using Banker's Algorithm.
2. Discuss virtual memory management and page replacement algorithms in detail.

### Section D: Topic-wise Revision Notes
- **Process Management**: A process is a program in execution. It transitions through New, Ready, Running, Waiting, Terminated states.
- **Memory Management**: Swapping, paging, and segmentation provide virtual memory abstraction.
- **Deadlocks**: Mutual exclusion, hold and wait, no preemption, and circular wait are required for deadlock.

### Section E: Key Definitions and Important Concepts
- **Kernel**: The central component of an operating system managing system resources.
- **Paging**: A memory management scheme that eliminates the need for contiguous allocation.
- **Deadlock**: A situation where a set of processes are blocked because each is holding a resource and waiting for another.
MD;

$res = test_parse_exam_prep_sections($sampleText);
echo "Result valid: " . ($res['valid'] ? 'YES' : 'NO') . "\n";
echo "Sec A count: " . count($res['secA']) . "\n";
echo "Sec B count: " . count($res['secB']) . "\n";
echo "Sec C count: " . count($res['secC']) . "\n";
echo "Sec D length: " . strlen($res['secD']) . "\n";
echo "Sec E length: " . strlen($res['secE']) . "\n";
