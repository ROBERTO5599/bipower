<?php
$tables = DB::select("SHOW TABLES FROM sistema_prendario_1 LIKE '%mov%'");
print_r($tables);
$cats = DB::select("SHOW TABLES FROM sistema_prendario_1");
foreach ($cats as $cat) {
    $val = array_values((array)$cat)[0];
    if (strpos($val, 'cat') !== false || strpos($val, 'tipo') !== false) {
        echo $val . "\n";
    }
}
