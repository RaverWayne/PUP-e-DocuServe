<?php
session_start();
require_once '../config/db.php';
require_once '../includes/release_date.php';

// Session timeout — 10 minutes
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > 600) {
        session_unset();
        session_destroy();
        header("Location: ../auth/login.php?timeout=1");
        exit();
    }
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch active documents grouped by category
$stmt     = $pdo->query("SELECT * FROM documents WHERE is_active = 1 ORDER BY category, document_name");
$all_docs = $stmt->fetchAll();

$categories = [];
foreach ($all_docs as $doc) {
    $categories[$doc['category']][] = $doc;
}

$success        = false;
$error          = '';
$control_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose        = trim($_POST['purpose'] ?? '');
    // Append Others free-text to purpose
    if ($purpose === 'Others' && !empty(trim($_POST['purpose_other'] ?? ''))) {
        $purpose = 'Others: ' . trim($_POST['purpose_other']);
    }
    $payment_method = trim($_POST['payment_method'] ?? '');
    $doc_ids        = $_POST['doc_ids'] ?? [];
    $quantities     = $_POST['quantities'] ?? [];

    // Validate
    if (empty($purpose)) {
        $error = "Please select a purpose for your request.";
    } elseif (empty($payment_method)) {
        $error = "Please select a payment method.";
    } elseif (empty($doc_ids)) {
        $error = "Please select at least one document.";
    } else {
        // Generate control number: YYYYMMDD-XXXX
        $date_part = date('Ymd');
        $stmt      = $pdo->query("SELECT COUNT(*) AS cnt FROM requests WHERE DATE(date_filed) = CURDATE()");
        $row       = $stmt->fetch();
        $seq            = str_pad($row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
        $control_number = $date_part . '-' . $seq;

        // Calculate total (no documentary stamp)
        $total    = 0;
        $items    = [];
        $max_days = 0; // track slowest document for release date

        foreach ($doc_ids as $doc_id) {
            $qty  = max(1, intval($quantities[$doc_id] ?? 1));
            $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND is_active = 1");
            $stmt->execute([$doc_id]);
            $doc = $stmt->fetch();
            if ($doc) {
                $subtotal  = $doc['price'] * $qty;
                $total    += $subtotal;
                $items[]   = ['doc' => $doc, 'qty' => $qty, 'subtotal' => $subtotal];
                // Use processing_max_days, fallback processing_days
                $doc_max = intval($doc['processing_max_days'] ?? $doc['processing_days'] ?? 5);
                if ($doc_max > $max_days) $max_days = $doc_max;
            }
        }

        // Release date from slowest document
        $tentative_release_date = calculateReleaseDate($max_days > 0 ? $max_days : 5);

        // Insert request (documentary_stamp = 0)
        $stmt = $pdo->prepare("
            INSERT INTO requests
                (control_number, user_id, purpose, payment_method, total_amount, documentary_stamp, tentative_release_date)
            VALUES (?, ?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([$control_number, $user_id, $purpose, $payment_method, $total, $tentative_release_date]);
        $request_id = $pdo->lastInsertId();

        // Insert request items
        foreach ($items as $item) {
            $pdo->prepare("
                INSERT INTO request_items (request_id, document_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $request_id,
                $item['doc']['id'],
                $item['qty'],
                $item['doc']['price'],
                $item['subtotal']
            ]);
        }

        // Redirect to feedback if not yet submitted, otherwise show success
        $fb_check = $pdo->prepare("SELECT id FROM feedback WHERE user_id = ?");
        $fb_check->execute([$user_id]);
        if (!$fb_check->fetch()) {
            header("Location: feedback.php?from=request&control=" . urlencode($control_number));
            exit();
        }

        $success = true;
    }
}

$purposes = [
    'Employment',
    'Scholarship',
    'Further Studies',
    'For Board Examination',
    'For Transfer of School',
    'Personal Copy',
    'Government Requirement',
    'Others',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Request | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }

        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; }

        .navbar-pup {
            background-color: var(--pup-red); padding: 8px 20px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3); position: sticky; top: 0; z-index: 999;
        }
        .navbar-pup .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .navbar-pup .brand img { height: 38px; }
        .navbar-pup .brand-text { color: white; font-size: 13px; font-weight: 600; line-height: 1.3; }
        .navbar-pup .brand-text span { display: block; font-size: 11px; font-weight: 400; opacity: 0.85; }
        .dropdown-toggle { background: none; border: none; color: white; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .dropdown-toggle:focus { box-shadow: none; }

        .tabs-bar { background: white; border-bottom: 1px solid #ddd; padding: 0 20px; }
        .tabs-bar .nav-tabs { border-bottom: none; }
        .tabs-bar .nav-link {
            color: #555; font-size: 13px; padding: 12px 16px;
            border: none; border-bottom: 3px solid transparent;
            border-radius: 0; text-decoration: none;
            display: flex; align-items: center; gap: 6px;
        }
        .tabs-bar .nav-link:hover { color: var(--pup-red); }
        .tabs-bar .nav-link.active { color: var(--pup-red); border-bottom-color: var(--pup-red); font-weight: 600; }

        .content-wrapper { max-width: 960px; margin: 24px auto; padding: 0 16px 40px; }

        .section-card { background: white; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; overflow: hidden; }
        .section-header { background: #e8e8e8; padding: 10px 16px; font-weight: 600; font-size: 13px; color: #333; border-bottom: 1px solid #ddd; }
        .section-body { padding: 16px 20px; }

        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
        .form-control, .form-select { font-size: 13px; border-radius: 4px; border: 1px solid #ccc; padding: 7px 10px; }
        .form-control:focus, .form-select:focus { border-color: var(--pup-red); box-shadow: 0 0 0 2px rgba(109,26,26,0.12); }

        /* Payment method cards */
        .payment-options { display: flex; gap: 12px; flex-wrap: wrap; }
        .payment-option { flex: 1; min-width: 180px; }
        .payment-option input[type="radio"] { display: none; }
        .payment-option label {
            display: flex; align-items: center; gap: 10px;
            border: 2px solid #ddd; border-radius: 6px;
            padding: 12px 16px; cursor: pointer;
            font-size: 13px; font-weight: 600; color: #444;
            transition: all 0.2s; background: #fafafa;
        }
        .payment-option label i { font-size: 20px; color: #aaa; }
        .payment-option input[type="radio"]:checked + label {
            border-color: var(--pup-red);
            background: #fff5f5;
            color: var(--pup-red);
        }
        .payment-option input[type="radio"]:checked + label i { color: var(--pup-red); }

        /* Category tabs */
        .doc-tabs { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 16px; }
        .doc-tab-btn {
            background: #f0f0f0; border: 1px solid #ccc; color: #555;
            padding: 6px 14px; border-radius: 4px; font-size: 12px;
            cursor: pointer; transition: all 0.2s;
        }
        .doc-tab-btn:hover, .doc-tab-btn.active { background: var(--pup-red); color: white; border-color: var(--pup-red); }

        /* Document table */
        .doc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .doc-table thead tr { background: #e8e8e8; }
        .doc-table th { padding: 9px 12px; font-weight: 600; color: #333; border: 1px solid #ddd; font-size: 12px; }
        .doc-table td { padding: 8px 12px; border: 1px solid #eee; vertical-align: middle; }
        .doc-table tbody tr:hover { background: #fafafa; }
        .doc-table input[type="checkbox"] { width: 15px; height: 15px; cursor: pointer; accent-color: var(--pup-red); }

        .qty-input { width: 60px; text-align: center; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
        .qty-input:disabled { background: #f5f5f5; color: #aaa; }

        .doc-category { display: none; }
        .doc-category.active { display: block; }

        /* Totals */
        .totals-table { width: 100%; font-size: 13px; }
        .totals-table td { padding: 6px 12px; }
        .totals-table .total-label { text-align: right; color: #555; width: 70%; }
        .totals-table .total-value { text-align: right; font-weight: 600; width: 30%; }
        .totals-table .grand-total td { background: #f5f5f5; font-size: 14px; border-top: 2px solid #ddd; }

        .hint-text { font-size: 11px; color: #888; font-style: italic; margin-bottom: 6px; }

        .btn-submit-req {
            background: var(--pup-red); color: white; border: none;
            padding: 10px 32px; border-radius: 5px; font-size: 14px;
            font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-submit-req:hover { background: #8B2020; }

        .footer-pup { background: #f8f8f8; border-top: 1px solid #ddd; padding: 12px 20px; text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
        .footer-pup a { color: var(--pup-red); text-decoration: none; }

        .success-box { background: #eaf4e8; border: 1px solid #b8ddb4; border-radius: 6px; padding: 24px; text-align: center; }
        .success-box h4 { color: #2d6a27; margin-bottom: 8px; }
        .success-box p { color: #555; font-size: 13px; }
        .success-box .control-num { font-size: 20px; font-weight: 700; color: var(--pup-red); margin: 10px 0; }

        /* Order Summary modal */
        .modal-header { background: var(--pup-red); color: white; }
        .modal-header .btn-close { filter: invert(1); }
        .summary-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .summary-table th { background: #f5f5f5; padding: 8px 12px; font-size: 12px; color: #555; border-bottom: 1px solid #ddd; }
        .summary-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; }
        .summary-total-row td { font-weight: 700; font-size: 14px; background: #fff5f5; color: var(--pup-red); border-top: 2px solid #ddd; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="brand-text">
            PUP e-DocuServe
            <span>Biñan Campus — Online Document Request</span>
        </div>
    </a>
    <div class="dropdown">
        <button class="dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle" style="font-size:18px;"></i>
            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
            <i class="fas fa-caret-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="font-size:13px;">
            <li><a class="dropdown-item" href="account_settings.php"><i class="fas fa-cog me-2"></i>Account Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </div>
</nav>

<!-- TABS -->
<div class="tabs-bar">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-user"></i> Profile</a></li>
        <li class="nav-item"><a class="nav-link active" href="new_request.php"><i class="fas fa-plus-circle"></i> New Request</a></li>
        <li class="nav-item"><a class="nav-link" href="requests.php"><i class="fas fa-list"></i> Requests</a></li>
        <li class="nav-item"><a class="nav-link" href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a></li>
    </ul>
</div>

<!-- CONTENT -->
<div class="content-wrapper">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="section-card">
            <div class="section-header">New Request</div>
            <div class="section-body">
                <div class="success-box">
                    <i class="fas fa-check-circle" style="font-size:40px; color:#2d6a27;"></i>
                    <h4 class="mt-3">Request Submitted Successfully!</h4>
                    <div class="control-num"><?= htmlspecialchars($control_number) ?></div>
                    <p>Your document request has been submitted. Please proceed to pay and present or upload your proof of payment in the <strong>Requests</strong> tab.</p>
                    <div class="mt-3 d-flex justify-content-center gap-2">
                        <a href="requests.php" class="btn btn-sm" style="background:var(--pup-red); color:white;">View My Requests</a>
                        <a href="new_request.php" class="btn btn-sm btn-outline-secondary">New Request</a>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>

    <form method="POST" action="" id="requestForm">
        <div class="section-card">
            <div class="section-header">New Request</div>
            <div class="section-body">

                <!-- Student Info (read only) -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Program/Course</label>
                        <input type="text" class="form-control"
                            value="<?= htmlspecialchars($user['course']) ?>"
                            readonly style="background:#f5f5f5;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Documents for</label>
                        <input type="text" class="form-control"
                            value="<?= htmlspecialchars($user['program_status']) ?> Students"
                            readonly style="background:#f5f5f5;">
                    </div>
                </div>

                <!-- Purpose -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Purpose of Request <span style="color:var(--pup-red);">*</span></label>
                        <select name="purpose" class="form-select" required>
                            <option value="">-- Select Purpose --</option>
                            <?php foreach ($purposes as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"
                                    <?= (isset($_POST['purpose']) && $_POST['purpose'] === $p) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" id="othersBox" style="display:none;">
                        <label class="form-label">Please specify <span style="color:var(--pup-red);">*</span></label>
                        <input type="text" name="purpose_other" id="purposeOtherInput" class="form-control"
                            placeholder="Describe your purpose..."
                            value="<?= htmlspecialchars($_POST['purpose_other'] ?? '') ?>">
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="mb-4">
                    <label class="form-label">Payment Method <span style="color:var(--pup-red);">*</span></label>
                    <div class="payment-options">
                        <div class="payment-option">
                            <input type="radio" name="payment_method" id="pm_cashier"
                                value="Walk-in (Cashier)"
                                <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'Walk-in (Cashier)') ? 'checked' : '' ?>>
                            <label for="pm_cashier">
                                <i class="fas fa-cash-register"></i>
                                Walk-in (Cashier)
                            </label>
                        </div>
                        <div class="payment-option">
                            <input type="radio" name="payment_method" id="pm_bankslip"
                                value="Walk-in (Bank Slip)"
                                <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'Walk-in (Bank Slip)') ? 'checked' : '' ?>>
                            <label for="pm_bankslip">
                                <i class="fas fa-receipt"></i>
                                Walk-in (Bank Slip)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Document Type Tabs -->
                <div class="mb-2">
                    <label class="form-label">
                        Type of Document <span style="color:var(--pup-red);">*</span>
                        <span style="font-weight:400; color:#888;">(Choose at least 1)</span>
                    </label>
                </div>

                <div class="doc-tabs">
                    <?php $first = true; foreach ($categories as $cat => $docs): ?>
                        <button type="button"
                            class="doc-tab-btn <?= $first ? 'active' : '' ?>"
                            onclick="showCategory('<?= htmlspecialchars(str_replace("'", "\\'", $cat)) ?>', this)">
                            <?= htmlspecialchars($cat) ?>
                        </button>
                    <?php $first = false; endforeach; ?>
                </div>

                <p class="hint-text">* Processing Time may vary depending on request load, number of documents requested, and year of admission.</p>
                <p class="hint-text">** Price may change without prior notice. The Office may request additional payment when needed.</p>

                <!-- Document Tables per Category -->
                <?php $first = true; foreach ($categories as $cat => $docs): ?>
                <div class="doc-category <?= $first ? 'active' : '' ?>"
                    id="cat-<?= htmlspecialchars(str_replace(' ', '_', $cat)) ?>">
                    <table class="doc-table">
                        <thead>
                            <tr>
                                <th style="width:30px;"></th>
                                <th>Document (click to select)</th>
                                <th style="width:160px;">Processing Time*</th>
                                <th style="width:80px;">Quantity</th>
                                <th style="width:100px;">Price**</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($docs as $doc): ?>
                            <tr onclick="toggleDoc(<?= $doc['id'] ?>)" style="cursor:pointer;">
                                <td style="text-align:center;">
                                    <input type="checkbox" name="doc_ids[]"
                                        value="<?= $doc['id'] ?>"
                                        id="doc_<?= $doc['id'] ?>"
                                        onclick="event.stopPropagation(); toggleDoc(<?= $doc['id'] ?>)">
                                </td>
                                <td><?= htmlspecialchars($doc['document_name']) ?></td>
                                <td>
<?php
$min = intval($doc['processing_min_days'] ?? $doc['processing_days'] ?? 1);
$max = intval($doc['processing_max_days'] ?? $doc['processing_days'] ?? 1);
echo $min === $max
    ? $min . ' working day' . ($min > 1 ? 's' : '')
    : $min . '–' . $max . ' working days';
?>
</td>
                                <td>
                                    <input type="number" name="quantities[<?= $doc['id'] ?>]"
                                        id="qty_<?= $doc['id'] ?>"
                                        class="qty-input" value="1" min="1" max="10"
                                        disabled
                                        onclick="event.stopPropagation()"
                                        oninput="updateTotal()">
                                </td>
                                <td style="text-align:right;">
                                    ₱ <span id="price_<?= $doc['id'] ?>"><?= number_format($doc['price'], 2) ?></span>
                                    <input type="hidden" id="unitprice_<?= $doc['id'] ?>" value="<?= $doc['price'] ?>">
                                    <input type="hidden" id="docname_<?= $doc['id'] ?>" value="<?= htmlspecialchars($doc['document_name']) ?>">
                                    <input type="hidden" id="maxdays_<?= $doc['id'] ?>" value="<?= intval($doc['processing_max_days'] ?? $doc['processing_days'] ?? 5) ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php $first = false; endforeach; ?>

                <!-- Totals -->
                <div class="mt-4">
                    <table class="totals-table">
                        <tr class="grand-total">
                            <td class="total-label" style="font-weight:700;">TOTAL:</td>
                            <td class="total-value" style="font-size:15px; color:var(--pup-red);">
                                ₱ <span id="grandTotal">0.00</span>
                            </td>
                        </tr>
                    </table>
                </div>

            </div>
        </div>

        <div class="text-end">
            <!-- Triggers Order Summary modal, does NOT submit directly -->
            <button type="button" class="btn-submit-req" onclick="openOrderSummary()">
                <i class="fas fa-paper-plane me-2"></i>Submit Request
            </button>
        </div>
    </form>

    <?php endif; ?>

</div>

<!-- FOOTER -->
<div class="footer-pup">
    © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus |
    <a href="#">Terms of Use</a> | <a href="#">Privacy Statement</a>
</div>

<!-- ORDER SUMMARY MODAL -->
<div class="modal fade" id="orderSummaryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-file-alt me-2"></i>Request Summary — Please Review Before Submitting</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size:13px; padding: 0;">

                <!-- Summary details injected by JS -->
                <div style="padding: 16px 20px; border-bottom: 1px solid #eee;">
                    <div class="row g-2">
                        <div class="col-sm-4">
                            <span style="font-size:11px; color:#888; font-weight:600; text-transform:uppercase;">Purpose</span>
                            <div id="summaryPurpose" style="font-weight:600; color:#333; margin-top:2px;"></div>
                        </div>
                        <div class="col-sm-4">
                            <span style="font-size:11px; color:#888; font-weight:600; text-transform:uppercase;">Payment Method</span>
                            <div id="summaryPayment" style="font-weight:600; color:#333; margin-top:2px;"></div>
                        </div>
                        <div class="col-sm-4">
                            <span style="font-size:11px; color:#888; font-weight:600; text-transform:uppercase;">Est. Release Date</span>
                            <div id="summaryReleaseDate" style="font-weight:600; color:var(--pup-red); margin-top:2px;"></div>
                            <div style="font-size:10px; color:#aaa; margin-top:1px;">Based on slowest document (max days)</div>
                        </div>
                    </div>
                </div>

                <div style="padding: 16px 20px;">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th style="width:80px; text-align:center;">Qty</th>
                                <th style="width:100px; text-align:right;">Unit Price</th>
                                <th style="width:110px; text-align:right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="summaryItems"></tbody>
                        <tfoot>
                            <tr class="summary-total-row">
                                <td colspan="3" style="text-align:right;">TOTAL</td>
                                <td style="text-align:right;">₱ <span id="summaryTotal"></span></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div id="summaryNotice" class="alert alert-info py-2 mt-3 mb-0" style="font-size:12px;"></div>
                </div>

            </div>
            <div class="modal-footer" style="gap:8px;">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-edit me-1"></i> Cancel / Edit
                </button>
                <button type="button" class="btn btn-sm"
                    style="background:var(--pup-red); color:white; font-weight:600;"
                    onclick="confirmSubmit()">
                    <i class="fas fa-check me-1"></i> Confirm &amp; Submit
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Category tab switching
    function showCategory(cat, btn) {
        document.querySelectorAll('.doc-category').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.doc-tab-btn').forEach(el => el.classList.remove('active'));
        const id = 'cat-' + cat.replace(/ /g, '_');
        document.getElementById(id).classList.add('active');
        btn.classList.add('active');
    }

    // Toggle document checkbox + qty
    function toggleDoc(docId) {
        const cb  = document.getElementById('doc_' + docId);
        const qty = document.getElementById('qty_' + docId);
        cb.checked     = !cb.checked;
        qty.disabled   = !cb.checked;
        if (!cb.checked) qty.value = 1;
        updateTotal();
    }

    // Recalculate total
    function updateTotal() {
        let total = 0;
        document.querySelectorAll('input[name="doc_ids[]"]').forEach(cb => {
            if (cb.checked) {
                const id    = cb.value;
                const price = parseFloat(document.getElementById('unitprice_' + id).value);
                const qty   = parseInt(document.getElementById('qty_' + id).value) || 1;
                total += price * qty;
            }
        });
        document.getElementById('grandTotal').textContent = total.toFixed(2);
    }

    // Open order summary modal
    function openOrderSummary() {
        const form    = document.getElementById('requestForm');
        const purpose = form.querySelector('select[name="purpose"]').value;
        const pmRadio = form.querySelector('input[name="payment_method"]:checked');

        // Validate before showing modal
        if (!purpose) {
            alert('Please select a purpose for your request.');
            return;
        }
        if (!pmRadio) {
            alert('Please select a payment method.');
            return;
        }

        const selectedDocs = [...document.querySelectorAll('input[name="doc_ids[]"]:checked')];
        if (selectedDocs.length === 0) {
            alert('Please select at least one document.');
            return;
        }

        const paymentMethod = pmRadio.value;
        let total    = 0;
        let rows     = '';
        let maxDays  = 0;

        selectedDocs.forEach(cb => {
            const id       = cb.value;
            const name     = document.getElementById('docname_' + id).value;
            const price    = parseFloat(document.getElementById('unitprice_' + id).value);
            const qty      = parseInt(document.getElementById('qty_' + id).value) || 1;
            const subtotal = price * qty;
            const docMax   = parseInt(document.getElementById('maxdays_' + id).value) || 5;
            total   += subtotal;
            if (docMax > maxDays) maxDays = docMax;
            rows += `<tr>
                <td>${name}</td>
                <td style="text-align:center;">${qty}</td>
                <td style="text-align:right;">₱ ${price.toFixed(2)}</td>
                <td style="text-align:right;">₱ ${subtotal.toFixed(2)}</td>
            </tr>`;
        });

        // Calculate estimated release date (working days, skip weekends)
        const releaseDate = addWorkingDays(maxDays);
        const releaseFmt  = releaseDate.toLocaleDateString('en-PH', {
            year: 'numeric', month: 'long', day: 'numeric'
        });

        document.getElementById('summaryPurpose').textContent     = purpose;
        document.getElementById('summaryPayment').textContent     = paymentMethod;
        document.getElementById('summaryReleaseDate').textContent = releaseFmt;
        document.getElementById('summaryItems').innerHTML         = rows;
        document.getElementById('summaryTotal').textContent       = total.toFixed(2);

        // Dynamic notice based on payment method
        const notice = paymentMethod === 'Walk-in (Cashier)'
            ? '<i class="fas fa-info-circle me-1"></i> You selected <strong>Walk-in (Cashier)</strong>. Please proceed to the PUP Biñan Cashier\'s Office to settle payment after submitting.'
            : '<i class="fas fa-info-circle me-1"></i> You selected <strong>Walk-in (Bank Slip)</strong>. After submitting, upload your bank deposit slip in the Requests tab as proof of payment.';
        document.getElementById('summaryNotice').innerHTML = notice;

        new bootstrap.Modal(document.getElementById('orderSummaryModal')).show();
    }

    // Add N working days (client-side estimate; server applies full rule)
    function addWorkingDays(n) {
        const date = new Date();
        let added  = 0;
        while (added < n) {
            date.setDate(date.getDate() + 1);
            const dow = date.getDay(); // 0=Sun, 6=Sat
            if (dow !== 0 && dow !== 6) added++;
        }
        // Shift off weekends
        while (date.getDay() === 0 || date.getDay() === 6) {
            date.setDate(date.getDate() + 1);
        }
        return date;
    }

    // Confirm and submit form
    function confirmSubmit() {
        bootstrap.Modal.getInstance(document.getElementById('orderSummaryModal')).hide();
        document.getElementById('requestForm').submit();
    }

    // Show/hide "Others" free-text box
    document.querySelector('select[name="purpose"]').addEventListener('change', function () {
        const box = document.getElementById('othersBox');
        const inp = document.getElementById('purposeOtherInput');
        if (this.value === 'Others') {
            box.style.display = 'block';
            inp.required = true;
        } else {
            box.style.display = 'none';
            inp.required = false;
        }
    });
    // Restore on page reload if POST had Others selected
    (function(){
        const sel = document.querySelector('select[name="purpose"]');
        if (sel && sel.value === 'Others') {
            document.getElementById('othersBox').style.display = 'block';
            document.getElementById('purposeOtherInput').required = true;
        }
    })();
</script>
</body>
</html>