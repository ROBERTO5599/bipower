<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$sessionFiles = glob(__DIR__ . '/../storage/framework/sessions/*');
foreach ($sessionFiles as $file) {
    if (basename($file) === '.gitignore') continue;
    $content = file_get_contents($file);
    echo "=== FILE: " . basename($file) . " ===\n";
    $data = @unserialize($content);
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            if (is_scalar($v)) {
                echo "  $k => $v\n";
            } elseif (is_array($v)) {
                echo "  $k => array(" . count($v) . ")\n";
            }
        }
    } else {
        echo "  Raw: " . substr($content, 0, 200) . "\n";
    }
}
