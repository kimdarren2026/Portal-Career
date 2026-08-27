<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * `auth` is UI-only: which nav shell to render and whether a "Lamar
     * Sekarang" or "Masuk" call-to-action shows on a public page. It never
     * gates a mutation — every write endpoint re-derives and re-checks
     * authorization server-side regardless of what this prop says.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => (int) $user->getKey(),
                    'name' => $user->name,
                ],
                'roles' => $user === null ? [] : $user->activeUserRoles()->with('role')->get()->pluck('role.code')->values()->all(),
            ],
        ];
    }
}
