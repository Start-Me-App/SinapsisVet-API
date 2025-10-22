<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckFileLimits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'file:check-limits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica los límites de configuración para subida de archivos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Verificación de Límites de Archivo ===');
        
        // Verificar límites de PHP
        $this->info("\n📋 Límites de PHP:");
        $this->table(
            ['Configuración', 'Valor'],
            [
                ['upload_max_filesize', ini_get('upload_max_filesize')],
                ['post_max_size', ini_get('post_max_size')],
                ['max_execution_time', ini_get('max_execution_time') . ' segundos'],
                ['memory_limit', ini_get('memory_limit')],
                ['max_file_uploads', ini_get('max_file_uploads') . ' archivos'],
            ]
        );
        
        // Verificar límites de Nginx (si está disponible)
        $this->info("\n🌐 Límites de Nginx:");
        $nginxConfig = file_get_contents(base_path('../docker/nginx/nginx.conf'));
        if (preg_match('/client_max_body_size\s+(\d+[KMG]?);/', $nginxConfig, $matches)) {
            $this->info("client_max_body_size: " . $matches[1]);
        } else {
            $this->warn("No se pudo encontrar client_max_body_size en la configuración de Nginx");
        }
        
        // Calcular límite efectivo
        $uploadMax = $this->parseSize(ini_get('upload_max_filesize'));
        $postMax = $this->parseSize(ini_get('post_max_size'));
        $effectiveLimit = min($uploadMax, $postMax);
        
        $this->info("\n✅ Límite efectivo de archivo: " . $this->formatBytes($effectiveLimit));
        
        // Recomendaciones
        $this->info("\n💡 Recomendaciones:");
        if ($effectiveLimit < 50 * 1024 * 1024) { // Menos de 50MB
            $this->warn("- El límite actual es bajo para archivos grandes");
            $this->info("- Considera aumentar upload_max_filesize y post_max_size a 100M o más");
        }
        
        if (ini_get('max_execution_time') < 300) {
            $this->warn("- El tiempo de ejecución es bajo para archivos grandes");
            $this->info("- Considera aumentar max_execution_time a 300 segundos o más");
        }
        
        if ($this->parseSize(ini_get('memory_limit')) < 512 * 1024 * 1024) { // Menos de 512MB
            $this->warn("- El límite de memoria es bajo para archivos grandes");
            $this->info("- Considera aumentar memory_limit a 512M o más");
        }
        
        return 0;
    }
    
    /**
     * Convierte un string de tamaño a bytes
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
    
    /**
     * Formatea bytes a formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
