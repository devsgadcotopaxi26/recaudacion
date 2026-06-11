<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    /**
     * Mostrar visor de logs
     */
    public function index(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            return view('admin.log-viewer', [
                'logs' => [],
                'error' => 'No se encontró el archivo de log'
            ]);
        }

        // Leer últimas 1000 líneas del log
        $lines = $this->tailFile($logPath, 1000);

        // Parsear logs
        $logs = $this->parseLogs($lines);

        // Filtrar por nivel si se solicita
        if ($request->has('level') && $request->level !== 'all') {
            $logs = array_filter($logs, function ($log) use ($request) {
                return strtolower($log['level']) === strtolower($request->level);
            });
        }

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $logs = array_filter($logs, function ($log) use ($search) {
                return str_contains(strtolower($log['message']), $search) ||
                    str_contains(strtolower($log['context']), $search);
            });
        }

        return view('admin.log-viewer', [
            'logs' => array_values($logs),
            'filters' => [
                'level' => $request->level ?? 'all',
                'search' => $request->search ?? ''
            ]
        ]);
    }

    /**
     * Leer últimas N líneas de un archivo
     */
    private function tailFile(string $filepath, int $lines = 1000): array
    {
        $file = new \SplFileObject($filepath, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $startLine = max(0, $lastLine - $lines);

        $result = [];
        $file->seek($startLine);
        while (!$file->eof()) {
            $result[] = $file->current();
            $file->next();
        }

        return $result;
    }

    /**
     * Parsear líneas de log
     */
    private function parseLogs(array $lines): array
    {
        $logs = [];
        $currentLog = null;

        foreach ($lines as $line) {
            // Patrón: [2024-01-20 10:00:00] local.ERROR: mensaje
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)$/', $line, $matches)) {
                if ($currentLog) {
                    $logs[] = $currentLog;
                }

                $currentLog = [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3],
                    'context' => ''
                ];
            } else if ($currentLog) {
                // Líneas de contexto
                $currentLog['context'] .= $line . "\n";
            }
        }

        if ($currentLog) {
            $logs[] = $currentLog;
        }

        return array_reverse($logs); // Más recientes primero
    }

    /**
     * Descargar log completo
     */
    public function download()
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            abort(404, 'Archivo de log no encontrado');
        }

        return response()->download($logPath, 'laravel-' . date('Y-m-d') . '.log');
    }

    /**
     * Limpiar logs
     */
    public function clear()
    {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        return redirect()->route('admin.logs')->with('success', 'Logs eliminados exitosamente');
    }
}
