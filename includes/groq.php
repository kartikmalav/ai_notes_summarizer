<?php
/**
 * Groq Cloud AI Service Module
 * High-speed LPU Inference via OpenAI-compatible endpoint
 * AI-Based PDF Notes Summarizer and Question Generator
 * 
 * Central AI engine powering:
 * 1. Short Notes & Summaries
 * 2. Important Exam Questions (2-Mark, 5-Mark, 10-Mark)
 * 3. Interactive Practice MCQs (10 Questions with instant explanations)
 * 4. 5-Section Exam Preparation Kit
 * 5. Ask Your Document (Document Grounded Q&A Assistant)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/markdown.php';

/**
 * List of officially supported Groq Chat Models
 * Verified against Groq Cloud Production Models List
 * 
 * @return array
 */
function get_supported_groq_models() {
    return [
        'openai/gpt-oss-120b',      // OpenAI GPT-OSS 120B (128k context) - Flagship
        'qwen/qwen3.8-27b',         // Qwen 3.8 27B (128k context) - Ultra-fast
        'openai/gpt-oss-20b',       // OpenAI GPT-OSS 20B (128k context)
        'llama-3.3-70b-versatile',  // Meta Llama 3.3 70B (where available)
        'llama-3.1-8b-instant',     // Meta Llama 3.1 8B (where available)
        'mixtral-8x7b-32768',       // Mistral Mixtral 8x7B (where available)
        'gemma2-9b-it'              // Google Gemma 2 9B on Groq
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
    $configured = defined('GROQ_MODEL') && GROQ_MODEL !== '' ? trim(GROQ_MODEL) : 'openai/gpt-oss-120b';

    if (in_array($configured, $supported, true)) {
        return $configured;
    }

    error_log("Configured GROQ_MODEL '{$configured}' is not in supported list. Using default 'openai/gpt-oss-120b'.");
    return 'openai/gpt-oss-120b';
}

/**
 * Check whether a valid Groq API key is present in configuration
 * 
 * @return bool
 */
function groq_is_configured() {
    $apiKey = trim(defined('GROQ_API_KEY') && GROQ_API_KEY !== '' ? GROQ_API_KEY : (getenv('GROQ_API_KEY') ?: ($_SERVER['GROQ_API_KEY'] ?? ($_ENV['GROQ_API_KEY'] ?? ''))));
    return !empty($apiKey) && $apiKey !== 'YOUR_GROQ_API_KEY_HERE';
}

/**
 * Alias for get_active_groq_model()
 * 
 * @return string
 */
function groq_get_model() {
    return get_active_groq_model();
}

/**
 * Alias for get_supported_groq_models()
 * 
 * @return array
 */
function groq_get_supported_models() {
    return get_supported_groq_models();
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
    $apiKey = trim(defined('GROQ_API_KEY') && GROQ_API_KEY !== '' ? GROQ_API_KEY : (getenv('GROQ_API_KEY') ?: ($_SERVER['GROQ_API_KEY'] ?? ($_ENV['GROQ_API_KEY'] ?? ''))));
    $activeModel = get_active_groq_model();

    // Validate API Key presence
    if (empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY_HERE') {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Groq API key is not configured. Please set your GROQ_API_KEY in the Apache/PHP environment or .env file.',
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

    // If HTTP 429 (Rate Limit) or 503 (Server Busy), failover immediately to an alternative model pool
    if ($httpCode === 429 || $httpCode === 503) {
        $candidateModels = ['qwen/qwen3.8-27b', 'openai/gpt-oss-20b', 'openai/gpt-oss-120b'];
        foreach ($candidateModels as $altModel) {
            if ($altModel !== $activeModel) {
                error_log("Groq API HTTP {$httpCode} on '{$activeModel}'. Failing over to model '{$altModel}'...");
                $altPayload = $requestBody;
                $altPayload['model'] = $altModel;
                // Cap max_tokens to 3500 to stay well under TPM limits
                if (isset($altPayload['max_tokens']) && $altPayload['max_tokens'] > 3500) {
                    $altPayload['max_tokens'] = 3500;
                }
                $altPayloadJson = json_encode($altPayload, JSON_UNESCAPED_UNICODE);

                $chAlt = curl_init($endpoint);
                curl_setopt($chAlt, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chAlt, CURLOPT_POST, true);
                curl_setopt($chAlt, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ]);
                curl_setopt($chAlt, CURLOPT_POSTFIELDS, $altPayloadJson);
                curl_setopt($chAlt, CURLOPT_CONNECTTIMEOUT, 15);
                curl_setopt($chAlt, CURLOPT_TIMEOUT, 120);
                if (isset($caBundle) && file_exists($caBundle)) {
                    curl_setopt($chAlt, CURLOPT_CAINFO, $caBundle);
                }

                $altResponse = curl_exec($chAlt);
                $altHttpCode = curl_getinfo($chAlt, CURLINFO_HTTP_CODE);
                curl_close($chAlt);

                if ($altHttpCode === 200) {
                    $altJson = json_decode($altResponse, true);
                    $altText = $altJson['choices'][0]['message']['content'] ?? null;
                    if (!empty($altText)) {
                        return [
                            'success' => true,
                            'data' => $altText,
                            'error' => null,
                            'provider' => 'groq',
                            'model' => $altModel
                        ];
                    }
                }
            }
        }

        // If all models hit rate limits, wait 3 seconds and retry
        error_log("Groq API rate limit reached across models. Waiting 3 seconds...");
        sleep(3);

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
        if (isset($caBundle) && file_exists($caBundle)) {
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
        $apiErrorMsg = $json['error']['message'] ?? '';
        $apiErrorType = $json['error']['type'] ?? '';

        // Auto-fallback if the configured model is unavailable on this account
        if ($httpCode === 404 && (stripos($apiErrorMsg, 'model') !== false || stripos($apiErrorMsg, 'does not exist') !== false)) {
            $fallbackCandidates = ['openai/gpt-oss-120b', 'qwen/qwen3.8-27b', 'openai/gpt-oss-20b'];
            foreach ($fallbackCandidates as $fbModel) {
                if ($fbModel !== $activeModel) {
                    error_log("Groq model '{$activeModel}' returned 404. Automatically trying '{$fbModel}'...");
                    $fbPayload = $requestBody;
                    $fbPayload['model'] = $fbModel;
                    $fbJson = json_encode($fbPayload, JSON_UNESCAPED_UNICODE);

                    $chFb = curl_init($endpoint);
                    curl_setopt($chFb, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chFb, CURLOPT_POST, true);
                    curl_setopt($chFb, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey
                    ]);
                    curl_setopt($chFb, CURLOPT_POSTFIELDS, $fbJson);
                    curl_setopt($chFb, CURLOPT_CONNECTTIMEOUT, 15);
                    curl_setopt($chFb, CURLOPT_TIMEOUT, 120);
                    if (isset($caBundle) && file_exists($caBundle)) {
                        curl_setopt($chFb, CURLOPT_CAINFO, $caBundle);
                    }
                    $fbResponse = curl_exec($chFb);
                    $fbHttpCode = curl_getinfo($chFb, CURLINFO_HTTP_CODE);
                    curl_close($chFb);

                    if ($fbHttpCode === 200) {
                        $fbDecoded = json_decode($fbResponse, true);
                        $fbText = $fbDecoded['choices'][0]['message']['content'] ?? null;
                        if (!empty($fbText)) {
                            return [
                                'success' => true,
                                'data' => $fbText,
                                'error' => null,
                                'provider' => 'groq',
                                'model' => $fbModel
                            ];
                        }
                    }
                }
            }
        }

        if ($httpCode === 401) {
            $formattedError = "Groq API Authentication Error (HTTP 401): Invalid or revoked GROQ_API_KEY.";
        } elseif ($httpCode === 429) {
            $formattedError = "Groq API Rate Limit Exceeded (HTTP 429): " . ($apiErrorMsg ?: 'Please wait a moment and try again.');
        } elseif ($httpCode >= 500) {
            $formattedError = "Groq API Server Error (HTTP {$httpCode}): " . ($apiErrorMsg ?: 'Temporary server failure.');
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

// =========================================================================
// SECTION 1: SHORT NOTES GENERATION
// =========================================================================

/**
 * Generate AI Short Notes using Groq
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'error' => string|null, 'provider' => 'groq']
 */
function groq_generate_short_notes($pdfText, $subject = 'Computer Science') {
    $maxLength = 45000;
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

    $systemMessage = "You are an expert college academic professor creating comprehensive exam revision notes for subject '{$subject}'.\n"
        . "STRICT GROUNDING: Use ONLY facts, definitions, and concepts stated in the provided document. Do NOT invent outside information.\n"
        . "FORMATTING RULES:\n"
        . "- Do NOT use decorative emojis or ornamental symbols.\n"
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
        . "(List explicit rules, formulas, algorithms, or processes found in the text; if none exist, summarize key principles)\n\n"
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

// Alias for seamless backward compatibility
function generate_pdf_short_notes($pdfText, $subject = 'Computer Science') {
    return groq_generate_short_notes($pdfText, $subject);
}

// =========================================================================
// SECTION 2: IMPORTANT QUESTIONS HELPERS & GENERATOR
// =========================================================================

/**
 * Robustly parse numbered question lines from a section block
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

    $patternA = '/#{1,4}\s*(?:Section\s*A|2[\s\-]*Mark)[^\n]*/i';
    $patternB = '/#{1,4}\s*(?:Section\s*B|5[\s\-]*Mark)[^\n]*/i';
    $patternC = '/#{1,4}\s*(?:Section\s*C|10[\s\-]*Mark)[^\n]*/i';

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
 * Generate questions for a single section using Groq as a fallback
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

    $res = call_groq_api($prompt, 4096, 0.2);
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

        // Section-by-section fallback if one section was missing
        $secA = ($validated && count($validated['secA']) === 5) ? $validated['secA'] : generate_single_section_questions($pdfText, $subject, 'A', 2, 5);
        $secB = ($validated && count($validated['secB']) === 3) ? $validated['secB'] : generate_single_section_questions($pdfText, $subject, 'B', 5, 3);
        $secC = ($validated && count($validated['secC']) === 2) ? $validated['secC'] : generate_single_section_questions($pdfText, $subject, 'C', 10, 2);

        if ($secA && $secB && $secC) {
            return [
                'success' => true,
                'data' => format_final_important_questions($secA, $secB, $secC),
                'error' => null,
                'provider' => 'groq',
                'model' => $res['model']
            ];
        }

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

// Alias for seamless backward compatibility
function generate_pdf_important_questions($pdfText, $subject = 'Computer Science') {
    return groq_generate_important_questions($pdfText, $subject);
}

// =========================================================================
// SECTION 3: MCQ GENERATOR & VALIDATOR HELPERS
// =========================================================================

/**
 * Extract JSON array from LLM response text
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
            'error' => "Groq generated {$count} questions, but exactly 10 valid questions are required."
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
        if ($rawAns === '' && isset($item['correct_option'])) {
            $idx = (int)$item['correct_option'];
            if (isset($letters[$idx])) {
                $rawAns = $letters[$idx];
            }
        }

        $firstLetter = strtoupper(substr($rawAns, 0, 1));
        $correctAns = null;

        if (in_array($firstLetter, $letters)) {
            $correctAns = $firstLetter;
        } else {
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
 * Generate 10 Interactive MCQs using Groq
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'mcqs' => array|null, 'error' => string|null, 'provider' => 'groq']
 */
function groq_generate_mcqs($pdfText, $subject = 'General') {
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

    $systemMessage = "You are an expert college professor designing a multiple-choice exam for subject '{$subject}'.\n"
        . "Generate EXACTLY 10 distinct multiple choice questions based SOLELY on the provided document text.\n"
        . "OUTPUT REQUIREMENT: Respond ONLY with a raw JSON array containing 10 question objects. NO markdown formatting, NO code blocks, NO text outside JSON.";

    $userPrompt = "Generate EXACTLY 10 multiple-choice questions grounded strictly in the text below.\n"
        . "Return a JSON array formatted exactly as follows:\n"
        . "[\n"
        . "  {\n"
        . "    \"id\": 1,\n"
        . "    \"question\": \"Question text here?\",\n"
        . "    \"options\": {\n"
        . "      \"A\": \"Option A text\",\n"
        . "      \"B\": \"Option B text\",\n"
        . "      \"C\": \"Option C text\",\n"
        . "      \"D\": \"Option D text\"\n"
        . "    },\n"
        . "    \"correct_answer\": \"A\",\n"
        . "    \"explanation\": \"Brief factual explanation citing the source text.\"\n"
        . "  }\n"
        . "]\n\n"
        . "RULES:\n"
        . "- Each question must have exactly 4 options (A, B, C, D).\n"
        . "- correct_answer must be \"A\", \"B\", \"C\", or \"D\".\n"
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
            'mcqs' => null,
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
            'mcqs' => null,
            'error' => $validated['error'] ?: 'Groq failed to generate all 10 valid MCQs.',
            'provider' => 'groq',
            'model' => $res['model']
        ];
    }

    $jsonStr = json_encode($validated['mcqs'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return [
        'success' => true,
        'data' => $jsonStr,
        'mcqs' => $validated['mcqs'],
        'error' => null,
        'provider' => 'groq',
        'model' => $res['model']
    ];
}

// Alias for seamless backward compatibility
function generate_document_mcqs($pdfText, $subject = 'General') {
    return groq_generate_mcqs($pdfText, $subject);
}

// =========================================================================
// SECTION 4: EXAM PREPARATION HELPERS & GENERATOR
// =========================================================================

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

    $patternA = '/#{1,4}\s*(?:Section\s*A|Part\s*A|2[\s\-]*Mark)[^\n]*/i';
    $patternB = '/#{1,4}\s*(?:Section\s*B|Part\s*B|5[\s\-]*Mark)[^\n]*/i';
    $patternC = '/#{1,4}\s*(?:Section\s*C|Part\s*C|10[\s\-]*Mark)[^\n]*/i';
    $patternD = '/#{1,4}\s*(?:Section\s*D|Topic[\s\-]*wise|Revision\s*Notes)[^\n]*/i';
    $patternE = '/#{1,4}\s*(?:Section\s*E|Key\s*Definitions|Core\s*Concepts)[^\n]*/i';

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
 * Fallback generator for Section D: Topic-wise revision notes using Groq
 */
function generate_exam_prep_section_notes($pdfText, $subject) {
    $prompt = "You are an expert college professor creating high-yield topic-wise revision notes for subject '{$subject}'.\n\n";
    $prompt .= "Based ONLY on the provided document text, create clear, comprehensive topic-wise revision notes.\n\n";
    $prompt .= "STRICT RULES:\n";
    $prompt .= "- Base notes strictly on the provided document without outside knowledge.\n";
    $prompt .= "- Organize notes under 3 to 5 clear subheadings representing key topics in the document.\n";
    $prompt .= "- Use concise bullet points with bold terms for critical points and mechanisms.\n";
    $prompt .= "- Do NOT include any introductory or concluding conversational filler.\n\n";
    $prompt .= "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $res = call_groq_api($prompt, 4096, 0.2);
    if ($res['success'] && !empty($res['data'])) {
        return clean_markdown_symbols($res['data']);
    }
    return '';
}

/**
 * Fallback generator for Section E: Key definitions using Groq
 */
function generate_exam_prep_key_definitions($pdfText, $subject) {
    $prompt = "You are an expert college professor creating key definitions for subject '{$subject}'.\n\n";
    $prompt .= "Based ONLY on the provided document text, extract and define 8 to 12 crucial definitions, formulas, and core concepts.\n\n";
    $prompt .= "STRICT RULES:\n";
    $prompt .= "- Format strictly as bullet points: **[Term Name]**: [Precise definition according to document]\n";
    $prompt .= "- Base all definitions strictly on the document text.\n";
    $prompt .= "- Do NOT include any conversational introduction or conclusion.\n\n";
    $prompt .= "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $res = call_groq_api($prompt, 4096, 0.2);
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
 * Generate comprehensive Exam Preparation material (Sections A, B, C, D, E) using Groq
 * 
 * @param string $pdfText Extracted document text
 * @param string $subject Course subject name
 * @return array ['success' => bool, 'data' => string|null, 'exam_prep' => array|null, 'error' => string|null, 'provider' => 'groq']
 */
function groq_generate_exam_prep($pdfText, $subject = 'General') {
    $maxLength = 35000;
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

    $systemMessage = "You are an expert academic professor and exam board examiner for subject: '{$subject}'.\n\n"
        . "Generate a comprehensive, high-yield Exam Preparation Kit strictly grounded in the document text provided below.\n\n"
        . "MANDATORY STRUCTURE - GENERATE ALL 5 SECTIONS:\n\n"
        . "### Section A: 2-Mark Short Answer Questions\n"
        . "Generate EXACTLY 5 concise questions (numbered 1 to 5). Questions only, NO answers.\n\n"
        . "### Section B: 5-Mark Medium Answer Questions\n"
        . "Generate EXACTLY 3 medium conceptual/analytical questions (numbered 1 to 3). Questions only, NO answers.\n\n"
        . "### Section C: 10-Mark Long Answer Questions\n"
        . "Generate EXACTLY 2 comprehensive questions (numbered 1 to 2). Questions only, NO answers.\n\n"
        . "### Section D: Topic-wise Revision Notes\n"
        . "Provide bulleted revision notes organized under major topic headings from the document. Highlight key points, mechanisms, and comparisons with clean Markdown tables if helpful.\n\n"
        . "### Section E: Key Definitions and Important Concepts\n"
        . "Provide 8 to 12 essential terms with bold headings and precise document definitions.\n\n"
        . "STRICT RULES:\n"
        . "- Base ALL content strictly on the provided document text.\n"
        . "- Generate EXACT numbers for questions: Section A (5), Section B (3), Section C (2).\n"
        . "- No emojis or ornamental symbols in question headers or text.";

    $userPrompt = "Generate the complete 5-section exam preparation kit from the text below.\n\n";
    if ($isTruncated) {
        $userPrompt .= "[Note: Source text was truncated to fit context limits]\n\n";
    }
    $userPrompt .= "Provided Document Text:\n---\n" . $pdfText . "\n---";

    $messages = [
        ['role' => 'system', 'content' => $systemMessage],
        ['role' => 'user', 'content' => $userPrompt]
    ];

    $res = call_groq_api($messages, 3500, 0.2);

    if (!$res['success'] || empty($res['data'])) {
        return [
            'success' => false,
            'data' => null,
            'exam_prep' => null,
            'error' => $res['error'] ?? 'Failed to communicate with the Groq AI service.',
            'provider' => 'groq'
        ];
    }

    $rawText = clean_markdown_symbols($res['data']);
    $parsed = parse_exam_prep_sections($rawText);

    $secA = $parsed['secA'];
    $secB = $parsed['secB'];
    $secC = $parsed['secC'];
    $secD = $parsed['secD'];
    $secE = $parsed['secE'];

    // Targeted single-section fallbacks if needed
    if (count($secA) < 5) {
        $fbA = generate_single_section_questions($pdfText, $subject, 'A', 2, 5);
        if ($fbA && count($fbA) === 5) {
            $secA = $fbA;
        }
    }

    if (count($secB) < 3) {
        $fbB = generate_single_section_questions($pdfText, $subject, 'B', 5, 3);
        if ($fbB && count($fbB) === 3) {
            $secB = $fbB;
        }
    }

    if (count($secC) < 2) {
        $fbC = generate_single_section_questions($pdfText, $subject, 'C', 10, 2);
        if ($fbC && count($fbC) === 2) {
            $secC = $fbC;
        }
    }

    if (empty($secD) || strlen($secD) < 30) {
        $fbD = generate_exam_prep_section_notes($pdfText, $subject);
        if (!empty($fbD)) {
            $secD = $fbD;
        }
    }

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
        'error' => null,
        'provider' => 'groq',
        'model' => $res['model']
    ];
}

// Alias for seamless backward compatibility
function generate_exam_preparation_material($pdfText, $subject = 'General') {
    return groq_generate_exam_prep($pdfText, $subject);
}

// =========================================================================
// SECTION 5: ASK YOUR DOCUMENT (DOCUMENT Q&A CHAT TUTOR)
// =========================================================================

/**
 * Extract relevant context excerpts for a question using keyword matching and sliding windows
 * 
 * @param string $fullText Document text
 * @param string $query User question
 * @param int $maxChars Max context characters to return
 * @return string Grounded context text
 */
function extract_relevant_document_context($fullText, $query, $maxChars = 30000) {
    if (strlen($fullText) <= $maxChars) {
        return $fullText;
    }

    // Split query into keywords
    $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query));
    $stopWords = [
        'what', 'is', 'the', 'of', 'and', 'a', 'in', 'to', 'how', 'does', 'why',
        'can', 'you', 'explain', 'tell', 'me', 'about', 'which', 'where', 'when',
        'for', 'on', 'with', 'by', 'at', 'from', 'this', 'that', 'these', 'those'
    ];
    $keywords = array_filter($words, function($w) use ($stopWords) {
        return mb_strlen($w) > 2 && !in_array($w, $stopWords, true);
    });

    if (empty($keywords)) {
        return mb_substr($fullText, 0, $maxChars);
    }

    // Break document into paragraphs
    $paragraphs = preg_split('/\n{2,}/u', $fullText);
    $scoredParagraphs = [];

    foreach ($paragraphs as $p) {
        $pLower = mb_strtolower($p);
        $score = 0;
        foreach ($keywords as $kw) {
            $occurrences = mb_substr_count($pLower, $kw);
            $score += $occurrences * (mb_strlen($kw) > 4 ? 2 : 1);
        }
        if ($score > 0) {
            $scoredParagraphs[] = ['text' => $p, 'score' => $score];
        }
    }

    if (empty($scoredParagraphs)) {
        return mb_substr($fullText, 0, $maxChars);
    }

    // Sort by relevance score
    usort($scoredParagraphs, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $accumulated = [];
    $totalLen = 0;

    foreach ($scoredParagraphs as $sp) {
        $pLen = mb_strlen($sp['text']);
        if ($totalLen + $pLen > $maxChars) {
            break;
        }
        $accumulated[] = $sp['text'];
        $totalLen += $pLen + 2;
    }

    if (empty($accumulated)) {
        return mb_substr($fullText, 0, $maxChars);
    }

    return implode("\n\n...\n\n", $accumulated);
}

/**
 * Answer Document Questions strictly grounded in document excerpts using Groq
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

    // Prepare relevant context excerpt (Groq Llama-3.3 70B supports 128k context)
    $contextText = extract_relevant_document_context($documentText, $question, 30000);

    $systemMessage = "You are a dedicated AI study tutor for the document \"{$documentName}\" (Subject: {$subject}).\n"
        . "GROUNDING RULES:\n"
        . "1. Answer strictly using ONLY the facts, definitions, and explanations provided in the document excerpts.\n"
        . "2. If the document does not contain the answer, explicitly state: \"This document does not contain information about [topic].\"\n"
        . "3. Formatting: Clean, standard Markdown without decorative emojis or ornamental symbols. Use bold for key terms and bullet points for lists.";

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

// Alias for seamless backward compatibility
function answer_document_question($documentText, $documentName, $subject = 'General', $question = '', $conversationHistory = []) {
    return groq_answer_document_question($documentText, $documentName, $subject, $question, $conversationHistory);
}
