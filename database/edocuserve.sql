-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 04, 2026 at 06:23 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `edocuserve`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `role` enum('admin','superadmin') DEFAULT 'admin',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `profile_photo`, `role`, `status`, `created_at`, `updated_at`)
VALUES
(1, 'Super Administrator', 'superadmin@pup-binan.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin_1_1788166624.png', 'superadmin', 'Active', '2026-03-12 04:57:43', '2026-08-31 08:57:04'),
(2, 'Registrar Admin', 'admin@pup-binan.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'admin', 'Active', '2026-03-12 04:57:43', '2026-03-12 04:57:43'),
(3, 'raver', 'raveradmin@gmail.com', '$2y$10$P7BdsTzgsuLJVTPXm7dRm.YkApFnFy8eOqYoG84DUzkweAOB9q6u6', 'admin_3_1788166399.png', 'admin', 'Active', '2026-03-12 11:51:58', '2026-09-03 03:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 'Online Registration', 'Please register now for students in 3rd Year hehe :D', 1, 'Super Administrator', '2026-06-06 09:24:15', '2026-06-06 09:24:15');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `document_name` varchar(200) NOT NULL,
  `category` enum('Transcript of Records','Certifications','Unclaimed','CAV','Others') DEFAULT 'Certifications',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `processing_days` int DEFAULT '5',
  `processing_min_days` int DEFAULT NULL,
  `processing_max_days` int DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `document_name`, `category`, `price`, `processing_days`, `processing_min_days`, `processing_max_days`, `description`, `is_active`, `created_at`) VALUES
(1, 'Transcript of Records (Non-Engineering)', 'Transcript of Records', 350.00, 7, 15, 21, NULL, 1, '2026-03-12 04:57:43'),
(2, 'Transcript of Records (Engineering)', 'Transcript of Records', 450.00, 7, 15, 21, NULL, 1, '2026-03-12 04:57:43'),
(3, 'Transcript of Records (Per Page)', 'Transcript of Records', 100.00, 5, 5, 5, NULL, 0, '2026-03-12 04:57:43'),
(4, 'Certification of Grades/COR', 'Certifications', 150.00, 3, 3, 5, NULL, 1, '2026-03-12 04:57:43'),
(5, 'Good Moral Character', 'Certifications', 150.00, 3, 3, 3, NULL, 1, '2026-03-12 04:57:43'),
(6, 'Honorable Dismissal', 'Certifications', 150.00, 3, 5, 7, NULL, 1, '2026-03-12 04:57:43'),
(7, 'Certification (Grades/Latin Honor/Graduation)', 'Certifications', 150.00, 3, 3, 5, NULL, 1, '2026-03-12 04:57:43'),
(8, 'Certification Authentication (Per Document)', 'Certifications', 150.00, 3, 3, 3, NULL, 1, '2026-03-12 04:57:43'),
(9, 'Certification of Ladderized Grades', 'Certifications', 150.00, 3, 3, 5, NULL, 1, '2026-03-12 04:57:43'),
(10, 'Statement of Accounts', 'Certifications', 150.00, 3, 3, 3, NULL, 1, '2026-03-12 04:57:43'),
(11, 'Informative Copy of Grades', 'Certifications', 150.00, 3, 3, 5, NULL, 1, '2026-03-12 04:57:43'),
(12, 'Detailed Description of Grades (Per Subject)', 'Certifications', 150.00, 3, 3, 3, NULL, 1, '2026-03-12 04:57:43'),
(13, 'Diploma Fee', 'Others', 200.00, 10, 15, 21, NULL, 1, '2026-03-12 04:57:43'),
(14, 'Re-printing of COR (Per Page)', 'Unclaimed', 150.00, 2, 2, 2, NULL, 0, '2026-03-12 04:57:43'),
(15, 'Verification Fee', 'Others', 200.00, 3, 3, 3, NULL, 1, '2026-03-12 04:57:43'),
(16, 'CAV/DFA', 'CAV', 920.00, 10, 5, 10, NULL, 1, '2026-03-12 04:57:43'),
(17, 'Cross-Enroll', 'Others', 150.00, 2, 2, 3, NULL, 1, '2026-03-12 04:57:43'),
(18, 'Completion Fee (Per Subject)', 'Others', 30.00, 3, 3, 3, NULL, 1, '2026-03-12 04:57:43'),
(19, 'Correction/Change of Name', 'Others', 150.00, 5, 5, 5, NULL, 1, '2026-03-12 04:57:43'),
(20, 'Retrieval Fee', 'Others', 100.00, 3, 3, 3, NULL, 1, '2026-03-12 04:57:43'),
(21, 'Certified True Copy', 'Certifications', 150.00, 3, 3, 3, NULL, 1, '2026-08-31 08:31:52');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `q1` tinyint NOT NULL COMMENT 'How easy was it to navigate and use the system?',
  `q2` tinyint NOT NULL COMMENT 'How satisfied are you with the document request process?',
  `q3` tinyint NOT NULL COMMENT 'How would you rate the clarity of the instructions provided?',
  `q4` tinyint NOT NULL COMMENT 'How satisfied are you with the payment process?',
  `q5` tinyint NOT NULL COMMENT 'How would you rate the speed and responsiveness of the system?',
  `q6` tinyint NOT NULL COMMENT 'How satisfied are you with the request status monitoring feature?',
  `q7` tinyint NOT NULL COMMENT 'How easy was it to register and set up your account?',
  `q8` tinyint NOT NULL COMMENT 'How would you rate the overall design and appearance of the system?',
  `q9` tinyint NOT NULL COMMENT 'How confident are you that your personal information is secure?',
  `q10` tinyint NOT NULL COMMENT 'Overall, how satisfied are you with PUP e-DocuServe?',
  `suggestions` text COMMENT 'Open-ended suggestions and comments',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_feedback` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `q1`, `q2`, `q3`, `q4`, `q5`, `q6`, `q7`, `q8`, `q9`, `q10`, `suggestions`, `submitted_at`) VALUES
(2, 1, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 'None for now.', '2026-03-17 12:32:03'),
(3, 2, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 'wala pa sa ngayon', '2026-03-17 12:59:45'),
(4, 4, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, '', '2026-06-19 11:39:20'),
(5, 8, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, '', '2026-08-31 11:01:11');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `request_id` int NOT NULL,
  `type` enum('Request Received','Payment Verified','Document in Process','Ready for Pickup','Claimed','Cancelled') NOT NULL,
  `message` text,
  `is_sent` tinyint(1) DEFAULT '0',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `request_id` (`request_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

DROP TABLE IF EXISTS `requests`;
CREATE TABLE IF NOT EXISTS `requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `control_number` varchar(30) NOT NULL,
  `user_id` int NOT NULL,
  `purpose` varchar(200) DEFAULT NULL,
  `payment_method` enum('Walk-in (Cashier)','Walk-in (Bank Slip)') DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `bank_slip_path` varchar(255) DEFAULT NULL,
  `payment_status` enum('Unpaid','Pending Verification','Paid') DEFAULT 'Unpaid',
  `request_status` enum('Pending','Processing','Ready for Pickup','Claimed','Cancelled') DEFAULT 'Pending',
  `tentative_release_date` date DEFAULT NULL,
  `admin_notes` text,
  `custom_requirements` text,
  `processed_by` int DEFAULT NULL,
  `date_filed` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_verified` timestamp NULL DEFAULT NULL,
  `date_released` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `control_number` (`control_number`),
  KEY `processed_by` (`processed_by`),
  KEY `idx_date_filed` (`date_filed`),
  KEY `idx_request_status` (`request_status`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `control_number`, `user_id`, `purpose`, `payment_method`, `total_amount`, `bank_slip_path`, `payment_status`, `request_status`, `tentative_release_date`, `admin_notes`, `custom_requirements`, `processed_by`, `date_filed`, `date_verified`, `date_released`, `updated_at`) VALUES
(1, '20260312-0001', 1, 'Scholarship', NULL, 180.00, 'slip_1_1773294912.jpg', 'Paid', 'Claimed', '2026-03-13', 'Please Bring the Document Stamp.', NULL, NULL, '2026-03-12 05:45:08', '2026-08-30 16:00:00', NULL, '2026-08-31 08:55:17'),
(5, '20260312-0002', 1, 'Scholarship', NULL, 180.00, 'walkin', 'Paid', 'Claimed', NULL, '', NULL, NULL, '2026-03-12 06:04:41', '2026-03-16 16:00:00', NULL, '2026-03-17 13:08:34'),
(6, '20260312-0003', 1, 'Personal Copy', NULL, 900.00, NULL, 'Paid', 'Claimed', '2026-03-16', '', NULL, NULL, '2026-03-12 11:36:07', '2026-08-30 16:00:00', NULL, '2026-08-31 10:41:45'),
(7, '20260312-0004', 1, 'Employment', NULL, 180.00, NULL, 'Paid', 'Cancelled', NULL, 'Cancelled by student: change of mind', NULL, NULL, '2026-03-12 12:09:43', '2026-08-30 16:00:00', NULL, '2026-08-31 10:42:04'),
(8, '20260317-0001', 1, 'Scholarship', NULL, 180.00, NULL, 'Paid', 'Claimed', NULL, 'Cancelled by student: wrong document', NULL, NULL, '2026-03-17 12:24:13', '2026-08-30 16:00:00', NULL, '2026-08-31 10:41:20'),
(9, '20260317-0002', 1, 'Scholarship', NULL, 180.00, NULL, 'Paid', 'Claimed', NULL, 'Cancelled by student: aaaa', NULL, NULL, '2026-03-17 12:26:05', '2026-08-30 16:00:00', NULL, '2026-03-17 13:08:40'),
(10, '20260317-0003', 1, 'Scholarship', NULL, 60.00, 'walkin', 'Paid', 'Claimed', NULL, 'uohjoilijoijio', NULL, NULL, '2026-03-17 12:27:54', '2026-08-30 16:00:00', NULL, '2026-08-31 10:41:32'),
(11, '20260317-0004', 1, 'Personal Copy', NULL, 180.00, 'walkin', 'Paid', 'Claimed', '2026-03-20', '', NULL, NULL, '2026-03-17 12:31:30', '2026-08-30 16:00:00', NULL, '2026-08-31 10:40:38'),
(12, '20260317-0005', 2, 'Scholarship', NULL, 180.00, 'walkin', 'Paid', 'Claimed', NULL, '', NULL, NULL, '2026-03-17 12:59:32', '2026-03-16 16:00:00', NULL, '2026-03-17 13:08:57'),
(14, '20260318-0001', 1, 'Other', NULL, 1640.00, NULL, 'Paid', 'Claimed', NULL, '', NULL, NULL, '2026-03-18 03:06:51', '2026-08-30 16:00:00', NULL, '2026-08-31 10:40:45'),
(15, '20260619-0001', 4, 'Scholarship', NULL, 560.00, 'slip_15_1781958000.jpg', 'Paid', 'Claimed', '2026-06-22', '', NULL, NULL, '2026-06-19 11:39:09', '2026-08-30 16:00:00', NULL, '2026-08-31 10:40:49'),
(16, '20260831-0001', 1, 'Employment', NULL, 1530.00, NULL, 'Paid', 'Claimed', NULL, '', NULL, NULL, '2026-08-31 07:42:41', '2026-08-30 16:00:00', NULL, '2026-08-31 10:41:09'),
(17, '20260831-0002', 1, 'Scholarship', 'Walk-in (Cashier)', 150.00, 'walkin', 'Paid', 'Claimed', NULL, '', NULL, NULL, '2026-08-31 08:38:10', '2026-08-30 16:00:00', NULL, '2026-08-31 10:40:56'),
(18, '20260831-0003', 1, 'Scholarship', 'Walk-in (Cashier)', 150.00, 'walkin', 'Paid', 'Claimed', '2026-09-03', '', NULL, NULL, '2026-08-31 08:44:24', '2026-08-30 16:00:00', NULL, '2026-08-31 10:40:24'),
(19, '20260831-0004', 1, 'Others: hehe', 'Walk-in (Cashier)', 150.00, NULL, 'Unpaid', 'Pending', '2026-09-07', NULL, NULL, NULL, '2026-08-31 10:43:50', NULL, NULL, '2026-08-31 10:43:50'),
(20, '20260831-0005', 1, 'Scholarship', 'Walk-in (Bank Slip)', 150.00, 'slip_20_1788173124.jpg', 'Pending Verification', 'Pending', '2026-09-03', NULL, NULL, NULL, '2026-08-31 10:44:28', NULL, NULL, '2026-08-31 10:45:24'),
(22, '20260831-0007', 1, 'Scholarship', 'Walk-in (Bank Slip)', 350.00, 'slip_22_1788174828.jpg', 'Pending Verification', 'Pending', '2026-09-29', NULL, NULL, NULL, '2026-08-31 11:13:36', NULL, NULL, '2026-08-31 11:13:48');

-- --------------------------------------------------------

--
-- Table structure for table `request_items`
--

DROP TABLE IF EXISTS `request_items`;
CREATE TABLE IF NOT EXISTS `request_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `document_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `idx_request_id` (`request_id`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `request_items`
--

INSERT INTO `request_items` (`id`, `request_id`, `document_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 4, 1, 150.00, 150.00),
(5, 5, 5, 1, 150.00, 150.00),
(6, 6, 4, 1, 150.00, 150.00),
(7, 6, 9, 1, 150.00, 150.00),
(8, 6, 12, 1, 150.00, 150.00),
(9, 6, 5, 1, 150.00, 150.00),
(10, 6, 6, 1, 150.00, 150.00),
(11, 7, 4, 1, 150.00, 150.00),
(12, 8, 11, 1, 150.00, 150.00),
(13, 9, 11, 1, 150.00, 150.00),
(14, 10, 18, 1, 30.00, 30.00),
(15, 11, 14, 1, 150.00, 150.00),
(16, 12, 11, 1, 150.00, 150.00),
(17, 13, 11, 1, 150.00, 150.00),
(18, 14, 1, 1, 350.00, 350.00),
(19, 14, 3, 1, 100.00, 100.00),
(20, 14, 8, 1, 150.00, 150.00),
(21, 14, 16, 1, 920.00, 920.00),
(22, 15, 1, 1, 350.00, 350.00),
(23, 15, 9, 1, 150.00, 150.00),
(24, 16, 2, 1, 450.00, 450.00),
(25, 16, 1, 1, 350.00, 350.00),
(26, 16, 3, 1, 100.00, 100.00),
(27, 16, 7, 1, 150.00, 150.00),
(28, 16, 8, 1, 150.00, 150.00),
(29, 16, 4, 1, 150.00, 150.00),
(30, 17, 21, 1, 150.00, 150.00),
(31, 18, 21, 1, 150.00, 150.00),
(32, 19, 4, 1, 150.00, 150.00),
(33, 20, 8, 1, 150.00, 150.00),
(34, 21, 2, 1, 450.00, 450.00),
(35, 22, 1, 1, 350.00, 350.00);

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `performed_by` varchar(100) NOT NULL,
  `performed_by_id` int DEFAULT NULL,
  `performed_by_role` enum('admin','superadmin') DEFAULT NULL,
  `target` varchar(100) DEFAULT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `action`, `performed_by`, `performed_by_id`, `performed_by_role`, `target`, `details`, `created_at`) VALUES
(2, 'Exported requests data (CSV)', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-12 10:39:17'),
(4, 'Added new admin account', 'Super Administrator', NULL, NULL, 'raveradmin@gmail.com', NULL, '2026-03-12 11:51:58'),
(5, 'Set admin status to Inactive', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-12 11:52:04'),
(6, 'Set admin status to Active', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-12 11:52:06'),
(7, 'Set admin status to Inactive', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-12 11:52:07'),
(8, 'Set admin status to Active', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-12 11:52:08'),
(9, 'Set document ID 2 to Inactive', 'Super Administrator', NULL, NULL, 'Doc ID 2', NULL, '2026-03-12 11:52:24'),
(10, 'Set document ID 2 to Active', 'Super Administrator', NULL, NULL, 'Doc ID 2', NULL, '2026-03-12 11:52:28'),
(11, 'Deleted announcement ID 1', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-12 11:53:12'),
(12, 'Exported requests data (CSV)', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-12 11:53:18'),
(13, 'Exported students data (CSV)', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-12 11:53:52'),
(14, 'Exported students data (CSV)', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-12 11:54:09'),
(15, 'Exported revenue data (CSV)', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-12 11:54:21'),
(16, 'Updated system settings', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-12 11:55:15'),
(17, 'Deleted announcement ID 2', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-12 12:23:27'),
(18, 'Set admin status to Inactive', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-12 12:49:56'),
(19, 'Reset admin password', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-12 12:51:05'),
(20, 'Set admin status to Active', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-12 12:51:08'),
(21, 'Set admin status to Inactive', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-12 12:51:34'),
(22, 'Set admin status to Active', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-14 04:32:20'),
(23, 'Set admin status to Inactive', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-14 04:32:23'),
(24, 'Set admin status to Active', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-14 04:32:53'),
(25, 'Set admin status to Inactive', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-14 04:32:58'),
(26, 'Set admin status to Active', 'Super Administrator', NULL, NULL, 'Admin ID 3', NULL, '2026-03-17 07:32:27'),
(27, 'Set document ID 2 to Inactive', 'Super Administrator', NULL, NULL, 'Doc ID 2', NULL, '2026-03-17 07:48:12'),
(28, 'Set document ID 2 to Active', 'Super Administrator', NULL, NULL, 'Doc ID 2', NULL, '2026-03-17 07:48:13'),
(29, 'Posted announcement: aaaa', 'Super Administrator', NULL, NULL, 'aaaa', NULL, '2026-03-17 07:49:21'),
(30, 'Deleted announcement ID 3', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-17 07:49:22'),
(31, 'Exported requests data (CSV)', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-03-18 02:55:41'),
(32, 'Posted announcement: Online Registration', 'Super Administrator', NULL, NULL, 'Online Registration', NULL, '2026-06-06 09:24:16'),
(33, 'Exported revenue data (CSV)', 'Super Administrator', NULL, NULL, NULL, NULL, '2026-08-31 07:17:45'),
(34, 'Approved student account', 'raver', 3, 'admin', 'User ID 5', NULL, '2026-08-31 08:56:25'),
(35, 'Exported requests data (CSV)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-08-31 09:21:15'),
(36, 'Exported requests data (CSV)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-08-31 10:13:23'),
(37, 'Updated system settings', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-08-31 10:18:00'),
(38, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-08-31 11:22:14'),
(39, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-08-31 11:23:08'),
(40, 'Uploaded PDF header image', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-08-31 11:40:31'),
(41, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-08-31 11:40:41'),
(42, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 02:54:57'),
(43, 'Uploaded PDF export template (png)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 03:02:52'),
(44, 'Exported requests data (CSV)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 03:02:55'),
(45, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 03:03:11'),
(46, 'Uploaded PDF export template (png)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 03:08:44'),
(47, 'Set admin status to Inactive', 'Super Administrator', 1, 'superadmin', 'Admin ID 3', NULL, '2026-09-03 03:10:21'),
(48, 'Set admin status to Active', 'Super Administrator', 1, 'superadmin', 'Admin ID 3', NULL, '2026-09-03 03:10:25'),
(49, 'Uploaded PDF export template (png)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 07:37:38'),
(50, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 07:37:42'),
(51, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 07:49:12'),
(52, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 07:49:34'),
(53, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 08:05:22'),
(54, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 08:05:45'),
(55, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 08:10:36'),
(56, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 08:12:19'),
(57, 'Exported requests data (PDF)', 'Super Administrator', 1, 'superadmin', NULL, NULL, '2026-09-03 08:13:58');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'school_year', '2025-2026', '2026-03-12 11:55:15'),
(2, 'office_hours', 'Monday to Friday, 8:00 AM - 5:00 PM', '2026-03-12 10:26:23'),
(3, 'office_contact', '(049) 123-4567', '2026-03-12 10:26:23'),
(4, 'office_email', 'registrar.binan@pup.edu.ph', '2026-03-12 10:26:23'),
(5, 'processing_notice', 'Processing time may vary depending on the volume of requests.', '2026-03-12 10:26:23'),
(6, 'bank_name', 'Land Bank of the Philippines', '2026-03-12 10:26:23'),
(7, 'bank_account_name', 'PUP Biñan Campus', '2026-03-12 10:26:23'),
(8, 'bank_account_number', '1234-5678-90', '2026-03-12 10:26:23'),
(9, 'maintenance_mode', '0', '2026-03-12 10:26:23'),
(10, 'pdf_header_image', 'pdf_header_1788404924.png', '2026-09-03 03:08:44'),
(13, 'smtp_host', '', '2026-08-31 10:14:02'),
(14, 'smtp_port', '587', '2026-08-31 10:14:02'),
(15, 'smtp_user', '', '2026-08-31 10:14:02'),
(16, 'smtp_pass', '', '2026-08-31 10:14:02'),
(17, 'smtp_secure', 'tls', '2026-08-31 10:14:02'),
(18, 'smtp_from_name', 'PUP e-DocuServe', '2026-08-31 10:14:02'),
(19, 'smtp_from_email', '', '2026-08-31 10:14:02'),
(20, 'pdf_footer_image', 'pdf_footer_1788421058.png', '2026-09-03 07:37:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_number` varchar(20) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text,
  `mobile_number` varchar(20) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `year_admitted` year DEFAULT NULL,
  `admitted_as` enum('New Freshman','Transferee','Cross Enrollee') DEFAULT 'New Freshman',
  `program_status` enum('Undergraduate','Graduate') DEFAULT 'Undergraduate',
  `high_school` varchar(200) DEFAULT NULL,
  `hs_year_grad` year DEFAULT NULL,
  `elementary` varchar(200) DEFAULT NULL,
  `elem_year_grad` year DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `verification_status` enum('Pending Verification','Active','Rejected') NOT NULL DEFAULT 'Pending Verification',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `idx_verification_status` (`verification_status`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `student_number`, `last_name`, `first_name`, `middle_name`, `suffix`, `email`, `password`, `reset_token`, `reset_token_expires`, `date_of_birth`, `address`, `mobile_number`, `phone_number`, `course`, `year_admitted`, `admitted_as`, `program_status`, `high_school`, `hs_year_grad`, `elementary`, `elem_year_grad`, `profile_photo`, `status`, `verification_status`, `created_at`, `updated_at`) VALUES
(1, '2023-00061-BN-0', 'Bondoc', 'Raver Wayne', 'Ramos', NULL, 'raverwayne123@gmail.com', '$2y$10$Yhox9XGBL6AFrIqP6gCufOzTNapVPRhmSLaedBHtwNjIyjSszeBiu', NULL, NULL, '2005-10-10', 'B19 L12 Lotus St/ Brgy. San Francisco(Halang) Binan, Laguna 4024', '09089236412', 'N/A', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', '2023', 'New Freshman', 'Undergraduate', 'Pacita Complex Senior High School', '2022', 'Rosario Complex Elementary School', '2017', 'user_1_1773313298.png', 'Active', 'Active', '2026-03-12 05:25:03', '2026-08-31 08:32:36'),
(2, '2023-00060-BN-0', 'Timbas', 'Althea', NULL, NULL, 'althea123@gmail.com', '$2y$10$skhZteHmn6dWuvovPHLITumrvgk8VwHsX08W7KtloAsxvgBvceLcW', NULL, NULL, '2004-12-12', 'B6 L7 Brainrot St. Brgy. Langkiwa Binan, Laguna 4024', '123456789', 'N/A', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', '2023', 'New Freshman', 'Undergraduate', 'di ko alam', '2022', 'di ko alam', '2017', NULL, 'Active', 'Active', '2026-03-17 12:59:00', '2026-08-31 08:19:14'),
(3, '2023-00059-BN-0', 'Bautista', 'Steffanie', 'Pangilinan', NULL, 'steffanie123@gmail.com', '$2y$10$2xDccV8AHrpbcj9VUuY2eexUhBGjapzji.jKbhQzn2Bi.3Y5UDfKi', NULL, NULL, '2004-12-08', 'B0 L8 haha St. Brgy. Langkiwa Binan, Laguna 4024', '123456789', 'N/A', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', '2023', 'New Freshman', 'Undergraduate', 'Lyceum De Sto. Tomas De Aquinas', '2022', 'San Vincent Elementary School', '2017', 'user_3_1773752795.jpg', 'Active', 'Active', '2026-03-17 13:04:24', '2026-08-31 08:19:14'),
(4, '2023-00001-BN-0', 'Bondoc', 'Raver Wayne', NULL, NULL, 'ravertest123@gmail.com', '$2y$10$x/A7W5tr1R2PxU6vtvYxSui5sbdDjDx2vGXvOpBN3UW4KP5ffaFOS', NULL, NULL, '2005-10-10', 'adadadadadaddadaadd', '123456', 'N/A', 'BACHELOR OF SCIENCE IN COMPUTER ENGINEERING', '2022', 'New Freshman', 'Undergraduate', 'Lyceum De Sto. Tomas De Aquinas', '2021', 'di ko alam', '2013', NULL, 'Active', 'Active', '2026-06-19 11:38:38', '2026-08-31 08:19:14');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
CREATE TABLE IF NOT EXISTS request_history (id INT AUTO_INCREMENT PRIMARY KEY, request_id INT NOT NULL, old_status VARCHAR(50), new_status VARCHAR(50), changed_by VARCHAR(100), changed_at DATETIME DEFAULT CURRENT_TIMESTAMP, notes TEXT, INDEX(request_id), INDEX(changed_at)) ENGINE=InnoDB;
ALTER TABLE documents ADD COLUMN max_quantity_per_request INT DEFAULT NULL;
CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, email VARCHAR(255) DEFAULT NULL, attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_ip_time (ip_address, attempted_at), INDEX idx_email_time (email, attempted_at)) ENGINE=InnoDB;
