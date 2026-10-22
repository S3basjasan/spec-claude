<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\BuscarColaboradoresInscribibles;
use App\Modules\Desarrollo\Application\UseCases\CambiarNivelesDeInscripcion;
use App\Modules\Desarrollo\Application\UseCases\InscribirEnRuta;
use App\Modules\Desarrollo\Application\UseCases\ListarInscripcionesDeRuta;
use App\Modules\Desarrollo\Application\UseCases\RegistrarNivelAlcanzado;
use App\Modules\Desarrollo\Application\UseCases\RetirarInscripcion;
use App\Modules\Desarrollo\Presentation\Requests\BuscarColaboradoresRequest;
use App\Modules\Desarrollo\Presentation\Requests\CambiarNivelesRequest;
use App\Modules\Desarrollo\Presentation\Requests\FiltroInscripcionesRequest;
use App\Modules\Desarrollo\Presentation\Requests\InscribirRequest;
use App\Modules\Desarrollo\Presentation\Requests\RetirarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InscripcionesRutaController
{
    use RespondeErroresDeRuta;

    public function index(FiltroInscripcionesRequest $request, ListarInscripcionesDeRuta $listar): JsonResponse
    {
        return response()->json($listar->ejecutar($this->tenant($request), $this->usuario($request), $request->filtro(), $request->pagina(), $request->porPagina()));
    }

    public function colaboradores(BuscarColaboradoresRequest $request, BuscarColaboradoresInscribibles $buscar): JsonResponse
    {
        return $this->responder(fn () => response()->json($buscar->ejecutar($this->tenant($request), $this->usuario($request), $request->texto(), $request->pagina(), $request->porPagina())));
    }

    public function store(InscribirRequest $request, InscribirEnRuta $inscribir): JsonResponse
    {
        return $this->responder(function () use ($request, $inscribir) {
            $inscripcion = $inscribir->ejecutar($this->tenant($request), $this->usuario($request), $request->rutaId(), $request->colaboradorId(), $request->nivelActualId(), $request->nivelObjetivoId());

            return response()->json(['data' => $inscripcion], 201);
        });
    }

    public function niveles(int $id, CambiarNivelesRequest $request, CambiarNivelesDeInscripcion $cambiar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $cambiar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->nivelActualId(), $request->nivelObjetivoId()),
        ]));
    }

    public function alcanzar(int $id, Request $request, RegistrarNivelAlcanzado $alcanzar): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $alcanzar) {
            $resultado = $alcanzar->ejecutar($this->tenant($request), $this->usuario($request), $id);

            return response()->json(['data' => $resultado['inscripcion'], 'nota' => $resultado['nota']]);
        });
    }

    public function retirar(int $id, RetirarRequest $request, RetirarInscripcion $retirar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $retirar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->motivo())]));
    }
}
