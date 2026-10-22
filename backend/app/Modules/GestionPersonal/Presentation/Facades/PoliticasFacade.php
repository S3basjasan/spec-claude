<?php

namespace App\Modules\GestionPersonal\Presentation\Facades;

use App\Modules\GestionPersonal\Application\Services\PoliticasService;
use Illuminate\Support\Facades\Facade;

/**
 * Contrato público de las políticas operativas para novedades, movimientos y offboarding.
 *
 * @method static array aplicablePara(int $tenantId, string $tipo, \DateTimeImmutable $instante) {situacion: SinConfigurar|Vigente|NoAdmiteSolicitudes, politica: {version_id, numero, tipo, inicio_vigencia, parametros, condiciones, hash}|null}; lanza PoliticaNoDisponible si la lectura falla.
 * @method static array seleccionarParaPresentar(int $tenantId, string $tipo, \DateTimeImmutable $instante) Versión aplicable al presentar, con el bloqueo del tipo; exige la transacción del consumidor; lanza PoliticaNoConfigurada, PoliticaNoAdmiteSolicitudes o PoliticaNoDisponible.
 *
 * @see PoliticasService
 */
class PoliticasFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'politicas-facade';
    }
}
