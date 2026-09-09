<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select('SHOW TABLES');
foreach ($tables as $t) {
    $arr = (array)$t;
    $tbl = reset($arr);
    if (str_contains($tbl, 'user') || str_contains($tbl, 'emplead') || str_contains($tbl, 'puest') || str_contains($tbl, 'sucurs')) {
        echo $tbl . PHP_EOL;
    }
}
