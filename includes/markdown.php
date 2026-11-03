<?php
/**
 * Lightweight Markdown Helper for rendering AI Generated Content
 * AI-Based PDF Notes Summarizer and Question Generator
 */

/**
 * Clean unnecessary decorative symbols, emojis, and ornamental markers from Markdown text.
 * Preserves mathematical operators, technical notation, formulas, and code syntax.
 * 
 * @param string $text Raw Markdown text
 * @return string Cleaned Markdown text
 */
function clean_markdown_symbols($text) {
    if (empty($text) || !is_string($text)) return '';

    // Standardize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // 1. Remove emojis and decorative pictographs
    // Emoji ranges: 1F300-1FAFF, 1F000-1F2FF, 2600-26FF, 2700-27BF (Dingbats)
    // Preserves: 0x2200-0x22FF (math operators), 0x2190-0x21FF (arrows), 0x2300-0x23FF (technical symbols)
    $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{1F000}-\x{1F2FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $text);

    // 2. Normalize lines starting with decorative markers to standard markdown bullet '-'
    $text = preg_replace('/^([ \t]*)[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]+[ \t]*/mu', '$1- ', $text);

    // If a standard markdown bullet or number is immediately followed by a decorative symbol, remove the symbol
    $text = preg_replace('/^([ \t]*(?:[-*+]|\d+\.))[ \t]+[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]+[ \t]*/mu', '$1 ', $text);

    // Remove remaining decorative symbols in inline text or headers
    $text = preg_replace('/[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]/u', '', $text);

    // 3. Clean up spaces before punctuation left by removed symbols, while preserving code operators like '!=' or '->'
    $text = preg_replace('/[ \t]+([.,;:?])(?=\s|$)/m', '$1', $text);

    // 4. Normalize multiple spaces after list markers and headers
    $text = preg_replace('/^([ \t]*(?:[-*+]|\d+\.))[ \t]{2,}/mu', '$1 ', $text);
    $text = preg_replace('/^(#{1,6})[ \t]{2,}/mu', '$1 ', $text);

    // 5. Remove redundant leading/trailing horizontal rules and repetitive dividers
    $text = preg_replace('/^\s*[-*_]{3,}\s*\n+/u', '', $text);
    $text = preg_replace('/\n+\s*[-*_]{3,}\s*$/u', '', $text);
    $text = preg_replace('/(\n[ \t]*[-*_]{3,}[ \t]*){2,}/u', "\n---", $text);

    // 6. Collapse excessive blank lines (3 or more newlines become at most 2 newlines)
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    // 7. Trim trailing spaces on lines
    $text = preg_replace('/[ \t]+$/m', '', $text);

    return trim($text);
}

/**
 * Render inline markdown formatting (bold, italic, inline code) safely with HTML escaping.
 * Used for table cells and inline text spans to prevent raw ** or __ syntax.
 * 
 * @param string $text Raw inline text
 * @return string Safely escaped and formatted HTML
 */
