<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Redirects
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethod('get') && ! $request->isMethod('head')) {
            return $next($request);
        }$path = '/'.trim($request->path(), '/');
        if (preg_match('~^/(admin|api|media|storage)(/|$)~', $path)) {
            return $next($request);
        }$seen = [$path];
        $code = 301;
        $changed = false;
        for ($i = 0; $i < 20; $i++) {
            $r = DB::table('redirects')->where('old_path', $path)->where('enabled', true)->first();
            if (! $r) {
                break;
            }$target = '/'.trim($r->target_path, '/');
            if (! preg_match('~^/[a-z0-9/_-]*$~', $target) || preg_match('~^/(admin|api|media|storage)(/|$)~', $target) || in_array($target, $seen)) {
                return $next($request);
            }$seen[] = $target;
            $path = $target;
            $code = in_array((int) $r->status_code, [301, 302]) ? (int) $r->status_code : 301;
            $changed = true;
        }

        return $changed ? redirect($path, $code) : $next($request);
    }
}
