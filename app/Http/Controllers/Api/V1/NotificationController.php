<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Aspirante;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function notifyAspirante(
        Request $request,
        Aspirante $aspirante,
        FirebaseNotificationService $notifications
    ) {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'body'  => 'required|string|max:255',
            'data'  => 'nullable|array',
        ]);

        $result = $notifications->notifyAspirante(
            $aspirante,
            $data['title'],
            $data['body'],
            $data['data'] ?? []
        );

        return response()->json([
            'ok' => true,
            'sent' => $result,
        ]);
    }

    public function notifyTokens(Request $request, FirebaseNotificationService $notifications)
    {
        $data = $request->validate([
            'tokens' => 'required|array|min:1',
            'tokens.*' => 'string',
            'title' => 'required|string|max:100',
            'body'  => 'required|string|max:255',
            'data'  => 'nullable|array',
        ]);

        $result = $notifications->sendToTokens(
            $data['tokens'],
            $data['title'],
            $data['body'],
            $data['data'] ?? []
        );

        return response()->json([
            'ok' => true,
            'sent' => $result,
        ]);
    }
}
