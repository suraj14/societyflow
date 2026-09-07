<?php
// Upload this file to /home/burgersoftwares/public_html/societyflow-app/public/
// Then visit: https://societyflow.burgersoftwares.com/DIAGNOSTIC_CHECK.php

echo "<h2>SocietyFlow Diagnostic Check</h2>";

// Check if storage directory exists
$storagePath = __DIR__ . '/../storage/app/public';
echo "<p><strong>Storage Path:</strong> " . $storagePath . "</p>";
echo "<p><strong>Storage Exists:</strong> " . (is_dir($storagePath) ? "✓ YES" : "✗ NO") . "</p>";

// Check if public/storage exists
$publicStorage = __DIR__ . '/storage';
echo "<p><strong>Public Storage Path:</strong> " . $publicStorage . "</p>";
echo "<p><strong>Public Storage Exists:</strong> " . (is_dir($publicStorage) ? "✓ YES" : "✗ NO") . "</p>";

// Check if it's a symlink
if (is_link($publicStorage)) {
    echo "<p><strong>Is Symlink:</strong> ✓ YES</p>";
    echo "<p><strong>Points To:</strong> " . readlink($publicStorage) . "</p>";
} else {
    echo "<p><strong>Is Symlink:</strong> ✗ NO</p>";
}

// Check write permissions
$testDir = __DIR__ . '/storage/test';
if (!is_dir(__DIR__ . '/storage')) {
    @mkdir(__DIR__ . '/storage', 0777, true);
}
$canWrite = @mkdir($testDir, 0777, true);
echo "<p><strong>Can Write to public/storage:</strong> " . ($canWrite ? "✓ YES" : "✗ NO") . "</p>";
if ($canWrite) {
    rmdir($testDir);
}

// Check PHP version
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// Check if fileinfo is loaded
echo "<p><strong>Fileinfo Extension:</strong> " . (extension_loaded('fileinfo') ? "✓ YES" : "✗ NO") . "</p>";

// List all loaded extensions
echo "<p><strong>Loaded Extensions:</strong></p>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";

// Check Laravel files
$noticeController = __DIR__ . '/../app/Http/Controllers/NoticeController.php';
echo "<p><strong>NoticeController Exists:</strong> " . (file_exists($noticeController) ? "✓ YES" : "✗ NO") . "</p>";

$handlesFormSubmissions = __DIR__ . '/../app/Traits/HandlesFormSubmissions.php';
echo "<p><strong>HandlesFormSubmissions Exists:</strong> " . (file_exists($handlesFormSubmissions) ? "✓ YES" : "✗ NO") . "</p>";

echo "<hr>";
echo "<p>If 'Can Write to public/storage' is NO, you need to fix permissions in cPanel Terminal.</p>";
echo "<p>If 'Public Storage Exists' is NO, you need to create the directory.</p>";
?>
