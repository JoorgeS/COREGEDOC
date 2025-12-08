<?php 
$u = $data['edit_user']; 
$isEdit = !empty($u);

// Lógica para separar el usuario del dominio si estamos editando
$emailUser = '';
$dominioFijo = "@gobiernovalparaiso.cl";

if ($isEdit && !empty($u['correo'])) {
    // Si edita, quitamos el dominio para mostrar solo el nombre
    $emailUser = str_replace($dominioFijo, '', $u['correo']);
}
?>

<div class="container mt-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between mb-4">
        <h3><?php echo $isEdit ? 'Editar Usuario' : 'Crear Usuario'; ?></h3>
        <a href="index.php?action=usuarios_dashboard" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="index.php?action=usuario_guardar" method="POST" autocomplete="off" id="formUsuario">
                <input type="hidden" name="idUsuario" value="<?php echo $u['idUsuario'] ?? ''; ?>">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombres *</label>
                        <input type="text" name="pNombre" class="form-control" required value="<?php echo $u['pNombre'] ?? ''; ?>" placeholder="Primer nombre">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Apellidos *</label>
                        <input type="text" name="aPaterno" class="form-control" required value="<?php echo $u['aPaterno'] ?? ''; ?>" placeholder="Apellido paterno">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Correo Electrónico *</label>
                    
                    <div class="input-group">
                        <input type="text" 
                               id="email_prefix" 
                               class="form-control" 
                               required 
                               value="<?php echo $emailUser; ?>" 
                               placeholder="nombre.apellido"
                               autocomplete="off">
                        
                        <span class="input-group-text"><?php echo $dominioFijo; ?></span>
                    </div>

                    <input type="hidden" name="correo" id="email_full" value="<?php echo $u['correo'] ?? ''; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Contraseña <?php echo $isEdit ? '(Dejar en blanco para mantener)' : '*'; ?></label>
                    <input type="password" 
                           name="contrasena" 
                           class="form-control" 
                           <?php echo $isEdit ? '' : 'required'; ?> 
                           autocomplete="new-password"
                           readonly 
                           onfocus="this.removeAttribute('readonly');">
                    </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Rol *</label>
                        <select name="tipoUsuario_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($data['roles'] as $r): ?>
                                <option value="<?php echo $r['idTipoUsuario']; ?>" <?php echo (isset($u) && $u['tipoUsuario_id'] == $r['idTipoUsuario']) ? 'selected' : ''; ?>>
                                    <?php echo $r['descTipoUsuario']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Partido</label>
                        <select name="partido_id" class="form-select">
                            <option value="">Ninguno</option>
                            <?php foreach($data['partidos'] as $p): ?>
                                <option value="<?php echo $p['idPartido']; ?>" <?php echo (isset($u) && $u['partido_id'] == $p['idPartido']) ? 'selected' : ''; ?>>
                                    <?php echo $p['nombrePartido']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Provincia</label>
                        <select name="provincia_id" class="form-select">
                            <option value="">Ninguna</option>
                            <?php foreach($data['provincias'] as $pr): ?>
                                <option value="<?php echo $pr['idProvincia']; ?>" <?php echo (isset($u) && $u['provincia_id'] == $pr['idProvincia']) ? 'selected' : ''; ?>>
                                    <?php echo $pr['nombreProvincia']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Guardar Datos</button>
            </form>
        </div>
    </div>
</div>

<script>
// Script para combinar el usuario + dominio antes de enviar
document.getElementById('formUsuario').addEventListener('submit', function(e) {
    var prefix = document.getElementById('email_prefix').value;
    var domain = "<?php echo $dominioFijo; ?>";
    // Asignamos el valor completo al input oculto que se llama 'correo'
    document.getElementById('email_full').value = prefix + domain;
});

// Opcional: Actualizar en tiempo real por si acaso
document.getElementById('email_prefix').addEventListener('input', function(e) {
    var domain = "<?php echo $dominioFijo; ?>";
    document.getElementById('email_full').value = this.value + domain;
});
</script>