<?php

namespace App\Console\Commands;

use App\Models\TransaccionPago;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillTokenVerificacion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pagos:backfill-token-verificacion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera token_verificacion para transacciones_pago existentes que no lo tengan (columna agregada después de su creación)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pendientes = TransaccionPago::whereNull('token_verificacion')->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay transacciones pendientes de backfill.');
            return 0;
        }

        $this->info("Generando token_verificacion para {$pendientes->count()} transacciones...");

        $progressBar = $this->output->createProgressBar($pendientes->count());
        $progressBar->start();

        foreach ($pendientes as $transaccion) {
            $transaccion->update(['token_verificacion' => Str::random(32)]);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info('Backfill completado.');

        return 0;
    }
}
