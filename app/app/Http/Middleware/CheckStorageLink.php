<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckStorageLink
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicPath = public_path('storage');
        $storagePath = storage_path('app/public');

        // Verificar si el enlace simbólico existe
        if (!is_link($publicPath)) {
            // Verificar si los directorios existen
            if (File::exists($publicPath) && File::exists($storagePath)) {
                try {
                    symlink($storagePath, $publicPath);
                } catch (\Exception $e) {
                    // Log del error pero no interrumpir la aplicación
                    Log::warning('No se pudo crear el enlace simbólico del storage: ' . $e->getMessage());
                }
            }
        }

        return $next($request);
    }
}
