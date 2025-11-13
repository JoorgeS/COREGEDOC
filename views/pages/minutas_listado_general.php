<?php
// views/pages/minutas_listado_general.php

// --- Conexión BD ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../class/class.conectorDB.php';
try {
    $db  = new conectorDB();
    $pdo = $db->getDatabase();
} catch (Exception $e) {
    $pdo = null;
    error_log("Error de conexión BD en minutas_listado_general.php: " . $e->getMessage());
}

// Variables esperadas del Controlador:
// $minutas (array), $estadoActual (string)
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
$rol = $_SESSION['tipoUsuario_id'] ?? null;

// --- INICIO CORRECCIÓN DE ESTADO ---
// Detectar el estado basado en la 'pagina' de la URL, si '$estadoActual' no viene seteado
if (!isset($estadoActual)) {
  $paginaGet = $_GET['pagina'] ?? 'minutas_pendientes';
  $estadoActual = ($paginaGet === 'minutas_aprobadas') ? 'APROBADA' : 'PENDIENTE';
}

$pageTitle  = ($estadoActual === 'APROBADA') ? 'Minutas Aprobadas' : 'Minutas Pendientes';
$paginaForm  = ($estadoActual === 'APROBADA') ? 'minutas_aprobadas' : 'minutas_pendientes';
// --- FIN CORRECCIÓN DE ESTADO ---

// -------- Filtros de UI (SIEMPRE ACTIVOS) --------
// -------- Filtros de UI (SIEMPRE ACTIVOS) --------

// --- INICIO MODIFICACIÓN: Fechas por defecto ---
// Comprobar si las variables de fecha vienen en la URL (incluso si están vacías)
$startDateSet = array_key_exists('startDate', $_GET);
$endDateSet   = array_key_exists('endDate', $_GET);

// Si 'startDate' NO vino en la URL (primera carga), poner el 1ro del mes actual.
// Si vino (p.ej. al filtrar o limpiar), usar su valor (que puede ser '2025-11-05' o '').
$currentStartDate = !$startDateSet ? date('Y-m-01') : $_GET['startDate'];

// Si 'endDate' NO vino en la URL (primera carga), poner hoy.
// Si vino, usar su valor.
$currentEndDate = !$endDateSet ? date('Y-m-d') : $_GET['endDate'];
// --- FIN MODIFICACIÓN ---

$currentThemeName   = $_GET['themeName'] ?? '';
$__hasKeyword       = trim($currentThemeName) !== '';
$selectedComisionId = $_GET['comisionSelectId'] ?? '';
$hasComisionSelect  = $selectedComisionId !== '';

// ------- Helpers de normalización / acceso -------
$__toUtf8 = function ($s) {
    if ($s === null) return '';
    if (!is_string($s)) return $s;
    if (!mb_detect_encoding($s, 'UTF-8', true)) {
        $s = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
    }
    return $s;
};
$__removeAccents = function ($s) {
    $s = (string)$s;
    $noAcc = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($noAcc !== false && $noAcc !== null) return $noAcc;
    $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N'];
    return strtr($s, $map);
};
$__valToText = function ($v) use ($__toUtf8): string {
    if ($v === null) return '';
    if (is_string($v)) return $__toUtf8($v);
    if (is_array($v)) return implode(' ', array_map(fn($x) => is_string($x) ? $__toUtf8($x) : json_encode($x, JSON_UNESCAPED_UNICODE), $v));
    if (is_object($v)) return (string)json_encode($v, JSON_UNESCAPED_UNICODE);
    return $__toUtf8((string)$v);
};
$__normalize = function ($s) use ($__toUtf8, $__removeAccents) {
    $s = $__toUtf8((string)$s);
    $s = preg_replace('/<br\s*\/?>/i', ' ', $s);
    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = mb_strtolower($s, 'UTF-8');
    $s = $__removeAccents($s);
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return in_array($s, ['n/a', 'na', '-'], true) ? '' : $s;
};
$__get = function ($m, $k) {
    if (is_array($m)) return $m[$k] ?? null;
    if (is_object($m)) {
        try {
            return $m->$k ?? null;
        } catch (Throwable) {
            return null;
        }
    }
    return null;
};

