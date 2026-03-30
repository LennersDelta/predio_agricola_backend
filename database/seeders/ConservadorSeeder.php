<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConservadorSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['descripcion' => 'Conservador de Bienes Raices de Curicó'],
            ['descripcion' => 'Conservador de Bienes Raíces de Arica'],
            ['descripcion' => 'Conservador de Bienes Raíces de Buin'],
            ['descripcion' => 'Conservador de Bienes Raíces de Caldera'],
            ['descripcion' => 'Conservador de Bienes Raíces de Cauquenes'],
            ['descripcion' => 'Conservador de Bienes Raíces de Chile Chico'],
            ['descripcion' => 'Conservador de Bienes Raíces de Chillan'],
            ['descripcion' => 'Conservador de Bienes Raíces de Concepción'],
            ['descripcion' => 'Conservador de Bienes Raíces de Copiapó'],
            ['descripcion' => 'Conservador de Bienes Raíces de Coronel'],
            ['descripcion' => 'Conservador de Bienes Raíces de La Serera'],
            ['descripcion' => 'Conservador de Bienes Raíces de Los Vilos'],
            ['descripcion' => 'Conservador de Bienes Raíces de Peumo-Las Cabras'],
            ['descripcion' => 'Conservador de Bienes Raíces de Puente Alto'],
            ['descripcion' => 'Conservador de Bienes Raíces de Puerto Cisnes'],
            ['descripcion' => 'Conservador de Bienes Raíces de Puerto Montt'],
            ['descripcion' => 'Conservador de Bienes Raíces de Puerto Varas'],
            ['descripcion' => 'Conservador de Bienes Raíces de Quellón'],
            ['descripcion' => 'Conservador de Bienes Raíces de Rengo'],
            ['descripcion' => 'Conservador de Bienes Raíces de San Antonio'],
            ['descripcion' => 'Conservador de Bienes Raíces de San Carlos'],
            ['descripcion' => 'Conservador de Bienes Raíces de San Clemente'],
            ['descripcion' => 'Conservador de Bienes Raíces de San Fernando'],
            ['descripcion' => 'Conservador de Bienes Raíces de San Javier de Loncomilla'],
            ['descripcion' => 'Conservador de Bienes Raíces de Santiago'],
            ['descripcion' => 'Conservador de Bienes Raíces de Talcahuano'],
            ['descripcion' => 'Conservador de Bienes Raíces de Tomé'],
            ['descripcion' => 'Conservador de Bienes Raíces de Valdivia'],
            ['descripcion' => 'Conservador de Bienes Raíces de Villarrica'],
            ['descripcion' => 'Conservador de Bienes Raíces de Viña del Mar'],
            ['descripcion' => 'Conservador de Bienes Raíces de Yumbel'],
            ['descripcion' => 'Conservador de Bienes Raíces y Comercio de Osorno'],
            ['descripcion' => 'Conservador de Pirque'],
        ];

        foreach ($data as &$item) {
            $item['created_at'] = Carbon::now();
        }

        DB::table('conservador')->insert($data);
    }
}
