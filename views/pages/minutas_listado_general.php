<?php
// views/pages/minutas_listado_general.php

// --- Conexión BD (necesaria para fallback / adjuntos) ---
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

// Variables esperadas del Controlador:
// $minutas (array), $estadoActual (string)
$idUsuarioLogueado = $_SESSION['idUsuario'] ?? null;
$rol = $_SESSION['idRol'] ?? null;

$estadoActual = $estadoActual ?? 'PENDIENTE';
$pageTitle = ($estadoActual === 'APROBADA') ? 'Minutas Aprobadas' : 'Minutas Pendientes';
$paginaForm = ($estadoActual === 'APROBADA') ? 'minutas_aprobadas' : 'minutas_pendientes';

// Filtros de UI
$currentStartDate = $_GET['startDate'] ?? '';
$currentEndDate   = $_GET['endDate']   ?? '';
$currentThemeName = $_GET['themeName'] ?? '';
$__hasKeyword     = trim($currentThemeName) !== '';


// ---------------------------------------------------------
// Helpers de normalización y acceso
// ---------------------------------------------------------
// Estas funciones se definen primero para que el Fallback SQL las pueda usar.
$__toUtf8 = function($s) {
    if ($s === null) return '';
    if (!is_string($s)) return $s;
    if (!mb_detect_encoding($s, 'UTF-8', true)) {
        $s = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
    }
    return $s;
};
$__removeAccents = function($s) {
    $s = (string)$s;
    // Función robusta para eliminar acentos
    $noAcc = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($noAcc !== false && $noAcc !== null) return $noAcc;
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'];
    return strtr($s, $map);
};
$__valToText = function($v) use ($__toUtf8): string {
    if ($v === null) return '';
    if (is_string($v)) return $__toUtf8($v);
    if (is_array($v)) return implode(' ', array_map(fn($x)=>is_string($x)?$__toUtf8($x):json_encode($x,JSON_UNESCAPED_UNICODE), $v));
    if (is_object($v)) return (string)json_encode($v, JSON_UNESCAPED_UNICODE);
    return $__toUtf8((string)$v);
};
$__normalize = function ($s) use ($__toUtf8, $__removeAccents) {
    $s = $__toUtf8((string)$s);
    $s = preg_replace('/<br\s*\/?>/i', ' ', $s);
    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = mb_strtolower($s, 'UTF-8');
    // PASO CLAVE: Se eliminan los acentos
    $s = $__removeAccents($s); 
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return in_array($s, ['n/a','na','-'], true) ? '' : $s;
};
$__get = function($m, $k) {
    if (is_array($m)) return $m[$k] ?? null;
    if (is_object($m)) { try { return $m->$k ?? null; } catch(Throwable) { return null; } }
    return null;
};
// ---------------------------------------------------------


// ---------------------------------------------------------
// Coerción segura de $minutas
// ---------------------------------------------------------
if ($minutas instanceof Traversable) {
    $tmp = [];
    foreach ($minutas as $row) { $tmp[] = $row; }
    $minutas = $tmp;
} elseif (!is_array($minutas)) {
    $minutas = $minutas ? [$minutas] : [];
}
$__minutasCountBackend = count($minutas);

