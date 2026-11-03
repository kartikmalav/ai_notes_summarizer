<?php
/**
 * Formal Verification Suite: Markdown Rendering and Cleaning for Ask Your Document
 */

require_once __DIR__ . '/../includes/markdown.php';

$allPassed = true;

function assertTest($name, $condition, $details = '') {
    global $allPassed;
    if ($condition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}: {$details}\n";
        $allPassed = false;
    }
}

echo "=== Running Markdown Verification Suite ===\n\n";

// TEST 1: Plain-text answer
$plain = "This is a simple plain text answer with no markdown formatting.";
$html1 = render_markdown($plain);
assertTest("Test 1: Plain text renders cleanly", 
    strpos($html1, "This is a simple plain text answer") !== false 
    && strpos($html1, "<h") === false 
    && strpos($html1, "<ul") === false,
    "Output: " . $html1
);

// TEST 2: Detailed answer with headings (h1-h4), bold, italic, and lists
$detailed = "#### Overview of Operating Systems\n"
          . "An operating system manages computer hardware.\n\n"
          . "### Key Components\n"
          . "- **Kernel**: The core of the operating system.\n"
          . "* **Process Manager**: Handles execution threads.\n\n"
          . "---\n\n"
          . "#### Execution Steps\n"
          . "1. *Fetch* the instruction from memory.\n"
          . "2. *Decode* the instruction.\n"
          . "3. *Execute* the instruction.\n";
$html2 = render_markdown($detailed);
assertTest("Test 2A: Level 4 heading (####) converted to h4 tag",
    strpos($html2, '<h4 class="markdown-h4">Overview of Operating Systems</h4>') !== false,
    "Output: " . $html2
);
assertTest("Test 2B: Level 3 heading (###) converted to h3 tag",
    strpos($html2, '<h3 class="markdown-h3">Key Components</h3>') !== false,
    "Output: " . $html2
);
assertTest("Test 2C: Unordered list bullets (- and *) grouped into single ul",
    strpos($html2, '<ul class="markdown-ul"><li class="markdown-li"><strong>Kernel</strong>: The core of the operating system.</li><li class="markdown-li"><strong>Process Manager</strong>: Handles execution threads.</li></ul>') !== false,
    "Output: " . $html2
);
assertTest("Test 2D: Horizontal rule (---) converted to hr tag",
    strpos($html2, '<hr class="markdown-hr">') !== false && strpos($html2, '---') === false,
    "Output: " . $html2
);
assertTest("Test 2E: Ordered list items (1., 2., 3.) converted into ol",
    strpos($html2, '<ol class="markdown-ol"><li class="markdown-li"><em>Fetch</em> the instruction from memory.</li>') !== false,
    "Output: " . $html2
);
assertTest("Test 2F: No raw #### or ** or stray * remains",
    strpos($html2, '####') === false && strpos($html2, '**') === false,
    "Output: " . $html2
);

// TEST 3: Formulas and technical notation
$technical = "The algorithm runtime is O(n * log(n)) and memory is O(n).\n"
           . "Formula: E = m * c^2, and \Delta x \ge \hbar / (2 * \Delta p).\n"
           . "Condition: x != y && a <= b -> result.\n";
$html3 = render_markdown($technical);
assertTest("Test 3A: Multiplication asterisks ' * ' preserved in formulas",
    strpos($html3, 'O(n * log(n))') !== false && strpos($html3, 'm * c^2') !== false,
    "Output: " . $html3
);
assertTest("Test 3B: Technical comparison operators preserved",
    strpos($html3, 'x != y') !== false && strpos($html3, '&amp;&amp;') !== false && strpos($html3, '&lt;=') !== false,
    "Output: " . $html3
);

// TEST 4: Code blocks and special characters
$codeText = "Here is a Python function:\n"
          . "```python\n"
          . "def calculate(a, b):\n"
          . "    # Notice: a * b is not italic!\n"
          . "    return a * b + 42\n"
          . "```\n"
          . "Use the variable `int max_val = 100;` in your loop.\n";
$html4 = render_markdown($codeText);
assertTest("Test 4A: Code block preserved inside pre and code tags",
    strpos($html4, '<pre class="markdown-code"><code>def calculate(a, b):') !== false
    && strpos($html4, '# Notice: a * b is not italic!') !== false,
    "Output: " . $html4
);
assertTest("Test 4B: No markdown formatting executed inside code block",
    strpos($html4, '<em>') === false,
    "Output: " . $html4
);
assertTest("Test 4C: Inline code rendered with markdown-inline-code class",
    strpos($html4, '<code class="markdown-inline-code">int max_val = 100;</code>') !== false,
    "Output: " . $html4
);

// TEST 5: Decorative symbols and excessive blank lines
$symbols = "★ Important Note\n\n\n\n\n\n"
         . "◆ Point 1: ✓ Verified\n"
         . "● Point 2: ➔ Next step\n\n"
         . "---\n\n";
