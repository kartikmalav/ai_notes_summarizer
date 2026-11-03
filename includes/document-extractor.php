<?php
/**
 * Native Multi-Format Document Extraction Module
 * AI-Based Study Assistant (PDF, DOCX, TXT, CSV, XLSX, PPTX)
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Extract normalized text content from any supported document format
 * 
 * @param string $filePath Full path to the uploaded document
 * @param string $fileType File extension/type (pdf, docx, txt, csv, xlsx, pptx)
 * @param string|null $textFilePath Companion text file path (for PDF or pre-extracted text)
 * @return array ['success' => bool, 'text' => string, 'item_count' => int, 'error' => string|null]
 */
function extract_document_text($filePath, $fileType, $textFilePath = null) {
    $fileType = strtolower(trim($fileType));

    if (!file_exists($filePath)) {
        return [
            'success' => false,
            'text' => '',
            'item_count' => 0,
            'error' => "Source document file not found at path: {$filePath}"
        ];
    }

    switch ($fileType) {
        case 'txt':
            return extract_txt_content($filePath);

        case 'csv':
            return extract_csv_content($filePath);

        case 'docx':
            return extract_docx_content($filePath);

        case 'xlsx':
            return extract_xlsx_content($filePath);

        case 'pptx':
            return extract_pptx_content($filePath);

        case 'pdf':
            return extract_pdf_content($filePath, $textFilePath);

        default:
            return [
                'success' => false,
                'text' => '',
                'item_count' => 0,
                'error' => "Unsupported document file type: .{$fileType}"
            ];
    }
}

/**
 * Extract text from Plain Text (.txt) files
 */
function extract_txt_content($filePath) {
    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'Failed to read plain text file.'];
    }

    // Convert encoding to UTF-8 safely
    $text = mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, ASCII');
    $text = trim($text);

    // Calculate approximate paragraph / line count
    $lines = explode("\n", $text);
    $lineCount = count(array_filter($lines));

    return [
        'success' => true,
        'text' => $text,
        'item_count' => max(1, $lineCount),
        'error' => null
    ];
}

/**
 * Extract text from Comma Separated Values (.csv) files
 */
function extract_csv_content($filePath) {
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'Failed to open CSV file for reading.'];
    }

    $formattedRows = [];
    $rowCount = 0;

    while (($data = fgetcsv($handle, 4096, ',')) !== false) {
        $rowCount++;
        // Clean empty cells
        $cleanCells = array_map(function($cell) {
            return trim(mb_convert_encoding($cell, 'UTF-8', 'UTF-8, ISO-8859-1, ASCII'));
        }, $data);

        // Skip completely empty rows
        if (count(array_filter($cleanCells)) === 0) continue;

        $formattedRows[] = "[Row {$rowCount}] " . implode(" | ", $cleanCells);
    }
    fclose($handle);

    $fullText = "DOCUMENT TYPE: CSV Spreadsheet\nTOTAL ROWS: {$rowCount}\n\n" . implode("\n", $formattedRows);

    return [
        'success' => true,
        'text' => trim($fullText),
        'item_count' => $rowCount,
        'error' => null
    ];
}

/**
 * Extract text from Microsoft Word (.docx) files using ZipArchive + SimpleXML
 */
function extract_docx_content($filePath) {
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'PHP ZipArchive extension is required to extract DOCX files.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'Failed to open DOCX zip container.'];
    }

    $xmlContent = $zip->getFromName('word/document.xml');
    $zip->close();

    if (!$xmlContent) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'word/document.xml missing from DOCX file.'];
    }

    // Replace XML tags to preserve paragraph structure
    $xmlContent = str_replace('</w:p>', "\n\n", $xmlContent);
    $xmlContent = str_replace(['</w:tr>', '</w:tc>'], ["\n", " | "], $xmlContent);

    // Strip remaining XML tags safely
    $text = strip_tags($xmlContent);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace("/\n\s+\n/", "\n\n", $text);
    $text = trim($text);

    $paragraphs = array_filter(explode("\n\n", $text));
    $paraCount = count($paragraphs);

    $fullText = "DOCUMENT TYPE: DOCX Word Document\nPARAGRAPHS: {$paraCount}\n\n" . $text;

    return [
        'success' => true,
        'text' => $fullText,
        'item_count' => max(1, $paraCount),
        'error' => null
    ];
}

/**
 * Extract text from Microsoft Excel (.xlsx) files using ZipArchive + XML parsing
 */
