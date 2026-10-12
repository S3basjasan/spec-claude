<?php

namespace App\Modules\Shared\Presentation\Controllers;

use App\Modules\Shared\Application\DTO\CredencialesDTO;
use App\Modules\Shared\Application\UseCases\CerrarSesion;
use App\Modules\Shared\Application\UseCases\IniciarSesion;
use App\Modules\Shared\Application\UseCases\ObtenerUsuarioActual;
use App\Modules\Shared\Domain\Exceptions\CredencialesInvalidas;
use App\Modules\Shared\Presentation\Requests\LoginRequest;
use App\Modules\Shared\Presentation\Resources\SesionResource;
use App\Modules\Shared\Presentation\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController
{
    /**
     * Iniciar sesión.
     *
     * Devuelve un token Bearer, el usuario y su rol.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request, IniciarSesion $iniciarSesion): JsonResponse
    {
        try {
            $sesion = $iniciarSesion->ejecutar(new CredencialesDTO(
                $request->validated('email'),
                $request->validated('password'),
            ));
        } catch (CredencialesInvalidas $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return (new SesionResource($sesion))->response();
    }

    public function logout(Request $request, CerrarSesion $cerrarSesion): Response
    {
        $cerrarSesion->ejecutar((int) $request->user()->getAuthIdentifier());

        return response()->noContent();
    }

    public function me(Request $request, ObtenerUsuarioActual $obtenerUsuarioActual): JsonResponse
    {
        $usuario = $obtenerUsuarioActual->ejecutar((int) $request->user()->getAuthIdentifier());

        if ($usuario === null) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return (new UsuarioResource($usuario))->response();
    }
}
