<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Konvertiert und speichert empfangene Zigbee2MQTT-Werte.
 *
 * Der Trait kapselt Typanpassungen, Boolean-Mappings und Spezialvariablen,
 * waehrend ModulBase die Lebenszyklus- und Orchestrierungsaufgaben behaelt.
 */
trait VariableValueProcessingHelper
{
    /**
     * SetValue
     *
     * Setzt den Wert einer Variable unter Berücksichtigung verschiedener Typen und Formatierungen
     *
     * Verarbeitung:
     * 1. Prüft Existenz der Variable, Abbruch wenn Variable nicht vorhanden
     * 2. Konvertiert Wert entsprechend Variablentyp (adjustValueByType)
     * 3. Beruecksichtigt Legacy-Profilzuordnungen vorhandener Variablen
     * 4. Behandelt Spezialfälle (z.B. ColorTemp, Color)
     *
     * Unterstützte Variablentypen:
     * 1. State-Variablen:
     *    - state: ON/OFF -> true/false
     *    - stateL1: Nummerierte States
     *    - stateLeft: Richtungs-States
     *    - stateLeftL1: Kombinierte States
     *
     * 2. Spezielle Variablen:
     *    - color: RGB-Farbwerte oder XY-Farbwerte mit Brightness
     *      Format RGB: Integer (0xRRGGBB)
     *      Format XY: Array ['x' => float, 'y' => float, 'brightness' => int]
     *    - color_temp: Farbtemperatur mit Kelvin-Konvertierung
     *    - preset: Vordefinierte Werte
     *
     * 3. Standard-Variablen:
     *    - Boolean: Automatische ON/OFF Konvertierung
     *    - Integer/Float: Typkonvertierung mit Einheitenbehandlung
     *    - String: Direkte Wertzuweisung
     *
     * @param string $ident Identifier der Variable (z.B. "state", "color_temp", "color")
     * @param mixed $value Zu setzender Wert
     *                    Bool: true/false oder "ON"/"OFF"
     *                    Int/Float: Numerischer Wert
     *                    String: Textwert
     *                    Array: Spezielle Behandlung für Farben und Presets
     *                    Array: Andere Payloads werden nur von expliziten Sonderpfaden verarbeitet
     *
     * @return bool True, wenn der Wert verarbeitet wurde, sonst false.
     *
     * Beispiel:
     * ```php
     * // States
     * $this->SetValue("state", "ON");         // Setzt bool true
     * $this->SetValue("stateL1", false);      // Setzt "OFF"
     *
     * // Farben & Temperatur
     * $this->SetValue("color_temp", 4000);    // Setzt Farbtemp + Kelvin
     * $this->SetValue("color", 0xFF0000);     // Setzt Rot als RGB
     * $this->SetValue("color", [              // Setzt Farbe im XY Format
     *     'x' => 0.7006,
     *     'y' => 0.2993,
     *     'brightness' => 254
     * ]);
     *
     * // Legacy-Profile
     * $this->SetValue("mode", "auto");        // Beruecksichtigt vorhandene Profilassoziationen
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::adjustValueByType()
     * @see \Zigbee2MQTT\ModulBase::convertMiredToKelvin()
     * @see \Zigbee2MQTT\ModulBase::handleColorVariable()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \IPSModule::SetValue()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see IPS_GetVariable()
     * @see IPS_VariableProfileExists()
     * @see IPS_GetVariableProfile()
     */
    protected function SetValue(string $ident, mixed $value): bool
    {
        $variableID = $this->GetObjectIDByIdent($ident);
        if (!$variableID) {
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden: ' . $ident, 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'Verarbeite Variable: ' . $ident . ' mit Wert: ' . json_encode($value), 0);

        // Array Spezialbehandlung für
        if (\is_array($value)) {
            // Color-Arrays
            if (strtolower($ident) === 'color') {
                $this->handleColorVariable($ident, $value);
                return true;
            }
            $this->SendDebug(__FUNCTION__, 'Wert ist ein Array, übersprungen: ' . $ident, 0);
            return false;
        }
        $var = IPS_GetVariable($variableID);
        $varType = $var['VariableType'];
        $adjustedValue = $this->adjustValueByType($var, $value);

        // Legacy-Profilverarbeitung nur für nicht-boolesche Werte
        if ($varType !== 0) {
            $profileName = ($var['VariableCustomProfile'] != '' ? $var['VariableCustomProfile'] : $var['VariableProfile']);
            if ($profileName && IPS_VariableProfileExists($profileName)) {
                $profileAssociations = IPS_GetVariableProfile($profileName)['Associations'];
                foreach ($profileAssociations as $association) {
                    if ($association['Name'] == $value) {
                        $adjustedValue = $association['Value'];
                        $this->SendDebug(__FUNCTION__, 'Profilwert gefunden: ' . $value . ' -> ' . $adjustedValue, 0);
                        $result = $this->SetModuleValue($ident, $variableID, $adjustedValue);
                        $this->UpdateCustomTileValuesIfRelevant($ident);
                        return $result;
                    }
                }
            }
        }

        $this->SendDebug(__FUNCTION__, 'Setze Variable: ' . $ident . ' auf Wert: ' . json_encode($adjustedValue), 0);
        $result = $this->SetModuleValue($ident, $variableID, $adjustedValue);

        // Spezialbehandlung für ColorTemp
        if ($ident === 'color_temp') {
            $kelvinIdent = 'color_temp_kelvin';
            $kelvinValue = $this->convertMiredToKelvin($value);
            $this->SetValueDirect($kelvinIdent, $kelvinValue);
            $this->UpdateColorTemperatureWhiteColorVariable($kelvinValue);
        }
        $this->UpdateCustomTileValuesIfRelevant($ident);
        return $result;
    }

    /**
     * SetValueDirect
     *
     * Setzt den Wert einer Variable direkt ohne weitere Verarbeitung.
     *
     * Diese Methode setzt den Wert einer Variable direkt mit minimaler Verarbeitung:
     * - Keine Profile-Verarbeitung
     * - Keine Spezialbehandlung von States
     * - Basale Konvertierung der Typen für grundlegende Datentypen
     *
     * Verarbeitung:
     * 1. Array-Werte werden zu JSON konvertiert
     * 2. Grundlegende Konvertierung des Typs (bool, int, float, string)
     * 3. Debug-Ausgaben für Fehleranalyse
     *
     * @param string $ident Der Identifikator der Variable, deren Wert gesetzt werden soll
     * @param mixed $value Der zu setzende Wert
     *                    - Array: Wird zu JSON konvertiert
     *                    - Bool: Wird zu bool konvertiert
     *                    - Int/Float: Wird zum entsprechenden Typ konvertiert
     *                    - String: Wird zu string konvertiert
     *
     * @return void
     *
     * Beispiel:
     * ```php
     * // Boolean setzen
     * $this->SetValueDirect("state", true);
     *
     * // Array als JSON
     * $this->SetValueDirect("data", ["temp" => 22]);
     * ```
     *
     * @internal Diese Methode wird hauptsächlich intern verwendet für:
     *          - Direkte Wertzuweisung ohne Profile
     *          - Array zu JSON Konvertierung
     *          - Debug-Werte setzen
     *
     * @see \IPSModule::SetValue()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see IPS_GetVariable()
     */
    protected function SetValueDirect(string $ident, mixed $value): void
    {
        $variableID = $this->GetObjectIDByIdent($ident);

        if (!$variableID) {
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden: ' . $ident, 0);
            return;
        }

        // Typ-Prüfung und Konvertierung
        if (\is_array($value)) {
            $this->SendDebug(__FUNCTION__, 'Array-Wert erkannt, konvertiere zu JSON', 0);
            $value = json_encode($value);
        }

        // Wert entsprechend Variablentyp konvertieren
        $debugVarType = 'unknown';
        switch (IPS_GetVariable($variableID)['VariableType']) {
            case VARIABLETYPE_BOOLEAN:
                $value = $this->adjustBooleanValueByType($ident, $value);
                $debugVarType = 'bool';
                break;
            case VARIABLETYPE_INTEGER:
                $value = (int) $value;
                $debugVarType = 'integer';
                break;
            case VARIABLETYPE_FLOAT:
                $value = (float) $value;
                $debugVarType = 'float';
                break;
            case VARIABLETYPE_STRING:
                $value = (string) $value;
                $debugVarType = 'string';
                break;
        }

        // Setze den Wert der Variable
        $this->SendDebug(__FUNCTION__, \sprintf('Setze Variable: %s, Typ: %s, Wert: %s', $ident, $debugVarType, json_encode($value)), 0);
        $this->SetModuleValue($ident, $variableID, $value);
        $this->UpdateCustomTileValuesIfRelevant($ident);
    }

    /**
     * Behandelt Sonderfaelle, bevor ein Payload-Wert normal geschrieben wird.
     *
     * Dazu gehoeren zum Beispiel Helligkeit in Lichtgruppen und die automatische
     * Spannungskonvertierung von Millivolt nach Volt.
     *
     * @param string $key Originaler Payload-Key.
     * @param mixed $value Payload-Wert, der bei Bedarf angepasst wird.
     * @param string $lowerKey Kleingeschriebener Payload-Key fuer Vergleiche.
     * @param array $variableProps Expose-/Variableninformationen der bekannten Variable.
     *
     * @return bool True, wenn der Sonderfall vollstaendig verarbeitet wurde.
     */
    private function processSpecialCases(string $key, mixed &$value, string $lowerKey, array $variableProps): bool
    {
        // Brightness in Lichtgruppen
        foreach (self::$VariableTypeMappings as $entry) {
            if (
                $entry['feature'] === $lowerKey &&
                isset($entry['group_type'], $variableProps['group_type']) &&
                $entry['group_type'] === 'light' &&
                $variableProps['group_type'] === 'light'
            ) {

                $this->SendDebug(__FUNCTION__, 'Brightness in Lichtgruppe - Variablenmapping', 0);
                return $this->processSpecialVariable($key, $value);
            }
        }

        // Voltage Behandlung
        if ($lowerKey === 'voltage') {
            $this->SendDebug(__FUNCTION__, 'Voltage vor Konvertierung: ' . $value, 0);
            if ($this->processSpecialVariable($key, $value)) {
                return true;
            }
            $value = self::convertMillivoltToVolt($value);
            $this->SendDebug(__FUNCTION__, 'Voltage nach Konvertierung: ' . $value, 0);
        }

        return false;
    }

    /**
     * adjustValueByType
     *
     * Passt den Wert basierend auf dem Variablentyp an.
     * Diese Methode konvertiert den übergebenen Wert in den entsprechenden Typ der Variable.
     *
     * Spezielle Behandlungen:
     * - Bei child_lock: 'LOCK' wird zu true, 'UNLOCK' zu false konvertiert
     * - Boolesche Werte: 'ON' wird zu true, 'OFF' zu false konvertiert
     *
     * @param array $variableObject Ein Array von IPS_GetVariable() mit folgenden Schlüsseln:
     *                             - 'VariableType': int - Der Typ der Variable (0=Bool, 1=Int, 2=Float, 3=String)
     *                             - 'VariableID': int - Die ID der Variable
     * @param mixed $value Der Wert, der angepasst werden soll
     *
     * @return mixed Der konvertierte Wert:
     *               - bool für VARIABLETYPE_BOOLEAN (0)
     *               - int für VARIABLETYPE_INTEGER (1)
     *               - float für VARIABLETYPE_FLOAT (2)
     *               - string für VARIABLETYPE_STRING (3)
     *               - original $value bei unbekanntem Typ
     *
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_bool()
     * @see is_string()
     * @see strtoupper()
     * @see IPS_GetObject()
     * @see VARIABLETYPE_BOOLEAN
     */
    private function adjustValueByType(array $variableObject, mixed $value): mixed
    {
        $varType = $variableObject['VariableType'];
        $varID = $variableObject['VariableID'];
        $ident = IPS_GetObject($varID)['ObjectIdent'];

        $this->SendDebug(__FUNCTION__, 'Variable ID: ' . $varID . ', Typ: ' . $varType . ', Ursprünglicher Wert: ' . json_encode($value), 0);

        switch ($varType) {
            case VARIABLETYPE_BOOLEAN:
                return $this->adjustBooleanValueByType($ident, $value);
            case VARIABLETYPE_INTEGER:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu int: ' . (int) $value, 0);
                return (int) $value;
            case VARIABLETYPE_FLOAT:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu float: ' . (float) $value, 0);
                return (float) $value;
            case VARIABLETYPE_STRING:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu string: ' . (string) $value, 0);
                return (string) $value;
            default:
                $this->SendDebug(__FUNCTION__, 'Unbekannter Variablentyp für ID ' . $varID . ', Wert: ' . json_encode($value), 0);
                return $value;
        }
    }

    /**
     * Konvertiert empfangene Werte passend fuer boolesche IPS-Variablen.
     */
    private function adjustBooleanValueByType(string $ident, mixed $value): bool
    {
        if (\is_bool($value)) {
            $this->SendDebug('adjustValueByType', 'Wert ist bereits bool: ' . json_encode($value), 0);
            return $value;
        }

        if (\is_string($value)) {
            $exposeValue = $this->getBooleanValueFromExpose($ident, $value);
            if ($exposeValue !== null) {
                return $exposeValue;
            }

            $knownValue = $this->getBooleanValueFromKnownString($value);
            if ($knownValue !== null) {
                return $knownValue;
            }

            $this->SendDebug('adjustValueByType', 'Unbekannter boolescher Stringwert für ' . $ident . ': ' . json_encode($value) . ' -> false', 0);
            return false;
        }

        return (bool) $value;
    }

    /**
     * Nutzt value_on/value_off aus dem Expose, wenn vorhanden.
     */
    private function getBooleanValueFromExpose(string $ident, string $value): ?bool
    {
        $feature = $this->findExposeFeatureByProperty($ident);
        if (
            $feature === null ||
            ($feature['type'] ?? '') !== 'binary' ||
            !isset($feature['value_on'], $feature['value_off'])
        ) {
            return null;
        }

        if ($value === $feature['value_on']) {
            return true;
        }
        if ($value === $feature['value_off']) {
            return false;
        }

        return null;
    }

    /**
     * Interpretiert bekannte Zigbee2MQTT-/Symcon-Textwerte als Boolean.
     */
    private function getBooleanValueFromKnownString(string $value): ?bool
    {
        $normalizedValue = strtoupper(trim($value, " \t\n\r\0\x0B\"'"));
        if (\in_array($normalizedValue, ['ON', 'TRUE', 'YES', '1', 'LOCK', 'OPEN'], true)) {
            return true;
        }
        if (\in_array($normalizedValue, ['OFF', 'FALSE', 'NO', '0', 'UNLOCK', 'CLOSE', 'CLOSED'], true)) {
            return false;
        }

        return null;
    }

    /**
     * processSpecialVariable
     *
     * Verarbeitet spezielle Variablen mit besonderen Anforderungen
     *
     * Verarbeitungsschritte:
     * 1. Prüft ob Variable in specialVariables definiert
     * 2. Konvertiert Property zu Ident und Label
     * 3. Registriert Variable falls nicht vorhanden
     * 4. Passt Wert entsprechend Variablentyp an
     * 5. Setzt Wert mit Debug-Ausgaben
     *
     * @param string $key Name der zu verarbeitenden Property
     * @param mixed $value Zu setzender Wert
     *                    Kann sein:
     *                    - String: Direkter Wert
     *                    - Array: Wird konvertiert
     *                    - Bool: Wird angepasst
     *                    - Int/Float: Wird skaliert
     *
     * @return bool True wenn Variable verarbeitet wurde,
     *              False wenn keine Spezialvariable
     *
     * Beispiel:
     * ```php
     * // Verarbeitet Farbtemperatur
     * $this->processSpecialVariable("color_temp", 4000);
     *
     * // Verarbeitet RGB-Farbe
     * $this->processSpecialVariable("color", ["r" => 255, "g" => 0, "b" => 0]);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::processPayload() Ruft diese Methode auf
     * @see \Zigbee2MQTT\ModulBase::processVariable() Ruft diese Methode auf
     *
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::adjustSpecialValue()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see sprintf()
     * @see gettype()
     */
    private function processSpecialVariable(string $key, mixed $value): bool
    {
        if (!isset(self::$specialVariables[$key])) {
            return false;
        }

        if (!$this->ensureSpecialVariable($key)) {
            return true;
        }

        $adjustedValue = $this->adjustSpecialValue($key, $value);
        $this->storeSpecialVariableValue($key, $adjustedValue);
        return true;
    }

    /**
     * Stellt sicher, dass eine Spezialvariable vorhanden ist.
     */
    private function ensureSpecialVariable(string $ident): bool
    {
        return (bool) $this->getOrRegisterVariable($ident, ['property' => $ident], $this->convertLabelToName($ident));
    }

    /**
     * Speichert den angepassten Wert einer Spezialvariable.
     */
    private function storeSpecialVariableValue(string $ident, mixed $adjustedValue): void
    {
        $debugValue = $this->formatPayloadDebugValue($adjustedValue);
        $this->SendDebug('processSpecialVariable' . ' :: ' . __LINE__ . ' :: ', $ident . ' verarbeitet: ' . $ident . ' => ' . $debugValue, 0);

        $this->SetValueDirect($ident, $adjustedValue);
        $this->SendDebug(
            'processSpecialVariable',
            \sprintf('SetValueDirect aufgerufen für %s mit Wert: %s (Typ: %s)', $ident, $debugValue, gettype($adjustedValue)),
            0
        );
        $this->updatePresetVariable($ident, $adjustedValue);
    }

    /**
     * adjustSpecialValue
     *
     * Passt den Wert spezieller Variablen entsprechend ihrer Anforderungen an
     *
     * Verarbeitungsschritte:
     * 1. Debug-Ausgabe des Eingangswerts
     * 2. Spezifische Konvertierung je nach Variablentyp
     * 3. Debug-Ausgabe des konvertierten Werts
     *
     * Unterstützte Variablentypen:
     * - last_seen: Konvertiert Millisekunden zu Sekunden
     * - color_mode: Wandelt Farbmodus in Großbuchstaben (hs->HS, xy->XY)
     * - color_temp_kelvin: Rechnet Kelvin in Mired um (1.000.000/K)
     *
     * @param string $ident Identifikator der Variable (last_seen, color_mode, color_temp_kelvin)
     * @param mixed $value Zu konvertierender Wert
     *                    - LastSeen: Integer (Millisekunden)
     *                    - ColorMode: String (hs, xy)
     *                    - ColorTempKelvin: Integer (2000-6500K)
     *
     * @return mixed Konvertierter Wert
     *               - LastSeen: Integer (Sekunden)
     *               - ColorMode: String (HS, XY)
     *               - ColorTempKelvin: String (Mired)
     *               - Default: Originalwert
     *
     * Beispiel:
     * ```php
     * // LastSeen konvertieren
     * $this->adjustSpecialValue("last_seen", 1600000000000); // Returns: 1600000000
     *
     * // ColorMode konvertieren
     * $this->adjustSpecialValue("color_mode", "hs"); // Returns: "HS"
     *
     * // Kelvin zu Mired
     * $this->adjustSpecialValue("color_temp_kelvin", 4000); // Returns: "250"
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::convertKelvinToMired()
     * @see \Zigbee2MQTT\ModulBase::normalizeValueToRange()
     * @see \Zigbee2MQTT\ModulBase::convertMillivoltToVolt()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see intdiv()
     * @see strtoupper()
     */
    private function adjustSpecialValue(string $ident, mixed $value): mixed
    {
        $debugValue = \is_array($value) ? json_encode($value) : $value;
        $this->SendDebug(__FUNCTION__, 'Processing special variable: ' . $ident . ' with value: ' . $debugValue, 0);
        switch ($ident) {
            case 'last_seen':
                // Umrechnung von Millisekunden auf Sekunden
                // $value nur mit Gleitkommazahlen Division durchführen um 32Bit-Systeme zu unterstützen
                // Anschließend zu INT casten.
                $adjustedValue = (int) ($value / 1000);
                $this->SendDebug(__FUNCTION__, 'Converted value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'color_mode':
                // Konvertierung von 'hs' zu 'HS' und 'xy' zu 'XY'
                $adjustedValue = strtoupper($value);
                $this->SendDebug(__FUNCTION__, 'Converted value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'color_temp_kelvin':
                // Umrechnung von Kelvin zu Mired
                return $this->convertKelvinToMired($value);
            case 'brightness':
                // Konvertiere Gerätewert in Prozentwert (0-100)
                $adjustedValue = $this->normalizeValueToRange($value, false);
                $this->SendDebug(__FUNCTION__, 'Converted brightness value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'voltage':
                // Konvertiere mV zu V
                $adjustedValue = self::convertMillivoltToVolt($value);
                $this->SendDebug(__FUNCTION__, 'Converted voltage value: ' . $adjustedValue, 0);
                return $adjustedValue;
            default:
                return $value;
        }
    }

    /**
     * convertMillivoltToVolt
     *
     * Konvertiert Millivolt in Volt, wenn der Wert größer als 400 ist.
     *
     * @param float $value Der zu konvertierende Wert in Millivolt.
     * @return float Der konvertierte Wert in Volt.
     */
    private static function convertMillivoltToVolt(float $value): float
    {
        if ($value > 400) { // Werte über 400 sind in mV
            return $value * 0.001; // Umrechnung von mV in V mit Faktor 0.001
        }
        return $value; // Werte <= 400 sind bereits in V
    }

    /**
     * convertOnOffValue
     *
     * Konvertiert Werte zwischen ON/OFF und bool.
     * Zentrale Konvertierungsfunktion für State-Handler.
     *
     * @param mixed $value Zu konvertierender Wert:
     *                    - String: "ON"/"OFF" wird zu true/false
     *                    - Bool: true/false wird zu "ON"/"OFF"
     *                    - Andere: Direkte Bool-Konvertierung
     * @param bool $toBool True wenn Konvertierung zu Boolean, False wenn zu ON/OFF String
     *
     * @return mixed Konvertierter Wert:
     *              - Bei toBool=true: Boolean true/false
     *              - Bei toBool=false: String "ON"/"OFF"
     *
     * @see \Zigbee2MQTT\ModulBase::handleStateVariable() Hauptnutzer der Funktion
     * @see \Zigbee2MQTT\ModulBase::processSpecialCases() Weitere Nutzung
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable() Weitere Nutzung
     * @see is_string() Prüft ob Wert ein String ist
     * @see strtoupper() Konvertiert String zu Großbuchstaben
     * @see bool() Boolean Typkonvertierung
     */
    private function convertOnOffValue($value, bool $toBool = true): mixed
    {
        if ($toBool) {
            if (\is_string($value)) {
                return strtoupper($value) === 'ON';
            }
            return (bool) $value;
        } else {
            return $value ? 'ON' : 'OFF';
        }
    }

}
