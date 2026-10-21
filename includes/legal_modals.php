<?php
/**
 * legal_modals.php — Reusable Terms of Use and Privacy Statement modals.
 * Include before </body> on any page that needs the footer links to work.
 * Requires $pdo to already be available (loaded via config/db.php).
 */

$legal_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('terms_of_use', 'privacy_statement')");
$legal = [];
foreach ($legal_stmt->fetchAll() as $row) {
    $legal[$row['setting_key']] = $row['setting_value'];
}
$terms_content   = $legal['terms_of_use']      ?? 'Terms of Use content has not been set yet. Please contact the Registrar\'s Office.';
$privacy_content = $legal['privacy_statement'] ?? 'Privacy Statement content has not been set yet. Please contact the Registrar\'s Office.';
?>

<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: #6D1A1A; color: white;">
                <h6 class="modal-title" id="termsModalLabel">
                    <i class="fas fa-file-contract me-2"></i>Terms of Use
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body" style="font-size: 13px; line-height: 1.8; color: #333; padding: 24px;">
                <?= nl2br(htmlspecialchars($terms_content)) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: #1a237e; color: white;">
                <h6 class="modal-title" id="privacyModalLabel">
                    <i class="fas fa-shield-alt me-2"></i>Privacy Statement
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body" style="font-size: 13px; line-height: 1.8; color: #333; padding: 24px;">
                <?= nl2br(htmlspecialchars($privacy_content)) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
