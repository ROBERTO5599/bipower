<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$users = User::where('status', 0)->get();
echo "Active users: " . $users->count() . "\n";

// Test matching logic
$testNames = [
    'ALBERTO FLORES SUAREZ',
    'CRISTINA ELIZABETH GUTIERREZ IRIGOYEN',
    'DULCE IXCHEL CRUZ BECERRIL',
    'KAROL JOCELYN CASTRO LORENZO',
    'GAEL HERNANDEZ GARDUÑO'
];

foreach ($testNames as $name) {
    $normName = preg_replace('/\s+/', ' ', trim($name));
    $found = $users->first(function($u) use ($normName) {
        $fullName = preg_replace('/\s+/', ' ', trim("{$u->nombre} {$u->primer_apellido} {$u->segundo_apellido}"));
        return strcasecmp($fullName, $normName) === 0 
            || str_contains($fullName, $normName) 
            || str_contains($normName, trim("{$u->nombre} {$u->primer_apellido}"));
    });
    if ($found) {
        echo "MATCH: '{$name}' => User #{$found->id} {$found->nombre_completo} (Sueldo: \${$found->sueldo})\n";
    } else {
        echo "NO MATCH: '{$name}'\n";
    }
}
