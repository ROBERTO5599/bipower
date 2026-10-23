<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Services\PowerBIPermissionService;

$sessionFiles = glob(__DIR__ . '/../storage/framework/sessions/*');
foreach ($sessionFiles as $file) {
    if (basename($file) === '.gitignore') continue;
    $content = file_get_contents($file);
    echo "File: " . basename($file) . " (Modified: " . date('Y-m-d H:i:s', filemtime($file)) . ")\n";
    $data = @unserialize($content);
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            if (strpos($k, 'login_web') !== false) {
                echo "  Logged in user identifier: " . print_r($v, true) . "\n";
                // Let's find this user in DB
                $user = User::where('nick_name', $v)->orWhere('id', $v)->first();
                if ($user) {
                    echo "  User found: ID {$user->id}, Name: {$user->name}, Nick: {$user->nick_name}, Email: {$user->email}\n";
                    echo "  User PBI Role: " . var_export($user->getPbiRoleType(), true) . "\n";
                    echo "  User Roles in DB: " . json_encode($user->roles->pluck('nombre')->toArray()) . "\n";
                    echo "  Permitted Sucursal IDs: " . json_encode($user->getPbiPermittedSucursalIds()) . "\n";
                    echo "  Permitted Sucursales Count: " . $user->getPbiPermittedSucursales()->count() . "\n";
                } else {
                    echo "  User NOT found for $v\n";
                }
            }
        }
    }
}
