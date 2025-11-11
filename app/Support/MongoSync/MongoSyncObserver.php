<?php
namespace App\Support\MongoSync;

use App\Contracts\MongoSyncable;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Client;

class MongoSyncObserver
{
    protected Client $client;
    protected string $db;
    protected bool $disabled;
    protected bool $queue;

    public function __construct()
    {
        $dsn = config('database.connections.mongodb.dsn');
        $this->db = config('database.connections.mongodb.database');
        $this->disabled = (bool) env('MONGO_SYNC_DISABLED', false);
        $this->queue = (bool) env('MONGO_SYNC_QUEUE', false);
        $this->client = new Client($dsn, config('database.connections.mongodb.options') ?? []);
    }

    public function created(Model $model): void { $this->upsert($model); }
    public function updated(Model $model): void { $this->upsert($model); }
    public function deleted(Model $model): void { $this->delete($model); }
    public function restored(Model $model): void { $this->upsert($model); }

    protected function upsert(Model $model): void
    {
        if ($this->disabled) { return; }
        
        // Caso especial para PersonalAccessToken de Sanctum
        if ($model instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $this->upsertPersonalAccessToken($model);
            return;
        }
        
        if (!($model instanceof MongoSyncable)) { return; }
        
        $doc = $model->toMongoDocument();
        $id = $model->mongoDocumentId();
        try {
            $this->client->selectCollection($this->db, $model->mongoCollection())
                ->updateOne(['_id' => $id], ['$set' => $doc], ['upsert' => true]);
        } catch (\Throwable $e) {
            Log::error('Mongo sync upsert failed', [ 'model' => get_class($model), 'id' => $id, 'err' => $e->getMessage() ]);
        }
    }

    protected function delete(Model $model): void
    {
        if ($this->disabled) { return; }
        
        // Caso especial para PersonalAccessToken de Sanctum
        if ($model instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $this->deletePersonalAccessToken($model);
            return;
        }
        
        if (!($model instanceof MongoSyncable)) { return; }
        
        $id = $model->mongoDocumentId();
        try {
            $this->client->selectCollection($this->db, $model->mongoCollection())
                ->deleteOne(['_id' => $id]);
        } catch (\Throwable $e) {
            Log::error('Mongo sync delete failed', [ 'model' => get_class($model), 'id' => $id, 'err' => $e->getMessage() ]);
        }
    }

    protected function upsertPersonalAccessToken($model): void
    {
        $doc = $model->attributesToArray();
        
        // Incluir el campo token hasheado que está hidden por defecto
        $doc['token'] = $model->token;
        
        $id = $model->getKey();
        try {
            $this->client->selectCollection($this->db, 'personal_access_tokens')
                ->updateOne(['_id' => $id], ['$set' => $doc], ['upsert' => true]);
        } catch (\Throwable $e) {
            Log::error('Mongo sync PersonalAccessToken upsert failed', [ 'id' => $id, 'err' => $e->getMessage() ]);
        }
    }

    protected function deletePersonalAccessToken($model): void
    {
        $id = $model->getKey();
        try {
            $this->client->selectCollection($this->db, 'personal_access_tokens')
                ->deleteOne(['_id' => $id]);
        } catch (\Throwable $e) {
            Log::error('Mongo sync PersonalAccessToken delete failed', [ 'id' => $id, 'err' => $e->getMessage() ]);
        }
    }
}
