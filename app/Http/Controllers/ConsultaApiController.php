<?php

namespace App\Http\Controllers;

use App\Services\DeudaVehicularService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ConsultaApiController extends Controller
{
    public function __construct(private DeudaVehicularService $deudaService)
    {
    }

    /**
     * Mostrar página de consulta visual de deuda vehicular
     */
    public function index()
    {
        return Inertia::render('Admin/ConsultaApi', [
            'anio_actual' => (int) date('Y'),
        ]);
    }

    /**
     * Ejecutar la consulta de deuda por placa.
     *
     * Reutiliza DeudaVehicularService — capa orquestadora que llama a
     * SriVehiculoService::consultarVehiculoCompleto() y delega la
     * conciliación en SriVehiculoService::conciliarPagosLocales() — misma
     * lógica que usa BancaController@consultarDeuda — para que esta
     * pantalla refleje correctamente qué años ya están pagados, en vez de
     * mostrar solo el bruto del SRI.
     */
    public function consultar(Request $request)
    {
        $request->validate([
            'placa' => 'required|string|max:10',
        ], [
            'placa.required' => 'La placa es obligatoria.',
            'placa.max'      => 'La placa no puede exceder 10 caracteres.',
        ]);

        $placa = strtoupper($request->placa);

        try {
            $resultado = $this->deudaService->consultar($placa);

            Log::info('Admin/ConsultaApi: Consulta exitosa', [
                'placa' => $placa,
                'user'  => auth()->user()->email,
                'todos_pagados' => $resultado['todos_pagados'],
            ]);

            return back()->with('resultado', [
                'success'      => true,
                'placa'        => $resultado['vehiculo']['placa'],
                'vehiculo'     => [
                    'marca'       => $resultado['vehiculo']['marca'],
                    'modelo'      => $resultado['vehiculo']['modelo'],
                    'anio'        => $resultado['vehiculo']['anio'],
                    'tipo'        => $resultado['vehiculo']['clase'] ?? 'AUTOMÓVIL',
                    'descripcion' => $resultado['vehiculo']['descripcion_completa'] ?? '',
                ],
                'valor_matricula'    => $resultado['valor_matricula'],
                // Desglose ya conciliado: cada año trae 'estado' (pagado|pendiente)
                // y, si corresponde, el sub-objeto 'pago' con comprobante/referencia/entidad/registro_historico.
                'desglose_anual'     => $resultado['desglose_anual'],
                'todos_pagados'      => $resultado['todos_pagados'],
                // Bruto: lo que calcula el sistema a partir de la matrícula del SRI,
                // SIN descontar pagos locales. Solo para fines informativos/auditoría.
                'totales_brutos'     => $resultado['totales_sri'],
                // Neto: lo que realmente falta por pagar según la BD local.
                // Este es el valor que debe usarse para decidir si hay deuda.
                'totales_pendientes' => $resultado['totales_pendientes'],
                'metodo_sri'         => $resultado['metodo_sri'],
                // Previsualización de la respuesta pública real de
                // consulta-deuda-rodaje-bancos, armada con el MISMO método que
                // usa BancaController (formatearRespuestaPublica) — para que
                // esta pantalla nunca muestre una forma distinta a la que un
                // banco realmente recibe. codigo_consulta va null: esta
                // previsualización no genera ni persiste una ConsultaBancaria
                // real (eso solo lo hace la API bancaria de verdad).
                'respuesta_api_banco' => $this->deudaService->formatearRespuestaPublica($resultado, null),
            ]);

        } catch (\Throwable $e) {
            Log::warning('Admin/ConsultaApi: Error en consulta', [
                'placa' => $placa,
                'error' => $e->getMessage(),
            ]);

            return back()->with('resultado', [
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
