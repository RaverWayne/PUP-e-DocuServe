<?php
session_start();
require_once '../config/db.php';
require_once 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Session timeout
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > 600) {
        session_unset();
        session_destroy();
        header("Location: ../auth/login.php?timeout=1");
        exit();
    }
}
$_SESSION['last_activity'] = time();

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check if already submitted
$check = $pdo->prepare("SELECT id FROM feedback WHERE user_id = ?");
$check->execute([$user_id]);
$already_submitted = $check->fetch();

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_submitted) {
    csrf_verify();
    $q1  = intval($_POST['q1']  ?? 0);
    $q2  = intval($_POST['q2']  ?? 0);
    $q3  = intval($_POST['q3']  ?? 0);
    $q4  = intval($_POST['q4']  ?? 0);
    $q5  = intval($_POST['q5']  ?? 0);
    $q6  = intval($_POST['q6']  ?? 0);
    $q7  = intval($_POST['q7']  ?? 0);
    $q8  = intval($_POST['q8']  ?? 0);
    $q9  = intval($_POST['q9']  ?? 0);
    $q10 = intval($_POST['q10'] ?? 0);
    $suggestions = trim($_POST['suggestions'] ?? '');

    $valid = true;
    foreach ([$q1,$q2,$q3,$q4,$q5,$q6,$q7,$q8,$q9,$q10] as $q) {
        if ($q < 1 || $q > 5) { $valid = false; break; }
    }

    if (!$valid) {
        $error = "Please answer all questions before submitting.";
    } else {
        $pdo->prepare("
            INSERT INTO feedback (user_id, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10, suggestions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$user_id, $q1, $q2, $q3, $q4, $q5, $q6, $q7, $q8, $q9, $q10, $suggestions]);
        $success = true;
    }
}

