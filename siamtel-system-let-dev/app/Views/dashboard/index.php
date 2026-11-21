<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Dashboard Visual - SIAMTEL Morbilidad') ?></title>
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

            <!-- Mensajes de Alerta -->
            <?php if (!empty($_SESSION['dash_success'])): ?>
                <div class="alert-banner alert-banner-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <div style="flex: 1;"><?= htmlspecialchars($_SESSION['dash_success']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#065f46;"><i class="bi bi-x-lg"></i></button>
                </div>
                <?php unset($_SESSION['dash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['dash_error'])): ?>
                <div class="alert-banner alert-banner-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div style="flex: 1;"><?= htmlspecialchars($_SESSION['dash_error']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#991b1b;"><i class="bi bi-x-lg"></i></button>
                </div>
                <?php unset($_SESSION['dash_error']); ?>
            <?php endif; ?>

            <!-- 1. KPIS CLÍNICOS Y EJECUTIVOS -->
            <div class="kpi-grid">
                <!-- KPI 1: Pacientes -->
                <div class="kpi-card">
                    <div class="kpi-details">
                        <div class="kpi-label">Pacientes / Clientes</div>
                        <div class="kpi-value" style="color: #0284c7;"><?= $totalPacientes ?></div>
                        <div class="kpi-sub">Población registrada en Lara</div>
                    </div>
                    <div class="kpi-icon-wrap icon-blue">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>

                <!-- KPI 2: Morbilidades -->
                <div class="kpi-card">
                    <div class="kpi-details">
                        <div class="kpi-label">Casos de Morbilidad</div>
                        <div class="kpi-value" style="color: #059669;"><?= $totalAtenciones ?></div>
                        <div class="kpi-sub">Consultas procesadas</div>
                    </div>
                    <div class="kpi-icon-wrap icon-green">
                        <i class="bi bi-journal-medical"></i>
                    </div>
                </div>

                <!-- KPI 3: Categorías -->
                <div class="kpi-card">
                    <div class="kpi-details">
                        <div class="kpi-label">Tipos Monitoreados</div>
                        <div class="kpi-value" style="color: #d97706;"><?= count($morbilidadPorTipo) ?></div>
                        <div class="kpi-sub">Agudas, Crónicas, Respiratorias</div>
                    </div>
                    <div class="kpi-icon-wrap icon-amber">
                        <i class="bi bi-activity"></i>
                    </div>
                </div>

                <!-- KPI 4: Personal del Sistema -->
                <div class="kpi-card">
                    <div class="kpi-details">
                        <div class="kpi-label">Personal del Sistema</div>
                        <div class="kpi-value" style="color: #1e3c72;"><?= $totalUsuarios ?></div>
                        <div class="kpi-sub">Médicos y operadores</div>
                    </div>
                    <div class="kpi-icon-wrap icon-purple">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                </div>
            </div>

            <!-- 2. ACCESOS DIRECTOS A MÓDULOS ESPECIALIZADOS -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <a href="/pacientes" style="text-decoration: none; color: inherit;">
                    <div class="content-card" style="padding: 18px; margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s; border-left: 4px solid #0284c7;">
                        <div>
                            <div style="font-weight: 700; font-size: 15px; color: #1e3c72;">Módulo de Pacientes</div>
                            <div style="font-size: 12px; color: var(--text-secondary);">Ver historias y admisiones clínicas</div>
                        </div>
                        <i class="bi bi-arrow-right-circle-fill" style="font-size: 24px; color: #0284c7;"></i>
                    </div>
                </a>

                <a href="/morbilidad" style="text-decoration: none; color: inherit;">
                    <div class="content-card" style="padding: 18px; margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s; border-left: 4px solid #059669;">
                        <div>
                            <div style="font-weight: 700; font-size: 15px; color: #1e3c72;">Módulo de Morbilidad</div>
                            <div style="font-size: 12px; color: var(--text-secondary);">Registrar consultas y CIE-10</div>
                        </div>
                        <i class="bi bi-arrow-right-circle-fill" style="font-size: 24px; color: #059669;"></i>
                    </div>
                </a>

                <?php if (strtoupper((string)($currentUser['rol'] ?? '')) === 'ADMIN' || !empty($_SESSION['permisos']['usuarios']['ver'])): ?>
                    <a href="/usuarios" style="text-decoration: none; color: inherit;">
                        <div class="content-card" style="padding: 18px; margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s; border-left: 4px solid #7c3aed;">
                            <div>
                                <div style="font-weight: 700; font-size: 15px; color: #1e3c72;">Gestión de Usuarios</div>
                                <div style="font-size: 12px; color: var(--text-secondary);">Roles, cuentas y submódulos</div>
                            </div>
                            <i class="bi bi-arrow-right-circle-fill" style="font-size: 24px; color: #7c3aed;"></i>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <!-- 3. GRAFICAS Y DISTRIBUCION EPIDEMIOLOGICA (VISUAL) -->
            <div class="dashboard-layout-grid">
                
                <!-- COLUMNA IZQUIERDA: RESUMEN DE MORBILIDADES RECIENTES -->
                <div>
                    <div class="content-card">
                        <div class="card-header-custom">
                            <div class="card-header-title">
                                <i class="bi bi-clipboard2-pulse-fill" style="color: #ef4444;"></i>
                                <span>Últimas Consultas de Morbilidad Atendidas</span>
                            </div>
                            <a href="/morbilidad" class="btn-primary-custom" style="padding: 6px 12px; font-size: 12px; background: #0284c7;">
                                Ver Todas &rarr;
                            </a>
                        </div>
                        <div class="card-body-custom" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="custom-table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Paciente</th>
                                            <th>Diagnóstico</th>
                                            <th>Tipo</th>
                                            <th>Médico</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($consultasRecientes)): ?>
                                            <tr>
                                                <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                                    Sin atenciones registradas todavía.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($consultasRecientes as $c): ?>
                                                <tr>
                                                    <td style="font-size: 12px; color: var(--text-secondary);">
                                                        <?= date('d/m/Y', strtotime($c['fecha_atencion'])) ?>
                                                    </td>
                                                    <td style="font-weight: 600;">
                                                        <?= htmlspecialchars($c['paciente_nombre'] ?? $c['cedula_cliente']) ?>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($c['diagnostico']) ?></div>
                                                        <?php if (!empty($c['codigo_cie10'])): ?>
                                                            <span style="font-size: 10px; background: #e2e8f0; padding: 1px 6px; border-radius: 4px;">CIE-10: <?= htmlspecialchars($c['codigo_cie10']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge-role" style="font-size: 11px;">
                                                            <?= htmlspecialchars($c['tipo_morbilidad']) ?>
                                                        </span>
                                                    </td>
                                                    <td style="font-size: 12px;">
                                                        <?= htmlspecialchars($c['medico_tratante']) ?>
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

                <!-- COLUMNA DERECHA: DISTRIBUCIÓN Y TOP PATOLOGÍAS -->
                <div>
                    <!-- DISTRIBUCIÓN PORCENTUAL -->
                    <div class="content-card">
                        <div class="card-header-custom">
                            <div class="card-header-title">
                                <i class="bi bi-pie-chart-fill" style="color: #059669;"></i>
                                <span>Distribución Epidemiológica</span>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <?php if (empty($morbilidadPorTipo)): ?>
                                <p style="font-size: 13px; color: var(--text-secondary); text-align: center;">Sin registros aún.</p>
                            <?php else: ?>
                                <?php foreach ($morbilidadPorTipo as $tipo): ?>
                                    <?php 
                                        $porcentaje = $totalAtenciones > 0 ? round(((int)$tipo['cantidad'] / $totalAtenciones) * 100) : 0;
                                    ?>
                                    <div style="margin-bottom: 14px;">
                                        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                                            <span><?= htmlspecialchars($tipo['tipo_morbilidad']) ?></span>
                                            <span><?= $tipo['cantidad'] ?> casos (<?= $porcentaje ?>%)</span>
                                        </div>
                                        <div style="background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="background: linear-gradient(90deg, #0284c7, #1e3c72); width: <?= $porcentaje ?>%; height: 100%; border-radius: 4px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TOP DIAGNÓSTICOS -->
                    <div class="content-card">
                        <div class="card-header-custom">
                            <div class="card-header-title">
                                <i class="bi bi-bar-chart-line-fill" style="color: #d97706;"></i>
                                <span>Diagnósticos Más Frecuentes</span>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <?php if (empty($topDiagnosticos)): ?>
                                <p style="font-size: 13px; color: var(--text-secondary); text-align: center;">Sin patologías registradas.</p>
                            <?php else: ?>
                                <ul class="info-list">
                                    <?php foreach ($topDiagnosticos as $diag): ?>
                                        <li class="info-item">
                                            <div>
                                                <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($diag['diagnostico']) ?></div>
                                                <?php if (!empty($diag['codigo_cie10'])): ?>
                                                    <span style="font-size: 10px; color: var(--text-secondary);">CIE-10: <?= htmlspecialchars($diag['codigo_cie10']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span style="background: #ecfdf5; color: #065f46; font-weight: 700; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                                <?= $diag['casos'] ?> casos
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </main>

    <script>
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            const el = document.getElementById('liveClock');
            if (el) el.textContent = `${h}:${m}:${s}`;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
