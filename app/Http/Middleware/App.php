<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class App
{


    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ( $request->is('api/*') ) {
            $lang = $request->header('locale') ?? 'en' ;
            app()->setLocale( $lang ) ;
        }
        return $next($request) ;
    }
}
