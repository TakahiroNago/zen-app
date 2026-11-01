<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class RestrictImageSources
{
    /**
     * Limits image loading to this site so rendered content cannot load external tracking images.
     * Only `img-src` is set on purpose; other directives would affect existing inline scripts.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', 'img-src '.implode(' ', $this->allowedImageSources()));
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    private function allowedImageSources(): array
    {
        $sources = ["'self'", 'data:'];

        if (Vite::isRunningHot()) {
            $sources[] = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
        }

        return $sources;
    }
}
