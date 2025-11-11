<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class BackupController extends Controller
{
    /**
     * Mostrar la página de gestión de backups
     */
    public function index()
    {
        $backups = $this->getBackupsList();
        
        return response()->json([
            'success' => true,
            'backups' => $backups,
            'storage_info' => $this->getStorageInfo()
        ]);
    }
    
    /**
     * Crear un nuevo backup
     */
    public function create(Request $request)
    {
        $request->validate([
            'cleanup' => 'boolean',
            'retention_days' => 'integer|min:1|max:365'
        ]);
        
        try {
            $options = [];
            
            if ($request->get('cleanup', false)) {
                $options['--cleanup'] = true;
            }
            
            if ($request->has('retention_days')) {
                $options['--days'] = $request->get('retention_days', 7);
            }
            
            // Ejecutar comando de backup en background
            $exitCode = Artisan::call('mongo:backup', $options);
            
            if ($exitCode === 0) {
                $output = Artisan::output();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Backup creado exitosamente',
                    'output' => $output,
                    'backups' => $this->getBackupsList()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear el backup',
                    'output' => Artisan::output()
                ], 500);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar backup: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Descargar un backup
     */
    public function download($filename)
    {
        $backupPath = storage_path('app/backups/' . $filename);
        
        if (!file_exists($backupPath) || !str_contains($filename, 'trailynsafe_backup_')) {
            return response()->json([
                'success' => false,
                'message' => 'Backup no encontrado'
            ], 404);
        }
        
        return response()->download($backupPath);
    }
    
    /**
     * Eliminar un backup
     */
    public function delete($filename)
    {
        $backupPath = storage_path('app/backups/' . $filename);
        
        if (!file_exists($backupPath) || !str_contains($filename, 'trailynsafe_backup_')) {
            return response()->json([
                'success' => false,
                'message' => 'Backup no encontrado'
            ], 404);
        }
        
        try {
            unlink($backupPath);
            
            return response()->json([
                'success' => true,
                'message' => 'Backup eliminado exitosamente',
                'backups' => $this->getBackupsList()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar backup: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Limpiar backups antiguos
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365'
        ]);
        
        try {
            $days = $request->get('days');
            
            $exitCode = Artisan::call('mongo:backup', [
                '--cleanup' => true,
                '--days' => $days
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Limpieza completada (backups > {$days} días)",
                'output' => Artisan::output(),
                'backups' => $this->getBackupsList()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en limpieza: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener información de estado de MongoDB
     */
    public function status()
    {
        try {
            $exitCode = Artisan::call('mongo:stats');
            $output = Artisan::output();
            
            return response()->json([
                'success' => true,
                'mongo_status' => $exitCode === 0 ? 'connected' : 'error',
                'stats_output' => $output
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mongo_status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Obtener lista de backups disponibles
     */
    private function getBackupsList(): array
    {
        $backupDir = storage_path('app/backups');
        
        if (!is_dir($backupDir)) {
            return [];
        }
        
        $files = glob($backupDir . '/trailynsafe_backup_*.gz');
        $backups = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            $filesize = filesize($file);
            $created = filemtime($file);
            
            // Extraer fecha del nombre del archivo
            if (preg_match('/trailynsafe_backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.gz/', $filename, $matches)) {
                $dateStr = str_replace('_', ' ', str_replace('-', ':', $matches[1], 2));
                $parsedDate = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr);
            } else {
                $parsedDate = Carbon::createFromTimestamp($created);
            }
            
            $backups[] = [
                'filename' => $filename,
                'size' => $this->formatBytes($filesize),
                'size_bytes' => $filesize,
                'created_at' => $parsedDate->toISOString(),
                'created_human' => $parsedDate->diffForHumans(),
                'age_days' => $parsedDate->diffInDays(Carbon::now())
            ];
        }
        
        // Ordenar por fecha (más recientes primero)
        usort($backups, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        
        return $backups;
    }
    
    /**
     * Obtener información de almacenamiento
     */
    private function getStorageInfo(): array
    {
        $backupDir = storage_path('app/backups');
        
        if (!is_dir($backupDir)) {
            return [
                'total_backups' => 0,
                'total_size' => '0 B',
                'total_size_bytes' => 0
            ];
        }
        
        $files = glob($backupDir . '/trailynsafe_backup_*.gz');
        $totalSize = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }
        
        return [
            'total_backups' => count($files),
            'total_size' => $this->formatBytes($totalSize),
            'total_size_bytes' => $totalSize
        ];
    }
    
    /**
     * Formatear bytes en unidades legibles
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
