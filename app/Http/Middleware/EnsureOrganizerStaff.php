<?php

namespace App\Http\Middleware;

use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin pages: the signed-in user must belong to the organizer whose
 * subdomain this is. (Users are tenant-scoped too, so a user from another
 * organizer can't even be loaded here; this is a second lock on the door.)
 */
class EnsureOrganizerStaff
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->guest(route('admin.login'));
        }
        if ((int) $user->organizer_id !== Tenancy::id()) {
            Auth::logout();
            abort(403);
        }
        if ($role === 'owner' && $user->role !== 'owner') {
            abort(403, 'Only the account owner can change this.');
        }

        return $next($request);
    }
}
