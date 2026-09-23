<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiTokenSeeder extends Seeder
{
    /**
     * Dos entidades de prueba a propósito (no solo una): la mayoría de
     * flujos bancarios (verificar-pago, reporte-conciliacion) solo se
     * prueban de verdad con una segunda entidad "rival" para confirmar
     * aislamiento cross-tenant (api_token_id scope) — ver auditoría de
     * seguridad de esta sesión.
     */
    private const ENTIDADES = [
        [
            'usuario' => 'banco_test',
            'entidad_nombre' => 'Banco de Prueba S.A.',
            'password' => 'password123',
        ],
        [
            'usuario' => 'coop_rival_test',
            'entidad_nombre' => 'Cooperativa Rival',
            'password' => 'password123',
        ],
    ];

    public function run(): void
    {
        $filas = [];

        foreach (self::ENTIDADES as $entidad) {
            // Token estático fijo (como existía antes, para compatibilidad)
            $staticToken = 'api_test_token_' . Str::random(16);

            DB::table('api_tokens')->updateOrInsert(
                ['usuario' => $entidad['usuario']],
                [
                    'entidad_nombre'  => $entidad['entidad_nombre'],
                    'usuario'         => $entidad['usuario'],
                    'password_hash'   => Hash::make($entidad['password']),
                    'token'           => $staticToken, // token estático de respaldo
                    'activo'          => true,
                    'requests_permitidos' => 1000,
                    'notas'           => 'Usuario de prueba creado por seeder',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );

            $filas[] = [$entidad['entidad_nombre'], $entidad['usuario'], $entidad['password'], $staticToken];
        }

        $this->command->info('✅ Usuarios API creados:');
        $this->command->table(
            ['Entidad', 'Usuario', 'Password', 'Token estático (backup)'],
            $filas
        );
        $this->command->info('Login: POST /api/v1/auth/login — válido por 24 horas por sesión.');
    }
}