// ---------------------------------------------------------
// FALLBACK CORREGIDO FINAL (Usa palabra clave sin acentos)
// ---------------------------------------------------------
if ($__hasKeyword && $__minutasCountBackend === 0 && $pdo) {
    try {
        // 1. Preparamos el valor de búsqueda para SQL:
        //    - Usamos $__normalize para quitar acentos y pasar a minúsculas
        //    - Agregamos comodines
        $normalizedKeyword = $__normalize(trim($currentThemeName));
        $keywordSql = '%'.$normalizedKeyword.'%';
        
        // 2. Consulta SQL que busca en Comisión, Temas y Objetivos
        //    - Usamos LOWER() en las columnas para asegurar coincidencia de minúsculas.
        //    - La coincidencia de acentos se asegura porque $keywordSql ya no tiene acentos.
        $sql = "SELECT 
                    m.idMinuta, m.estadoMinuta, m.fechaMinuta,
                    m.nombreTemas, m.objetivos,
                    COALESCE(m.totalAdjuntos,0) AS totalAdjuntos,
                    COALESCE(m.firmasActuales,0) AS firmasActuales,
                    COALESCE(m.presidentesRequeridos,1) AS presidentesRequeridos,
                    COALESCE(m.tieneFeedback,0) AS tieneFeedback,
                    c.nombreComision, m.pathArchivo
                FROM t_minuta m
                LEFT JOIN t_comision c ON c.idComision = m.t_comision_idComision
                WHERE m.estadoMinuta = :estado
                  -- CLÁUSULA WHERE FINAL: Compara la versión sin acentos del usuario
                  -- con la versión en minúsculas de la columna.
                  AND (
                      LOWER(m.nombreTemas) LIKE :keyword 
                   OR LOWER(m.objetivos) LIKE :keyword
                   OR LOWER(c.nombreComision) LIKE :keyword 
                  )
                ORDER BY m.idMinuta DESC
                LIMIT 1000";
        
        $st = $pdo->prepare($sql);
        $st->bindValue(':estado', $estadoActual);
        // Bindear la palabra clave (sin acentos, minúsculas, con comodines)
        $st->bindValue(':keyword', $keywordSql); 
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        
        if ($rows && count($rows) > 0) { 
            $minutas = $rows; 
        }
    } catch (Throwable $e) {
        // Registrar el error en caso de fallo de PDO
        error_log('[minutas_listado_general] Fallback error (Keyword search Final V3 Unaccented PHP): '.$e->getMessage());
    }
}


// ---------------------------------------------------------
// 1) Filtro por palabra (tema / objetivos / comisión) en VISTA
// ---------------------------------------------------------
// Este filtro actúa sobre los resultados del backend o del fallback SQL.
$minutasFiltradas = is_array($minutas) ? $minutas : [];
$__temaKeys = ['nombreTemas', 'nombreTema', 'temas', 'tema', 'temaNombre', 'temasNombres'];
$__objKeys  = ['objetivos', 'objetivo', 'objetivosTexto', 'objetivoTexto', 'objetivosDetalle'];
$__comKeys  = ['nombreComision', 'comision', 'nombreComisión'];

if (array_key_exists('themeName', $_GET)) {
    $needle = $__normalize($currentThemeName);
    if ($needle !== '') {
        $minutasFiltradas = array_values(array_filter($minutasFiltradas, function($m) use ($needle,$__get,$__valToText,$__normalize,$__temaKeys,$__objKeys,$__comKeys){
            $temas=''; foreach($__temaKeys as $k){ $v=$__get($m,$k); if($v!==null&&$v!=='') $temas.=' '.$__valToText($v); }
            $objs =''; foreach($__objKeys  as $k){ $v=$__get($m,$k); if($v!==null&&$v!=='') $objs .=' '.$__valToText($v); }
            $coms =''; foreach($__comKeys  as $k){ $v=$__get($m,$k); if($v!==null&&$v!=='') $coms .=' '.$__valToText($v); }
            
            // Normalizar y concatenar el texto para la búsqueda
            $searchText = $__normalize($temas . ' ' . $objs . ' ' . $coms);
            
            // Usamos strpos ya que $needle ya está normalizada (sin acentos, minúsculas)
            return (strpos($searchText,$needle)!==false);
        }));
        
        // Fallback: buscar en todo el registro si no hubo match
        if (empty($minutasFiltradas)) {
            $minutasFiltradas = array_values(array_filter($minutas, function($m) use ($needle,$__normalize){
                $full = $__normalize(json_encode($m, JSON_UNESCAPED_UNICODE));
                return $full!=='' && strpos($full,$needle)!==false;
            }));
        }
    }
}

// ---------------------------------------------------------
// 2) Filtro por fecha en VISTA (si el backend no lo hizo o estamos en fallback)
//    - fechaMinuta formateada como YYYY-MM-DD o YYYY-MM-DD HH:MM:SS
// ---------------------------------------------------------
$start = $currentStartDate ? date('Y-m-d', strtotime($currentStartDate)) : null;
$end   = $currentEndDate   ? date('Y-m-d', strtotime($currentEndDate))   : null;

if ($start || $end) {
    $minutasFiltradas = array_values(array_filter($minutasFiltradas, function($m) use ($start,$end,$__get){
        $f = (string)($__get($m,'fechaMinuta') ?? '');
        if ($f==='') return false;
        $d = substr($f,0,10); // YYYY-MM-DD
        if ($start && $d < $start) return false;
        if ($end   && $d > $end)   return false;
        return true;
    }));
}

