<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddRequestContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incomingRequestId = $request->headers->get('X-Request-ID');
        $requestId = is_string($incomingRequestId) && Str::isUuid($incomingRequestId)
            ? $incomingRequestId
            : (string) Str::uuid();

        Log::flushSharedContext();
        Log::shareContext(array_filter([
            'request_id' => $requestId,
            'user_id' => $request->user()?->getAuthIdentifier(),
        ], static fn (mixed $value): bool => $value !== null));

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
