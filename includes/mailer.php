<?php
/**
 * PUP e-DocuServe — Mailer Helper
 * Sends email through Brevo's transactional HTTPS API (api.brevo.com).
 * Switched away from PHPMailer/SMTP because Railway's Free/Hobby plans
 * block outbound SMTP ports (25/465/587) at the network level — the
 * HTTPS API runs over port 443, which is never blocked.
 * All outgoing email goes through sendMail().
 */

// ─────────────────────────────────────────────────────────────
// Core send function
// ─────────────────────────────────────────────────────────────
function sendMail(PDO $pdo, string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): bool
{
    // Pull Brevo config from DB
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings
                          WHERE setting_key IN ('brevo_api_key','smtp_from_name','smtp_from_email')");
    $cfg  = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cfg[$row['setting_key']] = $row['setting_value'];
    }

    $apiKey    = $cfg['brevo_api_key']   ?? '';
    $fromName  = $cfg['smtp_from_name']  ?? 'PUP e-DocuServe';
    $fromEmail = $cfg['smtp_from_email'] ?? 'noreply@pup.edu.ph';

    // If Brevo isn't configured yet, log and silently skip — never crash the page
    if (empty($apiKey) || empty($fromEmail)) {
        error_log("[mailer] Brevo API not configured. Skipping email to $toEmail — Subject: $subject");
        return false;
    }

    $payload = [
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
        'replyTo'     => ['name' => $fromName, 'email' => $fromEmail],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $plainBody ?: strip_tags($htmlBody),
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Brevo returns 201 Created on success
    if ($httpCode === 201) {
        return true;
    }

    error_log("[mailer] Brevo send failed to $toEmail — HTTP $httpCode — " . ($curlError ?: $response));
    return false;
}

// ─────────────────────────────────────────────────────────────
// Email template wrapper
// ─────────────────────────────────────────────────────────────
function emailWrap(string $title, string $bodyContent): string
{
    return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:Segoe UI,Arial,sans-serif;font-size:14px;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f0;padding:30px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <!-- HEADER -->
        <tr>
          <td style="background:#6D1A1A;padding:24px 32px;text-align:center;">
            <div style="color:#FFD700;font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;margin-bottom:4px;">
              Polytechnic University of the Philippines — Biñan Campus
            </div>
            <div style="color:#ffffff;font-size:20px;font-weight:700;">PUP e-DocuServe</div>
            <div style="color:rgba(255,255,255,0.75);font-size:12px;margin-top:2px;">Online Document Request System</div>
          </td>
        </tr>

        <!-- BODY -->
        <tr>
          <td style="padding:32px;">
            ' . $bodyContent . '
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="background:#f5f5f5;padding:16px 32px;text-align:center;font-size:11px;color:#999;border-top:1px solid #eee;">
            This is an automated message from PUP e-DocuServe. Please do not reply to this email.<br>
            &copy; ' . date('Y') . ' Polytechnic University of the Philippines &ndash; Biñan Campus
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>';
}

// ─────────────────────────────────────────────────────────────
// Template: Account Approved
// ─────────────────────────────────────────────────────────────
function emailAccountApproved(string $firstName): string
{
    $body = '
      <h2 style="color:#6D1A1A;margin:0 0 16px;">Account Approved</h2>
      <p style="color:#333;line-height:1.6;">Hi <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
      <p style="color:#333;line-height:1.6;">
        Great news! Your student account on <strong>PUP e-DocuServe</strong> has been
        <strong style="color:#27ae60;">approved</strong> by the Registrar\'s Office.
        You can now log in and submit document requests.
      </p>
      <div style="text-align:center;margin:28px 0;">
        <a href="' . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/edocuserve/auth/login.php"
           style="background:#6D1A1A;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:5px;font-weight:600;font-size:14px;display:inline-block;">
          Log In Now
        </a>
      </div>
      <p style="color:#888;font-size:12px;line-height:1.6;">
        If you have any questions, please contact the Registrar\'s Office directly.
      </p>';
    return emailWrap('Account Approved — PUP e-DocuServe', $body);
}

// ─────────────────────────────────────────────────────────────
// Template: Account Rejected
// ─────────────────────────────────────────────────────────────
function emailAccountRejected(string $firstName): string
{
    $body = '
      <h2 style="color:#6D1A1A;margin:0 0 16px;">Account Registration Update</h2>
      <p style="color:#333;line-height:1.6;">Hi <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
      <p style="color:#333;line-height:1.6;">
        We regret to inform you that your student account registration on
        <strong>PUP e-DocuServe</strong> has been <strong style="color:#e74c3c;">rejected</strong>
        by the Registrar\'s Office.
      </p>
      <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:14px 18px;margin:20px 0;color:#856404;font-size:13px;">
        <strong>What to do next:</strong> Please visit or contact the Registrar\'s Office to clarify
        your registration details and request a manual review.
      </div>
      <p style="color:#888;font-size:12px;line-height:1.6;">
        If you believe this is an error, please contact the Registrar\'s Office with your student number.
      </p>';
    return emailWrap('Account Registration Update — PUP e-DocuServe', $body);
}

