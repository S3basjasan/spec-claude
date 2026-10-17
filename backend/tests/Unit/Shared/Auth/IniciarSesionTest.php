<?php

use App\Modules\Shared\Application\DTO\CredencialesDTO;
use App\Modules\Shared\Application\Ports\EmisorTokenInterface;
use App\Modules\Shared\Application\Ports\HasherInterface;
use App\Modules\Shared\Application\UseCases\IniciarSesion;
use App\Modules\Shared\Domain\Entities\Usuario;
use App\Modules\Shared\Domain\Exceptions\CredencialesInvalidas;
use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;
use App\Modules\Shared\Domain\ValueObjects\Rol;

function repositorioCon(?Usuario $usuario): UsuarioRepositoryInterface
{
    return new class($usuario) implements UsuarioRepositoryInterface
    {
        public ?string $correoBuscado = null;

        public function __construct(private readonly ?Usuario $usuario) {}

        public function buscarPorCorreo(string $correo): ?Usuario
        {
            $this->correoBuscado = $correo;

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

function hasherQueAcepta(string $hashValido): HasherInterface
{
    return new class($hashValido) implements HasherInterface
    {
        public int $verificaciones = 0;

        public function __construct(private readonly string $hashValido) {}

        public function verificar(string $plano, string $hash): bool
        {
            $this->verificaciones++;

            return $plano === 'correcta' && $hash === $this->hashValido;
        }

        public function hashFicticio(): string
        {
            return 'hash-ficticio';
        }
    };
}

function emisorFalso(): EmisorTokenInterface
{
    return new class implements EmisorTokenInterface
    {
        public function emitir(Usuario $usuario): string
        {
            return 'token-'.$usuario->id;
        }

        public function revocarActual(int $usuarioId): void {}
    };
}

it('devuelve sesión con token y usuario si las credenciales son válidas', function () {
    $usuario = new Usuario(1, 'Ana', 'ana@hrms.test', Rol::Admin, 'hash-real');
    $repo = repositorioCon($usuario);

    $sesion = (new IniciarSesion($repo, hasherQueAcepta('hash-real'), emisorFalso()))
        ->ejecutar(new CredencialesDTO('  ANA@HRMS.test ', 'correcta'));

    expect($sesion->token)->toBe('token-1')
        ->and($sesion->usuario->rol)->toBe('admin')
        ->and($repo->correoBuscado)->toBe('ana@hrms.test');
});

it('lanza CredencialesInvalidas con contraseña errónea', function () {
    $usuario = new Usuario(1, 'Ana', 'ana@hrms.test', Rol::User, 'hash-real');

    (new IniciarSesion(repositorioCon($usuario), hasherQueAcepta('hash-real'), emisorFalso()))
        ->ejecutar(new CredencialesDTO('ana@hrms.test', 'mala'));
})->throws(CredencialesInvalidas::class);

it('compara contra un hash ficticio y lanza CredencialesInvalidas si el correo no existe', function () {
    $hasher = hasherQueAcepta('hash-real');

    try {
        (new IniciarSesion(repositorioCon(null), $hasher, emisorFalso()))
            ->ejecutar(new CredencialesDTO('nadie@hrms.test', 'correcta'));
        $this->fail('Debió lanzar CredencialesInvalidas');
    } catch (CredencialesInvalidas) {
        expect($hasher->verificaciones)->toBe(1);
    }
});
