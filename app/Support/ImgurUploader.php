<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImgurUploader
{
    /**
     * Subir una imagen a Imgur
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @return array ['success' => bool, 'url' => string|null, 'error' => string|null]
     */
    public static function upload($file)
    {
        try {
            // Obtener el Client ID de Imgur desde .env
            $clientId = env('IMGUR_CLIENT_ID', '546c25a59c58ad7');
            
            // Convertir la imagen a base64
            $imageData = base64_encode(file_get_contents($file->getRealPath()));
            
            // Hacer la petición a la API de Imgur
            $response = Http::withHeaders([
                'Authorization' => 'Client-ID ' . $clientId,
            ])->post('https://api.imgur.com/3/image', [
                'image' => $imageData,
                'type' => 'base64',
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']['link'])) {
                    Log::info('Imagen subida exitosamente a Imgur', [
                        'url' => $data['data']['link'],
                        'delete_hash' => $data['data']['deletehash'] ?? null
                    ]);
                    
                    return [
                        'success' => true,
                        'url' => $data['data']['link'],
                        'delete_hash' => $data['data']['deletehash'] ?? null
                    ];
                }
            }
            
            Log::error('Error al subir imagen a Imgur', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            
            return [
                'success' => false,
                'url' => null,
                'error' => 'Error al subir la imagen a Imgur'
            ];
            
        } catch (\Exception $e) {
            Log::error('Excepción al subir imagen a Imgur: ' . $e->getMessage());
            
            return [
                'success' => false,
                'url' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Eliminar una imagen de Imgur (requiere delete_hash)
     * 
     * @param string $deleteHash
     * @return bool
     */
    public static function delete($deleteHash)
    {
        try {
            $clientId = env('IMGUR_CLIENT_ID', '546c25a59c58ad7');
            
            $response = Http::withHeaders([
                'Authorization' => 'Client-ID ' . $clientId,
            ])->delete("https://api.imgur.com/3/image/{$deleteHash}");
            
            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('Error al eliminar imagen de Imgur: ' . $e->getMessage());
            return false;
        }
    }
}
