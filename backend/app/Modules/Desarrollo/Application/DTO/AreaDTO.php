<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class AreaDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
    ) {}
}
