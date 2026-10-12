<?php

namespace App\Modules\Shared\Application\UseCases;

use App\Modules\Shared\Application\DTO\CredencialesDTO;
use App\Modules\Shared\Application\DTO\SesionDTO;
use App\Modules\Shared\Application\DTO\UsuarioDTO;
use App\Modules\Shared\Application\Ports\EmisorTokenInterface;
use App\Modules\Shared\Application\Ports\HasherInterface;
use App\Modules\Shared\Domain\Exceptions\CredencialesInvalidas;
use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;

final class IniciarSesion
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly HasherInterface $hasher,
        private readonly EmisorTokenInterface $emisor,
    ) {}

    /** @throws CredencialesInvalidas */
    public function ejecutar(CredencialesDTO $credenciales): SesionDTO
    {
        $correo = mb_strtolower(trim($credenciales->correo));
        $usuario = $this->usuarios->buscarPorCorreo($correo);

        // Se compara siempre contra un hash, exista o no el correo, para no revelar cuentas por tiempo.
        $hash = $usuario?->passwordHash ?? $this->hasher->hashFicticio();
        $coincide = $this->hasher->verificar($credenciales->password, $hash);

        if ($usuario === null || ! $coincide) {
            throw new CredencialesInvalidas;
        }

        return new SesionDTO($this->emisor->emitir($usuario), UsuarioDTO::desde($usuario));
    }
}