// --------- Cargar Comisiones (para combobox) ---------
$comisiones = [];
if ($pdo) {
    try {
        $stCom = $pdo->query("SELECT idComision, nombreComision FROM t_comision ORDER BY nombreComision ASC");
        $comisiones = $stCom->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[minutas_listado_general] Error al obtener comisiones: ' . $e->getMessage());
    }
}

// --------- Coerción segura de $minutas ----------
if ($minutas instanceof Traversable) {
    $tmp = [];
    foreach ($minutas as $row) {
        $tmp[] = $row;
    }
    $minutas = $tmp;
} elseif (!is_array($minutas)) {
    $minutas = $minutas ? [$minutas] : [];
}
$__minutasCountBackend = count($minutas);

// =========================================================
// FALLBACK SQL (si backend vacío o hay filtros locales)
// + filtro por comisión (combobox) y fechas
// + palabra clave (HAVING normalizado)
// =========================================================
if (($__minutasCountBackend === 0 || $__hasKeyword || $hasComisionSelect) && $pdo) {
    try {
        $normalizedKeyword = $__normalize(trim($currentThemeName));

        $where  = [];
        $params = [];

        // Estado por pathArchivo
        if ($estadoActual === 'APROBADA') {
            $where[] = "COALESCE(m.pathArchivo,'') <> ''";
        } else {
            // El usuario logueado (definido en la línea 11)
            $params[':idStLogueado'] = (int)$idUsuarioLogueado;

            // Estados para "Pendientes":
            // 1. PENDIENTE (para firma) - Visible para todos los roles
            // 2. REQUIERE_REVISION (feedback) - Visible para todos los roles
            // 3. BORRADOR (solo visible para el ST que lo creó)
            $where[] = "(
        m.estadoMinuta IN ('PENDIENTE', 'REQUIERE_REVISION') 
        OR 
        (m.estadoMinuta = 'BORRADOR' AND r.t_usuario_idSecretario = :idStLogueado)
      )";
        }

        // Fechas
        if (!empty($currentStartDate)) {
            $where[] = "DATE(m.fechaMinuta) >= :fdesde";
            $params[':fdesde'] = date('Y-m-d', strtotime($currentStartDate));
        }
        if (!empty($currentEndDate)) {
            $where[] = "DATE(m.fechaMinuta) <= :fhasta";
            $params[':fhasta'] = date('Y-m-d', strtotime($currentEndDate));
        }

        // Comisión (combobox)
        if ($hasComisionSelect) {
            $where[] = "m.t_comision_idComision = :comboComisionId";
            $params[':comboComisionId'] = (int)$selectedComisionId;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Palabra clave (en HAVING por agregaciones)
        $having = '';
        if ($__hasKeyword) {
            $params[':kw'] = '%' . $normalizedKeyword . '%';
            $having = " HAVING
                /* AÑADIR ESTE BLOQUE OR */
                LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(m.nombreReunion,''), 'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'ñ','n')) LIKE :kw
                OR
                /* FIN BLOQUE AÑADIDO */
                LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(GROUP_CONCAT(DISTINCT tt.nombreTema SEPARATOR ' '),''), 'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'ñ','n')) LIKE :kw
                OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(GROUP_CONCAT(DISTINCT tt.objetivo   SEPARATOR ' '),''), 'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'ñ','n')) LIKE :kw
            ";
        }

        $sql = "
      SELECT
        m.idMinuta,
        m.fechaMinuta,
        m.nombreReunion,
        m.pathArchivo,
        m.estadoMinuta, -- <-- 1. AÑADIDO
        m.t_comision_idComision AS comisionId,
        r.t_usuario_idSecretario, -- <-- 2. AÑADIDO
        c.nombreComision,
        IFNULL(GROUP_CONCAT(DISTINCT tt.nombreTema ORDER BY tt.idTema SEPARATOR '<br>'),'N/A') AS nombreTemas,
        IFNULL(GROUP_CONCAT(DISTINCT tt.objetivo  ORDER BY tt.idTema SEPARATOR '<br>'),'N/A') AS objetivos,
        COUNT(DISTINCT adj.idAdjunto) AS totalAdjuntos,
        (SELECT COUNT(DISTINCT am1.t_usuario_idPresidente)
          FROM t_aprobacion_minuta am1
         WHERE am1.t_minuta_idMinuta = m.idMinuta
          AND am1.estado_firma = 'FIRMADO') AS firmasActuales,
        (SELECT COUNT(*)
          FROM t_aprobacion_minuta am2
         WHERE am2.t_minuta_idMinuta = m.idMinuta
          AND am2.estado_firma = 'REQUIERE_REVISION') AS tieneFeedback,
        COALESCE(m.presidentesRequeridos,1) AS presidentesRequeridos
      FROM t_minuta m
      LEFT JOIN t_comision c  ON c.idComision = m.t_comision_idComision
      LEFT JOIN t_tema   tt ON tt.t_minuta_idMinuta = m.idMinuta
      LEFT JOIN t_adjunto adj ON adj.t_minuta_idMinuta = m.idMinuta
      LEFT JOIN t_reunion r  ON r.t_minuta_idMinuta = m.idMinuta -- <-- 3. AÑADIDO
      $whereSql
      GROUP BY
        m.idMinuta, m.fechaMinuta, m.pathArchivo, m.estadoMinuta, m.t_comision_idComision,
        r.t_usuario_idSecretario, c.nombreComision, m.presidentesRequeridos, m.nombreReunion
            $having
            ORDER BY m.fechaMinuta DESC, m.idMinuta DESC
            LIMIT 1000
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($rows && count($rows) > 0) {
            $minutas = $rows;
        } elseif ($__minutasCountBackend === 0) {
            $minutas = [];
        }
    } catch (\Throwable $e) {
        error_log('[minutas_listado_general] Fallback SQL error: ' . $e->getMessage());
    }
}

