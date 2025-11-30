<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Seguridad de la Cuenta</h5>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($_GET['msg'])): ?>
                        <?php if ($_GET['msg'] == 'pass_ok'): ?>
                            <div class="alert alert-success">Contraseña cambiada exitosamente.</div>
                        <?php elseif ($_GET['msg'] == 'no_match'): ?>
                            <div class="alert alert-danger">Las contraseñas nuevas no coinciden.</div>
                        <?php elseif ($_GET['msg'] == 'wrong_current'): ?>
                            <div class="alert alert-danger">La contraseña actual es incorrecta.</div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="index.php?action=update_password" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Contraseña Actual</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i> Actualizar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>