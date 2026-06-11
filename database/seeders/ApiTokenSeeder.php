<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiTokenSeeder extends Seeder
{
    public function run(): void
    {
        // Token estático fijo (como existía antes, para compatibilidad)
        $staticToken = 'api_test_token_' . Str::random(16);

        DB::table('api_tokens')->updateOrInsert(
            ['usuario' => 'banco_test'],
            [
                'entidad_nombre'  => 'Banco de Prueba S.A.',
                'usuario'         => 'banco_test',
                'password_hash'   => Hash::make('password123'),
                'token'           => $staticToken, // token estático de respaldo
                'activo'          => true,
                'requests_permitidos' => 1000,
                'notas'           => 'Usuario de prueba creado por seeder',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        $this->command->info('✅ Usuario API creado:');
        $this->command->table(
            ['Campo', 'Valor'],
            [
                ['Endpoint Login', 'POST /api/v1/auth/login'],
                ['Usuario',        'banco_test'],
                ['Password',       'password123'],
                ['Token estático (backup)', $staticToken],
                ['Válido por',     '24 horas por sesión'],
            ]
        );
    }
}
