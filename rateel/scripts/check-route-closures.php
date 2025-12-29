<?php
/**
 * CI/Pre-Deploy Safety Check: Route Closure Detection
 * 
 * This script scans all route files for closure definitions that would
 * break Laravel's route caching. Run this in CI pipelines before deployment.
 * 
 * Usage:
 *   php scripts/check-route-closures.php
 * 
 * Exit codes:
 *   0 = All routes are cache-safe
 *   1 = Closure routes detected (will break route:cache)
 * 
 * @package SmartLine
 */

echo "============================================\n";
echo "🔍 Route Closure Safety Check\n";
echo "============================================\n\n";

$projectRoot = dirname(__DIR__);

// Directories to scan for route files
$routeDirectories = [
    $projectRoot . '/routes',
];

// Find all module route directories
$modulesPath = $projectRoot . '/Modules';
if (is_dir($modulesPath)) {
    $moduleRoutes = glob($modulesPath . '/*/Routes', GLOB_ONLYDIR);
    $routeDirectories = array_merge($routeDirectories, $moduleRoutes);
}

// Pattern to detect closure routes
// Matches: Route::get('...', function (...) { ... })
$closurePattern = '/Route::(get|post|put|patch|delete|any|match)\s*\([^,]+,\s*function\s*\(/';

$foundClosures = [];
$totalFilesScanned = 0;

foreach ($routeDirectories as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    
    $files = glob($dir . '/*.php');
    
    foreach ($files as $file) {
        $totalFilesScanned++;
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNum => $line) {
            if (preg_match($closurePattern, $line)) {
                $foundClosures[] = [
                    'file' => str_replace($projectRoot . '/', '', $file),
                    'line' => $lineNum + 1,
                    'content' => trim($line),
                ];
            }
        }
    }
}

echo "📁 Scanned directories:\n";
foreach ($routeDirectories as $dir) {
    $relativePath = str_replace($projectRoot . '/', '', $dir);
    echo "   - {$relativePath}\n";
}
echo "\n";

echo "📄 Total files scanned: {$totalFilesScanned}\n\n";

if (empty($foundClosures)) {
    echo "✅ SUCCESS: No closure routes detected!\n";
    echo "\n";
    echo "All routes are safe for caching with:\n";
    echo "  php artisan route:cache\n";
    echo "\n";
    exit(0);
} else {
    echo "❌ FAILED: Found " . count($foundClosures) . " closure route(s)!\n\n";
    
    echo "Closure routes will break route caching.\n";
    echo "Convert these to controller methods:\n\n";
    
    foreach ($foundClosures as $closure) {
        echo "  📍 {$closure['file']}:{$closure['line']}\n";
        echo "     {$closure['content']}\n\n";
    }
    
    echo "============================================\n";
    echo "How to fix:\n";
    echo "============================================\n";
    echo "1. Create a controller method for the closure logic\n";
    echo "2. Replace the closure with controller reference:\n";
    echo "   Route::get('/path', [Controller::class, 'method'])\n";
    echo "3. Re-run this check\n";
    echo "\n";
    
    exit(1);
}
