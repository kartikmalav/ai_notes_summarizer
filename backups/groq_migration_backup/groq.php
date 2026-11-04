<?php
/**
 * Groq Cloud API Client Module
 * High-speed LPU Inference via OpenAI-compatible endpoint
 * AI-Based PDF Notes Summarizer and Question Generator
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/markdown.php';
require_once __DIR__ . '/gemini.php'; // For shared helpers: parse_section_questions, clean_markdown_symbols, etc.

/**
 * List of officially supported Groq Chat Models
 * Verified against Groq Cloud Production Models List
 * 
 * @return array
 */
function get_supported_groq_models() {
    return [
        'llama-3.3-70b-versatile', // Meta Llama 3.3 70B - Recommended flagship
        'llama-3.1-8b-instant',    // Meta Llama 3.1 8B - Ultra-fast low latency
        'openai/gpt-oss-120b',     // OpenAI GPT-OSS 120B reasoning
        'openai/gpt-oss-20b'       // OpenAI GPT-OSS 20B fast reasoning
    ];
}

/**
 * Read and verify configured Groq model name from environment/config.
 * Validates that model is in the supported production list before sending API requests.
 * 
 * @return string Validated Groq model identifier
 */
function get_active_groq_model() {
    $supported = get_supported_groq_models();
    $configured = defined('GROQ_MODEL') && GROQ_MODEL !== '' ? trim(GROQ_MODEL) : 'llama-3.3-70b-versatile';

    if (in_array($configured, $supported, true)) {
        return $configured;
    }

    error_log("Configured GROQ_MODEL '{$configured}' is not in supported list. Using default 'llama-3.3-70b-versatile'.");
    return 'llama-3.3-70b-versatile';
}

/**
 * Call Groq Cloud API via OpenAI-compatible /v1/chat/completions endpoint
 * 
 * @param string|array $messagesOrPrompt Either prompt string or array of message objects [['role'=>..., 'content'=>...]]
 * @param int $maxTokens Maximum completion tokens
 * @param float $temperature Sampling temperature (0.0 to 1.0)
 * @return array ['success' => bool, 'data' => string|null, 'error' => string|null, 'provider' => 'groq', 'model' => string]
 */
