-- MySQL dump 10.13  Distrib 9.1.0, for Win64 (x86_64)
--
-- Host: localhost    Database: edocuserve
-- ------------------------------------------------------
-- Server version	9.1.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (4,'Online Registration','Please register now for students in 3rd Year hehe :D',1,'Super Administrator','2026-06-06 09:24:15','2026-06-06 09:24:15');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `document_name` varchar(200) NOT NULL,
  `category` enum('Transcript of Records','Certifications','Unclaimed','CAV','Others') DEFAULT 'Certifications',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `processing_days` int DEFAULT '5',
  `processing_min_days` int DEFAULT NULL,
  `processing_max_days` int DEFAULT NULL,
  `description` text,
  `requirements` text,
  `requires_clearance` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,'Transcript of Records (Non-Engineering)','Transcript of Records',350.00,7,15,21,'Official academic record listing all courses taken and grades earned. For Non-Engineering graduates.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- 2x2 Picture in Formal Attire (white background)\n- Valid Identification Card (ID)',0,1,'2026-03-12 04:57:43'),(2,'Transcript of Records (Engineering)','Transcript of Records',450.00,7,15,21,'Official academic record listing all courses taken and grades earned. For Engineering graduates.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- 2x2 Picture in Formal Attire (white background)\n- Valid Identification Card (ID)',0,0,'2026-03-12 04:57:43'),(3,'Transcript of Records (Per Page)','Transcript of Records',100.00,5,5,5,'Per-page copy of the official Transcript of Records.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Valid Identification Card (ID)',0,1,'2026-03-12 04:57:43'),(4,'Certification of Grades/COR','Certifications',150.00,3,3,5,'Official certification of grades or Certificate of Registration issued by the Registrar\'s Office.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)',0,1,'2026-03-12 04:57:43'),(5,'Good Moral Character','Certifications',150.00,3,3,3,'Official certification attesting to the student\'s good moral standing as verified by the Registrar\'s Office.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)',0,1,'2026-03-12 04:57:43'),(6,'Honorable Dismissal','Certifications',150.00,3,5,7,'Issued to students voluntarily withdrawing from PUP to transfer to another school.','- ODRS Application Voucher\n- Signed General Clearance (from all required offices)\n- Proof of Payment (Official Receipt)\n- Request Letter from the transferring school (if applicable)',1,1,'2026-03-12 04:57:43'),(7,'Certification (Grades/Latin Honor/Graduation)','Certifications',150.00,3,3,5,'Official certification for grades, Latin honor, or graduation status.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)',0,1,'2026-03-12 04:57:43'),(8,'Certification Authentication (Per Document)','Certifications',150.00,3,3,3,'Authentication of an existing document per piece, certified by the Registrar\'s Office.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Original document to be authenticated',0,1,'2026-03-12 04:57:43'),(9,'Certification of Ladderized Grades','Certifications',150.00,3,3,5,'Certification of grades under a ladderized program.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)',0,1,'2026-03-12 04:57:43'),(10,'Statement of Accounts','Certifications',150.00,3,3,3,'Official statement of the student\'s financial account with the university.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)',0,1,'2026-03-12 04:57:43'),(11,'Informative Copy of Grades','Certifications',150.00,3,3,5,'Informative (unofficial) copy of the student\'s grades for personal reference.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)',0,1,'2026-03-12 04:57:43'),(12,'Detailed Description of Grades (Per Subject)','Certifications',150.00,3,3,3,'Detailed description of grades per subject, typically required for board exam applications.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)',0,1,'2026-03-12 04:57:43'),(13,'Diploma Fee','Others',200.00,10,15,21,'Fee for issuance or replacement of the official PUP Diploma.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- 2x2 Picture in Academic Gown (Toga) with white background',0,1,'2026-03-12 04:57:43'),(14,'Re-printing of COR (Per Page)','Unclaimed',150.00,2,2,2,'Re-printing of a Certificate of Registration page that was unclaimed or lost.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Valid Identification Card (ID)',0,1,'2026-03-12 04:57:43'),(15,'Verification Fee','Others',200.00,3,3,3,'Academic verification of credentials, typically requested by employers or government agencies.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Authorization letter (if requested by a company or agency)',0,1,'2026-03-12 04:57:43'),(16,'CAV/DFA','CAV',920.00,10,5,10,'Certification, Authentication, and Verification (CAV) for submission to DFA or CHED for international use.','- ODRS Application Voucher\n- Signed General Clearance (from all required offices)\n- Original or photocopy of Transcript of Records and Diploma\n- 2x2 Picture in Formal Attire (white background)\n- Proof of Payment (Official Receipt)',1,1,'2026-03-12 04:57:43'),(17,'Cross-Enroll','Others',150.00,2,2,3,'Documentation for cross-enrollment to another school.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Acceptance letter from the receiving school',0,1,'2026-03-12 04:57:43'),(18,'Completion Fee (Per Subject)','Others',30.00,3,3,3,'Fee for completion of a subject with an incomplete grade.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)',0,1,'2026-03-12 04:57:43'),(19,'Correction/Change of Name','Others',150.00,5,5,5,'Request for correction or change of name in academic records.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- PSA Birth Certificate (original or certified true copy)\n- Court Order or Marriage Certificate (if applicable)',0,1,'2026-03-12 04:57:43'),(20,'Retrieval Fee','Others',100.00,3,3,3,'Fee for retrieval of archived academic documents.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Valid Identification Card (ID)',0,1,'2026-03-12 04:57:43'),(21,'Certified True Copy','Certifications',150.00,3,3,3,'Certified true copy of any registrar document.','- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Original document to be copied',0,1,'2026-08-31 08:31:52');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feedback` (
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
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`,`attempted_at`),
  KEY `idx_email_time` (`email`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `request_history`
--

DROP TABLE IF EXISTS `request_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `changed_at` (`changed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

UNLOCK TABLES;

--
-- Table structure for table `request_items`
--

DROP TABLE IF EXISTS `request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `document_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `idx_request_id` (`request_id`)
) ENGINE=MyISAM AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