// ─────────────────────────────────────────────────────────────
// Template: Request Status Update
// ─────────────────────────────────────────────────────────────
function emailRequestStatusUpdate(
    string $firstName,
    string $controlNumber,
    string $newStatus,
    string $documents,
    string $adminNotes,
    string $customRequirements,
    ?string $releaseDate
): string {
    $statusColors = [
        'Processing'      => '#3498db',
        'Ready for Pickup'=> '#27ae60',
        'Claimed'         => '#2c3e50',
        'Cancelled'       => '#e74c3c',
        'Pending'         => '#95a5a6',
    ];
    $statusColor = $statusColors[$newStatus] ?? '#6D1A1A';

    $releaseLine = $releaseDate
        ? '<tr><td style="padding:6px 0;color:#555;font-weight:600;width:160px;">Tentative Release:</td>
               <td style="padding:6px 0;color:#333;">' . date('F d, Y', strtotime($releaseDate)) . '</td></tr>'
        : '';

    $notesBlock = !empty($adminNotes)
        ? '<div style="background:#f0f4ff;border-left:4px solid #3498db;padding:12px 16px;margin:20px 0;border-radius:0 6px 6px 0;">
             <strong style="color:#2c3e50;">Note from Registrar:</strong>
             <p style="margin:6px 0 0;color:#555;">' . nl2br(htmlspecialchars($adminNotes)) . '</p>
           </div>'
        : '';

    $reqBlock = ($customRequirements && $customRequirements !== 'No requirements needed')
        ? '<div style="background:#fff8e1;border-left:4px solid #FFD700;padding:12px 16px;margin:20px 0;border-radius:0 6px 6px 0;">
             <strong style="color:#856404;">Requirements from You:</strong>
             <p style="margin:6px 0 0;color:#555;">' . nl2br(htmlspecialchars($customRequirements)) . '</p>
           </div>'
        : '';

    $body = '
      <h2 style="color:#6D1A1A;margin:0 0 16px;">Request Status Update</h2>
      <p style="color:#333;line-height:1.6;">Hi <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
      <p style="color:#333;line-height:1.6;">
        Your document request has been updated. Here are the details:
      </p>

      <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;font-size:13px;">
        <tr>
          <td style="padding:6px 0;color:#555;font-weight:600;width:160px;">Control Number:</td>
          <td style="padding:6px 0;color:#333;font-weight:700;">' . htmlspecialchars($controlNumber) . '</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:#555;font-weight:600;">Documents:</td>
          <td style="padding:6px 0;color:#333;">' . htmlspecialchars($documents) . '</td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:#555;font-weight:600;">New Status:</td>
          <td style="padding:6px 0;">
            <span style="background:' . $statusColor . ';color:#fff;padding:3px 12px;border-radius:4px;font-size:12px;font-weight:600;">
              ' . htmlspecialchars($newStatus) . '
            </span>
          </td>
        </tr>
        ' . $releaseLine . '
      </table>

      ' . $notesBlock . '
      ' . $reqBlock . '

      ' . ($newStatus === 'Ready for Pickup' ? '
      <div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;padding:14px 18px;margin:20px 0;color:#155724;">
        <strong>&#x2705; Your document is ready for pickup!</strong><br>
        Please visit the Registrar\'s Office during office hours to claim your document.
        Bring a valid ID and your control number.
      </div>' : '') . '

      ' . ($newStatus === 'Cancelled' ? '
      <div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;padding:14px 18px;margin:20px 0;color:#721c24;">
        <strong>&#x26A0; Your request has been cancelled.</strong><br>
        Please contact the Registrar\'s Office for more information.
      </div>' : '') . '

      <p style="color:#888;font-size:12px;line-height:1.6;margin-top:24px;">
        You can track your request status by logging in to PUP e-DocuServe.
      </p>';

    return emailWrap('Request Status Update — ' . $controlNumber, $body);
}

// ─────────────────────────────────────────────────────────────
// Template: Password Reset
// ─────────────────────────────────────────────────────────────
function emailPasswordReset(string $firstName, string $resetUrl, int $expiryMinutes = 60): string
{
    $body = '
      <h2 style="color:#6D1A1A;margin:0 0 16px;">Password Reset Request</h2>
      <p style="color:#333;line-height:1.6;">Hi <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
      <p style="color:#333;line-height:1.6;">
        We received a request to reset the password for your PUP e-DocuServe account.
        Click the button below to set a new password:
      </p>
      <div style="text-align:center;margin:28px 0;">
        <a href="' . htmlspecialchars($resetUrl) . '"
           style="background:#6D1A1A;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:5px;font-weight:600;font-size:14px;display:inline-block;">
          Reset My Password
        </a>
      </div>
      <p style="color:#888;font-size:12px;line-height:1.6;">
        This link will expire in <strong>' . $expiryMinutes . ' minutes</strong>.
        If you did not request a password reset, you can safely ignore this email &mdash;
        your password will remain unchanged.
      </p>
      <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px 16px;margin:20px 0;font-size:12px;color:#856404;">
        <strong>Security tip:</strong> Never share this link with anyone. PUP staff will never ask for your password.
      </div>';
    return emailWrap('Password Reset — PUP e-DocuServe', $body);
}