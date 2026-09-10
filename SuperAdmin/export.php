<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$success = '';
$error = '';

// Handle header/footer template upload
function handleTemplateUpload($pdo, $fileInputName, $settingKey, $filePrefix, $admin_name, &$success, &$error) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return;
    }
    $file = $_FILES[$fileInputName];

    // Verify real file content, not just extension
    $allowedTypes = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileSize = $file['size'];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);

    if (!isset($allowedTypes[$ext])) {
        $error = "Only PNG, JPG, PDF, DOC, or DOCX files are allowed for the export template.";
        return;
    }
    if (!in_array($realMime, $allowedTypes[$ext])) {
        $error = "The uploaded file's content doesn't match a valid $ext file.";
        return;
    }
    if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
        $error = "Template file must be less than 5MB.";
        return;
    }

    $uploadDir  = '../assets/uploads/';
    $fileName   = $filePrefix . '_' . time() . '.' . $ext;
    $uploadPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $error = "Failed to upload template file.";
        return;
    }

    // Delete old file
    $old = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $old->execute([$settingKey]);
    $oldFile = $old->fetchColumn();
    if ($oldFile && file_exists('../assets/uploads/' . $oldFile)) {
        unlink('../assets/uploads/' . $oldFile);
    }

    // Upsert setting
    $exists = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
    $exists->execute([$settingKey]);
    if ($exists->fetchColumn() > 0) {
        $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")
            ->execute([$fileName, $settingKey]);
    } else {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")
            ->execute([$settingKey, $fileName]);
    }

    $success = "Export template uploaded successfully.";
    $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role) VALUES (?, ?, ?, ?)")
        ->execute(["Uploaded PDF export template ($ext)", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role']]);
}

// Scale image to fit box, preserve aspect ratio
function fitImageBox($imagePath, $maxWidth, $maxHeight) {
    $imgSize = @getimagesize($imagePath);
    if ($imgSize && $imgSize[0] > 0) {
        $aspectRatio = $imgSize[1] / $imgSize[0];
        $w = $maxWidth;
        $h = $w * $aspectRatio;
        if ($h > $maxHeight) {
            $h = $maxHeight;
            $w = $h / $aspectRatio;
        }
    } else {
        $w = $maxWidth;
        $h = $maxHeight;
    }
    return [$w, $h];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleTemplateUpload($pdo, 'pdf_header_template', 'pdf_header_image', 'pdf_header', $admin_name, $success, $error);
    handleTemplateUpload($pdo, 'pdf_footer_template', 'pdf_footer_image', 'pdf_footer', $admin_name, $success, $error);
}

// Fetch settings
$settingsStmt = $pdo->query("SELECT * FROM system_settings");
$settings = [];
foreach ($settingsStmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['format'] === 'pdf') {
    // TCPDF check
    if (!file_exists('../includes/tcpdf/tcpdf.php')) {
        die('<h2>TCPDF Library Not Installed</h2>
             <p>To enable PDF export, please download TCPDF from <a href="https://github.com/tecnickcom/TCPDF/releases">GitHub</a> 
             and extract it to: <code>c:\wamp64\www\edocuserve\includes\tcpdf\</code></p>
             <p>See <code>c:\wamp64\www\edocuserve\includes\tcpdf\README_TCPDF.txt</code> for detailed instructions.</p>
             <p><a href="export.php">Back to Export Page</a></p>');
    }
    
    require_once '../includes/tcpdf/tcpdf.php';

    // Suppress error output (would corrupt binary PDF stream)
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

    // Header/footer repeat on every page via TCPDF callbacks
    class PUPReportPDF extends TCPDF {
        public $headerImagePath = '';
        public $headerImgW = 0;
        public $headerImgH = 0;
        public $footerImagePath = '';
        public $footerImgW = 0;
        public $footerImgH = 0;
        public $usableWidth = 267;
        public $reportTitle = '';
        public $reportDateRange = '';
        public $reportGeneratedOn = '';

        public function Header() {
            $y = 10;

            if ($this->headerImagePath && file_exists($this->headerImagePath)) {
                $x = 15 + ($this->usableWidth - $this->headerImgW) / 2; // center if narrower than full width
                $this->Image($this->headerImagePath, $x, $y, $this->headerImgW, $this->headerImgH);
                $y += $this->headerImgH + 6;
            } else {
                $this->SetXY(15, $y);
                $this->SetFont('helvetica', 'B', 16);
                $this->SetTextColor(109, 26, 26); // PUP Maroon
                $this->Cell(0, 10, 'Polytechnic University of the Philippines', 0, 1, 'C');
                $this->SetX(15);
                $this->SetFont('helvetica', '', 12);
                $this->SetTextColor(0, 0, 0);
                $this->Cell(0, 6, 'Biñan Campus - e-DocuServe', 0, 1, 'C');
                $y = $this->GetY() + 4;
            }

            // Same header content on every page (top margin reserves the space)
            $this->SetXY(15, $y);
            $this->SetFont('helvetica', 'B', 14);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 8, $this->reportTitle, 0, 1, 'C');
            $this->SetX(15);
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 6, $this->reportDateRange, 0, 1, 'C');
            $this->SetX(15);
            $this->Cell(0, 6, $this->reportGeneratedOn, 0, 1, 'C');
        }

        public function Footer() {
            if ($this->footerImagePath && file_exists($this->footerImagePath)) {
                $pageW = $this->getPageWidth();
                $x = ($pageW - $this->footerImgW) / 2;
                $y = $this->getPageHeight() - $this->footerImgH - 8;
                $this->Image($this->footerImagePath, $x, $y, $this->footerImgW, $this->footerImgH);
            } else {
                $this->SetY(-15);
                $this->SetFont('helvetica', 'I', 8);
                $this->SetTextColor(128, 128, 128);
                $this->Cell(0, 10, 'Generated by PUP e-DocuServe | (c) ' . date('Y') . ' Polytechnic University of the Philippines', 0, 0, 'C');
            }
        }
    }

    $type        = $_GET['export'];
    $date_from   = $_GET['date_from'] ?? '2000-01-01';
    $date_to     = $_GET['date_to']   ?? date('Y-m-d');
    $orientation = (isset($_GET['orientation']) && $_GET['orientation'] === 'P') ? 'P' : 'L';

    // Paper size (PH bond paper naming: Short=Letter, Long=Folio)
    $paperSizeParam = strtoupper($_GET['paper_size'] ?? 'LETTER');
    $paperSize = ($paperSizeParam === 'FOLIO') ? 'FOLIO' : 'LETTER';

    $headerFile    = $settings['pdf_header_image'] ?? '';
    $headerExt     = $headerFile ? strtolower(pathinfo($headerFile, PATHINFO_EXTENSION)) : '';
    $headerIsImage = in_array($headerExt, ['png', 'jpg', 'jpeg']);
    $headerPath    = '../assets/uploads/' . $headerFile;

    $footerFile    = $settings['pdf_footer_image'] ?? '';
    $footerExt     = $footerFile ? strtolower(pathinfo($footerFile, PATHINFO_EXTENSION)) : '';
    $footerIsImage = in_array($footerExt, ['png', 'jpg', 'jpeg']);
    $footerPath    = '../assets/uploads/' . $footerFile;

    $pdf = new PUPReportPDF($orientation, 'mm', $paperSize, true, 'UTF-8', false);
    $pdf->SetCreator('PUP e-DocuServe');
    $pdf->SetAuthor('Polytechnic University of the Philippines - Biñan Campus');
    $pdf->SetTitle(ucfirst($type) . ' Report');

    // Usable content width
    $usableWidth = $pdf->getPageWidth() - 30; // 15mm margin each side
    $pdf->usableWidth = $usableWidth;
    $pdf->reportTitle = ucfirst($type) . ' Report';
    $pdf->reportDateRange = 'Date Range: ' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to));
    $pdf->reportGeneratedOn = 'Generated on: ' . date('F d, Y g:i A');

    // Margins (top margin = header height, see Header() above)
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(15);

    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);

    // Header image sizing
    $headerContentHeight = 10; // starting offset from page top
    if ($headerIsImage && file_exists($headerPath)) {
        [$hw, $hh] = fitImageBox($headerPath, $usableWidth, 30);
        $pdf->headerImagePath = $headerPath;
        $pdf->headerImgW = $hw;
        $pdf->headerImgH = $hh;
        $headerContentHeight += $hh + 6;
    } else {
        $headerContentHeight += 10 + 6 + 4; // two text lines + gap
    }
    $headerContentHeight += 8 + 6 + 6 + 6; // title + date range + generated-on + trailing gap

    $pdf->SetMargins(15, $headerContentHeight, 15);

    // Only PNG/JPG footers render live
    if ($footerIsImage && file_exists($footerPath)) {
        [$fw, $fh] = fitImageBox($footerPath, $usableWidth, 20); // footer capped shorter than header
        $pdf->footerImagePath = $footerPath;
        $pdf->footerImgW = $fw;
        $pdf->footerImgH = $fh;
        // Reserve bottom space for footer
        $pdf->SetAutoPageBreak(true, $fh + 14);
    } else {
        $pdf->SetAutoPageBreak(true, 20);
    }

    $pdf->AddPage(); // Header() fires automatically here, and again on every page break

    // Smaller font/padding for portrait (less width)
    $tableFontSize = ($orientation === 'P') ? 7 : 9;
    $tableCellPad  = ($orientation === 'P') ? 2 : 4;

    if ($type === 'requests') {
        $rows = $pdo->prepare("
            SELECT r.control_number, CONCAT(u.last_name,', ',u.first_name) as name,
                   u.course,
                   GROUP_CONCAT(d.document_name SEPARATOR ', ') as documents,
                   r.purpose, r.payment_status, r.request_status,
                   DATE_FORMAT(r.date_filed, '%b %d, %Y %h:%i %p') as date_time_filed
            FROM requests r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN request_items ri ON r.id = ri.request_id
            LEFT JOIN documents d ON ri.document_id = d.id
            WHERE DATE(r.date_filed) BETWEEN ? AND ?
            GROUP BY r.id
            ORDER BY r.date_filed DESC
        ");
        $rows->execute([$date_from, $date_to]);
        $data = $rows->fetchAll();

        $html = '<table border="1" cellpadding="' . $tableCellPad . '" cellspacing="0" style="font-size:' . $tableFontSize . 'px;">';
        $html .= '<thead><tr style="background-color:#6D1A1A; color:#ffffff; font-weight:bold;">
                    <th width="8%">Control #</th>
                    <th width="15%">Student</th>
                    <th width="10%">Course</th>
                    <th width="25%">Documents</th>
                    <th width="12%">Purpose</th>
                    <th width="10%">Payment</th>
                    <th width="10%">Status</th>
                    <th width="10%">Date & Time</th>
                  </tr></thead><tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['control_number']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['name'] ?? 'Unknown Student') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['course'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['documents'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['purpose'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['payment_status'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['request_status'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['date_time_filed'] ?? 'N/A') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    if ($type === 'students') {
        $rows = $pdo->query("
            SELECT student_number, CONCAT(last_name,', ',first_name,' ',middle_name) as name,
                   email, mobile_number, course, program_status, year_admitted,
                   DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as registered_date
            FROM users ORDER BY last_name
        ");
        $data = $rows->fetchAll();

        $html = '<table border="1" cellpadding="' . $tableCellPad . '" cellspacing="0" style="font-size:' . $tableFontSize . 'px;">';
        $html .= '<thead><tr style="background-color:#6D1A1A; color:#ffffff; font-weight:bold;">
                    <th width="12%">Student #</th>
                    <th width="18%">Name</th>
                    <th width="18%">Email</th>
                    <th width="10%">Mobile</th>
                    <th width="12%">Course</th>
                    <th width="10%">Status</th>
                    <th width="8%">Year</th>
                    <th width="12%">Registered</th>
                  </tr></thead><tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['student_number'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['name'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['email'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['mobile_number'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['course'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['program_status'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['year_admitted'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['registered_date'] ?? 'N/A') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    // Footer drawn automatically via Footer() override
    $pdf->Output(ucfirst($type) . '_Report_' . date('Ymd_His') . '.pdf', 'D');

    $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role) VALUES (?, ?, ?, ?)")
        ->execute(["Exported $type data (PDF)", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role']]);
    exit();
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['format'] === 'csv') {
    $type      = $_GET['export'];
    $date_from = $_GET['date_from'] ?? '2000-01-01';
    $date_to   = $_GET['date_to']   ?? date('Y-m-d');

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $type . '_' . date('YmdHis') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($type === 'requests') {
        fputcsv($out, ['Control Number','Student Name','Course','Documents','Purpose','Payment Status','Request Status','Date Filed','Time Filed','Tentative Release']);
        $rows = $pdo->prepare("
            SELECT r.control_number, CONCAT(u.last_name,', ',u.first_name) as name,
                   u.course,
                   GROUP_CONCAT(d.document_name SEPARATOR '; ') as documents,
                   r.purpose, r.payment_status, r.request_status,
                   DATE(r.date_filed) as date_filed, TIME(r.date_filed) as time_filed,
                   r.tentative_release_date
            FROM requests r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN request_items ri ON r.id = ri.request_id
            LEFT JOIN documents d ON ri.document_id = d.id
            WHERE DATE(r.date_filed) BETWEEN ? AND ?
            GROUP BY r.id
            ORDER BY r.date_filed DESC
        ");
        $rows->execute([$date_from, $date_to]);
        foreach ($rows->fetchAll() as $row) fputcsv($out, $row);
    }

    if ($type === 'students') {
        fputcsv($out, ['Student Number','Last Name','First Name','Middle Name','Email','Mobile','Course','Program Status','Year Admitted','Date Registered']);
        $rows = $pdo->query("SELECT student_number, last_name, first_name, middle_name, email, mobile_number, course, program_status, year_admitted, created_at FROM users ORDER BY last_name");
        foreach ($rows->fetchAll() as $row) fputcsv($out, $row);
    }

    fclose($out);

    $pdo->prepare("INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role) VALUES (?, ?, ?, ?)")
        ->execute(["Exported $type data (CSV)", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role']]);
    exit();
}

$current_page = 'export';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Data | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --sa-color: #1a237e; }
        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; margin: 0; }
        .sidebar { position: fixed; top: 0; left: 0; width: 230px; height: 100vh; background: #0d0d0d; color: white; display: flex; flex-direction: column; z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 16px 16px 12px; border-bottom: 1px solid #222; display: flex; align-items: center; gap: 10px; }
        .sidebar-brand img { height: 36px; }
        .sidebar-brand-text { font-size: 12px; font-weight: 700; line-height: 1.3; color: white; }
        .sidebar-brand-text span { display: block; font-size: 10px; font-weight: 400; opacity: 0.7; }
        .sidebar-role { padding: 10px 16px; background: var(--sa-color); font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .sidebar-nav { flex: 1; padding: 10px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 9px 16px; color: #bbb; text-decoration: none; font-size: 12px; transition: all 0.2s; }
        .sidebar-nav a:hover { background: #1a1a1a; color: white; }
        .sidebar-nav a.active { background: var(--sa-color); color: white; }
        .sidebar-nav a i { width: 16px; text-align: center; font-size: 12px; }
        .sidebar-nav .nav-section { padding: 10px 16px 4px; font-size: 10px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar-footer { padding: 12px 16px; border-top: 1px solid #222; font-size: 12px; color: #777; }
        .sidebar-footer a { color: #f66; text-decoration: none; }
        .main-content { margin-left: 230px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: white; border-bottom: 1px solid #ddd; padding: 10px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 99; }
        .topbar-title { font-size: 15px; font-weight: 700; color: #333; }
        .topbar-user { font-size: 12px; color: #666; }
        .topbar-user strong { color: var(--sa-color); }
        .page-content { padding: 24px; flex: 1; max-width: 860px; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .section-body { padding: 20px; }
        .export-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 16px; margin-bottom: 14px; transition: box-shadow 0.2s; }
        .export-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .export-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; flex-shrink: 0; }
        .export-info { flex: 1; }
        .export-title { font-size: 14px; font-weight: 700; color: #333; margin-bottom: 4px; }
        .export-desc { font-size: 12px; color: #888; }
        .form-control { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; }
        .btn-export { background: var(--sa-color); color: white; border: none; padding: 8px 20px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-export:hover { background: #283593; color: white; }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-file-export me-2" style="color:var(--sa-color);"></i>Export Data</div>
        <div class="topbar-user">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong> &nbsp;|&nbsp; <?= date('F d, Y') ?></div>
    </div>

    <div class="page-content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="section-card">
            <div class="section-header"><i class="fas fa-file-pdf me-2" style="color:#6D1A1A;"></i>PDF Export Template</div>
            <div class="section-body">
                <form method="POST" enctype="multipart/form-data">

                    <!-- HEADER -->
                    <label class="form-label">Header (Letterhead) <span style="font-weight:400; color:#888;">(top of exported PDF reports)</span></label>
                    <div style="margin-bottom:10px; font-size:12px; color:#888;">
                        <i class="fas fa-info-circle me-1"></i>
                        Accepts PNG, JPG, PDF, or Word (DOC/DOCX). Recommended image size: 1200x300px. Max file size: 5MB.
                    </div>

                    <?php
                    $currentHeader    = $settings['pdf_header_image'] ?? '';
                    $currentHeaderExt = $currentHeader ? strtolower(pathinfo($currentHeader, PATHINFO_EXTENSION)) : '';
                    $headerImgOk      = in_array($currentHeaderExt, ['png', 'jpg', 'jpeg']);
                    ?>

                    <?php if ($currentHeader): ?>
                        <div style="margin-bottom:12px; padding:12px; background:#f8f8f8; border-radius:6px; border:1px solid #e0e0e0;">
                            <div style="font-size:12px; font-weight:600; color:#555; margin-bottom:6px;">Current Header:</div>
                            <?php if ($headerImgOk): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($currentHeader) ?>"
                                     alt="PDF Header"
                                     style="max-width:100%; height:auto; border:1px solid #ddd; border-radius:4px;">
                                <div style="font-size:11px; color:#28a745; margin-top:6px;">
                                    <i class="fas fa-check-circle me-1"></i>This image is applied to the top of every exported PDF.
                                </div>
                            <?php else: ?>
                                <div style="font-size:13px;">
                                    <i class="fas fa-file-<?= $currentHeaderExt === 'pdf' ? 'pdf' : 'word' ?> me-2"></i>
                                    <a href="../assets/uploads/<?= htmlspecialchars($currentHeader) ?>" target="_blank"><?= htmlspecialchars($currentHeader) ?></a>
                                </div>
                                <div style="font-size:11px; color:#856404; margin-top:6px;">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?= strtoupper($currentHeaderExt) ?> files are stored for reference but can't be rendered live — only PNG/JPG are drawn into the PDF. Default text header is used until an image is uploaded.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom:12px; padding:12px; background:#fff3cd; border:1px solid #ffc107; border-radius:6px; font-size:12px; color:#856404;">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            No header uploaded. Default PUP text header will be used in PDF exports.
                        </div>
                    <?php endif; ?>

                    <input type="file" name="pdf_header_template" class="form-control" accept=".png,.jpg,.jpeg,.pdf,.doc,.docx,image/png,image/jpeg,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <div style="font-size:11px; color:#999; margin-top:4px; margin-bottom:24px;">
                        Leave blank to keep the current header. Uploading a new file replaces the existing one.
                    </div>

                    <!-- FOOTER -->
                    <label class="form-label">Footer <span style="font-weight:400; color:#888;">(bottom of every page — repeats automatically on multi-page reports)</span></label>
                    <div style="margin-bottom:10px; font-size:12px; color:#888;">
                        <i class="fas fa-info-circle me-1"></i>
                        Same accepted formats as the header. Recommended image size: 1600x300px. Max file size: 5MB.
                    </div>

                    <?php
                    $currentFooter    = $settings['pdf_footer_image'] ?? '';
                    $currentFooterExt = $currentFooter ? strtolower(pathinfo($currentFooter, PATHINFO_EXTENSION)) : '';
                    $footerImgOk      = in_array($currentFooterExt, ['png', 'jpg', 'jpeg']);
                    ?>

                    <?php if ($currentFooter): ?>
                        <div style="margin-bottom:12px; padding:12px; background:#f8f8f8; border-radius:6px; border:1px solid #e0e0e0;">
                            <div style="font-size:12px; font-weight:600; color:#555; margin-bottom:6px;">Current Footer:</div>
                            <?php if ($footerImgOk): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($currentFooter) ?>"
                                     alt="PDF Footer"
                                     style="max-width:100%; height:auto; border:1px solid #ddd; border-radius:4px;">
                                <div style="font-size:11px; color:#28a745; margin-top:6px;">
                                    <i class="fas fa-check-circle me-1"></i>This image is applied to the bottom of every page in exported PDFs.
                                </div>
                            <?php else: ?>
                                <div style="font-size:13px;">
                                    <i class="fas fa-file-<?= $currentFooterExt === 'pdf' ? 'pdf' : 'word' ?> me-2"></i>
                                    <a href="../assets/uploads/<?= htmlspecialchars($currentFooter) ?>" target="_blank"><?= htmlspecialchars($currentFooter) ?></a>
                                </div>
                                <div style="font-size:11px; color:#856404; margin-top:6px;">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?= strtoupper($currentFooterExt) ?> files are stored for reference but can't be rendered live — only PNG/JPG are drawn into the PDF. Default text footer is used until an image is uploaded.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom:12px; padding:12px; background:#fff3cd; border:1px solid #ffc107; border-radius:6px; font-size:12px; color:#856404;">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            No footer uploaded. Default text footer ("Generated by PUP e-DocuServe...") will be used.
                        </div>
                    <?php endif; ?>

                    <input type="file" name="pdf_footer_template" class="form-control" accept=".png,.jpg,.jpeg,.pdf,.doc,.docx,image/png,image/jpeg,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <div style="font-size:11px; color:#999; margin-top:4px;">
                        Leave blank to keep the current footer. Uploading a new file replaces the existing one.
                    </div>

                    <button type="submit" class="btn-export" style="margin-top:14px; background:var(--sa-color);"><i class="fas fa-upload me-1"></i> Upload Templates</button>
                </form>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">Export to CSV</div>
            <div class="section-body">

                <div style="font-size:12px; color:#888; margin-bottom:20px;">
                    <i class="fas fa-info-circle me-1"></i>
                    Select a date range and export type. Data will be downloaded as a CSV file that can be opened in Excel.
                </div>

                <!-- Date Range -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label style="font-size:12px; font-weight:600; color:#555;">From Date</label>
                        <input type="date" id="date_from" class="form-control mt-1" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-4">
                        <label style="font-size:12px; font-weight:600; color:#555;">To Date</label>
                        <input type="date" id="date_to" class="form-control mt-1" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- PDF Orientation -->
                <div class="mb-3">
                    <label style="font-size:12px; font-weight:600; color:#555; display:block; margin-bottom:6px;">PDF Page Orientation <span style="font-weight:400; color:#999;">(CSV is unaffected)</span></label>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="pdf_orientation" id="orientLandscape" value="L" checked>
                        <label class="btn btn-outline-secondary btn-sm" for="orientLandscape" style="font-size:12px;"><i class="fas fa-file me-1" style="transform:rotate(90deg); display:inline-block;"></i> Landscape (recommended — wide tables)</label>

                        <input type="radio" class="btn-check" name="pdf_orientation" id="orientPortrait" value="P">
                        <label class="btn btn-outline-secondary btn-sm" for="orientPortrait" style="font-size:12px;"><i class="fas fa-file me-1"></i> Portrait</label>
                    </div>
                </div>

                <!-- PDF Paper Size -->
                <div class="mb-4">
                    <label style="font-size:12px; font-weight:600; color:#555; display:block; margin-bottom:6px;">PDF Paper Size <span style="font-weight:400; color:#999;">(CSV is unaffected)</span></label>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="pdf_paper_size" id="paperShort" value="LETTER" checked>
                        <label class="btn btn-outline-secondary btn-sm" for="paperShort" style="font-size:12px;"><i class="fas fa-file me-1"></i> Short (Letter, 8.5×11in)</label>

                        <input type="radio" class="btn-check" name="pdf_paper_size" id="paperLong" value="FOLIO">
                        <label class="btn btn-outline-secondary btn-sm" for="paperLong" style="font-size:12px;"><i class="fas fa-file me-1"></i> Long (Folio, 8.5×13in)</label>
                    </div>
                    <div style="font-size:11px; color:#999; margin-top:4px;">
                        "Long" here matches PH bond paper (Folio, 8.5×13in) — not the same as US Legal size (8.5×14in).
                    </div>
                </div>

                <!-- Export Options -->
                <div class="export-card">
                    <div class="export-icon" style="background: var(--pup-red);"><i class="fas fa-file-alt"></i></div>
                    <div class="export-info">
                        <div class="export-title">Document Requests</div>
                        <div class="export-desc">All requests with student info, documents requested, payment status, request status, and date/time filed.</div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <a href="#" onclick="doExport('requests', 'csv')" class="btn-export">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                        <a href="#" onclick="doExport('requests', 'pdf')" class="btn-export" style="background:#e74c3c;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    </div>
                </div>

                <div class="export-card">
                    <div class="export-icon" style="background: #3498db;"><i class="fas fa-users"></i></div>
                    <div class="export-info">
                        <div class="export-title">Student List</div>
                        <div class="export-desc">All registered students with student number, name, course, contact info, and registration date with full timestamp.</div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <a href="#" onclick="doExport('students', 'csv')" class="btn-export">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                        <a href="#" onclick="doExport('students', 'pdf')" class="btn-export" style="background:#e74c3c;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <div class="footer-admin">
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function doExport(type, format) {
    const from = document.getElementById('date_from').value;
    const to   = document.getElementById('date_to').value;
    if (!from || !to) { alert('Please select a date range first.'); return; }

    let url = `export.php?export=${type}&format=${format}&date_from=${from}&date_to=${to}`;

    if (format === 'pdf') {
        const orientation = document.querySelector('input[name="pdf_orientation"]:checked').value;
        const paperSize   = document.querySelector('input[name="pdf_paper_size"]:checked').value;
        url += `&orientation=${orientation}&paper_size=${paperSize}`;
    }

    window.location.href = url;
}
</script>
</body>
</html>