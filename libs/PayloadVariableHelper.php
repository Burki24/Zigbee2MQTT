<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Dynamische Registrierung und Aktualisierung von Payload-Variablen.
 */
trait PayloadVariableHelper
{
    /**
     * getOrRegisterVariable
     *
     * Holt oder registriert eine Variable basierend auf dem Identifikator.
     *
     * Diese Methode prüft, ob eine Variable mit dem angegebenen Identifikator existiert. Wenn nicht,
     * wird die Variable registriert und die ID der neu registrierten Variable zurückgegeben.
     *
     * @param string $ident Der Identifikator der Variable.
     * @param array|null $variableProps Die Eigenschaften der Variable, die registriert werden sollen, falls sie nicht existiert.
     * @param string|null $formattedLabel Das formatierte Label der Variable, falls vorhanden.
     *
     * @return ?int Die ID der Variable oder NULL, wenn die Registrierung fehlschlägt.
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::GetIDForIdent()
     * @see debug_backtrace()
     */
    private function getOrRegisterVariable(string $ident, ?array $variableProps = null, ?string $formattedLabel = null): ?int
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return null;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $this->SendDebug(__FUNCTION__, 'Aufruf von getOrRegisterVariable für Ident: ' . $ident . ' von Funktion: ' . $caller, 0);

