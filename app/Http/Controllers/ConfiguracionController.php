<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ConfiguracionController extends Controller
{
    private array $tablasPermitidas = [
        'estados',
    ];

    private function validarTabla(string $tabla): void
    {
        if (!in_array($tabla, $this->tablasPermitidas)) {

            abort(response()->json([
                'message' => 'Tabla no permitida.'
            ], 400));
        }
    }

    // ─────────────────────────────────────────────
    // LISTAR
    // ─────────────────────────────────────────────
    public function index(string $tabla): JsonResponse
    {
        try {

            $this->validarTabla($tabla);

            if ($tabla === 'estados') {

                $data = DB::table('estados')
                    ->select(
                        'id',
                        'tipo',
                        'nombre as descripcion'
                    )
                    ->orderBy('tipo')
                    ->orderBy('nombre')
                    ->get()
                    ->map(function ($item) {

                        $item->activo = true;

                        return $item;
                    });
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener registros.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // OBTENER TIPOS ÚNICOS
    // ─────────────────────────────────────────────
    public function tiposEstado(): JsonResponse
    {
        try {

            $tipos = DB::table('estados')
                ->select('tipo')
                ->distinct()
                ->whereNotNull('tipo')
                ->orderBy('tipo')
                ->pluck('tipo');

            return response()->json([
                'success' => true,
                'data' => $tipos
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────
    public function store(
        Request $request,
        string $tabla
    ): JsonResponse {

        try {

            $this->validarTabla($tabla);

            $request->validate([
                'tipo' => 'required|string|max:100',
                'descripcion' => 'required|string|max:100',
            ]);

            if ($tabla === 'estados') {

                $id = DB::table('estados')
                    ->insertGetId([
                        'tipo' => strtoupper(trim($request->tipo)),
                        'nombre' => trim($request->descripcion),
                        'fecha_creacion' => now(),
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registro creado.',
                'id' => $id
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al crear.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // ACTUALIZAR
    // ─────────────────────────────────────────────
    public function update(
        Request $request,
        string $tabla,
        int $id
    ): JsonResponse {

        try {

            $this->validarTabla($tabla);

            $request->validate([
                'tipo' => 'required|string|max:100',
                'descripcion' => 'required|string|max:100',
            ]);

            if ($tabla === 'estados') {

                DB::table('estados')
                    ->where('id', $id)
                    ->update([
                        'tipo' => strtoupper(trim($request->tipo)),
                        'nombre' => trim($request->descripcion),
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado.'
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // ELIMINAR
    // ─────────────────────────────────────────────
    public function destroy(
        string $tabla,
        int $id
    ): JsonResponse {

        try {

            $this->validarTabla($tabla);

            if ($tabla === 'estados') {

                $existe = DB::table('estados')
                    ->where('id', $id)
                    ->exists();

                if (!$existe) {

                    return response()->json([
                        'success' => false,
                        'message' => 'Registro no encontrado.'
                    ], 404);
                }

                DB::table('estados')
                    ->where('id', $id)
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado.'
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}