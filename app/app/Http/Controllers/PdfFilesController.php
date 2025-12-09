<?php

namespace App\Http\Controllers;

use App\Models\PdfFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfFilesController extends Controller
{
    /**
     * Listar todos los PDFs disponibles.
     */
    public function index()
    {
        $list = PdfFile::orderByDesc('created_at')->get();

        return response()->json(['data' => $list], 200);
    }

    /**
     * Subir un nuevo PDF.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $file = $request->file('file');
        $storedName = uniqid('pdf_', true) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('pdfs', $storedName, 'public');

        $pdf = PdfFile::create([
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ]);

        return response()->json(['data' => $pdf, 'msg' => 'PDF subido correctamente'], 201);
    }

    /**
     * Eliminar un PDF existente.
     */
    public function destroy($pdfId)
    {
        $pdf = PdfFile::find($pdfId);

        if (!$pdf) {
            return response()->json(['error' => 'PDF no encontrado'], 404);
        }

        Storage::disk('public')->delete($pdf->path);
        $pdf->delete();

        return response()->json(['msg' => 'PDF eliminado correctamente'], 200);
    }
}

