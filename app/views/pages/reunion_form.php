<?php
// Variables predefinidas para facilitar la lectura en el HTML
$isEdit = isset($reunion_data) && !empty($reunion_data);
$titulo = $isEdit ? 'Editar Reunión #' . $reunion_data['idReunion'] : 'Nueva Reunión';
$action = $isEdit ? 'update_reunion' : 'store_reunion';

// Valores por defecto
$nombre = $isEdit ? $reunion_data['nombreReunion'] : '';
$idCom1 = $isEdit ? $reunion_data['t_comision_idComision'] : '';
$idCom2 = $isEdit ? $reunion_data['t_comision_idComision_mixta'] : '';
$idCom3 = $isEdit ? $reunion_data['t_comision_idComision_mixta2'] : '';

// Fechas
$now = date('Y-m-d\TH:i');
$ini = $isEdit ? date('Y-m-d\TH:i', strtotime($reunion_data['fechaInicioReunion'])) : $now;
$fin = $isEdit ? date('Y-m-d\TH:i', strtotime($reunion_data['fechaTerminoReunion'])) : date('Y-m-d\TH:i', strtotime('+1 hour'));

$comisiones = $data['comisiones']; 
?>

<div class="container mt-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?php echo $titulo; ?></h3>
        <a href="index.php?action=reuniones_dashboard" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Menú
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">

            <form action="index.php" method="POST" id="formReunion">
                <input type="hidden" name="action" value="<?php echo $action; ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="idReunion" value="<?php echo $reunion_data['idReunion']; ?>">
                <?php endif; ?>

                <div class="mb-4 p-3 bg-light rounded border">
                    <h5 class="mb-3 text-primary"><i class="fas fa-users me-2"></i>Comisiones</h5>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Comisión Principal *</label>
                        <select name="t_comision_idComision" id="selectCom1" class="form-select select-comision" required onchange="verificarMixta(); actualizarExclusiones();">
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($comisiones as $c): ?>
                                <option value="<?php echo $c['idComision']; ?>" <?php echo ($idCom1 == $c['idComision']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nombreComision']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check mb-3" id="divCheckMixta" style="<?php echo $idCom1 ? '' : 'display:none;'; ?>">
                        <input class="form-check-input" type="checkbox" id="checkMixta" onchange="toggleMixta(); actualizarExclusiones();" <?php echo $idCom2 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="checkMixta">Es una reunión de Comisión Mixta</label>
                    </div>

                    <div id="bloqueMixta" style="<?php echo $idCom2 ? '' : 'display:none;'; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Segunda Comisión</label>
                            <select name="t_comision_idComision_mixta" id="selectCom2" class="form-select select-comision" onchange="actualizarExclusiones()">
                                <option value="">-- Seleccione --</option>
                                <?php foreach ($comisiones as $c): ?>
                                    <option value="<?php echo $c['idComision']; ?>" <?php echo ($idCom2 == $c['idComision']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nombreComision']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tercera Comisión (Opcional)</label>
                            <select name="t_comision_idComision_mixta2" id="selectCom3" class="form-select select-comision" onchange="actualizarExclusiones()">
                                <option value="">-- Seleccione --</option>
                                <?php foreach ($comisiones as $c): ?>
                                    <option value="<?php echo $c['idComision']; ?>" <?php echo ($idCom3 == $c['idComision']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nombreComision']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre de la Reunión *</label>
                    <input type="text"
                        name="nombreReunion"
                        class="form-control"
                        required
                        value="<?php echo $nombre; ?>"
                        placeholder="Ej: Reunión Ordinaria N° 15"
                        oninput="capitalizarInput(this)">
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Inicio *</label>
                        <input type="datetime-local" name="fechaInicioReunion" class="form-control" required value="<?php echo $ini; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Término Estimado *</label>
                        <input type="datetime-local" name="fechaTerminoReunion" class="form-control" required value="<?php echo $fin; ?>">
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Guardar Reunión
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // 1. Lógica para Mayúscula inmediata
    function capitalizarInput(input) {
        let valor = input.value;
        if (valor.length > 0) {
            // Toma la primera letra, la hace mayúscula y le concatena el resto
            input.value = valor.charAt(0).toUpperCase() + valor.slice(1);
        }
    }

    // 2. Lógica para filtrar Comisiones (Evitar duplicados)
    function actualizarExclusiones() {
        // Obtenemos los 3 selectores
        const s1 = document.getElementById('selectCom1');
        const s2 = document.getElementById('selectCom2');
        const s3 = document.getElementById('selectCom3');

        // Valores actuales
        const v1 = s1.value;
        const v2 = s2.value;
        const v3 = s3.value;

        // Función auxiliar para deshabilitar opciones en un select específico
        const deshabilitarEnSelect = (selectTarget, valoresExcluidos) => {
            const opciones = selectTarget.options;
            for (let i = 0; i < opciones.length; i++) {
                // Primero reseteamos (habilitamos todo)
                opciones[i].disabled = false;
                
                // Si el valor de la opción coincide con uno excluido (y no es el valor vacío ""), se deshabilita
                if (opciones[i].value !== "" && valoresExcluidos.includes(opciones[i].value)) {
                    opciones[i].disabled = true;
                }
            }
        };

        // Aplicamos la lógica cruzada
        // En el Select 2, no pueden estar lo que se eligió en el 1 ni en el 3
        deshabilitarEnSelect(s2, [v1, v3]);
        
        // En el Select 3, no pueden estar lo que se eligió en el 1 ni en el 2
        deshabilitarEnSelect(s3, [v1, v2]);

        // (Opcional) En el Select 1, no pueden estar lo que se eligió en el 2 ni en el 3
        deshabilitarEnSelect(s1, [v2, v3]);
    }


    // --- Lógica original de visibilidad ---
    function verificarMixta() {
        const com1 = document.getElementById('selectCom1').value;
        const divCheck = document.getElementById('divCheckMixta');

        if (com1) {
            divCheck.style.display = 'block';
        } else {
            divCheck.style.display = 'none';
            document.getElementById('checkMixta').checked = false;
            toggleMixta();
        }
    }

    function toggleMixta() {
        const isChecked = document.getElementById('checkMixta').checked;
        const bloque = document.getElementById('bloqueMixta');
        const sel2 = document.getElementById('selectCom2');

        if (isChecked) {
            bloque.style.display = 'block';
            sel2.required = true;
        } else {
            bloque.style.display = 'none';
            sel2.required = false;
            sel2.value = '';
            document.getElementById('selectCom3').value = '';
            // Importante: al ocultar y limpiar, debemos liberar las opciones bloqueadas
            actualizarExclusiones(); 
        }
    }

    // Ejecutar al cargar la página por si es modo Editar
    document.addEventListener('DOMContentLoaded', function() {
        verificarMixta();
        toggleMixta(); // Asegura el estado inicial
        actualizarExclusiones(); // Aplica filtros si vienen datos cargados
    });
</script>