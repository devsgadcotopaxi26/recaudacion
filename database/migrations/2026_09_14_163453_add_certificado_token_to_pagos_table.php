<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('certificado_token', 64)->nullable()->after('referencia_pago');
        });

        // Backfill: cada pago existente necesita su propio token, no puede
        // quedar en null antes del índice único (referencia_pago no sirve
        // como clave pública: la define el banco/cooperativa y es secuencial).
        DB::table('pagos')->whereNull('certificado_token')->orderBy('id')->each(function ($pago) {
            DB::table('pagos')->where('id', $pago->id)->update([
                'certificado_token' => Str::random(32),
            ]);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->unique('certificado_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropUnique(['certificado_token']);
            $table->dropColumn('certificado_token');
        });
    }
};
