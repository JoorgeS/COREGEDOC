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
$perPage   = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$page      = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset    = ($page - 1) * $perPage;
$total     = (is_array($minutasFiltradas ?? null)) ? count($minutasFiltradas) : 0;
$pages     = max(1, (int)ceil(($total ?: 1) / $perPage));
$minutasPage = array_slice($minutasFiltradas ?? [], $offset, $perPage);

if (!is_array($minutasFiltradas)) {
        $minutasFiltradas = []; // Si no es un array, la convertimos en un array vacío
    }

    // (Esta es tu línea 90, que ahora es segura y no fallará)
    $minutasPaginadas = array_slice($minutasFiltradas, $offset, $perPage);

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
                    <th scope="col">Estado</th>
                    <th scope="col" class="text-end">Acciones</th>
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
                            //$estado = $minuta['estadoMinuta'] ?? 'PENDIENTE'; // Esta variable se redefine abajo
                            $fechaCreacion = $minuta['fechaMinuta'] ?? 'N/A';
                            $totalAdjuntos = (int)($minuta['totalAdjuntos'] ?? 0);

                            // --- INICIO DE MODIFICACIÓN: Lógica de Firma Individual ---
                            // (Se mantiene por si se usa en otro lado, aunque la nueva lógica no la usa)
                            $estadoFirmaUsuario = null;
                            $esPresidenteRequerido = false;

                            if ($pdo && $idUsuarioLogueado && ($minuta['estadoMinuta'] ?? 'PENDIENTE') !== 'APROBADA') {
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

                            <?php
                            // --- (INTEGRADO) INICIO DE AJUSTE: Lógica de Estado General (Secretario) ---
                            $estado = $minuta['estadoMinuta'];
                            $firmasActuales = (int)($minuta['firmasActuales'] ?? 0);
                            $requeridos = max(1, (int)($minuta['presidentesRequeridos'] ?? 1));
                            $tieneFeedback = (int)($minuta['tieneFeedback'] > 0);

                            $statusText = 'Aprobación Pendiente';
                            $statusClass = 'text-warning'; // Amarillo

                            if ($tieneFeedback) {
                                $statusText = 'Requiere Revisión (Feedback)';
                                $statusClass = 'text-danger'; // Rojo
                            } elseif ($estado === 'PARCIAL') {
                                $statusText = "Aprobación Parcial ($firmasActuales / $requeridos)";
                                $statusClass = 'text-info'; // Azul claro
                            } elseif ($estado === 'PENDIENTE') {
                                $statusText = "Pendiente de Firma ($firmasActuales / $requeridos)";
                                $statusClass = 'text-warning';
                            } elseif ($estado === 'APROBADA') {
                                $statusText = 'Aprobada y Finalizada';
                                $statusClass = 'text-success'; // Verde
                            } elseif ($estado === 'BORRADOR') {
                                $statusText = 'Borrador (No enviado)';
                                $statusClass = 'text-muted'; // Gris
                            }
                            // --- FIN DE AJUSTE ---
                            ?>

                            <td>
                                <strong class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></strong>
                            </td>

                            <td class="text-end" style="white-space: nowrap;">
                                <?php if ($estado === 'BORRADOR'): ?>
                                    <button class="btn btn-danger btn-sm" onclick="enviarAprobacion(<?php echo $minuta['idMinuta']; ?>)">
                                        <i class="fas fa-paper-plane"></i> Enviar p/ Aprobación
                                    </button>
                                <?php elseif ($tieneFeedback): ?>
                                    <button class="btn btn-success btn-sm" onclick="aplicarFeedback(<?php echo $minuta['idMinuta']; ?>)">
                                        <i class="fas fa-check-double"></i> Aplicar Corrección y Reenviar
                                    </button>
                                <?php endif; ?>

                                <a href="/corevota/controllers/generar_pdf_borrador.php?id=<?php echo $minuta['idMinuta']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Ver Borrador PDF">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <a href="menu.php?pagina=editar_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-outline-primary btn-sm" title="Editar Minuta">
                                    <i class="fas fa-edit"></i>
                                </a>
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

<script>
/**
 * (INTEGRADO)
 * Confirma y reenvía una minuta que tenía feedback para aprobación.
 */
function aplicarFeedback(idMinuta) {
    if (!confirm('¿Confirma que ya aplicó las correcciones y desea reenviar la minuta para su aprobación?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('idMinuta', idMinuta);

    // Asegúrate que esta ruta sea correcta desde la raíz de tu proyecto
    fetch('../controllers/aplicar_feedback.php', { 
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error en aplicarFeedback:', error)
        alert('Ocurrió un error de conexión al aplicar el feedback.');
    });
}

/**
 * Envía una minuta en estado BORRADOR para aprobación por primera vez.
 * (Placeholder: Debes crear el controlador 'enviar_aprobacion.php')
 */
function enviarAprobacion(idMinuta) {
    if (!confirm('¿Está seguro de que desea enviar esta minuta para aprobación? Una vez enviada, no podrá editarla a menos que reciba feedback.')) {
        return;
    }

    const formData = new FormData();
    formData.append('idMinuta', idMinuta);

    // Asegúrate que esta ruta sea correcta
    fetch('../controllers/enviar_aprobacion.php', { // DEBES CREAR ESTE ARCHIVO
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error en enviarAprobacion:', error);
        alert('Ocurrió un error de conexión al enviar la minuta.');
    });
}

/**
 * Muestra el modal con la lista de adjuntos (Placeholder)
 * (Esta función probablemente ya existe en tu JS principal, si no, debes implementarla)
 */
function verAdjuntos(idMinuta) {
    console.log("Solicitando adjuntos para la minuta ID:", idMinuta);
    const modalElement = document.getElementById('modalAdjuntos');
    const modalList = document.getElementById('listaDeAdjuntos');
    
    if (!modalElement || !modalList) {
        console.error("No se encontraron los elementos del modal.");
        return;
    }

    // Mostrar modal (requiere Bootstrap)
    const modal = new bootstrap.Modal(modalElement);
    
    // Limpiar lista y mostrar 'Cargando...'
    modalList.innerHTML = '<li class="list-group-item text-muted">Cargando...</li>';
    modal.show();

    // Lógica (fetch) para obtener los adjuntos
    // (Debes crear un controlador 'get_adjuntos.php' o similar)
    /*
    fetch('../controllers/get_adjuntos.php?id=' + idMinuta)
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success' && data.adjuntos.length > 0) {
            modalList.innerHTML = ''; // Limpiar 'Cargando...'
            data.adjuntos.forEach(adj => {
                // Asumiendo que 'adj.url' es la ruta y 'adj.nombre' es el nombre
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.innerHTML = `
                    <span><i class="fas fa-file-alt me-2"></i> ${adj.nombre}</span>
                    <a href="${adj.url}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download"></i>
                    </a>
                `;
                modalList.appendChild(li);
            });
        } else {
             modalList.innerHTML = '<li class="list-group-item text-muted">No se encontraron adjuntos.</li>';
        }
    })
    .catch(error => {
        console.error('Error al cargar adjuntos:', error);
        modalList.innerHTML = '<li class="list-group-item text-danger">Error al cargar adjuntos.</li>';
    });
    */
    
    // --- INICIO DE CÓDIGO DE EJEMPLO (BORRAR DESPUÉS) ---
    // Simulación de carga (reemplaza esto con tu 'fetch')
    setTimeout(() => {
         modalList.innerHTML = `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-pdf me-2"></i> Documento_Ejemplo_1.pdf</span>
                <a href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-image me-2"></i> Imagen_Referencia.png</span>
                <a href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
            </li>
         `;
    }, 1000);
    // --- FIN DE CÓDIGO DE EJEMPLO ---
}
</script>