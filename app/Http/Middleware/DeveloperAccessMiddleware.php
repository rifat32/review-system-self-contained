<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeveloperAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $emailsString = env('DEV_ACCESS_EMAILS', '');
        $allowedEmails = array_filter(array_map('trim', explode(',', $emailsString)));

        // If no emails are set in .env, block access by default for safety
        if (empty($allowedEmails)) {
            abort(403, 'Developer access is disabled. Set DEV_ACCESS_EMAILS in .env to enable.');
        }

        if (!$request->session()->get('developer_authenticated')) {
            if ($request->expectsJson() || $request->isXmlHttpRequest()) {
                return response()->json(['message' => 'Unauthorized. Developer OTP authentication required.'], 401);
            }
            return redirect()->route('dev.login');
        }

        return $next($request);
    }
}
