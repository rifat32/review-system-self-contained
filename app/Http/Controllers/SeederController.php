<?php

namespace App\Http\Controllers;

use Database\Seeders\AiReadyDemoBusinessSeeder;
use Illuminate\Http\Request;

class SeederController extends Controller
{
    /**
     * Run the AI-ready demo business seeder via web request.
     * Accepts optional `email` query parameter.
     * Only allowed in local or debug environments.
     */
    public function runDemo(Request $request)
    {
        if (!app()->environment('local') && !config('app.debug')) {
            abort(403, 'Seeder can only be run in local or debug environments');
        }

        $email = $request->query('email', 'demo@example.com');

        try {
            (new AiReadyDemoBusinessSeeder())->run($email);

            return response()->json([
                'success' => true,
                'message' => 'Seeder executed',
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
