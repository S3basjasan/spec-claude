<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeBorrador;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Descarta el borrador activo: se conserva con su rastro y libera el cupo del borrador del tipo. */
final class DescartarBorrador
{
    public function __construct(
        private readonly PoliticaRepositoryInterface $politicas,
        private readonly ResolvedorDeBorrador $borradores,
        private readonly TransaccionPort $transaccion,
        private readonly RelojPort $reloj,
    ) {}

    /**
     * @throws EscrituraDeshabilitada
     * @throws PoliticaNoEncontrada
     * @throws BorradorModificado
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, TipoDePolitica $tipo, int $versionId, int $versionEsperada): void
    {
        if (! config('hrms.gestion_personal.politicas_habilitado', true)) {
            throw new EscrituraDeshabilitada('La configuración de políticas está deshabilitada temporalmente.');
        }

        $this->transaccion->enTransaccion(function () use ($contexto, $actorId, $tipo, $versionId, $versionEsperada) {
            $ahora = $this->reloj->ahora();
            $cabecera = $this->politicas->cabecera($contexto->tenantId, $tipo) ?? throw new PoliticaNoEncontrada;
            $this->borradores->obtener($contexto->tenantId, $cabecera->id, $versionId, $versionEsperada);

            $this->politicas->descartarBorrador($contexto->tenantId, $versionId, $versionEsperada, $actorId, $ahora);
            $this->politicas->registrarAccion($contexto->tenantId, $cabecera->id, $versionId, 'BorradorDescartado', $actorId, null, null, null, $ahora);
        });
    }
}
