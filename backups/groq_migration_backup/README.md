# AI-Based PDF Notes Summarizer and Question Generator

> **BCA 3rd Semester Major Web Application Project**  
> Built using PHP 8, MySQL (PDO), Vanilla JavaScript, and CSS3.

---

## 📌 Project Overview
**AI-Based PDF Notes Summarizer and Question Generator** is a web-based educational tool designed specifically for BCA students. It allows students to upload PDF lecture notes or textbook chapters, automatically extract key concepts, generate short summaries, 2/5/10 mark exam questions, practice interactive MCQs, and query documents directly.

---

## 🚀 Phase 1 Completed Features
- [x] Clean modular project structure (`config/`, `includes/`, `assets/`, `uploads/`)
- [x] Database Schema with relational integrity (`users`, `pdf_documents`, `generated_content`)
- [x] Safe MySQL PDO database connection helper (`config/database.php`)
- [x] User Registration with secure `password_hash()` encryption (`signup.php`)
- [x] User Authentication with `password_verify()` & PHP Sessions (`login.php`)
- [x] Secure Logout Handler (`logout.php`)
- [x] Protected Student Dashboard (`dashboard.php`)
- [x] Responsive, Modern CSS Styling System (`assets/css/style.css`)
- [x] Shared UI layout components (`header.php`, `sidebar.php`, `footer.php`)

---

## 📁 Directory & File Structure
```
ai_notes_summarizer/
│
├── config/
│   ├── config.php          # Global application settings, helper functions & session init
│   └── database.php        # MySQL PDO database connection module
│
├── includes/
│   ├── auth.php            # Session validation, login checks, user fetching & logout helpers
│   ├── header.php          # Shared top header bar HTML component
│   ├── sidebar.php         # Shared navigation sidebar component
│   └── footer.php          # Shared page footer HTML component
│
├── assets/
│   ├── css/
│   │   └── style.css       # Complete custom vanilla CSS stylesheet (Indigo/Slate design)
│   └── js/
│       └── app.js          # Mobile sidebar toggle & auto-dismiss alert handlers
│
├── uploads/
│   └── .htaccess           # Prevents directory indexing & secures file downloads
│
├── index.php               # Landing / hero page for unauthenticated visitors
├── signup.php              # User registration page
├── login.php               # User authentication page
├── logout.php              # User logout handler
├── dashboard.php           # Authenticated student dashboard
├── upload-pdf.php          # PDF upload view (Phase 2 stub)
├── my-pdfs.php             # User document library (Phase 2 stub)
├── exam-preparation.php    # Categorized exam preparation notes (Phase 4 stub)
├── mcqs-practice.php       # Interactive MCQ quiz generator (Phase 4 stub)
├── ask-from-pdf.php        # AI document QA view (Phase 5 stub)
├── pdf-results.php         # Generated summary & questions viewer (Phase 4 stub)
├── schema.sql              # Database creation & table structure script
└── README.md               # Project setup and documentation guide
```

---

## 🗄️ Database Tables (`ai_notes_summarizer`)

1. **`users`**
   - `id` (INT, Primary Key, Auto Increment)
   - `full_name` (VARCHAR)
   - `email` (VARCHAR, Unique)
   - `password_hash` (VARCHAR)
   - `course` (VARCHAR)
   - `created_at` (TIMESTAMP)

2. **`pdf_documents`**
   - `id` (INT, Primary Key, Auto Increment)
   - `user_id` (INT, Foreign Key -> `users.id`)
   - `original_filename` (VARCHAR)
   - `stored_filename` (VARCHAR)
   - `file_path` (VARCHAR)
   - `file_size` (INT)
   - `page_count` (INT)
   - `subject` (VARCHAR)
   - `status` (ENUM: 'uploaded', 'processing', 'completed', 'error')
   - `uploaded_at` (TIMESTAMP)

3. **`generated_content`**
   - `id` (INT, Primary Key, Auto Increment)
   - `pdf_id` (INT, Foreign Key -> `pdf_documents.id`)
   - `user_id` (INT, Foreign Key -> `users.id`)
   - `content_type` (ENUM: 'summary', 'questions', 'exam_prep', 'mcqs', 'qna')
   - `content_data` (LONGTEXT)
   - `created_at` (TIMESTAMP)

