<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class BackupController extends Controller
{
    private $backupPath;

    public function __construct()
    {
        // Directorio para almacenar backups
        $this->backupPath = storage_path('app/backups');
        
        // Crear directorio si no existe
        if (!file_exists($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Listar todos los backups
     */
    public function index()
    {
        try {
            $backups = Backup::with('creator:id,nombre,correo')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($backup) {
                    return [
                        'id' => $backup->id,
                        'nombre' => $backup->nombre,
                        'tipo' => $backup->tipo,
                        'formato' => $backup->formato,
                        'tablas' => $backup->tablas,
                        'tamano' => $backup->tamano,
                        'tamano_formateado' => $backup->tamano_formateado,
                        'descripcion' => $backup->descripcion,
                        'created_by' => $backup->creator ? $backup->creator->nombre : 'Sistema',
                        'created_at' => $backup->created_at->format('Y-m-d H:i:s'),
                        'existe_archivo' => file_exists($backup->ruta)
                    ];
                });

            return response()->json($backups, 200);
        } catch (\Exception $e) {
            Log::error('Error al listar backups: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener los backups',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de tablas disponibles
     */
    public function getTables()
    {
        try {
            $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            $tableNames = array_map(fn($t) => $t->tablename, $tables);
            
            return response()->json($tableNames, 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener tablas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener las tablas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo backup
     */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo' => 'required|in:completo,tablas,estructura',
                'formato' => 'required|in:sql,gz,zip',
                'tablas' => 'nullable|array',
                'tablas.*' => 'string',
                'descripcion' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 422);
            }

            $tipo = $request->tipo;
            $formato = $request->formato;
            $tablas = $request->tablas ?? [];
            $descripcion = $request->descripcion;

            // Generar nombre único para el backup
            $timestamp = date('Y-m-d_His');
            $baseName = "backup_{$tipo}_{$timestamp}";
            $sqlFile = "{$this->backupPath}/{$baseName}.sql";
            $finalFile = $sqlFile;

            // Obtener credenciales de la base de datos
            $dbHost = env('DB_HOST', 'localhost');
            $dbPort = env('DB_PORT', '5432');
            $dbName = env('DB_DATABASE');
            $dbUser = env('DB_USERNAME');
            $dbPassword = env('DB_PASSWORD');

            // Construir comando pg_dump según el tipo
            $command = "PGPASSWORD=\"{$dbPassword}\" pg_dump -h {$dbHost} -p {$dbPort} -U {$dbUser} -d {$dbName}";

            if ($tipo === 'estructura') {
                $command .= " --schema-only";
            } elseif ($tipo === 'tablas' && !empty($tablas)) {
                foreach ($tablas as $tabla) {
                    $command .= " -t {$tabla}";
                }
            }

            $command .= " > \"{$sqlFile}\" 2>&1";

            // Ejecutar backup
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error('Error en pg_dump: ' . implode("\n", $output));
                return response()->json([
                    'error' => 'Error al crear el backup',
                    'details' => implode("\n", $output)
                ], 500);
            }

            // Verificar que el archivo se creó
            if (!file_exists($sqlFile)) {
                return response()->json([
                    'error' => 'El archivo de backup no se creó correctamente'
                ], 500);
            }

            // Comprimir según el formato solicitado
            if ($formato === 'gz') {
                $gzFile = $sqlFile . '.gz';
                exec("gzip -c \"{$sqlFile}\" > \"{$gzFile}\"", $gzOutput, $gzReturn);
                
                if ($gzReturn === 0 && file_exists($gzFile)) {
                    unlink($sqlFile);
                    $finalFile = $gzFile;
                }
            } elseif ($formato === 'zip') {
                $zipFile = "{$this->backupPath}/{$baseName}.zip";
                $zip = new ZipArchive();
                
                if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                    $zip->addFile($sqlFile, basename($sqlFile));
                    $zip->close();
                    unlink($sqlFile);
                    $finalFile = $zipFile;
                }
            }

            // Obtener tamaño del archivo
            $tamano = filesize($finalFile);

            // Guardar registro en la base de datos
            $backup = Backup::create([
                'nombre' => basename($finalFile),
                'tipo' => $tipo,
                'formato' => $formato,
                'tablas' => $tipo === 'tablas' ? $tablas : null,
                'tamano' => $tamano,
                'ruta' => $finalFile,
                'descripcion' => $descripcion,
                'created_by' => auth('sanctum')->id()
            ]);

            return response()->json([
                'message' => 'Backup creado exitosamente',
                'backup' => [
                    'id' => $backup->id,
                    'nombre' => $backup->nombre,
                    'tipo' => $backup->tipo,
                    'formato' => $backup->formato,
                    'tamano_formateado' => $backup->tamano_formateado,
                    'created_at' => $backup->created_at->format('Y-m-d H:i:s')
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear backup: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al crear el backup',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar un backup
     */
    public function download($id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if (!file_exists($backup->ruta)) {
                return response()->json([
                    'error' => 'El archivo de backup no existe'
                ], 404);
            }

            return response()->download($backup->ruta, $backup->nombre);

        } catch (\Exception $e) {
            Log::error('Error al descargar backup: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al descargar el backup',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un backup
     */
    public function delete($id)
    {
        try {
            $backup = Backup::findOrFail($id);

            // Eliminar archivo físico
            if (file_exists($backup->ruta)) {
                unlink($backup->ruta);
            }

            // Eliminar registro de la base de datos
            $backup->delete();

            return response()->json([
                'message' => 'Backup eliminado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al eliminar backup: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al eliminar el backup',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restaurar un backup
     */
    public function restore(Request $request, $id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if (!file_exists($backup->ruta)) {
                return response()->json([
                    'error' => 'El archivo de backup no existe'
                ], 404);
            }

            $dbHost = env('DB_HOST', 'localhost');
            $dbPort = env('DB_PORT', '5432');
            $dbName = env('DB_DATABASE');
            $dbUser = env('DB_USERNAME');
            $dbPassword = env('DB_PASSWORD');

            $sqlFile = $backup->ruta;

            // Descomprimir si es necesario
            if ($backup->formato === 'gz') {
                $tempSql = $this->backupPath . '/temp_restore.sql';
                exec("gunzip -c \"{$sqlFile}\" > \"{$tempSql}\"");
                $sqlFile = $tempSql;
            } elseif ($backup->formato === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($backup->ruta) === TRUE) {
                    $zip->extractTo($this->backupPath);
                    $zip->close();
                }
            }

            // Ejecutar restauración
            $command = "PGPASSWORD=\"{$dbPassword}\" psql -h {$dbHost} -p {$dbPort} -U {$dbUser} -d {$dbName} < \"{$sqlFile}\" 2>&1";
            exec($command, $output, $returnCode);

            // Limpiar archivos temporales
            if (isset($tempSql) && file_exists($tempSql)) {
                unlink($tempSql);
            }

            if ($returnCode !== 0) {
                Log::error('Error en restauración: ' . implode("\n", $output));
                return response()->json([
                    'error' => 'Error al restaurar el backup',
                    'details' => implode("\n", $output)
                ], 500);
            }

            return response()->json([
                'message' => 'Backup restaurado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al restaurar backup: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al restaurar el backup',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar backups antiguos
     */
    public function cleanup(Request $request)
    {
        try {
            $dias = $request->input('dias', 30);
            $fecha = now()->subDays($dias);

            $backupsAntiguos = Backup::where('created_at', '<', $fecha)->get();
            $eliminados = 0;

            foreach ($backupsAntiguos as $backup) {
                if (file_exists($backup->ruta)) {
                    unlink($backup->ruta);
                }
                $backup->delete();
                $eliminados++;
            }

            return response()->json([
                'message' => "Se eliminaron {$eliminados} backups antiguos"
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al limpiar backups: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al limpiar backups antiguos',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
