<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$columns = Schema::getColumnListing('users');
echo "Columns in users table: " . implode(', ', $columns) . PHP_EOL;

$totalUsers = User::count();
echo "Total users in intranet: $totalUsers" . PHP_EOL;

// Check if there is an 'activo' column or status column
if (in_array('activo', $columns)) {
    echo "Active users (activo=1): " . User::where('activo', 1)->count() . PHP_EOL;
}
if (in_array('status', $columns)) {
    echo "Users with status: " . User::where('status', 1)->count() . PHP_EOL;
}

$sample = User::limit(5)->get();
foreach ($sample as $u) {
    echo "ID: {$u->id} | Name: {$u->nombre} {$u->primer_apellido} | sucursal_id: {$u->sucursal_id} | " . json_encode($u->toArray()) . PHP_EOL;
}
