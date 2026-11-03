<?php
/**
 * Google Gemini API Client Module
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/markdown.php';

/**
 * List of officially supported Google Gemini models
 * 
 * @return array
 */
function get_supported_gemini_models() {
    return [
        'gemini-1.5-flash',
        'gemini-1.5-pro',
        'gemini-2.0-flash',
        'gemini-2.5-flash',
        'gemini-1.0-pro'
    ];
}

/**
 * Read and verify configured model name from environment/config.
 * Validates that model is supported before sending API requests.
 * 
 * @return string Validated model identifier
 */
function get_active_gemini_model() {
    $supported = get_supported_gemini_models();
    $configured = defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? trim(GEMINI_MODEL) : 'gemini-1.5-flash';

    if (strpos($configured, 'models/') === 0) {
        $configured = substr($configured, 7);
    }

    if (in_array($configured, $supported, true)) {
        return $configured;
    }

    error_log("Configured GEMINI_MODEL '{$configured}' is unsupported. Using default 'gemini-1.5-flash'.");
    return 'gemini-1.5-flash';
}

/**
 * Call Google Gemini API with a text prompt using configured model
 * 
 * @param string $prompt The prompt text to send to Gemini
 * @param int $maxTokens Maximum output tokens
 * @return array ['success' => bool, 'data' => string|null, 'error' => string|null, 'model' => string]
 */
