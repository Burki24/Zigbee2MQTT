<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          BufferHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */

/**
 * Trait welcher Objekt-Eigenschaften in den Instance-Buffer schreiben und lesen kann.
 */
trait BufferHelper
{
    /**
     * Wert einer Eigenschaft aus den InstanceBuffer lesen.
     *
     * @access public
     * @param string $name PropertyName
     * @return mixed Value of Name
     */
    public function __get(string $name): mixed
    {
        if (strpos($name, 'Multi_') === 0) {
            $Lines = '';
            $BufferList = $this->{'BufferListe_' . $name};
            if (!\is_array($BufferList)) {
                return $this->GetBufferFallbackValue($name);
            }
            foreach ($BufferList as $BufferIndex) {
                $Part = $this->{'Part_' . $name . $BufferIndex};
                if (!\is_string($Part)) {
                    return $this->GetBufferFallbackValue($name);
                }
                $Lines .= $Part;
            }
            return $this->UnserializeBufferValue($Lines, $name);
        }
        return $this->UnserializeBufferValue($this->ReadRawBuffer($name), $name);
    }

    /**
     * Wert einer Eigenschaft in den InstanceBuffer schreiben.
     *
     * @access public
     * @param string $name PropertyName
     * @param mixed Value of Name
     */
    public function __set(string $name, mixed $value): void
    {
        $Data = serialize($value);
        if (strpos($name, 'Multi_') === 0) {
            $OldBuffers = $this->{'BufferListe_' . $name};
            if ($OldBuffers == false) {
                $OldBuffers = [];
            }
            $Lines = str_split($Data, 8000);
            foreach ($Lines as $BufferIndex => $BufferLine) {
                $this->{'Part_' . $name . $BufferIndex} = $BufferLine;
            }
            $NewBuffers = array_keys($Lines);
            $this->{'BufferListe_' . $name} = $NewBuffers;
            $DelBuffers = array_diff_key($OldBuffers, $NewBuffers);
            foreach ($DelBuffers as $DelBuffer) {
                $this->{'Part_' . $name . $DelBuffer} = '';
            }
            return;
        }
        $this->SetBuffer($name, $Data);
    }

    /**
     * Liest einen Rohwert aus dem Instance-Buffer, ohne temporaere Symcon-Warnungen fatal werden zu lassen.
     */
    private function ReadRawBuffer(string $name): string|false
    {
        set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            $Data = $this->GetBuffer($name);
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }

        return \is_string($Data) ? $Data : false;
    }

    /**
     * Deserialisiert einen Bufferwert und liefert bei unlesbarem Inhalt einen definierten Fallback.
     */
    private function UnserializeBufferValue(string|false $Data, string $name): mixed
    {
        if ($Data === false || $Data === '') {
            return $this->GetBufferFallbackValue($name);
        }

        $Value = @unserialize($Data);
        if ($Value === false && $Data !== serialize(false)) {
            return $this->GetBufferFallbackValue($name);
        }

        return $Value;
    }

    /**
     * Liefert einen Fallback fuer fehlende Bufferwerte.
     */
    private function GetBufferFallbackValue(string $name): mixed
    {
        if (method_exists($this, 'GetDefaultBufferValue')) {
            return $this->GetDefaultBufferValue($name);
        }

        return false;
    }
}
