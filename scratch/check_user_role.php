<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$userRoles = DB::table('user_role')
    ->join('roles', 'roles.id', '=', 'user_role.role_id')
    ->join('users', 'users.id', '=', 'user_role.user_id')
    ->select('users.id as uid', 'users.nombre', 'users.primer_apellido', 'roles.id as rid', 'roles.nombre as rname')
    ->get();

echo "Total users with roles: " . count($userRoles) . "\n";
foreach ($userRoles as $ur) {
    if (in_array($ur->rid, [77, 78, 79])) {
        echo "PBI USER: ID {$ur->uid} - {$ur->nombre} {$ur->primer_apellido} - Role: {$ur->rid} ({$ur->rname})\n";
    }
}
