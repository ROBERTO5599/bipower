<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportePagosTarjetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/reporte-pagos-tarjeta');
        $response->assertRedirect('/login');

        $responseJson = $this->getJson('/reporte-pagos-tarjeta/data');
        $responseJson->assertStatus(401);
    }

    public function test_authenticated_user_can_view_report_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/reporte-pagos-tarjeta');

        $response->assertOk();
        $response->assertViewIs('reporte-pagos-tarjeta.index');
        $response->assertViewHas('sucursales');
    }

    public function test_report_data_json_structure(): void
    {
        $user = User::factory()->create();
        
        // Let's create a test sucursal with id_valora_mas
        Sucursal::create([
            'nombre' => 'Test Sucursal',
            'id_valora_mas' => '999', // dummy ID
            'cod_empresa' => 1
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/reporte-pagos-tarjeta/data');

        // It should respond OK even if connection fails (it catches and logs exceptions per sucursal)
        $response->assertOk();
        
        // Assert base JSON structure exists
        $response->assertJsonStructure([
            'detalleMovimientos',
            'totalMonto',
            'totalComisionMeses',
            'totalComision',
            'totalIva',
            'totalGeneral'
        ]);
    }

    public function test_report_data_can_be_filtered_by_transaccion(): void
    {
        $user = User::factory()->create();

        Sucursal::create([
            'nombre' => 'Test Sucursal',
            'id_valora_mas' => '999',
            'cod_empresa' => 1
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/reporte-pagos-tarjeta/data?transaccion=CLIP');

        $response->assertOk();
    }
}
