<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina `vehiculos` — tabla huérfana desde el primer commit del proyecto
 * (2026-06-11): confirmado por auditoría que ningún flujo real la escribe
 * ni la lee (App\Models\Vehiculo se importaba en 3 controllers/services sin
 * usarse nunca — `grep -rn "Vehiculo::" app/` daba 0 resultados). Sus 5
 * filas eran datos de demo ficticios sembrados por DatabaseSeeder, nunca
 * poblados desde el SRI. Marca/modelo/año de vehículo siempre se obtienen
 * en vivo desde el SRI (SriVehiculoService::obtenerDetalleCompleto()), no
 * de esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vehiculos');
    }

    public function down(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10)->unique();
            $table->string('cedula_propietario', 10);
            $table->string('propietario');
            $table->string('marca', 50);
            $table->string('modelo', 50);
            $table->integer('anio');
            $table->decimal('avaluo', 10, 2);
            $table->decimal('valor_matricula', 10, 2)->default(0);
            $table->enum('tipo_vehiculo', ['automovil', 'camioneta', 'motocicleta', 'bus', 'camion'])->default('automovil');
            $table->timestamps();

            $table->index('placa');
            $table->index('cedula_propietario');
        });
    }
};
