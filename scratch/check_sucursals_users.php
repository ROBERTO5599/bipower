<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$sucs = Sucursal::whereNotNull('id_valora_mas')->get();
foreach ($sucs as $s) {
    $countInUsers = User::where('sucursal_id', $s->id)->whereNull('fecha_baja')->count();
    $countInUsersVM = User::where('sucursal_id', $s->id_valora_mas)->whereNull('fecha_baja')->count();
    echo "Sucursal: {$s->nombre} (id: {$s->id}, id_valora_mas: {$s->id_valora_mas}) -> users by s.id: $countInUsers | users by s.id_vm: $countInUsersVM\n";
}
