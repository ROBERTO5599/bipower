<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Sucursal;

class ResumenEjecutivoTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/resumen-ejecutivo');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_executive_summary_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/resumen-ejecutivo');

        $response->assertStatus(200);
    }

    public function test_executive_summary_data_json_structure(): void
    {
        $user = User::factory()->create();

        // Seed a dummy sucursal
        Sucursal::create([
            'nombre' => 'Test Sucursal',
            'id_valora_mas' => '2',
            'cod_empresa' => 1
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/resumen-ejecutivo/data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalIngresos',
            'totalEgresos',
            'carteraVigente',
            'carteraVencida',
            'carteraTotal',
            'tasaMora',
            'branchKPIs',
            'chartCartera'
        ]);
    }
}