---

## 🛠️ Step-by-Step Setup Guide (XAMPP Environment)

### 1. Import `schema.sql` into MySQL
1. Launch **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. Open your web browser and navigate to `http://localhost/phpmyadmin`.
3. Click on the **SQL** tab in phpMyAdmin.
4. Open `schema.sql` from your project folder, copy its contents, paste into the text area, and click **Go**.  
   *Alternatively:* Click on **Import**, choose `schema.sql`, and click **Import**.

### 2. Configure Local Credentials
The required configuration files are in the root `config/` directory. The checked-in `config/config.example.php` is a safe template; `config/config.php` is ignored by Git. On a fresh clone where `config/config.php` is absent, create it with `Copy-Item config\config.example.php config\config.php`. Secrets are read from the Apache/PHP environment; this app does not load `.env` automatically, and secrets must not be added to PHP source files.

Set these variables for Apache:

| Variable | Value |
| --- | --- |
| `DB_HOST` | `127.0.0.1` (local TCP connection) |
| `DB_PORT` | `3307` for this XAMPP installation, based on `C:\xampp\mysql\bin\my.ini` |
| `DB_NAME` | `ai_notes_summarizer` (schema default) |
| `DB_USER` | `root` (XAMPP default; a dedicated local user is also supported) |
| `DB_PASSWORD` | Your current MySQL password; leave empty only if your local account has no password |
| `DB_CHARSET` | `utf8mb4` |
| `GEMINI_API_KEY` | Your Google Gemini API key; needed only for AI generation features |

For XAMPP on Windows, add `SetEnv DB_HOST "127.0.0.1"`, `SetEnv DB_PORT "3307"`, `SetEnv DB_NAME "ai_notes_summarizer"`, `SetEnv DB_USER "root"`, `SetEnv DB_PASSWORD "your-local-password"`, and `SetEnv GEMINI_API_KEY "your-api-key"` to the Apache configuration outside this project (for example `C:\xampp\apache\conf\extra\httpd-xampp.conf`), then restart Apache. Replace the password and key locally; do not commit them. `DB_PASS` is accepted as a compatibility fallback for `DB_PASSWORD`.

The legacy source copy under `includes/config/` now forwards to `config/` and contains no credentials. Any key or password previously stored there should be rotated, especially if this folder or its history was shared.

### 3. Create or Check the Database
Start MySQL in XAMPP and open `http://localhost/phpmyadmin`. If the `ai_notes_summarizer` database and its tables do not already exist, import `schema.sql`. It uses `CREATE DATABASE IF NOT EXISTS` and `CREATE TABLE IF NOT EXISTS`; it does not drop tables or delete existing rows. Check phpMyAdmin before importing if this project already contains user data.

### 4. How to Run the Project
1. Ensure this project folder is located inside your XAMPP `htdocs` directory:
   `C:\xampp\htdocs\ai_notes_summarizer\`
2. Launch `C:\xampp\xampp-control.exe` and start Apache and MySQL. Or, if both services are already running, skip this step.
3. Open your browser and visit:
   `http://localhost/ai_notes_summarizer/`

---

## 🧪 Testing Phase 1 (Signup → Login → Dashboard → Logout)

1. **Visit Landing Page**: Go to `http://localhost/ai_notes_summarizer/`. Click **Create Account**.
2. **Register a User (Signup)**:
   - Fill in Full Name (e.g. `Rahul Sharma`), Email (`rahul@gmail.com`), Course (`BCA 3rd Sem`), and Password (`password123`).
   - Click **Register Account**. You will be redirected to `login.php` with a success flash message.
3. **Log In**:
   - Enter `rahul@gmail.com` and `password123`.
   - Click **Log In**. You will be securely logged in and redirected to `dashboard.php`.
4. **Explore Dashboard**:
   - Verify the welcome greeting displays "Welcome back, Rahul Sharma!".
   - Check stats cards and quick action cards.
   - Click navigation links in the sidebar to verify route protection and shared layouts.
5. **Log Out**:
   - Click **Logout** at the bottom of the sidebar or top menu.
   - You will be logged out and returned to the login page with an informational alert.
