<?php
require_once("../../cfg/config.php");

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class MinutaPendiente extends BaseConexion
{
    public $idUsuarioLogueado;
    private $conexion; // Propiedad para la conexión

    // Constructor para obtener el ID del usuario logueado y conectar
    public function __construct()
    {
        $this->idUsuarioLogueado = $_SESSION['idUsuario'] ?? 0;
        $this->conexion = $this->conectar(); // Conectar una vez
        $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Obtiene la lista precisa de IDs de presidentes requeridos para firmar.
     * Esta lógica está validada por los logs de 'guardar_minuta_completa.php'.
     */
    private function getListaPresidentesRequeridos(int $idMinuta): array
    {
        try {
            // 1. Obtener Presidente 1 (guardado en t_minuta usando 'nombrePresidente' como ID)
            $sqlMinuta = "SELECT nombrePresidente FROM t_minuta WHERE idMinuta = ?";
            $stmtMinuta = $this->conexion->prepare($sqlMinuta);
            $stmtMinuta->execute([$idMinuta]);
            $idPresidente1 = $stmtMinuta->fetchColumn();

            $presidentes = [$idPresidente1];

            // 2. Obtener Presidentes 2 y 3 (de comisiones mixtas en t_reunion)
            $sqlReunion = "SELECT r.t_comision_idComision_mixta, r.t_comision_idComision_mixta2 
                           FROM t_reunion r
                           WHERE r.t_minuta_idMinuta = ?";
            $stmtReunion = $this->conexion->prepare($sqlReunion);
            $stmtReunion->execute([$idMinuta]);
            $comisionesMixtas = $stmtReunion->fetch(PDO::FETCH_ASSOC);

            if ($comisionesMixtas) {
                $idComisiones = array_filter([
                    $comisionesMixtas['t_comision_idComision_mixta'],
                    $comisionesMixtas['t_comision_idComision_mixta2']
                ]);

                if (!empty($idComisiones)) {
                    // Consultar los IDs de presidentes para esas comisiones
                    $placeholders = implode(',', array_fill(0, count($idComisiones), '?'));
                    $sqlComision = "SELECT t_usuario_idPresidente FROM t_comision WHERE idComision IN ($placeholders)";
                    $stmtComision = $this->conexion->prepare($sqlComision);
                    $stmtComision->execute($idComisiones);
                    
                    $idsPresidentesMixtos = $stmtComision->fetchAll(PDO::FETCH_COLUMN, 0);
                    $presidentes = array_merge($presidentes, $idsPresidentesMixtos);
                }
            }

            // 3. Devolver lista de IDs únicos, filtrados y forzados a entero
            $presidentesUnicos = array_map('intval', array_unique(array_filter($presidentes)));
            
            return $presidentesUnicos;

        } catch (Exception $e) {
            error_log("ERROR idMinuta {$idMinuta}: No se pudo OBTENER la lista de presidentes en minutaPendiente.php. Error: " . $e->getMessage());
            return []; // Devolver vacío en caso de error
        }
    }


    public function obtenerMinutas()
    {
        // --- Paginación segura ---
        $pPage = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
        $offset = ($pPage - 1) * $perPage;

        // --- Contar solo las pendientes/parciales ---
        $sqlCount = "SELECT COUNT(*) AS total 
                     FROM t_minuta 
                     WHERE estadoMinuta <> 'APROBADA'";
        $totalRows = (int)$this->conexion->query($sqlCount)->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));

        // --- Consulta principal (CORREGIDA) ---
        $sql = "
            SELECT 
                m.idMinuta,
                m.nombreComision,
                u.pNombre AS presidenteNombre,
                u.aPaterno AS presidenteApellido,
                m.fechaMinuta AS fecha,
                m.horaMinuta AS hora,
                
                -- Campos de estado
                m.estadoMinuta,
                m.presidentesRequeridos,
                
                (SELECT COUNT(DISTINCT am.t_usuario_idPresidente) 
                 FROM t_aprobacion_minuta am 
                 WHERE am.t_minuta_idMinuta = m.idMinuta) AS aprobacionesActuales,
                
                -- Verificar si el usuario actual ya aprobó
                (SELECT COUNT(*) 
                 FROM t_aprobacion_minuta am2 
                 WHERE am2.t_minuta_idMinuta = m.idMinuta 
                   AND am2.t_usuario_idPresidente = :idUsuarioLogueado) AS usuarioYaAprobo,

                -- Conteo de adjuntos
                (SELECT COUNT(*)
                 FROM t_adjunto a
                 WHERE a.t_minuta_idMinuta = m.idMinuta) AS totalAdjuntos
            FROM t_minuta m
            
            -- CORRECCIÓN: Unir usando 'nombrePresidente' (que guarda el ID)
            LEFT JOIN t_usuario u ON u.idUsuario = m.nombrePresidente
            
            -- Filtro para mostrar solo pendientes o parciales
            WHERE m.estadoMinuta <> 'APROBADA'
            
            ORDER BY m.idMinuta DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':idUsuarioLogueado', $this->idUsuarioLogueado, PDO::PARAM_INT);
        
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Lógica de Presidentes Requeridos (Usando la función corregida) ---
        foreach ($rows as $i => $minuta) {
            $idMinuta = (int)$minuta['idMinuta'];
            // Usamos la función corregida para obtener la lista
            $rows[$i]['listaPresidentesRequeridos'] = $this->getListaPresidentesRequeridos($idMinuta);
        }

        return [
            'data' => $rows,
            'page' => $pPage,
            'per_page' => $perPage,
            'total' => $totalRows,
            'totalPages' => $totalPages
        ];
    }
} // Fin de la clase MinutaPendiente

