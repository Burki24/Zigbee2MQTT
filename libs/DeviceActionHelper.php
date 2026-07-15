<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Verarbeitet Benutzeraktionen fuer Geraete- und Gruppenvariablen.
 */
trait DeviceActionHelper
{
    /**
     * Leitet eine Aktion an den passenden internen Handler weiter.
     */
    private function handleRequestAction(string $ident, mixed $value): bool
    {
        $maintenanceResult = $this->HandleLocalVariableMaintenanceAction($ident, $value);
        if ($maintenanceResult !== null) {
            return $maintenanceResult;
        }

        $tileResult = $this->handleTileRequestAction($ident, $value);
        if ($tileResult !== null) {
            return $tileResult;
        }

        switch ($ident) {
            case 'UpdateInfo':
                $this->SendDebug(__FUNCTION__, 'Verarbeite UpdateInfo', 0);
                return $this->UpdateDeviceInfo();

            case 'ShowMissingTranslations':
                $this->SendDebug(__FUNCTION__, 'Verarbeite ShowMissingTranslations', 0);
                return $this->ShowMissingTranslations();

            case 'ToggleVariableCreation':
                $this->SendDebug(__FUNCTION__, 'Verarbeite ToggleVariableCreation: ' . (string) $value, 0);
                return $this->ToggleVariableCreation((string) $value);

            case 'RefreshVariableSelection':
                $this->SendDebug(__FUNCTION__, 'Aktualisiere Variablenkatalog aus aktuellen Gerätedaten', 0);
                $this->RefreshVariableSelectionFromForm();
                return true;
        }

        return $this->handleVariableRequestAction($ident, $value);
    }

    /**
     * Leitet HTML-SDK-Kachelaktionen an die jeweilige Kachel-Logik weiter.
     */
    private function handleTileRequestAction(string $ident, mixed $value): ?bool
    {
        return match (true) {
            str_starts_with($ident, 'HeatingTile.')        => $this->HandleHeatingTileAction($ident, $value),
            str_starts_with($ident, 'SensorTile.')         => $this->HandleSensorTileAction($ident, $value),
            str_starts_with($ident, 'SecurityTile.')       => $this->HandleSecurityTileAction($ident, $value),
            str_starts_with($ident, 'WindowHandleTile.')   => $this->HandleWindowHandleTileAction($ident, $value),
            str_starts_with($ident, 'ActionTile.')         => $this->HandleActionTileAction($ident, $value),
            str_starts_with($ident, 'MeteredSwitchTile.')  => $this->HandleMeteredSwitchTileAction($ident, $value),
            default                                        => null
        };
    }

    /**
     * Verarbeitet Variablenaktionen, die als MQTT-Set-Befehl enden.
     */
    private function handleVariableRequestAction(string $ident, mixed $value): bool
    {
        // Presets muessen vor Composite Keys verarbeitet werden.
        if (strpos($ident, 'presets') !== false) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite Preset: ' . $ident, 0);
            return $this->handlePresetVariable($ident, $value);
        }

        if (strpos($ident, '_and_') !== false) {
            $ident = str_replace('_and_', '&', $ident);
            $this->SendDebug(__FUNCTION__, 'recall action: ' . $ident, 0);
            $this->RequestAction($ident, $value);
            return true;
        }

