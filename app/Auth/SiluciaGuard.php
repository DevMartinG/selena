<?php
namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class SiluciaGuard extends SessionGuard
{
    public function attempt(array $credentials = [], $remember = false): bool
    {
        // \Log::info('SiluciaGuard::attempt ejecutado', ['login' => $credentials['login']]);

        $username = $credentials['login'];

        // 1. Llamada a API SILUCIA
        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.silucia.api_token'),
                'Content-Type'  => 'application/json',
            ])
            ->withBody(json_encode([
                'username' => $username,
                'password' => $credentials['password'],
            ]), 'application/json')
            ->post('https://sistemas.regionpuno.gob.pe/siluciav2-api/api/silucia-auth');


        if (!$response->successful()) return false;

        // \Log::info('Respeusta de silucia: ', ['response' => $response->json()]);

        $data = $response->json();
        if (!($data['success'] ?? false)) return false;

        // 2. Resolver/crear usuario local
        $user = $this->resolveUser($data['user'], $credentials['password']);
        if (!$user) return false;


        // 3. Sincronizar roles desde API personal TABLA 1
        $this->syncRolesFromApiTable1($user, $data['user']['dni'] ?? $data['user']['username'] ?? $data['user']['id']);
        
        
        // 4. Sincronizar roles desde API personal TABLA 2
        $this->syncMetasFromApiTable2($user, $data['user']['dni'] ?? $data['user']['username'] ?? $data['user']['id']);



        // 5. SessionGuard maneja la sesión automáticamente
        $this->login($user, $remember);

        return true;
    }

    protected function resolveUser(array $apiUser, string $password): ?User
    {
        // \Log::info('datos resolveUser:', ['apiUser' => $apiUser]);

        // $dni        = $apiUser['dni'];
        $dni = $apiUser['dni'] ?? $apiUser['username'] ?? $apiUser['id'];
        $nameParts  = explode(' ', trim($apiUser['name']));
        $totalParts = count($nameParts);
        $firstName  = implode(' ', array_slice($nameParts, 0, max(1, $totalParts - 2)));
        $lastName   = implode(' ', array_slice($nameParts, -2));

        $user = User::where('username', $dni)->first();

        if (!$user) {
            $user = User::create([
                'email'     => $dni . '@dayana.gob.pe',
                'username'  => $dni,
                'name'      => $firstName,
                'last_name' => $lastName,
                'nin'       => $dni,
                'password'  => Hash::make($password),
            ]);
            \Log::info('Usuario creado:', ['email' => $user->email]);

        } else {
            $user->update([
                'name'      => $firstName,
                'last_name' => $lastName,
                'password'  => Hash::make($password),
            ]);
            \Log::info('Usuario actualizado:', ['email' => $user->email]);
        }

        return $user;
    }


    protected function syncRolesFromApiTable1(User $user, string $dni, string $tableId = '01'): void
    {

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.silucia.api_token'),
            ])
            ->get('https://sistemas.regionpuno.gob.pe/siluciav2-api/api/personal/lista', [
                'rowsPerPage' => 0,
                'table_id'    => $tableId,
                'login'       => $dni,
            ]);

        $data     = $response->json();
        $personal = $data['data'][0] ?? null;

        if (!$personal) {
            \Log::warning('No se encontró personal en API TABLA 1:', ['login' => $dni]);
            return;
        }

        $rolNombre = $personal['rols']['desrol'] ?? null;

        \Log::warning('rolNombre en API TABLA 1:', ['rolNombre' => $rolNombre]);


        if (!$rolNombre) {
            \Log::warning('El personal no tiene rol asignado:', ['login' => $dni]);
            return;
        }

        // Proteger Admin y SuperAdmin
        if ($user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            \Log::info('Usuario protegido, rol no modificado:', ['user' => $user->email]);
            return;
        }

        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => $rolNombre, 'guard_name' => 'web']
        );

        $user->syncRoles([$role->name]);

        // \Log::info('Rol sincronizado:', ['user' => $user->email, 'rol' => $rolNombre]);
    }


    protected function syncMetasFromApiTable2(User $user, string $dni): void
    {

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.silucia.api_token'),
            ])
            ->get('https://sistemas.regionpuno.gob.pe/siluciav2-api/api/personal/lista', [
                'rowsPerPage' => 0,
                'flag' => 'T',
                'dni' => $dni,
            ]);

        $data = $response->json();
        $personal = $data['data'][0] ?? null;

        if (!$personal) {
            \Log::warning('No se encontró personal en API TABLA 2:', ['dni' => $dni]);
            return;
        }

        // -------- METAS --------

        $metasApi = $personal['metas'] ?? [];

        \Log::warning('metasApi en API TABLA 2:', ['metasApi' => $metasApi]);

        $metaIds = collect($metasApi)->map(function ($meta) {

            $metaModel = \App\Models\Meta::updateOrCreate(
                [
                    'codmeta' => $meta['codmeta'],
                    'anio' => $meta['anio'],
                ],
                [
                    'nombre' => $meta['nombre_corto'],
                    'desmeta' => $meta['desmeta'],
                    'cui' => $meta['prod_proy'],
                    'snapshot' => $meta
                ]
            );

            return $metaModel->id;

        })->toArray();

        $user->metas()->syncWithoutDetaching($metaIds);


    }




}