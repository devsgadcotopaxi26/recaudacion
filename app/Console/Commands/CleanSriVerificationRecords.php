<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanSriVerificationRecords extends Command
{
    protected $signature = 'sri:clean-verification';
    protected $description = 'Eliminar registros antiguos del endpoint de verificación';

    public function handle()
    {
        $deleted = DB::table('sri_requests')
            ->where('endpoint', 'verificacion')
            ->delete();

        $this->info("✅ Eliminados {$deleted} registros del endpoint 'verificacion'");

        $remaining = DB::table('sri_requests')->count();
        $this->info("📊 Registros restantes en sri_requests: {$remaining}");

        return 0;
    }
}