function render_inline_markdown($text) {
    if (empty($text) || !is_string($text)) return '';

    // 1. Protect inline code `code`
    $inlineCodes = [];
    $text = preg_replace_callback('/`([^`\r\n]+)`/', function($matches) use (&$inlineCodes) {
        $idx = count($inlineCodes);
        $escaped = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $inlineCodes[$idx] = '<code class="markdown-inline-code">' . $escaped . '</code>';
        return "%%%INLINECODE_{$idx}%%%";
    }, $text);

    // 2. Escape HTML characters to strictly prevent XSS
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 3. Bold-Italic (***text*** and ___text___)
    $html = preg_replace('/\*\*\*([^*\r\n]+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
    $html = preg_replace('/(?<!\w)___([^_\r\n]+?)___(?!\w)/', '<strong><em>$1</em></strong>', $html);

    // 4. Bold (**text** and __text__)
    $html = preg_replace('/\*\*[ \t]*([^*\r\n]+?)[ \t]*\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/(?<!\w)__[ \t]*([^_\r\n]+?)[ \t]*__(?!\w)/', '<strong>$1</strong>', $html);

    // 5. Italic (*text* and _text_) - requiring non-space edges so math multiplication like `x * y` is not treated as italics
    $html = preg_replace('/(?<!\*|\w)\*(?!\s|\*)([^*\r\n]+?)(?<!\s|\*)\*(?!\*|\w)/', '<em>$1</em>', $html);
    $html = preg_replace('/(?<!\w)_(?!\s|_)([^_\r\n]+?)(?<!\s|_)_(?!\w)/', '<em>$1</em>', $html);

    // 6. Restore inline code
    foreach ($inlineCodes as $idx => $codeHtml) {
        $html = str_replace("%%%INLINECODE_{$idx}%%%", $codeHtml, $html);
    }

    return trim($html);
}

/**
 * Convert Markdown syntax to clean, safe HTML for display
 * 
 * @param string $text Markdown formatted string
 * @return string Safe HTML string
 */
function render_markdown($text) {
    if (empty($text) || !is_string($text)) return '';

    // Standardize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Protect fenced code blocks: ```lang ... ```
    $codeBlocks = [];
    $text = preg_replace_callback('/```(?:[a-zA-Z0-9_-]*)\n(.*?)```/s', function($matches) use (&$codeBlocks) {
        $idx = count($codeBlocks);
        $escapedCode = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        $codeBlocks[$idx] = '<pre class="markdown-code"><code>' . $escapedCode . '</code></pre>';
        return "%%%CODEBLOCK_{$idx}%%%\n";
    }, $text);

    // Protect inline code: `code`
    $inlineCodes = [];
    $text = preg_replace_callback('/`([^`\n]+)`/', function($matches) use (&$inlineCodes) {
        $idx = count($inlineCodes);
        $escapedInline = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $inlineCodes[$idx] = '<code class="markdown-inline-code">' . $escapedInline . '</code>';
        return "%%%INLINECODE_{$idx}%%%";
    }, $text);

    // Clean decorative symbols and excessive blank lines on prose
    $text = clean_markdown_symbols($text);

    // Protect and render Markdown Tables (including cell inline bold, italic, code)
    $tables = [];
    $text = preg_replace_callback('/(?:^\|[^\n]+\|\n)(?:^\|[-:\s|]+\|\n)(?:^\|[^\n]+\|\n?)+/m', function($matches) use (&$tables) {
        $rawLines = explode("\n", trim($matches[0]));
        $lines = [];
        foreach ($rawLines as $l) {
            $trimmed = trim($l);
            if ($trimmed !== '') $lines[] = $trimmed;
        }
        if (count($lines) < 2) return $matches[0];

        $headerLine = array_shift($lines);
        $alignLine = array_shift($lines);

        $headers = array_values(array_filter(array_map('trim', explode('|', trim($headerLine, '|'))), function($c) { return $c !== ''; }));

        $tableHtml = '<div class="markdown-table-wrap"><table class="table-custom"><thead><tr>';
        foreach ($headers as $h) {
            $tableHtml .= '<th>' . render_inline_markdown($h) . '</th>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        foreach ($lines as $line) {
            $cols = array_values(array_map('trim', explode('|', trim($line, '|'))));
            if (!empty($cols)) {
                $tableHtml .= '<tr>';
                foreach ($cols as $c) {
                    $tableHtml .= '<td>' . render_inline_markdown($c) . '</td>';
                }
                $tableHtml .= '</tr>';
            }
        }
        $tableHtml .= '</tbody></table></div>';
        $idx = count($tables);
        $tables[$idx] = $tableHtml;
        return "\n%%%TABLE_{$idx}%%%\n";
    }, $text);

    // Escape HTML characters for all prose to strictly prevent XSS
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Headings (match from largest ###### down to # to prevent partial matching)
    $html = preg_replace('/^######[ \t]+(.*?)$/m', '<h6 class="markdown-h6">$1</h6>', $html);
    $html = preg_replace('/^#####[ \t]+(.*?)$/m', '<h5 class="markdown-h5">$1</h5>', $html);
    $html = preg_replace('/^####[ \t]+(.*?)$/m', '<h4 class="markdown-h4">$1</h4>', $html);
    $html = preg_replace('/^###[ \t]+(.*?)$/m', '<h3 class="markdown-h3">$1</h3>', $html);
    $html = preg_replace('/^##[ \t]+(.*?)$/m', '<h2 class="markdown-h2">$1</h2>', $html);
    $html = preg_replace('/^#[ \t]+(.*?)$/m', '<h1 class="markdown-h1">$1</h1>', $html);

    // Horizontal rules (---, ***, ___)
    $html = preg_replace('/^[ \t]*[-*_]{3,}[ \t]*$/m', '<hr class="markdown-hr">', $html);

    // Blockquotes (> Quote)
    $html = preg_replace('/^[ \t]*&gt;[ \t]?(.*?)$/m', '<blockquote class="markdown-blockquote">$1</blockquote>', $html);
    $html = preg_replace('/(<\/blockquote>\s*<blockquote class="markdown-blockquote">)/', '<br>', $html);

    // Lists: Unordered bullet lists (- or * or +) - processed BEFORE italic parsing
    $html = preg_replace('/^[ \t]*[-*+][ \t]+(.*?)$/m', '<li class="markdown-li">$1</li>', $html);
    $html = preg_replace('/(<li class="markdown-li">.*?<\/li>(?:\s*<li class="markdown-li">.*?<\/li>)*)/s', '<ul class="markdown-ul">$1</ul>', $html);

    // Lists: Ordered numbered lists (1. item)
    $html = preg_replace('/^[ \t]*\d+\.[ \t]+(.*?)$/m', '<li class="markdown-oli">$1</li>', $html);
    $html = preg_replace('/(<li class="markdown-oli">.*?<\/li>(?:\s*<li class="markdown-oli">.*?<\/li>)*)/s', '<ol class="markdown-ol">$1</ol>', $html);
    $html = str_replace('<li class="markdown-oli">', '<li class="markdown-li">', $html);

    // Bold-Italic (***text*** and ___text___)
    $html = preg_replace('/\*\*\*([^*\r\n]+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
    $html = preg_replace('/(?<!\w)___([^_\r\n]+?)___(?!\w)/', '<strong><em>$1</em></strong>', $html);

    // Bold (**text** and __text__) - allows single newline within bold phrase
    $html = preg_replace('/\*\*[ \t]*((?:[^*\r\n]|\r?\n(?!\r?\n))+?)[ \t]*\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/(?<!\w)__[ \t]*((?:[^_\r\n]|\r?\n(?!\r?\n))+?)[ \t]*__(?!\w)/', '<strong>$1</strong>', $html);

    // Italic (*text* and _text_) - requiring non-space edges so math multiplication like `x * y` is not treated as italics
    $html = preg_replace('/(?<!\*|\w)\*(?!\s|\*)([^*\r\n]+?)(?<!\s|\*)\*(?!\*|\w)/', '<em>$1</em>', $html);
    $html = preg_replace('/(?<!\w)_(?!\s|_)([^_\r\n]+?)(?<!\s|_)_(?!\w)/', '<em>$1</em>', $html);

    // Convert newlines to <br />
    $html = nl2br($html);

    // Clean up unnecessary <br /> tags around block elements
    $blockTags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'hr', 'blockquote'];
    foreach ($blockTags as $tag) {
        $html = preg_replace('/<br\s*\/?>\s*(<' . $tag . '[^>]*>)/i', '$1', $html);
        $html = preg_replace('/(<\/' . $tag . '>)\s*<br\s*\/?>/i', '$1', $html);
        $html = preg_replace('/(<' . $tag . '[^>]*>)\s*<br\s*\/?>/i', '$1', $html);
    }
    $html = preg_replace('/<hr class="markdown-hr">\s*<br\s*\/?>/i', '<hr class="markdown-hr">', $html);

    // Restore Tables
    foreach ($tables as $idx => $tableCode) {
        $html = str_replace("%%%TABLE_{$idx}%%%", $tableCode, $html);
    }

    // Restore Code Blocks
    foreach ($codeBlocks as $idx => $codeHtml) {
        $html = str_replace("%%%CODEBLOCK_{$idx}%%%", $codeHtml, $html);
    }

    // Clean up br tags around pre and table blocks after restoration
    $html = preg_replace('/<br\s*\/?>\s*(<pre\b)/i', '$1', $html);
    $html = preg_replace('/(<\/pre>)\s*<br\s*\/?>/i', '$1', $html);
    $html = preg_replace('/<br\s*\/?>\s*(<div class="markdown-table-wrap">)/i', '$1', $html);
    $html = preg_replace('/(<\/div>)\s*<br\s*\/?>/i', '$1', $html);

    // Collapse any remaining double or triple br tags
    $html = preg_replace('/(?:<br\s*\/?>\s*){3,}/i', '<br /><br />', $html);

    // Restore Inline Code
    foreach ($inlineCodes as $idx => $codeHtml) {
        $html = str_replace("%%%INLINECODE_{$idx}%%%", $codeHtml, $html);
    }

    return trim($html);
}
