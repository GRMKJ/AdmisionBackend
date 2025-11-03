<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request) {
        $user = $request->user();
        $data = $request->validate([
            'fcm_token' => 'required|string|max:255',
            'platform'  => 'nullable|string|max:30', // android, web, wear_os
        ]);

        $dt = DeviceToken::firstOrCreate(
            ['fcm_token' => $data['fcm_token']],
            ['id_aspirantes' => $user->getKey(), 'platform' => $data['platform'] ?? null]
        );

        return response()->json(['ok' => true, 'id' => $dt->id]);
    }

    public function destroy(Request $request) {
        $request->validate(['fcm_token' => 'required']);
        DeviceToken::where('fcm_token', $request->fcm_token)->delete();
        return response()->json(['ok' => true]);
    }

}
