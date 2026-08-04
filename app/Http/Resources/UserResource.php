<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            //'area' => $this->area?->nombre,

            'role' => $this->getRoleNames()->first() ?? 'usuario',
            'roles' => $this->getRoleNames(),
        ];
    }
}
