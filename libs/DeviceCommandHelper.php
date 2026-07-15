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
     * setColor
     *
     * Setzt die Farbe des Geräts basierend auf dem angegebenen Farbmodus.
     *
     * Diese Methode unterstützt verschiedene Farbmodi und konvertiert die Farbe in das entsprechende Format,
     * bevor sie an das Gerät gesendet wird. Unterstützte Modi sind:
     * - **cie**: Konvertiert RGB in den XY-Farbraum (CIE 1931).
     * - **hs**: Verwendet den Hue-Saturation-Modus (HS), um die Farbe zu setzen.
     * - **hsl**: Nutzt den Farbton, Sättigung und Helligkeit (HSL), um die Farbe zu setzen.
     * - **hsv**: Nutzt den Farbton, Sättigung und den Wert (HSV), um die Farbe zu setzen.
     *
     * @param int $color Der Farbwert in Hexadezimal- oder RGB-Format.
     *                   Die Farbe wird intern in verschiedene Farbmodelle umgerechnet.
     * @param string $mode Der Farbmodus, der verwendet werden soll. Unterstützte Werte:
     *                     - 'cie': Konvertiert die RGB-Werte in den XY-Farbraum.
     *                     - 'hs': Verwendet den Hue-Saturation-Modus.
     *                     - 'hsl': Nutzt den HSL-Modus für die Umrechnung.
     *                     - 'hsv': Nutzt den HSV-Modus für die Umrechnung.
     * @param string $Z2MMode Der Zigbee2MQTT-Modus, standardmäßig 'color'. Kann auch 'color_rgb' sein.
     *                        - 'color': Setzt den Farbwert im XY-Farbraum.
     *                        - 'color_rgb': Setzt den Farbwert im RGB-Modus (nur für 'cie' relevant).
     * @param int|null $TransitionTime Optionale Übergangszeit in Sekunden.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException Wenn der Modus ungültig ist.
     *
     * Beispiel:
     * ```php
     * // Setze eine Farbe im HSL-Modus.
     * $this->setColor(0xFF5733, 'hsl', 'color');
     *
     * // Setze eine Farbe im HSV-Modus.
     * $this->setColor(0x4287f5, 'hsv', 'color');
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::IntToRGB()
     * @see \Zigbee2MQTT\ModulBase::RGBToXy()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSB()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSL()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSV()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     */
    private function setColor(int $color, string $mode, string $Z2MMode = 'color', ?int $TransitionTime = null): bool
    {
        $Payload = match ($mode) {
            'cie' => function () use ($color, $Z2MMode)
            {
                $RGB = $this->IntToRGB($color);
                $cie = $this->RGBToXy($RGB);

                if ($Z2MMode === 'color') {
                    // Entferne 'bri' aus dem 'color'-Objekt und füge es separat als 'brightness' hinzu
                    $brightness = $cie['bri'];
                    unset($cie['bri']);
                    return ['color' => $cie, 'brightness' => $brightness];
                } elseif ($Z2MMode === 'color_rgb') {
                    return ['color_rgb' => $cie];
                }
            },
            'hs' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSB = $this->RGBToHSB($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSB Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSB['hue'],
                            'saturation' => $HSB['saturation'],
                        ],
                        'brightness' => $HSB['brightness']
                    ];
                } else {
                    return null;
                }
            },
            'hsl' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSL = $this->RGBToHSL($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSL Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSL['hue'],
                            'saturation' => $HSL['saturation'],
                            'lightness'  => $HSL['lightness']
                        ]
                    ];
                } else {
                    return null;
                }
            },
            'hsv' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSV = $this->RGBToHSV($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSV Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSV['hue'],
                            'saturation' => $HSV['saturation'],
                        ],
                        'brightness' => $HSV['brightness']
                    ];
                } else {
                    return null;
                }
            },
            default => throw new \InvalidArgumentException('Invalid color mode: ' . $mode),
        };

        $result = $Payload();
        if ($result !== null) {

            if ($result === false) {
                return true; // Wert hat sich nicht geändert
            }
            if ($TransitionTime !== null) {
                $result['transition'] = $TransitionTime;
            }
            return $this->SendSetCommand($result);
        }
        return false;
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
