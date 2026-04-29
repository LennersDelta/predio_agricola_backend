<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class PredioController extends Controller
{
    public function eliminarDocumento($uuid, Request $request)
    {
        $tipo = $request->input('tipo');
        $vehiculo = DB::table('parque_vehicular')
            ->where('uuid', $uuid)
            ->first();

        if (!$vehiculo) {
            return response()->json(['message' => 'Vehículo no encontrado'], 404);
        }

        // ─────────────────────────────
        // PERMISO CIRCULACIÓN
        // ─────────────────────────────
        if ($tipo == '1') {

            if ($vehiculo->permiso_circulacion_img) {
                $path = $vehiculo->permiso_circulacion_img;
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            DB::table('parque_vehicular')
                ->where('uuid', $uuid)
                ->update([
                    'permiso_circulacion_img' => null
                ]);
        }

        // ─────────────────────────────
        // SEGURO OBLIGATORIO
        // ─────────────────────────────
        if ($tipo == '2') {

            if ($vehiculo->seguro_obligatorio_img) {
                $path = $vehiculo->seguro_obligatorio_img;
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            DB::table('parque_vehicular')
                ->where('uuid', $uuid) 
                ->update([
                    'seguro_obligatorio_img' => null
                ]);
        }
        return response()->json(['message' => 'Documento eliminado correctamente']);
    }

    public function verDocumento($uuid, Request $request)
    {
        $tipo = $request->query('tipo');

        $vehiculo = DB::table('parque_vehicular')
            ->where('uuid', $uuid)
            ->first();

        if (!$vehiculo) {
            abort(404,'Vehículo no encontrado');
        }

        $path = null;

        if ($tipo == '1') {
            $path = $vehiculo->permiso_circulacion_img;
        }

        if ($tipo == '2') {
            $path = $vehiculo->seguro_obligatorio_img;
        }

        if (!$path) {
            abort(404,'Documento no existe');
        }

        if (!Storage::disk('public')->exists($path)) {
            abort(404,'Archivo no encontrado');
        }

        $fullPath = Storage::disk('public')->path($path);

        $mime = Storage::disk('public')->mimeType($path);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Word descarga
        if (in_array($extension, ['doc','docx'])) {
            return response()->download(
                $fullPath,
                basename($path),
                ['Content-Type' => $mime]
            );
        }

        // PDF / imágenes inline
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline'
        ]);
    }

    public function descargarDocumento($uuid, Request $request)
    {
        $tipo = $request->query('tipo');

        $vehiculo = DB::table('parque_vehicular')
            ->where('uuid', $uuid)
            ->first();

        if (!$vehiculo) {
            abort(404, 'Vehículo no encontrado');
        }

        $path = null;

        if ($tipo == '1') {
            $path = $vehiculo->permiso_circulacion_img;
        }

        if ($tipo == '2') {
            $path = $vehiculo->seguro_obligatorio_img;
        }

        if (!$path) {
            abort(404, 'Documento no existe');
        }

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        $fullPath = Storage::disk('public')->path($path);

        return response()->download(
            $fullPath,
            basename($path)
        );
    }
}