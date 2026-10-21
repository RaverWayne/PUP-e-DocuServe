<?php
/**
 * profile_url.php — Helper to generate the correct URL for a profile photo.
 *
 * - New uploads stored on Cloudinary have a public_id starting with 'edocuserve/profiles/'
 * - Empty/null = no photo set (caller shows placeholder icon)
 */

require_once __DIR__ . '/cloudinary.php';

/**
 * Returns a viewable URL for the given profile_photo value.
 * Returns empty string if no photo (caller shows placeholder icon).
 */
function getProfileUrl(?string $path, string $base = '../assets/uploads/'): string {
    if (empty($path)) {
        return '';
    }

    // Cloudinary-stored profile photo
    if (str_starts_with($path, 'edocuserve/profiles/')) {
        $cloudinary = getCloudinary();
        if ($cloudinary === null) {
            return '';
        }
        return (string) $cloudinary->image($path)->toUrl();
    }

    // Legacy local file
    return $base . basename($path);
}
