<?php
/**
 * Test script for clean_markdown_symbols and Markdown rendering
 */

function clean_markdown_symbols($text) {
    if (empty($text)) return '';

    // 1. Remove emojis and pictographs
    $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{1F000}-\x{1F2FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $text);

    // 2. Normalize lines starting with decorative markers to standard markdown bullet '-'
    $text = preg_replace('/^([ \t]*)[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]+[ \t]*/mu', '$1- ', $text);

    // If a standard markdown bullet or number is immediately followed by a decorative symbol, remove it
    $text = preg_replace('/^([ \t]*(?:[-*]|\d+\.))[ \t]+[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]+[ \t]*/mu', '$1 ', $text);

    // Remove remaining decorative symbols in inline text or headers
    $text = preg_replace('/[★☆◆◇●○►▻▶▷➤➢✓✔✕✖✗✘❖✦✧✶✷✸✹✺▪▫■□◘◙⦿⦾➔➜➡➛➝➞☛☞☚☜]/u', '', $text);

    // 3. Clean up spaces before punctuation left by removed symbols, but preserve code operators like '!=' or '->'
    $text = preg_replace('/[ \t]+([.,;:?])(?=\s|$)/m', '$1', $text);

    // 4. Normalize multiple spaces after list markers and headers
    $text = preg_replace('/^([ \t]*(?:[-*]|\d+\.))[ \t]{2,}/mu', '$1 ', $text);
    $text = preg_replace('/^(#{1,6})[ \t]{2,}/mu', '$1 ', $text);
    $text = preg_replace('/[ \t]+$/mu', '', $text);

    return trim($text);
}

function render_markdown($text) {
    if (empty($text)) return '';

    // First clean any decorative symbols
    $text = clean_markdown_symbols($text);

    // Standardize line endings to \n
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Escape HTML characters first for XSS prevention
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Code blocks ``` code ```
    $html = preg_replace_callback('/```(?:[a-zA-Z0-9_-]*)\n(.*?)```/s', function($matches) {
        return '<pre class="markdown-code"><code>' . trim($matches[1]) . '</code></pre>';
    }, $html);

    // Inline code `code`
    $html = preg_replace('/`([^`]+)`/', '<code class="markdown-inline-code">$1</code>', $html);

    // Markdown Tables
    $html = preg_replace_callback('/(?:^\|[^\n]+\|\n)(?:^\|[-:\s|]+\|\n)(?:^\|[^\n]+\|\n?)+/m', function($matches) {
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

        $tableHtml = '<div style="overflow-x: auto; margin: 16px 0;"><table class="table-custom"><thead><tr>';
        foreach ($headers as $h) {
            $tableHtml .= '<th>' . trim($h) . '</th>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        foreach ($lines as $line) {
            $cols = array_values(array_map('trim', explode('|', trim($line, '|'))));
            if (!empty($cols)) {
                $tableHtml .= '<tr>';
                foreach ($cols as $c) {
                    $tableHtml .= '<td>' . trim($c) . '</td>';
                }
                $tableHtml .= '</tr>';
            }
        }
        $tableHtml .= '</tbody></table></div>';
        return $tableHtml;
    }, $html);

    // Headings (#, ##, ###)
    $html = preg_replace('/^### (.*?)$/m', '<h3 class="markdown-h3">$1</h3>', $html);
    $html = preg_replace('/^## (.*?)$/m', '<h2 class="markdown-h2">$1</h2>', $html);
    $html = preg_replace('/^# (.*?)$/m', '<h1 class="markdown-h1">$1</h1>', $html);

    // Bold **text**
    $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);

    // Italic *text*
    $html = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html);

    // Bullet lists (- item or * item)
    $html = preg_replace('/^\s*[-*]\s+(.*?)$/m', '<li class="markdown-li">$1</li>', $html);
    $html = preg_replace('/(<li class="markdown-li">.*?<\/li>\s*)+/s', '<ul class="markdown-ul">$0</ul>', $html);

    // Numbered lists (1. item, 2. item)
    $html = preg_replace('/^\s*(\d+)\.\s+(.*?)$/m', '<li class="markdown-oli">$2</li>', $html);
    $html = preg_replace('/(<li class="markdown-oli">.*?<\/li>\s*)+/s', '<ol class="markdown-ol" style="padding-left: 24px; margin: 12px 0;">$0</ol>', $html);

    // Line breaks and paragraphs
    $html = nl2br($html);

    // Clean up extra br tags inside pre code blocks
    $html = preg_replace_callback('/<pre class="markdown-code"><code>(.*?)<\/code><\/pre>/s', function($m) {
        return str_replace(['<br />', '<br>', '<br/>'], '', $m[0]);
    }, $html);

    // Clean up extra br tags around lists and tables
    $html = str_replace(
        ['</ul><br />', '<ul class="markdown-ul"><br />', '</ul><br>', '</ol><br />', '<ol class="markdown-ol"><br />', '</ol><br>', '</div><br />', '</div><br>'],
        ['</ul>', '<ul class="markdown-ul">', '</ul>', '</ol>', '<ol class="markdown-ol">', '</ol>', '</div>', '</div>'],
        $html
    );

    return $html;
}

$sample = <<<EOD
### 📌 Executive Summary
A process is an instance of a computer program being executed.

### 💡 Key Definitions & Terminology
- ✓ **Process**: Program in execution with its own address space.
◆ **Thread**: Lightweight process sharing address space.
● **Context Switching**: Mechanism to store and restore CPU state ★.
► **Race Condition**: Multiple threads accessing shared resource simultaneously.

### Comparison Table
| Feature | Process | Thread |
|---|---|---|
| Memory | Separate address space | Shared address space |
| Overhead | High context switch overhead | Low overhead |

### 📐 Rules, Formulas & Algorithms
- Mathematical formula: f(x) = (x * 2) + 1 / sqrt(y) <= 100
- Complexity: O(n log n) and ptr->field == NULL && a != b
- Set theory: A ∩ B ⊆ C and α + β = γ
- Range: [-10, +50] and i++

### 🎯 Quick Exam Revision Checklist
1. ✓ First step in process scheduling
2. Second step (CPU allocation)
EOD;

echo "--- CLEANED MARKDOWN ---\n";
echo clean_markdown_symbols($sample) . "\n\n";

echo "--- RENDERED HTML ---\n";
echo render_markdown($sample) . "\n";
