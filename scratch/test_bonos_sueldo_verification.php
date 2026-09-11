<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = new \Illuminate\Http\Request([
    'fecha_inicio' => '2026-09-01',
    'fecha_fin' => '2026-09-10'
]);

$controller = new \App\Http\Controllers\BonosComisionesController();
$res = $controller->data($req);
$data = $res->getData();

echo "=== MUESTRA DE EMPLEADOS CON NOMINA BASE DESDE USERS ===\n";
foreach (array_slice($data->desglose_empleados, 0, 8) as $emp) {
    echo "Empleado: {$emp->empleado} | Puesto: {$emp->perfil_base} | Nomina Base: \${$emp->nomina_base} | Comp. Total: \${$emp->compensacion_total}\n";
}
