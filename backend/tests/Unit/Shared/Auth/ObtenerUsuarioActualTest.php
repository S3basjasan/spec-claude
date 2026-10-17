<?php

use App\Modules\Shared\Application\UseCases\ObtenerUsuarioActual;
use App\Modules\Shared\Domain\Entities\Usuario;
use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;
use App\Modules\Shared\Domain\ValueObjects\Rol;

function repoDeUnUsuario(?Usuario $usuario): UsuarioRepositoryInterface
{
    return new class($usuario) implements UsuarioRepositoryInterface
    {
        public function __construct(private readonly ?Usuario $usuario) {}

        public function buscarPorCorreo(string $correo): ?Usuario
        {
            return $this->usuario;
        }

        public function obtenerPorId(int $id): ?Usuario
        {
            return $this->usuario;
        }

        public function nombresPorIds(array $ids): array
        {
            return [];
        }

        public function idsPorNombre(string $texto, int $limite): array
        {
            return [];
        }
    };
}

it('devuelve el DTO con identidad y rol, sin password', function () {
    $dto = (new ObtenerUsuarioActual(repoDeUnUsuario(new Usuario(7, 'Luis', 'luis@hrms.test', Rol::User, 'hash'))))
        ->ejecutar(7);

    expect($dto->id)->toBe(7)
        ->and($dto->nombre)->toBe('Luis')
        ->and($dto->correo)->toBe('luis@hrms.test')
        ->and($dto->rol)->toBe('user')
        ->and(get_object_vars($dto))->not->toHaveKey('passwordHash');
});

it('devuelve null si el usuario no existe', function () {
    expect((new ObtenerUsuarioActual(repoDeUnUsuario(null)))->ejecutar(99))->toBeNull();
});
