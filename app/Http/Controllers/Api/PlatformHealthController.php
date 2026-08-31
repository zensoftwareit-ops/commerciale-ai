<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Operations\SystemHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformHealthController extends Controller
{
    public function __invoke(Request $request, SystemHealth $health): JsonResponse
    {
        $expected = (string) config('commerciale-ai.operations.healthcheck_token');
        $provided = (string) $request->header('X-Daria-Health-Token');
        abort_if($expected === '' || ! hash_equals($expected, $provided), 404);
        $snapshot = $health->snapshot();

        return response()->json([
            'status' => $snapshot['ready'] ? 'ok' : 'error',
            'checked_at' => now()->toIso8601String(),
            'checks' => collect($snapshot['checks'])->map(fn (array $check): array => [
                'key' => $check['key'],
                'status' => $check['status'],
            ])->values(),
        ], $snapshot['ready'] ? 200 : 503);
    }
}
