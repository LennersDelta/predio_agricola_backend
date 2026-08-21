<?php
// app/Services/AutentificaticService.php
// ─────────────────────────────────────────────────────────────────────────────
// Servicio reutilizable para integrar AutentificaTic en cualquier plataforma.
// Solo cambiar AUTENTIFICATIC_URL en el .env de cada proyecto.
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AutentificaticService
{
    private Client $http;
    private string $baseUrl;

    public function __construct()
    {
        $this->http    = new Client(['verify' => false]);
        $this->baseUrl = env('AUTENTIFICATIC_URL', 'http://autentificaticapi.carabineros.cl');
    }

    // ── LOGIN — devuelve ['token' => string] o ['error' => string] ────────────
    public function login(string $rut, string $password): array
    {
        try {
            $res = $this->http->post($this->baseUrl . '/api/auth/login', [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Origin'       => env('APP_URL', 'http://172.21.200.54'),
                ],
                'form_params' => ['rut' => $rut, 'password' => $password],
            ]);
            $data = json_decode((string) $res->getBody(), true);
            return ['token' => $data['success']['access_token']];
        } catch (GuzzleException $e) {
            $body = method_exists($e, 'getResponse') && $e->getResponse()
                ? json_decode((string) $e->getResponse()->getBody(), true)
                : [];
            $msg = collect($body['errors'] ?? [])->flatten()->first()
                ?? 'Error al conectar con AutentificaTic.';
            return ['error' => $msg];
        }
    }

    // ── OBTENER USUARIO — requiere token válido ───────────────────────────────
    public function getUser(string $token): array
    {
        try {
            $res = $this->http->get($this->baseUrl . '/api/auth/user-full', [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'Origin'        => env('APP_URL', 'http://172.21.200.54'),
                ],
            ]);
            $data = json_decode((string) $res->getBody(), true);
            return $data['success']['user'] ?? [];
        } catch (GuzzleException) {
            return [];
        }
    }

    // ── LOGOUT ────────────────────────────────────────────────────────────────
    public function logout(string $token): void
    {
        try {
            $this->http->get($this->baseUrl . '/api/auth/logout', [
                'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token],
            ]);
        } catch (GuzzleException) { /* silencioso */
        }
    }

    public function registrarUsuario(string $rut, string $token): array
    {
        try {
            $response = $this->http->request('POST', $this->baseUrl . '/api/institutional-app-user-from-external-app', [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Bearer ' . $token,
                    'Origin'        => env('APP_URL', 'http://172.21.200.54'),
                ],
                'form_params' => ['rut' => $rut],
            ]);
            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            $body = method_exists($e, 'getResponse') && $e->getResponse()
                ? json_decode((string) $e->getResponse()->getBody(), true)
                : [];
            return ['error' => collect($body['errors'] ?? [])->flatten()->first() ?? 'Error al registrar.'];
        }
    }

    public function eliminarUsuario(string $rut, string $token): array
    {
        try {
            $response = $this->http->request('DELETE', $this->baseUrl . '/api/institutional-app-user-from-external-app', [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Bearer ' . $token,
                    'Origin'        => env('APP_URL', 'http://172.21.200.54'),
                ],
                'form_params' => ['rut' => $rut],
            ]);
            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            $body = method_exists($e, 'getResponse') && $e->getResponse()
                ? json_decode((string) $e->getResponse()->getBody(), true)
                : [];
            return ['error' => collect($body['errors'] ?? [])->flatten()->first() ?? 'Error al eliminar.'];
        }
    }
}
