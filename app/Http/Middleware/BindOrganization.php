<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\OrganizationContext;
use App\Support\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasRole(Role::SuperAdmin)) {
            return $next($request);
        }

        if ($user->organization_id === null && $user->isKetua()) {
            Organization::provision($user);
        }

        abort_if($user->organization_id === null, 403, 'Akun belum terhubung ke tempat belajar.');

        OrganizationContext::set((int) $user->organization_id);

        try {
            return $next($request);
        } finally {
            OrganizationContext::forget();
        }
    }
}
