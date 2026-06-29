<?php

namespace App\Http\Controllers;

use App\Support\Operations\SystemHealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(SystemHealthService $health): JsonResponse
    {
        $ready = $health->readiness();

        return response()->json([
            'status' => $ready['ok'] ? 'ready' : 'unavailable',
            'correlation_id' => request()->attributes->get('correlation_id'),
        ], $ready['ok'] ? 200 : 503);
    }
}
