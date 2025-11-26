<?php 
$c = $data['edit_comision']; 
$isEdit = !empty($c);
$titulo = $isEdit ? "Editar Comisión" : "Crear Nueva Comisión";
?>

<div class="container mt-4" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?php echo $titulo; ?></h3>
        <a href="index.php?action=comisiones_dashboard" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="index.php?action=comision_guardar" method="POST">
                <input type="hidden" name="idComision" value="<?php echo $c['idComision'] ?? ''; ?>">

                <div class="mb-4">
                    <label class="form-label fw-bold">Nombre de la Comisión *</label>
                    <input type="text" name="nombreComision" class="form-control form-control-lg" required 
                           value="<?php echo $c['nombreComision'] ?? ''; ?>" placeholder="Ej: Comisión de Salud">
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-primary fw-bold">Presidente</label>
                        <select name="presidente" class="form-select">
                            <option value="">-- Sin Asignar --</option>
                            <?php foreach($data['candidatos'] as $u): ?>
                                <option value="<?php echo $u['idUsuario']; ?>" 
                                    <?php echo (isset($c) && $c['t_usuario_idPresidente'] == $u['idUsuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['nombreCompleto']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Usuario que firmará las minutas.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold">Vicepresidente</label>
                        <select name="vicepresidente" class="form-select">
                            <option value="">-- Sin Asignar --</option>
                            <?php foreach($data['candidatos'] as $u): ?>
                                <option value="<?php echo $u['idUsuario']; ?>" 
                                    <?php echo (isset($c) && $c['t_usuario_idVicepresidente'] == $u['idUsuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['nombreCompleto']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-save me-2"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>