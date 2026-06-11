<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerarTokenApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generar-token {nombre} {--ip=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar un token de API para una entidad bancaria';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $nombre = $this->argument('nombre');
        $ip = $this->option('ip');

        // Generar token único
        $token = Str::random(64);

        // Insertar en la base de datos
        $id = DB::table('api_tokens')->insertGetId([
            'entidad_nombre' => $nombre,
            'token' => $token,
            'activo' => true,
            'requests_permitidos' => 1000,
            'ip_permitida' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('✅ Token generado exitosamente!');
        $this->newLine();
        $this->line('ID: ' . $id);
        $this->line('Entidad: ' . $nombre);
        $this->line('Token: ' . $token);

        if ($ip) {
            $this->line('IP Permitida: ' . $ip);
        }

        $this->newLine();
        $this->comment('Guarda este token de forma segura. No se puede recuperar después.');
        $this->newLine();
        $this->comment('Ejemplo de uso:');
        $this->line('curl -X POST http://localhost:8000/api/v1/consulta-deuda \\');
        $this->line('  -H "Authorization: Bearer ' . $token . '" \\');
        $this->line('  -H "Content-Type: application/json" \\');
        $this->line('  -d \'{"placa":"ABC1234","anio_fiscal":2026}\'');

        return Command::SUCCESS;
    }
}
