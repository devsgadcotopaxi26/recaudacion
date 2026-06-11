<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
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
            $table->enum('tipo_vehiculo', ['automovil', 'camioneta', 'motocicleta', 'bus', 'camion'])->default('automovil');
            $table->timestamps();

            $table->index('placa');
            $table->index('cedula_propietario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
