<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponder
{
    public static function success(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    public static function error(string $message, int $code = 400, mixed $data = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }
}
