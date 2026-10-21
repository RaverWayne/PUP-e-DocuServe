<?php
session_start();
require_once '../config/db.php';
require_once "../includes/session_timeout.php";
require_once 'csrf.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $name              = trim($_POST['document_name'] ?? '');
    $price             = floatval($_POST['price'] ?? 0);
    $category          = trim($_POST['category'] ?? '');
    $days              = intval($_POST['processing_days'] ?? 3);
    $min_days          = intval($_POST['processing_min_days'] ?? $days);
    $max_days          = intval($_POST['processing_max_days'] ?? $days);
    $description       = trim($_POST['description'] ?? '') ?: null;
    $requirements      = trim($_POST['requirements'] ?? '') ?: null;
    $requires_clearance = isset($_POST['requires_clearance']) ? 1 : 0;

    if (empty($name) || empty($category) || $price <= 0) {
        $error = "Please fill in all required fields.";
    } else {
        $pdo->prepare(
            "INSERT INTO documents
             (document_name, price, category, processing_days, processing_min_days, processing_max_days,
              description, requirements, requires_clearance)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$name, $price, $category, $days, $min_days, $max_days,
                    $description, $requirements, $requires_clearance]);
        $pdo->prepare(
            "INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target)
             VALUES (?, ?, ?, ?, ?)"
        )->execute(["Added document: $name", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role'], $name]);
        $success = "Document added successfully.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    $id                = intval($_POST['doc_id']);
    $name              = trim($_POST['document_name'] ?? '');
    $price             = floatval($_POST['price'] ?? 0);
    $category          = trim($_POST['category'] ?? '');
    $days              = intval($_POST['processing_days'] ?? 3);
    $min_days          = intval($_POST['processing_min_days'] ?? $days);
    $max_days          = intval($_POST['processing_max_days'] ?? $days);
    $description       = trim($_POST['description'] ?? '') ?: null;
    $requirements      = trim($_POST['requirements'] ?? '') ?: null;
    $requires_clearance = isset($_POST['requires_clearance']) ? 1 : 0;

    $pdo->prepare(
        "UPDATE documents
         SET document_name=?, price=?, category=?, processing_days=?,
             processing_min_days=?, processing_max_days=?,
             description=?, requirements=?, requires_clearance=?
         WHERE id=?"
    )->execute([$name, $price, $category, $days, $min_days, $max_days,
                $description, $requirements, $requires_clearance, $id]);
    $pdo->prepare(
        "INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target)
         VALUES (?, ?, ?, ?, ?)"
    )->execute(["Edited document ID $id", $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role'], $name]);
    $success = "Document updated successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'toggle') {
    $id     = intval($_POST['doc_id']);
    $status = intval($_POST['new_status']);
    $pdo->prepare("UPDATE documents SET is_active = ? WHERE id = ?")->execute([$status, $id]);
    $pdo->prepare(
        "INSERT INTO system_logs (action, performed_by, performed_by_id, performed_by_role, target)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        "Set document ID $id to " . ($status ? 'Active' : 'Inactive'),
        $admin_name, $_SESSION['admin_id'], $_SESSION['admin_role'], "Doc ID $id"
    ]);
    $success = "Document status updated.";
}

$documents = $pdo->query(
    "SELECT * FROM documents ORDER BY category, document_name"
)->fetchAll();

$categories = [
    'Transcript of Records',
    'Certifications',
    'CAV',
    'Unclaimed',
    'Others',
];

$current_page = 'documents';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Documents | PUP e-DocuServe</title>
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
        .page-content { padding: 24px; flex: 1; }
        .section-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-header { background: #f5f5f5; padding: 12px 18px; font-weight: 700; font-size: 13px; color: #333; border-bottom: 1px solid #eee; }
        .section-body { padding: 20px; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .data-table th { padding: 9px 12px; font-weight: 600; color: #555; border-bottom: 1px solid #eee; background: #f8f8f8; }
        .data-table td { padding: 9px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fafafa; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 3px; }
        .form-label .public-note { font-weight: 400; color: #e67e22; font-size: 11px; }
        .form-control, .form-select { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 7px 10px; }
        .form-control:focus, .form-select:focus { border-color: var(--sa-color); box-shadow: 0 0 0 2px rgba(26,35,126,0.1); }
        .field-hint { font-size: 11px; color: #999; margin-top: 3px; }
        .btn-add { background: var(--sa-color); color: white; border: none; padding: 8px 20px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-add:hover { background: #283593; }
        .btn-sm-action { padding: 4px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-edit { background: #3498db; color: white; }
        .btn-edit:hover { background: #2980b9; }
        .btn-disable { background: #e74c3c; color: white; }
        .btn-disable:hover { background: #c0392b; }
        .btn-enable { background: #27ae60; color: white; }
        .btn-enable:hover { background: #219a52; }
        .clearance-badge { background: #fdecea; color: #c0392b; font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 10px; border: 1px solid #e74c3c; white-space: nowrap; }
        .modal-header { background: var(--sa-color); color: white; }
        .modal-header .btn-close { filter: invert(1); }
        .footer-admin { background: white; border-top: 1px solid #eee; padding: 12px 24px; text-align: center; font-size: 12px; color: #aaa; }
        .public-facing-note { background: #fff8e1; border: 1px solid #ffc107; border-left: 4px solid #e67e22; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #856404; margin-bottom: 16px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-file-invoice me-2" style="color:var(--sa-color);"></i>Manage Documents</div>
        <?php $account_href = 'account_settings.php'; include __DIR__ . '/../includes/admin_topbar.php'; ?>
    </div>

    <div class="page-content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3" style="font-size:13px;"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3" style="font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="section-card">
            <div class="section-header"><i class="fas fa-plus-circle me-2" style="color:var(--sa-color);"></i>Add New Document</div>
            <div class="section-body">
                <div class="public-facing-note">
                    <strong><i class="fas fa-globe me-1"></i> Public Visibility:</strong> Description, Requirements, and General Clearance flag are shown publicly on the landing page to all visitors — no login required.
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Document Name <span style="color:var(--pup-red);">*</span></label>
                            <input type="text" name="document_name" class="form-control" placeholder="Document Name" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Price (₱) <span style="color:var(--pup-red);">*</span></label>
                            <input type="number" name="price" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category <span style="color:var(--pup-red);">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c ?>"><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Base Days</label>
                            <input type="number" name="processing_days" class="form-control" value="3" min="1" title="Legacy base processing days">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Min Days</label>
                            <input type="number" name="processing_min_days" class="form-control" value="3" min="1">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Max Days</label>
                            <input type="number" name="processing_max_days" class="form-control" value="5" min="1">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">
                                Short Description
                                <span class="public-note"><i class="fas fa-globe me-1"></i> shown on landing page</span>
                            </label>
                            <textarea name="description" class="form-control" rows="2"
                                placeholder="e.g. Official certification attesting to the student's good moral standing…"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                Submission Requirements
                                <span class="public-note"><i class="fas fa-globe me-1"></i> shown on landing page</span>
                            </label>
                            <textarea name="requirements" class="form-control" rows="2"
                                placeholder="e.g. • ODRS Application Voucher&#10;• Proof of Payment (Official Receipt)&#10;• Valid ID"></textarea>
                            <div class="field-hint">Use • bullet points, one item per line. Based on the PUP Citizen's Charter.</div>
                        </div>
                        <div class="col-md-2 d-flex flex-column justify-content-center">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="requires_clearance" value="1" id="addClearance">
                                <label class="form-check-label" for="addClearance" style="font-size:12px; font-weight:600;">
                                    ⚠ Requires General Clearance
                                </label>
                                <div class="field-hint">Check if this document requires a signed General Clearance before submission.</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-add"><i class="fas fa-plus me-1"></i>Add Document</button>
                </form>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header"><i class="fas fa-list me-2" style="color:var(--sa-color);"></i>Documents List (<?= count($documents) ?>)</div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Document Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Processing Time</th>
                            <th>Clearance?</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $i => $doc): ?>
                        <?php
                            $min = intval($doc['processing_min_days'] ?? $doc['processing_days'] ?? 1);
                            $max = intval($doc['processing_max_days'] ?? $doc['processing_days'] ?? 1);
                            $proc_display = ($min === $max)
                                ? "{$min} day" . ($min > 1 ? 's' : '')
                                : "{$min}–{$max} days";
                            $desc_short = $doc['description']
                                ? (mb_strlen($doc['description']) > 45
                                    ? mb_substr($doc['description'], 0, 45) . '…'
                                    : $doc['description'])
                                : '<span style="color:#ccc;">—</span>';
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($doc['document_name']) ?></td>
                            <td><?= htmlspecialchars($doc['category']) ?></td>
                            <td>₱ <?= number_format($doc['price'], 2) ?></td>
                            <td><?= $proc_display ?></td>
                            <td style="text-align:center;">
                                <?php if ($doc['requires_clearance'] ?? 0): ?>
                                    <span class="clearance-badge">⚠ Yes</span>
                                <?php else: ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span title="<?= htmlspecialchars($doc['description'] ?? '') ?>">
                                    <?= $doc['description'] ? htmlspecialchars(mb_strlen($doc['description']) > 45 ? mb_substr($doc['description'],0,45).'…' : $doc['description']) : '<span style="color:#ccc;">—</span>' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $doc['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $doc['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:flex; gap:5px; justify-content:center;">
                                    <button class="btn-sm-action btn-edit"
                                        onclick="openEdit(<?= htmlspecialchars(json_encode($doc), ENT_QUOTES) ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $doc['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn-sm-action <?= $doc['is_active'] ? 'btn-disable' : 'btn-enable' ?>">
                                            <i class="fas fa-<?= $doc['is_active'] ? 'ban' : 'check' ?>"></i>
                                            <?= $doc['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="footer-admin">
        © <?= date('Y') ?> Polytechnic University of the Philippines – Biñan Campus | PUP e-DocuServe Super Admin
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Document</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <div class="modal-body" style="font-size:13px;">
                    <input type="hidden" name="doc_id" id="editDocId">

                    <div class="public-facing-note" style="font-size:11px;">
                        <i class="fas fa-globe me-1"></i>
                        <strong><i class="fas fa-globe me-1"></i> Public:</strong> Description, Requirements, and Clearance flag appear on the public landing page to all visitors.
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label">Document Name</label>
                            <input type="text" name="document_name" id="editDocName" class="form-control" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Category</label>
                            <select name="category" id="editDocCat" class="form-select">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c ?>"><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-3">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" name="price" id="editDocPrice" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Base Days</label>
                            <input type="number" name="processing_days" id="editDocDays" class="form-control" min="1" title="Legacy base processing days">
                        </div>
                        <div class="col-3">
                            <label class="form-label">Min Days</label>
                            <input type="number" name="processing_min_days" id="editDocMinDays" class="form-control" min="1">
                        </div>
                        <div class="col-3">
                            <label class="form-label">Max Days</label>
                            <input type="number" name="processing_max_days" id="editDocMaxDays" class="form-control" min="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Short Description
                            <span style="font-weight:400; color:#e67e22; font-size:11px;"><i class="fas fa-globe me-1"></i> shown on landing page</span>
                        </label>
                        <textarea name="description" id="editDocDesc" class="form-control" rows="2"
                            placeholder="Brief description of this document…"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Submission Requirements
                            <span style="font-weight:400; color:#e67e22; font-size:11px;"><i class="fas fa-globe me-1"></i> shown on landing page</span>
                        </label>
                        <textarea name="requirements" id="editDocReqs" class="form-control" rows="4"
                            placeholder="• ODRS Application Voucher&#10;• Proof of Payment (Official Receipt)&#10;• Valid ID"></textarea>
                        <div class="field-hint">Use • bullet points, one item per line. Based on the PUP Citizen's Charter.</div>
                    </div>

                    <div class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="requires_clearance" value="1" id="editClearance">
                            <label class="form-check-label" for="editClearance" style="font-size:12px; font-weight:600;">
                                ⚠ Requires General Clearance
                            </label>
                            <div class="field-hint">Check if this document requires a signed General Clearance before submission.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:var(--sa-color); color:white;">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEdit(doc) {
    document.getElementById('editDocId').value       = doc.id;
    document.getElementById('editDocName').value     = doc.document_name;
    document.getElementById('editDocPrice').value    = doc.price;
    document.getElementById('editDocDays').value     = doc.processing_days   || 3;
    document.getElementById('editDocMinDays').value  = doc.processing_min_days || doc.processing_days || 1;
    document.getElementById('editDocMaxDays').value  = doc.processing_max_days || doc.processing_days || 1;
    document.getElementById('editDocCat').value      = doc.category;
    document.getElementById('editDocDesc').value     = doc.description  || '';
    document.getElementById('editDocReqs').value     = doc.requirements || '';
    document.getElementById('editClearance').checked = (doc.requires_clearance == 1);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>
