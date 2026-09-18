<?php

namespace App\Services;

/**
 * Consulta de deuda vehicular: combina el cálculo del SRI (vía
 * SriVehiculoService::consultarVehiculoCompleto) con el cruce contra pagos
 * locales (vía SriVehiculoService::conciliarPagosLocales), para determinar
 * qué años fiscales están realmente pendientes (no solo lo que reporta el
 * SRI en bruto).
 *
 * Es una capa orquestadora, no reimplementa la conciliación: delega en
 * conciliarPagosLocales() (fuente única de esa regla, compartida también
 * por cualquier otro consumidor directo de SriVehiculoService) y solo
 * añade encima los datos que esa función no maneja (vehiculo, valor de
 * matrícula, totales brutos del SRI, método usado).
 *
 * Único punto de entrada de esta lógica combinada en el sistema: lo usan
 * tanto la API bancaria (BancaController::consultarDeuda, que además
 * genera codigo_consulta y audita en ConsultaBancaria) como el panel admin
 * (ConsultaApiController) — para no mantener dos criterios distintos de
 * "qué años están pendientes".
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
        $conciliacion = $this->sriService->conciliarPagosLocales($placa, $datos['desglose_anual']);

        return [
            'vehiculo' => $datos['vehiculo'],
            'valor_matricula' => $datos['valor_matricula'],
            'todos_pagados' => $conciliacion['todos_pagados'],
            'desglose_anual' => $conciliacion['desglose_anual'],
            'totales_sri' => $datos['totales'],
            'totales_pendientes' => $conciliacion['totales_pendientes'],
            'metodo_sri' => $datos['metodo_sri'] ?? 'deuda',
        ];
    }

    /**
     * Da forma al bloque 'data' exactamente como lo devuelve
     * BancaController::consultarDeuda() al banco — único lugar donde se
     * define esa forma, para que la previsualización del panel admin
     * (ConsultaApiController, pantalla "Ver respuesta JSON de la API")
     * no pueda divergir de la respuesta real sin que se note (ambos
     * llaman a este método).
     *
     * Sin efectos secundarios: no genera código de consulta ni escribe
     * auditoría — quien llama decide eso (BancaController sí lo hace,
     * ConsultaApiController no, porque solo previsualiza).
     */
    public function formatearRespuestaPublica(array $resultado, ?string $codigoConsulta): array
    {
        $vehiculo = $resultado['vehiculo'];

        return [
            'codigo_consulta' => $codigoConsulta,
            'placa' => $vehiculo['placa'],
            'vehiculo' => [
                'marca' => $vehiculo['marca'],
                'modelo' => $vehiculo['modelo'],
                'anio' => $vehiculo['anio'],
                'tipo' => $vehiculo['clase'] ?? 'automovil',
                'descripcion' => $vehiculo['descripcion_completa'] ?? '',
            ],
            'valor_matricula' => $resultado['valor_matricula'],
            'todos_pagados' => $resultado['todos_pagados'],
            'desglose_anual' => $resultado['desglose_anual'],
            'totales' => $resultado['totales_pendientes'],
            'nota' => $resultado['todos_pagados']
                ? 'Todos los años están pagados. No hay deuda pendiente.'
                : 'Use el codigo_consulta al registrar el pago. Válido por 24 horas.',
        ];
    }
}
