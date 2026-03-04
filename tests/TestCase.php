<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function setUpDatabase()
    {
        // Crear tabla users para pruebas
        DB::statement('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(255),
            primer_apellido VARCHAR(255),
            segundo_apellido VARCHAR(255),
            nick_name VARCHAR(255),
            email VARCHAR(255) NULL,
            email_verified_at DATETIME NULL,
            password VARCHAR(255),
            remember_token VARCHAR(100),
            created_at DATETIME,
            updated_at DATETIME
        )');

        // Crear tabla sucursales para pruebas
        DB::statement('CREATE TABLE IF NOT EXISTS sucursals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(255),
            id_valora_mas INTEGER NULL,
            created_at DATETIME,
            updated_at DATETIME
        )');

        // Crear tabla alhajas en sistema_prendario_X (simulado en sqlite principal por simplicidad de test)
        // Nota: En un entorno real de testing, se mockearían las conexiones o se usarían bases de datos en memoria separadas.
        // Para este fix rápido, asumimos que el test usará la conexión default sqlite.

        // Mockear las conexiones dinámicas en el Controller si fuera un test unitario estricto.
        // Aquí solo aseguramos que la DB base tenga lo mínimo para que no explote el framework.
    }
}
