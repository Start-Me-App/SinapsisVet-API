<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateFileSize
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el límite de tamaño de archivo desde la configuración
        $maxFileSize = $this->getMaxFileSize();
        
        // Verificar archivos en la request
        if ($request->hasFile('materials') || $request->hasFile('new_materials')) {
            $files = [];
            
            if ($request->hasFile('materials')) {
                $materials = $request->file('materials');
                if (is_array($materials)) {
                    $files = array_merge($files, $materials);
                } else {
                    $files[] = $materials;
                }
            }
            
            if ($request->hasFile('new_materials')) {
                $newMaterials = $request->file('new_materials');
                if (is_array($newMaterials)) {
                    $files = array_merge($files, $newMaterials);
                } else {
                    $files[] = $newMaterials;
                }
            }
            
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $fileSize = $file->getSize();
                    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                    
                    if ($fileSize > $maxFileSize) {
                        Log::warning("Archivo demasiado grande: {$file->getClientOriginalName()} ({$fileSizeMB}MB)");
                        return response()->json([
                            'error' => 'Archivo demasiado grande',
                            'message' => "El archivo '{$file->getClientOriginalName()}' excede el límite de tamaño permitido ({$fileSizeMB}MB > " . round($maxFileSize / 1024 / 1024, 2) . "MB)",
                            'max_size' => round($maxFileSize / 1024 / 1024, 2) . 'MB'
                        ], 413);
                    }
                }
            }
        }
        
        return $next($request);
    }
    
    /**
     * Obtiene el límite máximo de tamaño de archivo
     */
    private function getMaxFileSize(): int
    {
        // Obtener el límite de upload_max_filesize
        $uploadMaxFilesize = $this->parseSize(ini_get('upload_max_filesize'));
        
        // Obtener el límite de post_max_size
        $postMaxSize = $this->parseSize(ini_get('post_max_size'));
        
        // Retornar el menor de los dos
        return min($uploadMaxFilesize, $postMaxSize);
    }
    
    /**
     * Convierte un string de tamaño (ej: "100M") a bytes
     */
    private function parseSize(string $size): int
    {
        $size = strtolower(trim($size));
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;
        
        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }
        
        return $size;
    }
}
