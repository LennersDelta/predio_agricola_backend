<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    // ── GET /api/usuarios ────────────────────────────────────────────────────
    public function index(): AnonymousResourceCollection
    {
        $usuarios = User::with('roles')
            ->orderBy('apellido_ap')
            ->orderBy('apellido_mat')
            ->get();

        return UserResource::collection($usuarios);
    }

    // ── POST /api/usuarios ───────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rut'               => ['required', 'string', 'max:12', 'unique:users,rut'],
            'name'              => ['required', 'string', 'max:100'],
            'apellido_ap'       => ['required', 'string', 'max:100'],
            'apellido_mat'      => ['nullable', 'string', 'max:100'],
            'email'             => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'grado'             => ['nullable', 'string', 'max:100'],
            'tipo_contratacion' => ['nullable', 'string', 'max:30'],
            'telefono'          => ['nullable', 'string', 'max:15'],
            'area_id'           => ['nullable', 'integer'],
            'role'              => ['required', 'string', 'in:administrador,usuario,lector'],
            'password'          => ['required', 'confirmed', Password::min(8)],
        ], [
            'rut.required'          => 'El RUT es obligatorio.',
            'rut.unique'            => 'Este RUT ya está registrado en el sistema.',
            'name.required'         => 'El nombre es obligatorio.',
            'apellido_ap.required'  => 'El apellido paterno es obligatorio.',
            'email.email'           => 'El correo ingresado no es válido.',
            'email.unique'          => 'Este correo ya está registrado.',
            'role.required'         => 'Debe asignar un rol al usuario.',
            'role.in'               => 'El rol seleccionado no es válido.',
            'password.required'     => 'La contraseña es obligatoria.',
            'password.confirmed'    => 'Las contraseñas no coinciden.',
            'password.min'          => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $usuario = User::create([
            'rut'               => $data['rut'],
            'name'              => $data['name'],
            'apellido_ap'       => $data['apellido_ap'],
            'apellido_mat'      => $data['apellido_mat'] ?? null,
            'email'             => $data['email'] ?? null,
            'grado'             => $data['grado'] ?? null,
            'tipo_contratacion' => $data['tipo_contratacion'] ?? null,
            'telefono'          => $data['telefono'] ?? null,
            'area_id'           => $data['area_id'] ?? null,
            'password'          => Hash::make($data['password']),
        ]);

        $usuario->assignRole($data['role']);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data'    => new UserResource($usuario->load('roles')),
        ], 201);
    }

    // ── GET /api/usuarios/{id} ───────────────────────────────────────────────
    public function show(User $usuario): UserResource
    {
        return new UserResource($usuario->load('roles'));
    }

    // ── PUT /api/usuarios/{id} ───────────────────────────────────────────────
    public function update(Request $request, User $usuario): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'apellido_ap'       => ['required', 'string', 'max:100'],
            'apellido_mat'      => ['nullable', 'string', 'max:100'],
            'email'             => ['nullable', 'email', 'max:255', "unique:users,email,{$usuario->id}"],
            'grado'             => ['nullable', 'string', 'max:100'],
            'tipo_contratacion' => ['nullable', 'string', 'max:30'],
            'telefono'          => ['nullable', 'string', 'max:15'],
            'area_id'           => ['nullable', 'integer'],
            'role'              => ['required', 'string', 'in:administrador,usuario,lector'],
            'password'          => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'name.required'         => 'El nombre es obligatorio.',
            'apellido_ap.required'  => 'El apellido paterno es obligatorio.',
            'email.email'           => 'El correo ingresado no es válido.',
            'email.unique'          => 'Este correo ya está registrado por otro usuario.',
            'role.required'         => 'Debe asignar un rol al usuario.',
            'role.in'               => 'El rol seleccionado no es válido.',
            'password.confirmed'    => 'Las contraseñas no coinciden.',
            'password.min'          => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $usuario->update([
            'name'              => $data['name'],
            'apellido_ap'       => $data['apellido_ap'],
            'apellido_mat'      => $data['apellido_mat'] ?? null,
            'email'             => $data['email'] ?? null,
            'grado'             => $data['grado'] ?? null,
            'tipo_contratacion' => $data['tipo_contratacion'] ?? null,
            'telefono'          => $data['telefono'] ?? null,
            'area_id'           => $data['area_id'] ?? null,
            // Contraseña solo si viene
            ...( ! empty($data['password'])
                ? ['password' => Hash::make($data['password'])]
                : []
            ),
        ]);

        // Sincronizar rol
        $usuario->syncRoles([$data['role']]);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data'    => new UserResource($usuario->load('roles')),
        ]);
    }

    // ── DELETE /api/usuarios/{id} ────────────────────────────────────────────
    public function destroy(User $usuario): JsonResponse
    {
        // Prevenir auto-eliminación
        if ($usuario->id === auth()->id()) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta.',
            ], 403);
        }

        $usuario->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }
}