<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class Csrf extends PreventRequestForgery
{
    // Require a session token even with same-origin fetch metadata, including tests.
    protected function hasValidOrigin($request)
    {
        return false;
    }

    protected function runningUnitTests()
    {
        return false;
    }
}
