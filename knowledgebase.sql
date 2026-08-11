-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 02, 2026 at 06:23 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `knowledgebase`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 10,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `priority`, `is_active`, `created_by`, `created_at`) VALUES
(1, 'System Maintenance', 'Scheduled maintenance on Saturday 10pm–2am. Portal may be unavailable.', 1, 1, 1, '2026-05-29 04:00:08'),
(2, 'New Policy Update', 'Please review the updated leave policy on the HR portal before Friday.', 2, 1, 1, '2026-05-29 04:00:08'),
(3, 'Welcome to G&G Portal', 'Search for solutions to common queries. Cannot find an answer? Flag it!', 3, 1, 1, '2026-05-29 04:00:08'),
(4, 'Tommorrow is holiday', 'Tommorow is holiday due heavy rain', 1, 1, 1, '2026-06-01 08:00:47');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `parent_id`, `created_by`, `created_at`) VALUES
(1, 'Human Resources', NULL, 1, '2026-05-29 04:00:08'),
(2, 'IT Support', NULL, 1, '2026-05-29 04:00:08'),
(3, 'Finance', NULL, 1, '2026-05-29 04:00:08'),
(4, 'General', NULL, 1, '2026-05-29 04:00:08'),
(5, 'Leave & Attendance', 1, 1, '2026-05-29 04:00:08'),
(6, 'Payroll & Benefits', 1, 1, '2026-05-29 04:00:08'),
(7, 'Hardware Issues', 2, 1, '2026-05-29 04:00:08'),
(8, 'Software & Systems', 2, 1, '2026-05-29 04:00:08'),
(9, 'Expenses & Claims', 3, 1, '2026-05-29 04:00:08'),
(10, 'Holidays', 1, 1, '2026-06-01 09:43:45'),
(11, 'Holidays', 1, 1, '2026-06-01 09:47:07'),
(12, 'Holidays', 1, 1, '2026-06-01 09:47:58'),
(13, 'Holidays', 1, 1, '2026-06-01 09:47:59');

-- --------------------------------------------------------

--
-- Table structure for table `flags`
--

