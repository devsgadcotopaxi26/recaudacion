<?php

namespace App\Services;

use App\Models\Pago;

/**
 * Consulta de deuda vehicular: combina el cálculo del SRI (vía
 * SriVehiculoService) con el cruce contra pagos locales, para determinar
 * qué años fiscales están realmente pendientes (no solo lo que reporta el
 * SRI en bruto).
 *
 * Única implementación de esta lógica en el sistema: la usan tanto la API
 * bancaria (BancaController::consultarDeuda, que además genera
 * codigo_consulta y audita en ConsultaBancaria) como el panel de ventanilla
 * (VerificadorConsultaDeudaController, de solo lectura) — para no mantener
 * dos criterios distintos de "qué años están pendientes".
 *
 * No tiene efectos secundarios propios: solo lee `pagos` y transforma lo
 * que devuelve SriVehiculoService.
 */
class DeudaVehicularService
{
    public function __construct(private SriVehiculoService $sriService)
    {
    }

    /**
     * @return array{
     *     vehiculo: array,
     *     valor_matricula: float,
     *     todos_pagados: bool,
     *     desglose_anual: array,
     *     totales_sri: array,
     *     totales_pendientes: array,
     *     metodo_sri: string,
     * }
     */
    public function consultar(string $placa): array
    {
        $placa = strtoupper($placa);
        $datos = $this->sriService->consultarVehiculoCompleto($placa);

        // Todos los pagos 'pagado' de esta placa, indexados por año fiscal
        $pagosExistentes = Pago::where('placa', $placa)
            ->where('estado', 'pagado')
            ->get()
            ->keyBy('anio_fiscal');

        // Marcar cada año del desglose SRI como pagado o pendiente
        $desgloseConEstado = collect($datos['desglose_anual'])->map(function ($anio) use ($pagosExistentes) {
            $pago = $pagosExistentes->get($anio['anio']);
            $anio['estado'] = $pago ? 'pagado' : 'pendiente';
            if ($pago) {
                $anio['pago'] = [
                    'pago_id' => $pago->id,
                    'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
                    'codigo_consulta' => $pago->datos_adicionales['codigo_consulta'] ?? null,
                    'referencia' => $pago->referencia_pago,
                    'fecha_pago' => $pago->fecha_pago?->format('Y-m-d H:i:s'),
                    'entidad' => $pago->datos_adicionales['entidad_recaudadora'] ?? null,
                ];
            }
            return $anio;
        })->values()->toArray();

        $aniosPendientes = collect($desgloseConEstado)->where('estado', 'pendiente');

        return [
            'vehiculo' => $datos['vehiculo'],
            'valor_matricula' => $datos['valor_matricula'],
            'todos_pagados' => $aniosPendientes->isEmpty(),
            'desglose_anual' => $desgloseConEstado,
            'totales_sri' => $datos['totales'],
            'totales_pendientes' => [
                'total_rodaje' => round($aniosPendientes->sum('rodaje'), 2),
                'total_mora' => round($aniosPendientes->sum('mora'), 2),
                'total_a_pagar' => round($aniosPendientes->sum('valor'), 2),
            ],
            'metodo_sri' => $datos['metodo_sri'] ?? 'deuda',
        ];
    }
}
