<?php
// controllers/guardar_minuta_completa.php

// ----------------------------------------------------------------------
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../cfg/config.php';
require_once __DIR__ . '/../class/class.conectorDB.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Incluye el autoloader de Composer/Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;
// (PHPMailer eliminado de este archivo)

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
  $idSecretario = $_SESSION['idUsuario'] ?? 0;
  // ... (resto del código)
}

// --- 1. Recepción de Datos desde $_POST y $_FILES ---
$idMinuta = $_POST['idMinuta'] ?? null;
$asistenciaJson = $_POST['asistencia'] ?? '[]'; // JSON string
$temasJson = $_POST['temas'] ?? '[]'; // JSON string
$enlaceAdjunto = $_POST['enlaceAdjunto'] ?? null;

// Validar ID Minuta
if (!$idMinuta || !is_numeric($idMinuta)) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'ID de minuta inválido o faltante.']);
  exit;
}

// --- 2. Decodificar JSON ---
$asistenciaIDs = json_decode($asistenciaJson, true);
$temasData = json_decode($temasJson, true); // USO DE $temasJson CORREGIDO

// Validar JSON decodificado
if ($asistenciaIDs === null || $temasData === null) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Error al decodificar datos de asistencia o temas.']);
  exit;
}
if (!is_array($asistenciaIDs) || !is_array($temasData)) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Los datos de asistencia o temas no son arrays válidos.']);
  exit;
}


class MinutaManager extends BaseConexion
{
  private $db;

