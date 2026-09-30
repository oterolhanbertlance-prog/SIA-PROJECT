<?php
/**
 * Return the web base URL of this POS installation, regardless of the folder
 * name used inside htdocs (e.g. /milk-tea-pos/, /pos/, /boba-house/).
 */
function appBaseUrl(): string {
    static $base = null;
    if ($base !== null) return $base;

    $projectRoot = realpath(__DIR__ . '/..');
    $scriptFile  = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    $scriptName  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    if ($projectRoot && $scriptFile) {
        $rootNorm   = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $scriptNorm = str_replace('\\', '/', $scriptFile);
        if (strpos(strtolower($scriptNorm), strtolower($rootNorm . '/')) === 0 || strcasecmp($scriptNorm, $rootNorm) === 0) {
            $relative = ltrim(substr($scriptNorm, strlen($rootNorm)), '/');
            if ($relative !== '' && strlen($scriptName) >= strlen($relative) &&
                strcasecmp(substr($scriptName, -strlen($relative)), $relative) === 0) {
                $base = substr($scriptName, 0, -strlen($relative));
            }
        }
    }

    if ($base === null || $base === '') {
        // Fallback: current script directory. Move one level up for known app subfolders.
        $dir = str_replace('\\', '/', dirname($scriptName));
        if (preg_match('#/(admin|auth|customer|driver|integration|profile|staff)$#i', $dir)) {
            $dir = str_replace('\\', '/', dirname($dir));
        }
        $base = rtrim($dir, '/') . '/';
    }

    if ($base === '' || $base[0] !== '/') $base = '/' . ltrim($base, '/');
    return rtrim($base, '/') . '/';
}

function appUrl(string $path = ''): string {
    return appBaseUrl() . ltrim($path, '/');
}
