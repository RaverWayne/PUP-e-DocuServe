<?php
/**
 * slip_url.php — Helper to generate the correct URL for a bank slip.
 *
 * - New uploads stored on Cloudinary have a public_id starting with 'edocuserve/slips/'
 * - Old local uploads are just filenames (e.g. 'slip_42_1234567890.jpg')
 * - Walk-in requests store the string 'walkin' — no URL needed
 *
 * Usage:
 *   require_once '../includes/slip_url.php';
 *   $url = getSlipUrl($row['bank_slip_path']);
 */

require_once __DIR__ . '/cloudinary.php';

use Cloudinary\Transformation\Delivery;
use Cloudinary\Transformation\Format;

/**
 * Returns a viewable URL for the given bank_slip_path value.
 *
 * @param  string|null $path  Value from requests.bank_slip_path
 * @return string             URL string, or empty string if no slip
 */
function getSlipUrl(?string $path): string {
    $base = '../assets/uploads/';
    if (empty($path) || $path === 'walkin') {
        return '';
    }

    // Cloudinary-stored slip — public_id starts with our folder prefix
    if (str_starts_with($path, 'edocuserve/slips/')) {
        $cloudinary = getCloudinary();
        if ($cloudinary === null) {
            // Cloudinary not configured — can't generate signed URL
            return '';
        }
        // Generate a signed, authenticated URL (expires in 1 hour)
        return (string) $cloudinary->image($path)
            ->delivery(Delivery::format(Format::jpg()))
            ->toUrl(['type' => 'authenticated', 'sign_url' => true, 'expires_at' => time() + 3600]);
    }

    // Legacy local file — return the assets/uploads/ path
    return $base . basename($path);
}