function call_groq_api($messagesOrPrompt, $maxTokens = 4096, $temperature = 0.3) {
    $apiKey = trim(defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
    $activeModel = get_active_groq_model();

    // Validate API Key presence
    if (empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY_HERE') {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Groq API key is not configured. Set GROQ_API_KEY in the Apache/PHP environment or .env file.',
            'provider' => 'groq',
            'model' => $activeModel
        ];
    }

    // Build standard messages array
    $messages = [];
    if (is_string($messagesOrPrompt)) {
        $promptText = trim($messagesOrPrompt);
        if ($promptText === '') {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Cannot send Groq request: Prompt text is empty.',
                'provider' => 'groq',
                'model' => $activeModel
            ];
        }
        if (!mb_check_encoding($promptText, 'UTF-8')) {
            $promptText = mb_convert_encoding($promptText, 'UTF-8', 'UTF-8');
        }
        $messages = [
            ['role' => 'user', 'content' => $promptText]
        ];
    } elseif (is_array($messagesOrPrompt)) {
        foreach ($messagesOrPrompt as $m) {
            if (isset($m['role']) && isset($m['content'])) {
                $content = trim($m['content']);
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                }
                $messages[] = [
                    'role' => $m['role'],
                    'content' => $content
                ];
            }
        }
        if (empty($messages)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Cannot send Groq request: Messages array contains no valid message objects.',
                'provider' => 'groq',
                'model' => $activeModel
            ];
        }
    } else {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Invalid message payload supplied to Groq API.',
            'provider' => 'groq',
            'model' => $activeModel
        ];
    }

    $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

    // Construct OpenAI-compatible Chat Completion payload
    $requestBody = [
        'model' => $activeModel,
        'messages' => $messages,
        'temperature' => max(0.01, (float) $temperature),
        'max_tokens' => (int) $maxTokens
    ];

    $payloadJson = json_encode(
        $requestBody,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($payloadJson === false || empty($payloadJson)) {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Failed to JSON encode Groq request payload: ' . json_last_error_msg(),
            'provider' => 'groq',
            'model' => $activeModel
        ];
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
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

    // Redact API key from any output/error
    if (!empty($apiKey) && $apiKey !== 'YOUR_GROQ_API_KEY_HERE') {
        $curlError = str_ireplace($apiKey, '[REDACTED_API_KEY]', (string) $curlError);
    }

    if ($curlErrno) {
        error_log("Groq cURL Error ({$curlErrno}): " . $curlError);
        return [
            'success' => false,
            'data' => null,
            'error' => 'Network error connecting to Groq API: ' . $curlError,
            'provider' => 'groq',
            'model' => $activeModel
        ];
    }

    // Transient server errors (503 / 429) retry once after 2 seconds
    if ($httpCode === 503 || $httpCode === 429) {
        error_log("Groq API transient HTTP {$httpCode}. Retrying in 2 seconds...");
        sleep(2);

        $chRetry = curl_init($endpoint);
        curl_setopt($chRetry, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chRetry, CURLOPT_POST, true);
        curl_setopt($chRetry, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($chRetry, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($chRetry, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($chRetry, CURLOPT_TIMEOUT, 120);
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

        if (!empty($apiKey) && $apiKey !== 'YOUR_GROQ_API_KEY_HERE') {
            $curlError = str_ireplace($apiKey, '[REDACTED_API_KEY]', (string) $curlError);
        }

        if ($curlErrno) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Network error connecting to Groq API retry: ' . $curlError,
                'provider' => 'groq',
                'model' => $activeModel
            ];
        }
    }

    $json = json_decode($response, true);

    if ($httpCode !== 200) {
        $apiErrorMsg = $json['error']['message'] ?? null;
        $apiErrorType = $json['error']['type'] ?? null;
        $apiErrorCode = $json['error']['code'] ?? null;

        if ($httpCode === 401) {
            $formattedError = "Groq API Authentication Error (HTTP 401): Invalid or revoked GROQ_API_KEY.";
        } elseif ($httpCode === 429) {
            $formattedError = "Groq API Rate Limit Exceeded (HTTP 429): " . ($apiErrorMsg ?: 'Please wait a moment and try again.');
        } elseif ($apiErrorMsg) {
            $formattedError = "Groq API Error (HTTP {$httpCode} - {$apiErrorType}): {$apiErrorMsg}";
        } else {
            $formattedError = "Groq API Error (HTTP {$httpCode}): " . substr($response, 0, 300);
        }

        if (!empty($apiKey) && $apiKey !== 'YOUR_GROQ_API_KEY_HERE') {
            $formattedError = str_ireplace($apiKey, '[REDACTED_API_KEY]', $formattedError);
        }

        error_log("Groq API Error: " . $formattedError);
        return [
            'success' => false,
            'data' => null,
            'error' => $formattedError,
            'provider' => 'groq',
            'model' => $activeModel
        ];
    }

    // Extract completion text from OpenAI format
    $choice = $json['choices'][0] ?? null;
    $generatedText = $choice['message']['content'] ?? null;
    $finishReason = $choice['finish_reason'] ?? null;

    if (empty($generatedText)) {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Groq API returned an empty completion response.',
            'provider' => 'groq',
            'model' => $activeModel
        ];
    }

    return [
        'success' => true,
        'data' => trim($generatedText),
        'finish_reason' => $finishReason,
        'error' => null,
        'provider' => 'groq',
        'model' => $activeModel,
        'usage' => $json['usage'] ?? []
    ];
}

/**
 * Generate AI Short Notes using Groq
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'error' => string|null, 'provider' => 'groq']
 */
function groq_generate_short_notes($pdfText, $subject = 'Computer Science') {
    $maxLength = 45000; // Groq Llama-3.3 has 128k context, fits ample text
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

    $systemMessage = "You are an expert college academic evaluator creating comprehensive exam revision notes for subject '{$subject}'.\n"
        . "STRICT GROUNDING: Use ONLY facts, definitions, and concepts stated in the provided document. Do NOT invent outside information.\n"
        . "FORMATTING RULES:\n"
        . "- Do NOT use decorative symbols, emojis, or ornamental icons.\n"
        . "- Use clean, standard Markdown only (### headings, - bullet lists, **bold** key terms).\n"
        . "- Preserve technical notation, code syntax, and formulas.";

    $userPrompt = "Format your response using Markdown with the following exact sections:\n\n"
        . "### Executive Summary\n"
        . "(A concise 2-3 sentence overview strictly summarizing the core subject matter of the uploaded document)\n\n"
        . "### Key Definitions & Terminology\n"
        . "(Bullet points using standard hyphens with **bold terms** followed by their precise definitions as stated in the document)\n\n"
        . "### Core Topics & Breakdown\n"
        . "(Organized by major topics covered in the document, using subheadings and concise bullet points)\n\n"
        . "### Rules, Formulas & Algorithms\n"
        . "(List explicit rules, formulas, algorithms, or processes found in the text)\n\n"
        . "### Quick Exam Revision Checklist\n"
        . "(5-8 high-priority, exam-oriented bullet points covering major topics across the document)\n\n";

    if ($isTruncated) {
        $userPrompt .= "[Note: Source text was truncated to fit context limits]\n\n";
    }

    $userPrompt .= "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $messages = [
        ['role' => 'system', 'content' => $systemMessage],
        ['role' => 'user', 'content' => $userPrompt]
    ];

    $res = call_groq_api($messages, 4096, 0.2);

    if ($res['success'] && !empty($res['data'])) {
        $res['data'] = clean_markdown_symbols($res['data']);
    }

    return $res;
}

