<?php

namespace App\Services;

use App\Mail\ExamResultAcceptedMail;
use App\Mail\ExamResultRejectedMail;
use App\Models\Aspirante;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ExamSyncService
{
    private ?bool $hasResultColumns = null;

    public function exportFolios(): array
    {
        $endpoint = config('exam.export_endpoint');
        if (!$endpoint) {
            throw new \RuntimeException('No está configurado EXAM_EXPORT_ENDPOINT.');
        }

        $aspirantes = Aspirante::query()
            ->whereNotNull('folio_examen')
            ->whereNull('folio_exportado_at')
            ->orderBy('folio_examen')
            ->get();

        if ($aspirantes->isEmpty()) {
            return [
                'exported' => 0,
                'skipped' => 0,
                'message' => 'No hay folios pendientes por exportar.',
            ];
        }

        $payload = [
            'fecha_corte' => now()->toDateString(),
            'folios' => $aspirantes->map(fn (Aspirante $aspirante) => [
                'folio' => $aspirante->folio_examen,
                'nombre' => trim($aspirante->nombre . ' ' . $aspirante->ap_paterno . ' ' . $aspirante->ap_materno),
                'curp' => $aspirante->curp,
            ])->values()->all(),
        ];

        $response = $this->http()->post($endpoint, $payload);

        if (!$response->successful()) {
            Log::warning('Fallo al exportar folios a examen externo', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('No se pudo enviar la lista de folios al servicio externo.');
        }

        $now = now();
        foreach ($aspirantes as $aspirante) {
            $aspirante->folio_exportado_at = $now;
            $aspirante->save();
        }

        return [
            'exported' => $aspirantes->count(),
            'skipped' => 0,
        ];
    }

    public function syncResults(): array
    {
        $endpoint = config('exam.results_endpoint');
        if (!$endpoint) {
            throw new \RuntimeException('No está configurado EXAM_RESULTS_ENDPOINT.');
        }

        $response = $this->http()->get($endpoint);
        if (!$response->successful()) {
            Log::warning('Fallo al consultar resultados de examen externo', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('No se pudo obtener la lista de resultados del servicio externo.');
        }

        $payload = $response->json();
        $items = $this->normalizeResultsPayload($payload);

        $processed = 0;
        $accepted = 0;
        $rejected = 0;
        $unknown = 0;

        foreach ($items as $item) {
            $aspirante = $this->resolveAspirante($item['folio'] ?? null, $item['curp'] ?? null);
            if (!$aspirante) {
                $unknown++;
                continue;
            }

            $step = isset($item['step']) ? (int) $item['step'] : null;
            $status = ($step !== null && $step >= 5) ? 'aprobado' : 'rechazado';

            $this->applyResult($aspirante, $status, $step);

            $processed++;
            if ($status === 'aprobado') {
                $accepted++;
            } else {
                $rejected++;
            }
        }

        return compact('processed', 'accepted', 'rejected', 'unknown');
    }

    private function http()
    {
        $token = config('exam.api_token');
        $timeout = max(5, (int) config('exam.timeout', 15));

        $request = Http::timeout($timeout);
        if ($token) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    private function normalizeResultsPayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return $payload;
    }

    private function resolveAspirante(?string $folio, ?string $curp): ?Aspirante
    {
        $query = Aspirante::query();

        if ($folio) {
            $candidate = (clone $query)->where('folio_examen', $folio)->first();
            if ($candidate) {
                return $candidate;
            }
        }

        if ($curp) {
            return Aspirante::where('curp', strtoupper($curp))->first();
        }

        return null;
    }

    public function applyResult(Aspirante $aspirante, string $status, ?int $step = null, bool $forceNotification = false): void
    {
        $canStoreMetadata = $this->canStoreResultMetadata();
        $currentStatus = $canStoreMetadata ? $aspirante->resultado_examen : null;
        $shouldNotify = $forceNotification;

        if ($canStoreMetadata && $status !== $currentStatus) {
            $aspirante->resultado_examen = $status;
            $aspirante->resultado_notificado_at = null;
            $shouldNotify = true;
        }

        if ($status === 'aprobado') {
            $targetStep = max(5, $step ?? 5);
            if (($aspirante->progress_step ?? 1) < $targetStep) {
                $aspirante->progress_step = $targetStep;
                $shouldNotify = true;
            }
        } else {
            if (($aspirante->progress_step ?? 1) !== -1) {
                $aspirante->progress_step = -1;
                $shouldNotify = true;
            }
        }

        $aspirante->save();

        if ($shouldNotify && $aspirante->email) {
            $this->notifyResult($aspirante, $status);
        }
    }

    private function notifyResult(Aspirante $aspirante, string $status): void
    {
        if (!filter_var($aspirante->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if ($status === 'aprobado') {
            Mail::to($aspirante->email)->send(new ExamResultAcceptedMail($aspirante));
        } else {
            Mail::to($aspirante->email)->send(new ExamResultRejectedMail($aspirante));
        }

        if ($this->canStoreResultMetadata()) {
            $aspirante->resultado_notificado_at = now();
            $aspirante->save();
        }
    }

    private function canStoreResultMetadata(): bool
    {
        if ($this->hasResultColumns === null) {
            $this->hasResultColumns = Schema::hasColumn('aspirantes', 'resultado_examen')
                && Schema::hasColumn('aspirantes', 'resultado_notificado_at');
        }

        return $this->hasResultColumns;
    }
}
