<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditChanges
{
    public function handle(Request $request, Closure $next): Response
    {
        $actorId = $request->user()?->id;
        $response = $next($request);
        if (! $request->isMethodSafe()) {
            $ids = [];
            foreach ($request->route()?->parameters() ?? [] as $name => $value) {
                if ($value instanceof Model) {
                    $ids[$name.'_id'] = $value->getKey();
                }
            }
            try {
                Log::channel('audit')->info('web.action', [
                    'actor_id' => $actorId ?? $request->user()?->id,
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'response_status' => $response->getStatusCode(),
                    'records' => $ids,
                ]);
            } catch (Throwable) {
                // A logging outage must not undo a completed business operation.
            }
        }

        return $response;
    }
}
