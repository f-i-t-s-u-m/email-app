<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseController extends Controller
{


    public function sendResponse($message, $data = [])
    {
        $response = [
            'message' => $message,
            'data' => $data,
            'success' => true,
        ];

        return response()->json($response, 200);
    }

    public static function sendError($message, $errors = [])
    {
        $response = [
            'message' => $message,
            "errors" => $errors,
            'success' => false,
        ];

        return response()->json($response);
    }
}