$cleaned = clean_markdown_symbols($symbols);
assertTest("Test 5A: Decorative symbols removed by clean_markdown_symbols",
    strpos($cleaned, '★') === false 
    && strpos($cleaned, '◆') === false 
    && strpos($cleaned, '●') === false 
    && strpos($cleaned, '✓') === false 
    && strpos($cleaned, '➔') === false,
    "Cleaned: " . $cleaned
);
assertTest("Test 5B: Excessive blank lines collapsed",
    strpos($cleaned, "\n\n\n") === false,
    "Cleaned: " . $cleaned
);
assertTest("Test 5C: Trailing horizontal divider stripped",
    !preg_match('/\n---$/', $cleaned),
    "Cleaned: " . $cleaned
);

// TEST 6: XSS Protection & HTML Escaping
$xss = "<script>alert('XSS')</script>\n<b onmouseover=alert(1)>Test</b>\n<iframe src=\"javascript:alert(1)\"></iframe>";
$html6 = render_markdown($xss);
assertTest("Test 6: Raw HTML and script tags escaped safely without unescaped tags",
    strpos($html6, '<script>') === false 
    && strpos($html6, '<b ') === false
    && strpos($html6, '<iframe') === false
    && strpos($html6, '&lt;script&gt;') !== false
    && strpos($html6, '&lt;b onmouseover=alert(1)&gt;') !== false,
    "Output: " . $html6
);

// TEST 7: Bold text inside Markdown tables
$tableMarkdown = <<<MD
| Factor | Open Source | Proprietary |
| --- | --- | --- |
| Security | **High** | **Weak** |
| Control | **High** | __Weak__ |
| Challenges | None | **assumed problems** |
MD;
$html7 = render_markdown($tableMarkdown);
assertTest("Test 7A: Bold **High** converted to <strong>High</strong> in table",
    strpos($html7, '<td><strong>High</strong></td>') !== false,
    "Output: " . $html7
);
assertTest("Test 7B: Bold **Weak** converted to <strong>Weak</strong> in table",
    strpos($html7, '<td><strong>Weak</strong></td>') !== false,
    "Output: " . $html7
);
assertTest("Test 7C: Bold __Weak__ converted to <strong>Weak</strong> in table",
    strpos($html7, '<td><strong>Weak</strong></td>') !== false,
    "Output: " . $html7
);
assertTest("Test 7D: Bold **assumed problems** converted to <strong>assumed problems</strong> in table",
    strpos($html7, '<td><strong>assumed problems</strong></td>') !== false,
    "Output: " . $html7
);
assertTest("Test 7E: No raw ** or __ left in table output",
    strpos($html7, '**') === false && strpos($html7, '__') === false,
    "Output: " . $html7
);

// TEST 8: Bold text inside table headers and inline code/italic in table
$tableComplex = <<<MD
| **Metric Name** | *Expected Value* | `Data Type` |
| --- | --- | --- |
| **Max Memory** | *512 MB* | `integer` |
MD;
$html8 = render_markdown($tableComplex);
assertTest("Test 8A: Bold headers in table rendered with strong",
    strpos($html8, '<th><strong>Metric Name</strong></th>') !== false,
    "Output: " . $html8
);
assertTest("Test 8B: Italic and inline code inside table rendered cleanly",
    strpos($html8, '<td><em>512 MB</em></td>') !== false && strpos($html8, '<code class="markdown-inline-code">integer</code>') !== false,
    "Output: " . $html8
);

// TEST 9: Bold text in normal paragraphs and multi-line bold
$paraMarkdown = "System risks include **assumed problems** with **High** severity and __Weak__ defenses.\n"
              . "Also verified: **assumed\nproblems** across a line break.";
$html9 = render_markdown($paraMarkdown);
assertTest("Test 9A: Paragraph bold **assumed problems** rendered as strong",
    strpos($html9, '<strong>assumed problems</strong>') !== false,
    "Output: " . $html9
);
assertTest("Test 9B: Paragraph bold __Weak__ rendered as strong",
    strpos($html9, '<strong>Weak</strong>') !== false,
    "Output: " . $html9
);
assertTest("Test 9C: Multi-line bold **assumed\\nproblems** rendered as strong",
    strpos($html9, '<strong>assumed<br />' . "\n" . 'problems</strong>') !== false || strpos($html9, '<strong>assumed') !== false,
    "Output: " . $html9
);

// TEST 10: XSS inside table cell
$xssTable = <<<MD
| Column A | Column B |
| --- | --- |
| Safe text | <script>alert('table-xss')</script> |
MD;
$html10 = render_markdown($xssTable);
assertTest("Test 10: XSS inside table cell escaped safely",
    strpos($html10, '<script>') === false && strpos($html10, '&lt;script&gt;alert') !== false,
    "Output: " . $html10
);

echo "\n" . ($allPassed ? "=== ALL TESTS PASSED! ===" : "=== SOME TESTS FAILED! ===") . "\n";
exit($allPassed ? 0 : 1);