  public function __construct()
  {
    $this->db = $this->conectar();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /**
  * Obtiene todos los datos necesarios para el PDF: metadatos de la minuta/reunión,
  * comisiones, secretario y lista de asistencia con hora/fecha de registro Y ORIGEN.
  */
  private function getFullMinutaData(array $asistenciaIDs, int $idMinuta): array
  {
    // 1. Fetch main minuta and reunion data (SIN CAMBIOS)
    $sqlData = "SELECT
      m.idMinuta, m.fechaMinuta, m.horaMinuta, m.t_usuario_idSecretario,
      r.nombreReunion, r.t_comision_idComision, r.t_comision_idComision_mixta, r.t_comision_idComision_mixta2
      FROM t_minuta m
      JOIN t_reunion r ON m.idMinuta = r.t_minuta_idMinuta
      WHERE m.idMinuta = ?";
    $stmtData = $this->db->prepare($sqlData);
    $stmtData->execute([$idMinuta]);
    $minutaData = $stmtData->fetch(PDO::FETCH_ASSOC);

    if (!$minutaData) {
      return ['error' => 'Minuta no encontrada.'];
    }

    // 2, 3, 4. Obtener Comisiones y Datos del Secretario (SIN CAMBIOS)
    $comisionIDs = array_filter([
      $minutaData['t_comision_idComision'],
      $minutaData['t_comision_idComision_mixta'],
      $minutaData['t_comision_idComision_mixta2']
    ]);
    $comisionNombres = [];

    if (!empty($comisionIDs)) {
      $placeholders = implode(',', array_fill(0, count($comisionIDs), '?'));
      $sqlComision = "SELECT nombreComision FROM t_comision WHERE idComision IN ({$placeholders})";
      $stmtComision = $this->db->prepare($sqlComision);
      $stmtComision->execute($comisionIDs);
      $comisionNombres = $stmtComision->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    $minutaData['comisiones'] = implode(', ', $comisionNombres);

    $sqlSecretary = "SELECT pNombre, sNombre, aPaterno, aMaterno
               FROM t_usuario 
               WHERE idUsuario = ?";
    $stmtSecretary = $this->db->prepare($sqlSecretary);
    $stmtSecretary->execute([$minutaData['t_usuario_idSecretario']]);
    $secData = $stmtSecretary->fetch(PDO::FETCH_ASSOC);

    $minutaData['nombreSecretario'] = $secData ?
      trim(implode(' ', array_filter([$secData['pNombre'], $secData['sNombre'], $secData['aPaterno'], $secData['aMaterno']]))) :
      'Secretario Desconocido';

    $sqlSecretaryPosition = "SELECT t2.descTipoUsuario
                   FROM t_usuario t1
                   JOIN t_tipousuario t2 ON t1.tipoUsuario_id = t2.idTipoUsuario
                   WHERE t1.idUsuario = ?";
    $stmtSecretaryPosition = $this->db->prepare($sqlSecretaryPosition);
    $stmtSecretaryPosition->execute([$minutaData['t_usuario_idSecretario']]);
    $minutaData['cargoSecretario'] = $stmtSecretaryPosition->fetchColumn() ?? 'Cargo Desconocido';

        
    // 5. MODIFICACIÓN CRÍTICA: Obtener datos de Asistencia (fecha y ORIGEN)
    $asistenciaData = [];
    if (!empty($asistenciaIDs)) {
      $placeholders = implode(',', array_fill(0, count($asistenciaIDs), '?'));
      $params = array_merge([$idMinuta], $asistenciaIDs);

      // CONSULTA MODIFICADA: Incluimos origenAsistencia
      $sqlAsistencia = "SELECT t_usuario_idUsuario, fechaRegistroAsistencia, origenAsistencia 
                 FROM t_asistencia 
                 WHERE t_minuta_idMinuta = ? AND t_usuario_idUsuario IN ({$placeholders})";

      $stmtAsistencia = $this->db->prepare($sqlAsistencia);
      $stmtAsistencia->execute($params);

      foreach ($stmtAsistencia->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $asistenciaData[(int)$row['t_usuario_idUsuario']] = [
          'fecha' => $row['fechaRegistroAsistencia'],
          'origen' => $row['origenAsistencia'] // <-- Guardamos el origen
        ];
      }
    }

    // 6. Get all relevant users (Tipo 1: Consejero Regional and 3: Presidente Comisión) (SIN CAMBIOS EN SQL)
    $sqlUsers = "SELECT 
             idUsuario, 
             TRIM(CONCAT(pNombre, ' ', COALESCE(sNombre, ''), ' ', aPaterno, ' ', aMaterno)) AS nombreCompleto
           FROM 
             t_usuario
           WHERE 
             tipoUsuario_id IN (1, 3) 
           ORDER BY 
             nombreCompleto";

    $stmtUsers = $this->db->prepare($sqlUsers);
    $stmtUsers->execute();
    $miembrosTotales = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $asistentesFinal = [];
    $asistenciaIDsMap = array_flip(array_map('intval', $asistenciaIDs));

    foreach ($miembrosTotales as $miembro) {
      $idUsuario = (int) $miembro['idUsuario'];
      $miembro['estadoAsistencia'] = isset($asistenciaIDsMap[$idUsuario]) ? 'Presente' : 'Ausente';
      
      $asistenciaDetalle = $asistenciaData[$idUsuario] ?? null; // Usamos el array de detalles
      
      $miembro['fechaHoraAsistenciaFmt'] = null;
            $miembro['origenAsistencia'] = null; // <-- Nuevo: Inicializamos el origen

      if ($asistenciaDetalle) {
                // MODIFICACIÓN: Extraer fecha y origen
        $fechaHoraAsistencia = $asistenciaDetalle['fecha'] ?? null;
                $miembro['origenAsistencia'] = $asistenciaDetalle['origen'] ?? 'DESCONOCIDO';

        // Formato 'DD/MM/YYYY HH:i:s'
        if ($fechaHoraAsistencia) {
                    try {
            $dateTime = new \DateTime($fechaHoraAsistencia);
            $miembro['fechaHoraAsistenciaFmt'] = $dateTime->format('d/m/Y H:i:s');
          } catch (\Exception $e) {
            $miembro['fechaHoraAsistenciaFmt'] = null;
          }
        }
      }
      $asistentesFinal[] = $miembro;
    }

    $minutaData['asistentes'] = $asistentesFinal;
    return $minutaData;
  }


  // Método 1: Adaptación para obtener la lista final de asistentes (para mantener el flujo) (SIN CAMBIOS)
  private function getNombresAsistentes(array $asistenciaIDs, int $idMinuta): array
  {
    $fullData = $this->getFullMinutaData($asistenciaIDs, $idMinuta);

    if (isset($fullData['error'])) {
      return [
        'idMinuta' => $idMinuta,
        'nombreReunion' => 'Error al obtener metadatos.',
        'fechaMinuta' => (new \DateTime())->format('Y-m-d'),
        'horaMinuta' => (new \DateTime())->format('H:i:s'),
        'nombreSecretario' => 'N/A',
        'cargoSecretario' => 'N/A',
        'comisiones' => 'N/A',
        'asistentes' => []
      ];
    }

    return $fullData;
  }


  /**
  * Método 2: Generar el PDF de asistencia (Implementación con el estilo de sello final)
  */
  private function generarPdfAsistencia(int $idMinuta, array $dataAsistencia): string
  {
    $nombresAsistentes = $dataAsistencia['asistentes'];

    // Extracción de Metadatos (SIN CAMBIOS)
    $idMinuta = $dataAsistencia['idMinuta'];
    $nombreReunion = htmlspecialchars($dataAsistencia['nombreReunion']);
    $fechaMinuta = (new \DateTime($dataAsistencia['fechaMinuta']))->format('d/m/Y');
    $horaMinuta = (new \DateTime($dataAsistencia['horaMinuta']))->format('H:i');
    $nombreSecretario = htmlspecialchars($dataAsistencia['nombreSecretario']);
    $cargoSecretario = 'Secretario Técnico'; // htmlspecialchars($dataAsistencia['cargoSecretario']);
    $comisiones = htmlspecialchars($dataAsistencia['comisiones'] ?? 'No Aplica');

    $fechaGeneracion = (new \DateTime())->format('Y-m-d H:i:s');
    $fechaParaNombreArchivo = (new \DateTime())->format('Ymd_His');

    // Rutas de imágenes (SIN CAMBIOS)
    $rutaLogoCore = __DIR__ . '/../public/img/logoCore1.png'; // Logo CORE (Izquierda)
    $rutaLogoGore = __DIR__ . '/../public/img/logo2.png'; // Logo GORE (Derecha)
    $rutaSelloVerde = __DIR__ . '/../public/img/aprobacion.png'; // Sello de Validación

    // Convertir rutas locales a data URI (obligatorio para incrustar en Dompdf)
    $logoCoreBase64 = file_exists($rutaLogoCore) ? base64_encode(file_get_contents($rutaLogoCore)) : '';
    $logoGoreBase64 = file_exists($rutaLogoGore) ? base64_encode(file_get_contents($rutaLogoGore)) : '';
    $selloBase64 = file_exists($rutaSelloVerde) ? base64_encode(file_get_contents($rutaSelloVerde)) : '';

    // Definición del URI para usarlo directamente en el tag <img>
    $selloUri = !empty($selloBase64) ? "data:image/png;base64,{$selloBase64}" : '';

    $html = "
    <!DOCTYPE html><html><head><meta charset='UTF-8'><title>Listado de Asistencia - Minuta {$idMinuta}</title>
    <style>
     body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 0; padding: 0; }
    .header { margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    
    .header-logos { width: 100%; display: table; table-layout: fixed; margin-bottom: 10px; }
    .header-logos > div { display: table-cell; width: 33%; text-align: center; vertical-align: top; }
    .header-logos .left { text-align: left; }
    .header-logos .center { text-align: center; }
    .header-logos .right { text-align: right; }
    .header-logos img { max-width: 80px; height: auto; margin: 0 5px; }
    
    .header-title { font-size: 10px; margin: 2px 0; line-height: 1.2; font-weight: bold; text-align: center;}
    .main-title { font-size: 14px; margin-top: 15px; text-align: center; text-decoration: underline; }

    .metadata { font-size: 11px; margin-top: 10px; text-align: left; }
    .metadata-grid { width: 100%; border-collapse: collapse; margin-top: 5px; }
    .metadata-grid td { padding: 2px 0; }
    .metadata-grid .label { width: 35%; font-weight: bold; padding-right: 5px; }

    .attendance-list { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .attendance-list th, .attendance-list td { border: 1px solid #ccc; padding: 8px 5px; text-align: left; }
    .attendance-list th { background-color: #f2f2f2; font-weight: bold; }
    
    /* ESTILOS SOLICITADOS PARA ESTADO */
    .presente-cell { font-weight: bold; color: #155724; }
    .ausente-cell { font-style: italic; color: #6c757d; }
    
    /* --- ESTILOS DEL SELLO DE VALIDACIÓN (COMO EN LA IMAGEN) --- */
    .validation-block-container {
       width: 280px; /* Ancho similar al de la imagen */
       margin: 50px auto 0; /* Centrado, como en la imagen */
       text-align: center;
       padding: 15px;
       border: 2px solid #a3e635; /* Borde verde claro */
       border-radius: 10px; /* Bordes redondeados */
       position: relative; /* CLAVE: Contenedor para la imagen absoluta */
       overflow: hidden; 
       background-color: #e6ffb3; /* Fondo verde muy pálido */
    }
    
    /* ESTILO PARA LA IMAGEN (sello de agua) */
    .validation-block-container img.sello-fondo {
      position: absolute;
      top: 50%;
      left: 50%;
      /* Centrado con transform */
      -ms-transform: translate(-50%, -50%); 
      -webkit-transform: translate(-50%, -50%); 
      transform: translate(-50%, -50%); 
      width: 80%; /* Tamaño del sello */
      height: auto;
      opacity: 0.8; /* Opacidad semi-transparente */
      z-index: 1; /* Fondo */
    }

    .validation-content {
       position: relative; 
       z-index: 2; /* CLAVE: Asegura que el texto esté por encima del sello */
       color: #333; 
    }
    
    /* Ajuste para <strong> (Nombre) */
    .validation-content strong {
       display: block;
       font-size: 14px; /* Tamaño más grande para el nombre */
       margin-bottom: 2px;
       color: #000;
       font-weight: bold;
    }
    
    /* Ajuste para <em> (Cargo) */
    .validation-content em {
       display: block;
       font-size: 12px; /* Tamaño para el cargo */
       font-style: italic;
       color: #555;
       margin-bottom: 5px;
    }
    
    /* Ajuste para p (Detalle/Fecha) */
    .validation-content p {
       margin: 2px 0;
       line-height: 1.2;
       font-size: 11px; /* Tamaño para el detalle y la fecha */
    }

    .dashed-line {
       border-top: 1px dashed #cccccc; /* Línea punteada */
       margin: 8px auto; /* Centrar línea */
       width: 80%; /* Ancho de la línea */
    }
    
    .footer { 
       position: fixed; 
       bottom: 0; 
       width: 100%; 
       text-align: center; 
       font-size: 9px; 
       color: #999; 
    }
    </style></head><body>
  <div class='header'>
  <div class='header-logos'>
   <div class='left'>" . (!empty($logoCoreBase64) ? "<img src='data:image/png;base64,{$logoCoreBase64}' alt='Logo Core'>" : "") . "</div>
   <div class='center'>
    <p class='header-title'>GOBIERNO REGIONAL REGIÓN DE VALPARAÍSO</p>
    <p class='header-title'>CONSEJO REGIONAL</p>

   </div>
   <div class='right'>" . (!empty($logoGoreBase64) ? "<img src='data:image/png;base64,{$logoGoreBase64}' alt='Logo Gore'>" : "") . "</div>
  </div>
  
  <div class='metadata'>
  <h2 class='main-title'>Listado de Asistencia</h2>
   <table class='metadata-grid'>
   <tr><td class='label'>Minuta N°:</td><td>{$idMinuta}</td></tr>
   <tr><td class='label'>Nombre de la Reunión:</td><td>{$nombreReunion}</td></tr>
   <tr><td class='label'>Secretario Técnico:</td><td>{$nombreSecretario}</td></tr>
   <tr><td class='label'>Fecha / Hora Reunión:</td><td>{$fechaMinuta} / {$horaMinuta}</td></tr>
   <tr><td class='label'>Comisión(es):</td><td>{$comisiones}</td></tr>
   </table>
   
   
  </div>
  </div>
  
  <table class='attendance-list'><thead><tr><th>N°</th><th>Nombre Completo</th><th>Estado de Asistencia</th><th>Origen</th></tr></thead><tbody>"; // MODIFICACIÓN AQUÍ

    if (empty($nombresAsistentes)) {
      $html .= "<tr><td colspan='4' style='text-align: center;'>No se encontró el listado de Consejeros/Presidentes de Comisión.</td></tr>"; // MODIFICACIÓN AQUÍ
    } else {
      foreach ($nombresAsistentes as $index => $miembro) {
        $estado = htmlspecialchars($miembro['estadoAsistencia']);
        $claseCss = ($estado === 'Presente') ? 'presente-cell' : 'ausente-cell';
        $fechaFirma = '';
                $origenFmt = '—'; // Nuevo: Valor por defecto

        // Mostrar fecha, hora y origen si está Presente y el dato existe
        if ($estado === 'Presente' && !empty($miembro['fechaHoraAsistenciaFmt'])) {
          $fechaFirma = " ({$miembro['fechaHoraAsistenciaFmt']})";

                    // Formateo del origen para el PDF
                    $origen = htmlspecialchars($miembro['origenAsistencia'] ?? 'N/A');
                    if ($origen === 'AUTOREGISTRO') {
                        $origenFmt = '<span style="color:#155724; font-weight:bold;">Usuario</span>';
                    } elseif ($origen === 'SECRETARIO') {
                        $origenFmt = '<span style="color:#007bff; font-style:italic;">Secretario</span>';
                    } else {
                        $origenFmt = $origen;
                    }
        }

        $html .= "<tr>
       <td>" . ($index + 1) . "</td>
       <td>" . htmlspecialchars($miembro['nombreCompleto']) . "</td>
       <td class='{$claseCss}'>" . $estado . $fechaFirma . "</td>
              <td style='text-align: center;'>" . $origenFmt . "</td> 
      </tr>"; // MODIFICACIÓN AQUÍ
      }
    }

    $html .= "</tbody></table>
  
  <div class='validation-block-container'>
        " . (!empty($selloUri) ? "<img src='{$selloUri}' alt='Sello de Aprobación' class='sello-fondo'>" : "") . "
   <div class='validation-content'>
    <strong>{$nombreSecretario}</strong>
    <em>{$cargoSecretario}</em>
    <p>Validación de Asistencia</p>
    <div class='dashed-line'></div>
    <p>" . (new DateTime())->format('d-m-Y H:i:s') . "</p>
   </div>
  </div>
  
  <div class='footer'>Generado por CoreVota el {$fechaGeneracion}</div></body></html>";

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('fontDir', __DIR__ . '/../vendor/dompdf/dompdf/lib/fonts');
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('Letter', 'portrait');
    $dompdf->render();

    $rutaBase = __DIR__ . '/../public/docs/asistencia/';
    if (!is_dir($rutaBase)) {
      mkdir($rutaBase, 0775, true);
    }
    $nombreArchivo = "asistencia_minuta_{$idMinuta}_{$fechaParaNombreArchivo}.pdf";
    $rutaCompleta = $rutaBase . $nombreArchivo;
    $relativePath = 'public/docs/asistencia/' . $nombreArchivo;

    file_put_contents($rutaCompleta, $dompdf->output());
    return $relativePath;
  }
  
// ... (resto de la clase MinutaManager) ...

  /**
  * Guarda la asistencia, los temas/acuerdos, los adjuntos, y actualiza la hora de término de la reunión.
  * Preserva la marca de tiempo de auto-asistencia del usuario si ya existía.
  */
  public function guardarMinutaCompleta($idMinuta, $asistenciaIDs, $temasData, $enlaceAdjunto)
  {
    $adjuntosGuardados = [];
    try {
      $this->db->beginTransaction();

      // --- 2. ACTUALIZAR ASISTENCIA (t_asistencia) ---

      // 2.1 RECUPERAR LAS FECHAS Y ORIGEN DE REGISTRO EXISTENTES ANTES DE BORRAR
      $fechasAsistenciaOriginales = [];
      
      // Convertir IDs a enteros y filtrar para una consulta segura
      $asistenciaIDs_clean = array_map('intval', array_filter($asistenciaIDs, 'is_numeric'));
      
      if (!empty($asistenciaIDs_clean)) {
        $placeholders = implode(',', array_fill(0, count($asistenciaIDs_clean), '?'));
        // MODIFICACIÓN CRÍTICA: Rescatar fechaRegistroAsistencia Y origenAsistencia
        $sqlFechasOriginales = "SELECT t_usuario_idUsuario, fechaRegistroAsistencia, origenAsistencia 
                    FROM t_asistencia 
                    WHERE t_minuta_idMinuta = ? AND t_usuario_idUsuario IN ({$placeholders})";
        $stmtFechasOriginales = $this->db->prepare($sqlFechasOriginales);
        
        // Parámetros: [idMinuta, idUsuario1, idUsuario2, ...]
        $paramsFechas = array_merge([$idMinuta], $asistenciaIDs_clean);
        $stmtFechasOriginales->execute($paramsFechas);
        
        // Mapear idUsuario a un array con la fecha y el origen
        foreach ($stmtFechasOriginales->fetchAll(PDO::FETCH_ASSOC) as $row) {
          $fechasAsistenciaOriginales[(int)$row['t_usuario_idUsuario']] = [
                        'fecha' => $row['fechaRegistroAsistencia'],
                        'origen' => $row['origenAsistencia']
                    ];
        }
      }


      // 2.2 ELIMINAR TODAS LAS ASISTENCIAS DE LA MINUTA (Reset)
      $sqlDeleteAsistencia = "DELETE FROM t_asistencia WHERE t_minuta_idMinuta = :idMinuta";
      $stmtDeleteAsistencia = $this->db->prepare($sqlDeleteAsistencia);
      $stmtDeleteAsistencia->execute([':idMinuta' => $idMinuta]);

      // 2.3 INSERTAR SOLO LOS USUARIOS MARCADOS COMO PRESENTES
      $idTipoReunion = 1; // Asumido
      if (!empty($asistenciaIDs_clean)) {
        // MODIFICACIÓN CRÍTICA: Añadir la columna origenAsistencia al INSERT
        $sqlAsistencia = "INSERT INTO t_asistencia (t_minuta_idMinuta, t_usuario_idUsuario, t_tipoReunion_idTipoReunion, fechaRegistroAsistencia, origenAsistencia)
                 VALUES (:idMinuta, :idUsuario, :idTipoReunion, :fechaAsistencia, :origen)";
        $stmtAsistencia = $this->db->prepare($sqlAsistencia);
        
        foreach ($asistenciaIDs_clean as $idUsuario) {
          
          $dataOriginal = $fechasAsistenciaOriginales[$idUsuario] ?? null;

          // 1. CLAVE: Usamos la fecha original preservada, o generamos NOW() si es un nuevo registro.
          $fechaRegistro = $dataOriginal['fecha'] ?? (new DateTime())->format('Y-m-d H:i:s');
          
                    // 2. CLAVE: Origen. Si el ST guarda la minuta, el origen es SECRETARIO (trazabilidad).
                    $origen = 'SECRETARIO';
          
          $stmtAsistencia->execute([
            ':idMinuta' => $idMinuta,
            ':idUsuario' => $idUsuario,
            ':idTipoReunion' => $idTipoReunion,
            ':fechaAsistencia' => $fechaRegistro, // <-- La fecha que respeta el auto-registro
                        ':origen' => $origen                     // <-- Nuevo: Trazabilidad del ST
          ]);
        }
      }

      // --- Generación PDF Asistencia (USANDO LÓGICA DE DETALLE Y FORMATO ACTUALIZADA) ---
      $dataAsistencia = $this->getNombresAsistentes($asistenciaIDs_clean, $idMinuta); 
      
      // Solo generar si se encontraron miembros (Tipo de Usuario 1 o 3)
      if (!empty($dataAsistencia['asistentes'])) {
        $rutaPdfAsistencia = $this->generarPdfAsistencia($idMinuta, $dataAsistencia);
        
        $sqlInsertAdjunto = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) VALUES (:idMinuta, :path, :tipo)";
        $stmtInsertAdjunto = $this->db->prepare($sqlInsertAdjunto);
        // Eliminar cualquier adjunto de 'asistencia' anterior para evitar duplicados
        $this->db->prepare("DELETE FROM t_adjunto WHERE t_minuta_idMinuta = :idMinuta AND tipoAdjunto = 'asistencia'")
            ->execute([':idMinuta' => $idMinuta]);

        $stmtInsertAdjunto->execute([
          ':idMinuta' => $idMinuta,
          ':path' => $rutaPdfAsistencia,
          ':tipo' => 'asistencia'
        ]);
        $lastAdjId = $this->db->lastInsertId();
        $adjuntosGuardados[] = ['idAdjunto' => $lastAdjId, 'pathAdjunto' => $rutaPdfAsistencia, 'tipoAdjunto' => 'asistencia'];
        error_log("DEBUG idMinuta {$idMinuta}: PDF de asistencia generado y guardado en la BD: {$rutaPdfAsistencia}");
      }


      // --- 3. ACTUALIZAR TEMAS Y ACUERDOS (SIN CAMBIOS) ---
      $idsTemasActuales = [];
      foreach ($temasData as $tema) {
        if (!empty($tema['idTema']) && is_numeric($tema['idTema'])) {
          $idsTemasActuales[] = $tema['idTema'];
        }
      }
      $sqlTemasEnDB = "SELECT idTema FROM t_tema WHERE t_minuta_idMinuta = :idMinuta";
      $stmtTemasEnDB = $this->db->prepare($sqlTemasEnDB);
      $stmtTemasEnDB->execute([':idMinuta' => $idMinuta]);
      $idsTemasEnDB = $stmtTemasEnDB->fetchAll(PDO::FETCH_COLUMN, 0);
      $idsTemasABorrar = array_diff($idsTemasEnDB, $idsTemasActuales);

      if (!empty($idsTemasABorrar)) {
        $placeholdersBorrar = implode(',', array_fill(0, count($idsTemasABorrar), '?'));
        $sqlDeleteAcuerdos = "DELETE FROM t_acuerdo WHERE t_tema_idTema IN ($placeholdersBorrar)";
        $stmtDeleteAcuerdos = $this->db->prepare($sqlDeleteAcuerdos);
        $stmtDeleteAcuerdos->execute($idsTemasABorrar);
        $sqlDeleteTemas = "DELETE FROM t_tema WHERE idTema IN ($placeholdersBorrar) AND t_minuta_idMinuta = ?";
        $paramsBorrarTemas = array_merge($idsTemasABorrar, [$idMinuta]);
        $stmtDeleteTemas = $this->db->prepare($sqlDeleteTemas);
        $stmtDeleteTemas->execute($paramsBorrarTemas);
      }
      $sqlInsertTema = "INSERT INTO t_tema (t_minuta_idMinuta, nombreTema, objetivo, compromiso, observacion) VALUES (:idMinuta, :nombre, :objetivo, :compromiso, :observacion)";
      $sqlUpdateTema = "UPDATE t_tema SET nombreTema = :nombre, objetivo = :objetivo, compromiso = :compromiso, observacion = :observacion WHERE idTema = :idTema AND t_minuta_idMinuta = :idMinuta";
      $stmtInsertTema = $this->db->prepare($sqlInsertTema);
      $stmtUpdateTema = $this->db->prepare($sqlUpdateTema);

      foreach ($temasData as $index => $tema) {
        $idTema = $tema['idTema'] ?? null;
        $paramsTema = [
          ':idMinuta' => $idMinuta,
          ':nombre' => trim($tema['nombreTema'] ?? ''),
          ':objetivo' => trim($tema['objetivo'] ?? ''),
          ':compromiso' => trim($tema['compromiso'] ?? ''),
          ':observacion' => trim($tema['observacion'] ?? '')
        ];
        if (empty($paramsTema[':nombre'])) {
          error_log("DEBUG idMinuta {$idMinuta}: Saltando tema {$index} por estar vacío.");
          continue;
        }

        if ($idTema && in_array($idTema, $idsTemasActuales)) { // ACTUALIZAR
          $paramsTema[':idTema'] = $idTema;
          $stmtUpdateTema->execute($paramsTema);
        } else { // INSERTAR
          $stmtInsertTema->execute($paramsTema);
          $idTema = $this->db->lastInsertId(); // Obtenemos el nuevo ID
        }

        $descAcuerdo = trim($tema['descAcuerdo'] ?? '');
        $sqlDeleteAcuerdo = "DELETE FROM t_acuerdo WHERE t_tema_idTema = :idTema";
        $stmtDelAc = $this->db->prepare($sqlDeleteAcuerdo);
        $stmtDelAc->execute([':idTema' => $idTema]);

        if ($idTema && !empty($descAcuerdo)) {
          $sqlInsertAcuerdo = "INSERT INTO t_acuerdo (descAcuerdo, t_tema_idTema, t_tipoReunion_idTipoReunion) 
                     VALUES (:descAcuerdo, :idTema, :idTipoReunion)";
          $stmtInsAc = $this->db->prepare($sqlInsertAcuerdo);
          $idTipoReunion = 1; // Asumido
          $stmtInsAc->execute([
            ':descAcuerdo' => $descAcuerdo,
            ':idTema' => $idTema,
            ':idTipoReunion' => $idTipoReunion
          ]);
        }
      }

      // --- 4. PROCESAR ADJUNTOS (Tu lógica sin cambios) ---
      $sqlInsertAdjunto = "INSERT INTO t_adjunto (t_minuta_idMinuta, pathAdjunto, tipoAdjunto) VALUES (:idMinuta, :path, :tipo)";
      $stmtInsertAdjunto = $this->db->prepare($sqlInsertAdjunto);
      $baseUploadPath = __DIR__ . '/../public/DocumentosAdjuntos/';
      $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'mp4', 'ppt', 'pptx', 'doc', 'docx'];

      if (isset($_FILES['adjuntos']) && !empty($_FILES['adjuntos']['name'][0])) {
        $files = $_FILES['adjuntos'];
        $numFiles = count($files['name']);

        for ($i = 0; $i < $numFiles; $i++) {
          $fileName = $files['name'][$i];
          $tmpName = $files['tmp_name'][$i];
          $fileError = $files['error'][$i];

          if ($fileError === UPLOAD_ERR_OK) {
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExtension, $allowedExtensions)) {
              $targetDir = $baseUploadPath . strtoupper($fileExtension) . '/';
              if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0775, true)) {
                  throw new Exception("Error al crear directorio de subida: " . $targetDir);
                }
              }
              $safeOriginalName = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($fileName));
              $newFileName = uniqid('adj_', true) . '_' . $safeOriginalName;
              $targetPath = $targetDir . $newFileName;
              // (Ruta corregida para la BD)
              $relativePath = 'public/DocumentosAdjuntos/' . strtoupper($fileExtension) . '/' . $newFileName;

              if (move_uploaded_file($tmpName, $targetPath)) {
                $stmtInsertAdjunto->execute([
                  ':idMinuta' => $idMinuta,
                  ':path' => $relativePath,
                  ':tipo' => 'file'
                ]);
                $lastAdjId = $this->db->lastInsertId();
                $adjuntosGuardados[] = ['idAdjunto' => $lastAdjId, 'pathAdjunto' => $relativePath, 'tipoAdjunto' => 'file'];
              } else {
                throw new Exception("Error al mover el archivo subido: " . $fileName);
              }
            }
          }
        }
      }

      if (!empty($enlaceAdjunto)) {
        $enlaceSanitized = filter_var(trim($enlaceAdjunto), FILTER_SANITIZE_URL);
        if (filter_var($enlaceSanitized, FILTER_VALIDATE_URL)) {
          $stmtInsertAdjunto->execute([
            ':idMinuta' => $idMinuta,
            ':path' => $enlaceSanitized,
            ':tipo' => 'link'
          ]);
          $lastAdjId = $this->db->lastInsertId();
          $adjuntosGuardados[] = ['idAdjunto' => $lastAdjId, 'pathAdjunto' => $enlaceSanitized, 'tipoAdjunto' => 'link'];
        }
      }

      // --- 5. ACTUALIZAR HORA DE TÉRMINO DE LA REUNIÓN (Tu lógica sin cambios) ---
      $sql_find_reunion = "SELECT idReunion, fechaTerminoReunion FROM t_reunion WHERE t_minuta_idMinuta = :idMinuta LIMIT 1";
      $stmt_find = $this->db->prepare($sql_find_reunion);
      $stmt_find->execute([':idMinuta' => $idMinuta]);
      $reunion = $stmt_find->fetch(PDO::FETCH_ASSOC);
      $mensajeExito = 'Borrador guardado con éxito.';

      if (empty($reunion['fechaTerminoReunion']) && $reunion) {
        $idReunion = $reunion['idReunion'];
        $sql_update_termino = "UPDATE t_reunion SET fechaTerminoReunion = NOW() WHERE idReunion = :idReunion";
        $stmt_update = $this->db->prepare($sql_update_termino);
        $stmt_update->execute([':idReunion' => $idReunion]);
        $mensajeExito = 'Borrador guardado y hora de término de reunión actualizada.';
      }

      // --- 6. ASEGURAR QUE EL ESTADO SEA 'BORRADOR' ---
      $sqlSetBorrador = "UPDATE t_minuta 
                 SET estadoMinuta = 'BORRADOR' 
                 WHERE idMinuta = :idMinuta 
                 AND estadoMinuta <> 'APROBADA'";
      $this->db->prepare($sqlSetBorrador)->execute([':idMinuta' => $idMinuta]);

      // --- 7. COMMIT ---
      $this->db->commit();

      // --- Opcional: Obtener lista completa de adjuntos para devolver ---
      $sqlTodosAdjuntos = "SELECT idAdjunto, pathAdjunto, tipoAdjunto FROM t_adjunto WHERE t_minuta_idMinuta = :idMinuta ORDER BY idAdjunto";
      $stmtTodosAdjuntos = $this->db->prepare($sqlTodosAdjuntos);
      $stmtTodosAdjuntos->execute([':idMinuta' => $idMinuta]);
      $listaCompletaAdjuntos = $stmtTodosAdjuntos->fetchAll(PDO::FETCH_ASSOC);

      return ['status' => 'success', 'message' => $mensajeExito, 'idMinuta' => $idMinuta, 'adjuntosActualizados' => $listaCompletaAdjuntos];
    } catch (Exception $e) {
      error_log("ERROR CATCH idMinuta {$idMinuta}: Excepción capturada - " . $e->getMessage());
      if ($this->db->inTransaction()) {
        error_log("ERROR CATCH idMinuta {$idMinuta}: Realizando db->rollBack().");
        $this->db->rollBack();
      }
      return ['status' => 'error', 'message' => 'Ocurrió un error al guardar los datos.', 'error' => $e->getMessage()];
    }
  }
  
} // <-- Cierre de la clase MinutaManager

// --- INICIO DEL CÓDIGO DE EJECUCIÓN (SIN CAMBIOS) ---
$manager = null;
$resultado = null;

try {
  // 1. Instanciar el manager
  $manager = new MinutaManager();

  // 2. Llamar al método principal con los datos ya validados
  $resultado = $manager->guardarMinutaCompleta(
    $idMinuta,
    $asistenciaIDs,
    $temasData,
    $enlaceAdjunto
  );

  // 3. Establecer el código de respuesta HTTP basado en el resultado
  if (isset($resultado['status']) && $resultado['status'] === 'error') {
    http_response_code(500);
  } else {
    http_response_code(200);
  }
} catch (Exception $e) {
  // Captura errores en la *creación* de MinutaManager o fallos no capturados
  http_response_code(500);
  error_log("ERROR CRÍTICO en guardar_minuta_completa.php (fuera del método): " . $e->getMessage());
  $resultado = [
    'status' => 'error',
    'message' => 'Error fatal del script.',
    'error' => $e->getMessage()
  ];
}

// 4. Enviar la respuesta JSON al frontend
echo json_encode($resultado);


exit;