// ----------------- Filtros en VISTA (keyword/fecha) -----------------
$minutasFiltradas = is_array($minutas) ? $minutas : [];

$__temaKeys = ['nombreTemas', 'nombreTema', 'temas', 'tema', 'temaNombre', 'temasNombres'];
$__objKeys  = ['objetivos', 'objetivo', 'objetivosTexto', 'objetivoTexto', 'objetivosDetalle'];
$__comKeys  = ['nombreComision', 'comision', 'nombreComisión'];
$__reuKeys  = ['nombreReunion', 'reunion', 'nombreReunión'];

if (array_key_exists('themeName', $_GET)) {
    $needle = $__normalize($currentThemeName);
    if ($needle !== '') {
        $minutasFiltradas = array_values(array_filter($minutasFiltradas, function ($m) use ($needle, $__get, $__valToText, $__normalize, $__temaKeys, $__objKeys, $__comKeys, $__reuKeys) {
            $temas = '';
            foreach ($__temaKeys as $k) {
                $v = $__get($m, $k);
                if ($v !== null && $v !== '') $temas .= ' ' . $__valToText($v);
            }
            $objs = '';
            foreach ($__objKeys  as $k) {
                $v = $__get($m, $k);
                if ($v !== null && $v !== '') $objs .= ' ' . $__valToText($v);
            }
            $coms = '';
            foreach ($__comKeys  as $k) {
                $v = $__get($m, $k);
                if ($v !== null && $v !== '') $coms .= ' ' . $__valToText($v);
            }
            $reus = '';
            foreach ($__reuKeys  as $k) {
                $v = $__get($m, $k);
                if ($v !== null && $v !== '') $reus .= ' ' . $__valToText($v);
            }
            $searchText = $__normalize($temas . ' ' . $objs . ' ' . $reus);
            return (strpos($searchText, $needle) !== false);
        }));
        if (empty($minutasFiltradas)) {
            $minutasFiltradas = array_values(array_filter($minutas, function ($m) use ($needle, $__normalize) {
                $full = $__normalize(json_encode($m, JSON_UNESCAPED_UNICODE));
                return $full !== '' && strpos($full, $needle) !== false;
            }));
        }
    }
}

// Fecha en vista
$start = $currentStartDate ? date('Y-m-d', strtotime($currentStartDate)) : null;
$end   = $currentEndDate   ? date('Y-m-d', strtotime($currentEndDate))   : null;
if ($start || $end) {
    $minutasFiltradas = array_values(array_filter($minutasFiltradas, function ($m) use ($start, $end, $__get) {
        $f = (string)($__get($m, 'fechaMinuta') ?? '');
        if ($f === '') return false;
        $d = substr($f, 0, 10);
        if ($start && $d < $start) return false;
        if ($end   && $d > $end)   return false;
        return true;
    }));
}

