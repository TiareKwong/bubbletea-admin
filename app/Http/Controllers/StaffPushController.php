<?php

namespace App\Http\Controllers;

use App\Models\StaffPushSubscription;
use Illuminate\Http\Request;

class StaffPushController extends Controller
{
    public function subscribe(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'endpoint'  => 'required|string',
            'p256dh'    => 'required|string',
            'auth'      => 'required|string',
            'branch_id' => 'nullable|integer',
        ]);

        StaffPushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            ['user_id' => auth()->id(), 'branch_id' => $data['branch_id'] ?? null, 'endpoint' => $data['endpoint'], 'p256dh' => $data['p256dh'], 'auth' => $data['auth']]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): \Illuminate\Http\JsonResponse
    {
        $endpoint = $request->input('endpoint');
        StaffPushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();

        return response()->json(['ok' => true]);
    }
}
