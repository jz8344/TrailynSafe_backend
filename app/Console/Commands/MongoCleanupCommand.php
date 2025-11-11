<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MongoDB\Client;
use Laravel\Sanctum\PersonalAccessToken;

class MongoCleanupCommand extends Command
{
    protected $signature = 'mongo:cleanup {collection}';
    protected $description = 'Limpiar registros huérfanos en MongoDB que no existen en PostgreSQL';

    public function handle(): int
    {
        if (env('MONGO_SYNC_DISABLED', false)) {
            $this->warn('Sync deshabilitado por MONGO_SYNC_DISABLED');
            return self::SUCCESS;
        }

        $collection = $this->argument('collection');
        
        if ($collection === 'personal_access_tokens') {
            return $this->cleanupPersonalAccessTokens();
        } elseif ($collection === 'solicitudes_impresion_qr') {
            return $this->cleanupSolicitudesImpresion();
        } else {
            $this->error("Colección no soportada: {$collection}");
            return self::FAILURE;
        }
    }

    private function cleanupPersonalAccessTokens(): int
    {
        try {
            $dsn = config('database.connections.mongodb.dsn');
            $db = config('database.connections.mongodb.database');
            $client = new Client($dsn, config('database.connections.mongodb.options', []));
            $mongoCollection = $client->selectCollection($db, 'personal_access_tokens');
            
            // Obtener todos los IDs de PostgreSQL
            $pgIds = PersonalAccessToken::pluck('id')->toArray();
            
            // Obtener documentos de MongoDB que no están en PostgreSQL
            $orphans = $mongoCollection->find(['_id' => ['$nin' => $pgIds]]);
            
            $deleted = 0;
            foreach ($orphans as $orphan) {
                $this->line('Eliminando token huérfano: ' . $orphan['_id']);
                $mongoCollection->deleteOne(['_id' => $orphan['_id']]);
                $deleted++;
            }
            
            $this->info("Limpieza completada. Eliminados: {$deleted} registros huérfanos");
            return self::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error('Error en limpieza: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function cleanupSolicitudesImpresion(): int
    {
        try {
            $dsn = config('database.connections.mongodb.dsn');
            $db = config('database.connections.mongodb.database');
            $client = new Client($dsn, config('database.connections.mongodb.options', []));
            $mongoCollection = $client->selectCollection($db, 'solicitudes_impresion_qr');
            
            // Obtener todos los IDs de PostgreSQL
            $pgIds = \App\Models\SolicitudImpresionQr::pluck('id')->toArray();
            
            // Obtener documentos de MongoDB que no están en PostgreSQL
            $orphans = $mongoCollection->find(['_id' => ['$nin' => $pgIds]]);
            
            $deleted = 0;
            foreach ($orphans as $orphan) {
                $this->line('Eliminando solicitud huérfana: ' . $orphan['_id']);
                $mongoCollection->deleteOne(['_id' => $orphan['_id']]);
                $deleted++;
            }
            
            $this->info("Limpieza completada. Eliminados: {$deleted} registros huérfanos");
            return self::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error('Error en limpieza: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
