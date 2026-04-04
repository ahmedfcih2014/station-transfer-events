<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

abstract class Controller
{
    public function successResponse(mixed $data): JsonResponse
    {
        return response()->json($data, Response::HTTP_OK);
    }
}