function call_gemini_api($prompt, $maxTokens = 8192) {
    $apiKey = trim(GEMINI_API_KEY);
    $activeModel = get_active_gemini_model();

    // Validate API Key presence
    if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Gemini API key is not configured. Set GEMINI_API_KEY in the Apache/PHP environment and restart Apache.',
            'model' => $activeModel
        ];
    }

    // Validate prompt: must be a non-empty, non-whitespace string
    if (!is_string($prompt) || trim($prompt) === '') {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Cannot send Gemini API request: Prompt text is empty or invalid.',
            'model' => $activeModel
        ];
    }

    // Ensure prompt is valid UTF-8
    if (!mb_check_encoding($prompt, 'UTF-8')) {
        $prompt = mb_convert_encoding($prompt, 'UTF-8', 'UTF-8');
    }

    // REST API Endpoint using validated model name
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($activeModel) . ":generateContent?key=" . rawurlencode($apiKey);

    // Properly structured GenerateContentRequest with valid, non-empty contents
    $requestBody = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.3,
            "topK" => 40,
            "topP" => 0.95,
            "maxOutputTokens" => (int) $maxTokens
        ]
    ];

    // Encode JSON with safe invalid UTF-8 substitution and unescaped Unicode
    $payloadJson = json_encode(
        $requestBody,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($payloadJson === false || empty($payloadJson) || $payloadJson === '{}') {
        error_log("Gemini API Error: Failed to encode request JSON: " . json_last_error_msg());
        return [
            'success' => false,
            'data' => null,
            'error' => 'Failed to prepare Gemini request payload: ' . json_last_error_msg()
        ];
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    
    // cURL Timeout settings for network reliability
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // Connection timeout in seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);       // Total execution timeout in seconds

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $caBundle = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
    if (file_exists($caBundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
    }

    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Redact API key from any error messages to prevent accidental exposure
    if (!empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE') {
        $curlError = str_ireplace($apiKey, '[REDACTED_API_KEY]', (string) $curlError);
    }

    if ($curlErrno) {
        error_log("Gemini cURL Error ({$curlErrno}): " . $curlError);
        return [
            'success' => false,
            'data' => null,
            'error' => 'Network error connecting to Gemini API: ' . $curlError
        ];
    }

    // If model is temporarily experiencing a high-demand spike (503 / 429), retry once after 2 seconds
    if ($httpCode === 503 || $httpCode === 429) {
        error_log("Gemini API transient HTTP {$httpCode} encountered. Retrying in 2 seconds...");
        sleep(2);

        $chRetry = curl_init($endpoint);
        curl_setopt($chRetry, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chRetry, CURLOPT_POST, true);
        curl_setopt($chRetry, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($chRetry, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($chRetry, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($chRetry, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($chRetry, CURLOPT_SSL_VERIFYHOST, 2);
        if (file_exists($caBundle)) {
            curl_setopt($chRetry, CURLOPT_CAINFO, $caBundle);
        }

        $response = curl_exec($chRetry);
        $curlErrno = curl_errno($chRetry);
        $curlError = curl_error($chRetry);
        $httpCode = curl_getinfo($chRetry, CURLINFO_HTTP_CODE);
        curl_close($chRetry);

        if (!empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE') {
            $curlError = str_ireplace($apiKey, '[REDACTED_API_KEY]', (string) $curlError);
        }

        if ($curlErrno) {
            error_log("Gemini cURL Retry Error ({$curlErrno}): " . $curlError);
            return [
                'success' => false,
                'data' => null,
                'error' => 'Network error connecting to Gemini API: ' . $curlError
            ];
        }
    }

    $json = json_decode($response, true);

    if ($httpCode !== 200) {
        $apiErrorMsg = $json['error']['message'] ?? null;
        $apiErrorStatus = $json['error']['status'] ?? null;

        if ($apiErrorMsg) {
            $formattedError = "Gemini API Error (HTTP {$httpCode} - {$apiErrorStatus}): {$apiErrorMsg}";
        } else {
            $formattedError = "Gemini API Error (HTTP {$httpCode}): " . substr($response, 0, 300);
        }

        if (!empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE') {
            $formattedError = str_ireplace($apiKey, '[REDACTED_API_KEY]', $formattedError);
        }

        error_log("Gemini API Error: " . $formattedError);
        return [
            'success' => false,
            'data' => null,
            'error' => $formattedError
        ];
    }

    // Extract text and finish reason from Gemini response structure
    $candidate = $json['candidates'][0] ?? null;
    $finishReason = $candidate['finishReason'] ?? null;
    $generatedText = $candidate['content']['parts'][0]['text'] ?? null;

    if (empty($generatedText)) {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Gemini API returned an empty response. The input text might violate safety filters or contain no text.'
        ];
    }

    if ($finishReason === 'MAX_TOKENS') {
        error_log("Gemini API Warning: Output truncated due to MAX_TOKENS limit.");
        return [
            'success' => false,
            'data' => null,
            'finish_reason' => 'MAX_TOKENS',
            'error' => 'The generated response exceeded output limits and was truncated. Please try generating again.'
        ];
    }

    return [
        'success' => true,
        'data' => trim($generatedText),
        'error' => null
    ];
}

/**
 * Generate AI Short Notes from extracted document text
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array
 */
function generate_pdf_short_notes($pdfText, $subject = 'Computer Science') {
    // Sensible text length handling (limit to ~35,000 characters to fit token context)
    $maxLength = 35000;
    $isTruncated = false;

    if (strlen($pdfText) > $maxLength) {
        $pdfText = substr($pdfText, 0, $maxLength);
        $isTruncated = true;
    }

    if (strlen(trim($pdfText)) < 20) {
        return [
            'success' => false,
            'error' => 'The document contains insufficient printable text to generate short notes.'
        ];
    }

    $prompt = "You are an expert academic evaluator creating exam revision notes for subject '{$subject}'.\n\n";
    $prompt .= "STRICT GROUNDING & CONTENT RULES:\n";
    $prompt .= "1. Use ONLY facts, definitions, and concepts explicitly stated in the source text below. Do NOT invent, extrapolate, or add outside knowledge.\n";
    $prompt .= "2. Preserve exact document terminology, acronyms, and technical definitions as presented in the source.\n";
    $prompt .= "3. Never make stronger claims or broader generalizations than the source text explicitly supports.\n";
    $prompt .= "4. Avoid generic commentary, repetitive filler, or unnecessary introductory/concluding text.\n";
    $prompt .= "5. If the source text contains comparisons between concepts, present them using clean Markdown comparison tables.\n\n";
    $prompt .= "FORMATTING & CLEANLINESS RULES:\n";
    $prompt .= "- Do NOT use any decorative symbols, emojis, or ornamental icons (such as ★, ◆, ●, ►, ➤, ✓, 📌, 💡, 🔑, etc.).\n";
    $prompt .= "- Use clean, simple Markdown formatting only:\n";
    $prompt .= "  * Headings: plain Markdown headings (### Section Name) without decorative prefixes or emojis.\n";
    $prompt .= "  * Lists: standard hyphens (- item) or numbered lists (1. item) only.\n";
    $prompt .= "  * Key terms: bold text (**term**) only when useful.\n";
    $prompt .= "- Preserve all technical notation, mathematical formulas, operators, and code syntax.\n";
    $prompt .= "- Do NOT insert decorative markers before bullet items or headers.\n\n";
    $prompt .= "Format your response using Markdown with the following exact sections:\n\n";
    $prompt .= "### Executive Summary\n";
    $prompt .= "(A concise 2-3 sentence overview strictly summarizing the core subject matter of the uploaded document)\n\n";
    $prompt .= "### Key Definitions & Terminology\n";
    $prompt .= "(Bullet points using standard hyphens with **bold terms** followed by their precise definitions as stated in the document)\n\n";
    $prompt .= "### Core Topics & Breakdown\n";
    $prompt .= "(Organized by major topics covered in the document, using subheadings and concise bullet points)\n\n";
    $prompt .= "### Rules, Formulas & Algorithms\n";
    $prompt .= "(List any explicit rules, formulas, algorithms, or processes found in the text; if none exist, summarize key operational rules from the text)\n\n";
    $prompt .= "### Quick Exam Revision Checklist\n";
    $prompt .= "(5-8 high-priority, exam-oriented bullet points covering major topics across the document)\n\n";

    if ($isTruncated) {
        $prompt .= "\n[Note: Source text was truncated to fit context limits]\n";
    }

    $prompt .= "\nProvided Document Text:\n---\n" . $pdfText . "\n---";

    $res = call_gemini_api($prompt);

    if ($res['success'] && !empty($res['data'])) {
        $res['data'] = clean_markdown_symbols($res['data']);
    }

    return $res;
}

/**
 * Parse and extract questions from a section's markdown text
 * 
 * @param string $sectionText Raw text of the section
 * @param int $expectedCount Minimum/expected question count
 * @return array ['valid' => bool, 'count' => int, 'questions' => array]
 */
function parse_section_questions($sectionText, $expectedCount) {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $sectionText));
    $questions = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^(?:\*{0,2}(?:Q\s*)?\d+[\.\)]\*{0,2}|\-|\*)\s*(.+)$/iu', $trimmed, $m)) {
            $q = trim($m[1]);
            // Only strip redundant prefixes with question numbers like "Question 1:" or "Q1."
            $q = preg_replace('/^\*{0,2}(?:Question|Q)\s*\d+[:\.]?\*{0,2}\s*/iu', '', $q);
            $q = trim($q);
            if (strlen($q) >= 6 && !preg_match('/^(Section|Part|Unit|Chapter|Note|Answer)\b/i', $q)) {
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

/**
 * Split combined Important Questions response and validate that all 3 sections are complete
 * 
 * @param string $text Markdown text of all 3 sections
 * @return array|false ['valid' => bool, 'secA' => array, 'secB' => array, 'secC' => array]
 */
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

/**
 * Standardize and format validated question lists into clean markdown
 */
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

/**
 * Generate questions for a single section as a fallback
 */
function generate_single_section_questions($pdfText, $subject, $sectionLetter, $marks, $count) {
    $prompt = "You are an expert college examiner preparing exam questions for subject '{$subject}'.\n\n";
    $prompt .= "Based ONLY on the provided document text, generate EXACTLY {$count} concise exam questions for Section {$sectionLetter} ({$marks} Marks each).\n\n";
    $prompt .= "STRICT RULES:\n";
    $prompt .= "- Generate ONLY {$count} concise questions. NO answers, NO explanations, NO solutions.\n";
    $prompt .= "- Do NOT include any introduction, conclusion, or decorative symbols.\n";
    $prompt .= "- Format strictly as a numbered list from 1 to {$count}.\n";
    $prompt .= "- Base questions strictly on the provided document text without outside knowledge.\n\n";
    $prompt .= "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $res = call_gemini_api($prompt, 4096);
    if (!$res['success'] || empty($res['data'])) {
        return false;
    }

    $clean = clean_markdown_symbols($res['data']);
    $parsed = parse_section_questions($clean, $count);
    if ($parsed['valid']) {
        return $parsed['questions'];
    }

    return false;
}

/**
 * Generate Exam-Oriented Important Questions (2-Mark, 5-Mark, 10-Mark) from document text
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array
 */
function generate_pdf_important_questions($pdfText, $subject = 'Computer Science') {
    // Truncate text to 25,000 characters for optimal processing speed & response latency
    $maxLength = 25000;
    $isTruncated = false;

    if (strlen($pdfText) > $maxLength) {
        $pdfText = substr($pdfText, 0, $maxLength);
        $isTruncated = true;
    }

    if (strlen(trim($pdfText)) < 20) {
        return [
            'success' => false,
            'error' => 'The document contains insufficient printable text to generate questions.'
        ];
    }

    // Step 1: Combined generation with strict QUESTIONS ONLY instructions (no answers)
    $prompt = "You are an expert academic examiner preparing an exam question bank for subject '{$subject}'.\n\n";
    $prompt .= "STRICT GROUNDING & CONTENT RULES:\n";
    $prompt .= "1. Base ALL questions strictly on the provided document text. Do NOT use outside knowledge.\n";
    $prompt .= "2. Preserve the document's original terminology and technical phrasing.\n";
    $prompt .= "3. Distribute questions evenly across major topics in the document.\n";
    $prompt .= "4. ABSOLUTE ZERO FILLER: Generate questions ONLY. Do NOT include ANY answers, explanations, solutions, introductions, conclusions, or decorative symbols.\n\n";
    $prompt .= "Generate an exam question paper structured strictly into the following 3 sections:\n\n";
    $prompt .= "### Section A: 2-Mark Short Answer Questions\n";
    $prompt .= "Generate EXACTLY 5 concise questions (numbered 1 to 5). Questions only, NO answers.\n\n";
    $prompt .= "### Section B: 5-Mark Medium Answer Questions\n";
    $prompt .= "Generate EXACTLY 3 medium questions (numbered 1 to 3). Questions only, NO answers.\n\n";
    $prompt .= "### Section C: 10-Mark Long Answer Questions\n";
    $prompt .= "Generate EXACTLY 2 comprehensive questions (numbered 1 to 2). Questions only, NO answers.\n\n";
    $prompt .= "STRICT OUTPUT RULES:\n";
    $prompt .= "- Output ONLY the three sections above.\n";
    $prompt .= "- Number each question clearly (1., 2., 3., etc.).\n";
    $prompt .= "- Do NOT write answers or explanations for any question.\n";
    $prompt .= "- Do NOT add an introduction or conclusion.\n";

    if ($isTruncated) {
        $prompt .= "\n[Note: Source text was truncated to fit context limits]\n";
    }

    $prompt .= "\nProvided Document Text:\n---\n" . $pdfText . "\n---";

    // Call API with 8192 tokens (ample room for questions-only output)
    $res = call_gemini_api($prompt, 8192);

    if ($res['success'] && !empty($res['data'])) {
        $clean = clean_markdown_symbols($res['data']);
        $validated = split_and_validate_important_questions($clean);

        if ($validated && $validated['valid']) {
            $finalContent = format_final_important_questions(
                $validated['secA'],
                $validated['secB'],
                $validated['secC']
            );

            return [
                'success' => true,
                'data' => $finalContent,
                'error' => null
            ];
        }
    }

    // Step 2: Fallback - Generate Section A, Section B, and Section C separately
    error_log("Important Questions combined generation incomplete/truncated. Attempting robust per-section generation fallback.");

    $secAQuestions = generate_single_section_questions($pdfText, $subject, 'A', 2, 5);
    $secBQuestions = generate_single_section_questions($pdfText, $subject, 'B', 5, 3);
    $secCQuestions = generate_single_section_questions($pdfText, $subject, 'C', 10, 2);

    if ($secAQuestions && $secBQuestions && $secCQuestions) {
        $finalContent = format_final_important_questions($secAQuestions, $secBQuestions, $secCQuestions);
        return [
            'success' => true,
            'data' => $finalContent,
            'error' => null
        ];
    }

    return [
        'success' => false,
        'data' => null,
        'error' => 'The generated response was incomplete or truncated. Failed to generate all three complete question sections. Please try again.'
    ];
}

/**
 * Extract JSON array from Gemini response text
 * 
 * @param string $text
 * @return array|null
 */
function extract_mcq_json($text) {
    if (empty($text)) {
        return null;
    }
    $text = trim($text);

    // Strip markdown code fences if present
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

/**
 * Strictly validate and normalize a list of raw MCQs
 * Guarantees exactly 10 valid, unique questions with 4 options each
 * 
 * @param array $rawList
 * @return array ['valid' => bool, 'mcqs' => array, 'error' => string|null]
 */
function validate_and_normalize_mcqs($rawList) {
    if (!is_array($rawList) || count($rawList) < 10) {
        $count = is_array($rawList) ? count($rawList) : 0;
        return [
            'valid' => false,
            'mcqs' => [],
            'error' => "Gemini generated {$count} questions, but exactly 10 valid questions are required."
        ];
    }

    $normalized = [];
    $seenQuestions = [];
    $letters = ['A', 'B', 'C', 'D'];

    foreach ($rawList as $item) {
        if (!is_array($item)) {
            continue;
        }

        $question = trim($item['question'] ?? '');
        if (strlen($question) < 5) {
            continue;
        }

        // Deduplication key
        $qKey = preg_replace('/[^a-z0-9]/', '', strtolower($question));
        if (isset($seenQuestions[$qKey])) {
            continue;
        }
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

        // Must have exactly 4 non-empty options
        if (count($options) !== 4) {
            continue;
        }

        // Process correct answer
        $rawAns = trim((string)($item['correct_answer'] ?? $item['answer'] ?? ''));
        $firstLetter = strtoupper(substr($rawAns, 0, 1));

        $correctAns = null;
        if (in_array($firstLetter, $letters)) {
            $correctAns = $firstLetter;
        } else {
            // If the model returned full option text instead of letter, match it
            foreach ($options as $let => $optText) {
                if (strcasecmp($optText, $rawAns) === 0) {
                    $correctAns = $let;
                    break;
                }
            }
        }

        if (!$correctAns || !in_array($correctAns, $letters)) {
            continue;
        }

        $explanation = trim($item['explanation'] ?? '');
        if (empty($explanation)) {
            $explanation = "Option {$correctAns} is the correct answer according to the document text.";
        }

        $normalized[] = [
            'id' => count($normalized) + 1,
            'question' => $question,
            'options' => $options,
            'correct_answer' => $correctAns,
            'explanation' => $explanation
        ];

        if (count($normalized) === 10) {
            break;
        }
    }

    if (count($normalized) < 10) {
        $validCount = count($normalized);
        return [
            'valid' => false,
            'mcqs' => [],
            'error' => "Only {$validCount} questions satisfied all strict quality requirements (10 required)."
        ];
    }

    return [
        'valid' => true,
        'mcqs' => $normalized,
        'error' => null
    ];
}

/**
 * Generate 10 Multiple Choice Questions (MCQs) grounded in document text
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'mcqs' => array|null, 'error' => string|null]
 */
function generate_document_mcqs($pdfText, $subject = 'General') {
    $maxLength = 35000;
    if (strlen($pdfText) > $maxLength) {
        $pdfText = substr($pdfText, 0, $maxLength);
    }

    if (strlen(trim($pdfText)) < 20) {
        return [
            'success' => false,
            'data' => null,
            'mcqs' => null,
            'error' => 'The document contains insufficient printable text to generate MCQs.'
        ];
    }

    $prompt = "You are an expert academic examiner and professor for the subject: {$subject}.

Generate EXACTLY 10 Multiple Choice Questions (MCQs) strictly and solely grounded in the provided document text.

MANDATORY GROUNDING RULES:
1. Every single question, option, and explanation MUST be based strictly and explicitly on the facts in the document text.
2. Do NOT invent, assume, extrapolate, or add outside facts not found in the text.
3. Each question must have EXACTLY four options: A, B, C, and D.
4. Exactly ONE option must be clearly and unambiguously correct according to the document text.
5. The other three options must be plausible but definitively incorrect distractors based on the text.
6. Avoid duplicate questions. Distribute the 10 questions evenly across the key topics and sections of the document.
7. Keep question wording clear, professional, and exam-oriented.
8. Each explanation must be concise (1-2 sentences) directly stating the reason or fact from the text.

OUTPUT FORMAT:
Output ONLY a valid, raw JSON array of 10 objects. Do NOT use markdown code blocks (such as ```json or ```). Do not include any introductory or concluding text.

JSON Schema:
[
  {
    \"question\": \"What is ...?\",
    \"options\": {
      \"A\": \"Option A text\",
      \"B\": \"Option B text\",
      \"C\": \"Option C text\",
      \"D\": \"Option D text\"
    },
    \"correct_answer\": \"A\",
    \"explanation\": \"Brief factual reason why this is correct based on the text.\"
  }
]

DOCUMENT TEXT:
\"\"\"
{$pdfText}
\"\"\"";

    $res = call_gemini_api($prompt, 8192);

    if (!$res['success']) {
        return [
            'success' => false,
            'data' => null,
            'mcqs' => null,
            'error' => $res['error'] ?? 'Failed to communicate with Gemini API.'
        ];
    }

    $rawJson = extract_mcq_json($res['data']);

    if (!$rawJson) {
        // Fallback retry with even more explicit JSON instruction
        $retryPrompt = "Convert the following information into a strict JSON array of 10 MCQs conforming to the requested schema. Output NOTHING except the raw JSON array:\n\n" . $res['data'];
        $retryRes = call_gemini_api($retryPrompt, 8192);
        if ($retryRes['success']) {
            $rawJson = extract_mcq_json($retryRes['data']);
        }
    }

    if (!$rawJson) {
        return [
            'success' => false,
            'data' => null,
            'mcqs' => null,
            'error' => 'Gemini API returned a response that could not be parsed as valid JSON. Please try generating again.'
        ];
    }

    $validated = validate_and_normalize_mcqs($rawJson);

    if (!$validated['valid']) {
        return [
            'success' => false,
            'data' => null,
            'mcqs' => null,
            'error' => $validated['error']
        ];
    }

    $jsonString = json_encode($validated['mcqs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return [
        'success' => true,
        'data' => $jsonString,
        'mcqs' => $validated['mcqs'],
        'error' => null
    ];
}

/**
 * Extract relevant document context for Q&A, handling long documents safely
 * 
 * @param string $fullText Complete document text
 * @param string $query User's question
 * @param int $maxChars Character limit for context
 * @return string Filtered and structured context
 */
function extract_relevant_document_context($fullText, $query, $maxChars = 28000) {
    if (!mb_check_encoding($fullText, 'UTF-8')) {
        $fullText = mb_convert_encoding($fullText, 'UTF-8', 'UTF-8');
    }
    $fullText = trim($fullText);
    $textLength = mb_strlen($fullText, 'UTF-8');

    // If within safe token limits, return the entire text
    if ($textLength <= $maxChars) {
        return $fullText;
    }

    // Always reserve the document opening (up to 3,500 chars) for high-level structure/overview
    $introLength = min(3500, (int)($maxChars * 0.15));
    $introText = mb_substr($fullText, 0, $introLength, 'UTF-8');
    $remainingBudget = $maxChars - $introLength;

    // Remaining body
    $bodyText = mb_substr($fullText, $introLength, null, 'UTF-8');

    // Stop words to exclude from keyword search
    $stopWords = [
        'what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how',
        'this', 'that', 'these', 'those', 'is', 'are', 'was', 'were', 'be', 'been',
        'being', 'have', 'has', 'had', 'having', 'do', 'does', 'did', 'doing',
        'a', 'an', 'the', 'and', 'but', 'if', 'or', 'because', 'as', 'until',
        'while', 'of', 'at', 'by', 'for', 'with', 'about', 'against', 'between',
        'into', 'through', 'during', 'before', 'after', 'above', 'below', 'to',
        'from', 'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under', 'again',
        'further', 'then', 'once', 'here', 'there', 'all', 'any', 'both', 'each',
        'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only',
        'own', 'same', 'so', 'than', 'too', 'very', 'can', 'will', 'just', 'should',
        'now', 'tell', 'explain', 'describe', 'give', 'detail', 'summarize', 'find',
        'show', 'please', 'document', 'notes', 'mean', 'meaning', 'difference'
    ];

    // Extract query keywords (alphanumeric, >= 3 chars, not in stopWords)
    preg_match_all('/\b[a-zA-Z0-9]{3,}\b/u', mb_strtolower($query, 'UTF-8'), $matches);
    $rawTokens = $matches[0] ?? [];
    $keywords = array_values(array_filter($rawTokens, function($t) use ($stopWords) {
        return !in_array($t, $stopWords);
    }));

    // Split body into overlapping paragraphs / chunks (~1,800 chars each)
    $chunkSize = 1800;
    $overlap = 200;
    $step = $chunkSize - $overlap;
    $chunks = [];
    $bodyLen = mb_strlen($bodyText, 'UTF-8');

    for ($offset = 0; $offset < $bodyLen; $offset += $step) {
        $chunk = mb_substr($bodyText, $offset, $chunkSize, 'UTF-8');
        if (mb_strlen(trim($chunk), 'UTF-8') < 50) continue;

        // Calculate relevance score
        $score = 0;
        $chunkLower = mb_strtolower($chunk, 'UTF-8');

        if (!empty($keywords)) {
            $distinctMatches = 0;
            foreach ($keywords as $kw) {
                $count = mb_substr_count($chunkLower, $kw, 'UTF-8');
                if ($count > 0) {
                    $score += ($count * 3);
                    $distinctMatches++;
                }
            }
            // Reward chunks matching multiple distinct query keywords
            $score += ($distinctMatches * 5);
        } else {
            // If no distinct keywords found, distribute evenly
            $score = 1;
        }

        $chunks[] = [
            'offset' => $offset,
            'text' => $chunk,
            'score' => $score
        ];
    }

    if (empty($chunks)) {
        return $introText;
    }

    // Sort chunks by score descending to pick the best matching ones
    usort($chunks, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    // Pick top chunks within remaining budget
    $selectedChunks = [];
    $accumulated = 0;

    foreach ($chunks as $c) {
        $len = mb_strlen($c['text'], 'UTF-8');
        if ($accumulated + $len <= $remainingBudget) {
            $selectedChunks[] = $c;
            $accumulated += $len;
        }
        if ($accumulated >= $remainingBudget) break;
    }

    // Sort selected chunks back into original document sequence
    usort($selectedChunks, function($a, $b) {
        return $a['offset'] <=> $b['offset'];
    });

    $combined = $introText;
    $combined .= "\n\n[... Additional relevant document sections ...]\n\n";
    foreach ($selectedChunks as $sc) {
        $combined .= "\n" . trim($sc['text']) . "\n";
    }

    return $combined;
}

/**
 * Answer a question strictly grounded in the extracted document text
 * 
 * @param string $documentText Extracted text from uploaded document
 * @param string $documentName Original filename
 * @param string $subject Course or topic subject
 * @param string $question User question
 * @param array $conversationHistory Previous chat turns [['role' => 'user'|'assistant', 'content' => '...']]
 * @return array ['success' => bool, 'answer' => string|null, 'error' => string|null]
 */
function answer_document_question($documentText, $documentName, $subject = 'General', $question = '', $conversationHistory = []) {
    $question = trim($question);
    if (empty($question)) {
        return [
            'success' => false,
            'answer' => null,
            'error' => 'Please provide a valid question.'
        ];
    }

    if (empty(trim($documentText)) || strlen(trim($documentText)) < 20) {
        return [
            'success' => false,
            'answer' => null,
            'error' => 'The selected document does not contain sufficient text to answer questions.'
        ];
    }

    // Ensure inputs are valid UTF-8
    if (!mb_check_encoding($documentText, 'UTF-8')) {
        $documentText = mb_convert_encoding($documentText, 'UTF-8', 'UTF-8');
    }
    if (!mb_check_encoding($question, 'UTF-8')) {
        $question = mb_convert_encoding($question, 'UTF-8', 'UTF-8');
    }

    // 1. Prepare relevant document excerpts safely
    $contextText = extract_relevant_document_context($documentText, $question, 28000);

    // 2. Format conversation history (up to last 6 messages) for follow-up questions
    $recentHistory = array_slice($conversationHistory, -6);
    $historyString = '';
    if (!empty($recentHistory)) {
        $historyString = "RECENT CONVERSATION HISTORY:\n";
        foreach ($recentHistory as $msg) {
            $roleLabel = ($msg['role'] === 'user') ? 'Student' : 'Assistant';
            $contentClean = trim($msg['content'] ?? '');
            if (!mb_check_encoding($contentClean, 'UTF-8')) {
                $contentClean = mb_convert_encoding($contentClean, 'UTF-8', 'UTF-8');
            }
            $historyString .= "{$roleLabel}: " . $contentClean . "\n\n";
        }
        $historyString = trim($historyString);
    }

    // 3. Assemble grounded prompt
    $prompt = "You are a dedicated AI study assistant for the document: \"{$documentName}\" (Subject: {$subject}).

Your objective is to answer the student's question strictly and truthfully using ONLY the provided document excerpts.

STRICT GROUNDING RULES:
1. Base your answer ONLY on the explicit facts, definitions, data, and explanations contained within the document excerpts below.
2. If the question asks about something NOT mentioned in the document excerpts, state clearly:
   \"This document does not contain information about [topic].\"
   Do not speculate, invent, or bring in unsupported facts.
3. Distinguish document facts:
   - Provide the document facts clearly and directly.
   - If conceptual academic clarification is helpful to explain a document term, clearly label it:
     \"Note / Additional Context: [explanation]\"
     and ensure it does not contradict the document.
4. Maintain continuity with the prior conversation history when resolving pronouns (such as \"it\", \"they\", \"the second point\") or follow-up questions.
5. Formatting & Presentation:
   - Output clean, valid Markdown without decorative symbols or emojis.
   - Use standard headings (### or ####) for topic sections when organizing detailed answers.
   - Use standard markdown bullet points (- or *) or numbered lists (1., 2.) for structured points, steps, and comparisons.
   - Use bold for key terms and inline code `code` or code blocks for technical syntax, formulas, and algorithms.
   - Avoid excessive blank lines, repeated headers, or decorative divider lines (such as excessive '---'). Use horizontal rules only when genuinely separating major logical sections.
   - Do NOT use decorative symbols or ornamental bullets (such as ★, ◆, ●, ➔, ✓).
   - Keep answers readable, informative, and appropriately concise. If the user asks for a detailed breakdown or multiple steps, provide complete, well-organized explanations.

DOCUMENT EXCERPTS:
\"\"\"
{$contextText}
\"\"\"";

    if (!empty($historyString)) {
        $prompt .= "\n\n" . $historyString;
    }

    $prompt .= "\n\nCURRENT STUDENT QUESTION:\n{$question}\n\nASSISTANT ANSWER:";

    // Validate prompt is non-empty before calling API
    if (empty(trim($prompt))) {
        return [
            'success' => false,
            'answer' => null,
            'error' => 'Failed to assemble valid prompt for question answering.'
        ];
    }

    // 4. Call Gemini API
    $res = call_gemini_api($prompt, 4096);

    if (!$res['success']) {
        return [
            'success' => false,
            'answer' => null,
            'error' => $res['error'] ?? 'Failed to communicate with the Gemini AI service.'
        ];
    }

    $answer = trim($res['data']);
    $cleanAnswer = clean_markdown_symbols($answer);

    return [
        'success' => true,
        'answer' => $cleanAnswer,
        'error' => null
    ];
}

/**
 * Parse combined Exam Preparation response into its 5 distinct sections
 * 
 * @param string $text Markdown text of all 5 sections
 * @return array
 */
function parse_exam_prep_sections($text) {
    if (empty($text)) {
        return [
            'valid' => false,
            'secA' => [],
            'secB' => [],
            'secC' => [],
            'secD' => '',
            'secE' => '',
            'validA' => false,
            'validB' => false,
            'validC' => false,
            'validD' => false,
            'validE' => false
        ];
    }

    $patternA = '/###\s*(?:Section\s*A|Part\s*A|2[\s\-]*Mark)[^\n]*/i';
    $patternB = '/###\s*(?:Section\s*B|Part\s*B|5[\s\-]*Mark)[^\n]*/i';
    $patternC = '/###\s*(?:Section\s*C|Part\s*C|10[\s\-]*Mark)[^\n]*/i';
    $patternD = '/###\s*(?:Section\s*D|Topic[\s\-]*wise|Revision\s*Notes)[^\n]*/i';
    $patternE = '/###\s*(?:Section\s*E|Key\s*Definitions|Core\s*Concepts)[^\n]*/i';

    $hasA = preg_match($patternA, $text, $matchA, PREG_OFFSET_CAPTURE);
    $hasB = preg_match($patternB, $text, $matchB, PREG_OFFSET_CAPTURE);
    $hasC = preg_match($patternC, $text, $matchC, PREG_OFFSET_CAPTURE);
    $hasD = preg_match($patternD, $text, $matchD, PREG_OFFSET_CAPTURE);
    $hasE = preg_match($patternE, $text, $matchE, PREG_OFFSET_CAPTURE);

    $secAText = '';
    $secBText = '';
    $secCText = '';
    $secDText = '';
    $secEText = '';

    if ($hasA && $hasB) {
        $posA = $matchA[0][1] + strlen($matchA[0][0]);
        $secAText = substr($text, $posA, $matchB[0][1] - $posA);
    }
    if ($hasB && $hasC) {
        $posB = $matchB[0][1] + strlen($matchB[0][0]);
        $secBText = substr($text, $posB, $matchC[0][1] - $posB);
    }
    if ($hasC && $hasD) {
        $posC = $matchC[0][1] + strlen($matchC[0][0]);
        $secCText = substr($text, $posC, $matchD[0][1] - $posC);
    }
    if ($hasD && $hasE) {
        $posD = $matchD[0][1] + strlen($matchD[0][0]);
        $secDText = substr($text, $posD, $matchE[0][1] - $posD);
    }
    if ($hasE) {
        $posE = $matchE[0][1] + strlen($matchE[0][0]);
        $secEText = substr($text, $posE);
    }

    $qA = parse_section_questions($secAText, 5);
    $qB = parse_section_questions($secBText, 3);
    $qC = parse_section_questions($secCText, 2);

    $notesD = trim(clean_markdown_symbols($secDText));
    $defsE = trim(clean_markdown_symbols($secEText));

    $validA = $qA['valid'];
    $validB = $qB['valid'];
    $validC = $qC['valid'];
    $validD = (strlen($notesD) >= 30);
    $validE = (strlen($defsE) >= 30);

    return [
        'valid' => ($validA && $validB && $validC && $validD && $validE),
        'secA' => $qA['questions'],
        'secB' => $qB['questions'],
        'secC' => $qC['questions'],
        'secD' => $notesD,
        'secE' => $defsE,
        'validA' => $validA,
        'validB' => $validB,
        'validC' => $validC,
        'validD' => $validD,
        'validE' => $validE,
    ];
}

/**
 * Fallback generator for Section D: Topic-wise revision notes
 */
function generate_exam_prep_section_notes($pdfText, $subject) {
    $prompt = "You are an expert college professor creating high-yield topic-wise revision notes for subject '{$subject}'.\n\n";
    $prompt .= "Based ONLY on the provided document text, create clear, comprehensive topic-wise revision notes.\n\n";
    $prompt .= "STRICT RULES:\n";
    $prompt .= "- Organize by the major topics in the document.\n";
    $prompt .= "- Use bullet points (- ) and bold key terms (**term**).\n";
    $prompt .= "- No introductory text, no conclusions, no decorative symbols or emojis.\n";
    $prompt .= "- Base notes strictly on the provided document text.\n\n";
    $prompt .= "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $res = call_gemini_api($prompt, 4096);
    if ($res['success'] && !empty($res['data'])) {
        return clean_markdown_symbols($res['data']);
    }
    return '';
}

/**
 * Fallback generator for Section E: Key definitions and important concepts
 */
function generate_exam_prep_key_definitions($pdfText, $subject) {
    $prompt = "You are an expert academic examiner compiling essential definitions and core concepts for subject '{$subject}'.\n\n";
    $prompt .= "Based ONLY on the provided document text, extract the key definitions and core concepts.\n\n";
    $prompt .= "STRICT RULES:\n";
    $prompt .= "- Format each entry strictly as: - **Term**: Clear, concise definition.\n";
    $prompt .= "- Include 5 to 10 key terms and core concepts defined in the text.\n";
    $prompt .= "- No introductory text, no conclusions, no decorative symbols or emojis.\n";
    $prompt .= "- Base definitions strictly on the provided document text.\n\n";
    $prompt .= "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $res = call_gemini_api($prompt, 4096);
    if ($res['success'] && !empty($res['data'])) {
        return clean_markdown_symbols($res['data']);
    }
    return '';
}

/**
 * Format all 5 sections of Exam Preparation into unified clean markdown
 */
function format_exam_prep_markdown(array $secA, array $secB, array $secC, string $revisionNotes, string $keyDefinitions) {
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

    $out .= "\n### Section D: Topic-wise Revision Notes\n";
    $out .= trim($revisionNotes) . "\n";

    $out .= "\n### Section E: Key Definitions and Important Concepts\n";
    $out .= trim($keyDefinitions) . "\n";

    return trim($out);
}

/**
 * Generate comprehensive Exam Preparation material (Sections A, B, C, D, E)
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'exam_prep' => array|null, 'error' => string|null]
 */
function generate_exam_preparation_material($pdfText, $subject = 'General') {
    $maxLength = 30000;
    $isTruncated = false;

    if (strlen($pdfText) > $maxLength) {
        $pdfText = substr($pdfText, 0, $maxLength);
        $isTruncated = true;
    }

    if (strlen(trim($pdfText)) < 20) {
        return [
            'success' => false,
            'data' => null,
            'exam_prep' => null,
            'error' => 'The document contains insufficient printable text to generate exam preparation material.'
        ];
    }

    $prompt = "You are an expert academic professor and exam board examiner for subject: '{$subject}'.\n\n";
    $prompt .= "Generate a comprehensive, high-yield Exam Preparation Kit strictly grounded in the document text provided below.\n\n";
    $prompt .= "MANDATORY STRUCTURE - GENERATE ALL 5 SECTIONS:\n\n";
    $prompt .= "### Section A: 2-Mark Short Answer Questions\n";
    $prompt .= "Generate EXACTLY 5 concise questions (numbered 1 to 5). Questions only, NO answers.\n\n";
    $prompt .= "### Section B: 5-Mark Medium Answer Questions\n";
    $prompt .= "Generate EXACTLY 3 medium conceptual/analytical questions (numbered 1 to 3). Questions only, NO answers.\n\n";
    $prompt .= "### Section C: 10-Mark Long Answer Questions\n";
    $prompt .= "Generate EXACTLY 2 comprehensive questions (numbered 1 to 2). Questions only, NO answers.\n\n";
    $prompt .= "### Section D: Topic-wise Revision Notes\n";
    $prompt .= "Provide bulleted revision notes organized under major topic headings from the document. Highlight key points, mechanisms, and comparisons with clean Markdown tables if helpful.\n\n";
    $prompt .= "### Section E: Key Definitions and Important Concepts\n";
    $prompt .= "Provide 5 to 10 bulleted definitions in the format '- **Term**: Definition' for the essential terms and core concepts introduced in the text.\n\n";
    $prompt .= "STRICT GROUNDING & FORMATTING RULES:\n";
    $prompt .= "1. Base ALL content solely and strictly on the provided document text. Do NOT use outside information.\n";
    $prompt .= "2. Preserve the document's original technical terminology and exact phrasing.\n";
    $prompt .= "3. ZERO FILLER: Do NOT include any introductions, conclusions, or decorative symbols (such as ★, ◆, ●, ➔, ✓).\n";
    $prompt .= "4. Strictly follow the section headers specified above.\n\n";
    $prompt .= "DOCUMENT TEXT:\n---\n" . $pdfText . "\n---";

    $res = call_gemini_api($prompt, 8192);

    $secA = [];
    $secB = [];
    $secC = [];
    $secD = '';
    $secE = '';

    if ($res['success'] && !empty($res['data'])) {
        $clean = clean_markdown_symbols($res['data']);
        $parsed = parse_exam_prep_sections($clean);

        if ($parsed['validA']) $secA = $parsed['secA'];
        if ($parsed['validB']) $secB = $parsed['secB'];
        if ($parsed['validC']) $secC = $parsed['secC'];
        if ($parsed['validD']) $secD = $parsed['secD'];
        if ($parsed['validE']) $secE = $parsed['secE'];
    }

    // Fallback for Section A (5 questions of 2 marks)
    if (count($secA) < 5) {
        $fbA = generate_single_section_questions($pdfText, $subject, 'A', 2, 5);
        if ($fbA && count($fbA) === 5) {
            $secA = $fbA;
        }
    }

    // Fallback for Section B (3 questions of 5 marks)
    if (count($secB) < 3) {
        $fbB = generate_single_section_questions($pdfText, $subject, 'B', 5, 3);
        if ($fbB && count($fbB) === 3) {
            $secB = $fbB;
        }
    }

    // Fallback for Section C (2 questions of 10 marks)
    if (count($secC) < 2) {
        $fbC = generate_single_section_questions($pdfText, $subject, 'C', 10, 2);
        if ($fbC && count($fbC) === 2) {
            $secC = $fbC;
        }
    }

    // Fallback for Section D (Topic-wise revision notes)
    if (empty($secD) || strlen($secD) < 30) {
        $fbD = generate_exam_prep_section_notes($pdfText, $subject);
        if (!empty($fbD)) {
            $secD = $fbD;
        }
    }

    // Fallback for Section E (Key definitions)
    if (empty($secE) || strlen($secE) < 30) {
        $fbE = generate_exam_prep_key_definitions($pdfText, $subject);
        if (!empty($fbE)) {
            $secE = $fbE;
        }
    }

    // Validate that all sections are populated
    if (count($secA) < 5 || count($secB) < 3 || count($secC) < 2 || empty($secD) || empty($secE)) {
        return [
            'success' => false,
            'data' => null,
            'exam_prep' => null,
            'error' => 'Unable to generate all 5 required Exam Preparation sections with complete questions. Please try generating again.'
        ];
    }

    $fullMarkdown = format_exam_prep_markdown($secA, $secB, $secC, $secD, $secE);

    $prepData = [
        'section_a' => $secA,
        'section_b' => $secB,
        'section_c' => $secC,
        'revision_notes' => $secD,
        'key_definitions' => $secE,
        'full_markdown' => $fullMarkdown,
        'generated_at' => date('Y-m-d H:i:s')
    ];

    $jsonPayload = json_encode($prepData, JSON_UNESCAPED_UNICODE);

    return [
        'success' => true,
        'data' => $jsonPayload,
        'exam_prep' => $prepData,
        'error' => null
    ];
}



