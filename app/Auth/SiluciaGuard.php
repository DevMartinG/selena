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

        $data = $response->json();
        if (!($data['success'] ?? false)) return false;

        // 2. Resolver/crear usuario local
        $user = $this->resolveUser($data['user'], $credentials['password']);
        if (!$user) return false;


        // 3. Sincronizar roles desde API personal
        $this->syncRolesFromApi($user, $data['user']['dni']);


        // 4. SessionGuard maneja la sesión automáticamente
        $this->login($user, $remember);

        return true;
    }

    protected function resolveUser(array $apiUser, string $password): ?User
    {
        \Log::info('datos resolveUser:', ['apiUser' => $apiUser]);

        $dni        = $apiUser['dni'];
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


    protected function syncRolesFromApi(User $user, string $dni): void
    {
        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.silucia.api_token'),
            ])
            ->get('https://sistemas.regionpuno.gob.pe/siluciav2-api/api/personal/lista', [
                'rowsPerPage' => 0,
                'flag'        => 'T',
                'dni'         => $dni,
            ]);

        \Log::info('Respuesta personal API:', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

    }


}