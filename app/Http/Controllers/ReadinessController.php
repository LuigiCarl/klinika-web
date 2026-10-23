<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReadinessController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            if (config('queue.default') === 'database') {
                $jobsTable = (string) config('queue.connections.database.table', 'jobs');
                $failedJobsTable = (string) config('queue.failed.table', 'failed_jobs');

                if (! Schema::hasTable($jobsTable) || ! Schema::hasTable($failedJobsTable)) {
                    throw new \RuntimeException('Database queue storage is unavailable.');
                }
            }

            return response()->json([
                'status' => 'ready',
                'checks' => ['database' => 'ok', 'queue' => 'ok'],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Readiness check failed.', [
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'status' => 'not_ready',
                'checks' => ['database' => 'unavailable', 'queue' => 'unavailable'],
            ], 503);
        }
    }
}
