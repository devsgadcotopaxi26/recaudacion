<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * El SRI prometió desglose por componente para un rubro (codigoRubro
 * presente en ConsultaRubros) pero ConsultaComponente no lo pudo entregar
 * ni siquiera tras agotar los reintentos (ver SriVehiculoService::
 * obtenerDetalleCompleto()). Tipo distinto a cualquier otra falla del SRI
 * a propósito: NUNCA debe traducirse en un desglose colapsado por año —
 * el llamador debe propagar un error explícito al banco en vez de servir
 * datos incompletos como si fueran correctos.
 */
class SriConsultaIncompletaException extends Exception
{
    public function __construct(
        string $message = 'No se pudo obtener el desglose completo de la deuda. Intente nuevamente en unos minutos.',
        int $code = 502,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