CREATE TABLE `flags` (
  `id` int(10) UNSIGNED NOT NULL,
  `question` text NOT NULL,
  `raised_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('open','resolved','ignored') NOT NULL DEFAULT 'open',
  `resolved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `round_robin_pointer`
--

CREATE TABLE `round_robin_pointer` (
  `level_id` int(10) UNSIGNED NOT NULL,
  `last_admin_index` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `round_robin_pointer`
--

INSERT INTO `round_robin_pointer` (`level_id`, `last_admin_index`) VALUES
(1, 0),
(2, 0),
(3, 0);

-- --------------------------------------------------------

--
-- Table structure for table `solutions`
--

CREATE TABLE `solutions` (
  `id` int(10) UNSIGNED NOT NULL,
  `question` text NOT NULL,
  `answer` longtext NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requires_admin` tinyint(1) NOT NULL DEFAULT 0,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `solutions`
--

INSERT INTO `solutions` (`id`, `question`, `answer`, `category_id`, `submitted_by`, `status`, `requires_admin`, `verified_by`, `created_at`, `updated_at`) VALUES
(1, 'How do I apply for annual leave?', '<p>To apply for annual leave:</p><ol><li>Log in to the HR portal</li><li>Go to <strong>Leave Management</strong></li><li>Click <strong>New Leave Request</strong></li><li>Select <em>Annual Leave</em> and choose your dates</li><li>Submit for manager approval</li></ol><p>Leave requests must be submitted at least 3 working days in advance.</p>', 5, 1, 'approved', 0, NULL, '2026-05-29 04:00:08', '2026-05-29 04:00:08'),
(2, 'How do I reset my network password?', '<p>To reset your network password:</p><ul><li>Press <strong>Ctrl+Alt+Delete</strong> and choose <em>Change Password</em></li><li>Or visit the self-service portal at <strong>password.gg.gov</strong></li><li>If you are locked out, contact IT Support on extension 1234</li></ul>', 8, 1, 'approved', 0, NULL, '2026-05-29 04:00:08', '2026-05-29 04:00:08'),
(3, 'What is the expense claim submission deadline?', '<p>Expense claims must be submitted by the <strong>last working day of each month</strong>.</p><p>Late submissions will be processed in the following month cycle. Ensure all receipts are attached and amounts are in GBP.</p>', 9, 1, 'approved', 0, NULL, '2026-05-29 04:00:08', '2026-05-29 04:00:08'),
(4, 'How do I access the admin configuration panel?', '<p>The admin configuration panel is accessible only to IT Admin and System Admin roles.</p><p>Navigate to <strong>Settings → System Configuration</strong> from the admin dashboard.</p>', 8, 1, 'approved', 1, NULL, '2026-05-29 04:00:08', '2026-05-29 04:00:08'),
(5, 'Drinking water', 'Please repaire the drinking water machine at godavari bhavan.', 4, 3, 'approved', 0, 2, '2026-06-01 10:51:03', '2026-06-01 10:52:15');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `raised_by` int(10) UNSIGNED NOT NULL,
  `current_level` int(10) UNSIGNED DEFAULT NULL,
  `current_admin` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('open','in_progress','resolved','unresolved','unattended') NOT NULL DEFAULT 'open',
  `attend_deadline` datetime DEFAULT NULL,
  `resolve_deadline` datetime DEFAULT NULL,
  `attended_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_note` text DEFAULT NULL,
  `add_to_solution` tinyint(1) DEFAULT 0,
  `solution_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `title`, `description`, `category_id`, `raised_by`, `current_level`, `current_admin`, `status`, `attend_deadline`, `resolve_deadline`, `attended_at`, `resolved_at`, `resolution_note`, `add_to_solution`, `solution_public`, `created_at`, `updated_at`) VALUES
(1, 'printer', 'printer not working', 7, 3, NULL, NULL, 'open', NULL, NULL, NULL, NULL, NULL, 0, 0, '2026-06-01 09:18:14', '2026-06-01 09:18:14'),
(2, 'Drinking water', 'hfdkfojdg', 9, 3, NULL, NULL, 'open', NULL, NULL, NULL, NULL, NULL, 0, 0, '2026-06-01 10:44:50', '2026-06-01 10:44:50');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_activity`
--

CREATE TABLE `ticket_activity` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `actor_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `level_id` int(10) UNSIGNED DEFAULT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `attend_deadline` datetime DEFAULT NULL,
  `resolve_deadline` datetime DEFAULT NULL,
  `actual_time` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_activity`
--

INSERT INTO `ticket_activity` (`id`, `ticket_id`, `actor_id`, `action`, `level_id`, `admin_id`, `attend_deadline`, `resolve_deadline`, `actual_time`, `notes`, `created_at`) VALUES
(1, 1, 3, 'raised', NULL, NULL, NULL, NULL, '2026-06-01 14:48:14', 'Ticket raised.', '2026-06-01 09:18:14'),
(2, 2, 3, 'raised', NULL, NULL, NULL, NULL, '2026-06-01 16:14:50', 'Ticket raised.', '2026-06-01 10:44:50');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_extensions`
--

CREATE TABLE `ticket_extensions` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `reason_id` int(10) UNSIGNED NOT NULL,
  `remarks` text NOT NULL,
  `extra_hours` int(11) NOT NULL DEFAULT 1,
  `old_deadline` datetime NOT NULL,
  `new_deadline` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_extension_reasons`
--

CREATE TABLE `ticket_extension_reasons` (
  `id` int(10) UNSIGNED NOT NULL,
  `reason_text` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_extension_reasons`
--

INSERT INTO `ticket_extension_reasons` (`id`, `reason_text`, `is_active`, `created_by`, `created_at`) VALUES
(1, 'OEM Support Required', 1, 1, '2026-05-29 04:19:30'),
(2, 'Vendor Dependency', 1, 1, '2026-05-29 04:19:30'),
(3, 'Parts Procurement', 1, 1, '2026-05-29 04:19:30'),
(4, 'Awaiting User Response', 1, 1, '2026-05-29 04:19:30'),
(5, 'Network Issue', 1, 1, '2026-05-29 04:19:30');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_levels`
--

CREATE TABLE `ticket_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `level_name` varchar(50) NOT NULL,
  `level_order` int(11) NOT NULL DEFAULT 1,
  `attend_sla` int(11) NOT NULL DEFAULT 60,
  `resolve_sla` int(11) NOT NULL DEFAULT 120,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_levels`
--

INSERT INTO `ticket_levels` (`id`, `level_name`, `level_order`, `attend_sla`, `resolve_sla`, `created_by`, `created_at`) VALUES
(1, 'Level 1', 1, 60, 120, 1, '2026-05-29 04:19:30'),
(2, 'Level 2', 2, 90, 180, 1, '2026-05-29 04:19:30'),
(3, 'Level 3', 3, 120, 240, 1, '2026-05-29 04:19:30');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_level_admins`
--

CREATE TABLE `ticket_level_admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `level_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','system_admin') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `password`, `role`, `is_active`, `created_at`) VALUES
(1, 'System Administrator', 'sysadmin', 'sysadmin@gg.gov', '$2y$10$U7NIG6.6i4J/tfYi8epucuGXOPhMz/1udxgFD4a2shV0GOLwWQ9cS', 'system_admin', 1, '2026-05-29 03:59:29'),
(2, 'Portal Admin', 'admin', 'admin@gg.gov', '$2y$10$U7NIG6.6i4J/tfYi8epucuGXOPhMz/1udxgFD4a2shV0GOLwWQ9cS', 'admin', 1, '2026-05-29 03:59:29'),
(3, 'Regular User', 'user1', 'user1@gg.gov', '$2y$10$U7NIG6.6i4J/tfYi8epucuGXOPhMz/1udxgFD4a2shV0GOLwWQ9cS', 'user', 1, '2026-05-29 03:59:29'),
(19, 'A', 'user2', 'user2@gmail.com', '$2y$10$CdHiNqavhF9zRR35BxDRleoPdPphPQjBdW2nCgKQJMVDSqtOEeRdS', 'user', 1, '2026-06-01 07:48:08'),
(20, 'b', 'admin2', 'admin2@gmail.com', '$2y$10$LC1WV6AGeb9x7QXPs3s8bubkAyNDIlYAPyDUa7SSqvR7S10bJxJIa', 'admin', 1, '2026-06-01 07:49:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `flags`
--
ALTER TABLE `flags`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `round_robin_pointer`
--
ALTER TABLE `round_robin_pointer`
  ADD PRIMARY KEY (`level_id`);

--
-- Indexes for table `solutions`
--
ALTER TABLE `solutions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tickets_users_raised` (`raised_by`),
  ADD KEY `fk_tickets_levels` (`current_level`),
  ADD KEY `fk_tickets_users_admin` (`current_admin`),
  ADD KEY `fk_tickets_categories` (`category_id`);

--
-- Indexes for table `ticket_activity`
--
ALTER TABLE `ticket_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activity_ticket` (`ticket_id`),
  ADD KEY `fk_activity_actor` (`actor_id`),
  ADD KEY `fk_activity_level` (`level_id`),
  ADD KEY `fk_activity_admin` (`admin_id`);

--
-- Indexes for table `ticket_extensions`
--
ALTER TABLE `ticket_extensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_extensions_ticket` (`ticket_id`),
  ADD KEY `fk_extensions_admin` (`admin_id`),
  ADD KEY `fk_extensions_reason` (`reason_id`);

--
-- Indexes for table `ticket_extension_reasons`
--
ALTER TABLE `ticket_extension_reasons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_extension_reasons_users` (`created_by`);

--
-- Indexes for table `ticket_levels`
--
ALTER TABLE `ticket_levels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ticket_levels_users` (`created_by`);

--
-- Indexes for table `ticket_level_admins`
--
ALTER TABLE `ticket_level_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_level_user` (`level_id`,`user_id`),
  ADD KEY `fk_level_admins_users` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `flags`
--
ALTER TABLE `flags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `solutions`
--
ALTER TABLE `solutions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ticket_activity`
--
ALTER TABLE `ticket_activity`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ticket_extensions`
--
ALTER TABLE `ticket_extensions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_extension_reasons`
--
ALTER TABLE `ticket_extension_reasons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ticket_levels`
--
ALTER TABLE `ticket_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ticket_level_admins`
--
ALTER TABLE `ticket_level_admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `round_robin_pointer`
--
ALTER TABLE `round_robin_pointer`
  ADD CONSTRAINT `fk_rr_pointer_level` FOREIGN KEY (`level_id`) REFERENCES `ticket_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `solutions`
--
ALTER TABLE `solutions`
  ADD CONSTRAINT `solutions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_categories` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_levels` FOREIGN KEY (`current_level`) REFERENCES `ticket_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_users_admin` FOREIGN KEY (`current_admin`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_users_raised` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ticket_activity`
--
ALTER TABLE `ticket_activity`
  ADD CONSTRAINT `fk_activity_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activity_level` FOREIGN KEY (`level_id`) REFERENCES `ticket_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activity_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ticket_extensions`
--
ALTER TABLE `ticket_extensions`
  ADD CONSTRAINT `fk_extensions_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_extensions_reason` FOREIGN KEY (`reason_id`) REFERENCES `ticket_extension_reasons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_extensions_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ticket_extension_reasons`
--
ALTER TABLE `ticket_extension_reasons`
  ADD CONSTRAINT `fk_extension_reasons_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ticket_levels`
--
ALTER TABLE `ticket_levels`
  ADD CONSTRAINT `fk_ticket_levels_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ticket_level_admins`
--
ALTER TABLE `ticket_level_admins`
  ADD CONSTRAINT `fk_level_admins_levels` FOREIGN KEY (`level_id`) REFERENCES `ticket_levels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_level_admins_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
