<?php
/**
 * Multi-Format Document Upload & Text Extraction Page
 * AI-Based Study Assistant (PDF, DOCX, TXT, CSV, XLSX, PPTX)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/document-extractor.php';

// Enforce authentication
require_login();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? 'General');
    $pageCount = (int) ($_POST['page_count'] ?? 0);
    $extractedText = trim($_POST['extracted_text'] ?? '');

    // Server-Side Upload Validation
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErr = $_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
            $error = 'File is too large. Maximum allowed size is 50 MB.';
        } else {
            $error = 'Please select a valid document file to upload.';
        }
    } else {
        $file = $_FILES['pdf_file'];
        $originalFilename = basename($file['name']);
        $fileSize = $file['size'];
        $tmpPath = $file['tmp_name'];

        // 1. File Size Check (Max 50 MB)
        if ($fileSize > MAX_FILE_SIZE) {
            $error = 'File exceeds the maximum size limit of 50 MB.';
        } else {
            // 2. Extension Check
            $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                $error = 'Invalid file type. Allowed formats: .pdf, .docx, .txt, .csv, .xlsx, .pptx';
            } else {
                // 3. Server-Side MIME Type Validation
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpPath) ?: 'application/octet-stream';
                finfo_close($finfo);

                // Generate Unique Server Filename
                $storedFilename = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $targetPath = UPLOAD_DIR . $storedFilename;
                $relativeFilePath = 'uploads/' . $storedFilename;

                // Move uploaded file to uploads/ directory
                if (move_uploaded_file($tmpPath, $targetPath)) {
                    $textFilePath = UPLOAD_DIR . $storedFilename . '.txt';
                    $finalItemCount = $pageCount;

                    // Server-side extraction for non-PDFs or PDF fallback
                    if ($ext !== 'pdf' || empty($extractedText)) {
                        $extractionRes = extract_document_text($targetPath, $ext, $textFilePath);
                        if ($extractionRes['success']) {
                            $extractedText = $extractionRes['text'];
                            $finalItemCount = $extractionRes['item_count'];
                        }
                    }

                    // Save extracted text to companion file
                    file_put_contents($textFilePath, $extractedText);

                    // Status determination
                    $status = (strlen($extractedText) > 20) ? 'completed' : 'uploaded';

                    try {
                        $pdo = getDBConnection();
                        $stmt = $pdo->prepare("
                            INSERT INTO pdf_documents 
                            (user_id, original_filename, stored_filename, file_path, file_size, page_count, subject, file_type, mime_type, status, uploaded_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $userId,
                            $originalFilename,
                            $storedFilename,
                            $relativeFilePath,
                            $fileSize,
                            $finalItemCount,
                            empty($subject) ? 'General' : $subject,
                            $ext,
                            $mimeType,
                            $status
                        ]);

                        $docId = $pdo->lastInsertId();

                        $icon = get_format_icon($ext);
                        set_flash_message("{$icon} Document '$originalFilename' uploaded & extracted successfully!", "success");
                        header('Location: ' . url('my-pdfs.php'));
                        exit();

                    } catch (PDOException $e) {
                        error_log("Doc DB Insert Error: " . $e->getMessage());
                        @unlink($targetPath);
                        @unlink($textFilePath);
                        $error = 'Database error while saving document metadata. Please try again.';
                    }
                } else {
                    $error = 'Failed to move uploaded file to destination folder. Please check directory permissions.';
                }
            }
        }
    }
}

$pageTitle = 'Upload Document';
$pageSubTitle = 'Select and upload study materials (PDF, Word, TXT, CSV, Excel, PowerPoint)';

include __DIR__ . '/includes/header.php';
?>

<div class="page-card" style="max-width: 800px; margin: 0 auto;">
    <div class="page-header">
        <h2 class="page-title">📤 Upload Study Document</h2>
        <p class="page-subtitle">Upload lecture notes, textbooks, spreadsheets, or presentations (PDF, DOCX, TXT, CSV, XLSX, PPTX — up to 50 MB)</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <div class="alert-icon">⚠</div>
            <div class="alert-body"><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- Format badges info bar -->
    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
        <span class="tag-item">📄 PDF</span>
        <span class="tag-item">📝 DOCX (Word)</span>
        <span class="tag-item">📑 TXT (Text)</span>
        <span class="tag-item">📊 CSV</span>
        <span class="tag-item">📈 XLSX (Excel)</span>
        <span class="tag-item">📊 PPTX (PowerPoint)</span>
    </div>

    <form id="uploadForm" action="<?= url('upload-pdf.php') ?>" method="POST" enctype="multipart/form-data">
        <!-- Hidden fields for extracted text & item/page count -->
        <input type="hidden" id="page_count" name="page_count" value="0">
        <textarea id="extracted_text" name="extracted_text" style="display: none;"></textarea>

        <!-- Subject Field -->
        <div class="form-group">
            <label for="subject" class="form-label">Subject / Course Module</label>
            <input type="text" id="subject" name="subject" class="form-control" placeholder="e.g. Data Structures, Operating Systems, DBMS" list="subject-suggestions" required>
            <datalist id="subject-suggestions">
                <option value="Data Structures & Algorithms">
                <option value="Operating Systems">
                <option value="Database Management System">
                <option value="Computer Networks">
                <option value="Software Engineering">
                <option value="Object Oriented Programming with C++">
                <option value="Java Programming">
                <option value="Web Technologies">
                <option value="Python Programming">
            </datalist>
            <span class="form-text">Categorizing your documents by subject helps organize your library and study notes.</span>
        </div>

        <!-- Custom Drag & Drop File Upload Container -->
        <div class="form-group">
            <label class="form-label">Select Document File</label>
            <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('pdf_file').click()">
                <div class="dropzone-icon" id="formatIcon">📂</div>
                <h3 class="dropzone-title">Click to Browse or Drag & Drop Document here</h3>
                <p class="dropzone-sub">Supports PDF, DOCX, TXT, CSV, XLSX, PPTX (Maximum 50 MB)</p>
                <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 12px;" onclick="event.stopPropagation(); document.getElementById('pdf_file').click();">
                    📂 Browse Files
                </button>
                <input type="file" id="pdf_file" name="pdf_file" accept=".pdf,.docx,.txt,.csv,.xlsx,.pptx" style="display: none;" required>
            </div>
        </div>

        <!-- File Details Card (Hidden until file selected) -->
        <div id="fileInfoBox" class="file-info-box" style="display: none;">
            <div class="file-info-icon" id="fileTypeBadgeIcon">📑</div>
            <div class="file-info-details">
                <div id="fileNameDisplay" class="file-info-name">document.docx</div>
                <div id="fileSizeDisplay" class="file-info-size">0.00 MB</div>
                <div id="filePageDisplay" class="file-info-pages">Status: Ready for upload & extraction</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline" style="color: var(--accent-rose); border-color: #fca5a5;" onclick="resetFileSelection()">
                ✕ Remove
            </button>
        </div>

        <!-- extraction warning / info alert -->
        <div id="extractionNotice" class="alert alert-info" style="display: none; margin-top: 15px;">
            <div class="alert-icon">ℹ</div>
            <div id="extractionNoticeText" class="alert-body">Extracting text...</div>
        </div>

        <!-- Extraction Progress Indicator -->
        <div id="progressContainer" style="display: none; margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; color: var(--slate-700); margin-bottom: 6px;">
                <span id="progressStatus">Extracting text...</span>
                <span id="progressPercent">0%</span>
            </div>
            <div class="progress-bar-track">
                <div id="progressBarFill" class="progress-bar-fill" style="width: 0%;"></div>
            </div>
        </div>

        <!-- Submit Button -->
        <div style="margin-top: 24px;">
            <button type="submit" id="submitBtn" class="btn btn-primary btn-block" style="padding: 14px;" disabled>
                ⚡ Upload & Extract Text &rarr;
            </button>
        </div>
    </form>
</div>

<!-- Load Local PDF.js Library for PDF files -->
<script src="<?= url('pdfjs/pdf.min.js') ?>"></script>

<script>
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= url("pdfjs/pdf.worker.min.js") ?>';
}

const fileInput = document.getElementById('pdf_file');
const dropzone = document.getElementById('dropzone');
const fileInfoBox = document.getElementById('fileInfoBox');
const fileNameDisplay = document.getElementById('fileNameDisplay');
const fileSizeDisplay = document.getElementById('fileSizeDisplay');
const filePageDisplay = document.getElementById('filePageDisplay');
const fileTypeBadgeIcon = document.getElementById('fileTypeBadgeIcon');
const submitBtn = document.getElementById('submitBtn');
const uploadForm = document.getElementById('uploadForm');
const progressContainer = document.getElementById('progressContainer');
const progressStatus = document.getElementById('progressStatus');
const progressPercent = document.getElementById('progressPercent');
const progressBarFill = document.getElementById('progressBarFill');
const extractionNotice = document.getElementById('extractionNotice');
const extractionNoticeText = document.getElementById('extractionNoticeText');

const allowedExts = ['pdf', 'docx', 'txt', 'csv', 'xlsx', 'pptx'];

let selectedFile = null;
let extractedTextContent = '';
let extractedTotalPages = 0;
let isExtractionComplete = false;

fileInput.addEventListener('change', (e) => {
    if (e.target.files && e.target.files[0]) {
        handleFileSelected(e.target.files[0]);
    }
});

['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('drag-over');
    }, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('drag-over');
    }, false);
});

dropzone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    if (dt.files && dt.files[0]) {
        fileInput.files = dt.files;
        handleFileSelected(dt.files[0]);
    }
});

function getFormatIcon(ext) {
    switch(ext) {
        case 'docx': return '📝';
        case 'txt': return '📑';
        case 'csv': return '📊';
        case 'xlsx': return '📈';
        case 'pptx': return '📊';
        case 'pdf': default: return '📄';
    }
}

function handleFileSelected(file) {
    selectedFile = file;

    const fileName = file.name;
    const ext = fileName.split('.').pop().toLowerCase();
    if (!allowedExts.includes(ext)) {
        alert('Invalid file format. Supported formats: .pdf, .docx, .txt, .csv, .xlsx, .pptx');
        resetFileSelection();
        return;
    }

    const maxSize = 50 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('File size exceeds the 50 MB limit.');
        resetFileSelection();
        return;
    }

    fileTypeBadgeIcon.textContent = getFormatIcon(ext);
    fileNameDisplay.textContent = fileName;
    fileSizeDisplay.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
    fileInfoBox.style.display = 'flex';
    extractionNotice.style.display = 'none';

    submitBtn.disabled = false;

    if (ext === 'pdf') {
        filePageDisplay.textContent = 'Analyzing pages with PDF.js...';
        extractPDFText(file);
    } else {
        filePageDisplay.textContent = `Format: .${ext.toUpperCase()} (Server extraction ready)`;
        isExtractionComplete = true;
    }
}

function resetFileSelection() {
    selectedFile = null;
    fileInput.value = '';
    fileInfoBox.style.display = 'none';
    progressContainer.style.display = 'none';
    extractionNotice.style.display = 'none';
    submitBtn.disabled = true;
    extractedTextContent = '';
    extractedTotalPages = 0;
    isExtractionComplete = false;
}

async function extractPDFText(file) {
    if (typeof pdfjsLib === 'undefined') {
        filePageDisplay.textContent = 'PDF.js ready for upload.';
        isExtractionComplete = true;
        return;
    }

    progressContainer.style.display = 'block';
    updateProgress(5, 'Loading PDF document...');

    try {
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

        extractedTotalPages = pdf.numPages;
        filePageDisplay.textContent = `Pages: ${extractedTotalPages} detected`;
        document.getElementById('page_count').value = extractedTotalPages;

        let fullText = '';

        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const textContent = await page.getTextContent();
            const pageText = textContent.items.map(item => item.str).join(' ');
            fullText += `--- Page ${i} ---\n` + pageText + '\n\n';

            const percent = Math.round((i / pdf.numPages) * 90) + 5;
            updateProgress(percent, `Extracted page ${i} of ${pdf.numPages}...`);
        }

        extractedTextContent = fullText.trim();
        document.getElementById('extracted_text').value = extractedTextContent;
        isExtractionComplete = true;

        updateProgress(100, 'Text extraction complete!');

        if (extractedTextContent.length < 20) {
            extractionNoticeText.textContent = '⚠️ Warning: Scanned or image-only PDF detected. Minimal text could be extracted.';
            extractionNotice.className = 'alert alert-danger';
            extractionNotice.style.display = 'flex';
        } else {
            extractionNoticeText.textContent = `✓ Successfully extracted text from ${extractedTotalPages} pages (${(extractedTextContent.length).toLocaleString()} characters).`;
            extractionNotice.className = 'alert alert-success';
            extractionNotice.style.display = 'flex';
        }

        setTimeout(() => {
            progressContainer.style.display = 'none';
        }, 1200);

    } catch (err) {
        console.error('PDF.js Extraction Error:', err);
        filePageDisplay.textContent = 'Error reading PDF text';
        extractionNoticeText.textContent = '⚠️ Could not read text inside PDF.';
        extractionNotice.className = 'alert alert-danger';
        extractionNotice.style.display = 'flex';
        progressContainer.style.display = 'none';
        isExtractionComplete = true;
    }
}

function updateProgress(percent, text) {
    progressPercent.textContent = percent + '%';
    progressBarFill.style.width = percent + '%';
    progressStatus.textContent = text;
}

uploadForm.addEventListener('submit', (e) => {
    if (!selectedFile) {
        e.preventDefault();
        alert('Please select a document file first.');
        return;
    }

    if (!isExtractionComplete) {
        e.preventDefault();
        alert('Text extraction is still processing. Please wait a moment.');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '⌛ Uploading & Extracting...';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
