<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class MongoResyncCommand extends Command
{
    protected $signature = 'mongo:resync {model? : Nombre de modelo específico (Opcional)}';
    protected $description = 'Re-sincroniza datos completos hacia Mongo (idempotente)';

    public function handle(): int
    {
        if (env('MONGO_SYNC_DISABLED', false)) {
            $this->warn('Sync deshabilitado por MONGO_SYNC_DISABLED');
            return self::SUCCESS;
        }

        $targets = [
            \App\Models\Usuario::class,
            \App\Models\Hijo::class,
            \App\Models\Chofer::class,
            \App\Models\Unidad::class,
            \App\Models\Ruta::class,
            \App\Models\Admin::class,
            \App\Models\CodigoSeguridad::class,
            \App\Models\Sesion::class,
            \App\Models\SolicitudImpresionQr::class,
            \Laravel\Sanctum\PersonalAccessToken::class,
        ];

        $specific = $this->argument('model');
        if ($specific) {
            $targets = array_filter($targets, fn($m) => str_ends_with($m, $specific) || $m === $specific);
            if (empty($targets)) {
                $this->error('Modelo no encontrado en lista permitida');
                return self::INVALID;
            }
        }

        $bar = $this->output->createProgressBar();
        foreach ($targets as $class) {
            $this->line("\nResync: $class");
            $class::chunk(500, function($chunk) use ($bar, $class) {
                foreach ($chunk as $model) {
                    if (method_exists($model, 'toMongoDocument')) {
                        // Modelo con MongoSyncable
                        $observer = app(\App\Support\MongoSync\MongoSyncObserver::class);
                        $observer->created($model);
                    } elseif ($class === \Laravel\Sanctum\PersonalAccessToken::class) {
                        // Caso especial: PersonalAccessToken de Sanctum
                        $this->syncPersonalAccessToken($model);
                    }
                }
                $bar->advance(count($chunk));
            });
        }
        $bar->finish();
        $this->newLine();
        $this->info('Resync completado');
        return self::SUCCESS;
    }

    private function syncPersonalAccessToken($token)
    {
        if (env('MONGO_SYNC_DISABLED', false)) {
            return;
        }

        try {
            $dsn = config('database.connections.mongodb.dsn');
            $db = config('database.connections.mongodb.database');
            $client = new \MongoDB\Client($dsn, config('database.connections.mongodb.options') ?? []);
            
            $doc = $token->attributesToArray();
            // Incluir el campo token hasheado que está hidden
            $doc['token'] = $token->token;
            $id = $token->getKey();
            
            $client->selectCollection($db, 'personal_access_tokens')
                ->updateOne(['_id' => $id], ['$set' => $doc], ['upsert' => true]);
        } catch (\Throwable $e) {
            $this->warn('Error syncing token ' . $token->getKey() . ': ' . $e->getMessage());
        }
    }
}
