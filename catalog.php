<?php
/**
 * catalog.php — Public read-only Document Catalog
 * No session, no CSRF, no auth required.
 * Queries ONLY the documents table (strict column whitelist).
 */
require_once 'config/db.php';

// Public whitelist query — no joins to sensitive tables
$catalog_docs = $pdo->query(
    "SELECT document_name, category, price,
            processing_min_days, processing_max_days, processing_days,
            description, requirements, requires_clearance, is_active
     FROM documents
     ORDER BY is_active DESC, category, document_name"
)->fetchAll();

$catalog_by_category = [];
foreach ($catalog_docs as $doc) {
    $catalog_by_category[$doc['category']][] = $doc;
}

$total_active   = count(array_filter($catalog_docs, fn($d) => $d['is_active']));
$total_inactive = count($catalog_docs) - $total_active;

$category_order = ['Transcript of Records', 'Certifications', 'CAV', 'Others', 'Unclaimed'];
$remaining_cats = array_diff(array_keys($catalog_by_category), $category_order);
$ordered_cats   = array_merge(
    array_filter($category_order, fn($c) => isset($catalog_by_category[$c])),
    $remaining_cats
);

$category_icons = [
    'Transcript of Records' => 'fas fa-scroll',
    'Certifications'        => 'fas fa-certificate',
    'CAV'                   => 'fas fa-globe',
    'Others'                => 'fas fa-folder-open',
    'Unclaimed'             => 'fas fa-inbox',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Catalog | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --pup-red:       #6D1A1A;
            --pup-red-light: #8B2020;
            --pup-gold:      #FFD700;
            --pup-dark:      #1a1a1a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f0f0; color: #333; font-size: 15px; }

        .navbar-pup {
            background: var(--pup-red); padding: 10px 20px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 999;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        .navbar-pup .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .navbar-pup .brand img { height: 38px; width: 38px; object-fit: contain; }
        .navbar-pup .brand-text { color: white; font-size: 15px; font-weight: 600; line-height: 1.3; }
        .navbar-pup .brand-text span { display: block; font-size: 11px; font-weight: 400; opacity: .8; }
        .nav-actions { display: flex; gap: 8px; align-items: center; }
        .btn-nav-outline {
            background: transparent; color: white;
            border: 1px solid rgba(255,255,255,0.5);
            padding: 6px 16px; border-radius: 4px; font-size: 13px;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-nav-outline:hover { background: rgba(255,255,255,0.15); color: white; }
        .btn-nav-gold {
            background: var(--pup-gold); color: var(--pup-dark);
            border: none; padding: 6px 18px; border-radius: 4px;
            font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s;
        }
        .btn-nav-gold:hover { background: #e6c200; color: var(--pup-dark); }

        .page-header {
            background: linear-gradient(135deg, var(--pup-red) 0%, #5a0000 100%);
            color: white; padding: 34px 20px 28px; text-align: center;
        }
        .page-header h1 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .page-header h1 span { color: var(--pup-gold); }
        .page-header p { font-size: 14px; opacity: .8; }
        .page-header p.note { font-size: 13px; opacity: .65; margin-top: 4px; }

        .catalog-wrap { max-width: 1100px; margin: 0 auto; padding: 28px 20px 48px; }

        .stats-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-pill {
            background: white; border: 1px solid #e0e0e0; border-radius: 20px;
            padding: 5px 16px; font-size: 13px; color: #555;
            display: flex; align-items: center; gap: 6px;
        }
        .stat-pill i { color: var(--pup-red); font-size: 12px; }

        .clearance-callout {
            background: #fff8e1; border: 1px solid #ffc107;
            border-left: 4px solid #e67e22; border-radius: 6px;
            padding: 14px 18px; font-size: 14px; color: #856404;
            margin-bottom: 24px; line-height: 1.7;
        }

        .category-group { margin-bottom: 28px; }
        .category-title {
            font-size: 11px; font-weight: 700; color: #888;
            text-transform: uppercase; letter-spacing: 1px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px; margin-bottom: 10px;
            display: flex; align-items: center; gap: 8px;
        }

        .catalog-card {
            background: white; border: 1px solid #e5e5e5; border-radius: 8px;
            padding: 14px 18px; margin-bottom: 8px;
            transition: box-shadow 0.15s;
        }
        .catalog-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .catalog-card.inactive { opacity: .5; filter: grayscale(20%); }

        .card-top {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 12px; flex-wrap: wrap;
        }
        .doc-name { font-size: 15px; font-weight: 700; color: #1a1a1a; }
        .doc-desc { font-size: 13px; color: #888; font-style: italic; margin-top: 2px; }

        .badges { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding-top: 2px; }
        .fee-badge {
            background: var(--pup-gold); color: var(--pup-dark);
            font-weight: 700; font-size: 14px;
            padding: 3px 13px; border-radius: 20px; white-space: nowrap;
        }
        .catalog-card.inactive .fee-badge { text-decoration: line-through; opacity: .7; }
        .proc-chip {
            background: #eef2ff; color: #3949ab;
            font-size: 12px; padding: 3px 10px; border-radius: 12px; white-space: nowrap;
        }
        .badge-available   { color: #27ae60; font-size: 12px; font-weight: 600; }
        .badge-unavailable { color: #aaa;    font-size: 12px; font-weight: 600; }
        .badge-clearance {
            background: #fdecea; color: #c0392b;
            font-size: 11px; font-weight: 600;
            padding: 2px 9px; border-radius: 12px;
            border: 1px solid #e74c3c; white-space: nowrap;
        }

        details.req-details { margin-top: 8px; }
        details.req-details > summary {
            cursor: pointer; list-style: none;
            font-size: 13px; color: var(--pup-red); font-weight: 600;
            padding: 3px 0; user-select: none;
        }
        details.req-details > summary::-webkit-details-marker { display: none; }
        details.req-details[open] > summary { margin-bottom: 6px; }
        .req-body {
            font-size: 13px; color: #555; line-height: 1.9;
            background: #f9f9f9; border-radius: 4px;
            padding: 10px 14px; border-left: 3px solid #ddd;
        }

        .claiming-wrap { margin-top: 4px; margin-bottom: 24px; }
        details.claiming-details > summary {
            cursor: pointer; list-style: none;
            font-size: 14px; font-weight: 700; color: #555;
            padding: 12px 0; user-select: none;
            border-top: 1px solid #ddd; display: flex; align-items: center; gap: 8px;
        }
        details.claiming-details > summary::-webkit-details-marker { display: none; }
        .claiming-body {
            background: white; border: 1px solid #e8e8e8; border-radius: 6px;
            padding: 18px 22px; margin-top: 8px;
            display: grid; grid-template-columns: 1fr 1fr; gap: 24px;
        }
        .claiming-tier h6 { font-size: 13px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .claiming-tier ul { padding-left: 18px; margin: 0; font-size: 13px; color: #555; line-height: 1.9; }

        .cta-bar {
            background: white; border: 1px solid #e5e5e5; border-radius: 8px;
            padding: 24px; text-align: center; margin-top: 24px;
        }
        .cta-bar p { font-size: 15px; color: #555; margin-bottom: 14px; font-weight: 500; }
        .cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-login {
            background: var(--pup-gold); color: var(--pup-dark);
            font-weight: 700; font-size: 14px;
            padding: 9px 28px; border-radius: 5px; text-decoration: none; transition: all 0.2s;
        }
        .btn-login:hover { background: #e6c200; color: var(--pup-dark); }
        .btn-register {
            background: var(--pup-red); color: white;
            font-weight: 600; font-size: 14px;
            padding: 9px 28px; border-radius: 5px; text-decoration: none; transition: all 0.2s;
        }
        .btn-register:hover { background: var(--pup-red-light); color: white; }

        .footer-pup {
            background: #f8f8f8; border-top: 1px solid #ddd;
            padding: 12px 20px; text-align: center; font-size: 13px; color: #888;
        }
        .footer-pup a { color: var(--pup-red); text-decoration: none; }

        @media (max-width: 768px) {
            .card-top { flex-direction: column; }
            .claiming-body { grid-template-columns: 1fr; }
        }

        .search-filter-bar {
            background: white; border: 1px solid #e0e0e0; border-radius: 8px;
            padding: 16px 20px; margin-bottom: 24px;
            display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
        }
        .search-input-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-input-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 13px; pointer-events: none; }
        .search-input {
            width: 100%; padding: 8px 10px 8px 32px;
            border: 1px solid #ccc; border-radius: 4px; font-size: 14px;
            outline: none; transition: border-color 0.2s;
        }
        .search-input:focus { border-color: var(--pup-red); box-shadow: 0 0 0 2px rgba(109,26,26,0.1); }
        .filter-select {
            padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px;
            font-size: 13px; color: #333; outline: none; min-width: 180px;
            transition: border-color 0.2s; background: white;
        }
        .filter-select:focus { border-color: var(--pup-red); }
        .btn-clear-filter {
            background: #f0f0f0; border: 1px solid #ccc; color: #555;
            padding: 8px 16px; border-radius: 4px; font-size: 13px;
            cursor: pointer; transition: all 0.2s; white-space: nowrap;
        }
        .btn-clear-filter:hover { background: var(--pup-red); color: white; border-color: var(--pup-red); }
        .no-results-msg {
            display: none; text-align: center; padding: 32px;
            color: #aaa; font-size: 15px; background: white;
            border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 24px;
        }
    </style>
</head>
<body>

<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="assets/images/pup-logo.png" alt="PUP Logo" onerror="this.style.display='none'">
        <div class="brand-text">
            PUP e-DocuServe
            <span>Binan Campus &mdash; Online Document Request</span>
        </div>
    </a>
    <div class="nav-actions">
        <a href="index.php"         class="btn-nav-outline"><i class="fas fa-arrow-left me-1"></i> Back to Home</a>
        <a href="auth/register.php" class="btn-nav-outline">Register</a>
        <a href="auth/login.php"    class="btn-nav-gold">Login</a>
    </div>
</nav>

<div class="page-header">
    <h1><i class="fas fa-file-alt me-2"></i>Document <span>Catalog</span></h1>
    <p>All requestable documents, fees, processing times, and submission requirements</p>
    <p class="note">Based on the PUP Citizen's Charter &mdash; for reference only</p>
</div>

<div class="catalog-wrap">

    <div class="stats-bar">
        <span class="stat-pill"><i class="fas fa-check-circle"></i> <strong><?= $total_active ?></strong> Available</span>
        <?php if ($total_inactive > 0): ?>
        <span class="stat-pill"><i class="fas fa-ban"></i> <strong><?= $total_inactive ?></strong> Currently Unavailable</span>
        <?php endif; ?>
        <span class="stat-pill"><i class="fas fa-list"></i> <strong><?= count($catalog_docs) ?></strong> Total Documents</span>
    </div>

    <div class="clearance-callout">
        <strong><i class="fas fa-exclamation-triangle me-1"></i> General Clearance is required for some documents.</strong><br>
        A signed General Clearance &mdash; obtained from the University Library, Accounting Office, Office of Student Services, and Legal Counsel &mdash; must be submitted alongside requests marked
        <strong><i class="fas fa-exclamation-triangle"></i> Requires General Clearance</strong> below.
    </div>

    <div class="search-filter-bar">
        <div class="search-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="catalogSearch" class="search-input" placeholder="Search documents by name...">
        </div>
        <select id="catalogCategoryFilter" class="filter-select">
            <option value="">All Categories</option>
            <?php foreach ($ordered_cats as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn-clear-filter" onclick="clearCatalogFilter()">
            <i class="fas fa-times me-1"></i> Clear
        </button>
    </div>

    <div id="noResultsMsg" class="no-results-msg">
        <i class="fas fa-search" style="font-size:32px; display:block; margin-bottom:12px;"></i>
        No documents found matching your search.
    </div>

    <?php foreach ($ordered_cats as $cat): ?>
    <div class="category-group" data-category="<?= htmlspecialchars($cat) ?>">
        <div class="category-title">
            <i class="<?= $category_icons[$cat] ?? 'fas fa-file' ?>"></i>
            <?= htmlspecialchars($cat) ?>
        </div>

        <?php foreach ($catalog_by_category[$cat] as $doc): ?>
        <?php
            $active = (bool)$doc['is_active'];
            $min_d  = intval($doc['processing_min_days'] ?? $doc['processing_days'] ?? 1);
            $max_d  = intval($doc['processing_max_days'] ?? $doc['processing_days'] ?? 1);
            $proc   = ($min_d === $max_d)
                ? $min_d . ' working day' . ($min_d !== 1 ? 's' : '')
                : $min_d . ' - ' . $max_d . ' working days';
        ?>
        <div class="catalog-card <?= $active ? '' : 'inactive' ?>"
             data-name="<?= htmlspecialchars(strtolower($doc['document_name'])) ?>"
             data-category="<?= htmlspecialchars($cat) ?>">
            <div class="card-top">
                <div>
                    <div class="doc-name"><?= htmlspecialchars($doc['document_name']) ?></div>
                    <?php if (!empty($doc['description'])): ?>
                        <div class="doc-desc"><?= htmlspecialchars($doc['description']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="badges">
                    <span class="fee-badge">&#8369; <?= number_format($doc['price'], 2) ?></span>
                    <span class="proc-chip"><i class="fas fa-clock me-1"></i><?= $proc ?></span>
                    <?php if ($active): ?>
                        <span class="badge-available"><i class="fas fa-circle me-1" style="font-size:9px;"></i>Available</span>
                    <?php else: ?>
                        <span class="badge-unavailable"><i class="fas fa-circle me-1" style="font-size:9px;"></i>Currently Unavailable</span>
                    <?php endif; ?>
                    <?php if (!empty($doc['requires_clearance'])): ?>
                        <span class="badge-clearance"><i class="fas fa-exclamation-triangle me-1"></i>Requires General Clearance</span>
                    <?php endif; ?>
                </div>
            </div>

            <details class="req-details">
                <summary><i class="fas fa-clipboard-list me-1"></i>View Submission Requirements</summary>
                <div class="req-body">
                    <?php if (!empty($doc['requirements'])): ?>
                        <?= nl2br(htmlspecialchars($doc['requirements'])) ?>
                    <?php else: ?>
                        <em style="color:#aaa;">No special requirements on file &mdash; standard submission applies.</em>
                    <?php endif; ?>
                </div>
            </details>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div class="claiming-wrap">
        <details class="claiming-details">
            <summary>
                <i class="fas fa-user-friends"></i>
                Claiming via a Representative?
                <i class="fas fa-chevron-down ms-auto" style="font-size:12px; opacity:.5;"></i>
            </summary>
            <div class="claiming-body">
                <div class="claiming-tier">
                    <h6><i class="fas fa-users me-2" style="color:var(--pup-red);"></i>For Immediate Family Members</h6>
                    <ul>
                        <li>Duly signed <strong>Authorization Letter</strong> from the requestor</li>
                        <li><strong>Photocopy of Birth Certificate</strong> of the claimant (to prove relationship)</li>
                        <li><strong>Photocopy of Valid Government-Issued IDs</strong> of both claimant and requestor</li>
                    </ul>
                </div>
                <div class="claiming-tier">
                    <h6><i class="fas fa-user-tie me-2" style="color:var(--pup-red);"></i>For Non-Immediate / Authorized Representatives</h6>
                    <ul>
                        <li>Duly <strong>notarized Special Power of Attorney (SPA)</strong> &mdash; notarized at the requestor's current location</li>
                        <li><strong>Photocopy of Valid Government-Issued IDs</strong> of both claimant and requestor</li>
                    </ul>
                </div>
            </div>
        </details>
    </div>

    <div class="cta-bar">
        <p>Ready to request your documents? Login or register to get started.</p>
        <div class="cta-btns">
            <a href="auth/login.php"    class="btn-login"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
            <a href="auth/register.php" class="btn-register"><i class="fas fa-user-plus me-2"></i>Register</a>
        </div>
    </div>

</div>

<div class="footer-pup">
    &copy; <?= date('Y') ?> Polytechnic University of the Philippines &ndash; Binan Campus |
    <a href="index.php">Home</a> |
    <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Use</a> |
    <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Statement</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
        crossorigin="anonymous"></script>
<script>
function filterCatalog() {
    const search    = document.getElementById('catalogSearch').value.toLowerCase().trim();
    const catFilter = document.getElementById('catalogCategoryFilter').value;
    const groups    = document.querySelectorAll('.category-group');
    let totalVisible = 0;

    groups.forEach(group => {
        const groupCat = group.getAttribute('data-category');
        if (catFilter && groupCat !== catFilter) {
            group.style.display = 'none';
            return;
        }
        const cards = group.querySelectorAll('.catalog-card');
        let visibleInGroup = 0;
        cards.forEach(card => {
            const name    = card.getAttribute('data-name') || '';
            const matches = !search || name.includes(search);
            card.style.display = matches ? '' : 'none';
            if (matches) visibleInGroup++;
        });
        group.style.display = visibleInGroup > 0 ? '' : 'none';
        totalVisible += visibleInGroup;
    });

    document.getElementById('noResultsMsg').style.display = totalVisible === 0 ? 'block' : 'none';
}

function clearCatalogFilter() {
    document.getElementById('catalogSearch').value        = '';
    document.getElementById('catalogCategoryFilter').value = '';
    filterCatalog();
}

document.getElementById('catalogSearch').addEventListener('input', filterCatalog);
document.getElementById('catalogCategoryFilter').addEventListener('change', filterCatalog);
</script>

<?php include 'includes/legal_modals.php'; ?>
</body>
</html>
