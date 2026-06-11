<?php

namespace App\Http\Middleware;

use Inertia\Middleware;
use Illuminate\Http\Request;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
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
     * @see https://inertiajs.com/shared-data
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()?->load('roles'),
            ],
            'flash' => [
                'success'   => fn() => $request->session()->get('success'),
                'error'     => fn() => $request->session()->get('error'),
                'warning'   => fn() => $request->session()->get('warning'),
                'info'      => fn() => $request->session()->get('info'),
                'resultado' => fn() => $request->session()->get('resultado'),
            ],
        ]);
    }
}

