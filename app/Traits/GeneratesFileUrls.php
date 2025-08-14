<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait GeneratesFileUrls
{
    protected function diskUrl(string $disk, ?string $path): ?string
    {
        if (empty($path)) return null;

        $fs = Storage::disk($disk);

        // Si el driver soporta url()
        if (method_exists($fs, 'url')) {
            try {
                return $fs->url($path);
            } catch (\Throwable $e) {
                // seguirá al fallback
            }
        }

        // Fallback usando la config del disco
        $base = config("filesystems.disks.$disk.url");
        if ($base) {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }

        // Último recurso: para disco 'public'
        if ($disk === 'public') {
            return asset('storage/' . ltrim($path, '/'));
        }

        return null;
    }
}
