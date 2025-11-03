<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AspiranteCollection extends ResourceCollection
{
    public $collects = AspiranteResource::class;

    public function toArray(Request $request): array
    {
        return [
            'items' => $this->collection,
            'meta'  => [
                'count' => $this->count(),
            ],
        ];
    }
}
