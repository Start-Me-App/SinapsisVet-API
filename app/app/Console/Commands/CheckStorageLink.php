<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckStorageLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:check-link';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica y crea el enlace simbólico del storage si no existe';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $publicPath = public_path('storage');
        $storagePath = storage_path('app/public');

        // Verificar si el enlace simbólico ya existe
        if (is_link($publicPath)) {
            $this->info('El enlace simbólico del storage ya existe.');
            return 0;
        }

        // Verificar si el directorio público existe
        if (!File::exists($publicPath)) {
            $this->error('El directorio público no existe.');
            return 1;
        }

        // Verificar si el directorio de storage existe
        if (!File::exists($storagePath)) {
            $this->error('El directorio de storage no existe.');
            return 1;
        }

        // Crear el enlace simbólico
        try {
            symlink($storagePath, $publicPath);
            $this->info('Enlace simbólico del storage creado exitosamente.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error al crear el enlace simbólico: ' . $e->getMessage());
            return 1;
        }
    }
}
