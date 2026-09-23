<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\TransaccionPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReporteConciliacionInternoTest extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(): User
    {
        Role::create(['name' => 'admin']);

        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-test@recaudacion.gob.ec',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * La separación de formatearDetalle() en formatearDetalleBancario()/
     * formatearDetalleInterno() no debe cambiar nada para el panel admin
     * interno (ReporteConciliacion.vue) — sigue viendo el id real y
     * comprobante, y sigue viendo TODAS las entidades (sin scope por
     * api_token_id, a diferencia del endpoint bancario).
     */
    public function test_panel_interno_sigue_mostrando_id_real_y_comprobante_de_todas_las_entidades(): void
    {
        $admin = $this->crearAdmin();

        $bancoA = ApiToken::create([
            'entidad_nombre' => 'Banco Interno A',
            'usuario' => 'banco_interno_a',
            'token' => 'token_interno_a',
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);
        $bancoB = ApiToken::create([
            'entidad_nombre' => 'Banco Interno B',
            'usuario' => 'banco_interno_b',
            'token' => 'token_interno_b',
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);

        $transaccionA = TransaccionPago::create([
            'placa' => 'INT1111',
            'canal' => 'banco',
            'api_token_id' => $bancoA->id,
            'referencia_externa' => 'TXN-INTERNO-A-001',
            'monto_total' => 10.00,
            'estado' => 'pagado',
            'fecha_pago' => now(),
        ]);
        $transaccionA->detalles()->create([
            'placa' => 'INT1111', 'estado' => 'pagado', 'anio_fiscal' => 2026,
            'monto_impuesto' => 10.00, 'monto_total' => 10.00,
        ]);

        $transaccionB = TransaccionPago::create([
            'placa' => 'INT2222',
            'canal' => 'banco',
            'api_token_id' => $bancoB->id,
            'referencia_externa' => 'TXN-INTERNO-B-001',
            'monto_total' => 20.00,
            'estado' => 'pagado',
            'fecha_pago' => now(),
        ]);
        $transaccionB->detalles()->create([
            'placa' => 'INT2222', 'estado' => 'pagado', 'anio_fiscal' => 2026,
            'monto_impuesto' => 20.00, 'monto_total' => 20.00,
        ]);

        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->actingAs($admin)
            ->from('/admin/reporte-conciliacion')
            ->post('/admin/reporte-conciliacion', [
                'fecha_desde' => now()->subDay()->format('Y-m-d'),
                'fecha_hasta' => now()->addDay()->format('Y-m-d'),
            ]);

        $response->assertSessionHas('resultado');
        $resultado = $response->getSession()->get('resultado');

        $this->assertTrue($resultado['success']);
        $filas = $resultado['detalle_pagos']['data'];

        // Sin scope: el panel interno ve AMBAS entidades, a diferencia del
        // endpoint bancario (ver BancaAdminReporteConciliacionScopeTest).
        $this->assertCount(2, $filas);

        foreach ($filas as $fila) {
            $this->assertArrayHasKey('comprobante', $fila);
            $this->assertMatchesRegularExpression('/^PAG-\d{6}$/', $fila['comprobante']);
            $this->assertIsInt($fila['pago_id']);
        }

        $idsReales = collect($filas)->pluck('pago_id')->all();
        $this->assertContains($transaccionA->id, $idsReales);
        $this->assertContains($transaccionB->id, $idsReales);
    }
}
