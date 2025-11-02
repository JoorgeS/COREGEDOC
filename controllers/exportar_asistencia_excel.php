<?php
// controllers/exportar_asistencia_excel.php

// 0. Habilitar reporte de errores (solo para desarrollo)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Incluir dependencias
require_once __DIR__ . '/../vendor/autoload.php'; // Para PhpSpreadsheet
require_once __DIR__ . '/../class/class.conectorDB.php'; // Tu conector

// Usar las clases de PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 2. Obtener y validar idMinuta de la URL
$idMinuta = isset($_GET['idMinuta']) ? filter_input(INPUT_GET, 'idMinuta', FILTER_VALIDATE_INT) : 0;

if ($idMinuta === false || $idMinuta <= 0) {
    exit("Error: ID de Minuta no válido o no proporcionado.");
}

// 3. Crear instancia del conectorDB
try {
    $db = new conectorDB(); // Usamos tu clase existente
} catch (Exception $e) {
    error_log("Error al instanciar conectorDB en exportar_asistencia_excel.php: " . $e->getMessage());
    exit("Error interno del servidor al inicializar la conexión.");
}

// 4. Preparar la consulta SQL y los valores
// (La consulta SQL usa placeholders como ':idMinuta')
// Dentro de controllers/exportar_asistencia_excel.php (YA ESTÁ ASÍ):
// ...
$sql = "SELECT
            u.idUsuario,
            u.pNombre,
            u.sNombre,
            u.aPaterno,
            u.aMaterno,
            u.correo,
            a.fechaHoraRegistro, // <-- Correcto
            a.estado             // <-- Correcto
        FROM t_asistencia a
        JOIN t_usuario u ON a.t_usuario_idUsuario = u.idUsuario // <-- Correcto
        WHERE a.t_minuta_idMinuta = :idMinuta                // <-- Correcto
        ORDER BY u.aPaterno, u.aMaterno, u.pNombre";
// ...
$valores = [
    'idMinuta' => $idMinuta
];
// ...
$asistentes = $db->consultarBD($sql, $valores); // Llama a la función
// ...
if ($asistentes === false) { // <-- Este es el bloque que se ejecuta y muestra el error
    error_log("Error devuelto por consultarBD en exportar_asistencia_excel.php para Minuta ID: $idMinuta. SQL: $sql. Valores: " . print_r($valores, true));
    exit("Error al consultar la base de datos para generar el Excel. Contacte al administrador.");
}
// ... (resto del código) ...
if (empty($asistentes)) {
    // Mensaje si no se encontraron asistentes
    echo "<!DOCTYPE html><html><head><title>Sin Asistencia</title></head><body>";
    echo "<h1>Asistencia no encontrada</h1>";
    echo "<p>No hay registros de asistencia para la Minuta ID: " . htmlspecialchars($idMinuta) . ".</p>";
    echo "<p>Por favor, guarde la asistencia antes de intentar exportar.</p>";
    echo '<button onclick="window.history.back();">Volver</button>';
    echo "</body></html>";
    exit;
}

// --- Si llegamos aquí, $asistentes es un array con los datos ---

// 7. Crear el objeto Spreadsheet
try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Asistencia Minuta ' . $idMinuta);
} catch (Exception $e) {
    error_log("Error al crear objeto Spreadsheet: " . $e->getMessage());
    exit("Error interno al inicializar la generación del archivo Excel.");
}

// --- Generación del Excel (sin cambios desde aquí) ---
try {
    // 8. Escribir Cabeceras
    $sheet->setCellValue('A1', 'ID Usuario');
    $sheet->setCellValue('B1', 'Nombre Completo');
    $sheet->setCellValue('C1', 'Correo Electrónico');
    $sheet->setCellValue('D1', 'Fecha/Hora Registro');
    $sheet->setCellValue('E1', 'Estado');

    // Estilo cabeceras
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

    // 9. Escribir Datos
    $row = 2;
    foreach ($asistentes as $asistente) {
        $nombreCompleto = implode(' ', array_filter([
            $asistente['pNombre'],
            $asistente['sNombre'],
            $asistente['aPaterno'],
            $asistente['aMaterno']
        ]));

        $sheet->setCellValue('A' . $row, $asistente['idUsuario']);
        $sheet->setCellValue('B' . $row, $nombreCompleto);
        $sheet->setCellValue('C' . $row, $asistente['correo'] ?? 'N/A');
        $sheet->setCellValue('D' . $row, $asistente['fechaHoraAsistencia'] ?? 'N/A');
        $sheet->setCellValue('E' . $row, $asistente['estadoAsistencia'] ?? 'N/A');
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
    }

    // 10. Ajustar Ancho Columnas
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setWidth(40);
    $sheet->getColumnDimension('C')->setWidth(35);
    $sheet->getColumnDimension('D')->setAutoSize(true);
    $sheet->getColumnDimension('E')->setAutoSize(true);

    // 11. Crear Writer y Headers HTTP
    $writer = new Xlsx($spreadsheet);
    $filename = 'asistencia_minuta_' . $idMinuta . '_' . date('Ymd_His') . '.xlsx';

    if (ob_get_level()) ob_end_clean(); // Limpiar buffer

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // 12. Enviar archivo
    $writer->save('php://output');
    exit;
} catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
    error_log("Error de PhpSpreadsheet al generar Excel: " . $e->getMessage());
    exit("Error interno al generar el archivo Excel.");
} catch (Exception $e) {
    error_log("Error inesperado al generar Excel: " . $e->getMessage());
    exit("Ocurrió un error inesperado al generar el archivo.");
}
