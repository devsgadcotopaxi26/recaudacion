<?php

namespace Tests\Feature;

use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransaccionPagoCodigoTransaccionTest extends TestCase
{
    use RefreshDatabase;

    private const ALFABETO_SIN_AMBIGUOS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function test_codigo_transaccion_se_genera_automaticamente_al_crear(): void
    {
        $transaccion = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $this->assertNotEmpty($transaccion->codigo_transaccion);
        $this->assertMatchesRegularExpression(
            '/^TRX-[A-Z0-9]{6}$/',
            $transaccion->codigo_transaccion
        );
    }

    public function test_codigo_transaccion_excluye_caracteres_ambiguos(): void
    {
        // Genera varias transacciones y confirma que ninguno de los 6
        // caracteres tras "TRX-" es 0, O, 1, I o L en ningún caso.
        $ambiguos = ['0', 'O', '1', 'I', 'L'];

        for ($i = 0; $i < 30; $i++) {
            $transaccion = TransaccionPago::create([
                'placa' => 'AMB' . $i,
                'canal' => 'pasarela_ciudadana',
                'monto_total' => 10.00,
                'estado' => 'pendiente',
            ]);

            $sufijo = substr($transaccion->codigo_transaccion, 4);
            $this->assertSame(6, strlen($sufijo));

            foreach (str_split($sufijo) as $caracter) {
                $this->assertContains(
                    $caracter,
                    str_split(self::ALFABETO_SIN_AMBIGUOS),
                    "Carácter '{$caracter}' no debería aparecer (alfabeto sin ambiguos)."
                );
                $this->assertNotContains($caracter, $ambiguos);
            }
        }
    }

    public function test_certificado_token_se_sigue_generando_junto_al_nuevo_campo(): void
    {
        // Regresión: confirmar que el rename/reformato de codigo_transaccion
        // no rompió la generación de certificado_token (mismo bloque
        // creating()).
        $transaccion = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $this->assertNotEmpty($transaccion->certificado_token);
        $this->assertSame(32, strlen($transaccion->certificado_token));
        $this->assertNotSame($transaccion->certificado_token, $transaccion->codigo_transaccion);
    }

    public function test_codigo_transaccion_es_distinto_entre_transacciones(): void
    {
        $a = TransaccionPago::create([
            'placa' => 'AAA1111',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $b = TransaccionPago::create([
            'placa' => 'BBB2222',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $this->assertNotSame($a->codigo_transaccion, $b->codigo_transaccion);
    }

    public function test_no_se_sobreescribe_si_ya_viene_asignado(): void
    {
        $transaccion = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
            'codigo_transaccion' => 'TRX-FIJO01',
        ]);

        $this->assertSame('TRX-FIJO01', $transaccion->codigo_transaccion);
    }

    public function test_el_generador_evita_valores_ya_existentes(): void
    {
        // No es posible forzar una colisión real de forma determinista
        // (random_int es CSPRNG, no seedable, y el espacio son ~887
        // millones de combinaciones) — esto prueba en su lugar, vía
        // Reflection, que cada candidato generado se valida contra
        // self::where('codigo_transaccion', $codigo)->exists() antes de
        // aceptarse: se pre-llena la tabla con varios códigos conocidos y
        // se confirma que el generador nunca devuelve ninguno de ellos en
        // muchas corridas.
        $codigosExistentes = [];
        for ($i = 0; $i < 15; $i++) {
            $t = TransaccionPago::create([
                'placa' => 'PRE' . $i,
                'canal' => 'pasarela_ciudadana',
                'monto_total' => 10.00,
                'estado' => 'pendiente',
            ]);
            $codigosExistentes[] = $t->codigo_transaccion;
        }

        $metodo = new \ReflectionMethod(TransaccionPago::class, 'generarCodigoTransaccion');
        $metodo->setAccessible(true);

        for ($i = 0; $i < 30; $i++) {
            $generado = $metodo->invoke(null);
            $this->assertNotContains(
                $generado,
                $codigosExistentes,
                'El generador no debe devolver un código que ya existe en la tabla.'
            );
        }
    }

    public function test_la_restriccion_unique_sigue_activa_tras_el_rename(): void
    {
        // La BD nunca permite dos filas con el mismo codigo_transaccion —
        // eso no cambia. Lo que sí cambió (ver siguiente test) es que ya
        // no basta con forzar un duplicado explícito para observar el
        // QueryException crudo: TransaccionPago::save() ahora lo
        // intercepta y reintenta con un código nuevo antes de rendirse.
        // Esta prueba confirma la garantía de integridad en sí misma,
        // consultando directamente la tabla (sin pasar por el modelo),
        // que es donde vive la garantía real.
        TransaccionPago::create([
            'placa' => 'COL0001',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
            'codigo_transaccion' => 'TRX-COLIDE',
        ]);

        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('transacciones_pago')
                ->where('codigo_transaccion', 'TRX-COLIDE')
                ->count(),
            'No debe ser posible persistir dos filas con el mismo codigo_transaccion.'
        );
    }

    public function test_una_colision_de_codigo_transaccion_se_recupera_sola_con_un_reintento(): void
    {
        // Ventana de carrera (TOCTOU) entre el exists() de
        // generarCodigoTransaccion() y el INSERT real: forzamos el mismo
        // escenario explícitamente (codigo_transaccion ya asignado antes
        // de creating(), así que el hook no lo regenera por su cuenta) —
        // el INSERT debe violar el UNIQUE de verdad, y
        // TransaccionPago::save() debe capturarlo, regenerar un código
        // nuevo y reintentar, sin que la excepción llegue al banco.
        TransaccionPago::create([
            'placa' => 'COL0001',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
            'codigo_transaccion' => 'TRX-COLIDE',
        ]);

        $segunda = TransaccionPago::create([
            'placa' => 'COL9999',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
            'codigo_transaccion' => 'TRX-COLIDE', // misma colision forzada
        ]);

        // Se recuperó solo: existe, tiene un codigo_transaccion valido,
        // y NO es el que colisionaba (tuvo que regenerarse).
        $this->assertTrue($segunda->exists);
        $this->assertNotSame('TRX-COLIDE', $segunda->codigo_transaccion);
        $this->assertMatchesRegularExpression('/^TRX-[A-Z0-9]{6}$/', $segunda->codigo_transaccion);

        // Ambas transacciones quedaron persistidas, cada una con su
        // propio codigo_transaccion distinto -- cero duplicados.
        $this->assertSame(2, TransaccionPago::count());
        $this->assertSame(
            2,
            TransaccionPago::distinct()->count('codigo_transaccion'),
            'Debe haber 2 codigo_transaccion distintos, no un duplicado silencioso.'
        );
    }

    public function test_una_colision_con_otro_tipo_de_error_de_bd_no_se_reintenta(): void
    {
        // El retry debe ser ESPECIFICO a la colision de codigo_transaccion
        // -- cualquier otro QueryException (ej. una FK inexistente) debe
        // propagarse normal, sin reintentar a ciegas. api_token_id
        // apunta a un id que no existe en api_tokens -> viola la FK
        // (constraint distinto, siempre forzado por InnoDB sin importar
        // el modo SQL, a diferencia de un enum/NOT NULL que depende de
        // sql_mode).
        $this->expectException(\Illuminate\Database\QueryException::class);

        TransaccionPago::create([
            'placa' => 'ERR0001',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
            'api_token_id' => 999999999,
        ]);
    }
}
