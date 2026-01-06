<?php
/**
 * Plugin Validation Script
 * Validates PHP syntax and displays plugin information
 */

echo "===========================================\n";
echo "  Delivery Dates Manager - Plugin Validator\n";
echo "===========================================\n\n";

$plugin_dir = __DIR__ . '/delivery-dates-manager';
$errors = [];
$files_checked = 0;

function check_php_syntax($file) {
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
    return [
        'valid' => $return_var === 0,
        'output' => implode("\n", $output)
    ];
}

function scan_directory($dir, &$errors, &$files_checked) {
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            scan_directory($path, $errors, $files_checked);
        } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $files_checked++;
            $result = check_php_syntax($path);
            
            if (!$result['valid']) {
                $errors[] = [
                    'file' => $path,
                    'error' => $result['output']
                ];
            } else {
                echo "  [OK] " . basename($path) . "\n";
            }
        }
    }
}

echo "Checking PHP syntax...\n\n";

if (is_dir($plugin_dir)) {
    scan_directory($plugin_dir, $errors, $files_checked);
} else {
    echo "  [ERROR] Plugin directory not found!\n";
    exit(1);
}

echo "\n-------------------------------------------\n";
echo "Results:\n";
echo "  Files checked: {$files_checked}\n";
echo "  Errors found: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  File: {$error['file']}\n";
        echo "  {$error['error']}\n\n";
    }
    exit(1);
}

echo "\n-------------------------------------------\n";
echo "Plugin Structure:\n\n";

function display_tree($dir, $prefix = '') {
    $files = scandir($dir);
    $files = array_diff($files, ['.', '..']);
    $files = array_values($files);
    $count = count($files);
    
    foreach ($files as $i => $file) {
        $path = $dir . '/' . $file;
        $is_last = ($i === $count - 1);
        $connector = $is_last ? '└── ' : '├── ';
        $extension = $is_last ? '    ' : '│   ';
        
        echo $prefix . $connector . $file . "\n";
        
        if (is_dir($path)) {
            display_tree($path, $prefix . $extension);
        }
    }
}

display_tree($plugin_dir);

echo "\n-------------------------------------------\n";
echo "Plugin Information:\n\n";

$main_file = $plugin_dir . '/delivery-dates-manager.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    
    preg_match('/Plugin Name:\s*(.+)/', $content, $name);
    preg_match('/Version:\s*(.+)/', $content, $version);
    preg_match('/Description:\s*(.+)/', $content, $desc);
    preg_match('/Requires PHP:\s*(.+)/', $content, $php);
    preg_match('/WC requires at least:\s*(.+)/', $content, $wc);
    
    echo "  Name: " . (isset($name[1]) ? trim($name[1]) : 'N/A') . "\n";
    echo "  Version: " . (isset($version[1]) ? trim($version[1]) : 'N/A') . "\n";
    echo "  PHP Required: " . (isset($php[1]) ? trim($php[1]) : 'N/A') . "\n";
    echo "  WooCommerce Required: " . (isset($wc[1]) ? trim($wc[1]) : 'N/A') . "\n";
}

echo "\n===========================================\n";
echo "  All checks passed! Plugin is ready.\n";
echo "===========================================\n";
echo "\nTo install:\n";
echo "1. Zip the 'delivery-dates-manager' folder\n";
echo "2. Upload to WordPress via Plugins > Add New > Upload\n";
echo "3. Activate and configure at WooCommerce > Delivery Dates\n\n";