// --- Ejecución ---
$minutaModel = new MinutaPendiente();
$res = $minutaModel->obtenerMinutas();
$minutas = $res['data'] ?? [];
$pPage = $res['page'] ?? 1;
$perPage = $res['per_page'] ?? 10;
$totalRows = $res['total'] ?? 0;
$totalPages = $res['totalPages'] ?? 1;

// ID de usuario de la sesión para la lógica del botón
$idUsuarioLogueado = intval($minutaModel->idUsuarioLogueado); 

// Helper de paginación (sin cambios)
function renderPagination($current, $pages)
{
    if ($pages <= 1) return;
    echo '<nav aria-label="Paginación"><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $current) ? ' active' : '';
        $qsArr = $_GET;
        $qsArr['p'] = $i;
        $qs = http_build_query($qsArr);
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . $qs . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}
?>

<div class="container mt-4">
    <h4 class="fw-bold mb-4">Minutas Pendientes de Aprobación</h4>

    <?php if (!empty($minutas)) : ?>
        <?php foreach ($minutas as $minuta) : ?>
            <?php
            $idMinuta = (int)($minuta['idMinuta'] ?? 0);
            $totalAdjuntos = (int)($minuta['totalAdjuntos'] ?? 0);

            // --- INICIO: Lógica de Aprobación y Estado ---
            $estado = $minuta['estadoMinuta'] ?? 'PENDIENTE';
            $requeridos = max(1, (int)($minuta['presidentesRequeridos'] ?? 1)); 
            $actuales = (int)($minuta['aprobacionesActuales'] ?? 0);
            $usuarioYaAprobo = (int)($minuta['usuarioYaAprobo'] > 0);

            // Usamos la lista que pre-calculamos en la clase
            $listaPresidentesRequeridos = $minuta['listaPresidentesRequeridos'] ?? [];
            
            // Comparamos INT (usuario) vs ARRAY DE INTs (lista)
            $esPresidenteRequerido = in_array($idUsuarioLogueado, $listaPresidentesRequeridos, true);
            
            // Condición para mostrar el botón
            $mostrarBotonAprobar = $esPresidenteRequerido && !$usuarioYaAprobo;
            
            // --- Lógica de texto y color del Estado ---
            $statusClass = 'text-warning'; // PENDIENTE (default)
            $statusText = "PENDIENTE ($actuales de $requeridos firmas)";
            
            if ($estado === 'PARCIAL') {
                $statusClass = 'text-info'; // PARCIAL
                $statusText = "APROBACIÓN PARCIAL ($actuales de $requeridos firmas)";
            }
            ?>
            <div class="card mb-4 shadow-sm" id="card-minuta-<?= $idMinuta ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                    <span class="fw-bold text-primary fs-5">Minuta N° <?= htmlspecialchars($minuta['idMinuta']) ?></span>
                    <span class="fw-bold <?= $statusClass ?> ms-3"><?= htmlspecialchars($statusText) ?></span>
                </div>

                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Comisión:</strong><br><?= htmlspecialchars($minuta['nombreComision'] ?? '—') ?></div>
                        <div class="col-md-3"><strong>Presidente (Principal):</strong><br><?= htmlspecialchars(trim(($minuta['presidenteNombre'] ?? '') . ' ' . ($minuta['presidenteApellido'] ?? ''))) ?: '—' ?></div>
                        <div class="col-md-3"><strong>Fecha:</strong><br><?= !empty($minuta['fecha']) ? date("d-m-Y", strtotime($minuta['fecha'])) : '—' ?></div>
                        <div class="col-md-3"><strong>Hora:</strong><br><?= !empty($minuta['hora']) ? date("H:i", strtotime($minuta['hora'])) : '—' ?></div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <strong>Adjuntos:</strong><br>
                            <?php if ($totalAdjuntos > 0) : ?>
                                <button type="button" class="btn btn-info btn-sm" title="Ver adjuntos" onclick="verAdjuntos(<?= $idMinuta; ?>)">
                                    <i class="fas fa-paperclip"></i> (<?= $totalAdjuntos; ?>)
                                </button>
                            <?php else : ?>
                                <span class="text-muted">No posee archivos adjuntos</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light text-end">
                    
                    <a href="menu.php?pagina=editar_minuta&id=<?= $idMinuta ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-edit"></i> Editar / Ver
                    </a>
                    
                    <?php if ($mostrarBotonAprobar) : ?>
                        <button type="button" class="btn btn-success btn-sm" 
                                id="btn-aprobar-<?= $idMinuta ?>" 
                                onclick="aprobarMinuta(<?= $idMinuta ?>)">
                            <i class="fas fa-check"></i> Aprobar con Firma
                        </button>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>

        <?php renderPagination($pPage, $totalPages); ?>

    <?php else : ?>
        <p class="text-muted">No hay minutas pendientes de aprobación.</p>
    <?php endif; ?>
