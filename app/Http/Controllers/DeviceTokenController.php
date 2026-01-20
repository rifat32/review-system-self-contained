<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function createDeviceToken(Request $request)
    {
        $request->validate([
            'device_token' => ['required', 'string', 'regex:/^\S+$/'],
            'device_type' => 'nullable|string',
        ]);

        $deviceToken = $request->device_token;

        // Store first
        $tokenModel = DeviceToken::updateOrCreate(
            ['device_token' => $deviceToken],
            [
                'user_id' => $request->user()->id,
                'device_type' => $request->device_type,
            ]
        );



        return response()->json([
            'success' => true,
            'message' => 'Device token registered/updated',
            'token' => $tokenModel
        ], 201);
    }
}
