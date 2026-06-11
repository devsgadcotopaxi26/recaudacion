<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('placa', 10)->nullable()->after('vehiculo_id');
            $table->unsignedBigInteger('vehiculo_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('placa');
        });
    }
};
