# 💾 MySQL Backup Automático

Script PHP para realizar backups automáticos de bases de datos MySQL con compresión y limpieza automática de archivos antiguos.

## 🚀 Características

- ✅ Backup automático de todas las bases de datos MySQL
- 🗜️ Compresión con gzip para ahorrar espacio
- 🧹 Limpieza automática de backups antiguos
- 📧 Notificaciones por email (opcional)
- 📝 Logging detallado de todas las operaciones
- ⚙️ Configuración simple y flexible
- 🔒 Excluye bases de datos del sistema automáticamente

## 📋 Requisitos

- PHP 7.0 o superior
- Extensión PDO de PHP
- Extensión gzip de PHP
- MySQL/MariaDB instalado
- Acceso a `mysqldump` desde la línea de comandos

## 📦 Instalación

1. Descarga el archivo `backup-mysql.php`
2. Edita la configuración al inicio del archivo:

```php
// Configuración de la base de datos
$db_config = [
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'tu_usuario',
    'password' => 'tu_contraseña',
    'databases' => [] // Array vacío para todas las bases de datos
];

// Configuración de backups
$backup_config = [
    'backup_dir' => './backups/',
    'compress' => true,
    'keep_days' => 7,
    'max_backups' => 10,
];
```

3. Da permisos de ejecución (en sistemas Unix/Linux):
```bash
chmod +x backup-mysql.php
```

## 🎯 Uso

### Ejecución Manual

```bash
php backup-mysql.php
```

### Automatización con Cron

Para ejecutar el backup diariamente a las 3:00 AM:

```bash
crontab -e
```

Añade la siguiente línea:

```bash
0 3 * * * /usr/bin/php /ruta/completa/backup-mysql.php >> /var/log/backup-mysql.log 2>&1
```

### Varios horarios

```bash
# Cada 6 horas
0 */6 * * * php backup-mysql.php

# Cada día a medianoche
0 0 * * * php backup-mysql.php

# Cada semana (domingos a las 2 AM)
0 2 * * 0 php backup-mysql.php
```

## ⚙️ Configuración

### Base de Datos

```php
$db_config = [
    'host' => 'localhost',    // Servidor MySQL
    'port' => 3306,           // Puerto MySQL
    'user' => 'root',         // Usuario MySQL
    'password' => 'secret',   // Contraseña MySQL
    'databases' => []         // Array vacío = todas las bases de datos
];
```

Para hacer backup de bases de datos específicas:

```php
'databases' => ['mi_bd', 'otra_bd']
```

### Backups

```php
$backup_config = [
    'backup_dir' => './backups/',  // Directorio donde guardar backups
    'compress' => true,             // Comprimir con gzip
    'keep_days' => 7,               // Mantener backups de últimos N días
    'max_backups' => 10,            // Máximo número de backups a mantener
];
```

### Email (Opcional)

```php
$email_config = [
    'enabled' => true,
    'to' => 'admin@example.com',
    'subject_prefix' => '[MySQL Backup]'
];
```

## 📁 Estructura de Archivos

```
backups/
├── mi_bd_2024-01-15_03-00-01.sql.gz
├── mi_bd_2024-01-15_09-00-02.sql.gz
├── otra_bd_2024-01-15_03-00-01.sql.gz
└── otra_bd_2024-01-15_09-00-02.sql.gz
```

## 📝 Formato de Salida

El script genera mensajes de log en tiempo real:

```
[2024-01-15 03:00:01] === Iniciando Backup MySQL ===
[2024-01-15 03:00:01] Bases de datos encontradas: 2
[2024-01-15 03:00:05] Backup creado: ./backups/mi_bd_2024-01-15_03-00-01.sql
[2024-01-15 03:00:05] Backup comprimido: ./backups/mi_bd_2024-01-15_03-00-01.sql.gz
[2024-01-15 03:00:10] Backup creado: ./backups/otra_bd_2024-01-15_03-00-01.sql
[2024-01-15 03:00:10] Backup comprimido: ./backups/otra_bd_2024-01-15_03-00-01.sql.gz
[2024-01-15 03:00:15] Backup eliminado: ./backups/mi_bd_2024-01-08_03-00-01.sql.gz
[2024-01-15 03:00:15] === Backup Completado ===
[2024-01-15 03:00:15] Backups creados: 2
[2024-01-15 03:00:15] Backups eliminados: 1
[2024-01-15 03:00:15] Espacio liberado: 245.67 MB
```

## 🔧 Opciones Avanzadas

### Backup de tablas específicas

Modifica la función `backupDatabase()` para incluir solo tablas específicas:

```php
$command = sprintf(
    'mysqldump -h%s -u%s -p%s %s tabla1 tabla2 > %s',
    // ...
);
```

### Excluir tablas

```php
$command = sprintf(
    'mysqldump -h%s -u%s -p%s --ignore-table=%s.logs %s > %s',
    // ...
);
```

### Restaurar un Backup

```bash
# Descomprimir
gunzip backup-2024-01-15.sql.gz

# Restaurar
mysql -u usuario -p nombre_bd < backup-2024-01-15.sql
```

## 🛡️ Seguridad

- Usa variables de entorno para credenciales sensibles
- Establece permisos de archivo apropiados (600 o 700)
- Protege el directorio de backups con `.htaccess` o permisos restrictivos

### Ejemplo con variables de entorno

```php
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD'),
];
```

## 🐛 Solución de Problemas

### Error: "mysqldump: command not found"

Añade la ruta de MySQL al PATH:

```bash
export PATH=$PATH:/usr/local/mysql/bin
```

### Error: "Access denied for user"

Verifica las credenciales y permisos del usuario MySQL:

```sql
GRANT ALL PRIVILEGES ON *.* TO 'usuario'@'localhost';
FLUSH PRIVILEGES;
```

### Backups muy grandes

Ajusta el nivel de compresión o aumenta el límite de tiempo de ejecución:

```php
ini_set('max_execution_time', 0); // Sin límite de tiempo
```

## 📊 Estadísticas

El script proporciona información sobre:
- Número de backups creados
- Número de backups eliminados
- Espacio liberado (MB/GB)
- Estado de la operación (Éxito/Parcial/Error)

## 🤝 Contribuciones

¡Las contribuciones son bienvenidas! Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 👤 Autor

**Hugo Moreno**

- Portfolio: [hugomoreno.es](https://hugomoreno.pro)
- GitHub: [@hugomoreno](https://github.com/huugoomooreenoo)

## ⭐ Agradecimientos

- Gracias a todos los que contribuyen y reportan issues
- Inspirado en las mejores prácticas de backup de bases de datos

## 📞 Soporte

Si tienes preguntas o necesitas ayuda, puedes:
- Abrir un issue en GitHub
- Contactar al autor

---

⭐ Si este proyecto te resultó útil, ¡dale una estrella en GitHub!

