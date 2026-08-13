<?php


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{

    public function toArray(Request $request): array
    {

        $roles = $this->getRoleNames()->values()->toArray();
        /**
         * Rol principal
         *
         * Si el usuario tiene varios roles, se utiliza
         * el primero como rol principal.
         */
        $role = $roles[0] ?? null;

        return [
            'id' => $this->id,
            'rut' => $this->rut,
            'rut_formateado' => $this->rut_formateado,
            'name' => $this->name,
            'apellido_ap' => $this->apellido_ap,
            'apellido_mat' => $this->apellido_mat,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'tipo_contratacion' => $this->tipo_contratacion,
            'tipo_contratacion_nombre' => $this->contratacion?->nombre,
            'grado_id' => $this->grado_id,
            'grado' => $this->grado?->descripcion,
            'area_id' => $this->area_id,
            // 'area' => $this->area?->nombre,
            'role' => $role,
            'roles' => $roles,
            'predio_id' => $this->predio_id,
            'predio' => $this->predio
                ? [
                    'id' => $this->predio->id,
                    'nombre' => $this->predio->nombre,
                ]
                : null,
        ];
    }
}
