-- =========================================================
-- AI-Based PDF Notes Summarizer and Question Generator
-- Database Schema for MySQL / MariaDB (XAMPP)
-- =========================================================

CREATE DATABASE IF NOT EXISTS `ai_notes_summarizer` 
  DEFAULT CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `ai_notes_summarizer`;

-- ---------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `course` VARCHAR(100) DEFAULT 'BCA 3rd Sem',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: pdf_documents
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pdf_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT NOT NULL,
  `page_count` INT DEFAULT 0,
  `subject` VARCHAR(100) DEFAULT NULL,
  `file_type` VARCHAR(10) NOT NULL DEFAULT 'pdf',
  `mime_type` VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
  `status` ENUM('uploaded', 'processing', 'completed', 'error') DEFAULT 'uploaded',
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pdf_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: generated_content
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `generated_content` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pdf_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `content_type` ENUM('summary', 'short_notes', 'questions', 'important_questions', 'exam_prep', 'mcqs', 'qna') NOT NULL,
  `content_data` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_content_pdf` FOREIGN KEY (`pdf_id`) REFERENCES `pdf_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_content_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
