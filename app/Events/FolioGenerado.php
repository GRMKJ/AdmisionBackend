<?php

namespace App\Events;

use App\Models\Aspirante;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FolioGenerado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Aspirante $aspirante,
        public string $folio
    ) {}
}
