<?php
// views/pages/minutas_listado_general.php

// --- INICIO DE MODIFICACIÓN: Conexión a BD ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../class/class.conectorDB.php';
try {
    $db = new conectorDB();
    $pdo = $db->getDatabase();
} catch (Exception $e) {
    $pdo = null;
    error_log("Error de conexión BD en minutas_listado_general.php: " . $e->getMessage());
}
// --- FIN DE MODIFICACIÓN ---


// Variables esperadas del Controlador:
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
$rol = $_SESSION['idRol'] ?? null;

// Determinar el título y la página del formulario
$estadoActual = $estadoActual ?? 'PENDIENTE';
$pageTitle = ($estadoActual === 'APROBADA') ? 'Minutas Aprobadas' : 'Minutas Pendientes';
$paginaForm = ($estadoActual === 'APROBADA') ? 'minutas_aprobadas' : 'minutas_pendientes';

// Usar fechas de la URL si existen, si no, usar mes actual
$currentStartDate = $_GET['startDate'] ?? date('Y-m-01');
$currentEndDate = $_GET['endDate'] ?? date('Y-m-d');
$currentThemeName = $_GET['themeName'] ?? '';


// --- INICIO DE MODIFICACIÓN (Paso 1: Definir Pestaña) ---
$esSecretarioTecnico = ($rol == 2);
$activeTab = $_GET['tab'] ?? 'borradores'; // 'borradores' o 'pendientes_aprobacion'
$esPaginaPendientes = ($estadoActual === 'PENDIENTE');
// --- FIN DE MODIFICACIÓN ---


// ---------- (CÓDIGO ACTUAL) PREFILTRO: buscar en Tema y Objetivo (vista) ----------
$minutasFiltradas = $minutas;
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
    $temaKeys = ['nombreTemas', 'nombreTema', 'temas', 'tema'];
    $objKeys = ['objetivos', 'objetivo', 'objetivosTexto'];
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
        $objsNorm = $__normalize($objs);
        return (mb_stripos($temasNorm, $needle, 0, 'UTF-8') !== false) ||
            (mb_stripos($objsNorm, $needle, 0, 'UTF-8') !== false);
    }));
}

// --- INICIO DE MODIFICACIÓN (Paso 3: Filtrar por Pestaña) ---
if ($esSecretarioTecnico && $esPaginaPendientes) {
    if ($activeTab === 'borradores') {
        // TAREAS DEL ST: 'BORRADOR' o si tiene feedback ('REQUIERE_REVISION')
        $pageTitle = "Mis Tareas (Borradores y Feedback)"; // Sobrescribir título
        $minutasFiltradas = array_filter($minutasFiltradas, function ($m) {
            // $m['tieneFeedback'] > 0 es verdadero si estadoMinuta es REQUIERE_REVISION
            // (Añadimos BORRADOR que viene de reunion_listado)
            $estado = $m['estadoMinuta'] ?? null;
            return $estado === 'BORRADOR' || ($m['tieneFeedback'] > 0);
        });
    } else { // 'pendientes_aprobacion'
        // EN ESPERA DE PRESIDENTES: 'PENDIENTE' o 'PARCIAL' (y sin feedback)
        $pageTitle = "Minutas en Espera de Firma"; // Sobrescribir título
        $minutasFiltradas = array_filter($minutasFiltradas, function ($m) {
            $estado = $m['estadoMinuta'] ?? null;
            return (in_array($estado, ['PENDIENTE', 'PARCIAL']) && ($m['tieneFeedback'] == 0));
        });
    }
}
// --- FIN DE MODIFICACIÓN ---


// ---------- Paginación en la vista ----------
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $perPage;
$total = (is_array($minutasFiltradas ?? null)) ? count($minutasFiltradas) : 0;
$pages = max(1, (int)ceil(($total ?: 1) / $perPage));
if (!is_array($minutasFiltradas)) {
    $minutasFiltradas = [];
}
$minutasPaginadas = array_slice($minutasFiltradas, $offset, $perPage);

// Helper paginación
function renderPaginationListado($current, $pages)
{
    if ($pages <= 1) return;
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $current) ? ' active' : '';

        // --- INICIO MODIFICACIÓN (Paso 4A: Paginación) ---
        $qsArr = $_GET;
        if (isset($_GET['tab'])) $qsArr['tab'] = $_GET['tab']; // <-- Añadido
        // --- FIN MODIFICACIÓN ---

        $qsArr['p'] = $i;
        $qs = http_build_query($qsArr);
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . $qs . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}
?>

