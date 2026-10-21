-- Migration: Document Catalog Preview + Landing Page CMS
-- Run via: mysql -u root edocuserve < migration_sql.sql
-- IDEMPOTENT: Uses ALTER TABLE only when column missing, INSERT IGNORE for settings

-- ─── 1. Add columns to documents ──────────────────────────────────────────────
-- Run with --force; MySQL will error-skip if the column already exists (idempotent)

ALTER TABLE `documents` ADD COLUMN `requirements` TEXT NULL DEFAULT NULL AFTER `description`;
ALTER TABLE `documents` ADD COLUMN `requires_clearance` TINYINT(1) NOT NULL DEFAULT 0 AFTER `requirements`;

-- ─── 1b. Request handler name + account deletion status ──────────────────────
-- admin_name records which staff member handled a request (used by manage_requests / requests history)
-- 'Deleted' lets a student account be removed while keeping its request history intact

ALTER TABLE `requests` ADD COLUMN `admin_name` VARCHAR(200) DEFAULT NULL AFTER `processed_by`;
ALTER TABLE `users` MODIFY COLUMN `verification_status` ENUM('Pending Verification','Active','Rejected','Deleted') NOT NULL DEFAULT 'Pending Verification';

-- ─── 2. Seed system_settings landing page keys ────────────────────────────────

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('landing_tagline', 'Request, Pay, Submit the Requirements, Monitor, and Claim.'),
('landing_advisory_title', '⚠ Advisory to Our Clients'),
('landing_advisory_body', 'As part of our monitoring and evaluation, and to improve the quality of our system and services, all requests in the PUP e-DocuServe with incomplete and invalid requirements shall be automatically deleted after 90 days of noncompliance. Please be advised that the deletion from the system may be done without prior notice or approval from the requesting client.\n\nPlease be guided accordingly.'),
('landing_howto_title', '📋 How to Submit Your Document Request'),
('landing_howto_steps', 'Register and complete your profile.\nClick New Request and select your documents.\nPay via bank slip and upload proof of payment.\nMonitor your request status via email notifications.\nClaim your documents at the Registrar''s Office.'),
('landing_instructions_steps', 'Click Register and fill out your personal and educational information.\nLog in using your registered email and password.\nGo to New Request and select the documents you need.\nNote the total amount and pay through your bank. Keep the bank slip.\nUpload a clear photo or scan of your bank slip in the system.\nWait for the registrar to verify your payment. You will be notified via email.\nOnce your documents are ready, you will receive an email notification.\nClaim your documents at the Registrar''s Office, PUP Biñan Campus.'),
('landing_instructions_note', 'Requests with incomplete or invalid requirements will be automatically deleted after 90 days of noncompliance.');

-- ─── 3. Seed document descriptions, requirements, clearance flags ─────────────
-- Uses COALESCE so existing data is never overwritten

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Official academic record listing all courses taken and grades earned. For Non-Engineering graduates.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- 2x2 Picture in Formal Attire (white background)\n- Valid Identification Card (ID)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Transcript of Records (Non-Engineering)';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Official academic record listing all courses taken and grades earned. For Engineering graduates.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- 2x2 Picture in Formal Attire (white background)\n- Valid Identification Card (ID)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Transcript of Records (Engineering)';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Per-page copy of the official Transcript of Records.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Valid Identification Card (ID)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Transcript of Records (Per Page)';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Official certification of grades or Certificate of Registration issued by the Registrar\'s Office.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Certification of Grades/COR';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Official certification attesting to the student\'s good moral standing as verified by the Registrar\'s Office.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Good Moral Character';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Issued to students voluntarily withdrawing from PUP to transfer to another school.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Signed General Clearance (from all required offices)\n- Proof of Payment (Official Receipt)\n- Request Letter from the transferring school (if applicable)'),
  `requires_clearance` = 1
WHERE `document_name` = 'Honorable Dismissal';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Official certification for grades, Latin honor, or graduation status.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Certification (Grades/Latin Honor/Graduation)';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Authentication of an existing document per piece, certified by the Registrar\'s Office.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Original document to be authenticated'),
  `requires_clearance` = 0
WHERE `document_name` = 'Certification Authentication (Per Document)';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Certification of grades under a ladderized program.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Certification of Ladderized Grades';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Official statement of the student\'s financial account with the university.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Statement of Accounts';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Informative (unofficial) copy of the student\'s grades for personal reference.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Informative Copy of Grades';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Detailed description of grades per subject, typically required for board exam applications.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Detailed Description of Grades (Per Subject)';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Certified true copy of any registrar document.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Original document to be copied'),
  `requires_clearance` = 0
WHERE `document_name` = 'Certified True Copy';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Fee for issuance or replacement of the official PUP Diploma.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- 2x2 Picture in Academic Gown (Toga) with white background'),
  `requires_clearance` = 0
WHERE `document_name` = 'Diploma Fee';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Re-printing of a Certificate of Registration page that was unclaimed or lost.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Valid Identification Card (ID)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Re-printing of COR (Per Page)';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Academic verification of credentials, typically requested by employers or government agencies.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Authorization letter (if requested by a company or agency)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Verification Fee';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Certification, Authentication, and Verification (CAV) for submission to DFA or CHED for international use.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Signed General Clearance (from all required offices)\n- Original or photocopy of Transcript of Records and Diploma\n- 2x2 Picture in Formal Attire (white background)\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 1
WHERE `document_name` = 'CAV/DFA';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Documentation for cross-enrollment to another school.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Acceptance letter from the receiving school'),
  `requires_clearance` = 0
WHERE `document_name` = 'Cross-Enroll';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Fee for completion of a subject with an incomplete grade.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Completion Fee (Per Subject)';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Request for correction or change of name in academic records.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- PSA Birth Certificate (original or certified true copy)\n- Court Order or Marriage Certificate (if applicable)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Correction/Change of Name';

UPDATE `documents` SET
  `description`       = COALESCE(`description`, 'Fee for retrieval of archived academic documents.'),
  `requirements`      = COALESCE(`requirements`, '- ODRS Application Voucher\n- Proof of Payment (Official Receipt)\n- Valid Identification Card (ID)'),
  `requires_clearance` = 0
WHERE `document_name` = 'Retrieval Fee';

-- ─────────────────────────────────────────────────────────────────────────────
-- Session 3 Migration: Terms of Use + Privacy Statement CMS keys
-- Run on both local WAMP and Railway before deploying.
-- ─────────────────────────────────────────────────────────────────────────────

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('terms_of_use',       'By using PUP e-DocuServe, you agree to use this system solely for legitimate document requests as a student of Polytechnic University of the Philippines – Biñan Campus. Misuse of the system, submission of false information, or unauthorized access may result in disciplinary action in accordance with university policies.'),
('privacy_statement',  'PUP e-DocuServe collects personal information (name, student number, contact details, and course) solely for the purpose of processing document requests. Your information is kept confidential and is not shared with third parties. Data is stored securely and accessed only by authorized Registrar staff. For concerns, contact the Registrar''s Office directly.');
