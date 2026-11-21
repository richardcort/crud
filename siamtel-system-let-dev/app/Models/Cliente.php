<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Modelo de Cliente / Paciente - SIAMTEL (Sistema de Morbilidad)
 * Gobernación del Estado Lara
 *
 * Principio SOLID: Los métodos de operación a BD son privados y se accede a través del
 * único método público de interfaz operacional: Transaccion($params).
 */
class Cliente extends Persona
{
    private ?string $grupo_sanguineo = null;
    private ?string $alergias = null;
    private ?string $antecedentes = null;
    private int $estatus = 1;
    private ?string $fecha_registro = null;

    public function __construct()
    {
        $this->estatus = 1;
    }

    public function setGrupoSanguineo(?string $grupo): void
    {
        $this->grupo_sanguineo = ($grupo !== null && trim($grupo) !== '') ? strtoupper(trim($grupo)) : null;
    }

    public function getGrupoSanguineo(): ?string
    {
        return $this->grupo_sanguineo;
    }

    public function setAlergias(?string $alergias): void
    {
        $this->alergias = ($alergias !== null && trim($alergias) !== '') ? trim($alergias) : null;
    }

    public function getAlergias(): ?string
    {
        return $this->alergias;
    }

    public function setAntecedentes(?string $antecedentes): void
    {
        $this->antecedentes = ($antecedentes !== null && trim($antecedentes) !== '') ? trim($antecedentes) : null;
    }

    public function getAntecedentes(): ?string
    {
        return $this->antecedentes;
    }

    public function setEstatus(int $estatus): void
    {
        if (!in_array($estatus, [0, 1], true)) {
            throw new Exception('El estatus debe ser 0 (inactivo) o 1 (activo).');
        }
        $this->estatus = $estatus;
    }

    public function getEstatus(): int
    {
        return $this->estatus;
    }

    /**
     * ÚNICO método público para ejecutar operaciones en el modelo
     */
    public function Transaccion(array $params = []): array
    {
        $accion = $params['peticion'] ?? '';

        return match ($accion) {
            'registrar'               => $this->registrarCliente($params['datos'] ?? []),
            'consultar'               => $this->consultarClientes($params['busqueda'] ?? null),
            'consultar_uno'           => $this->obtenerCliente($params['cedula'] ?? $this->cedula),
            'actualizar', 'modificar' => $this->modificarCliente($params['datos'] ?? []),
            'eliminar'                => $this->eliminarCliente($params['cedula'] ?? $this->cedula),
            'verificar_cedula'        => $this->verificarCedulaExiste($params['cedula'] ?? $this->cedula),
            'registrar_morbilidad'    => $this->registrarMorbilidad($params['datos'] ?? []),
            'consultar_morbilidades'  => $this->consultarTodasMorbilidades($params['busqueda'] ?? null),
            'historial_morbilidad'    => $this->consultarHistorialMorbilidad($params['cedula'] ?? $this->cedula),
            'estadisticas_morbilidad' => $this->obtenerEstadisticasMorbilidad(),
            default => [
                'response' => [
                    'resultado' => 400,
                    'mensaje'   => "Petición no válida o no soportada: '{$accion}'",
                ],
                'HTTP_STATUS' => ['codigo' => 400, 'mensaje' => 'Bad Request']
            ]
        };
    }

    // =========================================================================
    // MÉTODOS PRIVADOS DE OPERACIÓN A BASE DE DATOS
    // =========================================================================

