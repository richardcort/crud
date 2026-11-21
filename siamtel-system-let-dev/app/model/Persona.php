<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Exception;

abstract class Persona extends Model
{
    protected string $cedula = '';
    protected string $nombre = '';
    protected string $apellido = '';
    protected ?string $fecha_nacimiento = null;
    protected ?string $telefono = null;
    protected ?string $correo = null;
    protected ?string $direccion = null;
    protected string $sexo = 'M';

    public function setCedula(string $cedula): void
    {
        $cedula = strtoupper(trim($cedula));
        if (!preg_match('/^[VEJPG]-\d{6,9}$/i', $cedula)) {
            // Si viene sólo dígitos, asumimos prefijo V-
            if (preg_match('/^\d{6,9}$/', $cedula)) {
                $cedula = 'V-' . $cedula;
            } else {
                throw new Exception('La cédula debe tener un prefijo válido (V, E, J, P, G) y entre 6 y 9 dígitos.');
            }
        }
        $this->cedula = $cedula;
    }

    public function getCedula(): string
    {
        return $this->cedula;
    }

    public function setNombre(string $nombre): void
    {
        $nombre = trim($nombre);
        if (mb_strlen($nombre) < 2) {
            throw new Exception('El nombre debe tener al menos 2 caracteres.');
        }
        $this->nombre = $nombre;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setApellido(string $apellido): void
    {
        $apellido = trim($apellido);
        if (mb_strlen($apellido) < 2) {
            throw new Exception('El apellido debe tener al menos 2 caracteres.');
        }
        $this->apellido = $apellido;
    }

    public function getApellido(): string
    {
        return $this->apellido;
    }

    public function setFechaNacimiento(?string $fecha): void
    {
        if ($fecha !== null && $fecha !== '') {
            $fecha = trim($fecha);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                throw new Exception('Formato de fecha de nacimiento inválido (debe ser YYYY-MM-DD).');
            }
            if ($fecha > date('Y-m-d')) {
                throw new Exception('La fecha de nacimiento no puede ser futura.');
            }
            $this->fecha_nacimiento = $fecha;
        } else {
            $this->fecha_nacimiento = null;
        }
    }

    public function getFechaNacimiento(): ?string
    {
        return $this->fecha_nacimiento;
    }

    public function setTelefono(?string $telefono): void
    {
        if ($telefono !== null && trim($telefono) !== '') {
            $this->telefono = trim($telefono);
        } else {
            $this->telefono = null;
        }
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setCorreo(?string $correo): void
    {
        if ($correo !== null && trim($correo) !== '') {
            $correo = trim($correo);
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El formato de correo electrónico es inválido.');
            }
            $this->correo = strtolower($correo);
        } else {
            $this->correo = null;
        }
    }

    public function getCorreo(): ?string
    {
        return $this->correo;
    }

    public function setDireccion(?string $direccion): void
    {
        $this->direccion = ($direccion !== null && trim($direccion) !== '') ? trim($direccion) : null;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setSexo(string $sexo): void
    {
        $sexo = strtoupper(trim($sexo));
        if (!in_array($sexo, ['M', 'F'], true)) {
            $sexo = 'M';
        }
        $this->sexo = $sexo;
    }

    public function getSexo(): string
    {
        return $this->sexo;
    }
}

