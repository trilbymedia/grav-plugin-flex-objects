<?php

declare(strict_types=1);

/**
 * Test bootstrap for the flex-objects plugin.
 *
 * The media endpoints live in classes/Api/FlexApiController.php, which extends
 * the API plugin's AbstractApiController and uses its HandlesMediaUploads trait.
 * So three autoloaders have to be wired up:
 *
 *   1. This plugin's own autoloader  → Grav\Plugin\FlexObjects\*
 *   2. Grav core's autoloader        → Grav\Common\* / Grav\Framework\* (+ PHPUnit)
 *   3. The API plugin's autoloader    → Grav\Plugin\Api\*
 *
 * In a real install all three are siblings under user/plugins; for a symlinked
 * development clone we also fall back to the workspace layout. Set GRAV_ROOT to
 * override discovery, e.g. `GRAV_ROOT=/path/to/grav composer test`.
 */

// 1. This plugin's classes.
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Locate the hosting Grav root (holds Grav core + its vendor, incl. PHPUnit).
$findGravRoot = static function (): ?string {
    $env = getenv('GRAV_ROOT');
    if ($env && is_file(rtrim($env, '/') . '/system/defines.php')) {
        return rtrim($env, '/');
    }

    // Walk up from the symlink-preserving shell CWD and the resolved paths.
    $starts = array_filter([
        getenv('PWD') ?: null,
        getcwd() ?: null,
        __DIR__ . '/../../../..', // user/plugins/flex-objects/tests → grav root
    ]);
    foreach ($starts as $dir) {
        $dir = rtrim((string) $dir, '/');
        while ($dir !== '' && $dir !== '/' && $dir !== '.') {
            if (is_file($dir . '/vendor/autoload.php') && is_file($dir . '/system/defines.php')) {
                return $dir;
            }
            $parent = \dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
    }

    return null;
};

$gravRoot = $findGravRoot();
if ($gravRoot === null) {
    fwrite(STDERR, "Could not locate the hosting Grav root. Set GRAV_ROOT to run these tests.\n");
    exit(1);
}

require_once $gravRoot . '/vendor/autoload.php';

if (!defined('GRAV_ROOT')) {
    define('GRAV_ROOT', $gravRoot);
}

// 3. The API plugin's autoloader (FlexApiController's parent + trait live there).
$apiAutoloadCandidates = array_filter([
    $gravRoot . '/user/plugins/api/vendor/autoload.php',
    \dirname(__DIR__, 2) . '/grav-plugin-api/vendor/autoload.php', // workspace sibling
]);
foreach ($apiAutoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        require_once $candidate;
        break;
    }
}

if (!class_exists(\Grav\Plugin\Api\Controllers\AbstractApiController::class)) {
    fwrite(STDERR, "Could not load the API plugin autoloader. Is user/plugins/api present?\n");
    exit(1);
}

date_default_timezone_set('UTC');
