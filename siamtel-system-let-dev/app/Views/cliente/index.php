<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Pacientes / Clientes - SIAMTEL') ?></title>
    <!-- Estilos Dashboard y Bootstrap Icons -->
    <link rel="stylesheet" href="/css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <!-- SIDEBAR DRY -->
    <?php require BASE_PATH . '/app/Views/layout/sidebar.php'; ?>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="dash-main">
        <!-- TOPBAR DRY -->
        <?php require BASE_PATH . '/app/Views/layout/topbar.php'; ?>

        <div class="dash-content">

            <!-- Mensajes Flash -->
            <?php if (!empty($_SESSION['paciente_success'])): ?>
                <div class="alert-banner alert-banner-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <div style="flex: 1;"><?= htmlspecialchars($_SESSION['paciente_success']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#065f46;"><i class="bi bi-x-lg"></i></button>
                </div>
                <?php unset($_SESSION['paciente_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['paciente_error'])): ?>
                <div class="alert-banner alert-banner-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div style="flex: 1;"><?= htmlspecialchars($_SESSION['paciente_error']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#991b1b;"><i class="bi bi-x-lg"></i></button>
                </div>
                <?php unset($_SESSION['paciente_error']); ?>
            <?php endif; ?>

            <!-- LISTADO Y GESTIÓN DE PACIENTES -->
            <div class="content-card">
                <div class="card-header-custom">
                    <div class="card-header-title">
                        <i class="bi bi-person-vcard-fill" style="color: #0284c7;"></i>
                        <span>Registro de Pacientes / Clientes (Estado Lara)</span>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="pacSearch" placeholder="Buscar por cédula, nombre o correo..." onkeyup="filterPacientes()" style="padding: 7px 12px; font-size: 13px; border: 1px solid var(--border-color); border-radius: 6px; outline: none; width: 260px;">
                        <button type="button" class="btn-primary-custom" onclick="openModal('nuevoPacModal')" style="background: #0284c7;">
                            <i class="bi bi-person-plus-fill"></i>
                            <span>Nuevo Paciente</span>
                        </button>
                    </div>
                </div>

                <div class="card-body-custom" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="custom-table" id="tablaPacientes">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Paciente</th>
                                    <th>Edad / Sexo</th>
                                    <th>Grupo Sang.</th>
                                    <th>Contacto & Ubicación</th>
                                    <th>Historial Clínico</th>
                                    <th style="text-align: right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pacientes)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                            No se han registrado pacientes en el sistema.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pacientes as $p): ?>
                                        <tr>
                                            <td style="font-weight: 700; color: #1e3c72;">
                                                <?= htmlspecialchars($p['cedula']) ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($p['nombre_completo']) ?></strong>
                                                <div style="font-size: 11px; color: var(--text-secondary);">
                                                    Reg: <?= date('d/m/Y', strtotime($p['fecha_registro'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars((string)($p['edad'] ?? '--')) ?> años &bull; 
                                                <span class="badge-role" style="font-size: 10px;"><?= $p['sexo'] === 'F' ? 'Femenino' : 'Masculino' ?></span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 700; color: #b91c1c; background: #fee2e2; padding: 2px 8px; border-radius: 4px; font-size: 11px;">
                                                    <?= htmlspecialchars($p['grupo_sanguineo'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-size: 12px;"><?= htmlspecialchars($p['telefono'] ?? '--') ?></div>
                                                <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($p['direccion'] ?? 'Lara') ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($p['alergias']) && $p['alergias'] !== 'Ninguna'): ?>
                                                    <div style="font-size: 11px; color: #dc2626;"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($p['alergias']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($p['antecedentes']) && $p['antecedentes'] !== 'Ninguno'): ?>
                                                    <div style="font-size: 11px; color: #475569;"><?= htmlspecialchars($p['antecedentes']) ?></div>
                                                <?php endif; ?>
                                                <?php if (empty($p['alergias']) && empty($p['antecedentes'])): ?>
                                                    <span style="font-size: 11px; color: var(--text-secondary); font-style: italic;">Sin antecedentes</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <div style="display: inline-flex; gap: 6px;">
                                                    <a href="/morbilidad?cedula=<?= urlencode($p['cedula']) ?>" class="btn-sm-action btn-sm-success" title="Registrar consulta médica">
                                                        <i class="bi bi-file-earmark-medical-fill"></i> Consulta
                                                    </a>
                                                    <form method="POST" action="/pacientes" style="display: inline;" onsubmit="return confirm('¿Inhabilitar al paciente <?= htmlspecialchars($p['nombre_completo']) ?>?');">
                                                        <input type="hidden" name="accion" value="eliminar_paciente">
                                                        <input type="hidden" name="cedula" value="<?= htmlspecialchars($p['cedula']) ?>">
                                                        <button type="submit" class="btn-sm-action btn-sm-danger" title="Inactivar registro">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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

    <!-- MODAL: REGISTRAR NUEVO PACIENTE -->
    <div id="nuevoPacModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 600px;">
            <div class="modal-header-custom">
                <h3><i class="bi bi-person-plus-fill" style="color: #0284c7;"></i> Registrar Paciente / Cliente</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('nuevoPacModal')">&times;</button>
            </div>
            <form method="POST" action="/pacientes">
                <input type="hidden" name="accion" value="registrar_paciente">

                <div class="modal-body-custom" style="max-height: 70vh; overflow-y: auto;">
                    <div style="display: flex; gap: 10px;">
                        <div style="width: 100px;">
                            <label class="form-label-custom">Nac.</label>
                            <select name="particle" class="form-input-custom" style="background:#fff;">
                                <option value="V-">V-</option>
                                <option value="E-">E-</option>
                                <option value="J-">J-</option>
                                <option value="P-">P-</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label class="form-label-custom">Cédula de Identidad *</label>
                            <input type="text" name="ci" class="form-input-custom" placeholder="Ej: 12345678" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label class="form-label-custom">Nombres *</label>
                            <input type="text" name="nombre" class="form-input-custom" placeholder="Ej: María José" required>
                        </div>
                        <div style="flex: 1;">
                            <label class="form-label-custom">Apellidos *</label>
                            <input type="text" name="apellido" class="form-input-custom" placeholder="Ej: Gómez Pérez" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label class="form-label-custom">Fecha de Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" class="form-input-custom">
                        </div>
                        <div style="width: 120px;">
                            <label class="form-label-custom">Sexo</label>
                            <select name="sexo" class="form-input-custom" style="background:#fff;">
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                            </select>
                        </div>
                        <div style="width: 120px;">
                            <label class="form-label-custom">Grupo Sang.</label>
                            <select name="grupo_sanguineo" class="form-input-custom" style="background:#fff;">
                                <option value="">Desconocido</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label class="form-label-custom">Teléfono de Contacto</label>
                            <input type="text" name="telefono" class="form-input-custom" placeholder="Ej: 0414-1234567">
                        </div>
                        <div style="flex: 1;">
                            <label class="form-label-custom">Correo Electrónico</label>
                            <input type="email" name="correo" class="form-input-custom" placeholder="Ej: paciente@email.com">
                        </div>
                    </div>

                    <label class="form-label-custom">Dirección de Habitación (Municipio / Parroquia Lara)</label>
                    <input type="text" name="direccion" class="form-input-custom" placeholder="Ej: Barquisimeto, Parroquia Catedral, Carrera 19 con Calle 25">

                    <label class="form-label-custom">Alergias Conocidas</label>
                    <input type="text" name="alergias" class="form-input-custom" placeholder="Ej: Penicilina, AINEs (o 'Ninguna')">

                    <label class="form-label-custom">Antecedentes Médicos Relevantes</label>
                    <input type="text" name="antecedentes" class="form-input-custom" placeholder="Ej: Diabetes Tipo 2, Hipertensión arterial, Asma">
                </div>

                <div class="modal-footer-custom">
                    <button type="button" class="btn-sm-action" onclick="closeModal('nuevoPacModal')">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="background: #0284c7;">Guardar Paciente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        }
        function filterPacientes() {
            const filter = document.getElementById('pacSearch').value.toLowerCase();
            const table = document.getElementById('tablaPacientes');
            const rows = table.getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                const text = rows[i].textContent || rows[i].innerText;
                rows[i].style.display = (text.toLowerCase().indexOf(filter) > -1) ? '' : 'none';
            }
        }
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

