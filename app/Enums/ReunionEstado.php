<?php

namespace App\Enums;

enum ReunionEstado: string
{
    case Borrador     = 'borrador';
    case AnteSala     = 'ante_sala';
    case EnCurso      = 'en_curso';
    case Suspendida   = 'suspendida';
    case Finalizada   = 'finalizada';
    case Cancelada    = 'cancelada';
    case Reprogramada = 'reprogramada';

    public function esTerminal(): bool
    {
        return match ($this) {
            self::Finalizada, self::Cancelada, self::Reprogramada => true,
            default => false,
        };
    }

    public function requiereAlerta(): bool
    {
        return $this === self::Cancelada;
    }

    /**
     * @return ReunionEstado[]
     */
    public function transicionesPermitidas(): array
    {
        if ($this->esTerminal()) {
            return [];
        }

        return match ($this) {
            self::Borrador   => [self::AnteSala, self::Cancelada],
            self::AnteSala   => [self::EnCurso, self::Reprogramada, self::Cancelada],
            self::EnCurso    => [self::Finalizada, self::Suspendida, self::Cancelada],
            self::Suspendida => [self::EnCurso, self::Cancelada],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Borrador     => 'Borrador',
            self::AnteSala     => 'Ante Sala',
            self::EnCurso      => 'En Curso',
            self::Suspendida   => 'Suspendida',
            self::Finalizada   => 'Finalizada',
            self::Cancelada    => 'Cancelada',
            self::Reprogramada => 'Reprogramada',
        };
    }
}
