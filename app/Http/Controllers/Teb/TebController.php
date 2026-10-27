<?php
// app/Http/Controllers/Teb/TebController.php

namespace App\Http\Controllers\Teb;

use App\Http\Controllers\Controller;
use App\Services\Teb\TebPresenter;
use App\Services\Teb\TebUser;
use Inertia\Inertia;

abstract class TebController extends Controller
{
    // Every TEB page gets the viewer's role and the shared lookups.
    protected function render(string $page, array $props = [])
    {
        $user = TebUser::current();

        return Inertia::render("Teb/{$page}", $props + [
            'teb' => [
                'me'      => $user?->id,
                'isAdmin' => TebUser::isAdmin($user),
                'options' => TebPresenter::options(),
                // Path of the app home (/{APP_NAME}); every TEB link is built from it.
                'base'    => rtrim((string) parse_url(route('dashboard'), PHP_URL_PATH), '/'),
                'logout'  => \Illuminate\Support\Facades\Route::has('logout') ? route('logout') : null,
            ],
        ]);
    }
}
