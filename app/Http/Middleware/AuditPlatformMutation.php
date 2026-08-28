<?php

namespace App\Http\Middleware;

use App\Models\PlatformAuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditPlatformMutation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethodSafe() || $response->getStatusCode() >= 400 || ! $request->user()?->isPlatformAdmin()) {
            return $response;
        }

        $parameters = collect($request->route()?->parameters() ?? [])->map(function ($value) {
            return $value instanceof Model ? (string) $value->getKey() : (is_scalar($value) ? (string) $value : null);
        })->filter()->all();
        $subjectType = array_key_first($parameters);

        try {
            PlatformAuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => $request->route()?->getName() ?: $request->path(),
                'method' => $request->method(),
                'subject_type' => $subjectType,
                'subject_id' => $subjectType ? $parameters[$subjectType] : null,
                'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
                'metadata' => [
                    'route_parameters' => $parameters,
                    'changed_fields' => collect($request->except(['_token', '_method', 'password', 'password_confirmation', 'current_password']))->keys()->all(),
                    'status_code' => $response->getStatusCode(),
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }
}