/**
 * Generate Exam Important Questions (Section A: 5x 2-mark, Section B: 3x 5-mark, Section C: 2x 10-mark) using Groq
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'error' => string|null, 'provider' => 'groq']
 */
function groq_generate_important_questions($pdfText, $subject = 'Computer Science') {
    $maxLength = 35000;
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

    $systemMessage = "You are an expert academic examiner preparing an exam question bank for subject '{$subject}'.\n"
        . "RULES:\n"
        . "- Base ALL questions strictly on the provided document text.\n"
        . "- ABSOLUTE ZERO FILLER: Generate questions ONLY. Do NOT write answers, explanations, solutions, or introductions.\n"
        . "- No emojis or ornamental symbols.\n"
        . "- Number each question clearly (1., 2., 3., etc.).";

    $userPrompt = "Generate an exam question paper structured strictly into the following 3 sections:\n\n"
        . "### Section A: 2-Mark Short Answer Questions\n"
        . "Generate EXACTLY 5 concise questions (numbered 1 to 5). Questions only, NO answers.\n\n"
        . "### Section B: 5-Mark Medium Answer Questions\n"
        . "Generate EXACTLY 3 medium questions (numbered 1 to 3). Questions only, NO answers.\n\n"
        . "### Section C: 10-Mark Long Answer Questions\n"
        . "Generate EXACTLY 2 comprehensive questions (numbered 1 to 2). Questions only, NO answers.\n\n";

    if ($isTruncated) {
        $userPrompt .= "[Note: Source text was truncated to fit context limits]\n\n";
    }

    $userPrompt .= "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $messages = [
        ['role' => 'system', 'content' => $systemMessage],
        ['role' => 'user', 'content' => $userPrompt]
    ];

    $res = call_groq_api($messages, 4096, 0.2);

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
                'error' => null,
                'provider' => 'groq',
                'model' => $res['model']
            ];
        }

        // If formatting was slight variance, return cleaned data if it contains the sections
        if (stripos($clean, 'Section A') !== false && stripos($clean, 'Section B') !== false && stripos($clean, 'Section C') !== false) {
            return [
                'success' => true,
                'data' => $clean,
                'error' => null,
                'provider' => 'groq',
                'model' => $res['model']
            ];
        }
    }

    return $res;
}

/**
 * Generate 10 Interactive MCQs using Groq
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'error' => string|null, 'provider' => 'groq']
 */
