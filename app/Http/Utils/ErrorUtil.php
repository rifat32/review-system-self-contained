<?php
namespace App\Http\Utils;
use Exception;
use Illuminate\Http\Request;

trait ErrorUtil
{
    // this function do all the task and returns transaction id or -1
    public function sendError(Exception $e, $statusCode, Request $request)
    {

        $errorData = [
            "message" => $e->getMessage(),
            "line" => $e->getLine(),
            "file" => $e->getFile(),
        ];
        return response()->json($errorData, 422);

    }

    public function storeActivity() {
         return "";
    }


}
