<?php

declare(strict_types=1);

/**
 * Controlador del Dashboard Visual - SIAMTEL (Sistema de Morbilidad Médica)
 * Gobernación del Estado Lara
 *
 * Responsabilidad Única (SRP): Presentar métricas epidemiológicas y KPIs ejecutivos.
 */

use App\Core\Database;
use App\Models\Cliente;
use App\Models\Usuario;

// 1. Verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    redirect('login');
}

$currentUser = $_SESSION['user'];

// 2. Cargar Métricas Clínicas y Epidemiológicas
$clienteModel = new Cliente();
$usuarioModel = new Usuario();

$pacientesRes = $clienteModel->Transaccion(['peticion' => 'consultar']);
$totalPacientes = (int)($pacientesRes['response']['total'] ?? 0);

$statsRes = $clienteModel->Transaccion(['peticion' => 'estadisticas_morbilidad']);
$estadisticas = $statsRes['response'] ?? [];

$totalAtenciones = $estadisticas['total_atenciones'] ?? 0;
$morbilidadPorTipo = $estadisticas['por_tipo'] ?? [];
$topDiagnosticos = $estadisticas['top_diagnosticos'] ?? [];
$consultasRecientes = array_slice($estadisticas['recientes'] ?? [], 0, 5);

// 3. Métricas de Personal y Servidor
$usuariosRes = $usuarioModel->Transaccion(['peticion' => 'consultar']);
$totalUsuarios = (int)($usuariosRes['response']['total'] ?? 0);

$dbVersion = 'MariaDB 10.11';
try {
    $db = Database::getConnection();
    if ($db) {
        $v = $db->query("SELECT VERSION()")->fetchColumn();
        if ($v) $dbVersion = (string)$v;
    }
} catch (\Throwable $th) {}

// 4. Renderizar Vista Visual del Dashboard
view('dashboard/index', [
    'titulo'             => 'Dashboard Visual - SIAMTEL Morbilidad',
    'currentUser'        => $currentUser,
    'totalPacientes'     => $totalPacientes,
    'totalAtenciones'    => $totalAtenciones,
    'morbilidadPorTipo'  => $morbilidadPorTipo,
    'topDiagnosticos'    => $topDiagnosticos,
    'consultasRecientes' => $consultasRecientes,
    'totalUsuarios'      => $totalUsuarios,
    'dbVersion'          => $dbVersion,
    'submodulos'         => $_SESSION['submodulos'] ?? [],
]);