// ---------------------------------------------------------
// Paginación
// ---------------------------------------------------------
$perPage    = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$page       = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset     = ($page - 1) * $perPage;
$total      = count($minutasFiltradas);
$pages      = max(1, (int)ceil(($total ?: 1) / $perPage));
$minutasPaginadas = array_slice($minutasFiltradas, $offset, $perPage);

// Helper de paginación
function renderPaginationListado($current, $pages)
{
    if ($pages <= 1) return;
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $current) ? ' active' : '';
        $qsArr  = $_GET; $qsArr['p'] = $i; $qs = http_build_query($qsArr);
        echo '<li class="page-item'.$active.'"><a class="page-link" href="?'.$qs.'">'.$i.'</a></li>';
    }
    echo '</ul></nav>';
}
?>
<style>
  .filters-card{border:1px solid #e5e7eb;border-radius:.5rem;background:#f8fafc}
  .filters-card .form-label{font-weight:600}
  .sticky-th thead th{position:sticky;top:0;z-index:1}
</style>

<div class="container-fluid mt-4">
    <h3 class="mb-3"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h3>

    <form id="filtrosForm" method="GET" class="mb-4 p-3 border rounded bg-light filters-card">
        <input type="hidden" name="pagina" value="<?php echo htmlspecialchars($paginaForm, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="estado" value="<?php echo htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="p" id="pHidden" value="<?php echo (int)$page; ?>">

        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="startDate" class="form-label">Fecha Creación Desde:</label>
                <input type="date" class="form-control form-control-sm" id="startDate" name="startDate" value="<?php echo htmlspecialchars($currentStartDate); ?>">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label">Fecha Creación Hasta:</label>
                <input type="date" class="form-control form-control-sm" id="endDate" name="endDate" value="<?php echo htmlspecialchars($currentEndDate); ?>">
            </div>

            <div class="col-md-4">
                <label for="themeName" class="form-label">Buscar por palabra clave</label>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    id="themeName"
                    name="themeName"
                    placeholder="Busca en Comisión, Tema u Objetivo…"
                    value="<?php echo htmlspecialchars($currentThemeName); ?>">
            </div>

            <div class="col-md-2">
                <label for="per_page" class="form-label">Resultados</label>
                <select id="per_page" name="per_page" class="form-select form-select-sm">
                    <?php foreach ([10,25,50] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo ($perPage===$opt)?'selected':''; ?>>
                            <?php echo $opt; ?>/pág
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 mt-1">
                <button type="button" id="btnClear" class="btn btn-outline-secondary btn-sm">Limpiar filtros</button>
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
                <?php if (empty($minutasPaginadas)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No hay minutas que coincidan con los filtros aplicados.</td>
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
                            $tieneFeedback = (int)(($minuta['tieneFeedback'] ?? 0) > 0);
                            $nombreComision = $minuta['nombreComision'] ?? 'N/A';
                            $nombreTemasVal = $minuta['nombreTemas'] ?? 'N/A';
                            ?>
                            <td><?php echo htmlspecialchars($minutaId); ?></td>
                            <td><?php echo htmlspecialchars($nombreComision); ?></td>
                            <td><?php echo is_array($nombreTemasVal) ? htmlspecialchars(implode(', ', $nombreTemasVal)) : htmlspecialchars($nombreTemasVal); ?></td>
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
                            <?php
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
                            <td><strong class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></strong></td>
                            <td class="text-center" style="white-space: nowrap;">
                                <?php if ($estado === 'APROBADA'): ?>
                                    <a href="/corevota/<?php echo htmlspecialchars($minuta['pathArchivo']); ?>" target="_blank" class="btn btn-success btn-sm" title="Ver PDF Aprobado">
                                        <i class="fas fa-file-pdf"></i> Ver PDF Final
                                    </a>
                                <?php else: ?>
                                    <?php if ($tieneFeedback): ?>
                                        <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Primero debe editar la minuta y guardar los cambios. El botón de reenvío aparecerá en la página de edición.">
                                            <a href="menu.php?pagina=editar_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-danger btn-sm">
                                                <i class="fas fa-edit"></i> Revisar Feedback
                                            </a>
                                        </span>
                                    <?php else: ?>
                                        <a href="/corevota/controllers/generar_pdf_borrador.php?id=<?php echo $minuta['idMinuta']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Ver Borrador PDF">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($rol == 2): ?>
                                            <a href="menu.php?pagina=editar_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-outline-primary btn-sm" title="Editar Minuta">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <a href="menu.php?pagina=seguimiento_minuta&id=<?php echo $minuta['idMinuta']; ?>" class="btn btn-info btn-sm" title="Seguimiento de Aprobación">
                                        <i class="fas fa-route"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pdo) { $pdo = null; } ?>
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
// Modal de adjuntos (igual que antes)
if (typeof verAdjuntos !== 'function') {
  function verAdjuntos(idMinuta) {
    const modalElement = document.getElementById('modalAdjuntos');
    const modalList = document.getElementById('listaDeAdjuntos');
    if (!modalElement || !modalList) { alert("Error: No se encontró el modal de adjuntos."); return; }
    const modal = new bootstrap.Modal(modalElement);
    modalList.innerHTML = '<li class="list-group-item text-muted">Cargando...</li>';
    modal.show();

    fetch(`/corevota/controllers/obtener_adjuntos.php?idMinuta=${idMinuta}&_cacheBust=${Date.now()}`)
      .then(r => r.ok ? r.json() : Promise.reject('Error al obtener adjuntos'))
      .then(data => {
        if (data.status === 'success' && data.data && data.data.length > 0) {
          modalList.innerHTML = '';
          data.data.forEach(adj => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            const link = document.createElement('a');
            const url = (adj.tipoAdjunto === 'file' || adj.tipoAdjunto === 'asistencia') ? `/corevota/${adj.pathAdjunto}` : adj.pathAdjunto;
            link.href = url; link.target = '_blank';
            let icon = '🔗';
            if (adj.tipoAdjunto === 'asistencia') icon = '👥';
            else if (adj.tipoAdjunto === 'file') icon = '📄';
            let nombreArchivo = (adj.pathAdjunto || '').split('/').pop();
            if (adj.tipoAdjunto === 'link') {
              nombreArchivo = adj.pathAdjunto.length > 50 ? adj.pathAdjunto.substring(0,50)+'...' : adj.pathAdjunto;
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
      .catch(() => modalList.innerHTML = '<li class="list-group-item text-danger">Error al cargar adjuntos.</li>');
  }
}

// Autosubmit (>=5 chars o vacío), Enter, y cambios de fecha/per_page reinician a pág 1
(function(){
  const form = document.getElementById('filtrosForm');
  if (!form) return;

  const qInput   = document.getElementById('themeName');
  const desdeInp = document.getElementById('startDate');
  const hastaInp = document.getElementById('endDate');
  const perPage  = document.getElementById('per_page');
  const pHidden  = document.getElementById('pHidden');

  function toFirstPage(){ if(pHidden){ pHidden.value='1'; } }

  [desdeInp, hastaInp, perPage].forEach(el=>{
    if(!el) return;
    el.addEventListener('change', ()=>{ toFirstPage(); form.submit(); });
  });

  if(qInput){
    let t=null;
    const DEBOUNCE_MS=350;
    const launch=()=>{
      const val=(qInput.value||'').trim();
      if(val.length>=5 || val.length===0){
        toFirstPage();
        // Cambiamos a requestSubmit para enviar el formulario sin necesidad del botón
        if(form.requestSubmit) form.requestSubmit(); else form.submit(); 
      }
    };
    qInput.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(launch, DEBOUNCE_MS); });
    qInput.addEventListener('keydown', (e)=>{
      if(e.key==='Enter'){
        e.preventDefault();
        toFirstPage();
        // Cambiamos a requestSubmit para enviar el formulario sin necesidad del botón
        if(form.requestSubmit) form.requestSubmit(); else form.submit();
      }
    });
  }

  document.getElementById('btnClear')?.addEventListener('click', ()=>{
    if(desdeInp) desdeInp.value='';
    if(hastaInp) hastaInp.value='';
    if(qInput)   qInput.value='';
    toFirstPage();
    if(form.requestSubmit) form.requestSubmit(); else form.submit();
  });
})();
</script>