<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Auth\Muser;
use Symfony\Component\HttpFoundation\Response;

class EnforceCompanyLifecycle
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->input('user_id') ?? $request->query('user_id') ?? $request->input('id');
        $email = strtolower(trim((string) ($request->input('email') ?? $request->query('email') ?? '')));

        $user = null;
        if ($userId) {
            $user = Muser::where('nid', $userId)->first();
        } elseif (!empty($email)) {
            $user = Muser::where('cemail', $email)->first();
        } elseif ($request->user()) {
            $user = $request->user();
        }

        if ($user) {
            $isInactive = $user->isCompanyInactive();
            $isDeletionPending = $user->isDeletionPending();

            if ($isInactive || $isDeletionPending) {
                // Check if requested path is whitelisted
                if ($this->isWhitelistedPath($request)) {
                    return $next($request);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Akun perusahaan sedang dinonaktifkan. Silakan hubungi Owner perusahaan Anda.',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Check if request matches whitelisted routes
     */
    private function isWhitelistedPath(Request $request): bool
    {
        $whitelistedPatterns = [
            'api/company/status',
            'company/status',
            'api/company/reactivate',
            'company/reactivate',
            'api/company/cancel-deletion',
            'company/cancel-deletion',
            'api/me',
            'me',
            'api/login',
            'login',
            'api/register',
            'register',
            'api/verify-email',
            'verify-email',
            'api/resend-verification',
            'resend-verification',
        ];

        $path = trim($request->path(), '/');

        foreach ($whitelistedPatterns as $pattern) {
            if ($path === trim($pattern, '/')) {
                return true;
            }
        }

        return false;
    }
}