UNLOCK TABLES;

--
-- Table structure for table `requests`
--

DROP TABLE IF EXISTS `requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requests` (
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
  `admin_name` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `control_number` (`control_number`),
  KEY `processed_by` (`processed_by`),
  KEY `idx_date_filed` (`date_filed`),
  KEY `idx_request_status` (`request_status`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `performed_by` varchar(100) NOT NULL,
  `performed_by_id` int DEFAULT NULL,
  `performed_by_role` enum('admin','superadmin') DEFAULT NULL,
  `target` varchar(100) DEFAULT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'school_year','2025-2026','2026-03-12 11:55:15'),(2,'office_hours','Monday to Friday, 8:00 AM - 5:00 PM','2026-03-12 10:26:23'),(3,'office_contact','(049) 123-4567','2026-03-12 10:26:23'),(4,'office_email','registrar.binan@pup.edu.ph','2026-03-12 10:26:23'),(5,'processing_notice','Processing time may vary depending on the volume of requests.','2026-03-12 10:26:23'),(6,'bank_name','Land Bank of the Philippines','2026-03-12 10:26:23'),(7,'bank_account_name','PUP Bi├▒an Campus','2026-03-12 10:26:23'),(8,'bank_account_number','1234-5678-90','2026-03-12 10:26:23'),(9,'maintenance_mode','0','2026-03-12 10:26:23'),(10,'pdf_header_image','pdf_header_1789089517.png','2026-09-11 01:18:37'),(13,'smtp_host',' smtp-relay.brevo.com','2026-09-23 11:41:24'),(14,'smtp_port','587','2026-08-31 10:14:02'),(15,'smtp_user','bac15a001@smtp-brevo.com','2026-09-23 11:41:24'),(16,'smtp_pass','','2026-09-23 11:41:24'),(17,'smtp_secure','tls','2026-08-31 10:14:02'),(18,'smtp_from_name','PUP e-DocuServe','2026-08-31 10:14:02'),(19,'smtp_from_email','raverwayne123@gmail.com','2026-09-23 11:41:24'),(20,'pdf_footer_image','pdf_footer_1788421058.png','2026-09-03 07:37:38'),(21,'landing_tagline','Request, Pay, Submit the Requirements, Monitor, and Claim.hahahaha','2026-10-18 10:13:19'),(22,'landing_advisory_title','Advisory to Our Clients','2026-10-18 10:13:46'),(23,'landing_advisory_body','As part of our monitoring and evaluation, and to improve the quality of our system and services, all requests in the PUP e-DocuServe with incomplete and invalid requirements shall be automatically deleted after 90 days of noncompliance. Please be advised that the deletion from the system may be done without prior notice or approval from the requesting client.\r\n\r\nPlease be guided accordingly.','2026-10-18 10:13:19'),(24,'landing_howto_title','How to Submit Your Document Request','2026-10-18 10:13:46'),(25,'landing_howto_steps','Register and complete your profile.\r\nClick New Request and select your documents.\r\nPay via bank slip and upload proof of payment.\r\nMonitor your request status via email notifications.\r\nClaim your documents at the Registrar\'s Office.','2026-10-18 10:13:19'),(26,'landing_instructions_steps','Click Register and fill out your personal and educational information.\r\nLog in using your registered email and password.\r\nGo to New Request and select the documents you need.\r\nNote the total amount and pay through your bank. Keep the bank slip.\r\nUpload a clear photo or scan of your bank slip in the system.\r\nWait for the registrar to verify your payment. You will be notified via email.\r\nOnce your documents are ready, you will receive an email notification.\r\nClaim your documents at the Registrar\'s Office, PUP BiΓö£ΓûÆan Campus.','2026-10-18 10:13:19'),(27,'landing_instructions_note','Requests with incomplete or invalid requirements will be automatically deleted after 90 days of noncompliance.','2026-10-18 09:58:46'),(29,'terms_of_use','By using PUP e-DocuServe, you agree to use this system solely for legitimate document requests as a student of Polytechnic University of the Philippines - Binan Campus. Misuse of the system, submission of false information, or unauthorized access may result in disciplinary action.','2026-10-18 16:09:21'),(30,'privacy_statement','PUP e-DocuServe collects personal information solely for the purpose of processing document requests. Your information is kept confidential and is not shared with third parties. Data is stored securely and accessed only by authorized Registrar staff.','2026-10-18 16:09:21');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
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
  `verification_status` enum('Pending Verification','Active','Rejected','Deleted') NOT NULL DEFAULT 'Pending Verification',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `idx_verification_status` (`verification_status`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

UNLOCK TABLES;

--
-- Dumping routines for database 'edocuserve'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-19 21:51:44
