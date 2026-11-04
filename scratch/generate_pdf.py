import os
import re
import subprocess

md_path = r"C:\xampp\htdocs\ai_notes_summarizer\PROJECT_REPORT.md"
html_path = r"C:\xampp\htdocs\ai_notes_summarizer\scratch\project_report.html"
pdf_path = r"C:\xampp\htdocs\ai_notes_summarizer\PROJECT_REPORT.pdf"

with open(md_path, "r", encoding="utf-8") as f:
    md_content = f.read()

# Simple markdown to HTML converter for report
lines = md_content.split("\n")
html_lines = []
in_table = False
in_code = False
table_header = False

def escape(t):
    return t.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")

for line in lines:
    stripped = line.strip()
    
    if stripped.startswith("```"):
        if in_code:
            html_lines.append("</pre>")
            in_code = False
        else:
            html_lines.append("<pre class='code-block'>")
            in_code = True
        continue
    
    if in_code:
        html_lines.append(escape(line))
        continue
        
    if stripped.startswith("|") and stripped.endswith("|"):
        cells = [c.strip() for c in stripped.split("|")[1:-1]]
        if not in_table:
            html_lines.append("<table class='report-table'>")
            in_table = True
            table_header = True
            html_lines.append("<thead><tr>")
            for c in cells:
                html_lines.append(f"<th>{escape(c)}</th>")
            html_lines.append("</tr></thead><tbody>")
            continue
        elif "---" in stripped:
            continue
        else:
            html_lines.append("<tr>")
            for c in cells:
                # bold
                val = escape(c)
                val = re.sub(r'\*\*(.*?)\*\*', r'<strong>\1</strong>', val)
                html_lines.append(f"<td>{val}</td>")
            html_lines.append("</tr>")
            continue
    else:
        if in_table:
            html_lines.append("</tbody></table>")
            in_table = False

    if stripped.startswith("# "):
        html_lines.append(f"<h1 class='doc-title'>{escape(stripped[2:])}</h1>")
    elif stripped.startswith("## "):
        html_lines.append(f"<h2 class='section-title'>{escape(stripped[3:])}</h2>")
    elif stripped.startswith("### "):
        html_lines.append(f"<h3 class='sub-title'>{escape(stripped[4:])}</h3>")
    elif stripped.startswith("#### "):
        html_lines.append(f"<h4 class='subsub-title'>{escape(stripped[5:])}</h4>")
    elif stripped == "---":
        html_lines.append("<hr class='divider'>")
    elif stripped.startswith("- "):
        val = escape(stripped[2:])
        val = re.sub(r'\*\*(.*?)\*\*', r'<strong>\1</strong>', val)
        val = re.sub(r'`(.*?)`', r'<code>\1</code>', val)
        html_lines.append(f"<li class='bullet-item'>{val}</li>")
    elif re.match(r'^\d+\.\s', stripped):
        val = re.sub(r'^\d+\.\s', '', stripped)
        val = escape(val)
        val = re.sub(r'\*\*(.*?)\*\*', r'<strong>\1</strong>', val)
        val = re.sub(r'`(.*?)`', r'<code>\1</code>', val)
        html_lines.append(f"<li class='num-item'>{val}</li>")
    elif stripped == "":
        html_lines.append("<p class='space'></p>")
    else:
        val = escape(line)
        val = re.sub(r'\*\*(.*?)\*\*', r'<strong>\1</strong>', val)
        val = re.sub(r'`(.*?)`', r'<code>\1</code>', val)
        html_lines.append(f"<p>{val}</p>")

if in_table:
    html_lines.append("</tbody></table>")

body_content = "\n".join(html_lines)

html_template = f"""<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>PROJECT REPORT - AI-Based PDF Notes Summarizer</title>
<style>
@page {{
    size: A4;
    margin: 20mm 15mm 20mm 15mm;
}}
body {{
    font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
    color: #1e293b;
    line-height: 1.6;
    font-size: 11pt;
    background: #fff;
}}
.doc-title {{
    color: #1e3a8a;
    font-size: 22pt;
    text-align: center;
    border-bottom: 2px solid #2563eb;
    padding-bottom: 10px;
    margin-top: 20px;
    margin-bottom: 5px;
}}
.section-title {{
    color: #1e40af;
    font-size: 15pt;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 5px;
    margin-top: 25px;
    page-break-after: avoid;
}}
.sub-title {{
    color: #0f172a;
    font-size: 12pt;
    margin-top: 15px;
    page-break-after: avoid;
}}
.report-table {{
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    font-size: 9.5pt;
    page-break-inside: avoid;
}}
.report-table th, .report-table td {{
    border: 1px solid #cbd5e1;
    padding: 7px 10px;
    text-align: left;
}}
.report-table th {{
    background-color: #f1f5f9;
    color: #0f172a;
    font-weight: bold;
}}
.report-table tr:nth-child(even) {{
    background-color: #f8fafc;
}}
.code-block {{
    background: #0f172a;
    color: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 8.5pt;
    overflow-x: auto;
    white-space: pre-wrap;
    page-break-inside: avoid;
}}
code {{
    background: #f1f5f9;
    color: #2563eb;
    padding: 2px 5px;
    border-radius: 4px;
    font-family: 'Consolas', monospace;
    font-size: 9pt;
}}
.divider {{
    border: 0;
    border-top: 1px solid #e2e8f0;
    margin: 20px 0;
}}
li {{
    margin-bottom: 4px;
}}
.space {{
    margin: 6px 0;
}}
</style>
</head>
<body>
{body_content}
</body>
</html>
"""

with open(html_path, "w", encoding="utf-8") as f:
    f.write(html_template)

print("HTML generated successfully:", html_path)

edge_exe = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
cmd = [
    edge_exe,
    "--headless",
    "--no-sandbox",
    "--disable-gpu",
    "--no-pdf-header-footer",
    f"--print-to-pdf={pdf_path}",
    f"file:///{html_path.replace(os.sep, '/')}"
]

print("Printing PDF via Edge headless...")
res = subprocess.run(cmd, capture_output=True, text=True)
if os.path.exists(pdf_path) and os.path.getsize(pdf_path) > 1000:
    print(f"SUCCESS: PDF generated ({os.path.getsize(pdf_path)} bytes) at: {pdf_path}")
else:
    print("Edge output:", res.stdout, res.stderr)
