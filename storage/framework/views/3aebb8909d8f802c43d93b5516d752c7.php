<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Viewer - Sistema de Recaudación</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #4F46E5;
            color: white;
        }

        .btn-secondary {
            background: #6B7280;
            color: white;
        }

        .btn-danger {
            background: #EF4444;
            color: white;
        }

        .filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        select,
        input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .log-container {
            background: #1E1E1E;
            color: #D4D4D4;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            max-height: 70vh;
            overflow-y: auto;
        }

        .log-entry {
            margin-bottom: 15px;
            padding: 10px;
            border-left: 4px solid #666;
        }

        .ERROR {
            border-left-color: #EF4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .WARNING {
            border-left-color: #F59E0B;
            background: rgba(245, 158, 11, 0.1);
        }

        .INFO {
            border-left-color: #3B82F6;
            background: rgba(59, 130, 246, 0.1);
        }

        .DEBUG {
            border-left-color: #6B7280;
            background: rgba(107, 114, 128, 0.1);
        }

        .timestamp {
            color: #10B981;
        }

        .level {
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 3px;
            display: inline-block;
            margin: 0 5px;
        }

        .level-ERROR {
            background: #EF4444;
            color: white;
        }

        .level-WARNING {
            background: #F59E0B;
            color: white;
        }

        .level-INFO {
            background: #3B82F6;
            color: white;
        }

        .level-DEBUG {
            background: #6B7280;
            color: white;
        }

        .context {
            color: #9CA3AF;
            margin-top: 8px;
            padding-left: 10px;
            white-space: pre-wrap;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #888;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📝 Visor de Logs</h1>
            <p>Sistema de Recaudación - Logs de Aplicación</p>
            <div class="actions">
                <a href="/admin/dashboard" class="btn btn-secondary">← Volver al Dashboard</a>
                <a href="/admin/logs/download" class="btn btn-primary">↓ Descargar Log</a>
                <form method="POST" action="/admin/logs/clear" style="display: inline;"
                    onsubmit="return confirm('¿Estás seguro de eliminar todos los logs?');">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-danger">🗑️ Limpiar Logs</button>
                </form>
            </div>
        </div>

        <div class="filters">
            <div>
                <label>Nivel:</label>
                <select
                    onchange="window.location.href='?level='+this.value+'&search='+document.getElementById('search').value">
                    <option value="all" <?php echo e(request('level') == 'all' ? 'selected' : ''); ?>>Todos</option>
                    <option value="error" <?php echo e(request('level') == 'error' ? 'selected' : ''); ?>>ERROR</option>
                    <option value="warning" <?php echo e(request('level') == 'warning' ? 'selected' : ''); ?>>WARNING</option>
                    <option value="info" <?php echo e(request('level') == 'info' ? 'selected' : ''); ?>>INFO</option>
                    <option value="debug" <?php echo e(request('level') == 'debug' ? 'selected' : ''); ?>>DEBUG</option>
                </select>
            </div>
            <div style="flex: 1;">
                <input type="text" id="search" placeholder="Buscar en logs..." value="<?php echo e(request('search')); ?>"
                    style="width: 100%">
                <button
                    onclick="window.location.href='?level='+document.querySelector('select').value+'&search='+document.getElementById('search').value"
                    class="btn btn-primary" style="margin-left: 10px;">🔍 Buscar</button>
            </div>
        </div>

        <div class="log-container">
            <?php if(isset($error)): ?>
                <div class="empty"><?php echo e($error); ?></div>
            <?php elseif(count($logs) == 0): ?>
                <div class="empty">No hay logs para mostrar con los filtros seleccionados</div>
            <?php else: ?>
                <?php $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="log-entry <?php echo e($log['level']); ?>">
                        <div>
                            <span class="timestamp"><?php echo e($log['timestamp']); ?></span>
                            <span class="level level-<?php echo e($log['level']); ?>"><?php echo e($log['level']); ?></span>
                            <span><?php echo e($log['message']); ?></span>
                        </div>
                        <?php if(!empty($log['context'])): ?>
                            <div class="context"><?php echo e($log['context']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html><?php /**PATH /var/www/html/resources/views/admin/log-viewer.blade.php ENDPATH**/ ?>