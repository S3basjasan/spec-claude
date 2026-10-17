<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum TipoMicro: string
{
    case Video = 'Video';
    case Pdf = 'PDF';
    case Articulo = 'Articulo';
    case Quiz = 'Quiz';

    public function esQuiz(): bool
    {
        return $this === self::Quiz;
    }

    /** Video, PDF y Artículo se publican con URL; el Quiz no la lleva. */
    public function requiereUrl(): bool
    {
        return ! $this->esQuiz();
    }
}