$questions = [
    'q1'  => 'How easy was it to navigate and use the system?',
    'q2'  => 'How satisfied are you with the document request process?',
    'q3'  => 'How would you rate the clarity of the instructions provided?',
    'q4'  => 'How satisfied are you with the payment process (bank slip / walk-in)?',
    'q5'  => 'How would you rate the speed and responsiveness of the system?',
    'q6'  => 'How satisfied are you with the request status monitoring feature?',
    'q7'  => 'How easy was it to register and set up your account?',
    'q8'  => 'How would you rate the overall design and appearance of the system?',
    'q9'  => 'How confident are you that your personal information is secure in the system?',
    'q10' => 'Overall, how satisfied are you with PUP e-DocuServe?',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | PUP e-DocuServe</title>
    <link rel="icon" type="image/png" href="../assets/images/pup-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pup-red: #6D1A1A; --pup-gold: #FFD700; }
        body { background: #f0f0f0; font-family: 'Segoe UI', sans-serif; font-size: 13px; margin: 0; }
        .navbar-pup { background: var(--pup-red); padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999; box-shadow: 0 2px 6px rgba(0,0,0,0.3); }
        .navbar-pup .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .navbar-pup .brand img { height: 38px; width: 38px; object-fit: contain; }
        .navbar-pup .brand-text { color: white; font-size: 13px; font-weight: 600; line-height: 1.3; }
        .navbar-pup .brand-text span { display: block; font-size: 11px; font-weight: 400; opacity: 0.8; }
        .dropdown-toggle { background: none; border: none; color: white; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .dropdown-toggle:focus { box-shadow: none; }
        .tabs-bar { background: white; border-bottom: 1px solid #ddd; padding: 0 20px; }
        .tabs-bar .nav-tabs { border-bottom: none; }
        .tabs-bar .nav-link { color: #555; font-size: 13px; padding: 12px 16px; border: none; border-bottom: 3px solid transparent; border-radius: 0; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .tabs-bar .nav-link:hover { color: var(--pup-red); }
        .tabs-bar .nav-link.active { color: var(--pup-red); border-bottom-color: var(--pup-red); font-weight: 600; }
        .content-wrapper { max-width: 780px; margin: 30px auto; padding: 0 16px 60px; }
        .feedback-header { text-align: center; margin-bottom: 28px; }
        .feedback-header h4 { font-size: 18px; font-weight: 700; color: var(--pup-red); margin-bottom: 6px; }
        .feedback-header p { font-size: 13px; color: #666; }
        .section-card { background: white; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
        .section-header { background: var(--pup-red); color: white; padding: 11px 18px; font-weight: 700; font-size: 13px; }
        .section-body { padding: 20px 24px; }
        .question-row { display: flex; align-items: center; justify-content: space-between; padding: 13px 0; border-bottom: 1px solid #f0f0f0; gap: 16px; }
        .question-row:last-child { border-bottom: none; }
        .question-text { flex: 1; font-size: 13px; color: #333; font-weight: 500; }
        .question-num { font-size: 11px; color: #aaa; margin-right: 6px; }
        .rating-group { display: flex; gap: 6px; flex-shrink: 0; }
        .rating-group input[type="radio"] { display: none; }
        .rating-group label { width: 36px; height: 36px; border: 2px solid #ddd; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; cursor: pointer; color: #888; transition: all 0.2s; user-select: none; }
        .rating-group label:hover { border-color: var(--pup-red); color: var(--pup-red); background: #fff0f0; }
        .rating-group input[type="radio"]:checked + label { background: var(--pup-red); border-color: var(--pup-red); color: white; }
        .rating-legend { display: flex; justify-content: flex-end; gap: 14px; font-size: 11px; color: #aaa; margin-bottom: 8px; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
        .form-control { font-size: 13px; border: 1px solid #ccc; border-radius: 4px; padding: 8px 10px; }
        .form-control:focus { border-color: var(--pup-red); box-shadow: 0 0 0 2px rgba(109,26,26,0.1); outline: none; }
        .btn-submit { background: var(--pup-red); color: white; border: none; padding: 11px 36px; border-radius: 5px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background: #8B2020; }
        .btn-skip { background: #f0f0f0; color: #777; border: 1px solid #ccc; padding: 11px 36px; border-radius: 5px; font-size: 13px; cursor: pointer; width: 100%; text-align: center; display: block; text-decoration: none; margin-top: 8px; }
        .btn-skip:hover { background: #e0e0e0; color: #555; }
        .success-box, .already-box { text-align: center; background: white; border-radius: 8px; padding: 50px 30px; border: 1px solid #ddd; }
        .success-box i { font-size: 52px; color: #27ae60; margin-bottom: 16px; display: block; }
        .already-box i { font-size: 52px; color: var(--pup-gold); margin-bottom: 16px; display: block; }
        .success-box h5, .already-box h5 { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .success-box p, .already-box p { font-size: 13px; color: #666; margin-bottom: 20px; }
        .btn-go { background: var(--pup-red); color: white; border: none; padding: 9px 28px; border-radius: 5px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-go:hover { background: #8B2020; color: white; }
        @media (max-width: 600px) { .question-row { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>

<nav class="navbar-pup">
    <a href="index.php" class="brand">
        <img src="../assets/images/pup-logo.png" alt="PUP" onerror="this.style.display='none'">
        <div class="brand-text">PUP e-DocuServe <span>Biñan Campus — Online Document Request</span></div>
    </a>
    <div class="dropdown">
        <button class="dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle" style="font-size:18px;"></i>
            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
            <i class="fas fa-caret-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="font-size:13px;">
            <li><a class="dropdown-item" href="index.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
            <li><a class="dropdown-item" href="account_settings.php"><i class="fas fa-cog me-2"></i>Account Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </div>
</nav>

<div class="tabs-bar">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-user"></i> Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="new_request.php"><i class="fas fa-plus-circle"></i> New Request</a></li>
        <li class="nav-item"><a class="nav-link" href="requests.php"><i class="fas fa-list"></i> Requests</a></li>
        <li class="nav-item"><a class="nav-link active" href="feedback.php"><i class="fas fa-star"></i> Feedback</a></li>
        <li class="nav-item"><a class="nav-link" href="account_settings.php"><i class="fas fa-cog"></i> Account Settings</a></li>
    </ul>
</div>

<div class="content-wrapper">

<?php if ($success): ?>
    <div class="success-box">
        <i class="fas fa-check-circle"></i>
        <h5>Thank you for your feedback!</h5>
        <p>Your response has been recorded. We appreciate you taking the time to help us improve PUP e-DocuServe.</p>
        <a href="requests.php" class="btn-go"><i class="fas fa-list me-2"></i>Back to My Requests</a>
    </div>

<?php elseif ($already_submitted): ?>
    <div class="already-box">
        <i class="fas fa-star"></i>
        <h5>You've already submitted your feedback!</h5>
        <p>Thank you for your response. You can only submit feedback once per account.</p>
        <a href="requests.php" class="btn-go"><i class="fas fa-list me-2"></i>Back to My Requests</a>
    </div>

<?php else: ?>
    <div class="feedback-header">
        <h4><i class="fas fa-star me-2"></i>System Feedback</h4>
        <p>Please rate your experience with PUP e-DocuServe. Your feedback helps us improve the system.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">
            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="feedbackForm">
        <?= csrf_field() ?>
        <div class="section-card">
            <div class="section-header"><i class="fas fa-clipboard-list me-2"></i>Rate Your Experience</div>
            <div class="section-body">
                <div class="rating-legend">
                    <span>1 = Poor</span>
                    <span>2 = Fair</span>
                    <span>3 = Good</span>
                    <span>4 = Very Good</span>
                    <span>5 = Excellent</span>
                </div>
                <?php foreach ($questions as $name => $text):
                    $num = substr($name, 1); ?>
                <div class="question-row">
                    <div class="question-text">
                        <span class="question-num"><?= $num ?>.</span><?= htmlspecialchars($text) ?>
                    </div>
                    <div class="rating-group" id="group_<?= $name ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" name="<?= $name ?>" id="<?= $name ?>_<?= $i ?>" value="<?= $i ?>">
                            <label for="<?= $name ?>_<?= $i ?>"><?= $i ?></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header"><i class="fas fa-comment-alt me-2"></i>Suggestions & Comments</div>
            <div class="section-body">
                <label class="form-label">Do you have any suggestions or comments about the system? <span style="font-weight:400; color:#aaa;">(Optional)</span></label>
                <textarea name="suggestions" class="form-control" rows="5"
                    placeholder="Share your thoughts — what needs improvement, what you liked, or anything else you'd like us to know..."></textarea>
            </div>
        </div>

        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane me-2"></i>Submit Feedback</button>
        <!-- <a href="requests.php" class="btn-skip">Skip for now</a> -->
    </form>
<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.rating-group input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const name = this.name;
        const val  = parseInt(this.value);
        document.querySelectorAll(`.rating-group input[name="${name}"] + label`).forEach((label, i) => {
            if (i < val) {
                label.style.background  = 'var(--pup-red)';
                label.style.borderColor = 'var(--pup-red)';
                label.style.color       = 'white';
            } else {
                label.style.background  = '';
                label.style.borderColor = '#ddd';
                label.style.color       = '#888';
            }
        });
    });
});

document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    const names = ['q1','q2','q3','q4','q5','q6','q7','q8','q9','q10'];
    for (const name of names) {
        if (!document.querySelector(`input[name="${name}"]:checked`)) {
            e.preventDefault();
            alert('Please answer all 10 questions before submitting.');
            document.querySelector(`#group_${name}`).scrollIntoView({ behavior: 'smooth', block: 'center' });
            document.querySelectorAll(`#group_${name} label`).forEach(l => l.style.borderColor = '#e74c3c');
            return;
        }
    }
});
</script>
</body>
</html>