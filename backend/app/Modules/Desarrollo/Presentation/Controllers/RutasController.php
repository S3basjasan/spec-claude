<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\Services\AutorizadorDeRutas;
use App\Modules\Desarrollo\Application\UseCases\ActualizarRuta;
use App\Modules\Desarrollo\Application\UseCases\CambiarEstadoDeRuta;
use App\Modules\Desarrollo\Application\UseCases\CrearRuta;
use App\Modules\Desarrollo\Application\UseCases\ListarHistorialDeRuta;
use App\Modules\Desarrollo\Application\UseCases\ListarRutas;
use App\Modules\Desarrollo\Application\UseCases\ObtenerOpcionesDeRutas;
use App\Modules\Desarrollo\Application\UseCases\ObtenerPermisosDeRutas;
use App\Modules\Desarrollo\Application\UseCases\ObtenerRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoRutas;
use App\Modules\Desarrollo\Presentation\Requests\CambiarEstadoRutaRequest;
use App\Modules\Desarrollo\Presentation\Requests\FiltroRutasRequest;
use App\Modules\Desarrollo\Presentation\Requests\GuardarRutaRequest;
use App\Modules\Desarrollo\Presentation\Requests\HistorialRutaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RutasController
{
    use RespondeErroresDeRuta;

    public function permisos(Request $request, ObtenerPermisosDeRutas $permisos): JsonResponse
    {
        return response()->json(['data' => $permisos->ejecutar($this->esAdministrador($request))]);
    }

    public function opciones(ObtenerOpcionesDeRutas $opciones): JsonResponse
    {
        return response()->json(['data' => $opciones->ejecutar()]);
    }

    public function index(FiltroRutasRequest $request, ListarRutas $listar, AutorizadorDeRutas $autorizador): JsonResponse
    {
        $soloVigentes = ! $autorizador->permite($this->esAdministrador($request), ClaveDePermisoRutas::Gestionar);

        return response()->json($listar->ejecutar($this->tenant($request), $request->filtro(), $soloVigentes, $request->pagina(), $request->porPagina()));
    }

    public function historial(int $id, HistorialRutaRequest $request, ListarHistorialDeRuta $listar): JsonResponse
    {
        return $this->responder(fn () => response()->json($listar->ejecutar($this->tenant($request), $id, $request->pagina(), $request->porPagina())));
    }

    public function cambiarEstado(int $id, CambiarEstadoRutaRequest $request, CambiarEstadoDeRuta $cambiar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $cambiar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->version(), $request->estado(), $request->confirmaInscritos()),
        ]));
    }

    public function show(int $id, Request $request, ObtenerRuta $obtener, AutorizadorDeRutas $autorizador): JsonResponse
    {
        $soloVigentes = ! $autorizador->permite($this->esAdministrador($request), ClaveDePermisoRutas::Gestionar);

        return $this->responder(fn () => response()->json(['data' => $obtener->ejecutar($this->tenant($request), $this->usuario($request), $id, $soloVigentes)]));
    }

    public function store(GuardarRutaRequest $request, CrearRuta $crear): JsonResponse
    {
        return $this->responder(function () use ($request, $crear) {
            $ruta = $crear->ejecutar($this->tenant($request), $this->usuario($request), $request->datos());

            return response()->json(['data' => $ruta], 201)->header('Location', url('/api/rutas/'.$ruta['id']));
        });
    }

    public function update(int $id, GuardarRutaRequest $request, ActualizarRuta $actualizar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $actualizar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->datos()),
        ]));
    }
}