<div class="container-fluid mt-4">
    <h3 class="mb-3"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h3>

    <?php
    // --- INICIO DE MODIFICACIÓN (Paso 2: HTML de Pestañas) ---
    // Solo mostramos las pestañas si somos ST y estamos en la página de "Pendientes"
    if ($esSecretarioTecnico && $esPaginaPendientes):
    ?>
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?php echo ($activeTab === 'borradores') ? 'active' : ''; ?>" 
                   href="menu.php?pagina=minutas_pendientes&tab=borradores">
                    <i class="fas fa-edit me-1"></i> Mis Tareas (Borradores y Feedback)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($activeTab === 'pendientes_aprobacion') ? 'active' : ''; ?>" 
                   href="menu.php?pagina=minutas_pendientes&tab=pendientes_aprobacion">
                    <i class="fas fa-clock me-1"></i> En Espera de Firma
                </a>
            </li>
        </ul>
    <?php endif;
    // --- FIN DE MODIFICACIÓN ---
    ?>

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
                <label for="themeName" class="form-label">Buscar por palabra clave</label>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    id="themeName"
                    name="themeName"
                    placeholder="Busca en “Nombre(s) del Tema” u “Objetivo(s)”…"
                    value="<?php echo htmlspecialchars($currentThemeName ?? ''); ?>">
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
                    <th scope="col">Comisión</th>
                    <th scope="col">Nombre(s) del Tema</th>
                    <th scope="col">Fecha Creación</th>
                    <th scope="col" class="text-center">Adjuntos</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($minutasPaginadas) || !is_array($minutasPaginadas)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <?php 
                            // --- INICIO MODIFICACIÓN (Paso 4B) ---
                            if ($esSecretarioTecnico && $esPaginaPendientes && $activeTab === 'borradores') {
                                echo "No tienes minutas en borrador o que requieran revisión.";
                            } elseif ($esSecretarioTecnico && $esPaginaPendientes && $activeTab === 'pendientes_aprobacion') {
                                echo "No hay minutas en espera de firma.";
                            } else {
                                echo "No hay minutas que coincidan con los filtros aplicados.";
                            }
                            // --- FIN MODIFICACIÓN ---
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($minutasPaginadas as $minuta): ?>
                        <tr>
                            <?php
                            $minutaId = $minuta['idMinuta'];
                            $estado = $minuta['estadoMinuta'] ?? 'PENDIENTE';
                            $fechaCreacion = $minuta['fechaMinuta'] ?? 'N/A';
                            $totalAdjuntos = (int)($minuta['totalAdjuntos'] ?? 0);
                            $firmasActuales = (int)($minuta['firmasActuales'] ?? 0);
                            $requeridos = max(1, (int)($minuta['presidentesRequeridos'] ?? 1));
                            $tieneFeedback = (int)($minuta['tieneFeedback'] > 0);
                            // (Añadimos BORRADOR a la lógica de estado)
                            $esBorrador = ($estado === 'BORRADOR');
                            ?>
                            <td><?php echo htmlspecialchars($minutaId); ?></td>
                            <td><?php echo htmlspecialchars($minuta['nombreComision'] ?? 'N/A'); ?></td>
                            <td><?php echo $minuta['nombreTemas'] ?? 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($fechaCreacion); ?></td>
                            <td class="text-center">
                                <?php if ($totalAdjuntos > 0): ?>
                                    <button type="button" class="btn btn-info btn-sm"
                                        title="Ver adjuntos"
                                        onclick="verAdjuntos(<?php echo (int)$minutaId; ?>)">
                                        <i class="fas fa-paperclip"></i> (<?php echo $totalAdjuntos; ?>)
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted" title="Sin adjuntos">No posee archivos adjuntos</span>
                                <?php endif; ?>
                            </td>
                            <?php
                            // --- Lógica de Estado para Secretario (Se añade BORRADOR) ---
                            if ($estado === 'APROBADA') {
                                $statusText = "Aprobada ($firmasActuales / $requeridos)";
                                $statusClass = 'text-success';
                            } elseif ($esBorrador) {
                                $statusText = 'Borrador';
                                $statusClass = 'text-info';
                            } elseif ($tieneFeedback) {
                                $statusText = 'Feedback Recibido';
                                $statusClass = 'text-danger';
                            } elseif ($firmasActuales > 0 && $firmasActuales < $requeridos) {
                                $statusText = "Aprobación Parcial ($firmasActuales / $requeridos)";
                                $statusClass = 'text-info';
                            } else {
                                $statusText = "Pendiente de Firma ($firmasActuales / $requeridos)";
                                $statusClass = 'text-warning';
                            }
                            ?>
                            <td>
                                <strong class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></strong>
                            </td>
                            <td class="text-center" style="white-space: nowrap;">
                                <?php if ($estado === 'APROBADA'): ?>
                                    <a href="/corevota/<?php echo htmlspecialchars($minuta['pathArchivo']); ?>" target="_blank" class="btn btn-success btn-sm" title="Ver PDF Aprobado">
                                        <i class="fas fa-file-pdf"></i> Ver PDF Final
                                    </a>
                                <?php else: ?>
                                    <?php if ($tieneFeedback): ?>
                                        <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Primero debe editar la minuta y guardar los cambios. El botón de reenvío aparecerá en la página de edición.">
                                            <a href="menu.php?pagina=editar_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-danger btn-sm" title="Revisar Feedback y Editar">
                                                <i class="fas fa-edit"></i> Revisar Feedback
                                            </a>
                                        </span>
                                    <?php elseif ($esBorrador && $rol == 2): // <-- ¡NUEVO! Botón editar para Borrador 
                                    ?>
                                        <a href="menu.php?pagina=editar_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-info btn-sm" title="Continuar Edición (Borrador)">
                                            <i class="fas fa-edit"></i> Editar Borrador
                                        </a>
                                    <?php else: // Es PENDIENTE o PARCIAL 
                                    ?>
                                        <a href="/corevota/controllers/generar_pdf_borrador.php?id=<?php echo $minuta['idMinuta']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Ver Borrador PDF">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($rol == 2): ?>
                                            <a href="menu.php?pagina=editar_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-outline-primary btn-sm" title="Editar Minuta">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <a href="menu.php?pagina=seguimiento_minuta&id=<?php echo $minuta['idMinuta']; ?>"
                                    class="btn btn-info btn-sm"
                                    title="Seguimiento de Aprobación">
                                    <i class="fas fa-route"></i>
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
     * (CÓDIGO ACTUAL - MANTENIDO)
     * Confirma y reenvía una minuta que tenía feedback para aprobación.
     * (Esta función se llama desde la página de edición, no desde esta lista)
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
     * (CÓDIGO ACTUAL - MANTENIDO)
     * Envía una minuta en estado BORRADOR para aprobación por primera vez.
     * (Esta función se llama desde la página de edición, no desde esta lista)
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
     * (CÓDIGO ANTIGUO - RESTAURADO)
     * Muestra el modal con la lista de adjuntos (Versión funcional)
     */
    if (typeof verAdjuntos !== 'function') {
        function verAdjuntos(idMinuta) {
            console.log("Solicitando adjuntos para la minuta ID:", idMinuta);
            const modalElement = document.getElementById('modalAdjuntos');
            const modalList = document.getElementById('listaDeAdjuntos');

            if (!modalElement || !modalList) {
                console.error("No se encontraron los elementos del modal.");
                alert("Error: No se encontró el modal de adjuntos.");
                return;
            }

            const modal = new bootstrap.Modal(modalElement);

            modalList.innerHTML = '<li class="list-group-item text-muted">Cargando...</li>';
            modal.show();

            // (Ruta absoluta corregida)
            fetch(`/corevota/controllers/obtener_adjuntos.php?idMinuta=${idMinuta}&_cacheBust=${new Date().getTime()}`)
                .then(response => response.ok ? response.json() : Promise.reject('Error al obtener adjuntos'))
                .then(data => {
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        modalList.innerHTML = ''; // Limpiar 'Cargando...'
                        data.data.forEach(adj => {
                            const li = document.createElement('li');
                            li.className = 'list-group-item d-flex justify-content-between align-items-center';

                            const link = document.createElement('a');
                            const url = (adj.tipoAdjunto === 'file' || adj.tipoAdjunto === 'asistencia') ? `/corevota/${adj.pathAdjunto}` : adj.pathAdjunto;
                            link.href = url;
                            link.target = '_blank';

                            let icon = '🔗'; // link
                            if (adj.tipoAdjunto === 'asistencia') icon = '👥';
                            else if (adj.tipoAdjunto === 'file') icon = '📄';

                            let nombreArchivo = adj.pathAdjunto.split('/').pop();
                            if (adj.tipoAdjunto === 'link') {
                                nombreArchivo = adj.pathAdjunto.length > 50 ? adj.pathAdjunto.substring(0, 50) + '...' : adj.pathAdjunto;
                            }

                            link.textContent = ` ${icon} ${nombreArchivo}`;
                            link.title = adj.pathAdjunto;
                            li.appendChild(link);

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
        }
    }
</script>