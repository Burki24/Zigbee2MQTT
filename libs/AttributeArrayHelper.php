<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

require_once __DIR__ . '/DataCompressionHelper.php';

/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          AttributeArrayHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */

/**
 * Liest und schreibt Arrays über JSON-kodierte Symcon-Stringattribute.
 */
trait AttributeArrayHelper
{
    /**
     * Registriert ein Array Attribute.
     *
     * @access protected
     * @param string $name Attributname
     * @param array  $Value Standardwert des Attribut
     */
    protected function RegisterAttributeArray(string $name, array $Value): void
    {
        $Data = json_encode($Value);
        $this->RegisterAttributeString($name, $Data);
    }

    /**
     * Liest den Inhalt eines Attribut aus.
     * @param string $name Name des Attribut
     * @return array Inhalt des Attribut
     */
    protected function ReadAttributeArray(string $name): array
    {
        set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            $data = $this->ReadAttributeString($name);
        } catch (\Throwable) {
            return [];
        } finally {
            restore_error_handler();
        }

        if (!\is_string($data) || $data === '') {
            return [];
        }

        $storedData = $data;
        $data = DataCompressionHelper::Decode($storedData);
        if ($data === null) {
            return [];
        }

        $decoded = json_decode($data, true);
        if (\is_array($decoded)) {
            $compressedData = DataCompressionHelper::Encode($data);
            if ($compressedData !== $storedData) {
                $this->WriteAttributeArrayData($name, $compressedData);
            }
        }
        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Schreibt ein Array in das Attribut
     * @param string $name des Attribut
     * @param array $value Array welches in das Attribut geschrieben wird
     */
    protected function WriteAttributeArray(string $name, array $value): void
    {
        $Data = json_encode($value);
        $this->WriteAttributeArrayData(
            $name,
            \is_string($Data) ? DataCompressionHelper::Encode($Data) : '[]'
        );
    }

    /**
     * Schreibt den bereits kodierten Attributwert fehlertolerant.
     */
    private function WriteAttributeArrayData(string $name, string $data): void
    {
        set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            $this->WriteAttributeString($name, $data);
        } catch (\Throwable) {
            return;
        } finally {
            restore_error_handler();
        }
    }
}
