<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$cfg = Config::get('database.connections.mysql');
$cfg['database'] = 'sistema_prendario_2';
Config::set('database.connections.sp2', $cfg);

$alh = DB::connection('sp2')->selectOne("SELECT * FROM alhajas LIMIT 1");
echo "--- alhajas columns and sample ---\n";
print_r($alh);

$var = DB::connection('sp2')->selectOne("SELECT * FROM varios LIMIT 1");
echo "--- varios columns and sample ---\n";
print_r($var);
