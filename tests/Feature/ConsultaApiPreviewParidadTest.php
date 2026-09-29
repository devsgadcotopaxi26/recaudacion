<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * /admin/consulta-api debe mostrar EXACTAMENTE la misma forma de JSON que
 * recibiría un banco real en POST /api/v1/consulta-deuda-rodaje-bancos
 * (para el mismo vehículo) — es la pantalla de soporte para reproducir
 * reclamos. Ver auditoría: el backend ya construye ambas respuestas con
 * DeudaVehicularService::formatearRespuestaPublica() (mismo método), pero
 * un bug de frontend (roles.some((r) => r.name === 'admin') contra un
 * array plano de strings) ocultaba la única sección que la mostraba
 * correctamente para TODOS los usuarios, siempre.
 */
class ConsultaApiPreviewParidadTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSri(string $placa, int $codigoVehiculo): void
    {
        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => $codigoVehiculo,
                'numeroPlaca' => $placa,
                'descripcionMarca' => 'TEST',
                'descripcionModelo' => 'MODELO TEST',
                'anioAuto' => 2020,
                'nombreClase' => 'AUTOMOVIL',
                'cilindraje' => 1600,
                'ultimoAnioPagado' => 0,
            ], 200),
            '*ConsultaRubros/obtenerPorCodigoVehiculo*' => Http::response([
                [
                    'codigoTipoDeuda' => 'MATRICULA',
                    'valorRubro' => 100,
                    'anioHastaPago' => (int) date('Y'),
                    'anioDesdePago' => (int) date('Y'),
                    'nombreCortoBeneficiario' => 'GAD',
                    'descripcionRubro' => 'MATRICULA',
                    'codigoRubro' => null,
                ],
            ], 200),
        ]);
    }

    private function crearAdmin(): User
    {
        Role::create(['name' => 'admin']);

        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-consulta-api@recaudacion.gob.ec',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_respuesta_api_banco_del_panel_coincide_en_forma_con_la_respuesta_bancaria_real(): void
    {
        $placa = 'PRV1234';
        $this->fakeSri($placa, 999555);

        // 1) Lo que recibe un banco real.
        $token = ApiToken::create([
            'entidad_nombre' => 'Banco de Prueba',
            'usuario' => 'banco_paridad_test',
            'token' => 'token_paridad_test',
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);

        $respuestaBanco = $this->withHeaders(['Authorization' => 'Bearer token_paridad_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => $placa]);

        $respuestaBanco->assertOk();
        $dataBanco = $respuestaBanco->json('data');

        // 2) Lo que ve el panel admin/soporte para la MISMA placa (el SRI
        // fake sigue activo, la caché de conciliarPagosLocales() no
        // interfiere porque es la misma placa/estado).
        $admin = $this->crearAdmin();

        $respuestaPanel = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->actingAs($admin)
            ->from('/admin/consulta-api')
            ->post('/admin/consulta-api', ['placa' => $placa]);

        $respuestaPanel->assertSessionHas('resultado');
        $resultado = $respuestaPanel->getSession()->get('resultado');
        $this->assertTrue($resultado['success']);
        // Round-trip por JSON (igual que hace Vue con JSON.stringify en
        // jsonFormateado) — evita falsos negativos de tipo PHP puro
        // (float 100.0 vs int 100) que no existen una vez serializado,
        // que es como de verdad se compara "lo mismo que ve el banco".
        $dataPanel = json_decode(json_encode($resultado['respuesta_api_banco']), true);

        // Mismas claves de nivel superior, exactamente — ni de más ni de
        // menos (el objetivo del fix: cero campos añadidos/reinterpretados).
        $this->assertEqualsCanonicalizing(array_keys($dataBanco), array_keys($dataPanel));

        // Mismo contenido, campo por campo, EXCEPTO codigo_consulta: el
        // banco genera y persiste una ConsultaBancaria real; la
        // previsualización del panel no (por diseño, ver commit 835e33d).
        foreach ($dataBanco as $campo => $valor) {
            if ($campo === 'codigo_consulta') {
                continue;
            }
            $this->assertSame(
                $valor,
                $dataPanel[$campo],
                "El campo '{$campo}' difiere entre la respuesta bancaria real y la previsualización del panel."
            );
        }

        $this->assertNotNull($dataBanco['codigo_consulta']);
        $this->assertNull($dataPanel['codigo_consulta']);

        // Específicamente los campos que el reporte del usuario decía que
        // faltaban/divergían.
        $this->assertArrayHasKey('nota', $dataPanel);
        $this->assertArrayHasKey('totales', $dataPanel);
        $this->assertArrayNotHasKey('totales_brutos', $dataPanel);
        $this->assertArrayNotHasKey('totales_pendientes', $dataPanel);
    }
}
