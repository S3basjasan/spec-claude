<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El plan cambió desde que se leyó: control optimista de concurrencia (research R9). */
final class VersionDePdiDesfasada extends ErrorDePdi
{
    public function __construct(public readonly int $versionActual)
    {
        parent::__construct(
            'El plan cambió mientras lo revisabas. Recarga la página para ver la versión vigente.',
            'version_desfasada',
            null,
            [],
            ['version_actual' => $versionActual],
        );
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