function extract_xlsx_content($filePath) {
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'PHP ZipArchive extension is required for XLSX files.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'Failed to open XLSX zip container.'];
    }

    // Parse Shared Strings Dictionary
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml) {
        $xml = @simplexml_load_string($sharedStringsXml);
        if ($xml) {
            foreach ($xml->si as $val) {
                if (isset($val->t)) {
                    $sharedStrings[] = (string)$val->t;
                } elseif (isset($val->r)) {
                    $t = '';
                    foreach ($val->r as $r) {
                        $t .= (string)$r->t;
                    }
                    $sharedStrings[] = $t;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    // Iterate through Sheet XML files
    $sheetsOutput = [];
    $sheetCount = 0;

    for ($i = 1; $i <= 50; $i++) {
        $sheetXmlContent = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
        if (!$sheetXmlContent) continue;

        $sheetCount++;
        $xml = @simplexml_load_string($sheetXmlContent);
        if (!$xml) continue;

        $rowsData = [];
        $rowIdx = 0;

        foreach ($xml->sheetData->row as $row) {
            $rowIdx++;
            $cells = [];
            foreach ($row->c as $cell) {
                $cellVal = '';
                $type = (string)$cell['t'];

                if ($type === 's') { // Shared String
                    $idx = (int)$cell->v;
                    $cellVal = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $cellVal = (string)$cell->is->t;
                } else {
                    $cellVal = (string)$cell->v;
                }

                $cells[] = trim($cellVal);
            }
            if (count(array_filter($cells)) > 0) {
                $rowsData[] = "[Row {$rowIdx}] " . implode(" | ", $cells);
            }
        }

        if (!empty($rowsData)) {
            $sheetsOutput[] = "=== SHEET {$sheetCount} ===\n" . implode("\n", $rowsData);
        }
    }

    $zip->close();

    $fullText = "DOCUMENT TYPE: XLSX Excel Workbook\nSHEETS PROCESSED: {$sheetCount}\n\n" . implode("\n\n", $sheetsOutput);

    return [
        'success' => true,
        'text' => trim($fullText),
        'item_count' => max(1, $sheetCount),
        'error' => null
    ];
}

/**
 * Extract text from Microsoft PowerPoint (.pptx) files using ZipArchive + XML parsing
 */
function extract_pptx_content($filePath) {
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'PHP ZipArchive extension is required for PPTX files.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['success' => false, 'text' => '', 'item_count' => 0, 'error' => 'Failed to open PPTX zip container.'];
    }

    $slidesOutput = [];
    $slideCount = 0;

    for ($i = 1; $i <= 100; $i++) {
        $slideXmlContent = $zip->getFromName("ppt/slides/slide{$i}.xml");
        if (!$slideXmlContent) continue;

        $slideCount++;
        // Add space after paragraph tags before stripping XML
        $cleanXml = str_replace('</a:p>', "\n", $slideXmlContent);
        $slideText = strip_tags($cleanXml);
        $slideText = html_entity_decode($slideText, ENT_QUOTES, 'UTF-8');
        $slideText = preg_replace("/\n\s+\n/", "\n", $slideText);
        $slideText = trim($slideText);

        if (!empty($slideText)) {
            $slidesOutput[] = "--- SLIDE {$slideCount} ---\n" . $slideText;
        }
    }

    $zip->close();

    $fullText = "DOCUMENT TYPE: PPTX PowerPoint Presentation\nTOTAL SLIDES: {$slideCount}\n\n" . implode("\n\n", $slidesOutput);

    return [
        'success' => true,
        'text' => trim($fullText),
        'item_count' => max(1, $slideCount),
        'error' => null
    ];
}

/**
 * Extract / load text for PDF documents with native server-side fallback
 */
function extract_pdf_content($filePath, $textFilePath = null) {
    $targetTextPath = $textFilePath ?: ($filePath . '.txt');

    // 1. If companion extracted text file exists and has content, use it
    if (file_exists($targetTextPath)) {
        $text = @file_get_contents($targetTextPath);
        if (!empty(trim($text))) {
            return [
                'success' => true,
                'text' => trim($text),
                'item_count' => max(1, substr_count($text, '--- Page ')),
                'error' => null
            ];
        }
    }

    // 2. Perform native server-side PDF extraction
    if (!file_exists($filePath)) {
        return [
            'success' => false,
            'text' => '',
            'item_count' => 0,
            'error' => 'PDF document file not found on disk.'
        ];
    }

    $content = @file_get_contents($filePath);
    if ($content === false || strlen($content) < 8) {
        return [
            'success' => false,
            'text' => '',
            'item_count' => 0,
            'error' => 'Unable to read PDF file or the file is corrupted.'
        ];
    }

    // Validate PDF header
    if (substr($content, 0, 5) !== '%PDF-') {
        return [
            'success' => false,
            'text' => '',
            'item_count' => 0,
            'error' => 'Invalid file format: file header does not match PDF specification.'
        ];
    }

    // Check for password protection / encryption
    if (preg_match('/\/Encrypt\s+/i', $content)) {
        return [
            'success' => false,
            'text' => '',
            'item_count' => 0,
            'error' => 'Password-protected or encrypted PDF detected. Please decrypt the PDF before uploading.'
        ];
    }

    // Estimate page count
    $pageCount = 0;
    if (preg_match('/\/Type\s*\/Pages[^>]*\/Count\s+(\d+)/s', $content, $m)) {
        $pageCount = (int)$m[1];
    }
    if ($pageCount === 0) {
        $pageMatches = preg_match_all('/\/Type\s*\/Page\b/i', $content, $dummy);
        $pageCount = max(1, $pageMatches);
    }

    // Parse ToUnicode CMaps for font translation
    $cMap = parse_pdf_tounicode_cmaps($content);

    // Extract all decompressed streams
    $streams = [];
    $offset = 0;
    while (($streamPos = strpos($content, 'stream', $offset)) !== false) {
        $streamStart = $streamPos + 6;
        while ($streamStart < strlen($content) && ($content[$streamStart] === "\r" || $content[$streamStart] === "\n")) {
            $streamStart++;
        }

        $streamEnd = strpos($content, 'endstream', $streamStart);
        if ($streamEnd === false) break;

        $rawStream = substr($content, $streamStart, $streamEnd - $streamStart);
        $uncompressed = @gzuncompress($rawStream);
        if ($uncompressed === false) {
            $uncompressed = @gzinflate(substr($rawStream, 2));
        }
        if ($uncompressed === false) {
            $uncompressed = @gzinflate($rawStream);
        }

        if ($uncompressed !== false) {
            $streams[] = $uncompressed;
        } else {
            $streams[] = $rawStream;
        }

        $offset = $streamEnd + 9;
    }

    $extractedSegments = [];
    foreach ($streams as $stream) {
        if (preg_match_all('/BT[\s\S]*?ET/s', $stream, $btMatches)) {
            foreach ($btMatches[0] as $btBlock) {
                // Literal string operators: (...) Tj
                if (preg_match_all('/\((.*?)(?<!\\\\)\)\s*Tj/s', $btBlock, $litTj)) {
                    foreach ($litTj[1] as $str) {
                        $extractedSegments[] = decode_pdf_text_string($str);
                    }
                }
                // Hex string operators: <hex> Tj
                if (preg_match_all('/<([0-9a-fA-F\s]+)>\s*Tj/s', $btBlock, $hexTj)) {
                    foreach ($hexTj[1] as $hex) {
                        $hexClean = preg_replace('/\s+/', '', $hex);
                        $extractedSegments[] = decode_pdf_hex_string($hexClean, $cMap);
                    }
                }
                // Array operators: [...] TJ
                if (preg_match_all('/\[([\s\S]*?)\]\s*TJ/s', $btBlock, $tjBlocks)) {
                    foreach ($tjBlocks[1] as $inner) {
                        if (preg_match_all('/\((.*?)(?<!\\\\)\)|<([0-9a-fA-F\s]+)>/s', $inner, $elemMatches, PREG_SET_ORDER)) {
                            foreach ($elemMatches as $el) {
                                if (!empty($el[1])) {
                                    $extractedSegments[] = decode_pdf_text_string($el[1]);
                                } elseif (!empty($el[2])) {
                                    $hexClean = preg_replace('/\s+/', '', $el[2]);
                                    $extractedSegments[] = decode_pdf_hex_string($hexClean, $cMap);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    $fullExtracted = implode(' ', array_filter($extractedSegments));
    // Clean whitespace and remove control characters
    $fullExtracted = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $fullExtracted);
    $fullExtracted = preg_replace('/\s+/', ' ', $fullExtracted);
    $fullExtracted = trim($fullExtracted);

    // Check for scanned / image-only PDF
    $hasImages = (stripos($content, '/Subtype /Image') !== false || stripos($content, '/Subtype/Image') !== false);
    if (strlen($fullExtracted) < 50 && $hasImages) {
        return [
            'success' => false,
            'text' => '',
            'item_count' => $pageCount,
            'error' => 'Scanned or image-only PDF detected. Text cannot be extracted without an OCR engine.'
        ];
    }

    if (empty($fullExtracted)) {
        return [
            'success' => false,
            'text' => '',
            'item_count' => $pageCount,
            'error' => 'No readable text content found in this PDF. The document may be image-only, protected, or use unsupported font encodings.'
        ];
    }

    // Cache the extracted text to companion file
    if (!empty($targetTextPath)) {
        @file_put_contents($targetTextPath, $fullExtracted);
    }

    return [
        'success' => true,
        'text' => $fullExtracted,
        'item_count' => max(1, $pageCount),
        'error' => null
    ];
}

/**
 * Helper: Decode escaped literal PDF text strings
 */
function decode_pdf_text_string($str) {
    $replacements = [
        '\\n' => "\n",
        '\\r' => "\r",
        '\\t' => "\t",
        '\\b' => "\x08",
        '\\f' => "\x0C",
        '\\(' => '(',
        '\\)' => ')',
        '\\\\' => '\\'
    ];
    $decoded = strtr($str, $replacements);
    // Decode octal escapes \ddd
    $decoded = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
        return chr(octdec($m[1]));
    }, $decoded);
    return mb_convert_encoding($decoded, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
}

/**
 * Helper: Decode hex PDF strings using ToUnicode CMap or UTF-16BE / ASCII
 */
function decode_pdf_hex_string($hex, $cMap = []) {
    if (strlen($hex) % 2 !== 0) {
        $hex .= '0';
    }

    // Try CMap 4-hex digit lookup
    if (!empty($cMap) && strlen($hex) >= 4) {
        $chunks = str_split($hex, 4);
        $result = '';
        foreach ($chunks as $chunk) {
            $key = strtoupper(str_pad($chunk, 4, '0', STR_PAD_LEFT));
            if (isset($cMap[$key])) {
                $result .= $cMap[$key];
            } else {
                $bin = @hex2bin($chunk);
                if ($bin !== false) {
                    $result .= (ord($bin[0]) === 0 && isset($bin[1])) ? $bin[1] : $bin;
                }
            }
        }
        if (!empty(trim($result))) {
            return $result;
        }
    }

    $bin = @hex2bin($hex);
    if ($bin === false) return '';

    if (strlen($bin) >= 2 && ($bin[0] === "\0" || $bin[1] === "\0")) {
        $conv = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
        if ($conv !== false) return $conv;
    }

    return mb_convert_encoding($bin, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
}

/**
 * Helper: Parse ToUnicode CMaps from PDF stream objects
 */
function parse_pdf_tounicode_cmaps($content) {
    $maps = [];
    if (!preg_match_all('/\/ToUnicode\s+(\d+)\s+(\d+)\s+R/', $content, $toUni)) {
        return $maps;
    }

    foreach (array_unique($toUni[1]) as $objNum) {
        $pattern = '/' . $objNum . '\s+\d+\s+obj[\s\S]*?stream[\r\n]+([\s\S]*?)[\r\n]+endstream/s';
        if (preg_match($pattern, $content, $m)) {
            $raw = $m[1];
            $decompressed = @gzuncompress($raw);
            if ($decompressed === false) $decompressed = @gzinflate(substr($raw, 2));
            if ($decompressed === false) $decompressed = @gzinflate($raw);

            if ($decompressed !== false) {
                // Parse beginbfchar: <src> <dst>
                if (preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>/', $decompressed, $bfChars, PREG_SET_ORDER)) {
                    foreach ($bfChars as $pair) {
                        $src = strtoupper(str_pad(ltrim($pair[1], '0'), 4, '0', STR_PAD_LEFT));
                        $dstBin = @hex2bin($pair[2]);
                        if ($dstBin !== false) {
                            $maps[$src] = @mb_convert_encoding($dstBin, 'UTF-8', 'UTF-16BE') ?: chr(hexdec($pair[2]));
                        }
                    }
                }

                // Parse beginbfrange: <start> <end> <dstStart>
                if (preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>/', $decompressed, $bfRanges, PREG_SET_ORDER)) {
                    foreach ($bfRanges as $range) {
                        $start = hexdec($range[1]);
                        $end = hexdec($range[2]);
                        $dstStart = hexdec($range[3]);
                        for ($c = $start; $c <= $end; $c++) {
                            $srcKey = strtoupper(str_pad(dechex($c), 4, '0', STR_PAD_LEFT));
                            $targetHex = str_pad(dechex($dstStart + ($c - $start)), 4, '0', STR_PAD_LEFT);
                            $targetBin = @hex2bin($targetHex);
                            $maps[$srcKey] = @mb_convert_encoding($targetBin, 'UTF-8', 'UTF-16BE') ?: chr($dstStart + ($c - $start));
                        }
                    }
                }
            }
        }
    }

    return $maps;
}
