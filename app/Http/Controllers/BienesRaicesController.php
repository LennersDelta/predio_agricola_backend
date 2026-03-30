<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class BienesRaicesController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Query base con todos los JOINs */
    private function queryBase()
    {
        return DB::table('propiedades as p')
            ->where('p.estado_propiedad_id', '!=', 2)
            ->where('p.es_borrador', false)
            ->leftJoin('tipo_propiedad as tp',   'p.tipo_propiedad_id',   '=', 'tp.id')
            ->leftJoin('estado_propiedad as ep',  'p.estado_propiedad_id', '=', 'ep.id')
            ->leftJoin('regiones as r',           'p.region_id',           '=', 'r.id')
            ->leftJoin('provincias as pr',        'p.provincia_id',        '=', 'pr.id')
            ->leftJoin('comunas as c',            'p.comuna_id',           '=', 'c.id')
            ->leftJoin('conservador as con',      'p.conservador_id',      '=', 'con.id')
            ->leftJoin('administrador as adm',    'p.administrador_id',    '=', 'adm.id')
            ->leftJoin('uso as u',                'p.uso_id',              '=', 'u.id')
            ->select([
                'p.id', 'p.uuid', 'p.carpeta', 'p.nombre_conjunto', 'p.rol_avaluo',
                'p.tasacion_comercial', 'p.avaluo_fiscal_terreno', 'p.avaluo_fiscal_construccion',
                'p.avaluo_fiscal_total', 'p.superficie', 'p.metros_construidos',
                'p.fojas', 'p.numero_inscripcion', 'p.ano_registro', 'p.direccion', 'p.numero_rol_sii', 'p.observaciones',
                'p.casa', 'p.departamentos', 'p.cabana', 'p.centro_recreacional',
                'p.refugio', 'p.casino', 'p.oficina', 'p.fundo',
                'p.agricola', 'p.bodega', 'p.sitio_eriazo',
                'p.latitud', 'p.longitud', 'p.created_at', 'p.es_borrador',
                DB::raw("json_build_object('id', tp.id,  'descripcion', tp.descripcion)  as tipo_propiedad"),
                DB::raw("json_build_object('id', ep.id,  'descripcion', ep.descripcion)  as estado_propiedad"),
                DB::raw("json_build_object('id', r.id,   'descripcion', r.descripcion)   as region"),
                DB::raw("json_build_object('id', pr.id,  'descripcion', pr.descripcion)  as provincia"),
                DB::raw("json_build_object('id', c.id,   'descripcion', c.descripcion)   as comuna"),
                DB::raw("json_build_object('id', con.id, 'descripcion', con.descripcion) as conservador"),
                DB::raw("json_build_object('id', adm.id, 'descripcion', adm.descripcion) as administrador"),
                DB::raw("json_build_object('id', u.id,   'descripcion', u.descripcion)   as uso"),
            ]);
    }

    /** Decodificar relaciones JSON */
    private function decodeRelaciones($bien): object
    {
        foreach (['tipo_propiedad','estado_propiedad','region','provincia','comuna','conservador','administrador','uso'] as $rel) {
            if (isset($bien->$rel) && is_string($bien->$rel)) {
                $bien->$rel = json_decode($bien->$rel);
            }
        }
        return $bien;
    }

    /** Cargar documentos de una propiedad */
    private function cargarDocumentos(int $propiedadId): array
    {
        return DB::table('propiedad_documento as pd')
            ->leftJoin('tipo_documento as td', 'pd.tipo_documento_id', '=', 'td.id')
            ->leftJoin('estado_documento as ed', 'pd.estado_documento_id', '=', 'ed.id')
            ->where('pd.propiedad_id', $propiedadId)
            ->select([
                'pd.id', 'pd.uuid', 'pd.nombre_original', 'pd.nombre_archivo',
                'pd.mime_type', 'pd.ruta', 'pd.peso',
                'pd.tipo_documento_id', 'pd.estado_documento_id',
                'pd.created_at',
                DB::raw("COALESCE(td.descripcion, td.label, '') as tipo_descripcion"),
                DB::raw("COALESCE(ed.descripcion, '') as estado_descripcion"),
            ])
            ->orderBy('pd.created_at')
            ->get()
            ->map(fn($d) => [
                'id'              => $d->id,
                'uuid'            => $d->uuid,
                'nombre_original' => $d->nombre_original,
                'nombre_archivo'  => $d->nombre_archivo,
                'mime_type'       => $d->mime_type,
                'peso'            => $d->peso,
                'tipo_documento_id' => $d->tipo_documento_id,
                'tipo'            => $d->tipo_descripcion,
                'estado'          => $d->estado_descripcion,
                'url'             => $d->ruta ? Storage::url($d->ruta) : null,
                'created_at'      => $d->created_at,
            ])
            ->toArray();
    }

    // ── LISTADO PARA SELECT (vivienda padre) ────────────────────────────────────
    public function listadoSelect()
    {
        $bienes = DB::table('propiedades as p')
            ->where('p.estado_propiedad_id', '!=', 2)
            ->where('p.es_borrador', false)
            ->leftJoin('tipo_propiedad as tp',  'p.tipo_propiedad_id',  '=', 'tp.id')
            ->leftJoin('estado_propiedad as ep', 'p.estado_propiedad_id','=', 'ep.id')
            ->leftJoin('regiones as r',          'p.region_id',          '=', 'r.id')
            ->leftJoin('provincias as pr',        'p.provincia_id',       '=', 'pr.id')
            ->leftJoin('comunas as c',            'p.comuna_id',          '=', 'c.id')
            ->leftJoin('conservador as con',      'p.conservador_id',     '=', 'con.id')
            ->select([
                'p.id', 'p.uuid', 'p.carpeta', 'p.nombre_conjunto', 'p.rol_avaluo',
                'p.fojas', 'p.numero', 'p.ano_registro',
                'p.direccion', 'p.decreto_destinacion',
                'p.superficie', 'p.metros_construidos',
                'p.tasacion_comercial',
                'p.avaluo_fiscal_terreno', 'p.avaluo_fiscal_construccion', 'p.avaluo_fiscal_total',
                'p.observaciones',
                DB::raw("COALESCE(tp.descripcion,  '') as tipo_propiedad"),
                DB::raw("COALESCE(ep.descripcion,  '') as estado_propiedad"),
                DB::raw("COALESCE(r.descripcion,   '') as region"),
                DB::raw("COALESCE(pr.descripcion,  '') as provincia"),
                DB::raw("COALESCE(c.descripcion,   '') as comuna"),
                DB::raw("COALESCE(con.descripcion, '') as conservador"),
            ])
            ->orderBy('p.carpeta')
            ->get();

        return response()->json(['data' => $bienes]);
    }

    // ── INDEX BORRADORES ─────────────────────────────────────────────────────
    public function indexBorradores()
    {
        $bienes = DB::table('propiedades as p')
            ->where('p.es_borrador', true)
            ->where('p.estado_propiedad_id', '!=', 2)
            ->leftJoin('tipo_propiedad as tp',   'p.tipo_propiedad_id',   '=', 'tp.id')
            ->leftJoin('estado_propiedad as ep',  'p.estado_propiedad_id', '=', 'ep.id')
            ->leftJoin('regiones as r',           'p.region_id',           '=', 'r.id')
            ->leftJoin('comunas as c',            'p.comuna_id',           '=', 'c.id')
            ->select([
                'p.id', 'p.uuid', 'p.carpeta', 'p.nombre_conjunto',
                'p.rol_avaluo', 'p.direccion', 'p.created_at', 'p.updated_at',
                'p.es_borrador', 'p.user_id',
                DB::raw("COALESCE(tp.descripcion,'—') as tipo_propiedad"),
                DB::raw("COALESCE(ep.descripcion,'—') as estado_propiedad"),
                DB::raw("COALESCE(r.descripcion,'—')  as region"),
                DB::raw("COALESCE(c.descripcion,'—')  as comuna"),
            ])
            ->orderByDesc('p.updated_at')
            ->get();

        return response()->json(['data' => $bienes]);
    }

    // ── INDEX ────────────────────────────────────────────────────────────────
    public function index()
    {
        $bienes = $this->queryBase()
            ->orderByDesc('p.created_at')
            ->get()
            ->map(fn($b) => $this->decodeRelaciones($b));

        return response()->json(['data' => $bienes]);
    }

    // ── SHOW (por ID) ────────────────────────────────────────────────────────
    public function show($id)
    {
        $bien = $this->queryBase()->where('p.id', $id)->first();
        if (!$bien) return response()->json(['message' => 'Registro no encontrado.'], 404);

        $bien = $this->decodeRelaciones($bien);
        $bien->documentos = $this->cargarDocumentos($bien->id);

        LogService::registrar(LogService::ACCION_VER, LogService::MODULO_BIENES, $bien->id);

        return response()->json(['data' => $bien]);
    }

    // ── SHOW BY UUID ─────────────────────────────────────────────────────────
    public function showByUuid($uuid)
    {
        // Sin filtro es_borrador — permite cargar tanto normales como borradores
        $bien = DB::table('propiedades as p')
            ->where('p.uuid', $uuid)
            ->where('p.estado_propiedad_id', '!=', 2)
            ->leftJoin('tipo_propiedad as tp',   'p.tipo_propiedad_id',   '=', 'tp.id')
            ->leftJoin('estado_propiedad as ep',  'p.estado_propiedad_id', '=', 'ep.id')
            ->leftJoin('regiones as r',           'p.region_id',           '=', 'r.id')
            ->leftJoin('provincias as pr',        'p.provincia_id',        '=', 'pr.id')
            ->leftJoin('comunas as c',            'p.comuna_id',           '=', 'c.id')
            ->leftJoin('conservador as con',      'p.conservador_id',      '=', 'con.id')
            ->leftJoin('administrador as adm',    'p.administrador_id',    '=', 'adm.id')
            ->leftJoin('uso as u',                'p.uso_id',              '=', 'u.id')
            ->select([
                'p.id', 'p.uuid', 'p.carpeta', 'p.nombre_conjunto', 'p.rol_avaluo',
                'p.tasacion_comercial', 'p.avaluo_fiscal_terreno', 'p.avaluo_fiscal_construccion',
                'p.avaluo_fiscal_total', 'p.superficie', 'p.metros_construidos',
                'p.fojas', 'p.numero_inscripcion', 'p.ano_registro', 'p.direccion',
                'p.numero_rol_sii', 'p.observaciones',
                'p.casa', 'p.departamentos', 'p.cabana', 'p.centro_recreacional',
                'p.refugio', 'p.casino', 'p.oficina', 'p.fundo',
                'p.agricola', 'p.bodega', 'p.sitio_eriazo',
                'p.latitud', 'p.longitud', 'p.created_at', 'p.es_borrador',
                DB::raw("json_build_object('id', tp.id,  'descripcion', tp.descripcion)  as tipo_propiedad"),
                DB::raw("json_build_object('id', ep.id,  'descripcion', ep.descripcion)  as estado_propiedad"),
                DB::raw("json_build_object('id', r.id,   'descripcion', r.descripcion)   as region"),
                DB::raw("json_build_object('id', pr.id,  'descripcion', pr.descripcion)  as provincia"),
                DB::raw("json_build_object('id', c.id,   'descripcion', c.descripcion)   as comuna"),
                DB::raw("json_build_object('id', con.id, 'descripcion', con.descripcion) as conservador"),
                DB::raw("json_build_object('id', adm.id, 'descripcion', adm.descripcion) as administrador"),
                DB::raw("json_build_object('id', u.id,   'descripcion', u.descripcion)   as uso"),
            ])
            ->first();

        if (!$bien) return response()->json(['message' => 'Registro no encontrado.'], 404);

        $bien = $this->decodeRelaciones($bien);
        $bien->documentos = $this->cargarDocumentos($bien->id);

        LogService::registrar(LogService::ACCION_VER, LogService::MODULO_BIENES, $bien->id);

        return response()->json(['data' => $bien]);
    }

    // ── GRABAR (crear) ───────────────────────────────────────────────────────
    public function grabar(Request $request)
    {
        $esBorrador = $request->boolean('es_borrador', false);

        // Si es borrador solo se requiere el N° de carpeta
        $rules = $esBorrador ? [
            'numero_carpeta' => ['required', 'string', 'max:20'],
        ] : [
            'numero_carpeta'     => ['required', 'string', 'max:20'],
            'nombre_conjunto'    => ['nullable', 'string', 'max:150'],
            'rol_avaluo'         => ['required', 'string', 'max:50'],
            'tipo_vivienda'      => ['required', 'integer', 'exists:tipo_propiedad,id'],
            'estado_vivienda'    => ['required', 'integer', 'exists:estado_propiedad,id'],
            'region_id'          => ['required', 'integer', 'exists:regiones,id'],
            'provincia_id'       => ['required', 'integer', 'exists:provincias,id'],
            'comuna_id'          => ['required', 'integer', 'exists:comunas,id'],
            'direccion'          => ['required', 'string', 'max:255'],
            'latitud'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'           => ['nullable', 'numeric', 'between:-180,180'],
            'numero_rol_sii'     => ['required', 'string', 'max:50'],
            'avaluo_terreno'     => ['required', 'numeric', 'min:0'],
            'avaluo_construccion'=> ['required', 'numeric', 'min:0'],
            'tasacion_comercial' => ['required', 'numeric', 'min:0'],
            'superficie_terreno' => ['required', 'numeric', 'min:0'],
            'metros_construidos' => ['required', 'numeric', 'min:0'],
            'inscrito_fojas'     => ['required', 'string', 'max:50'],
            'numero_inscripcion' => ['required', 'string', 'max:50'],
            'anio_registrado'    => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'conservador'        => ['required', 'integer', 'exists:conservador,id'],
            'observaciones'      => ['nullable', 'string', 'max:1000'],
            'documentos'                     => ['nullable', 'array'],
            'documentos.*.archivo'           => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'documentos.*.tipo_documento_id' => ['nullable', 'integer', 'exists:tipo_documento,id'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'numero_carpeta.required'      => 'Debe ingresar nombre de la carpeta.',
            'numero_carpeta.max'           => 'La carpeta no puede superar los 20 caracteres.',
            'rol_avaluo.required'          => 'El Rol de Avalúo es obligatorio.',
            'tipo_vivienda.required'       => 'Debe seleccionar un tipo de vivienda.',
            'estado_vivienda.required'     => 'Debe seleccionar un estado.',
            'region_id.required'           => 'Debe seleccionar una región.',
            'provincia_id.required'        => 'Debe seleccionar una provincia.',
            'comuna_id.required'           => 'Debe seleccionar una comuna.',
            'direccion.required'           => 'La dirección es obligatoria.',
            'numero_rol_sii.required'      => 'El Número Rol SII es obligatorio.',
            'avaluo_terreno.required'      => 'El Avalúo Fiscal Terreno es obligatorio.',
            'avaluo_construccion.required' => 'El Avalúo Fiscal Construcción es obligatorio.',
            'tasacion_comercial.required'  => 'La Tasación Comercial es obligatoria.',
            'superficie_terreno.required'  => 'La Superficie de Terreno es obligatoria.',
            'metros_construidos.required'  => 'Los Metros Construidos son obligatorios.',
            'inscrito_fojas.required'      => 'Las Fojas son obligatorias.',
            'numero_inscripcion.required'  => 'El N° de Inscripción es obligatorio.',
            'anio_registrado.required'     => 'El Año de Registro es obligatorio.',
            'conservador.required'         => 'Debe seleccionar un Conservador.',
            'documentos.*.archivo.max'     => 'Cada documento no puede superar los 10 MB.',
            'documentos.*.archivo.mimes'   => 'Solo se permiten PDF, JPG, PNG, DOC o DOCX.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $bien_id = DB::table('propiedades')->insertGetId([
                'uuid'                       => Str::uuid(),
                'carpeta'                    => $request->numero_carpeta,
                'nombre_conjunto'            => $request->nombre_conjunto,
                'rol_avaluo'                 => $request->rol_avaluo,
                'tipo_propiedad_id'          => $request->tipo_vivienda,
                'estado_propiedad_id'        => $request->estado_vivienda,
                'tasacion_comercial'         => $request->tasacion_comercial,
                'region_id'                  => $request->region_id,
                'provincia_id'               => $request->provincia_id,
                'comuna_id'                  => $request->comuna_id,
                'direccion'                  => $request->direccion,
                'avaluo_fiscal_terreno'      => $request->avaluo_terreno,
                'avaluo_fiscal_construccion' => $request->avaluo_construccion,
                'avaluo_fiscal_total'        => $request->avaluo_total,
                'superficie'                 => $request->superficie_terreno,
                'metros_construidos'         => $request->metros_construidos,
                'fojas'                      => $request->inscrito_fojas,
                'numero_inscripcion'         => $request->numero_inscripcion,
                'numero_rol_sii'             => $request->numero_rol_sii,
                'ano_registro'               => $request->anio_registrado,
                'conservador_id'             => $request->conservador,
                'administrador_id'           => $request->administrador_id ?: null,
                'uso_id'                     => $request->uso_id ?: null,
                'observaciones'              => $request->observaciones,
                'casa'                => (int) ($request->casa                ?? 0),
                'departamentos'       => (int) ($request->departamentos       ?? 0),
                'cabana'              => (int) ($request->cabana              ?? 0),
                'centro_recreacional' => (int) ($request->centro_recreacional ?? 0),
                'refugio'             => (int) ($request->refugio             ?? 0),
                'casino'              => (int) ($request->casino              ?? 0),
                'oficina'             => (int) ($request->oficina             ?? 0),
                'fundo'               => (int) ($request->fundo               ?? 0),
                'agricola'            => (int) ($request->agricola            ?? 0),
                'bodega'              => (int) ($request->bodega              ?? 0),
                'sitio_eriazo'        => (int) ($request->sitio_eriazo        ?? 0),

                'latitud'                    => $request->latitud ?: null,
                'longitud'                   => $request->longitud ?: null,
                'es_borrador'                => $esBorrador,
                'user_id'                    => auth()->id(),
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);

            // ── Documentos ────────────────────────────────────────────────
            if ($request->has('documentos') && is_array($request->documentos)) {
                foreach ($request->documentos as $doc) {
                    if (empty($doc['archivo'])) continue;

                    $file  = $doc['archivo'];
                    $ruta  = $file->store("propiedades/{$bien_id}/documentos", 'public');
                    $uuid  = Str::uuid();

                    DB::table('propiedad_documento')->insert([
                        'uuid'               => $uuid,
                        'propiedad_id'       => $bien_id,
                        'tipo_documento_id'  => $doc['tipo_documento_id'] ?? null,
                        'estado_documento_id'=> 1, // activo por defecto
                        'user_id'            => auth()->id(),
                        'nombre_original'    => $file->getClientOriginalName(),
                        'nombre_archivo'     => basename($ruta),
                        'mime_type'          => $file->getMimeType(),
                        'ruta'               => $ruta,
                        'peso'               => $file->getSize(),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }

            DB::commit();

            LogService::registrar(
                LogService::ACCION_CREAR,
                LogService::MODULO_BIENES,
                $bien_id
            );

            return response()->json([
                'message' => 'Propiedad registrada correctamente.',
                'id'      => $bien_id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar el registro.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ── COMPLETAR BORRADOR ───────────────────────────────────────────────────
    public function completar(Request $request, $id)
    {
        $bien = DB::table('propiedades')->where('id', $id)->where('es_borrador', true)->first();
        if (!$bien) return response()->json(['message' => 'Borrador no encontrado.'], 404);

        return $this->updateInterno($request, $id, false);
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        return $this->updateInterno($request, $id, $request->boolean('es_borrador', false));
    }

    private function updateInterno(Request $request, $id, bool $esBorrador)
    {
        $bien = DB::table('propiedades')->where('id', $id)->first();
        if (!$bien) return response()->json(['message' => 'Registro no encontrado.'], 404);

        // Si guarda como borrador, solo validar carpeta
        $rules = $esBorrador ? [
            'numero_carpeta' => ['required', 'string', 'max:20'],
        ] : [
            'numero_carpeta'     => ['required', 'string', 'max:20'],
            'nombre_conjunto'    => ['nullable', 'string', 'max:150'],
            'rol_avaluo'         => ['required', 'string', 'max:50'],
            'tipo_vivienda'      => ['required', 'integer', 'exists:tipo_propiedad,id'],
            'estado_vivienda'    => ['required', 'integer', 'exists:estado_propiedad,id'],
            'region_id'          => ['required', 'integer', 'exists:regiones,id'],
            'provincia_id'       => ['required', 'integer', 'exists:provincias,id'],
            'comuna_id'          => ['required', 'integer', 'exists:comunas,id'],
            'direccion'          => ['required', 'string', 'max:255'],
            'latitud'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'           => ['nullable', 'numeric', 'between:-180,180'],
            'numero_rol_sii'     => ['required', 'string', 'max:50'],
            'avaluo_terreno'     => ['required', 'numeric', 'min:0'],
            'avaluo_construccion'=> ['required', 'numeric', 'min:0'],
            'tasacion_comercial' => ['required', 'numeric', 'min:0'],
            'superficie_terreno' => ['required', 'numeric', 'min:0'],
            'metros_construidos' => ['required', 'numeric', 'min:0'],
            'inscrito_fojas'     => ['required', 'string', 'max:50'],
            'numero_inscripcion' => ['required', 'string', 'max:50'],
            'anio_registrado'    => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'conservador'        => ['required', 'integer', 'exists:conservador,id'],
            'observaciones'      => ['nullable', 'string', 'max:1000'],
            'documentos'                     => ['nullable', 'array'],
            'documentos.*.archivo'           => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'documentos.*.tipo_documento_id' => ['nullable', 'integer', 'exists:tipo_documento,id'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'numero_carpeta.required'      => 'Debe ingresar nombre de la carpeta.',
            'rol_avaluo.required'          => 'El Rol de Avalúo es obligatorio.',
            'tipo_vivienda.required'       => 'Debe seleccionar un tipo de vivienda.',
            'estado_vivienda.required'     => 'Debe seleccionar un estado.',
            'region_id.required'           => 'Debe seleccionar una región.',
            'provincia_id.required'        => 'Debe seleccionar una provincia.',
            'comuna_id.required'           => 'Debe seleccionar una comuna.',
            'direccion.required'           => 'La dirección es obligatoria.',
            'numero_rol_sii.required'      => 'El Número Rol SII es obligatorio.',
            'avaluo_terreno.required'      => 'El Avalúo Fiscal Terreno es obligatorio.',
            'avaluo_construccion.required' => 'El Avalúo Fiscal Construcción es obligatorio.',
            'tasacion_comercial.required'  => 'La Tasación Comercial es obligatoria.',
            'superficie_terreno.required'  => 'La Superficie de Terreno es obligatoria.',
            'metros_construidos.required'  => 'Los Metros Construidos son obligatorios.',
            'inscrito_fojas.required'      => 'Las Fojas son obligatorias.',
            'numero_inscripcion.required'  => 'El N° de Inscripción es obligatorio.',
            'anio_registrado.required'     => 'El Año de Registro es obligatorio.',
            'conservador.required'         => 'Debe seleccionar un Conservador.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Capturar estado anterior ANTES del update para el log
        $oldData = LogService::capturarAntes(LogService::MODULO_BIENES, $id);

        DB::beginTransaction();
        try {
            DB::table('propiedades')->where('id', $id)->update([
                'carpeta'                    => $request->numero_carpeta,
                'nombre_conjunto'            => $request->nombre_conjunto,
                'rol_avaluo'                 => $request->rol_avaluo,
                'tipo_propiedad_id'          => $request->tipo_vivienda,
                'estado_propiedad_id'        => $request->estado_vivienda,
                'tasacion_comercial'         => $request->tasacion_comercial,
                'region_id'                  => $request->region_id,
                'provincia_id'               => $request->provincia_id,
                'comuna_id'                  => $request->comuna_id,
                'direccion'                  => $request->direccion,
                'avaluo_fiscal_terreno'      => $request->avaluo_terreno,
                'avaluo_fiscal_construccion' => $request->avaluo_construccion,
                'avaluo_fiscal_total'        => $request->avaluo_total,
                'superficie'                 => $request->superficie_terreno,
                'metros_construidos'         => $request->metros_construidos,
                'fojas'                      => $request->inscrito_fojas,
                'numero_inscripcion'         => $request->numero_inscripcion,
                'numero_rol_sii'             => $request->numero_rol_sii,
                'ano_registro'               => $request->anio_registrado,
                'conservador_id'             => $request->conservador,
                'administrador_id'           => $request->administrador_id ?: null,
                'uso_id'                     => $request->uso_id ?: null,
                'observaciones'              => $request->observaciones,
                'casa'                => (int) ($request->casa                ?? 0),
                'departamentos'       => (int) ($request->departamentos       ?? 0),
                'cabana'              => (int) ($request->cabana              ?? 0),
                'centro_recreacional' => (int) ($request->centro_recreacional ?? 0),
                'refugio'             => (int) ($request->refugio             ?? 0),
                'casino'              => (int) ($request->casino              ?? 0),
                'oficina'             => (int) ($request->oficina             ?? 0),
                'fundo'               => (int) ($request->fundo               ?? 0),
                'agricola'            => (int) ($request->agricola            ?? 0),
                'bodega'              => (int) ($request->bodega              ?? 0),
                'sitio_eriazo'        => (int) ($request->sitio_eriazo        ?? 0),
                'latitud'                    => $request->latitud ?: null,
                'longitud'                   => $request->longitud ?: null,
                'es_borrador'                => $esBorrador,
                'updated_at'                 => now(),
            ]);

            // ── Agregar documentos nuevos (no elimina los existentes) ──────
            if ($request->has('documentos') && is_array($request->documentos)) {
                foreach ($request->documentos as $doc) {
                    if (empty($doc['archivo'])) continue;

                    $file = $doc['archivo'];
                    $ruta = $file->store("propiedades/{$id}/documentos", 'public');

                    DB::table('propiedad_documento')->insert([
                        'uuid'               => Str::uuid(),
                        'propiedad_id'       => $id,
                        'tipo_documento_id'  => $doc['tipo_documento_id'] ?? null,
                        'estado_documento_id'=> 1,
                        'user_id'            => auth()->id(),
                        'nombre_original'    => $file->getClientOriginalName(),
                        'nombre_archivo'     => basename($ruta),
                        'mime_type'          => $file->getMimeType(),
                        'ruta'               => $ruta,
                        'peso'               => $file->getSize(),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }

            DB::commit();

            // old_data = antes del update, new_data = después (capturado automáticamente)
            LogService::registrarEdicion(LogService::MODULO_BIENES, $id, $oldData);

            return response()->json([
                'message' => 'Propiedad actualizada correctamente.',
                'id'      => $id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el registro.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ── DESTROY ──────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        $bien = DB::table('propiedades')->where('id', $id)->first();
        if (!$bien) return response()->json(['message' => 'Registro no encontrado.'], 404);

        DB::beginTransaction();
        try {
            // Eliminar archivos físicos y registros
            $docs = DB::table('propiedad_documento')->where('propiedad_id', $id)->get();
            foreach ($docs as $doc) {
                if ($doc->ruta && Storage::disk('public')->exists($doc->ruta)) {
                    Storage::disk('public')->delete($doc->ruta);
                }
            }
            // Capturar old_data ANTES de eliminar
            LogService::registrar(LogService::ACCION_ELIMINAR, LogService::MODULO_BIENES, $id);

            DB::table('propiedad_documento')->where('propiedad_id', $id)->delete();
            DB::table('propiedades')->where('id', $id)->delete();

            DB::commit();

            return response()->json(['message' => 'Propiedad eliminada correctamente.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar el registro.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ── VER DOCUMENTO (registra log) ────────────────────────────────────────
    public function verDocumento($uuid)
    {
        $doc = DB::table('propiedad_documento')->where('uuid', $uuid)->first();
        if (!$doc) return response()->json(['message' => 'Documento no encontrado.'], 404);

        LogService::registrar(LogService::ACCION_VER, LogService::MODULO_BIENES, $doc->propiedad_id);

        return response()->json(['ok' => true]);
    }

    // ── DESCARGAR DOCUMENTO (registra log) ───────────────────────────────────
    public function descargarDocumento($uuid)
    {
        $doc = DB::table('propiedad_documento')->where('uuid', $uuid)->first();
        if (!$doc) return response()->json(['message' => 'Documento no encontrado.'], 404);

        LogService::registrar(LogService::ACCION_DESCARGAR, LogService::MODULO_BIENES, $doc->propiedad_id);

        return response()->json(['ok' => true]);
    }

    // ── ELIMINAR DOCUMENTO ───────────────────────────────────────────────────
    public function destroyDocumento($uuid)
    {
        $doc = DB::table('propiedad_documento')->where('uuid', $uuid)->first();
        if (!$doc) return response()->json(['message' => 'Documento no encontrado.'], 404);

        try {
            if ($doc->ruta && Storage::disk('public')->exists($doc->ruta)) {
                Storage::disk('public')->delete($doc->ruta);
            }
            // Capturar old_data ANTES de eliminar
            LogService::registrar(LogService::ACCION_ELIMINAR, LogService::MODULO_DOCUMENTOS, $doc->id);

            DB::table('propiedad_documento')->where('uuid', $uuid)->delete();

            return response()->json(['message' => 'Documento eliminado correctamente.']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el documento.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}