<?php

declare(strict_types=1);

namespace App\Support;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class UploadServer
{

    /**
     * Sube una imagen al almacenamiento público.
     *
     * @param UploadedFile $image
     * @param string $folder
     * @return string URL de la imagen subida
     */
    public static function uploadImage(UploadedFile $image, string $folder = 'images'): string
    {
        // Almacena la imagen en el disco 'public' dentro de la carpeta especificada
        $path = $image->store($folder, 'public');

        // Retorna la URL pública del archivo subido
        return Storage::url($path);

    }



    /**
     * Sube un archivo al almacenamiento público.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string URL del archivo subido
     * @throws \Exception
     */
    public static function uploadFile(UploadedFile $file, string $folder = 'images'): string
    {
        // Validar que el archivo existe y es válido
        if (!$file || !$file->isValid()) {
            throw new \Exception('El archivo no es válido o no se pudo leer');
        }
        
        // Validar que el archivo no esté vacío
        if ($file->getSize() === 0) {
            throw new \Exception('El archivo está vacío');
        }
        
        // Crear el directorio si no existe
        $disk = Storage::disk('public');
        if (!$disk->exists($folder)) {
            $disk->makeDirectory($folder);
        }
        
        // Almacena el archivo en el disco 'public' dentro de la carpeta especificada
        $path = $file->store($folder, 'public');
        
        if (!$path) {
            throw new \Exception('No se pudo almacenar el archivo');
        }

        // Retorna la URL pública del archivo subido
        return Storage::url($path);
    }



   /**
     * Obtiene la URL de una imagen almacenada.
     *
     * @param string $filePath
     * @return string URL pública de la imagen
     */
    public static function getImageUrl(string $filePath): ?string
    {
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::url($filePath);
        }

        return null; // O puedes lanzar una excepción si prefieres manejarlo así
    }

    public static function validateImage(UploadedFile $image): bool
    {
        return $image->getClientMimeType() === 'image/jpeg' || $image->getClientMimeType() === 'image/png' || $image->getClientMimeType() === 'image/jpg';
    }
}
