<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Registro de Morbilidad - SIAMTEL') ?></title>
    <!-- Estilos Dashboard y Bootstrap Icons -->
    <link rel="stylesheet" href="/css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .badge-cronica { background: #fee2e2; color: #b91c1c; }
        .badge-aguda { background: #fef3c7; color: #b45309; }
        .badge-respiratoria { background: #e0f2fe; color: #0369a1; }
        .badge-infecciosa { background: #f3e8ff; color: #7e22ce; }
        .badge-otra { background: #f1f5f9; color: #475569; }
    </style>
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
            <?php if (!empty($_SESSION['morb_success'])): ?>
                <div class="alert-banner alert-banner-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <div style="flex: 1;"><?= htmlspecialchars($_SESSION['morb_success']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#065f46;"><i class="bi bi-x-lg"></i></button>
                </div>
                <?php unset($_SESSION['morb_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['morb_error'])): ?>
                <div class="alert-banner alert-banner-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div style="flex: 1;"><?= htmlspecialchars($_SESSION['morb_error']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#991b1b;"><i class="bi bi-x-lg"></i></button>
                </div>
                <?php unset($_SESSION['morb_error']); ?>
            <?php endif; ?>

            <!-- REGISTRO GENERAL DE MORBILIDADES -->
            <div class="content-card">
                <div class="card-header-custom">
                    <div class="card-header-title">
                        <i class="bi bi-journal-medical" style="color: #059669;"></i>
                        <span>Registro Clínico de Morbilidad & Consultas (CIE-10)</span>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="morbSearch" placeholder="Buscar por paciente, diagnóstico o CIE-10..." onkeyup="filterMorbilidades()" style="padding: 7px 12px; font-size: 13px; border: 1px solid var(--border-color); border-radius: 6px; outline: none; width: 280px;">
                        <button type="button" class="btn-primary-custom" onclick="openModal('nuevaMorbModal')" style="background: #059669;">
                            <i class="bi bi-clipboard2-pulse"></i>
                            <span>Nueva Consulta</span>
                        </button>
                    </div>
                </div>

                <div class="card-body-custom" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="custom-table" id="tablaMorbilidad">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Paciente Atendido</th>
                                    <th>Diagnóstico Clínico (CIE-10)</th>
                                    <th>Tipo de Patología</th>
                                    <th>Especialidad & Médico</th>
                                    <th>Tratamiento & Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($casosMorbilidad)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                            No se han registrado atenciones de morbilidad en el sistema.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($casosMorbilidad as $m): ?>
                                        <?php
                                            $tipoClase = match (strtolower((string)$m['tipo_morbilidad'])) {
                                                'crónica', 'cronica' => 'badge-cronica',
                                                'aguda' => 'badge-aguda',
                                                'respiratoria' => 'badge-respiratoria',
                                                'infecciosa' => 'badge-infecciosa',
                                                default => 'badge-otra'
                                            };
                                        ?>
                                        <tr>
                                            <td style="font-size: 12px; color: var(--text-secondary);">
                                                <?= date('d/m/Y H:i', strtotime($m['fecha_atencion'])) ?>
                                            </td>
                                            <td>
                                                <strong style="color: #1e3c72;"><?= htmlspecialchars($m['paciente_nombre'] ?? $m['cedula_cliente']) ?></strong>
                                                <div style="font-size: 11px; color: var(--text-secondary);">
                                                    <?= htmlspecialchars($m['cedula_cliente']) ?> &bull; <?= $m['edad'] ?? '--' ?> años (<?= $m['sexo'] ?? 'M' ?>)
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($m['diagnostico']) ?></div>
                                                <?php if (!empty($m['codigo_cie10'])): ?>
                                                    <span style="font-size: 11px; background: #e2e8f0; color: #334155; padding: 1px 6px; border-radius: 4px; font-weight: 600;">
                                                        CIE-10: <?= htmlspecialchars($m['codigo_cie10']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <div style="font-size: 11px; color: #64748b; font-style: italic; margin-top: 2px;">
                                                    Motivo: <?= htmlspecialchars($m['motivo_consulta']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-role <?= $tipoClase ?>">
                                                    <?= htmlspecialchars($m['tipo_morbilidad']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-size: 13px; font-weight: 600;"><?= htmlspecialchars($m['medico_tratante']) ?></div>
                                                <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($m['especialidad']) ?></div>
                                            </td>
                                            <td style="font-size: 12px; max-width: 250px;">
                                                <div style="font-weight: 600; color: #334155;">
                                                    <?= htmlspecialchars($m['tratamiento'] ?: 'Indicaciones verbales') ?>
                                                </div>
                                                <?php if (!empty($m['observaciones'])): ?>
                                                    <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                                                        <i class="bi bi-info-circle"></i> <?= htmlspecialchars($m['observaciones']) ?>
                                                    </div>
                                                <?php endif; ?>
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

    <!-- MODAL: REGISTRAR CONSULTA DE MORBILIDAD -->
    <div id="nuevaMorbModal" class="modal-overlay <?= !empty($selectedCedula) ? 'active' : '' ?>">
        <div class="modal-box" style="max-width: 650px;">
            <div class="modal-header-custom">
                <h3><i class="bi bi-clipboard2-pulse-fill" style="color: #059669;"></i> Registrar Consulta de Morbilidad</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('nuevaMorbModal')">&times;</button>
            </div>
            <form method="POST" action="/morbilidad">
                <input type="hidden" name="accion" value="registrar_morbilidad">

                <div class="modal-body-custom" style="max-height: 70vh; overflow-y: auto;">
                    <label class="form-label-custom">Seleccionar Paciente / Cliente *</label>
                    <select name="cedula_cliente" class="form-input-custom" style="background:#fff;" required>
                        <option value="">-- Seleccionar Paciente Registrado --</option>
                        <?php foreach ($pacientes as $p): ?>
                            <option value="<?= htmlspecialchars($p['cedula']) ?>" <?= ($p['cedula'] === $selectedCedula) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['cedula']) ?> - <?= htmlspecialchars($p['nombre_completo']) ?> (<?= $p['edad'] ?? '?' ?> años)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="form-label-custom">Motivo de la Consulta *</label>
                    <input type="text" name="motivo_consulta" class="form-input-custom" placeholder="Ej: Cefalea intensa, fiebre de 38°C y dolor retroocular" required>

                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 2;">
                            <label class="form-label-custom">Diagnóstico Clínico *</label>
                            <input type="text" name="diagnostico" class="form-input-custom" placeholder="Ej: Síndrome Febril Agudo / Sospecha Dengue" required>
                        </div>
                        <div style="flex: 1;">
                            <label class="form-label-custom">Código CIE-10</label>
                            <input type="text" name="codigo_cie10" class="form-input-custom" placeholder="Ej: A90">
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label class="form-label-custom">Tipo de Morbilidad</label>
                            <select name="tipo_morbilidad" class="form-input-custom" style="background:#fff;">
                                <option value="Aguda">Aguda</option>
                                <option value="Crónica">Crónica</option>
                                <option value="Infecciosa">Infecciosa</option>
                                <option value="Respiratoria">Respiratoria</option>
                                <option value="Cardiovascular">Cardiovascular</option>
                                <option value="Otra">Otra</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label class="form-label-custom">Especialidad Médica</label>
                            <select name="especialidad" class="form-input-custom" style="background:#fff;">
                                <option value="Medicina General">Medicina General</option>
                                <option value="Medicina Interna">Medicina Interna</option>
                                <option value="Pediatría">Pediatría</option>
                                <option value="Ginecología y Obstetricia">Ginecología y Obstetricia</option>
                                <option value="Neumonología">Neumonología</option>
                                <option value="Traumatología">Traumatología</option>
                                <option value="Epidemiología">Epidemiología</option>
                            </select>
                        </div>
                    </div>

                    <label class="form-label-custom">Médico Tratante</label>
                    <input type="text" name="medico_tratante" class="form-input-custom" value="Dr. <?= htmlspecialchars($currentUser['user'] ?? 'Médico de Guardia') ?>" placeholder="Nombre del Médico">

                    <label class="form-label-custom">Tratamiento Indicado</label>
                    <textarea name="tratamiento" class="form-input-custom" rows="2" placeholder="Ej: Acetaminofén 500mg VO c/6h en caso de fiebre, hidratación oral con suero..." style="resize:vertical;"></textarea>

                    <label class="form-label-custom">Observaciones Clínicas / Seguimiento</label>
                    <input type="text" name="observaciones" class="form-input-custom" placeholder="Ej: Control por consulta externa en 72 horas con hemograma">
                </div>

                <div class="modal-footer-custom">
                    <button type="button" class="btn-sm-action" onclick="closeModal('nuevaMorbModal')">Cancelar</button>
                    <button type="submit" class="btn-primary-custom" style="background: #059669;">Guardar Caso de Morbilidad</button>
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
        function filterMorbilidades() {
            const filter = document.getElementById('morbSearch').value.toLowerCase();
            const table = document.getElementById('tablaMorbilidad');
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

