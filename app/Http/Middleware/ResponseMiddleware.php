<?php

namespace App\Http\Middleware;

use App\Models\ErrorLog;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;

class ResponseMiddleware
{


    public function handle($request, Closure $next)
    {

        // Define your API project's base URL



        $response = $next($request);

        if ($response->headers->get('content-type') === 'application/json') {
            $content = $response->getContent();
            $convertedContent = $this->convertDatesInJson($content);
            $response->setContent($convertedContent);

            if ($response->getStatusCode() >= 300) {
                $responseData = json_decode($response->getContent(), true);
                $isJsonArray = is_array($responseData);
                
                // Check if this error was already logged by Handler.php
                $alreadyLogged = $isJsonArray && isset($responseData['message']) && strpos($responseData['message'], 'Error ID:') !== false;

                if (!$alreadyLogged) {
                    try {
                        $payload = request()->except(['password', 'password_confirmation']);
                        $queries = request()->query();

                        $log = \App\Models\ActivityLog::create([
                            "api_url" => '/' . $request->path(),
                            "token" => $request->bearerToken(),
                            "user" => auth()->user() ? auth()->user()->email : null,
                            "user_id" => auth()->user() ? auth()->user()->id : null,
                            "activity" => "Manual Error Response",
                            "payload" => !empty($payload) ? json_encode($payload) : null,
                            "queries" => !empty($queries) ? json_encode($queries) : null,
                            "ip_address" => $request->ip(),
                            "request_method" => $request->method(),
                            "device" => $request->header('User-Agent'),
                            "is_error" => true,
                            "message" => $isJsonArray && isset($responseData['message']) ? $responseData['message'] : $response->getContent(),
                            "error_trace" => null, // No trace because no Exception was thrown
                            "status_code" => $response->getStatusCode(),
                        ]);

                        if ($response->getStatusCode() >= 500) {
                            $errorMessage = "We encountered an issue while processing your request. Please contact customer support and provide the Error ID: " . $log->id . " for assistance.";
                            $response->setContent(json_encode(['success' => false, 'message' => $errorMessage]));
                        } else {
                            if ($isJsonArray && isset($responseData['message'])) {
                                $responseData['message'] = "Error ID: " . $log->id . " - Status: " . $log->status_code . " - " . $responseData['message'];
                                $response->setContent(json_encode($responseData));
                            }
                        }
                    } catch (\Throwable $loggingException) {
                        // Silently catch to prevent loop
                    }
                }
            }
        }

        return $response;
    }

    private function convertDatesInJson($json)
    {
        $data = json_decode($json, true);


        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            array_walk_recursive($data, function (&$value, $key) {
                // Check if the value resembles a date but not in the format G-0001
                if (is_string($value) && (Carbon::hasFormat($value, 'Y-m-d') || Carbon::hasFormat($value, 'Y-m-d\TH:i:s.u\Z') || Carbon::hasFormat($value, 'Y-m-d\TH:i:s')  ||  Carbon::hasFormat($value, 'Y-m-d H:i:s'))) {
                    // Parse the date and format it as 'd-m-Y'

                    $date = Carbon::parse($value);

                    // If the date is in the far past, it's likely invalid
                    if ($date->year <= 0) {
                        $value = "";
                    } else {
                        // Format the date as 'd-m-Y' if no time is present, otherwise 'd-m-Y H:i:s'
                        if ($date->hour == 0 && $date->minute == 0 && $date->second == 0) {
                            $value = $date->format('d-m-Y');
                        } else {
                            $value = $date->format('d-m-Y H:i:s');
                        }
                    }
                }
            });

            return json_encode($data);
        }

        return $json;
    }
}