        if (strpos($ident, '__') !== false) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite Composite Key: ' . $ident, 0);
            return $this->SendSetCommand($this->buildNestedPayload($ident, $value));
        }

        if (\in_array($ident, self::$stringVariablesNoResponse, true)) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite String ohne Rückmeldung: ' . $ident, 0);
            return $this->handleStringVariableNoResponse($ident, (string) $value);
        }

        if (\in_array($ident, ['color', 'color_hs', 'color_rgb', 'color_temp_kelvin'], true)) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite Farbvariable: ' . $ident, 0);
            return $this->handleColorVariable($ident, $value);
        }

        if (preg_match(self::STATE_PATTERN['SYMCON'], $ident)) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite Status-Variable: ' . $ident, 0);
            return $this->handleStateVariable($ident, $value);
        }

        $this->SendDebug(__FUNCTION__, 'Verarbeite Standard-Variable: ' . $ident, 0);
        return $this->handleStandardVariable($ident, $value);
    }

    /**
     * handleStandardVariable
     *
     * Verarbeitet Standard-Variablenaktionen und sendet diese an das Zigbee-Gerät.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Standard-Variable angefordert wird.
     * Sie konvertiert den Wert bei Bedarf und sendet den entsprechenden Set-Befehl.
     *
     * Spezielle Wertkonvertierungen:
     * - child_lock: bool true/false wird zu 'LOCK'/'UNLOCK' konvertiert
     * - Boolesche Werte: true/false wird zu 'ON'/'OFF' konvertiert
     * - brightness: Prozentwert (0-100) wird in Gerätewert (0-254) konvertiert
     *
     * @param string $ident Der Identifikator der Standard-Variable (z.B. 'state', 'brightness', 'child_lock')
     * @param mixed $value Der zu setzende Wert:
     *                    - bool für ON/OFF oder LOCK/UNLOCK
     *                    - int für Helligkeitswerte (0-100)
     *                    - mixed für andere Werte
     *
     * @return bool True wenn der Set-Befehl erfolgreich gesendet wurde, False bei Fehlern
     *
     * @example
     * handleStandardVariable('state', true)      // Sendet: {"state": "ON"}
     * handleStandardVariable('child_lock', true) // Sendet: {"child_lock": "LOCK"}
     * handleStandardVariable('brightness', 50)   // Sendet: {"brightness": 127}
     *
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::normalizeValueToRange()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_bool()
     */
    private function handleStandardVariable(string $ident, mixed $value): bool
    {
        $variableID = $this->getOrRegisterVariable($ident);
        if (!$variableID) {
            return false;
        }

        if (\is_bool($value)) {
            $boolResult = $this->handleStandardBooleanVariable($ident, $value);
            if ($boolResult !== null) {
                return $boolResult;
            }
            $value = $this->convertOnOffValue($value, false);
        }

        if ($this->isCompositeKey($ident)) {
            $payload = $this->buildNestedPayload($ident, $value);
            $this->SendDebug(__FUNCTION__, 'Sende composite payload: ' . json_encode($payload), 0);
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $value, $payload, __FUNCTION__);
        }

        if ($ident === 'brightness') {
            return $this->sendBrightnessAction($value);
        }

        if ($ident === 'color_temp' && !$this->HasExposeProperty('color_temp')) {
            $this->SendDebug(__FUNCTION__, 'Skip color_temp action without color_temp expose support', 0);
            return false;
        }

        return $this->sendStandardActionPayload($ident, $value);
    }

    /**
     * Verarbeitet boolesche Standard-Aktionen mit Expose-spezifischem Mapping.
     */
    private function handleStandardBooleanVariable(string $ident, bool $value): ?bool
    {
        $feature = $this->findExposeFeatureByProperty($ident);
        if ($feature === null) {
            return null;
        }

        if ($this->isWriteOnlySingleEnumCommand($feature)) {
            if (!$value) {
                return true;
            }
            return $this->SendSetCommand([$ident => $feature['values'][0]]);
        }

        if (($feature['type'] ?? '') === 'binary' && isset($feature['value_on'], $feature['value_off'])) {
            $payloadValue = $value ? $feature['value_on'] : $feature['value_off'];
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $value, [$ident => $payloadValue], __FUNCTION__);
        }

        return null;
    }

    /**
     * Sucht ein gespeichertes Expose-Feature anhand seiner Property.
     */
    private function findExposeFeatureByProperty(string $property): ?array
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            $feature = $this->findExposeFeatureByPropertyRecursive($expose, $property);
            if ($feature !== null) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * Prueft, ob die aktuelle Zigbee2MQTT-Expose-Liste eine Property anbietet.
     */
    private function HasExposeProperty(string $property): bool
    {
        return $this->findExposeFeatureByProperty($property) !== null;
    }

    /**
     * Prueft, ob Zigbee2MQTT fuer eine Aktion voraussichtlich wieder einen Wert publiziert.
     */
    private function ShouldWaitForZigbee2MQTTFeedback(string $ident): bool
    {
        $feature = $this->findExposeFeatureByProperty($ident);
        if ($feature === null && $this->isCompositeKey($ident)) {
            $parts = explode('__', $ident);
            $childIdent = end($parts);
            if (\is_string($childIdent) && $childIdent !== '') {
                $feature = $this->findExposeFeatureByProperty($childIdent);
            }
        }

        if ($feature === null) {
            return true;
        }

        return (((int) ($feature['access'] ?? 0)) & 0b001) !== 0;
    }

    /**
     * Merkt nur reine Schreib- und Befehlswerte lokal, die keine Rueckmeldung liefern.
     */
    private function UpdateLocalValueAfterSetIfNoFeedback(string $ident, mixed $value, string $context): void
    {
        if ($this->ShouldWaitForZigbee2MQTTFeedback($ident)) {
            $this->SendDebug($context, 'Set-Befehl gesendet; lokaler Wert wird erst nach Zigbee2MQTT-Rueckmeldung aktualisiert.', 0);
            return;
        }

        $this->SendDebug($context, 'Set-Befehl ohne erwartete Rueckmeldung; lokaler Wert wird lokal gemerkt.', 0);
        $this->SetValueDirect($ident, $value);
    }

    /**
     * Sendet ein Set-Payload und merkt Werte ohne Zigbee2MQTT-Rueckmeldung lokal.
     */
    private function SendSetCommandAndUpdateLocalIfNoFeedback(string $ident, mixed $localValue, array $payload, string $context): bool
    {
        if (!$this->SendSetCommand($payload)) {
            return false;
        }

        $this->UpdateLocalValueAfterSetIfNoFeedback($ident, $localValue, $context);
        return true;
    }

    /**
     * Rekursive Suche in gruppierten Exposes.
     */
    private function findExposeFeatureByPropertyRecursive(array $feature, string $property): ?array
    {
        if (($feature['property'] ?? null) === $property) {
            return $feature;
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return null;
        }

        foreach ($feature['features'] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }

            $found = $this->findExposeFeatureByPropertyRecursive($subFeature, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Sendet eine Helligkeitsaktion im Wertebereich des Zigbee2MQTT-Geraets.
     */
    private function sendBrightnessAction(mixed $value): bool
    {
        $payload = ['brightness' => $this->normalizeValueToRange($value, true)];
        return $this->SendSetCommandAndUpdateLocalIfNoFeedback('brightness', $value, $payload, __FUNCTION__);
    }

    /**
     * Sendet ein einfaches Set-Payload fuer Standard-Aktionen.
     */
    private function sendStandardActionPayload(string $ident, mixed $value): bool
    {
        $payload = [$ident => $value];
        $this->SendDebug('handleStandardVariable', 'Sende payload: ' . json_encode($payload), 0);
        return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $value, $payload, 'handleStandardVariable');
    }

    /**
     * handleStateVariable
     *
     * Verarbeitet State-bezogene Aktionen und sendet entsprechende MQTT-Befehle.
     *
     * Diese Methode überprüft verschiedene State-Szenarien:
     * 1. Standard State-Pattern (ON/OFF)
     * 2. Vordefinierte States aus stateDefinitions
     * 3. States aus dem STATE_PATTERN
     *
     * @param string $ident Identifikator der State-Variable
     * @param mixed $value Zu setzender Wert (bool|string|int)
     *
     * @return bool True wenn State erfolgreich verarbeitet wurde, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::convertOnOffValue() Konvertiert Werte zwischen ON/OFF und bool
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand() Sendet MQTT Befehle
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect() Setzt Variablenwert direkt
     * @see \IPSModule::SendDebug() Debug Ausgaben
     * @see \IPSModule::GetValue() Aktuellen Wert abfragen
     * @see preg_match() Pattern Matching für State-Erkennung
     * @see strtoupper() String zu Großbuchstaben
     * @see json_encode() JSON Konvertierung für Debug
     * @see isset() Array Key Prüfung
     */
    private function handleStateVariable(string $ident, mixed $value): bool
    {
        $this->SendDebug(__FUNCTION__, 'State-Handler für: ' . $ident . ' mit Wert: ' . json_encode($value), 0);

        $stateFeature = $this->findExposeFeatureByProperty($ident);
        if ($this->isEnumStateFeature($stateFeature)) {
            $enumStateValue = $this->normalizeEnumStateActionValue($stateFeature, $value);
            if ($enumStateValue === null) {
                $this->SendDebug(__FUNCTION__, 'Unbekannter Enum-State-Wert: ' . json_encode($value), 0);
                return false;
            }

            $payload = [$ident => $enumStateValue];
            $this->SendDebug(__FUNCTION__, 'Enum-State-Payload wird gesendet: ' . json_encode($payload), 0);
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $enumStateValue, $payload, __FUNCTION__);
        }

        // State Pattern Prüfung
        if (preg_match(self::STATE_PATTERN['SYMCON'], $ident)) {
            $stateValue = $this->convertOnOffValue($value, false);
            $payload = [$ident => $stateValue];
            $this->SendDebug(__FUNCTION__, 'State-Payload wird gesendet: ' . json_encode($payload), 0);
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $stateValue, $payload, __FUNCTION__);
        }

        // Prüfe auf vordefinierte States
        if (isset(static::$stateDefinitions[$ident])) {
            $stateInfo = static::$stateDefinitions[$ident];
            if (isset($stateInfo['values'])) {
                $index = \is_bool($value) ? (int) $value : $value;
                if (isset($stateInfo['values'][$index])) {
                    $stateValue = $stateInfo['values'][$index];
                    $payload = [$ident => $stateValue];
                    $this->SendDebug(__FUNCTION__, 'Vordefinierter State-Payload wird gesendet: ' . json_encode($payload), 0);
                    return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $stateValue, $payload, __FUNCTION__);
                }
            }
        }

        // Überprüfen, ob der Wert in STATE_PATTERN definiert ist
        $stringValue = (string) $value;
        if (isset(self::STATE_PATTERN[strtoupper($stringValue)])) {
            $adjustedValue = self::STATE_PATTERN[strtoupper($stringValue)];
            $this->SendDebug(__FUNCTION__, 'State-Wert gefunden: ' . $stringValue . ' -> ' . json_encode($adjustedValue), 0);
            $payload = [$ident => $adjustedValue];
            $this->SendDebug(__FUNCTION__, 'State-Payload wird gesendet: ' . json_encode($payload), 0);
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $adjustedValue, $payload, __FUNCTION__);
        }

        $this->SendDebug(__FUNCTION__, 'Kein passender State-Handler gefunden', 0);
        return false;
    }

    /**
     * Ermittelt den exakten Enum-Wert fuer State-Aktionen wie OPEN/CLOSE/STOP.
     */
    private function normalizeEnumStateActionValue(array $feature, mixed $value): ?string
    {
        $values = array_values(array_map(static fn (mixed $entry): string => (string) $entry, $feature['values']));
        if (\is_int($value) && isset($values[$value])) {
            return $values[$value];
        }

        $stringValue = (string) $value;
        foreach ($values as $enumValue) {
            if ($enumValue === $stringValue) {
                return $enumValue;
            }
        }

        return null;
    }

    /**
     * Prueft, ob ein State-Expose als Enum definiert ist.
     */
    private function isEnumStateFeature(?array $feature): bool
    {
        return $feature !== null
            && ($feature['type'] ?? '') === 'enum'
            && \is_array($feature['values'] ?? null);
    }

    /**
     * handleColorVariable
     *
     * Verarbeitet Farbvariablenaktionen.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Farbvariable angefordert wird.
     * Sie verarbeitet verschiedene Arten von Farbvariablen basierend auf dem Identifikator der Variable.
     * Der Identifikator color kann verschiedene Modi abbilden.
     * Der aktuelle Modus wird aus der Variable color_mode über getColorMode ermittelt.
     *
     * @param string $ident Der Identifikator der Farbvariable.
     * @param mixed $value Der Wert, der mit der Farbvariablen-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück, wenn die Aktion erfolgreich verarbeitet wurde, andernfalls false.
     *
     * @see \Zigbee2MQTT\ColorHelper::getColorMode()
     * @see \Zigbee2MQTT\ColorHelper::xyToInt()
     * @see \Zigbee2MQTT\ColorHelper::convertKelvinToMired()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \Zigbee2MQTT\ModulBase::setColor()
     * @see \IPSModule::GetValue()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_int()
     * @see is_array()
     * @see sprintf()
     */
    private function handleColorVariable(string $ident, mixed $value): bool
    {
        return match ($ident) {
            'color'             => $this->handleMainColorVariable($value),
            'color_hs'          => $this->handleColorSpaceAction($value, 'hs'),
            'color_rgb'         => $this->handleColorSpaceAction($value, 'cie', 'color_rgb'),
            'color_temp_kelvin' => $this->handleColorTemperatureKelvinAction($value),
            'color_temp'        => $this->handleColorTemperatureAction($value),
            default             => false,
        };
    }

    /**
     * Verarbeitet die Haupt-Farbvariable fuer Aktion und Datenempfang.
     */
    private function handleMainColorVariable(mixed $value): bool
    {
        $this->SendDebug('handleColorVariable', 'Color Value: ' . json_encode($value), 0);

        if (\is_int($value)) {
            return $this->handleIntegerColorAction($value);
        }

        if (\is_array($value)) {
            return $this->updateColorVariableFromPayload($value);
        }

        $this->SendDebug('handleColorVariable', 'Ungültiger Wert für color: ' . json_encode($value), 0);
        return false;
    }

    /**
     * Sendet eine Farbe aus der Symcon-Farbauswahl an Zigbee2MQTT.
     */
    private function handleIntegerColorAction(int $value): bool
    {
        if ($this->GetValue('color') === $value) {
            return false;
        }

        return $this->setColor($value, $this->getColorMode());
    }

    /**
     * Aktualisiert die lokale Farbvariable aus einem Zigbee2MQTT-Farbpayload.
     */
    private function updateColorVariableFromPayload(array $value): bool
    {
        $hexValue = $this->getColorIntFromPayload($value);
        if ($hexValue !== null) {
            $this->SetValueDirect('color', $hexValue);
        }

        return true;
    }

    /**
     * Wandelt bekannte Zigbee2MQTT-Farbpayloads in einen Symcon-Integer-Farbwert.
     */
    private function getColorIntFromPayload(array $value): ?int
    {
        $brightness = $value['brightness'] ?? 254;
        $this->SendDebug('handleColorVariable', 'Processing color with brightness: ' . $brightness, 0);

        if (isset($value['color']['x'], $value['color']['y'])) {
            return $this->xyToInt($value['color']['x'], $value['color']['y'], $brightness);
        }

        if (isset($value['x'], $value['y'])) {
            return $this->xyToInt($value['x'], $value['y'], $brightness);
        }

        if (isset($value['hue'], $value['saturation'])) {
            return $this->HSVToInt($value['hue'], $value['saturation'], $brightness);
        }

        return null;
    }

    /**
     * Sendet eine Farbaktion in einem expliziten Farbraum.
     */
    private function handleColorSpaceAction(mixed $value, string $mode, string $z2mMode = 'color'): bool
    {
        $this->SendDebug('handleColorVariable', 'Color mode: ' . $mode . ', Z2M mode: ' . $z2mMode, 0);
        return $this->setColor($value, $mode, $z2mMode);
    }

    /**
     * Sendet eine Kelvin-Farbtemperatur als Zigbee2MQTT-Mired-Wert und aktualisiert die Mired-Variable.
     */
    private function handleColorTemperatureKelvinAction(mixed $value): bool
    {
        if (!$this->HasExposeProperty('color_temp')) {
            $this->SendDebug('handleColorVariable', 'Skip color_temp_kelvin action without color_temp expose support', 0);
            return false;
        }

        $kelvinValue = $this->ClampColorTemperatureKelvinToConfiguredRange((int) $value);
        $convertedValue = $this->convertKelvinToMired($kelvinValue);
        $this->SendDebug('handleColorVariable', \sprintf('Converting %dK to %d Mired', $kelvinValue, $convertedValue), 0);

        if (!$this->SendSetCommand(['color_temp' => $convertedValue])) {
            return false;
        }

        $this->UpdateColorTemperatureLocallyIfNoFeedback($convertedValue, $kelvinValue);
        return true;
    }

    /**
     * Sendet eine Farbtemperaturaktion an Zigbee2MQTT.
     */
    private function handleColorTemperatureAction(mixed $value): bool
    {
        if (!$this->HasExposeProperty('color_temp')) {
            $this->SendDebug('handleColorVariable', 'Skip color_temp action without color_temp expose support', 0);
            return false;
        }

        $convertedValue = $this->convertKelvinToMired($value);
        $this->SendDebug('handleColorVariable', 'Converted Color Temp: ' . $convertedValue, 0);

        if (!$this->SendSetCommand(['color_temp' => $convertedValue])) {
            return false;
        }

        $kelvinValue = $this->convertMiredToKelvin($convertedValue);
        $this->UpdateColorTemperatureLocallyIfNoFeedback($convertedValue, $kelvinValue);

        return true;
    }

    /**
     * Aktualisiert abgeleitete Farbtemperaturwerte nur bei Befehlen ohne Zigbee2MQTT-Rueckmeldung.
     */
    private function UpdateColorTemperatureLocallyIfNoFeedback(int $miredValue, int $kelvinValue): void
    {
        if ($this->ShouldWaitForZigbee2MQTTFeedback('color_temp')) {
            $this->SendDebug('handleColorVariable', 'Farbtemperatur wird erst nach Zigbee2MQTT-Rueckmeldung aktualisiert.', 0);
            return;
        }

        $this->SetValueDirect('color_temp', $miredValue);
        $this->SetValueDirect('color_temp_kelvin', $kelvinValue);
        $this->UpdateColorTemperatureWhiteColorVariable($kelvinValue);
    }

    /**
     * handlePresetVariable
     *
     * Verarbeitet Preset-Variablenaktionen.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Preset-Variable angefordert wird.
     * Sie leitet die Aktion an die Hauptvariable weiter und sendet den entsprechenden Set-Befehl.
     *
     * @param string $ident Der Identifikator der Preset-Variable.
     * @param mixed $value Der Wert, der mit der Preset-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück, wenn die Aktion erfolgreich verarbeitet wurde, andernfalls false.
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \Zigbee2MQTT\ModulBase::convertMiredToKelvin()
     * @see \IPSModule::SendDebug()
     * @see str_replace()
     */
    private function handlePresetVariable(string $ident, mixed $value): bool
    {
        $mainIdent = $this->getPresetMainIdent($ident);
        if ($this->shouldRedirectPresetAction($mainIdent)) {
            $this->SendDebug(__FUNCTION__, 'Preset-Variable wird direkt umgeleitet: ' . $mainIdent, 0);
            return $this->sendPresetAction($ident, $mainIdent, $value);
        }

        $this->SendDebug(__FUNCTION__, 'Aktion über presets erfolgt, Weiterleitung zur eigentlichen Variable: ' . $mainIdent, 0);
        return $this->sendPresetAction($ident, $mainIdent, $value);
    }

    /**
     * Liefert die Hauptvariable zu einer Preset-Variable.
     */
    private function getPresetMainIdent(string $ident): string
    {
        return str_replace('_presets', '', $ident);
    }

    /**
     * Prueft, ob ein Preset laut vordefinierter Konfiguration direkt umgeleitet wird.
     */
    private function shouldRedirectPresetAction(string $mainIdent): bool
    {
        return isset(self::$presetDefinitions[$mainIdent]['redirect']);
    }

    /**
     * Sendet eine Preset-Aktion an die Hauptvariable und aktualisiert beide lokalen Variablen.
     */
    private function sendPresetAction(string $presetIdent, string $mainIdent, mixed $value): bool
    {
        if (!$this->SendSetCommand($this->buildPresetActionPayload($mainIdent, $value))) {
            return false;
        }

        $this->SetValueDirect($presetIdent, $value);
        $this->UpdateLocalValueAfterSetIfNoFeedback($mainIdent, $value, __FUNCTION__);
        return true;
    }

    /**
     * Baut das MQTT-Payload fuer eine Preset-Aktion.
     */
    private function buildPresetActionPayload(string $mainIdent, mixed $value): array
    {
        if ($this->isCompositeKey($mainIdent)) {
            return $this->buildNestedPayload($mainIdent, $value);
        }

        return [$mainIdent => $value];
    }

    /**
     * handleStringVariableNoResponse
     *
     * Verarbeitet String-Variablen, die keine Rückmeldung von Zigbee2MQTT erfordern.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine String-Variable angefordert wird,
     * die keine Rückmeldung von Zigbee2MQTT erfordert. Sie sendet den entsprechenden Set-Befehl
     * und aktualisiert die Variable direkt, wenn der Set-Befehl erfolgreich gesendet wurde.
     *
     * @param string $ident Der Identifikator der String-Variablen.
     * @param string $value Der Wert, der mit der String-Variablen-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück wenn der Set-Befehl abgesetzt wurde, sonder false.
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     */
    private function handleStringVariableNoResponse(string $ident, string $value): bool
    {
        $this->SendDebug(__FUNCTION__, 'Behandlung String ohne Rückmeldung: ' . $ident, 0);
        $payload = [$ident => $value];
        if ($this->SendSetCommand($payload)) {
            $this->SetValue($ident, $value);
            return true;
        }
        return false;
    }
}

