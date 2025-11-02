<?php
// views/pages/minutas_listado_general.php

// --- INICIO DE MODIFICACIÓN: Conexión a BD ---
// Necesaria para consultar el estado de firma individual en la lista
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../class/class.conectorDB.php';
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
} catch (Exception $e) {
    // Si la app está funcionando, $pdo no debería ser null
    // Si falla, los botones de firma no aparecerán.
    $pdo = null;
    error_log("Error de conexión BD en minutas_listado_general.php: " . $e->getMessage());
}
// --- FIN DE MODIFICACIÓN ---


// Variables esperadas del Controlador:
// $minutas (array), $estadoActual (string), $currentStartDate (string), $currentEndDate (string), $currentThemeName (string)

$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;

// Determinar el título y la página del formulario
$estadoActual = $estadoActual ?? 'PENDIENTE';
$pageTitle = ($estadoActual === 'APROBADA') ? 'Minutas Aprobadas' : 'Minutas Pendientes';
$paginaForm = ($estadoActual === 'APROBADA') ? 'minutas_aprobadas' : 'minutas_pendientes';

// Usar fechas de la URL si existen, si no, usar mes actual
$currentStartDate = $_GET['startDate'] ?? date('Y-m-01');
$currentEndDate   = $_GET['endDate']   ?? date('Y-m-d');

// Palabra clave (para buscar en Tema y Objetivo)
$currentThemeName = $_GET['themeName'] ?? '';

// ---------- PREFILTRO: buscar en Tema y Objetivo (vista) ----------
$minutasFiltradas = $minutas;

// Normalizador robusto (quita <br>, tags, decodifica entidades y pasa a minúsculas)
$__normalize = function ($s) {
    $s = (string)$s;
    $s = preg_replace('/<br\s*\/?>/i', ' ', $s);
    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = mb_strtolower($s, 'UTF-8');
    $s = trim($s);
    if (in_array($s, ['n/a', 'na', '-'], true)) $s = '';
    return $s;
};

if (is_array($minutasFiltradas ?? null) && $currentThemeName !== '') {
    $needle = mb_strtolower(trim($currentThemeName), 'UTF-8');

    // claves posibles para tema y objetivo (por si cambian los alias en otra parte)
    $temaKeys = ['nombreTemas', 'nombreTema', 'temas', 'tema'];
    $objKeys  = ['objetivos', 'objetivo', 'objetivosTexto'];

    $minutasFiltradas = array_values(array_filter($minutasFiltradas, function ($m) use ($needle, $__normalize, $temaKeys, $objKeys) {
        $temas = '';
        foreach ($temaKeys as $k) {
            if (isset($m[$k]) && $m[$k] !== null && $m[$k] !== '') {
                $temas .= ' ' . $m[$k];
            }
        }
        $objs = '';
        foreach ($objKeys as $k) {
            if (isset($m[$k]) && $m[$k] !== null && $m[$k] !== '') {
                $objs .= ' ' . $m[$k];
            }
        }

        $temasNorm = $__normalize($temas);
        $objsNorm  = $__normalize($objs);

        // Coincide si aparece en Tema o en Objetivo
        return (mb_stripos($temasNorm, $needle, 0, 'UTF-8') !== false) ||
           (mb_stripos($objsNorm,  $needle, 0, 'UTF-8') !== false);
    }));
}

// ---------- Paginación en la vista ----------
$perPage  = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$page     = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset   = ($page - 1) * $perPage;
$total    = (is_array($minutasFiltradas ?? null)) ? count($minutasFiltradas) : 0;
$pages    = max(1, (int)ceil(($total ?: 1) / $perPage));
$minutasPage = array_slice($minutasFiltradas ?? [], $offset, $perPage);

// Helper paginación
function renderPaginationListado($current, $pages)
{
    if ($pages <= 1) return;
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $current) ? ' active' : '';
        $qsArr  = $_GET;
        $qsArr['p'] = $i;
        $qs = http_build_query($qsArr);
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . $qs . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}
?>