    /**
     * Registra un nuevo paciente/cliente en persona y cliente
     */
    private function registrarCliente(array $datos = []): array
    {
        $cedula = trim($datos['cedula'] ?? $this->cedula);
        $nombre = trim($datos['nombre'] ?? $this->nombre);
        $apellido = trim($datos['apellido'] ?? $this->apellido);

        if (empty($cedula) || empty($nombre) || empty($apellido)) {
            return [
                'response' => [
                    'resultado' => 400,
                    'mensaje'   => 'Cédula, nombre y apellido son campos obligatorios.'
                ]
            ];
        }

        $verif = $this->verificarCedulaExiste($cedula);
        if ($verif['response']['existe'] ?? false) {
            return [
                'response' => [
                    'resultado' => 409,
                    'mensaje'   => "El paciente con cédula {$cedula} ya se encuentra registrado."
                ]
            ];
        }

        $pdo = Database::getConnection();
        if ($pdo === null) {
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error de conexión a la base de datos']];
        }

        try {
            $pdo->beginTransaction();

            $sqlPersona = "INSERT INTO persona (cedula, nombre, apellido, fecha_nacimiento, telefono, correo, direccion, sexo)
                           VALUES (:cedula, :nombre, :apellido, :fecha_nac, :telefono, :correo, :direccion, :sexo)";
            $stmtP = $pdo->prepare($sqlPersona);
            $stmtP->execute([
                ':cedula'    => $cedula,
                ':nombre'    => $nombre,
                ':apellido'  => $apellido,
                ':fecha_nac' => $datos['fecha_nacimiento'] ?? $this->fecha_nacimiento,
                ':telefono'  => $datos['telefono'] ?? $this->telefono,
                ':correo'    => $datos['correo'] ?? $this->correo,
                ':direccion' => $datos['direccion'] ?? $this->direccion,
                ':sexo'      => $datos['sexo'] ?? $this->sexo,
            ]);

            $sqlCliente = "INSERT INTO cliente (cedula, grupo_sanguineo, alergias, antecedentes, estatus)
                           VALUES (:cedula, :grupo, :alergias, :antecedentes, :estatus)";
            $stmtC = $pdo->prepare($sqlCliente);
            $stmtC->execute([
                ':cedula'       => $cedula,
                ':grupo'        => $datos['grupo_sanguineo'] ?? $this->grupo_sanguineo,
                ':alergias'     => $datos['alergias'] ?? $this->alergias,
                ':antecedentes' => $datos['antecedentes'] ?? $this->antecedentes,
                ':estatus'      => $this->estatus,
            ]);

            $pdo->commit();

            return [
                'response' => [
                    'resultado' => 200,
                    'mensaje'   => "Paciente {$nombre} {$apellido} ({$cedula}) registrado exitosamente.",
                    'cedula'    => $cedula
                ],
                'HTTP_STATUS' => ['codigo' => 200, 'mensaje' => 'OK']
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'response' => [
                    'resultado' => 500,
                    'mensaje'   => 'Error al registrar paciente: ' . $e->getMessage()
                ],
                'HTTP_STATUS' => ['codigo' => 500, 'mensaje' => 'Internal Server Error']
            ];
        }
    }

    /**
     * Consulta listado de pacientes activos desde la vista vw_clientes
     */
    private function consultarClientes(?string $busqueda = null): array
    {
        $sql = "SELECT * FROM vw_clientes WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (cedula LIKE :q OR nombre_completo LIKE :q OR correo LIKE :q OR telefono LIKE :q)";
            $params[':q'] = '%' . trim($busqueda) . '%';
        }

        $sql .= " ORDER BY fecha_registro DESC";

        $datos = $this->fetchAll($sql, $params);