function groq_generate_mcqs($pdfText, $subject = 'General') {
    $maxLength = 35000;
    if (strlen($pdfText) > $maxLength) {
        $pdfText = substr($pdfText, 0, $maxLength);
    }

    if (strlen(trim($pdfText)) < 20) {
        return [
            'success' => false,
            'error' => 'The document contains insufficient printable text to generate MCQs.'
        ];
    }

    $systemMessage = "You are an expert college professor designing a multiple-choice exam for subject '{$subject}'.\n"
        . "Generate EXACTLY 10 distinct multiple choice questions based SOLELY on the provided document text.\n"
        . "OUTPUT REQUIREMENT: You MUST respond ONLY with a raw JSON array containing 10 question objects. NO markdown formatting, NO code blocks, NO text outside JSON.";

    $userPrompt = "Generate EXACTLY 10 multiple-choice questions grounded in the text below.\n"
        . "Return a JSON array formatted exactly as follows:\n"
        . "[\n"
        . "  {\n"
        . "    \"id\": 1,\n"
        . "    \"question\": \"Question text here?\",\n"
        . "    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],\n"
        . "    \"correct_option\": 0,\n"
        . "    \"explanation\": \"Brief factual explanation citing the source text.\"\n"
        . "  }\n"
        . "]\n\n"
        . "RULES:\n"
        . "- Each question must have exactly 4 options.\n"
        . "- correct_option is an integer index from 0 to 3 (0=Option A, 1=Option B, 2=Option C, 3=Option D).\n"
        . "- All 10 questions must be strictly grounded in the document text.\n\n"
        . "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $messages = [
        ['role' => 'system', 'content' => $systemMessage],
        ['role' => 'user', 'content' => $userPrompt]
    ];

    $res = call_groq_api($messages, 4096, 0.2);

    if (!$res['success'] || empty($res['data'])) {
        return $res;
    }

    $rawJson = extract_mcq_json($res['data']);
    if (!$rawJson) {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Groq API returned an invalid JSON response structure for MCQs.',
            'provider' => 'groq',
            'model' => $res['model']
        ];
    }

    $validated = validate_and_normalize_mcqs($rawJson);
    if (!$validated['valid']) {
        return [
            'success' => false,
            'data' => null,
            'error' => $validated['error'] ?: 'Groq failed to generate all 10 valid MCQs.',
            'provider' => 'groq',
            'model' => $res['model']
        ];
    }

    return [
        'success' => true,
        'data' => json_encode($validated['mcqs'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'error' => null,
        'provider' => 'groq',
        'model' => $res['model']
    ];
}

/**
 * Generate 5-Section Exam Preparation Material using Groq
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'error' => string|null, 'provider' => 'groq']
 */
function groq_generate_exam_prep($pdfText, $subject = 'General') {
    $maxLength = 35000;
    if (strlen($pdfText) > $maxLength) {
        $pdfText = substr($pdfText, 0, $maxLength);
    }

    if (strlen(trim($pdfText)) < 20) {
        return [
            'success' => false,
            'error' => 'The document contains insufficient printable text to generate exam prep material.'
        ];
    }

    $systemMessage = "You are a university exam prep specialist designing a comprehensive study kit for subject '{$subject}'.\n"
        . "RULES:\n"
        . "- Base all answers, questions, and notes strictly on the provided document text.\n"
        . "- Use clean Markdown formatting with standard headings (###).\n"
        . "- No decorative icons or emojis.";

    $userPrompt = "Generate a complete 5-section exam preparation kit structured with these exact headers:\n\n"
        . "### Section A: 2-Mark Short Answer Questions\n"
        . "(Provide 5 questions with concise 2-3 sentence model answers for each)\n\n"
        . "### Section B: 5-Mark Medium Answer Questions\n"
        . "(Provide 3 questions with structured, well-explained model answers for each)\n\n"
        . "### Section C: 10-Mark Long Answer Questions\n"
        . "(Provide 2 in-depth essay-style questions with comprehensive model answers including bullet points and step-by-step breakdowns)\n\n"
        . "### Section D: Topic-wise Quick Revision Notes\n"
        . "(High-yield bullet point revision notes covering all core units and topics in the document)\n\n"
        . "### Section E: Key Definitions & Core Concepts\n"
        . "(Alphabetical list of crucial terms with bold names and precise definitions)\n\n"
        . "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $messages = [
        ['role' => 'system', 'content' => $systemMessage],
        ['role' => 'user', 'content' => $userPrompt]
    ];

    $res = call_groq_api($messages, 8192, 0.2);

    if ($res['success'] && !empty($res['data'])) {
        $res['data'] = clean_markdown_symbols($res['data']);
    }

    return $res;
}

/**
 * Answer Document Questions (Ask Tutor) strictly grounded in document excerpts using Groq
 * 
 * @param string $documentText Extracted document text
 * @param string $documentName Original filename
 * @param string $subject Subject name
 * @param string $question User question
 * @param array $conversationHistory Prior conversation turns
 * @return array ['success' => bool, 'answer' => string|null, 'error' => string|null, 'provider' => 'groq']
 */
function groq_answer_document_question($documentText, $documentName, $subject = 'General', $question = '', $conversationHistory = []) {
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

    // Prepare relevant context excerpt
    $contextText = extract_relevant_document_context($documentText, $question, 30000);

    $systemMessage = "You are a dedicated AI study tutor for the document \"{$documentName}\" (Subject: {$subject}).\n"
        . "GROUNDING RULES:\n"
        . "1. Answer strictly using ONLY the facts and explanations provided in the document excerpts.\n"
        . "2. If the document does not contain the answer, explicitly state: \"This document does not contain information about [topic].\"\n"
        . "3. Formatting: Clean, standard Markdown without emojis or decorative symbols. Use bold for key terms and bullet points for lists.";

    $messages = [
        ['role' => 'system', 'content' => $systemMessage]
    ];

    // Inject recent conversation turns (up to 6)
    $recentHistory = array_slice($conversationHistory, -6);
    foreach ($recentHistory as $turn) {
        $role = ($turn['role'] === 'user') ? 'user' : 'assistant';
        $content = trim($turn['content'] ?? '');
        if (!empty($content)) {
            $messages[] = ['role' => $role, 'content' => $content];
        }
    }

    // Add current query with document excerpts
    $userTurnContent = "DOCUMENT EXCERPTS:\n\"\"\"\n{$contextText}\n\"\"\"\n\nSTUDENT QUESTION: {$question}";
    $messages[] = ['role' => 'user', 'content' => $userTurnContent];

    $res = call_groq_api($messages, 4096, 0.2);

    if (!$res['success']) {
        return [
            'success' => false,
            'answer' => null,
            'error' => $res['error'] ?? 'Failed to communicate with the Groq AI service.',
            'provider' => 'groq'
        ];
    }

    $answer = clean_markdown_symbols(trim($res['data']));

    return [
        'success' => true,
        'answer' => $answer,
        'error' => null,
        'provider' => 'groq',
        'model' => $res['model']
    ];
}