// Filtro en VISTA por combobox si vinieron minutas sin comisionId (seguridad)
if ($hasComisionSelect) {
    $minutasFiltradas = array_values(array_filter($minutasFiltradas, function ($m) use ($selectedComisionId, $__get) {
        $cid = $__get($m, 'comisionId');
        if ($cid !== null && $cid !== '') {
            return (string)$cid === (string)$selectedComisionId;
        }
        return true;
    }));
}

// ----------------- Paginación -----------------
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$page    = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset  = ($page - 1) * $perPage;
$total   = count($minutasFiltradas);
$pages   = max(1, (int)ceil(($total ?: 1) / $perPage));
$minutasPaginadas = array_slice($minutasFiltradas, $offset, $perPage);

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
<style>
    .filters-card {
        border: 1px solid #e5e7eb;
        border-radius: .5rem;
        background: #f8fafc
    }

    .filters-card .form-label {
        font-weight: 600
    }

    .sticky-th thead th {
        position: sticky;
        top: 0;
        z-index: 1
    }
</style>

<div class="container-fluid mt-4">

    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="menu.php?pagina=home">Home</a></li>
            <li class="breadcrumb-item"><a href="menu.php?pagina=minutas_dashboard">Módulo de Minutas</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($tituloPagina); ?></li>
        </ol>
    </nav>


    <h3 class="mb-3"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h3>

    <form id="filtrosForm" method="GET" class="mb-4 p-3 border rounded bg-light filters-card">
        <input type="hidden" name="pagina" value="<?php echo htmlspecialchars($paginaForm, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="estado" value="<?php echo htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="p" id="pHidden" value="<?php echo (int)$page; ?>">

        <div class="row g-3">
            <div class="col-md-3">
                <label for="startDate" class="form-label">Fecha Creación Desde:</label>
                <input type="date" class="form-control form-control-sm" id="startDate" name="startDate" value="<?php echo htmlspecialchars($currentStartDate); ?>">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label">Fecha Creación Hasta:</label>
                <input type="date" class="form-control form-control-sm" id="endDate" name="endDate" value="<?php echo htmlspecialchars($currentEndDate); ?>">
            </div>


            <!-- Combobox de comisión SIEMPRE activo -->
            <div class="col-md-2">
                <label for="comisionSelectId" class="form-label">Comisión</label>
                <select id="comisionSelectId" name="comisionSelectId" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($comisiones as $c):
                        $cid = (string)$c['idComision'];
                        $sel = ((string)$selectedComisionId === $cid) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($cid); ?>" <?php echo $sel; ?>>
                            <?php echo htmlspecialchars($c['nombreComision']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="themeName" class="form-label">Buscar por palabra clave (Reunión / Tema / Objetivo)</label>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    id="themeName"
                    name="themeName"

                    value="<?php echo htmlspecialchars($currentThemeName); ?>">
            </div>


            <div class="col-12">
                <button type="button" id="btnClearAll" class="btn btn-outline-secondary btn-sm mt-2">Limpiar todos los filtros</button>
            </div>
        </div>
    </form>

    <div class="table-responsive shadow-sm">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark sticky-top">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Nombre Reunión</th>
                    <th scope="col">Comisión</th>
                    <th scope="col">Nombre(s) del Tema</th>
                    <th scope="col">Fecha Creación</th>
                    <th scope="col" class="text-center">Adjuntos</th>
                    <th scope="col" class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($minutasPaginadas)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No hay minutas que coincidan con los filtros aplicados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($minutasPaginadas as $minuta): ?>
                        <tr>
                            <?php
                            $minutaId        = $minuta['idMinuta'];
                            $estado          = $minuta['estadoMinuta'] ?? 'PENDIENTE';
                            $fechaCreacion   = $minuta['fechaMinuta'] ?? 'N/A';
                            $totalAdjuntos   = (int)($minuta['totalAdjuntos'] ?? 0);
                            $firmasActuales  = (int)($minuta['firmasActuales'] ?? 0);
                            $requeridos      = max(1, (int)($minuta['presidentesRequeridos'] ?? 1));
                            $tieneFeedback   = (int)(($minuta['tieneFeedback'] ?? 0) > 0);
                            $nombreComision  = $minuta['nombreComision'] ?? 'N/A';
                            $nombreReunion   = $minuta['nombreReunion'] ?? 'N/A';
                            $nombreTemasVal  = $minuta['nombreTemas'] ?? 'N/A';

                            // NOTA: mantenemos estos cálculos porque se usan para las acciones,
                            // aunque ya no se muestre la columna "Estado".
                            if ($estado === 'APROBADA') {
                                $statusText = "Aprobada ($firmasActuales / $requeridos)";
                                $statusClass = 'text-success';
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
                            <td><?php echo htmlspecialchars($minutaId); ?></td>
                            <td><?php echo htmlspecialchars($nombreReunion); ?></td>
                            <td><?php echo htmlspecialchars($nombreComision); ?></td>
                            <td><?php echo is_array($nombreTemasVal) ? htmlspecialchars(implode(', ', $nombreTemasVal)) : $nombreTemasVal; ?></td>
                            <td><?php echo htmlspecialchars($fechaCreacion); ?></td>
                            <td class="text-center">
                                <?php if ($totalAdjuntos > 0): ?>
                                    <button type="button" class="btn btn-info btn-sm" title="Ver adjuntos" onclick="verAdjuntos(<?php echo (int)$minutaId; ?>)">
                                        <i class="fas fa-paperclip"></i> (<?php echo $totalAdjuntos; ?>)
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">No posee archivos adjuntos</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" style="white-space: nowrap;">

                                <?php if ($estado === 'APROBADA'): ?>
                                    <a href="/corevota/<?php echo htmlspecialchars($minuta['pathArchivo']); ?>" target="_blank" class="btn btn-success btn-sm" title="Ver PDF Aprobado">
                                        <i class="fas fa-file-pdf"></i> Ver PDF Final
                                    </a>
                                <?php else: // Si NO está APROBADA 
                                ?>

                                    <?php // --- INICIO DE LA LÓGICA DE ACCIONES CORREGIDA --- 
                                    ?>

                                    <?php if ($estado === 'BORRADOR'): ?>
                                        <?php // Caso 1: Es un BORRADOR 
                                        ?>
                                        <?php if ($rol == 2): // Verificamos que el usuario logueado es ST 
                                        ?>
                                            <a href="menu.php?pagina=editar_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Continuar con la edición
                                            </a>
                                        <?php endif; ?>

                                    <?php elseif ($tieneFeedback): ?>
                                        <?php // Caso 2: Tiene FEEDBACK (REQUIERE_REVISION) 
                                        ?>
                                        <?php if ($rol == 2): // Solo el ST puede revisar el feedback 
                                        ?>
                                            <a href="menu.php?pagina=editar_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-danger btn-sm">
                                                <i class="fas fa-edit"></i> Revisar Feedback
                                            </a>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <?php // Caso 3: Está PENDIENTE o PARCIAL (esperando firmas) 
                                        ?>
                                        <span class="badge bg-warning text-dark" style="font-size: 0.85rem;">
                                            <i class="fas fa-hourglass-half me-1"></i>
                                            A la espera de firma (<?php echo $firmasActuales; ?>/<?php echo $requeridos; ?>)
                                        </span>
                                    <?php endif; ?>

                                    <?php // --- FIN DE LA LÓGICA DE ACCIONES --- 
                                    ?>

                                <?php endif; ?>

                                <?php // El botón de seguimiento es visible para todos 
                                ?>
                                <a href="menu.php?pagina=seguimiento_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-info btn-sm" title="Seguimiento de Aprobación">
                                    <i class="fas fa-route"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pdo) {
        $pdo = null;
    } ?>
    <?php renderPaginationListado($page, $pages); ?>
</div>

<!-- Modal Adjuntos -->
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
    // Definición directa de la función global 'verAdjuntos'
    window.verAdjuntos = function(idMinuta) {
        const modalElement = document.getElementById('modalAdjuntos');
        const modalList = document.getElementById('listaDeAdjuntos');

        // Mostrar error si faltan elementos cruciales
        if (!modalElement || !modalList) {
            // Utilizamos Swal para alertar al usuario si el modal no existe
            Swal.fire("Error", "No se encontró el modal de adjuntos (Elementos HTML faltantes).", "error");
            return;
        }

        // Es esencial usar una instancia de Modal
        const modal = new bootstrap.Modal(modalElement);

        // Mostrar estado de carga antes del fetch
        modalList.innerHTML = '<li class="list-group-item text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</li>';
        modal.show();

        // La URL utiliza el ID de la minuta
        fetch(`/corevota/controllers/obtener_adjuntos.php?idMinuta=${idMinuta}&_cacheBust=${Date.now()}`)
            .then(r => {
                if (!r.ok) {
                    // Si el servidor devuelve un error HTTP (ej: 500)
                    return r.text().then(text => Promise.reject(`Error HTTP ${r.status}: ${text.substring(0, 50)}...`));
                }
                return r.json();
            })
            .then(data => {
                // Verificar el formato de la respuesta del controlador
                if (data.status === 'success' && data.adjuntos && data.adjuntos.length > 0) { // <-- Usar data.adjuntos
                    modalList.innerHTML = '';
                    data.adjuntos.forEach(adj => {

                        // --- INICIO DE LA CORRECCIÓN ---
                        // Si el adjunto es de tipo 'asistencia', sáltalo.
                        if (adj.tipoAdjunto === 'asistencia') {
                            return; // Salta esta iteración del bucle
                        }

                        // <-- Iterar sobre data.adjuntos
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center';
                        const link = document.createElement('a');

                        // Construcción de URL (el controlador ahora devuelve el pathWebFinal)
                        // NOTA: El controlador en el paso anterior devolvía 'pathArchivo' y no 'pathAdjunto'
                        const url = adj.pathArchivo;

                        link.href = url;
                        link.target = '_blank';
                        let icon = '🔗';
                        if (adj.tipoAdjunto === 'asistencia') icon = '👥';
                        else if (adj.tipoAdjunto === 'file') icon = '📄';

                        // El controlador devuelve el nombre ya simplificado en 'nombreArchivo'
                        const displayName = adj.nombreArchivo;

                        link.innerHTML = ` ${icon} ${displayName}`;
                        link.title = adj.pathArchivo;
                        li.appendChild(link);
                        modalList.appendChild(li);
                    });
                } else {
                    modalList.innerHTML = '<li class="list-group-item text-muted">No se encontraron adjuntos.</li>';
                }
            })
            .catch((error) => {
                console.error("Error al cargar adjuntos:", error);
                modalList.innerHTML = `<li class="list-group-item text-danger">Error al cargar adjuntos. Verifique la consola (F12) para detalles.</li>`;
            });
    };

    // Autosubmit y manejo de filtros SIEMPRE ACTIVOS
    // ... (Tu código de filtros original va aquí) ...
    (function() {
        const form = document.getElementById('filtrosForm');
        if (!form) return;

        const qInput = document.getElementById('themeName');
        const desdeInp = document.getElementById('startDate');
        const hastaInp = document.getElementById('endDate');
        const perPage = document.getElementById('per_page');
        const pHidden = document.getElementById('pHidden');
        const comSelect = document.getElementById('comisionSelectId');

        function toFirstPage() {
            if (pHidden) {
                pHidden.value = '1';
            }
        }

        function submitNow() {
            if (form.requestSubmit) form.requestSubmit();
            else form.submit();
        }

        [desdeInp, hastaInp, perPage].forEach(el => {
            if (!el) return;
            el.addEventListener('change', () => {
                toFirstPage();
                submitNow();
            });
        });

        // Texto: autosubmit >=5 chars o vacío
        if (qInput) {
            let t = null;
            const DEBOUNCE_MS = 350;
            const launch = () => {
                const val = (qInput.value || '').trim();
                if (val.length >= 5 || val.length === 0) {
                    toFirstPage();
                    submitNow();
                }
            };
            qInput.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(launch, DEBOUNCE_MS);
            });
            qInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    toFirstPage();
                    submitNow();
                }
            });
        }

        // Combobox de comisión
        comSelect?.addEventListener('change', () => {
            toFirstPage();
            submitNow();
        });

        // Limpiar todo
        document.getElementById('btnClearAll')?.addEventListener('click', () => {
            if (desdeInp) desdeInp.value = '';
            if (hastaInp) hastaInp.value = '';
            if (qInput) qInput.value = '';
            if (comSelect) comSelect.value = '';
            toFirstPage();
            submitNow();
        });
    })();
</script>