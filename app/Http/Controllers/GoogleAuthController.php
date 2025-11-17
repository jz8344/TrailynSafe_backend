<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Google\Client as GoogleClient;

class GoogleAuthController extends Controller
{
    /**
     * Autenticación con Google ID Token
     * 
     * Este endpoint recibe el ID token de Google, lo valida
     * y crea o autentica al usuario en el sistema
     */
    public function loginWithGoogle(Request $request)
    {
        try {
            \Log::info('=== Inicio Google Auth ===');
            \Log::info('Request data', $request->all());
            
            $request->validate([
                'id_token' => 'required|string',
                'device_name' => 'string|nullable'
            ]);

            $idToken = $request->id_token;
            $deviceName = $request->device_name ?? 'android-device';
            
            \Log::info('ID Token recibido (primeros 50 chars): ' . substr($idToken, 0, 50) . '...');

            // Validar el token con Google
            $payload = $this->verifyGoogleToken($idToken);

            if (!$payload) {
                \Log::error('Token de Google inválido o no pudo ser verificado');
                return response()->json([
                    'success' => false,
                    'message' => 'Token de Google inválido'
                ], 401);
            }

            // Extraer información del usuario de Google
            $googleId = $payload['sub'] ?? null;
            $email = $payload['email'] ?? null;
            $name = $payload['name'] ?? '';
            $givenName = $payload['given_name'] ?? '';
            $familyName = $payload['family_name'] ?? '';
            $picture = $payload['picture'] ?? null;
            $emailVerified = $payload['email_verified'] ?? false;

            if (!$email || !$googleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el email del usuario'
                ], 400);
            }

            // Buscar o crear usuario
            $usuario = Usuario::where('correo', $email)->first();

            if (!$usuario) {
                // Crear nuevo usuario
                $usuario = Usuario::create([
                    'nombre' => $givenName ?: explode(' ', $name)[0],
                    'apellido' => $familyName ?: (explode(' ', $name)[1] ?? ''),
                    'correo' => $email,
                    'password' => Hash::make(Str::random(32)), // Password aleatorio
                    'telefono' => '',
                    'rol' => 'usuario',
                    'google_id' => $googleId,
                    'avatar' => $picture,
                    'email_verified' => $emailVerified,
                    'auth_provider' => 'google'
                ]);

                $isNewUser = true;
            } else {
                // Actualizar google_id si no lo tiene
                if (!$usuario->google_id) {
                    $usuario->google_id = $googleId;
                    $usuario->avatar = $usuario->avatar ?: $picture;
                    $usuario->auth_provider = 'google';
                    $usuario->email_verified = $emailVerified;
                    $usuario->save();
                }

                $isNewUser = false;
            }

            // Crear token de Sanctum
            $token = $usuario->createToken($deviceName)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => $isNewUser ? 'Usuario registrado exitosamente' : 'Inicio de sesión exitoso',
                'is_new_user' => $isNewUser,
                'data' => [
                    'usuario' => [
                        'id' => $usuario->id,
                        'nombre' => $usuario->nombre,
                        'apellido' => $usuario->apellido,
                        'correo' => $usuario->correo,
                        'telefono' => $usuario->telefono,
                        'rol' => $usuario->rol,
                        'avatar' => $usuario->avatar,
                        'google_id' => $usuario->google_id,
                        'email_verified' => $usuario->email_verified
                    ],
                    'token' => $token
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error en Google Auth', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            \Log::error('=== Error en Google Auth ===');
            \Log::error('Message: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la autenticación con Google',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Verificar el ID token de Google
     * 
     * @param string $idToken
     * @return array|false Payload del token o false si es inválido
     */
    private function verifyGoogleToken($idToken)
    {
        try {
            \Log::info('Verificando Google token...');
            
            // Método 1: Validación usando Google API Client (recomendado en producción)
            // Descomentar si instalas google/apiclient
            /*
            $client = new GoogleClient(['client_id' => env('GOOGLE_CLIENT_ID')]);
            $payload = $client->verifyIdToken($idToken);
            return $payload;
            */

            // Método 2: Validación manual usando endpoint de Google con cURL
            $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $idToken;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                \Log::error('cURL error al verificar token: ' . $curlError);
                return false;
            }
            
            if ($httpCode !== 200) {
                \Log::error('Google tokeninfo HTTP error: ' . $httpCode);
                \Log::error('Response: ' . $response);
                return false;
            }
            
            if (!$response) {
                \Log::error('Empty response from Google tokeninfo');
                return false;
            }

            $payload = json_decode($response, true);
            
            if (!$payload) {
                \Log::error('Failed to decode JSON response from Google');
                return false;
            }
            
            \Log::info('Token payload received', ['email' => $payload['email'] ?? 'N/A']);

            // Verificar que el token sea para tu aplicación
            $clientId = env('GOOGLE_CLIENT_ID');
            $webClientId = env('GOOGLE_WEB_CLIENT_ID');
            
            \Log::info('Verificando audience', [
                'token_aud' => $payload['aud'] ?? 'N/A',
                'expected_client_id' => $clientId,
                'expected_web_client_id' => $webClientId
            ]);
            
            if (isset($payload['aud'])) {
                if ($payload['aud'] === $clientId || $payload['aud'] === $webClientId) {
                    \Log::info('Token válido - audience match');
                    return $payload;
                }
            }
            
            \Log::error('Token audience no coincide con los Client IDs configurados');
            return false;

        } catch (\Exception $e) {
            \Log::error('Exception verificando Google token: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Desconectar cuenta de Google
     * Remueve el google_id del usuario
     */
    public function disconnectGoogle(Request $request)
    {
        try {
            $usuario = $request->user();

            if (!$usuario->google_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay cuenta de Google vinculada'
                ], 400);
            }

            // Verificar que el usuario tenga una contraseña antes de desconectar
            if ($usuario->auth_provider === 'google' && !$usuario->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe establecer una contraseña antes de desconectar Google'
                ], 400);
            }

            $usuario->google_id = null;
            $usuario->auth_provider = 'local';
            $usuario->save();

            return response()->json([
                'success' => true,
                'message' => 'Cuenta de Google desconectada exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al desconectar cuenta de Google',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
