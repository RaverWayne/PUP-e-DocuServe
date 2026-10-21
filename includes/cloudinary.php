<?php
/**
 * cloudinary.php — Cloudinary SDK initializer.
 *
 * Credentials are loaded from environment variables ONLY — never hardcoded.
 * Set these in Railway: CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET
 *
 * Usage:
 *   require_once '../includes/cloudinary.php';
 *   $cloudinary = getCloudinary();
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

/**
 * Returns a singleton Cloudinary instance configured from env vars.
 * Returns null if credentials are missing (so non-Cloudinary features still work).
 */
function getCloudinary(): ?Cloudinary {
    static $instance = null;
    if ($instance !== null) {
        return $instance;
    }

    $cloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? '');
    $apiKey    = getenv('CLOUDINARY_API_KEY')    ?: ($_ENV['CLOUDINARY_API_KEY']    ?? '');
    $apiSecret = getenv('CLOUDINARY_API_SECRET') ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? '');

    if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
        // Credentials not configured — return null so callers can fall back gracefully
        return null;
    }

    $instance = new Cloudinary(
        Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => ['secure' => true],
        ])
    );

    return $instance;
}
