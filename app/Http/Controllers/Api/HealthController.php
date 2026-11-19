<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Veřejný /health: jen stav a obecná hláška, text výjimky (connection string, host, cesta) do odpovědi nejde,
 * ale do logu (#51). Kontrola úložiště zapisuje na privátní disk.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
        ];

        $allHealthy = ! in_array(false, array_column($checks, 'healthy'));

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'version' => 'v1',
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'message' => 'Connected',
                'response_time_ms' => $duration,
            ];
        } catch (Throwable $e) {
            report($e);

            return ['healthy' => false, 'message' => 'Connection failed'];
        }
    }

    private function checkStorage(): array
    {
        try {
            $testFile = 'health_check_'.time().'.tmp';
            Storage::disk(PrivateFile::DISK)->put($testFile, 'ok');
            Storage::disk(PrivateFile::DISK)->delete($testFile);

            return [
                'healthy' => true,
                'message' => 'Writable',
            ];
        } catch (Throwable $e) {
            report($e);

            return ['healthy' => false, 'message' => 'Not writable'];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_'.time();
            cache()->put($key, 'ok', 10);
            $value = cache()->get($key);
            cache()->forget($key);

            return [
                'healthy' => $value === 'ok',
                'message' => $value === 'ok' ? 'Working' : 'Read/write mismatch',
            ];
        } catch (Throwable $e) {
            report($e);

            return ['healthy' => false, 'message' => 'Failed'];
        }
    }
}