        $variableID = $this->GetObjectIDByIdent($ident);
        if (!$variableID && $variableProps !== null) {
            if (!$this->CanCreateVariable($ident, $variableProps, 'payload')) {
                return null;
            }
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden, Registrierung: ' . $ident, 0);
            $this->registerVariable($variableProps, $formattedLabel);
            $variableID = $this->GetObjectIDByIdent($ident);
            if (!$variableID) {
                $this->SendDebug(__FUNCTION__, 'Fehler beim Registrieren der Variable: ' . $ident, 0);
                return null;
            }
        }
        $this->SendDebug(__FUNCTION__, 'Variable gefunden: ' . $ident . ' (ID: ' . $variableID . ')', 0);
        return $variableID;
    }

    /**
     * processVariable
     *
     * Verarbeitet eine einzelne Variable mit ihrem Wert.
     *
     * Diese Methode wird aufgerufen, um eine einzelne Variable aus dem empfangenen Payload zu verarbeiten.
     * Sie prüft, ob die Variable bekannt ist, registriert sie gegebenenfalls und setzt den Wert.
     *
     * @param string $key Der Schlüssel im empfangenen Payload.
     * @param mixed $value Der Wert, der mit dem Schlüssel verbunden ist.
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::processSpecialVariable()
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::handleColorVariable()
     * @see \Zigbee2MQTT\ModulBase::convertMillivoltToVolt()
     * @see \Zigbee2MQTT\ModulBase::getKnownVariables()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::GetIDForIdent()
     * @see strtolower()
     * @see is_array()
     * @see strpos()
     */
    private function processVariable(string $key, mixed $value): void
    {
        if ($this->processCompositeKeyVariable($key, $value)) {
            return;
        }

        if ($this->processStructuredArrayVariable($key, $value)) {
            return;
        }

        $ident = $key;

        if ($this->updateExistingPayloadVariable($ident, $value)) {
            return;
        }

        // Bekannte Variablen laden und prüfen
        $lowerKey = strtolower($key);
        $knownVariables = $this->getKnownVariables();
        if (!isset($knownVariables[$lowerKey])) {
            $this->RememberVariableDefinition($ident, ['property' => $ident, 'type' => $this->GetPayloadValueTypeName($value)], 'payload', $value);
            $this->CanCreateVariable($ident, ['property' => $ident, 'type' => $this->GetPayloadValueTypeName($value)], 'payload', $value);
            $this->SendDebug(__FUNCTION__, 'Variable nicht bekannt: ' . $key, 0);
            return;
        }

        $variableProps = $knownVariables[$lowerKey];
        $this->RememberVariableDefinition($ident, $variableProps, 'payload', $value);

        // Array-Werte verarbeiten
        if (\is_array($value)) {
            $this->processArrayValue($ident, $value);
            return;
        }

        // Spezialbehandlungen durchführen
        if ($this->processSpecialCases($key, $value, $lowerKey, $variableProps)) {
            return;
        }

        $this->registerKnownPayloadVariable($ident, $value, $variableProps);
        $this->updatePayloadPresetVariable($ident, $value);
    }

    /**
     * Verarbeitet Composite-Key-Variablen wie color_options__execute_if_off.
     */
    private function processCompositeKeyVariable(string $key, mixed $value): bool
    {
        if (!$this->isCompositeKey($key)) {
            return false;
        }

        $varType = $this->getPayloadVariableTypeDefinition($value, $key);
        if (!$this->GetObjectIDByIdent($key)) {
            if (!$this->CanCreateVariable($key, ['property' => $key, 'type' => $this->GetPayloadValueTypeName($value)], 'payload', $value)) {
                return true;
            }
            $registerFunc = $varType['registerFunc'];
            $this->$registerFunc(
                $key,
                $this->Translate($this->convertLabelToName($key)),
                $varType['presentation']
            );
            $this->MarkVariableCreated($key);
            $this->synchronizeVariableAction($key);
        }

        $this->SetValue($key, $value);
        return true;
    }

    /**
     * Ermittelt Registrierungsdaten fuer dynamisch angelegte Payload-Variablen.
     *
     * Bekannte Feature-Idents werden auch dann fuer den Variablentyp beruecksichtigt,
     * wenn Zigbee2MQTT einen Wert nur im Payload liefert und keine vollstaendigen
     * Expose-Metadaten vorliegen. Eine Profilzuweisung erfolgt hier bewusst nicht.
     */
    private function getPayloadVariableTypeDefinition(mixed $value, string $ident = ''): array
    {
        if ($ident !== '') {
            $payloadType = $this->GetPayloadValueTypeName($value);
            $registerFunc = match ($this->getVariableTypeFromFeature($payloadType, $ident)) {
                'bool'  => 'RegisterVariableBoolean',
                'int'   => 'RegisterVariableInteger',
                'float' => 'RegisterVariableFloat',
                default => 'RegisterVariableString'
            };

            return [
                'presentation' => '',
                'registerFunc' => $registerFunc
            ];
        }

        return match (true) {
            \is_bool($value) => [
                'presentation' => '',
                'registerFunc' => 'RegisterVariableBoolean'
            ],
            \is_int($value) => [
                'presentation' => '',
                'registerFunc' => 'RegisterVariableInteger'
            ],
            \is_float($value) => [
                'presentation' => '',
                'registerFunc' => 'RegisterVariableFloat'
            ],
            default => [
                'presentation' => '',
                'registerFunc' => 'RegisterVariableString'
            ]
        };
    }

    /**
     * Verarbeitet Array-Werte mit besonderer Zigbee2MQTT-Struktur.
     */
    private function processStructuredArrayVariable(string $key, mixed $value): bool
    {
        if (!\is_array($value)) {
            return false;
        }

        if (strpos($key, 'color') === 0) {
            $this->handleColorVariable($key, $value);
            return true;
        }

        if (isset($value['composite'])) {
            foreach ($value['composite'] as $compositeKey => $compositeValue) {
                $this->processVariable($compositeKey, $compositeValue);
            }
            return true;
        }

        if (isset($value['type']) && $value['type'] === 'list') {
            $this->processListPayloadVariable($key, $value);
            return true;
        }

        return false;
    }

    /**
     * Speichert Listen als JSON und verarbeitet deren Items einzeln.
     */
    private function processListPayloadVariable(string $key, array $value): void
    {
        $this->SetValueDirect($key, json_encode($value));
        if (!isset($value['items']) || !\is_array($value['items'])) {
            return;
        }

        foreach ($value['items'] as $index => $item) {
            $this->processVariable($key . '_item_' . $index, $item);
        }
    }

    /**
     * Aktualisiert eine bereits vorhandene Variable aus einem Payload-Wert.
     */
    private function updateExistingPayloadVariable(string $ident, mixed $value): bool
    {
        if ($this->GetObjectIDByIdent($ident) === false) {
            return false;
        }

        $this->SendDebug('processVariable', 'Existierende Variable gefunden: ' . $ident, 0);
        $this->SetValue($ident, $value);
        $this->updatePresetVariable($ident, $value);
        return true;
    }

    /**
     * Registriert eine bekannte Variable bei Bedarf und setzt ihren Wert.
     */
    private function registerKnownPayloadVariable(string $ident, mixed $value, array $variableProps): void
    {
        $variableID = $this->getOrRegisterVariable($ident, $variableProps);
        if (!$variableID) {
            return;
        }

        $this->synchronizeVariableAction($ident, $variableProps);
        $this->SetValue($ident, $value);
    }

    /**
     * Aktualisiert die zugehoerige Preset-Variable, wenn sie vorhanden ist.
     */
    private function updatePayloadPresetVariable(string $ident, mixed $value): void
    {
        $presetIdent = $ident . '_presets';
        if ($this->GetObjectIDByIdent($presetIdent) !== false) {
            $this->SetValue($presetIdent, $value);
        }
    }

    /**
     * Verarbeitet Array-Werte aus dem Payload, die keine eigene Variable abbilden.
     *
     * Farb-Arrays werden an die Farbverarbeitung weitergereicht; andere Array-Werte
     * werden nur ins Debug geschrieben, damit sie nicht ungefiltert serialisiert werden.
     *
     * @param string $ident Ident der Payload-Variable.
     * @param array $value Array-Wert aus dem Zigbee2MQTT-Payload.
     */
    private function processArrayValue(string $ident, array $value): void
    {
        if (strpos($ident, 'color') === 0) {
            $this->handleColorVariable($ident, $value);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'Array-Wert für: ' . $ident, 0);
        $this->SendDebug(__FUNCTION__, 'Inhalt: ' . json_encode($value), 0);
    }

}