</div>

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function aprobarMinuta(idMinuta) {
        const boton = document.getElementById('btn-aprobar-' + idMinuta);
        boton.disabled = true;
        boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

        Swal.fire({
            title: '¿Confirmar Aprobación?',
            text: "Esta acción registrará su firma digital y no se puede deshacer. ¿Está seguro?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Llamada al controlador que maneja la firma
                fetch('../controllers/aprobar_minuta.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            idMinuta: idMinuta
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                             // Si el servidor responde con 4xx o 5xx
                             return response.json().then(err => { throw new Error(err.message || 'Error del servidor') });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success_final') {
                            // --- ¡FIRMA FINAL! ---
                            Swal.fire({
                                title: '¡Aprobada!',
                                text: 'La minuta ha sido aprobada, firmada y el PDF ha sido generado.',
                                icon: 'success',
                                timer: 2500,
                                showConfirmButton: false
                            }).then(() => {
                                // Ocultar la tarjeta de la minuta recién aprobada
                                document.getElementById('card-minuta-' + idMinuta).style.display = 'none';
                            });
                        } else if (data.status === 'success_partial') {
                            // --- FIRMA PARCIAL ---
                            Swal.fire({
                                title: 'Firma Registrada',
                                text: data.message, // "Firma registrada. Faltan X aprobación(es) más."
                                icon: 'info'
                            }).then(() => {
                                location.reload(); // Recargar la página para actualizar el estado (ej. "1 de 3")
                            });
                        } else {
                            // --- ERROR CONOCIDO (ej. "No tiene permisos") ---
                            throw new Error(data.message || 'Error desconocido al aprobar.');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', error.message, 'error');
                        boton.disabled = false;
                        boton.innerHTML = '<i class="fas fa-check"></i> Aprobar con Firma';
                    });
            } else {
                // Si el usuario cancela el Swal
                boton.disabled = false;
                boton.innerHTML = '<i class="fas fa-check"></i> Aprobar con Firma';
            }
        });
    }

    // Función para ver adjuntos (debes tenerla implementada)
    function verAdjuntos(idMinuta) {
        Swal.fire('Función no implementada', 'Aquí se deben cargar los adjuntos de la minuta ' + idMinuta, 'info');
        console.log('Implementar JS para modal de adjuntos para minuta ' + idMinuta);
    }
</script>