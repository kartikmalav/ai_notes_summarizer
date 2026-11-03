<?php
/**
 * Test script for MCQ JSON parsing and validation
 */

function extract_json_from_response($text) {
    if (empty($text)) return null;
    $text = trim($text);
    // Remove markdown code fences if present
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
        $text = trim($matches[1]);
    }
    // Find outermost array brackets '[' and ']'
    $start = strpos($text, '[');
    $end = strrpos($text, ']');
    if ($start !== false && $end !== false && $end > $start) {
        $text = substr($text, $start, $end - $start + 1);
    }
    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : null;
}

function validate_and_normalize_mcqs($rawList) {
    if (!is_array($rawList) || count($rawList) < 10) {
        return ['valid' => false, 'error' => 'Expected at least 10 MCQs, got ' . (is_array($rawList) ? count($rawList) : 0), 'mcqs' => []];
    }

    $normalized = [];
    $seenQuestions = [];
    $letters = ['A', 'B', 'C', 'D'];

    foreach ($rawList as $index => $item) {
        if (!is_array($item)) continue;

        $question = trim($item['question'] ?? '');
        if (strlen($question) < 5) continue;

        // Duplicate check (normalize lower-case alphanumeric)
        $qKey = preg_replace('/[^a-z0-9]/', '', strtolower($question));
        if (isset($seenQuestions[$qKey])) continue;
        $seenQuestions[$qKey] = true;

        // Process options
        $rawOptions = $item['options'] ?? [];
        $options = [];

        if (is_array($rawOptions)) {
            // Case 1: Associative array with A, B, C, D
            if (isset($rawOptions['A']) || isset($rawOptions['B']) || isset($rawOptions['C']) || isset($rawOptions['D'])) {
                foreach ($letters as $let) {
                    $optVal = trim($rawOptions[$let] ?? $rawOptions[strtolower($let)] ?? '');
                    if ($optVal !== '') {
                        $options[$let] = $optVal;
                    }
                }
            } else {
                // Case 2: Indexed array [0, 1, 2, 3]
                $vals = array_values($rawOptions);
                for ($i = 0; $i < 4; $i++) {
                    if (isset($vals[$i]) && trim($vals[$i]) !== '') {
                        $options[$letters[$i]] = trim($vals[$i]);
                    }
                }
            }
        }

        // Must have exactly 4 options
        if (count($options) !== 4) continue;

        // Process correct answer
        $rawAns = trim((string)($item['correct_answer'] ?? $item['answer'] ?? ''));
        $correctAns = strtoupper(substr($rawAns, 0, 1));

        // If correct_answer was option text rather than letter, find matching letter
        if (!in_array($correctAns, $letters)) {
            foreach ($options as $let => $optText) {
                if (strcasecmp($optText, $rawAns) === 0) {
                    $correctAns = $let;
                    break;
                }
            }
        }

        if (!in_array($correctAns, $letters)) continue;

        $explanation = trim($item['explanation'] ?? 'Based on the provided document.');
        if (empty($explanation)) {
            $explanation = 'Correct answer is Option ' . $correctAns . '.';
        }

        $normalized[] = [
            'id' => count($normalized) + 1,
            'question' => $question,
            'options' => $options,
            'correct_answer' => $correctAns,
            'explanation' => $explanation
        ];

        if (count($normalized) === 10) break;
    }

    if (count($normalized) === 10) {
        return ['valid' => true, 'mcqs' => $normalized, 'error' => null];
    }

    return ['valid' => false, 'error' => 'Could only validate ' . count($normalized) . ' complete questions out of 10 required.', 'mcqs' => []];
}

// Test 1: Code fence JSON with exactly 10 questions
$mockData = [];
for ($i = 1; $i <= 10; $i++) {
    $mockData[] = [
        "id" => $i,
        "question" => "Sample Question $i about Operating Systems?",
        "options" => [
            "A" => "Option A for question $i",
            "B" => "Option B for question $i",
            "C" => "Option C for question $i",
            "D" => "Option D for question $i"
        ],
        "correct_answer" => ($i % 4 === 1 ? 'A' : ($i % 4 === 2 ? 'B' : ($i % 4 === 3 ? 'C' : 'D'))),
        "explanation" => "Explanation for question $i."
    ];
}
$mockResponse = "Here are the MCQs:\n```json\n" . json_encode($mockData, JSON_PRETTY_PRINT) . "\n```\nHope this helps!";

$extracted = extract_json_from_response($mockResponse);
$validation = validate_and_normalize_mcqs($extracted);

echo "Test 1 (Valid 10 MCQs wrapped in markdown):\n";
echo "Extracted: " . (is_array($extracted) ? 'YES (' . count($extracted) . ')' : 'NO') . "\n";
echo "Validated: " . ($validation['valid'] ? 'YES' : 'NO: ' . $validation['error']) . "\n";
if ($validation['valid']) {
    echo "Question 1: " . $validation['mcqs'][0]['question'] . "\n";
    echo "Options: " . implode(" | ", array_keys($validation['mcqs'][0]['options'])) . "\n";
    echo "Correct Answer: " . $validation['mcqs'][0]['correct_answer'] . "\n";
}

// Test 2: Incomplete / Malformed (only 5 questions)
$incomplete = array_slice($mockData, 0, 5);
$validation2 = validate_and_normalize_mcqs($incomplete);
echo "\nTest 2 (Incomplete 5 MCQs):\n";
echo "Validated: " . ($validation2['valid'] ? 'YES' : 'NO (Correctly rejected: ' . $validation2['error'] . ')') . "\n";

// Test 3: Indexed options array [0,1,2,3] and lowercase answer
$flexibleItem = [
    "question" => "Which scheduling algorithm is preemptive?",
    "options" => ["FCFS", "Round Robin", "SJF Non-preemptive", "Priority Non-preemptive"],
    "correct_answer" => "b",
    "explanation" => "Round Robin uses time quantum and is preemptive."
];
$mockFlexible = array_fill(0, 10, $flexibleItem);
// Make questions unique
for ($k = 0; $k < 10; $k++) {
    $mockFlexible[$k]['question'] = "Unique Question " . ($k + 1) . "?";
}
$validation3 = validate_and_normalize_mcqs($mockFlexible);
echo "\nTest 3 (Indexed options & lowercase answer):\n";
echo "Validated: " . ($validation3['valid'] ? 'YES' : 'NO: ' . $validation3['error']) . "\n";
if ($validation3['valid']) {
    echo "Normalized Answer: " . $validation3['mcqs'][0]['correct_answer'] . " -> " . $validation3['mcqs'][0]['options']['B'] . "\n";
}
