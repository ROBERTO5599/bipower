<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $columns = Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM users");
    echo "=== COLUMNAS DE TABLA USERS ===\n";
    foreach ($columns as $c) {
        echo "- {$c->Field} ({$c->Type})\n";
    }

    $sample = Illuminate\Support\Facades\DB::table('users')->limit(5)->get();
    echo "\n=== EJEMPLO DE USUARIOS ===\n";
    print_r($sample);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
