<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Gestión de Usuarios - SIAMTEL') ?></title>
    <link rel="stylesheet" href="/css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <!-- SIDEBAR -->
    <?php require BASE_PATH . '/app/Views/layout/sidebar.php'; ?>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="dash-main">
        <!-- TOPBAR -->
        <?php require BASE_PATH . '/app/Views/layout/topbar.php'; ?>

        <div class="dash-content">

            <!-- Contenedor global de notificaciones (Flash inicial + respuestas AJAX dinámicas) -->
            <div id="userAlertContainer" aria-live="polite">
                <?php if (!empty($_SESSION['user_success'])): ?>
                    <div class="alert-banner alert-banner-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <div style="flex: 1;"><?= htmlspecialchars($_SESSION['user_success']) ?></div>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#065f46;"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <?php unset($_SESSION['user_success']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['user_error'])): ?>
                    <div class="alert-banner alert-banner-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div style="flex: 1;"><?= htmlspecialchars($_SESSION['user_error']) ?></div>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#991b1b;"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <?php unset($_SESSION['user_error']); ?>
                <?php endif; ?>
            </div>

            <!-- CRUD COMPLETO DE USUARIOS CON AJAX -->
            <div class="content-card">
                <div class="card-header-custom" style="flex-wrap: wrap; gap: 12px;">
                    <div class="card-header-title">
                        <i class="bi bi-people-fill" style="color: #7c3aed;"></i>
                        <span>Gestión de Usuarios (SIAMTEL)</span>
                        <span id="tableLoadingBadge" class="table-loading-badge" style="display: none;">
                            <i class="bi bi-arrow-repeat"></i> Actualizando...
                        </span>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <!-- Filtros conectados a consulta GET AJAX en tiempo real -->
                        <form method="GET" action="/usuarios" id="formFiltrosUsuarios" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="userSearch" name="q" maxlength="100" value="<?= htmlspecialchars($busqueda ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar cédula, usuario o nombre..." style="padding: 7px 12px; font-size: 13px; border: 1px solid var(--border-color); border-radius: 6px; outline: none; width: 230px;">
                            <select id="filtroRol" name="rol" style="padding: 7px 10px; font-size: 13px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff;">
                                <option value="">Todos los roles</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= htmlspecialchars($r['id_rol']) ?>" <?= (($rolFiltro ?? '') === $r['id_rol']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['nombre_rol']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="filtroEstatus" name="estatus" style="padding: 7px 10px; font-size: 13px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff;">
                                <option value="">Todos los estados</option>
                                <option value="1" <?= (($estatusFiltro ?? '') === '1') ? 'selected' : '' ?>>Activos</option>
                                <option value="0" <?= (($estatusFiltro ?? '') === '0') ? 'selected' : '' ?>>Inhabilitados</option>
                            </select>
                            <button type="submit" class="btn-sm-action" title="Aplicar búsqueda">
                                <i class="bi bi-search"></i>
                            </button>
                            <button type="button" id="btnLimpiarFiltros" class="btn-sm-action" title="Limpiar filtros" style="color: var(--text-secondary); display: <?= (!empty($busqueda) || !empty($rolFiltro) || ($estatusFiltro ?? '') !== '') ? 'inline-flex' : 'none' ?>;">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </button>
                        </form>
                        <button type="button" class="btn-primary-custom" onclick="abrirModalCrear()">
                            <i class="bi bi-person-plus-fill"></i>
                            <span>Nuevo Usuario</span>
                        </button>
                    </div>
                </div>

                <div class="card-body-custom" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="custom-table" id="tablaUsuarios">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Usuario / Nombre</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Último Acceso</th>
                                    <th>Contacto</th>
                                    <th style="text-align: right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaUsuariosBody">
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                            No se encontraron usuarios con los criterios seleccionados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <?php
                                            $isActivo = ((int)$u['estatus'] === 1);
                                            $isSelf = ($u['cedula'] === ($currentUser['cedula'] ?? ''));
                                            $rolCode = strtoupper((string)$u['rol']);
                                            $badgeClass = match ($rolCode) {
                                                'ADMIN'    => 'role-admin',
                                                'MEDICO'   => 'role-medico',
                                                'OPERADOR' => 'role-operador',
                                                'CLIENTE'  => 'role-cliente',
                                                default    => '',
                                            };
                                            $nombreCompleto = trim((string)($u['nombre_completo'] ?? ''));
                                            if ($nombreCompleto === '') {
                                                $nombreCompleto = (string)$u['user'];
                                            }
                                        ?>
                                        <tr>
                                            <td style="font-weight: 700; color: #1e3c72; white-space: nowrap;">
                                                <?= htmlspecialchars($u['cedula']) ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($u['user']) ?></strong>
                                                <?php if ($isSelf): ?>
                                                    <span style="font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 10px; margin-left: 4px;">Tú</span>
                                                <?php endif; ?>
                                                <div style="font-size: 12px; color: var(--text-secondary);">
                                                    <?= htmlspecialchars($nombreCompleto) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-role <?= $badgeClass ?>">
                                                    <?= htmlspecialchars($u['nombre_rol'] ?? $u['rol']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status <?= $isActivo ? 'badge-active' : 'badge-inactive' ?>">
                                                    <i class="bi <?= $isActivo ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                                    <?= $isActivo ? 'Activo' : 'Inhabilitado' ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 12px; color: var(--text-secondary); white-space: nowrap;">
                                                <?php if (!empty($u['ultimo_acceso'])): ?>
                                                    <i class="bi bi-clock-history"></i>
                                                    <?= date('d/m/Y H:i', strtotime((string)$u['ultimo_acceso'])) ?>
                                                <?php else: ?>
                                                    <span style="font-style: italic; opacity: 0.75;">Sin ingresos</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 12px;">
                                                <div><?= htmlspecialchars($u['correo'] ?: '--') ?></div>
                                                <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($u['telefono'] ?? '') ?></div>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <div style="display: inline-flex; gap: 5px; flex-wrap: nowrap;">
                                                    <!-- Editar Usuario -->
                                                    <button
                                                        type="button"
                                                        class="btn-sm-action"
                                                        onclick="abrirModalEditar(this)"
                                                        data-cedula="<?= htmlspecialchars($u['cedula'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-user="<?= htmlspecialchars($u['user'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-nombre="<?= htmlspecialchars((string)($u['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-apellido="<?= htmlspecialchars((string)($u['apellido'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-rol="<?= htmlspecialchars($rolCode, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-correo="<?= htmlspecialchars((string)($u['correo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-telefono="<?= htmlspecialchars((string)($u['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-direccion="<?= htmlspecialchars((string)($u['direccion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-self="<?= $isSelf ? '1' : '0' ?>"
                                                        title="Editar datos y rol"
                                                    >
                                                        <i class="bi bi-pencil-square"></i> Editar
                                                    </button>

                                                    <!-- Cambiar Clave -->
                                                    <button
                                                        type="button"
                                                        class="btn-sm-action"
                                                        onclick="abrirModalClave(this)"
                                                        data-cedula="<?= htmlspecialchars($u['cedula'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-user="<?= htmlspecialchars($u['user'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Cambiar Contraseña"
                                                    >
                                                        <i class="bi bi-key-fill"></i> Clave
                                                    </button>

                                                    <!-- Habilitar / Inhabilitar y Eliminar (vía AJAX) -->
                                                    <?php if (!$isSelf): ?>
                                                        <button
                                                            type="button"
                                                            class="btn-sm-action <?= $isActivo ? 'btn-sm-danger' : 'btn-sm-success' ?>"
                                                            onclick="cambiarEstatusAjax(this)"
                                                            data-cedula="<?= htmlspecialchars($u['cedula'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-user="<?= htmlspecialchars($u['user'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-nuevo-estatus="<?= $isActivo ? '0' : '1' ?>"
                                                            title="<?= $isActivo ? 'Inhabilitar cuenta' : 'Activar cuenta' ?>"
                                                        >
                                                            <i class="bi <?= $isActivo ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                                                            <?= $isActivo ? 'Desactivar' : 'Activar' ?>
                                                        </button>

                                                        <button
                                                            type="button"
                                                            class="btn-sm-action btn-sm-danger"
                                                            onclick="eliminarUsuarioAjax(this)"
                                                            data-cedula="<?= htmlspecialchars($u['cedula'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-user="<?= htmlspecialchars($u['user'], ENT_QUOTES, 'UTF-8') ?>"
                                                            title="Eliminar cuenta"
                                                        >
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL: REGISTRAR NUEVO USUARIO -->
    <div id="nuevoUserModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 580px;">
            <div class="modal-header-custom">
                <h3><i class="bi bi-person-plus-fill" style="color: #7c3aed;"></i> Registrar Nuevo Usuario</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('nuevoUserModal')">&times;</button>
            </div>
            <form method="POST" action="/usuarios" id="formNuevoUsuario" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="crear_usuario">
                <div class="modal-body-custom" style="max-height: 70vh; overflow-y: auto;">
                    <!-- Alerta AJAX dentro del modal (evita cerrarlo si el backend devuelve error) -->
                    <div id="alertNuevoUsuario" class="modal-ajax-alert" aria-live="polite"></div>

                    <div style="display: flex; gap: 10px;">
                        <div class="field-group" style="width: 90px;">
                            <label class="form-label-custom" for="new_particle">Prefijo</label>
                            <select id="new_particle" name="particle" class="form-input-custom" style="background:#fff;">
                                <option value="V-">V-</option>
                                <option value="E-">E-</option>
                                <option value="J-">J-</option>
                                <option value="P-">P-</option>
                                <option value="G-">G-</option>
                            </select>
                        </div>
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="new_cedula">Cédula *</label>
                            <input type="text" id="new_cedula" name="cedula" class="form-input-custom" placeholder="Ej: 12345678" maxlength="12" required>
                            <small class="field-error" id="err_new_cedula"></small>
                        </div>
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="new_user">Nombre de Usuario *</label>
                            <input type="text" id="new_user" name="user" class="form-input-custom" placeholder="Ej: dr.mendoza" minlength="3" maxlength="50" required>
                            <small class="field-error" id="err_new_user"></small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="new_nombre">Nombres *</label>
                            <input type="text" id="new_nombre" name="nombre" class="form-input-custom" placeholder="Ej: Carlos Alberto" minlength="2" maxlength="100" required>
                            <small class="field-error" id="err_new_nombre"></small>
                        </div>
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="new_apellido">Apellidos *</label>
                            <input type="text" id="new_apellido" name="apellido" class="form-input-custom" placeholder="Ej: Mendoza Gil" minlength="2" maxlength="100" required>
                            <small class="field-error" id="err_new_apellido"></small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="new_rol">Rol en el Sistema *</label>
                            <select id="new_rol" name="rol" class="form-input-custom" style="background:#fff;" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= htmlspecialchars($r['id_rol']) ?>">
                                        <?= htmlspecialchars($r['nombre_rol']) ?> (<?= htmlspecialchars($r['id_rol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-error" id="err_new_rol"></small>
                        </div>
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="new_telefono">Teléfono</label>
                            <input type="text" id="new_telefono" name="telefono" class="form-input-custom" placeholder="Ej: 0414-1234567" maxlength="20">
                            <small class="field-error" id="err_new_telefono"></small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="new_password">Contraseña Inicial *</label>
                            <div class="pwd-wrap">
                                <input type="password" id="new_password" name="password" class="form-input-custom" placeholder="Mínimo 6 caracteres" minlength="6" maxlength="128" required>
                                <button type="button" class="pwd-toggle-btn" onclick="toggleInputPassword('new_password', this)" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                            </div>
                            <small class="field-error" id="err_new_password"></small>
                        </div>
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="new_password_confirm">Confirmar Contraseña *</label>
                            <div class="pwd-wrap">
                                <input type="password" id="new_password_confirm" name="password_confirm" class="form-input-custom" placeholder="Repita la contraseña" minlength="6" maxlength="128" required>
                                <button type="button" class="pwd-toggle-btn" onclick="toggleInputPassword('new_password_confirm', this)" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                            </div>
                            <small class="field-error" id="err_new_password_confirm"></small>
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="form-label-custom" for="new_correo">Correo Institucional</label>
                        <input type="email" id="new_correo" name="correo" class="form-input-custom" placeholder="Ej: personal@siamtel.gob.ve" maxlength="120">
                        <small class="field-error" id="err_new_correo"></small>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-sm-action" onclick="closeModal('nuevoUserModal')">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="background: #7c3aed;">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDITAR USUARIO -->
    <div id="editarUserModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 560px;">
            <div class="modal-header-custom">
                <h3><i class="bi bi-pencil-square" style="color: #0284c7;"></i> Editar Usuario</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('editarUserModal')">&times;</button>
            </div>
            <form method="POST" action="/usuarios" id="formEditarUsuario" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="editar_usuario">
                <input type="hidden" id="edit_cedula" name="cedula" value="">

                <div class="modal-body-custom" style="max-height: 70vh; overflow-y: auto;">
                    <!-- Alerta AJAX dentro del modal de edición -->
                    <div id="alertEditarUsuario" class="modal-ajax-alert" aria-live="polite"></div>

                    <div style="display: flex; gap: 10px;">
                        <div class="field-group" style="width: 150px;">
                            <label class="form-label-custom">Cédula</label>
                            <input type="text" id="edit_cedula_display" class="form-input-custom" style="background: #f1f5f9; color: #475569;" disabled>
                        </div>
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="edit_user">Nombre de Usuario *</label>
                            <input type="text" id="edit_user" name="user" class="form-input-custom" minlength="3" maxlength="50" required>
                            <small class="field-error" id="err_edit_user"></small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="edit_nombre">Nombres *</label>
                            <input type="text" id="edit_nombre" name="nombre" class="form-input-custom" minlength="2" maxlength="100" required>
                            <small class="field-error" id="err_edit_nombre"></small>
                        </div>
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="edit_apellido">Apellidos *</label>
                            <input type="text" id="edit_apellido" name="apellido" class="form-input-custom" minlength="2" maxlength="100" required>
                            <small class="field-error" id="err_edit_apellido"></small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="edit_rol">Rol en el Sistema *</label>
                            <select id="edit_rol" name="rol" class="form-input-custom" style="background:#fff;" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= htmlspecialchars($r['id_rol']) ?>">
                                        <?= htmlspecialchars($r['nombre_rol']) ?> (<?= htmlspecialchars($r['id_rol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="edit_rol_hidden" disabled>
                            <small class="field-error" id="err_edit_rol"></small>
                        </div>
                        <div class="field-group" style="flex: 1;">
                            <label class="form-label-custom" for="edit_telefono">Teléfono</label>
                            <input type="text" id="edit_telefono" name="telefono" class="form-input-custom" maxlength="20">
                            <small class="field-error" id="err_edit_telefono"></small>
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="form-label-custom" for="edit_correo">Correo Institucional</label>
                        <input type="email" id="edit_correo" name="correo" class="form-input-custom" maxlength="120">
                        <small class="field-error" id="err_edit_correo"></small>
                    </div>

                    <div class="field-group">
                        <label class="form-label-custom" for="edit_direccion">Dirección / Dependencia</label>
                        <input type="text" id="edit_direccion" name="direccion" class="form-input-custom" maxlength="255">
                        <small class="field-error" id="err_edit_direccion"></small>
                    </div>
                </div>

                <div class="modal-footer-custom">
                    <button type="button" class="btn-sm-action" onclick="closeModal('editarUserModal')">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="background: #0284c7;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: CAMBIAR CONTRASEÑA -->
    <div id="cambiarClaveModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 440px;">
            <div class="modal-header-custom">
                <h3><i class="bi bi-key-fill" style="color: #0284c7;"></i> Cambiar Contraseña</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('cambiarClaveModal')">&times;</button>
            </div>
            <form method="POST" action="/usuarios" id="formCambiarClave" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="cambiar_clave">
                <input type="hidden" id="clave_cedula" name="cedula" value="">

                <div class="modal-body-custom">
                    <!-- Alerta AJAX dentro del modal de cambio de clave -->
                    <div id="alertCambiarClave" class="modal-ajax-alert" aria-live="polite"></div>

                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">
                        Actualizar credenciales de acceso para: <strong id="clave_username" style="color: #1e3c72;"></strong>
                    </p>
                    <div class="field-group">
                        <label class="form-label-custom" for="nueva_clave">Nueva Contraseña *</label>
                        <div class="pwd-wrap">
                            <input type="password" id="nueva_clave" name="nueva_clave" class="form-input-custom" placeholder="Mínimo 6 caracteres" minlength="6" maxlength="128" required>
                            <button type="button" class="pwd-toggle-btn" onclick="toggleInputPassword('nueva_clave', this)" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                        </div>
                        <small class="field-error" id="err_nueva_clave"></small>
                    </div>

                    <div class="field-group">
                        <label class="form-label-custom" for="confirmar_clave">Confirmar Nueva Contraseña *</label>
                        <div class="pwd-wrap">
                            <input type="password" id="confirmar_clave" name="confirmar_clave" class="form-input-custom" placeholder="Repita la nueva contraseña" minlength="6" maxlength="128" required>
                            <button type="button" class="pwd-toggle-btn" onclick="toggleInputPassword('confirmar_clave', this)" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                        </div>
                        <small class="field-error" id="err_confirmar_clave"></small>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-sm-action" onclick="closeModal('cambiarClaveModal')">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Actualizar Contraseña</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // =====================================================================
        // ESTADO GLOBAL DE SEGURIDAD (CSRF) Y SESIÓN PARA PETICIONES AJAX
        // =====================================================================
        let CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const CURRENT_CEDULA = <?= json_encode((string)($currentUser['cedula'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        /**
         * Mantiene sincronizado el token CSRF en memoria y en todos los inputs ocultos de los formularios.
         */
        function syncCsrfToken(newToken) {
            if (!newToken) return;
            CSRF_TOKEN = newToken;
            document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                input.value = newToken;
            });
        }

        /**
         * Escapa texto plano para insertarlo de forma segura en HTML (prevención de XSS al repintar la tabla).
         */
        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        /**
         * Muestra una alerta global (éxito o error) en la parte superior de la pantalla sin recargar.
         */
        function showPageAlert(type, message) {
            const container = document.getElementById('userAlertContainer');
            if (!container) return;

            const isSuccess = (type === 'success');
            const banner = document.createElement('div');
            banner.className = `alert-banner ${isSuccess ? 'alert-banner-success' : 'alert-banner-danger'}`;
            banner.innerHTML = `
                <i class="bi ${isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'}"></i>
                <div style="flex: 1;">${escapeHtml(message)}</div>
                <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:${isSuccess ? '#065f46' : '#991b1b'};">
                    <i class="bi bi-x-lg"></i>
                </button>
            `;
            container.innerHTML = '';
            container.appendChild(banner);
        }

        /**
         * Muestra un mensaje de error dentro del modal activo (para que el usuario corrija sin perder datos).
         */
        function showModalAlert(alertBoxId, message) {
            const box = document.getElementById(alertBoxId);
            if (!box) return;
            if (!message) {
                box.innerHTML = '';
                box.style.display = 'none';
                return;
            }
            box.style.display = 'flex';
            box.innerHTML = `
                <i class="bi bi-exclamation-circle-fill" style="flex-shrink:0;"></i>
                <span>${escapeHtml(message)}</span>
            `;
        }

        /**
         * Formatea fecha MySQL ("YYYY-MM-DD HH:MM:SS") a "DD/MM/YYYY HH:MM".
         */
        function formatFechaAcceso(fechaSql) {
            if (!fechaSql) {
                return '<span style="font-style: italic; opacity: 0.75;">Sin ingresos</span>';
            }
            const match = String(fechaSql).match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
            if (!match) return escapeHtml(fechaSql);
            return `<i class="bi bi-clock-history"></i> ${match[3]}/${match[2]}/${match[1]} ${match[4]}:${match[5]}`;
        }

        /**
         * Devuelve la clase CSS del badge según el código de rol.
         */
        function getRolBadgeClass(rolCode) {
            switch (String(rolCode || '').toUpperCase()) {
                case 'ADMIN':    return 'role-admin';
                case 'MEDICO':   return 'role-medico';
                case 'OPERADOR': return 'role-operador';
                case 'CLIENTE':  return 'role-cliente';
                default:         return '';
            }
        }

        // =====================================================================
        // CONSULTA Y RENDERIZADO DINÁMICO DE LA TABLA VÍA AJAX (GET /usuarios)
        // =====================================================================

        /**
         * Repinta el cuerpo de #tablaUsuarios con el arreglo JSON devuelto por el backend.
         */
        function renderTablaUsuarios(usuarios, currentCedula) {
            const tbody = document.getElementById('tablaUsuariosBody');
            if (!tbody) return;

            if (!Array.isArray(usuarios) || usuarios.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No se encontraron usuarios con los criterios seleccionados.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = usuarios.map(u => {
                const isActivo = Number(u.estatus) === 1;
                const isSelf = u.cedula === currentCedula;
                const rolCode = String(u.rol || '').toUpperCase();
                const badgeClass = getRolBadgeClass(rolCode);
                const nombreCompleto = (u.nombre_completo || '').trim() || u.user;

                const accionesOtroUsuario = isSelf ? '' : `
                    <button
                        type="button"
                        class="btn-sm-action ${isActivo ? 'btn-sm-danger' : 'btn-sm-success'}"
                        onclick="cambiarEstatusAjax(this)"
                        data-cedula="${escapeHtml(u.cedula)}"
                        data-user="${escapeHtml(u.user)}"
                        data-nuevo-estatus="${isActivo ? '0' : '1'}"
                        title="${isActivo ? 'Inhabilitar cuenta' : 'Activar cuenta'}"
                    >
                        <i class="bi ${isActivo ? 'bi-person-x' : 'bi-person-check'}"></i>
                        ${isActivo ? 'Desactivar' : 'Activar'}
                    </button>
                    <button
                        type="button"
                        class="btn-sm-action btn-sm-danger"
                        onclick="eliminarUsuarioAjax(this)"
                        data-cedula="${escapeHtml(u.cedula)}"
                        data-user="${escapeHtml(u.user)}"
                        title="Eliminar cuenta"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                `;

                return `
                    <tr>
                        <td style="font-weight: 700; color: #1e3c72; white-space: nowrap;">
                            ${escapeHtml(u.cedula)}
                        </td>
                        <td>
                            <strong>${escapeHtml(u.user)}</strong>
                            ${isSelf ? '<span style="font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 10px; margin-left: 4px;">Tú</span>' : ''}
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                ${escapeHtml(nombreCompleto)}
                            </div>
                        </td>
                        <td>
                            <span class="badge-role ${badgeClass}">
                                ${escapeHtml(u.nombre_rol || u.rol)}
                            </span>
                        </td>
                        <td>
                            <span class="badge-status ${isActivo ? 'badge-active' : 'badge-inactive'}">
                                <i class="bi ${isActivo ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}"></i>
                                ${isActivo ? 'Activo' : 'Inhabilitado'}
                            </span>
                        </td>
                        <td style="font-size: 12px; color: var(--text-secondary); white-space: nowrap;">
                            ${formatFechaAcceso(u.ultimo_acceso)}
                        </td>
                        <td style="font-size: 12px;">
                            <div>${escapeHtml(u.correo || '--')}</div>
                            <div style="font-size: 11px; color: var(--text-secondary);">${escapeHtml(u.telefono || '')}</div>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <div style="display: inline-flex; gap: 5px; flex-wrap: nowrap;">
                                <button
                                    type="button"
                                    class="btn-sm-action"
                                    onclick="abrirModalEditar(this)"
                                    data-cedula="${escapeHtml(u.cedula)}"
                                    data-user="${escapeHtml(u.user)}"
                                    data-nombre="${escapeHtml(u.nombre || '')}"
                                    data-apellido="${escapeHtml(u.apellido || '')}"
                                    data-rol="${escapeHtml(rolCode)}"
                                    data-correo="${escapeHtml(u.correo || '')}"
                                    data-telefono="${escapeHtml(u.telefono || '')}"
                                    data-direccion="${escapeHtml(u.direccion || '')}"
                                    data-self="${isSelf ? '1' : '0'}"
                                    title="Editar datos y rol"
                                >
                                    <i class="bi bi-pencil-square"></i> Editar
                                </button>
                                <button
                                    type="button"
                                    class="btn-sm-action"
                                    onclick="abrirModalClave(this)"
                                    data-cedula="${escapeHtml(u.cedula)}"
                                    data-user="${escapeHtml(u.user)}"
                                    title="Cambiar Contraseña"
                                >
                                    <i class="bi bi-key-fill"></i> Clave
                                </button>
                                ${accionesOtroUsuario}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        /**
         * Consulta al backend vía Fetch GET con los filtros activos (q, rol, estatus)
         * y actualiza la tabla sin recargar la página.
         */
        async function cargarUsuariosAjax() {
            const q = document.getElementById('userSearch')?.value.trim() || '';
            const rol = document.getElementById('filtroRol')?.value || '';
            const estatus = document.getElementById('filtroEstatus')?.value || '';
            const loadingBadge = document.getElementById('tableLoadingBadge');
            const btnLimpiar = document.getElementById('btnLimpiarFiltros');

            if (btnLimpiar) {
                btnLimpiar.style.display = (q || rol || estatus !== '') ? 'inline-flex' : 'none';
            }

            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (rol) params.set('rol', rol);
            if (estatus !== '') params.set('estatus', estatus);

            const url = '/usuarios' + (params.toString() ? ('?' + params.toString()) : '');
            if (loadingBadge) loadingBadge.style.display = 'inline-flex';

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                // Si la sesión caducó o fue revocada, redirigir al login
                if (response.status === 401 && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                if (data.csrf_token) syncCsrfToken(data.csrf_token);

                if (response.ok && data.ok) {
                    renderTablaUsuarios(data.usuarios || [], data.current_cedula || CURRENT_CEDULA);
                } else {
                    showPageAlert('danger', data.mensaje || 'No se pudo cargar el listado de usuarios.');
                }
            } catch (err) {
                showPageAlert('danger', 'Error de conexión al consultar los usuarios.');
            } finally {
                if (loadingBadge) loadingBadge.style.display = 'none';
            }
        }

        // =====================================================================
        // OPERACIONES CRUD VÍA AJAX (POST /usuarios)
        // =====================================================================

        /**
         * Envía un formulario modal (Crear, Editar o Cambiar Clave) usando Fetch POST.
         * - Si el servidor responde error (422, 409, 403, 500), muestra el mensaje DENTRO del modal.
         * - Si responde éxito (200), cierra el modal, muestra alerta global y recarga la tabla por AJAX.
         */
        async function enviarModalAjax(form, modalId, alertBoxId) {
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHtml = submitBtn ? submitBtn.innerHTML : '';

            showModalAlert(alertBoxId, '');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';
            }

            try {
                const formData = new FormData(form);
                formData.set('csrf_token', CSRF_TOKEN);

                const response = await fetch(form.getAttribute('action') || '/usuarios', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (response.status === 401 && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                if (data.csrf_token) syncCsrfToken(data.csrf_token);

                if (response.ok && data.ok) {
                    closeModal(modalId);
                    showPageAlert('success', data.mensaje || 'Operación realizada con éxito.');

                    // Si el ADMIN editó su propio nombre de usuario, actualizar el topbar en vivo
                    if (data.self_updated && data.current_user && data.current_user.user) {
                        const nameEl = document.querySelector('.user-info-name');
                        const avatarEl = document.querySelector('.user-avatar');
                        if (nameEl) nameEl.textContent = data.current_user.user;
                        if (avatarEl) avatarEl.textContent = data.current_user.user.charAt(0).toUpperCase();
                    }

                    // Recargar tabla con los filtros actuales sin refrescar toda la página
                    await cargarUsuariosAjax();
                } else {
                    showModalAlert(alertBoxId, data.mensaje || 'No se pudo completar la operación.');
                }
            } catch (err) {
                showModalAlert(alertBoxId, 'Error de conexión con el servidor. Intente nuevamente.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            }
        }

        /**
         * Activa o inhabilita una cuenta de usuario vía AJAX sin recargar la página.
         */
        async function cambiarEstatusAjax(btn) {
            const cedula = btn.dataset.cedula || '';
            const user = btn.dataset.user || '';
            const nuevoEstatus = btn.dataset.nuevoEstatus || '1';
            const accionTexto = nuevoEstatus === '0' ? 'inhabilitar' : 'activar';

            if (!confirm(`¿Desea ${accionTexto} la cuenta de ${user}?`)) {
                return;
            }

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

            try {
                const body = new FormData();
                body.append('csrf_token', CSRF_TOKEN);
                body.append('accion', 'toggle_estatus');
                body.append('cedula', cedula);
                body.append('nuevo_estatus', nuevoEstatus);

                const response = await fetch('/usuarios', {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (response.status === 401 && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (data.csrf_token) syncCsrfToken(data.csrf_token);

                if (response.ok && data.ok) {
                    showPageAlert('success', data.mensaje);
                    await cargarUsuariosAjax();
                } else {
                    showPageAlert('danger', data.mensaje || 'No se pudo cambiar el estado del usuario.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (err) {
                showPageAlert('danger', 'Error de conexión al cambiar el estado de la cuenta.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        /**
         * Elimina permanentemente una cuenta de usuario vía AJAX sin recargar la página.
         */
        async function eliminarUsuarioAjax(btn) {
            const cedula = btn.dataset.cedula || '';
            const user = btn.dataset.user || '';

            if (!confirm(`¿Eliminar permanentemente la cuenta de acceso de ${user}? Esta acción no se puede deshacer.`)) {
                return;
            }

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

            try {
                const body = new FormData();
                body.append('csrf_token', CSRF_TOKEN);
                body.append('accion', 'eliminar_usuario');
                body.append('cedula', cedula);

                const response = await fetch('/usuarios', {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (response.status === 401 && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (data.csrf_token) syncCsrfToken(data.csrf_token);

                if (response.ok && data.ok) {
                    showPageAlert('success', data.mensaje);
                    await cargarUsuariosAjax();
                } else {
                    showPageAlert('danger', data.mensaje || 'No se pudo eliminar el usuario.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (err) {
                showPageAlert('danger', 'Error de conexión al eliminar la cuenta.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        // =====================================================================
        // MANEJO DE MODALES Y VISIBILIDAD DE CONTRASEÑA
        // =====================================================================
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('active');
                const form = el.querySelector('form');
                if (form) clearFormValidation(form);
                el.querySelectorAll('.modal-ajax-alert').forEach(a => {
                    a.innerHTML = '';
                    a.style.display = 'none';
                });
            }
        }

        function toggleInputPassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                if (icon) icon.className = 'bi bi-eye';
            }
        }

        // =====================================================================
        // VALIDACIONES FRONTEND PARA GESTIÓN DE USUARIOS
        // =====================================================================
        const REGEX_CEDULA = /^(?:[VEJPG]-?)?\d{6,9}$/i;
        const REGEX_USER = /^[a-zA-Z0-9._-]{3,50}$/;
        const REGEX_NAME = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'\-.]{2,100}$/;
        const REGEX_PHONE = /^[0-9+\-\s()]{7,20}$/;
        const REGEX_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

        function setValidation(inputEl, errorId, message) {
            const errEl = document.getElementById(errorId);
            if (message) {
                inputEl.classList.add('input-invalid');
                inputEl.classList.remove('input-valid');
                if (errEl) {
                    errEl.textContent = message;
                    errEl.style.display = 'block';
                }
                return false;
            }
            inputEl.classList.remove('input-invalid');
            if (inputEl.value.trim() !== '') {
                inputEl.classList.add('input-valid');
            } else {
                inputEl.classList.remove('input-valid');
            }
            if (errEl) {
                errEl.textContent = '';
                errEl.style.display = 'none';
            }
            return true;
        }

        function clearFormValidation(form) {
            form.querySelectorAll('.input-invalid, .input-valid').forEach(el => {
                el.classList.remove('input-invalid', 'input-valid');
            });
            form.querySelectorAll('.field-error').forEach(el => {
                el.textContent = '';
                el.style.display = 'none';
            });
        }

        function checkCedula(inputEl, errId) {
            const v = inputEl.value.trim();
            if (!v) return setValidation(inputEl, errId, 'La cédula es obligatoria.');
            if (!REGEX_CEDULA.test(v)) return setValidation(inputEl, errId, 'Ingrese entre 6 y 9 dígitos (ej: 12345678).');
            return setValidation(inputEl, errId, '');
        }

        function checkUser(inputEl, errId) {
            const v = inputEl.value.trim();
            if (!v) return setValidation(inputEl, errId, 'El nombre de usuario es obligatorio.');
            if (v.length < 3 || v.length > 50) return setValidation(inputEl, errId, 'Debe tener entre 3 y 50 caracteres.');
            if (!REGEX_USER.test(v)) return setValidation(inputEl, errId, 'Solo letras, números, punto (.), guión (-) o guión bajo (_).');
            return setValidation(inputEl, errId, '');
        }

        function checkPersonName(inputEl, errId, label) {
            const v = inputEl.value.trim();
            if (!v) return setValidation(inputEl, errId, `El campo ${label} es obligatorio.`);
            if (v.length < 2 || v.length > 100) return setValidation(inputEl, errId, 'Debe tener entre 2 y 100 caracteres.');
            if (!REGEX_NAME.test(v)) return setValidation(inputEl, errId, 'Solo se permiten letras, espacios y guiones.');
            return setValidation(inputEl, errId, '');
        }

        function checkPhone(inputEl, errId) {
            const v = inputEl.value.trim();
            if (!v) return setValidation(inputEl, errId, '');
            if (!REGEX_PHONE.test(v)) return setValidation(inputEl, errId, 'Teléfono inválido (ej: 0414-1234567).');
            return setValidation(inputEl, errId, '');
        }

        function checkEmail(inputEl, errId) {
            const v = inputEl.value.trim();
            if (!v) return setValidation(inputEl, errId, '');
            if (v.length > 120 || !REGEX_EMAIL.test(v)) return setValidation(inputEl, errId, 'Ingrese un correo electrónico válido.');
            return setValidation(inputEl, errId, '');
        }

        function checkDireccion(inputEl, errId) {
            const v = inputEl.value.trim();
            if (v.length > 255) return setValidation(inputEl, errId, 'Máximo 255 caracteres.');
            return setValidation(inputEl, errId, '');
        }

        function checkPassword(inputEl, errId) {
            const v = inputEl.value;
            if (!v || v.trim() === '') return setValidation(inputEl, errId, 'La contraseña es obligatoria.');
            if (v.length < 6 || v.length > 128) return setValidation(inputEl, errId, 'Debe tener entre 6 y 128 caracteres.');
            return setValidation(inputEl, errId, '');
        }

        function checkPasswordConfirm(pwdEl, confirmEl, errId) {
            const v = confirmEl.value;
            if (!v) return setValidation(confirmEl, errId, 'Debe confirmar la contraseña.');
            if (v !== pwdEl.value) return setValidation(confirmEl, errId, 'Las contraseñas no coinciden.');
            return setValidation(confirmEl, errId, '');
        }

        function bindLiveValidation(inputEl, validator) {
            if (!inputEl) return;
            inputEl.addEventListener('blur', validator);
            inputEl.addEventListener('input', () => {
                if (inputEl.classList.contains('input-invalid') || inputEl.value.length > 0) {
                    validator();
                }
            });
        }

        // =====================================================================
        // INICIALIZACIÓN DE EVENTOS (VALIDACIONES + AJAX EN FILTROS Y MODALES)
        // =====================================================================
        (function initUserAjaxModule() {
            // 1) Búsqueda en vivo (debounce 300ms) y filtros de rol/estado vía AJAX
            const formFiltros = document.getElementById('formFiltrosUsuarios');
            const searchInput = document.getElementById('userSearch');
            const rolSelect = document.getElementById('filtroRol');
            const estatusSelect = document.getElementById('filtroEstatus');
            const btnLimpiar = document.getElementById('btnLimpiarFiltros');
            let searchTimer = null;

            formFiltros.addEventListener('submit', function (e) {
                e.preventDefault();
                cargarUsuariosAjax();
            });

            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(cargarUsuariosAjax, 300);
            });

            rolSelect.addEventListener('change', cargarUsuariosAjax);
            estatusSelect.addEventListener('change', cargarUsuariosAjax);

            btnLimpiar.addEventListener('click', function () {
                searchInput.value = '';
                rolSelect.value = '';
                estatusSelect.value = '';
                cargarUsuariosAjax();
            });

            // 2) Modal Crear Usuario: validar en frontend y enviar por AJAX
            const formNew = document.getElementById('formNuevoUsuario');
            const nCed = document.getElementById('new_cedula');
            const nUser = document.getElementById('new_user');
            const nNom = document.getElementById('new_nombre');
            const nApe = document.getElementById('new_apellido');
            const nTel = document.getElementById('new_telefono');
            const nCor = document.getElementById('new_correo');
            const nPwd = document.getElementById('new_password');
            const nPwdC = document.getElementById('new_password_confirm');

            bindLiveValidation(nCed, () => checkCedula(nCed, 'err_new_cedula'));
            bindLiveValidation(nUser, () => checkUser(nUser, 'err_new_user'));
            bindLiveValidation(nNom, () => checkPersonName(nNom, 'err_new_nombre', 'Nombres'));
            bindLiveValidation(nApe, () => checkPersonName(nApe, 'err_new_apellido', 'Apellidos'));
            bindLiveValidation(nTel, () => checkPhone(nTel, 'err_new_telefono'));
            bindLiveValidation(nCor, () => checkEmail(nCor, 'err_new_correo'));
            bindLiveValidation(nPwd, () => {
                const ok = checkPassword(nPwd, 'err_new_password');
                if (nPwdC.value !== '') checkPasswordConfirm(nPwd, nPwdC, 'err_new_password_confirm');
                return ok;
            });
            bindLiveValidation(nPwdC, () => checkPasswordConfirm(nPwd, nPwdC, 'err_new_password_confirm'));

            formNew.addEventListener('submit', function (e) {
                e.preventDefault();
                const checks = [
                    [nCed, checkCedula(nCed, 'err_new_cedula')],
                    [nUser, checkUser(nUser, 'err_new_user')],
                    [nNom, checkPersonName(nNom, 'err_new_nombre', 'Nombres')],
                    [nApe, checkPersonName(nApe, 'err_new_apellido', 'Apellidos')],
                    [nTel, checkPhone(nTel, 'err_new_telefono')],
                    [nCor, checkEmail(nCor, 'err_new_correo')],
                    [nPwd, checkPassword(nPwd, 'err_new_password')],
                    [nPwdC, checkPasswordConfirm(nPwd, nPwdC, 'err_new_password_confirm')],
                ];
                const firstFail = checks.find(([, ok]) => !ok);
                if (firstFail) {
                    firstFail[0].focus();
                    return;
                }
                enviarModalAjax(formNew, 'nuevoUserModal', 'alertNuevoUsuario');
            });

            // 3) Modal Editar Usuario: validar en frontend y enviar por AJAX
            const formEdit = document.getElementById('formEditarUsuario');
            const eUser = document.getElementById('edit_user');
            const eNom = document.getElementById('edit_nombre');
            const eApe = document.getElementById('edit_apellido');
            const eTel = document.getElementById('edit_telefono');
            const eCor = document.getElementById('edit_correo');
            const eDir = document.getElementById('edit_direccion');

            bindLiveValidation(eUser, () => checkUser(eUser, 'err_edit_user'));
            bindLiveValidation(eNom, () => checkPersonName(eNom, 'err_edit_nombre', 'Nombres'));
            bindLiveValidation(eApe, () => checkPersonName(eApe, 'err_edit_apellido', 'Apellidos'));
            bindLiveValidation(eTel, () => checkPhone(eTel, 'err_edit_telefono'));
            bindLiveValidation(eCor, () => checkEmail(eCor, 'err_edit_correo'));
            bindLiveValidation(eDir, () => checkDireccion(eDir, 'err_edit_direccion'));

            formEdit.addEventListener('submit', function (e) {
                e.preventDefault();
                const checks = [
                    [eUser, checkUser(eUser, 'err_edit_user')],
                    [eNom, checkPersonName(eNom, 'err_edit_nombre', 'Nombres')],
                    [eApe, checkPersonName(eApe, 'err_edit_apellido', 'Apellidos')],
                    [eTel, checkPhone(eTel, 'err_edit_telefono')],
                    [eCor, checkEmail(eCor, 'err_edit_correo')],
                    [eDir, checkDireccion(eDir, 'err_edit_direccion')],
                ];
                const firstFail = checks.find(([, ok]) => !ok);
                if (firstFail) {
                    firstFail[0].focus();
                    return;
                }
                enviarModalAjax(formEdit, 'editarUserModal', 'alertEditarUsuario');
            });

            // 4) Modal Cambiar Contraseña: validar en frontend y enviar por AJAX
            const formClave = document.getElementById('formCambiarClave');
            const cPwd = document.getElementById('nueva_clave');
            const cPwdC = document.getElementById('confirmar_clave');

            bindLiveValidation(cPwd, () => {
                const ok = checkPassword(cPwd, 'err_nueva_clave');
                if (cPwdC.value !== '') checkPasswordConfirm(cPwd, cPwdC, 'err_confirmar_clave');
                return ok;
            });
            bindLiveValidation(cPwdC, () => checkPasswordConfirm(cPwd, cPwdC, 'err_confirmar_clave'));

            formClave.addEventListener('submit', function (e) {
                e.preventDefault();
                const ok1 = checkPassword(cPwd, 'err_nueva_clave');
                const ok2 = checkPasswordConfirm(cPwd, cPwdC, 'err_confirmar_clave');
                if (!ok1 || !ok2) {
                    (!ok1 ? cPwd : cPwdC).focus();
                    return;
                }
                enviarModalAjax(formClave, 'cambiarClaveModal', 'alertCambiarClave');
            });
        })();

        function abrirModalCrear() {
            const form = document.getElementById('formNuevoUsuario');
            form.reset();
            clearFormValidation(form);
            showModalAlert('alertNuevoUsuario', '');
            openModal('nuevoUserModal');
        }

        function abrirModalClave(btn) {
            const form = document.getElementById('formCambiarClave');
            clearFormValidation(form);
            showModalAlert('alertCambiarClave', '');
            const cedula = btn.dataset.cedula || '';
            const username = btn.dataset.user || '';
            document.getElementById('clave_cedula').value = cedula;
            document.getElementById('clave_username').textContent = username + ' (' + cedula + ')';
            document.getElementById('nueva_clave').value = '';
            document.getElementById('confirmar_clave').value = '';
            openModal('cambiarClaveModal');
        }

        function abrirModalEditar(btn) {
            const form = document.getElementById('formEditarUsuario');
            clearFormValidation(form);
            showModalAlert('alertEditarUsuario', '');
            const isSelf = btn.dataset.self === '1';
            const rol = btn.dataset.rol || 'OPERADOR';
            document.getElementById('edit_cedula').value = btn.dataset.cedula || '';
            document.getElementById('edit_cedula_display').value = btn.dataset.cedula || '';
            document.getElementById('edit_user').value = btn.dataset.user || '';
            document.getElementById('edit_nombre').value = btn.dataset.nombre || '';
            document.getElementById('edit_apellido').value = btn.dataset.apellido || '';
            document.getElementById('edit_correo').value = btn.dataset.correo || '';
            document.getElementById('edit_telefono').value = btn.dataset.telefono || '';
            document.getElementById('edit_direccion').value = btn.dataset.direccion || '';

            const rolSelect = document.getElementById('edit_rol');
            const rolHidden = document.getElementById('edit_rol_hidden');
            rolSelect.value = rol;
            if (isSelf && rol === 'ADMIN') {
                rolSelect.disabled = true;
                rolHidden.name = 'rol';
                rolHidden.value = rol;
                rolHidden.disabled = false;
            } else {
                rolSelect.disabled = false;
                rolHidden.removeAttribute('name');
                rolHidden.disabled = true;
            }

            openModal('editarUserModal');
        }

        // Cerrar modales al hacer clic fuera o presionar Escape
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closeModal(overlay.id);
                }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => closeModal(m.id));
            }
        });

        function updateClock() {
            const now = new Date();
            const el = document.getElementById('liveClock');
            if (el) el.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0') + ':' + String(now.getSeconds()).padStart(2, '0');
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
