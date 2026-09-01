<?php

namespace App\Enums;

/**
 * Shared by projects and tasks. Priority is never communicated by colour
 * alone in the UI — each level also carries a glyph.
 */
enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    /** Lucide icon name used beside the label. */
    public function icon(): string
    {
        return match ($this) {
            self::Low => 'chevron-down',
            self::Medium => 'minus',
            self::High => 'chevron-up',
            self::Critical => 'triangle-alert',
        };
    }

    /** Sort weight: Critical first. */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::High => 1,
            self::Medium => 2,
            self::Low => 3,
        };
    }
}
