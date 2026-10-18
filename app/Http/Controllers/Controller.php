<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Flash a toast message to the next Inertia response.
     */
    protected function success(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    /**
     * Flash an error toast to the next Inertia response.
     */
    protected function error(string $message): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);
    }
}