        return [
            'response' => [
                'resultado' => 200,
                'mensaje'   => 'OK',
                'datos'     => $datos,
                'total'     => count($datos)
            ],
            'HTTP_STATUS' => ['codigo' => 200, 'mensaje' => 'OK']
        ];
    }

    /**
     * Obtiene el perfil completo de un paciente específico
     */
    private function obtenerCliente(string $cedula): array
    {
        $cedula = trim($cedula);
        $sql = "SELECT * FROM vw_clientes WHERE cedula = :cedula LIMIT 1";
        $cliente = $this->fetchOne($sql, [':cedula' => $cedula]);

        if (!$cliente) {
            return [
                'response' => [
                    'resultado' => 404,
                    'mensaje'   => 'Paciente no encontrado.'
                ],
                'HTTP_STATUS' => ['codigo' => 404, 'mensaje' => 'Not Found']
            ];
        }

        return [
            'response' => [
                'resultado' => 200,
                'mensaje'   => 'OK',
                'datos'     => $cliente
            ],
            'HTTP_STATUS' => ['codigo' => 200, 'mensaje' => 'OK']
        ];
    }

    /**
     * Modifica los datos de un paciente
     */
    private function modificarCliente(array $datos = []): array
    {
        $cedula = trim($datos['cedula'] ?? $this->cedula);
        if (empty($cedula)) {
            return ['response' => ['resultado' => 400, 'mensaje' => 'Cédula no proporcionada.']];
        }

        $pdo = Database::getConnection();
        if ($pdo === null) {
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error de conexión.']];
        }

        try {
            $pdo->beginTransaction();

            $sqlP = "UPDATE persona 
                     SET nombre = :nombre, apellido = :apellido, fecha_nacimiento = :fecha_nac, 
                         telefono = :telefono, correo = :correo, direccion = :direccion, sexo = :sexo 
                     WHERE cedula = :cedula";
            $stmtP = $pdo->prepare($sqlP);
            $stmtP->execute([
                ':cedula'    => $cedula,
                ':nombre'    => $datos['nombre'] ?? $this->nombre,
                ':apellido'  => $datos['apellido'] ?? $this->apellido,
                ':fecha_nac' => $datos['fecha_nacimiento'] ?? $this->fecha_nacimiento,
                ':telefono'  => $datos['telefono'] ?? $this->telefono,
                ':correo'    => $datos['correo'] ?? $this->correo,
                ':direccion' => $datos['direccion'] ?? $this->direccion,
                ':sexo'      => $datos['sexo'] ?? $this->sexo,
            ]);

            $sqlC = "UPDATE cliente 
                     SET grupo_sanguineo = :grupo, alergias = :alergias, antecedentes = :antecedentes, estatus = :estatus 
                     WHERE cedula = :cedula";
            $stmtC = $pdo->prepare($sqlC);
            $stmtC->execute([
                ':cedula'       => $cedula,
                ':grupo'        => $datos['grupo_sanguineo'] ?? $this->grupo_sanguineo,
                ':alergias'     => $datos['alergias'] ?? $this->alergias,
                ':antecedentes' => $datos['antecedentes'] ?? $this->antecedentes,
                ':estatus'      => $this->estatus,
            ]);

            $pdo->commit();

            return [
                'response' => [
                    'resultado' => 200,
                    'mensaje'   => 'Datos del paciente actualizados correctamente.'
                ]
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error: ' . $e->getMessage()]];
        }
    }

    /**
     * Elimina o inactiva un paciente
     */
    private function eliminarCliente(string $cedula): array
    {
        $cedula = trim($cedula);
        $sql = "UPDATE cliente SET estatus = 0 WHERE cedula = :cedula";
        $filas = $this->execute($sql, [':cedula' => $cedula]);

        if ($filas > 0) {
            return [
                'response' => [
                    'resultado' => 200,
                    'mensaje'   => 'Paciente dado de baja/inhabilitado exitosamente.'
                ]
            ];
        }

        return [
            'response' => [
                'resultado' => 404,
                'mensaje'   => 'No se pudo localizar el paciente para eliminar.'
            ]
        ];
    }

    /**
     * Verifica si una cédula ya existe en la base de datos
     */
    private function verificarCedulaExiste(string $cedula): array
    {
        $cedula = trim($cedula);
        $clean = preg_replace('/^[VEJPG]-?/i', '', $cedula);

        $sql = "SELECT COUNT(*) as total FROM persona WHERE cedula = :c_raw OR cedula = :c_clean";
        $row = $this->fetchOne($sql, [
            ':c_raw'   => $cedula,
            ':c_clean' => $clean
        ]);

        $existe = ($row && (int)$row['total'] > 0);

        return [
            'response' => [
                'resultado' => 200,
                'existe'    => $existe,
                'mensaje'   => $existe ? 'Cédula registrada' : 'Cédula disponible'
            ],
            'HTTP_STATUS' => ['codigo' => 200, 'mensaje' => 'OK']
        ];
    }

    /**
     * Consulta todas las atenciones médicas y diagnósticos de morbilidad
     */
    private function consultarTodasMorbilidades(?string $busqueda = null): array
    {
        $sql = "SELECT m.*, CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre, p.sexo, 
                       TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) as edad
                FROM morbilidad m
                JOIN persona p ON m.cedula_cliente = p.cedula
                WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (m.cedula_cliente LIKE :q OR m.diagnostico LIKE :q OR m.codigo_cie10 LIKE :q OR m.medico_tratante LIKE :q OR CONCAT(p.nombre, ' ', p.apellido) LIKE :q)";
            $params[':q'] = '%' . trim($busqueda) . '%';
        }

        $sql .= " ORDER BY m.fecha_atencion DESC";

        $datos = $this->fetchAll($sql, $params);

        return [
            'response' => [
                'resultado' => 200,
                'datos'     => $datos,
                'total'     => count($datos)
            ]
        ];
    }

    /**
     * Registra un caso de morbilidad / consulta médica
     */
    private function registrarMorbilidad(array $datos): array
    {
        $cedula = trim($datos['cedula_cliente'] ?? $this->cedula);
        $motivo = trim($datos['motivo_consulta'] ?? '');
        $diagnostico = trim($datos['diagnostico'] ?? '');
        $cie10 = trim($datos['codigo_cie10'] ?? '');
        $tipo = trim($datos['tipo_morbilidad'] ?? 'Aguda');
        $especialidad = trim($datos['especialidad'] ?? 'Medicina General');
        $medico = trim($datos['medico_tratante'] ?? 'Médico de Guardia');
        $tratamiento = trim($datos['tratamiento'] ?? '');
        $observaciones = trim($datos['observaciones'] ?? '');

        if (empty($cedula) || empty($motivo) || empty($diagnostico)) {
            return [
                'response' => [
                    'resultado' => 400,
                    'mensaje'   => 'Cédula del paciente, motivo y diagnóstico son obligatorios.'
                ]
            ];
        }

        $sql = "INSERT INTO morbilidad (cedula_cliente, motivo_consulta, diagnostico, codigo_cie10, tipo_morbilidad, especialidad, medico_tratante, tratamiento, observaciones)
                VALUES (:cedula, :motivo, :diag, :cie10, :tipo, :esp, :medico, :trat, :obs)";

        $rows = $this->execute($sql, [
            ':cedula' => $cedula,
            ':motivo' => $motivo,
            ':diag'   => $diagnostico,
            ':cie10'  => $cie10,
            ':tipo'   => $tipo,
            ':esp'    => $especialidad,
            ':medico' => $medico,
            ':trat'   => $tratamiento,
            ':obs'    => $observaciones,
        ]);

        if ($rows > 0) {
            return [
                'response' => [
                    'resultado' => 200,
                    'mensaje'   => 'Caso de morbilidad registrado exitosamente.',
                    'id'        => $this->lastInsertId()
                ]
            ];
        }

        return ['response' => ['resultado' => 500, 'mensaje' => 'Error al guardar el registro de morbilidad.']];
    }

    /**
     * Consulta el historial clínico de morbilidad de un paciente
     */
    private function consultarHistorialMorbilidad(string $cedula): array
    {
        $sql = "SELECT * FROM morbilidad WHERE cedula_cliente = :cedula ORDER BY fecha_atencion DESC";
        $historial = $this->fetchAll($sql, [':cedula' => trim($cedula)]);

        return [
            'response' => [
                'resultado' => 200,
                'mensaje'   => 'OK',
                'datos'     => $historial
            ]
        ];
    }

    /**
     * Retorna estadísticas epidemiológicas y de morbilidad para el Dashboard
     */
    private function obtenerEstadisticasMorbilidad(): array
    {
        $totStmt = $this->fetchOne("SELECT COUNT(*) as total FROM morbilidad");
        $totalAtenciones = (int)($totStmt['total'] ?? 0);

        $porTipo = $this->fetchAll("SELECT tipo_morbilidad, COUNT(*) as cantidad FROM morbilidad GROUP BY tipo_morbilidad ORDER BY cantidad DESC");

        $topDiag = $this->fetchAll("SELECT diagnostico, codigo_cie10, COUNT(*) as casos FROM morbilidad GROUP BY diagnostico, codigo_cie10 ORDER BY casos DESC LIMIT 5");

        $recientes = $this->fetchAll("SELECT m.*, CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre 
                                      FROM morbilidad m 
                                      JOIN persona p ON m.cedula_cliente = p.cedula 
                                      ORDER BY m.fecha_atencion DESC LIMIT 8");

        return [
            'response' => [
                'resultado'        => 200,
                'total_atenciones' => $totalAtenciones,
                'por_tipo'         => $porTipo,
                'top_diagnosticos' => $topDiag,
                'recientes'        => $recientes
            ]
        ];
    }
}

// Alias de compatibilidad
if (!class_exists('App\model\Cliente', false)) {
    class_alias(Cliente::class, 'App\model\Cliente');
}
if (!class_exists('app\model\Cliente', false)) {
    class_alias(Cliente::class, 'app\model\Cliente');
}
if (!class_exists('Cliente', false)) {
    class_alias(Cliente::class, 'Cliente');
}
