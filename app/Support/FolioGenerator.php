<?php

namespace App\Support;

use App\Models\Aspirante;

class FolioGenerator
{
    public static function generate(): string
    {
        $year = now()->format('Y');

        do {
            $rand = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $folio = "UTH-{$year}-{$rand}";
        } while (Aspirante::where('folio_examen', $folio)->exists());

        return $folio;
    }
}
