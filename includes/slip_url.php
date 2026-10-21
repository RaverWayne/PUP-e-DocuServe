<?php
/**
 * slip_url.php — Helper to generate the correct URL for a bank slip.
 *
 * - New uploads stored on Cloudinary have a public_id starting with 'edocuserve/slips/'
 * - Walk-in requests store the string 'walkin' — no URL needed
 */

require_once __DIR__ . '/cloudinary.php';

/**
 * Returns a viewable URL for the given bank_slip_path value.
 * Returns empty string if no slip (caller skips the link).
 */
function getSlipUrl(?string $path, string $base = '../assets/uploads/'): string {
    if (empty($path) || $path === 'walkin') {
        return '';
    }

    // Cloudinary-stored slip
    if (str_starts_with($path, 'edocuserve/slips/')) {
        $cloudinary = getCloudinary();
        if ($cloudinary === null) {
            return '';
        }
        return (string) $cloudinary->image($path)->toUrl();
    }

    // Legacy local file
    return $base . basename($path);
}
