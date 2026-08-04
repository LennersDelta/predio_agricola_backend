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
use Illuminate\Support\Facades\DB;

class UsuarioController extends BaseController
{
    // ── GET /api/usuarios ────────────────────────────────────────────────────
    public function index(): AnonymousResourceCollection
    {
        $usuarios = User::with([
            'roles',
            'grado',
            'contratacion',
        ])
        ->orderBy('apellido_ap')
        ->orderBy('apellido_mat')
        ->get();

        return UserResource::collection($usuarios);
    }
    
    // ── POST /api/usuarios ───────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {

            $data = $request->validate([
                'rut'               => ['required', 'string', 'max:12', 'unique:users,rut'],
                'name'              => ['required', 'string', 'max:100'],
                'apellido_ap'       => ['required', 'string', 'max:100'],
                'apellido_mat'      => ['nullable', 'string', 'max:100'],
                'email'             => ['nullable', 'email', 'max:255', 'unique:users,email'],
                'grado_id'          => ['nullable', 'string', 'max:100'],
                'tipo_contratacion' => ['nullable', 'string', 'max:30'],
                'telefono'          => ['nullable', 'string', 'max:15'],
                'area_id'           => ['nullable', 'integer'],
                'role'              => ['required', 'string', 'exists:roles,name'],
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
                'grado_id'          => $data['grado_id'] ?? null,
                'tipo_contratacion' => $data['tipo_contratacion'] ?? null,
                'telefono'          => $data['telefono'] ?? null,
                'area_id'           => $data['area_id'] ?? null,
                'password'          => Hash::make($data['password']),
            ]);

            $usuario->assignRole($data['role']);

            // Obtener registro para auditoría
            $registro = DB::table('users')
                ->where('id', $usuario->id)
                ->first();

            if (!$registro) {
                throw new \Exception('No fue posible recuperar el usuario creado.');
            }

            // Agregar el rol al objeto auditado
            $registro->rol = $data['role'];

            $this->auditar(
                modulo: 'Usuarios',
                accion: 'CREAR',
                tabla: 'users',
                registroId: $usuario->id,
                descripcion: 'Se creó un nuevo usuario.',
                despues: $registro
            );

            DB::commit();

            return response()->json([
                'message' => 'Usuario creado correctamente.',
                'data'    => new UserResource($usuario->load('roles')),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el usuario.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ── GET /api/usuarios/{id} ───────────────────────────────────────────────
    /*public function show(User $usuario): UserResource
    {
        return new UserResource($usuario->load('roles'));
    }*/
    public function show(User $usuario): UserResource
    {
        return new UserResource(
            $usuario->load([
                'roles',
                'grado',
                'contratacion'
            ])
        );
    }

    // ── PUT /api/usuarios/{id} ───────────────────────────────────────────────
    public function update(Request $request, User $usuario): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'apellido_ap'        => ['required', 'string', 'max:100'],
            'apellido_mat'       => ['nullable', 'string', 'max:100'],
            'email'              => ['nullable', 'email', 'max:255', "unique:users,email,{$usuario->id}"],
            'grado_id'           => ['nullable', 'string', 'max:100'],
            'tipo_contratacion'  => ['nullable', 'string', 'max:30'],
            'telefono'           => ['nullable', 'string', 'max:15'],
            'area_id'            => ['nullable', 'integer'],
            'role'               => ['required', 'string', 'exists:roles,name'],
            'password'           => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'name.required'         => 'El nombre es obligatorio.',
            'apellido_ap.required'  => 'El apellido paterno es obligatorio.',
            'email.email'           => 'El correo ingresado no es válido.',
            'email.unique'          => 'Este correo ya está registrado por otro usuario.',
            'role.required'         => 'Debe asignar un rol al usuario.',
            'password.confirmed'    => 'Las contraseñas no coinciden.',
            'password.min'          => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        DB::beginTransaction();

        try {

            // Estado anterior
            $antes = DB::table('users')
                ->where('id', $usuario->id)
                ->first();

            $usuario->update([
                'name'              => $data['name'],
                'apellido_ap'       => $data['apellido_ap'],
                'apellido_mat'      => $data['apellido_mat'] ?? null,
                'email'             => $data['email'] ?? null,
                'grado_id'          => $data['grado_id'] ?? null,
                'tipo_contratacion' => $data['tipo_contratacion'] ?? null,
                'telefono'          => $data['telefono'] ?? null,
                'area_id'           => $data['area_id'] ?? null,

                ...( ! empty($data['password'])
                    ? ['password' => Hash::make($data['password'])]
                    : []
                ),
            ]);

            // Actualizar rol
            $usuario->syncRoles([$data['role']]);

            // Estado posterior
            $despues = DB::table('users')
                ->where('id', $usuario->id)
                ->first();

            // Agregar el rol para que quede registrado en la auditoría
            $despues->role = $data['role'];

            $this->auditar(
                modulo: 'Usuarios',
                accion: 'ACTUALIZAR',
                tabla: 'users',
                registroId: $usuario->id,
                descripcion: "Actualizó el usuario {$usuario->name}.",
                antes: $antes,
                despues: $despues
            );

            DB::commit();

            return response()->json([
                'message' => 'Usuario actualizado correctamente.',
                'data'    => new UserResource($usuario->load('roles')),
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar el usuario.',
                'error'   => $e->getMessage(),
            ], 500);
        }
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

        DB::beginTransaction();

        try {

            // Obtener registro antes de eliminar
            $antes = DB::table('users')
                ->where('id', $usuario->id)
                ->first();

            if (!$antes) {
                return response()->json([
                    'message' => 'El usuario no existe.',
                ], 404);
            }

            // Obtener rol para dejarlo registrado en la auditoría
            $antes->role = $usuario->getRoleNames()->first();

            // Auditoría
            $this->auditar(
                modulo: 'Usuarios',
                accion: 'ELIMINAR',
                tabla: 'users',
                registroId: $usuario->id,
                descripcion: "Eliminó el usuario {$usuario->name}.",
                antes: $antes
            );

            // Eliminar usuario
            $usuario->delete();

            DB::commit();

            return response()->json([
                'message' => 'Usuario eliminado correctamente.',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar el usuario.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}