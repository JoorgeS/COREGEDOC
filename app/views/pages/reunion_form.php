<?php
// Variables predefinidas para facilitar la lectura en el HTML
$isEdit = isset($reunion_data) && !empty($reunion_data);
$titulo = $isEdit ? 'Editar Reunión #' . $reunion_data['idReunion'] : 'Nueva Reunión';
$action = $isEdit ? 'update_reunion' : 'store_reunion';

// Valores por defecto (vacíos si es nuevo, datos si es edit)
$nombre = $isEdit ? $reunion_data['nombreReunion'] : '';
$idCom1 = $isEdit ? $reunion_data['t_comision_idComision'] : '';
$idCom2 = $isEdit ? $reunion_data['t_comision_idComision_mixta'] : '';
$idCom3 = $isEdit ? $reunion_data['t_comision_idComision_mixta2'] : '';

// Formato fecha para input datetime-local (Y-m-d\TH:i)
$ini = $isEdit ? date('Y-m-d\TH:i', strtotime($reunion_data['fechaInicioReunion'])) : date('Y-m-d\TH:i');
$fin = $isEdit ? date('Y-m-d\TH:i', strtotime($reunion_data['fechaTerminoReunion'])) : date('Y-m-d\TH:i', strtotime('+1 hour'));

$comisiones = $data['comisiones']; // Viene del controlador
?>

<div class="container mt-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?php echo $titulo; ?></h3>
        <a href="index.php?action=reuniones_dashboard" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            
            <form action="index.php" method="POST" id="formReunion">
                <input type="hidden" name="action" value="<?php echo $action; ?>">
                <?php if($isEdit): ?>
                    <input type="hidden" name="idReunion" value="<?php echo $reunion_data['idReunion']; ?>">
                <?php endif; ?>

                <div class="mb-4 p-3 bg-light rounded border">
                    <h5 class="mb-3 text-primary"><i class="fas fa-users me-2"></i>Comisiones</h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Comisión Principal *</label>
                        <select name="t_comision_idComision" id="selectCom1" class="form-select" required onchange="verificarMixta()">
                            <option value="">-- Seleccione --</option>
                            <?php foreach($comisiones as $c): ?>
                                <option value="<?php echo $c['idComision']; ?>" <?php echo ($idCom1 == $c['idComision']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nombreComision']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check mb-3" id="divCheckMixta" style="<?php echo $idCom1 ? '' : 'display:none;'; ?>">
                        <input class="form-check-input" type="checkbox" id="checkMixta" onchange="toggleMixta()" <?php echo $idCom2 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="checkMixta">Es una reunión de Comisión Mixta</label>
                    </div>

                    <div id="bloqueMixta" style="<?php echo $idCom2 ? '' : 'display:none;'; ?>">
                        <div class="mb-3">
                            <label class="form-label">Segunda Comisión</label>
                            <select name="t_comision_idComision_mixta" id="selectCom2" class="form-select">
                                <option value="">-- Seleccione --</option>
                                <?php foreach($comisiones as $c): ?>
                                    <option value="<?php echo $c['idComision']; ?>" <?php echo ($idCom2 == $c['idComision']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nombreComision']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tercera Comisión (Opcional)</label>
                            <select name="t_comision_idComision_mixta2" id="selectCom3" class="form-select">
                                <option value="">-- Seleccione --</option>
                                <?php foreach($comisiones as $c): ?>
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
                    <input type="text" name="nombreReunion" class="form-control" required value="<?php echo $nombre; ?>" placeholder="Ej: Reunión Ordinaria N° 15">
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
    // Lógica simple para mostrar/ocultar campos de mixta
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
        }
    }
</script>