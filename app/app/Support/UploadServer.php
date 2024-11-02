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
     * @param UploadedFile $image
     * @param string $folder
     * @return string URL de la imagen subida
     */
    public static function uploadFile(UploadedFile $file, string $folder = 'images'): string
    {
        // Almacena el archivo en el disco 'public' dentro de la carpeta especificada
        $path = $file->store($folder, 'public');

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
}