<div class="container-fluid mt-4">
    <h3 class="mb-3"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h3>

    <form method="GET" class="mb-4 p-3 border rounded bg-light">
        <input type="hidden" name="pagina" value="<?php echo htmlspecialchars($paginaForm, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="estado" value="<?php echo htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="startDate" class="form-label">Fecha Creación Desde:</label>
                <input type="date" class="form-control form-control-sm" id="startDate" name="startDate" value="<?php echo htmlspecialchars($currentStartDate ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label">Fecha Creación Hasta:</label>
                <input type="date" class="form-control form-control-sm" id="endDate" name="endDate" value="<?php echo htmlspecialchars($currentEndDate ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <?php if ($estadoActual === 'APROBADA'): ?>
                    <label for="themeName" class="form-label">Buscar por palabra clave</label>
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        id="themeName"
                        name="themeName"
                        placeholder="Busca en “Nombre(s) del Tema” u “Objetivo(s)”…"
                        value="<?php echo htmlspecialchars($currentThemeName ?? ''); ?>">
                <?php else: ?>
                    <input type="hidden" id="themeName" name="themeName" value="">
                <?php endif; ?>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="table-responsive shadow-sm">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark sticky-top">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Nombre(s) del Tema</th>
                    <th scope="col">Objetivo(s)</th>
                    <th scope="col">Fecha Creación</th>
                    <th scope="col" class="text-center">Adjuntos</th>
                    <th scope="col">Revisar</th>
                    <th scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($minutasPage) || !is_array($minutasPage)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No hay minutas que coincidan con los filtros aplicados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($minutasPage as $minuta): ?>
                        <tr>
                            <?php
                            $minutaId = $minuta['idMinuta'];
                            $estado = $minuta['estadoMinuta'] ?? 'PENDIENTE';
                            $fechaCreacion = $minuta['fechaMinuta'] ?? 'N/A';
                            $totalAdjuntos = (int)($minuta['totalAdjuntos'] ?? 0);

                            // --- INICIO DE MODIFICACIÓN: Lógica de Firma Individual ---
                            $estadoFirmaUsuario = null;
                            $esPresidenteRequerido = false;

                            if ($pdo && $idUsuarioLogueado && $estado !== 'APROBADA') {
                                try {
                                    $sqlFirma = $pdo->prepare("SELECT estado_firma FROM t_aprobacion_minuta 
                                                                WHERE t_minuta_idMinuta = :idMinuta 
                                                                AND t_usuario_idPresidente = :idUsuario");
                                    $sqlFirma->execute([':idMinuta' => $minutaId, ':idUsuario' => $idUsuarioLogueado]);
                                    $estadoFirmaUsuario = $sqlFirma->fetchColumn(); 

                                    if ($estadoFirmaUsuario !== false) { // false = no es firmante
                                        $esPresidenteRequerido = true;
                                    }
                                } catch (Exception $e) {
                                    error_log("Error query firma en lista (ID Minuta: $minutaId): " . $e->getMessage());
                                }
                            }
                            // --- FIN DE MODIFICACIÓN ---
                            ?>

                            <td><?php echo htmlspecialchars($minutaId); ?></td>
                            <td><?php echo $minuta['nombreTemas'] ?? 'N/A'; ?></td>
                            <td><?php echo $minuta['objetivos'] ?? 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($fechaCreacion); ?></td>

                            <td class="text-center">
                                <?php if ($totalAdjuntos > 0): ?>
                                    <button type="button" class="btn btn-info btn-sm"
                                        title="Ver adjuntos"
                                        onclick="verAdjuntos(<?php echo (int)$minutaId; ?>)">
                                        <i class="fas fa-paperclip"></i> (<?php echo $totalAdjuntos; ?>)
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">No posee archivo adjunto</span>
                                <?php endif; ?>
                            </td>

                            <td style="white-space: nowrap;">
                                <?php if ($estado === 'PENDIENTE' || $estado === 'PARCIAL'): ?>
                                    
                                    <?php if ($esPresidenteRequerido && $estadoFirmaUsuario === 'REQUIERE_REVISION'): ?>
                                        <a href="menu.php?pagina=editar_minuta&id=<?php echo (int)$minutaId; ?>" class="btn btn-sm btn-warning fw-bold">
                                            ⚠️ Revisar Cambios
                                        </a>
                                    <?php else: ?>
                                        <a href="menu.php?pagina=editar_minuta&id=<?php echo (int)$minutaId; ?>" class="btn btn-sm btn-info text-white" title="Revisar/Editar Minuta">
                                            Revisar / Editar
                                        </a>
                                    <?php endif; ?>

                                <?php elseif ($estado === 'APROBADA'): ?>
                                    <a href="<?php echo htmlspecialchars($minuta['pathArchivo'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-secondary <?php echo empty($minuta['pathArchivo']) ? 'disabled' : ''; ?>">
                                        Ver PDF Final
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if ($estado === 'PENDIENTE' || $estado === 'PARCIAL'): ?>
                                    
                                    <?php if ($esPresidenteRequerido): ?>
                                        <?php if ($estadoFirmaUsuario === 'REQUIERE_REVISION'): ?>
                                            <span class="badge bg-warning text-dark">Requiere Re-Firma</span>
                                        
                                        <?php elseif ($estadoFirmaUsuario === 'EN_ESPERA'): ?>
                                            <button type="button" class="btn btn-sm btn-success" disabled>
                                                ✅ Firma Registrada (En Espera)
                                            </button>
                                        
                                        <?php else: // $estadoFirmaUsuario es null (pendiente) ?>
                                            <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="aprobarMinuta(<?php echo (int)$minutaId; ?>)">
                                                🔒 Registrar Mi Firma
                                            </button>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Gestión ST</span>
                                    <?php endif; ?>

                                <?php elseif ($estado === 'APROBADA'): ?>
                                    <span class="badge bg-success">Minuta Aprobada</span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    // --- INICIO DE MODIFICACIÓN: Cerrar conexión ---
    if ($pdo) {
        $pdo = null;
    }
    // --- FIN DE MODIFICACIÓN ---
    ?>

    <?php renderPaginationListado($page, $pages); ?>
</div>

<div class="modal fade" id="modalAdjuntos" tabindex="-1" aria-labelledby="modalAdjuntosLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAdjuntosLabel">Documentos Adjuntos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul id="listaDeAdjuntos" class="list-group list-group-flush">
                    <li class="list-group-item text-muted">Cargando...</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>