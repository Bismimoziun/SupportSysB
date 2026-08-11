-- ============================================================
-- G&G Support Portal — database_setup.sql
-- Run this ONCE in phpMyAdmin to set up the entire database.
-- Steps:
--   1. Open phpMyAdmin → http://localhost/phpmyadmin
--   2. Click "knowledgebase" database (create it first if needed)
--   3. Click the SQL tab
--   4. Paste ALL of this and click Go
-- ============================================================

-- Make sure we are using the right database
CREATE DATABASE IF NOT EXISTS `knowledgebase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `knowledgebase`;

-- ── Drop tables in safe order ─────────────────────────────────
DROP TABLE IF EXISTS `flags`;
DROP TABLE IF EXISTS `solutions`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ── users ─────────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(120)    NOT NULL,
  `username`   VARCHAR(60)     NOT NULL UNIQUE,
  `email`      VARCHAR(150)    DEFAULT NULL,
  `password`   VARCHAR(255)    NOT NULL,
  `role`       ENUM('user','admin','system_admin') NOT NULL DEFAULT 'user',
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── categories ───────────────────────────────────────────────
CREATE TABLE `categories` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120)    NOT NULL,
  `parent_id`  INT UNSIGNED    DEFAULT NULL,
  `created_by` INT UNSIGNED    DEFAULT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── solutions ─────────────────────────────────────────────────
CREATE TABLE `solutions` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `question`       TEXT          NOT NULL,
  `answer`         LONGTEXT      NOT NULL,
  `category_id`    INT UNSIGNED  DEFAULT NULL,
  `submitted_by`   INT UNSIGNED  DEFAULT NULL,
  `status`         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requires_admin` TINYINT(1)   NOT NULL DEFAULT 0,
  `verified_by`    INT UNSIGNED  DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── announcements ─────────────────────────────────────────────
CREATE TABLE `announcements` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(200)  NOT NULL,
  `content`    TEXT          DEFAULT NULL,
  `priority`   INT           NOT NULL DEFAULT 10,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED  DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── flags ─────────────────────────────────────────────────────
CREATE TABLE `flags` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `question`    TEXT          NOT NULL,
  `raised_by`   INT UNSIGNED  DEFAULT NULL,
  `status`      ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
  `resolved_by` INT UNSIGNED  DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA
-- Passwords are bcrypt of "Test@1234"
-- ============================================================

INSERT INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `is_active`) VALUES
('System Administrator', 'sysadmin', 'sysadmin@gg.gov', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'system_admin', 1),
('Portal Admin',         'admin',    'admin@gg.gov',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',        1),
('Regular User',         'user1',    'user1@gg.gov',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user',         1);

-- NOTE: The hash above is Laravel's default test hash for "password".
-- IMPORTANT: You MUST replace these hashes with real bcrypt hashes of "Test@1234".
-- To generate correct hashes, create a file C:\xampp\htdocs\hash.php with:
--
--   <?php
--   echo password_hash('Test@1234', PASSWORD_BCRYPT);
--
-- Visit http://localhost/hash.php, copy the hash, and run this UPDATE:
--
--   UPDATE users SET password = 'PASTE_HASH_HERE';
--
-- Then delete hash.php immediately.

INSERT INTO `categories` (`name`, `parent_id`, `created_by`) VALUES
('Human Resources', NULL, 1),
('IT Support',      NULL, 1),
('Finance',         NULL, 1),
('General',         NULL, 1);

-- Sub-categories (parent_id references the IDs above — adjust if auto_increment starts differently)
INSERT INTO `categories` (`name`, `parent_id`, `created_by`) VALUES
('Leave & Attendance', 1, 1),
('Payroll & Benefits',  1, 1),
('Hardware Issues',     2, 1),
('Software & Systems',  2, 1),
('Expenses & Claims',   3, 1);

INSERT INTO `announcements` (`title`, `content`, `priority`, `is_active`, `created_by`) VALUES
('System Maintenance',    'Scheduled maintenance on Saturday 10pm–2am. Portal may be unavailable.',  1, 1, 1),
('New Policy Update',     'Please review the updated leave policy on the HR portal before Friday.',   2, 1, 1),
('Welcome to G&G Portal', 'Search for solutions to common queries. Cannot find an answer? Flag it!', 3, 1, 1);

INSERT INTO `solutions` (`question`, `answer`, `category_id`, `submitted_by`, `status`, `requires_admin`) VALUES
('How do I apply for annual leave?',
 '<p>To apply for annual leave:</p><ol><li>Log in to the HR portal</li><li>Go to <strong>Leave Management</strong></li><li>Click <strong>New Leave Request</strong></li><li>Select <em>Annual Leave</em> and choose your dates</li><li>Submit for manager approval</li></ol><p>Leave requests must be submitted at least 3 working days in advance.</p>',
 5, 1, 'approved', 0),

('How do I reset my network password?',
 '<p>To reset your network password:</p><ul><li>Press <strong>Ctrl+Alt+Delete</strong> and choose <em>Change Password</em></li><li>Or visit the self-service portal at <strong>password.gg.gov</strong></li><li>If you are locked out, contact IT Support on extension 1234</li></ul>',
 8, 1, 'approved', 0),

('What is the expense claim submission deadline?',
 '<p>Expense claims must be submitted by the <strong>last working day of each month</strong>.</p><p>Late submissions will be processed in the following month cycle. Ensure all receipts are attached and amounts are in GBP.</p>',
 9, 1, 'approved', 0),

('How do I access the admin configuration panel?',
 '<p>The admin configuration panel is accessible only to IT Admin and System Admin roles.</p><p>Navigate to <strong>Settings → System Configuration</strong> from the admin dashboard.</p>',
 8, 1, 'approved', 1);
