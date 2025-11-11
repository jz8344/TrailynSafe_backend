<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MongoDB\Client;

class MongoStatsCommand extends Command
{
    protected $signature = 'mongo:stats {collection? : Colección específica}';
    protected $description = 'Mostrar estadísticas de conteo entre PostgreSQL y MongoDB';

    public function handle(): int
    {
        if (env('MONGO_SYNC_DISABLED', false)) {
            $this->warn('Sync deshabilitado por MONGO_SYNC_DISABLED');
            return self::SUCCESS;
        }

        $models = [
            ['model' => \App\Models\Usuario::class, 'table' => 'usuarios', 'collection' => 'usuarios'],
            ['model' => \App\Models\Hijo::class, 'table' => 'hijos', 'collection' => 'hijos'],
            ['model' => \App\Models\Chofer::class, 'table' => 'choferes', 'collection' => 'choferes'],
            ['model' => \App\Models\Unidad::class, 'table' => 'unidades', 'collection' => 'unidades'],
            ['model' => \App\Models\Ruta::class, 'table' => 'rutas', 'collection' => 'rutas'],
            ['model' => \App\Models\Admin::class, 'table' => 'admins', 'collection' => 'admins'],
            ['model' => \App\Models\CodigoSeguridad::class, 'table' => 'codigo_seguridads', 'collection' => 'codigo_seguridads'],
            ['model' => \App\Models\Sesion::class, 'table' => 'sesiones', 'collection' => 'sesiones'],
            ['model' => \App\Models\SolicitudImpresionQr::class, 'table' => 'solicitudes_impresion_qr', 'collection' => 'solicitudes_impresion_qr'],
            ['model' => \Laravel\Sanctum\PersonalAccessToken::class, 'table' => 'personal_access_tokens', 'collection' => 'personal_access_tokens'],
        ];

        $specific = $this->argument('collection');
        if ($specific) {
            $models = array_filter($models, fn($m) => $m['collection'] === $specific || $m['table'] === $specific);
            if (empty($models)) {
                $this->error('Colección/tabla no encontrada');
                return self::INVALID;
            }
        }

        try {
            $dsn = config('database.connections.mongodb.dsn');
            $db = config('database.connections.mongodb.database');
            $client = new Client($dsn, config('database.connections.mongodb.options', []));
            $mongoDb = $client->selectDatabase($db);
        } catch (\Throwable $e) {
            $this->error('Error conectando a MongoDB: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->table(['Tabla/Colección', 'PostgreSQL', 'MongoDB', 'Diferencia'], array_map(function($item) use ($mongoDb) {
            $pgCount = $item['model']::count();
            try {
                $mongoCount = $mongoDb->selectCollection($item['collection'])->countDocuments();
            } catch (\Throwable $e) {
                $mongoCount = 'Error: ' . $e->getMessage();
            }
            $diff = is_numeric($mongoCount) ? ($pgCount - $mongoCount) : 'N/A';
            return [$item['table'], $pgCount, $mongoCount, $diff];
        }, $models));

        return self::SUCCESS;
    }
}
