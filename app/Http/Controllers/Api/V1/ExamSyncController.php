<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ExamSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamSyncController extends Controller
{
    public function exportFolios(Request $request, ExamSyncService $service): JsonResponse
    {
        $data = $service->exportFolios();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function syncResults(Request $request, ExamSyncService $service): JsonResponse
    {
        $data = $service->syncResults();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
