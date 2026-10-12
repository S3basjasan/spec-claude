<?php

use App\Modules\Shared\Application\Ports\EmisorTokenInterface;
use App\Modules\Shared\Application\UseCases\CerrarSesion;
use App\Modules\Shared\Domain\Entities\Usuario;

it('delega la revocación del token actual del usuario', function () {
    $emisor = new class implements EmisorTokenInterface
    {
        public ?int $revocadoPara = null;

        public function emitir(Usuario $usuario): string
        {
            return 'token';
        }

        public function revocarActual(int $usuarioId): void
        {
            $this->revocadoPara = $usuarioId;
        }
    };

    (new CerrarSesion($emisor))->ejecutar(5);

    expect($emisor->revocadoPara)->toBe(5);
});
