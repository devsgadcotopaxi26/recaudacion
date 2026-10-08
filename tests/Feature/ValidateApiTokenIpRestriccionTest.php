<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unico 403 de toda la API bancaria (ValidateApiToken.php) -- se dispara
 * cuando el token tiene ip_permitida configurada y la IP real del
 * request no coincide. Causa unica e inequivoca, por eso error_code
 * 'IP_NO_AUTORIZADA' (no uno generico tipo ACCESO_NO_AUTORIZADO).
 */
class ValidateApiTokenIpRestriccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_403_por_ip_no_autorizada_incluye_error_code(): void
    {
        ApiToken::create([
            'entidad_nombre' => 'Banco IP Restringida',
            'usuario' => 'banco_ip_test',
            'token' => 'token_ip_test',
            'activo' => true,
            'requests_permitidos' => 1000,
            'ip_permitida' => '203.0.113.99', // IP distinta a la del test
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_ip_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ABC1234']);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'IP no autorizada para este token',
            'error_code' => 'IP_NO_AUTORIZADA',
        ]);
    }
}
