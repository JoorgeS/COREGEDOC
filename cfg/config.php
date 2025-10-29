<?php

/**
 * @author elporfirio.com | Ajustado por Gemini
 * Archivo de configuración base para conexión PDO, leyendo credenciales desde configuracion.ini.
 * Esta clase debe ser extendida (heredada) para ser utilizada.
 */

abstract class BaseConexion
{
	// ... (Tu código actual de la clase BaseConexion permanece sin cambios) ...

	protected $datahost;

	/**
	 * Establece y retorna la conexión PDO.
	 * @param string $archivo Nombre del archivo INI de configuración.
	 * @return PDO
	 */
	protected function conectar($archivo = 'configuracion.ini')
	{
		// 1. Define la ruta del archivo INI (asume que está en el mismo directorio cfg/)
		$ruta_archivo_ini = __DIR__ . '/' . $archivo;

		if (!$ajustes = parse_ini_file($ruta_archivo_ini, true)) {
			// Usamos throw para un manejo de errores más profesional
			throw new Exception('No se puede abrir el archivo de configuración: ' . $ruta_archivo_ini . '.');
		}

		// 2. Extraer parámetros de conexión del INI
		$servidor = $ajustes["database"]["host"];
		$puerto = $ajustes["database"]["port"];
		$basedatos = $ajustes["database"]["schema"];

		try {
			// 3. Crear la conexión PDO con el charset=utf8mb4 para mayor compatibilidad de caracteres
			$this->datahost = new PDO(
				"mysql:host=$servidor;port=$puerto;dbname=$basedatos;charset=utf8mb4",
				$ajustes['database']['username'],
				$ajustes['database']['password'],
				array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
			);

			// 4. Configurar manejo de errores y modo de fetch (tomado de tu código inicial)
			$this->datahost->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->datahost->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			return $this->datahost;
		} catch (PDOException $e) {
			// Detener la ejecución con un mensaje de error claro
			die("Error en la conexión a la base de datos: " . $e->getMessage());
		}
	}
}


// ==============================================================================
// 🚀 INICIO DE CONFIGURACIÓN DE CORREO ELECTRÓNICO (SMTP)
// ==============================================================================

// **IMPORTANTE:** Reemplaza estos valores con tus credenciales reales de correo.
// Si usas Gmail, recuerda generar una "Contraseña de aplicación" y usar el puerto 465 o 587.

define('SMTP_HOST', 'smtp.gmail.com');                  // Servidor SMTP (ej. 'smtp.gmail.com')
define('SMTP_USER', 'tu_correo_de_envio@gmail.com');    // Tu correo electrónico completo
define('SMTP_PASS', 'tu_clave_de_aplicacion');          // La contraseña o Clave de Aplicación (NO la contraseña de tu cuenta)
define('SMTP_PORT', 465);                               // Puerto SMTPS (465) o STARTTLS (587)

// ==============================================================================
// 🛑 FIN DE CONFIGURACIÓN DE CORREO ELECTRÓNICO (SMTP)
// ==============================================================================