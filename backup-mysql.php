<?php
/**
 * Script de Backup Automático de MySQL
 * 
 * Funcionalidades:
 * - Backup de bases de datos MySQL
 * - Compresión con gzip
 * - Limpieza automática de backups antiguos
 * - Logging de operaciones
 * - Envío de email con el resultado
 * 
 * Autor: Hugo Moreno
 * Fecha: 2024
 */

// ========================================
// CONFIGURACIÓN
// ========================================

// Configuración de la base de datos
$db_config = [
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'root',
    'password' => '',
    'databases' => [] // Array vacío = todas las bases de datos
];

// Configuración de backups
$backup_config = [
    'backup_dir' => './backups/',        // Directorio de backups
    'compress' => true,                   // Comprimir con gzip
    'keep_days' => 7,                     // Mantener backups de últimos N días
    'max_backups' => 10,                  // Máximo número de backups
];

// Configuración de email (opcional)
$email_config = [
    'enabled' => false,
    'to' => 'admin@example.com',
    'subject_prefix' => '[MySQL Backup]'
];

// ========================================
// FUNCIONES
// ========================================

/**
 * Conectar a MySQL
 */
function connectMySQL($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']}";
        $pdo = new PDO($dsn, $config['user'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        logMessage("Error conectando a MySQL: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener lista de bases de datos
 */
function getDatabases($pdo, $config) {
    if (!empty($config['databases'])) {
        return $config['databases'];
    }
    
    try {
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $db = $row['Database'];
            // Excluir bases de datos del sistema
            if (!in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys'])) {
                $databases[] = $db;
            }
        }
        return $databases;
    } catch (PDOException $e) {
        logMessage("Error obteniendo bases de datos: " . $e->getMessage());
        return [];
    }
}

/**
 * Realizar backup de una base de datos
 */
function backupDatabase($pdo, $database, $backup_dir, $compress, $db_config) {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$database}_{$timestamp}.sql";
    $filepath = $backup_dir . $filename;
    
    // Crear directorio si no existe
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Ejecutar mysqldump
    $command = sprintf(
        'mysqldump -h%s -P%s -u%s -p%s %s > %s 2>&1',
        escapeshellarg($db_config['host']),
        escapeshellarg($db_config['port']),
        escapeshellarg($db_config['user']),
        escapeshellarg($db_config['password']),
        escapeshellarg($database),
        escapeshellarg($filepath)
    );
    
    exec($command, $output, $return_var);
    
    if ($return_var !== 0) {
        logMessage("Error en backup de {$database}: " . implode("\n", $output));
        return false;
    }
    
    // Comprimir si está habilitado
    if ($compress && function_exists('gzencode')) {
        $compressed_file = $filepath . '.gz';
        $data = file_get_contents($filepath);
        $gzdata = gzencode($data, 9);
        file_put_contents($compressed_file, $gzdata);
        unlink($filepath); // Eliminar archivo original
        logMessage("Backup comprimido: {$compressed_file}");
        return $compressed_file;
    }
    
    logMessage("Backup creado: {$filepath}");
    return $filepath;
}

/**
 * Limpiar backups antiguos
 */
function cleanupOldBackups($backup_dir, $keep_days, $max_backups) {
    $files = glob($backup_dir . '*.sql*');
    $deleted = 0;
    $total_size = 0;
    
    // Ordenar por fecha de modificación (más recientes primero)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach ($files as $file) {
        $age_days = (time() - filemtime($file)) / (24 * 60 * 60);
        $size = filesize($file);
        
        // Eliminar si es más antiguo que keep_days o si excede max_backups
        if ($age_days > $keep_days || $deleted < (count($files) - $max_backups)) {
            unlink($file);
            $deleted++;
            $total_size += $size;
            logMessage("Backup eliminado: {$file}");
        }
    }
    
    return [
        'deleted' => $deleted,
        'size_freed' => $total_size
    ];
}

/**
 * Registrar mensajes
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
}

/**
 * Enviar email con resultado
 */
function sendEmail($result, $config) {
    if (!$config['enabled']) {
        return;
    }
    
    $subject = $config['subject_prefix'] . ' ' . $result['status'];
    $message = "Resultado del backup MySQL\n\n";
    $message .= "Estado: {$result['status']}\n";
    $message .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
    $message .= "Bases de datos: " . count($result['backups']) . "\n";
    $message .= "Backups creados: " . $result['created'] . "\n";
    
    if (isset($result['cleanup'])) {
        $message .= "Backups eliminados: {$result['cleanup']['deleted']}\n";
        $message .= "Espacio liberado: " . formatBytes($result['cleanup']['size_freed']) . "\n";
    }
    
    $message .= "\nDetalles:\n";
    foreach ($result['backups'] as $backup) {
        $message .= "- {$backup}\n";
    }
    
    mail($config['to'], $subject, $message);
}

/**
 * Formatear bytes a formato legible
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// ========================================
// EJECUCIÓN
// ========================================

logMessage("=== Iniciando Backup MySQL ===");

// Conectar a MySQL
$pdo = connectMySQL($db_config);
if (!$pdo) {
    logMessage("No se pudo conectar a MySQL. Abortando.");
    exit(1);
}

// Obtener bases de datos
$databases = getDatabases($pdo, $db_config);
if (empty($databases)) {
    logMessage("No se encontraron bases de datos para hacer backup.");
    exit(1);
}

logMessage("Bases de datos encontradas: " . count($databases));

// Realizar backups
$backups_created = [];
foreach ($databases as $database) {
    $backup_file = backupDatabase($pdo, $database, $backup_config['backup_dir'], $backup_config['compress'], $db_config);
    if ($backup_file) {
        $backups_created[] = $backup_file;
    }
}

// Limpieza de backups antiguos
$cleanup_result = cleanupOldBackups(
    $backup_config['backup_dir'],
    $backup_config['keep_days'],
    $backup_config['max_backups']
);

// Preparar resultado
$result = [
    'status' => count($backups_created) === count($databases) ? 'ÉXITO' : 'PARCIAL',
    'backups' => $backups_created,
    'created' => count($backups_created),
    'cleanup' => $cleanup_result
];

// Enviar email
sendEmail($result, $email_config);

logMessage("=== Backup Completado ===");
logMessage("Backups creados: " . count($backups_created));
logMessage("Backups eliminados: {$cleanup_result['deleted']}");
logMessage("Espacio liberado: " . formatBytes($cleanup_result['size_freed']));

exit($result['status'] === 'ÉXITO' ? 0 : 1);
?>
