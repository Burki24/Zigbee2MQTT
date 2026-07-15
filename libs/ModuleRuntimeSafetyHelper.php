<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Liefert defensive Laufzeitzugriffe fuer Properties, Attribute und Buffer.
 */
trait ModuleRuntimeSafetyHelper
{
    /**
     * Liest eine boolesche Property mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadPropertyBooleanSafe(string $name, bool $default): bool
    {
        return (bool) $this->ReadPropertySafe(fn (): bool => $this->ReadPropertyBoolean($name), $default);
    }

    /**
     * Liest eine Integer-Property mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadPropertyIntegerSafe(string $name, int $default): int
    {
        return (int) $this->ReadPropertySafe(fn (): int => $this->ReadPropertyInteger($name), $default);
    }

    /**
     * Liest eine Float-Property mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadPropertyFloatSafe(string $name, float $default): float
    {
        return (float) $this->ReadPropertySafe(fn (): float => $this->ReadPropertyFloat($name), $default);
    }

    /**
     * Liest eine String-Property mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadPropertyStringSafe(string $name, string $default): string
    {
        return (string) $this->ReadPropertySafe(fn (): string => $this->ReadPropertyString($name), $default);
    }

    /**
     * Liest ein boolesches Attribut mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadAttributeBooleanSafe(string $name, bool $default): bool
    {
        \set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            return $this->ReadAttributeBoolean($name);
        } catch (\Throwable) {
            return $default;
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * Liefert konservative Defaults, wenn Symcon waehrend eines Modul-Reloads keinen Buffer lesen kann.
     */
    protected function GetDefaultBufferValue(string $name): mixed
    {
        return match ($name) {
            'BUFFER_MQTT_SUSPENDED',
            'BUFFER_PROCESSING_MIGRATION' => true,
            'lastPayload',
            'latestPayload',
            'missingTranslations',
            'brightnessConfig',
            'TransactionData',
            'Multi_TransactionData'       => [],
            default                       => false
        };
    }

    /**
     * Fuehrt Property-Lesezugriffe aus, ohne fehlende neue Properties als Warning weiterzugeben.
     */
    private function ReadPropertySafe(\Closure $reader, bool|int|float|string $default): bool|int|float|string
    {
        \set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            return $reader();
        } catch (\Throwable) {
            return $default;
        } finally {
            \restore_error_handler();
        }
    }

}

