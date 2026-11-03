<?php
/**
 * Test render_markdown() on tables with bold markers
 */

require_once __DIR__ . '/../includes/markdown.php';

$tableMarkdown = <<<MD
| Factor | Open Source | Proprietary |
| --- | --- | --- |
| Security | **High** | **Weak** |
| Control | **High** | __Weak__ |
| Challenges | None | **assumed problems** |
MD;

echo "--- Rendered Table with render_markdown() ---\n";
echo render_markdown($tableMarkdown) . "\n\n";

$paragraphMarkdown = "Here is a paragraph with **assumed problems** and __Weak__ impact and **High** priority.\n"
                   . "Also bold across line: **assumed\nproblems** in system.";

echo "--- Rendered Paragraph with render_markdown() ---\n";
echo render_markdown($paragraphMarkdown) . "\n";
