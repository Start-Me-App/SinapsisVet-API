<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function success($data, $code = 200): JsonResponse
    {
        return response()->json($data, $code);
    }

    protected function error(\Exception $e, $message = null): JsonResponse
    {
        $statusCode = $this->httpCode($e);
        $message    = $message ?? $e->getMessage();
        $error      = [
            'errors' => [
                'status' => $statusCode,
                'title'  => $message,
                'file'   => $e->getFile(),
                'line'   => $e->getLine()
            ],
        ];

        return response()->json($error, $statusCode);
    }

    /**
     * Get HTTP code from Exception
     */
    protected function httpCode(\Exception $e): int
    {
        if ($e->getCode() <= 0) {
            return 500;
        }

        if ($e->getCode() > 500) {
            return 500;
        }

        return (int)$e->getCode();
    }
}
