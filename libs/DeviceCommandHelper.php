<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Oeffentliche Lese- und Schreibbefehle fuer Geraete- und Gruppeninstanzen.
 */
trait DeviceCommandHelper
{
    /**
     * Sendet eine boolesche Aktion auch bei ueberschriebener Standardaktion.
     */
    public function WriteValueBoolean(string $ident, bool $value): bool
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Sendet eine Integer-Aktion auch bei ueberschriebener Standardaktion.
     */
    public function WriteValueInteger(string $ident, int $value): bool
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Sendet eine Float-Aktion auch bei ueberschriebener Standardaktion.
     */
    public function WriteValueFloat(string $ident, float $value): bool
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Sendet eine String-Aktion auch bei ueberschriebener Standardaktion.
     */
    public function WriteValueString(string $ident, string $value): bool
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Sendet eine Leseanforderung fuer ein einzelnes Property.
     */
    public function ReadValue(string $Property): mixed
    {
        $Payload = [$Property => ''];
        $Topic = $this->BuildConfiguredMQTTTopic(self::MQTT_TOPIC, 'get');
        if ($Topic === null) {
            return false;
        }

        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * Sendet einen generischen Set-Befehl an das Geraet.
     */
    public function SendSetCommand(array $Payload): bool
    {
        $Topic = $this->BuildConfiguredMQTTTopic(self::MQTT_TOPIC, 'set');
        if ($Topic === null) {
            return false;
        }

        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * Fragt alle laut Exposes lesbaren und nicht gefilterten Properties ab.
     */
    public function SendGetCommand(): bool
    {
        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);
        $Payload = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if (\is_array($expose)) {
                $this->CollectGettableExposeProperties($expose, $aFiltered, $Payload);
            }
        }
        if ($Payload === []) {
            $this->SendDebug(__FUNCTION__, 'No properties with GET access available', 0);
            return false;
        }

        $Topic = $this->BuildConfiguredMQTTTopic(self::MQTT_TOPIC, 'get');
        if ($Topic === null) {
            return false;
        }

        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * Setzt eine Farbe mit Transition, sofern ein natives Farb-Expose vorhanden ist.
     */
    public function SetColorExt(int $color, int $TransitionTime): bool
    {
        if (!$this->HasNativeColorExpose()) {
            $this->SendDebug(__FUNCTION__, 'Skip color transition action without native color expose support', 0);
            return false;
        }

        return $this->setColor($color, $this->getColorMode(), 'color', $TransitionTime);
    }

    /**
     * Sammelt MQTT-Properties, deren Expose das Zigbee2MQTT-GET-Bit besitzt.
     */
    private function CollectGettableExposeProperties(array $expose, array $filtered, array &$payload): void
    {
        $property = isset($expose['property']) && \is_string($expose['property'])
            ? trim($expose['property'])
            : '';
        $isComposite = ($expose['type'] ?? '') === 'composite';

        if ($property !== '') {
            if ((((int) ($expose['access'] ?? 0)) & 0b100) !== 0) {
                if (\in_array($property, $filtered, true)) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $property, 0);
                } else {
                    $payload[$property] = '';
                }
            }

            if ($isComposite) {
                return;
            }
        }

        if (!isset($expose['features']) || !\is_array($expose['features'])) {
            return;
        }

        foreach ($expose['features'] as $feature) {
            if (\is_array($feature)) {
                $this->CollectGettableExposeProperties($feature, $filtered, $payload);
            }
        }
    }
}
