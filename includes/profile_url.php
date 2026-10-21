<?php
/**
 * profile_url.php — Helper to generate the correct URL for a profile photo.
 *
 * - New uploads stored on Cloudinary have a public_id starting with 'edocuserve/profiles/'
 * - Old local uploads are just filenames (e.g. 'user_42_1234567890.jpg')
 * - Empty/null = no photo set (caller should show a placeholder icon)
 *
 * Usage:
 *   require_once '../includes/profile_url.php';
 *   $url = getProfileUrl($user['profile_photo'] ?? '');
 */

require_once __DIR__ . '/cloudinary.php';

/**
 * Returns a viewable URL for the given profile_photo value.
 * Returns empty string if no photo (caller shows placeholder icon).
 *
 * @param  string|null $path  Value from users.profile_photo or admins.profile_photo
 */
function getProfileUrl(?string $path): string {
    $base = '../assets/uploads/';
    if (empty($path)) {
        return '';
    }

    // Cloudinary-stored profile — public_id starts with our folder prefix
    if (str_starts_with($path, 'edocuserve/profiles/')) {
        $cloudinary = getCloudinary();
        if ($cloudinary === null) {
            return '';
        }
        return (string) $cloudinary->image($path)
            ->toUrl(['type' => 'authenticated', 'sign_url' => true, 'expires_at' => time() + 3600]);
    }

    // Legacy local file — return the assets/uploads/ path
    return $base . basename($path);
}
