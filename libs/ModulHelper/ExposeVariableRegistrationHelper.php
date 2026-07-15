<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Wertet Zigbee2MQTT-Exposes aus und registriert die zugehoerigen Variablen.
 */
trait ExposeVariableRegistrationHelper
{
    // Feature & Expose Handling

    /**
     * mapExposesToVariables
     *
     * Mappt die übergebenen Exposes auf Variablen und registriert diese.
     * Diese Funktion verarbeitet die übergebenen Exposes (z.B. Sensoreigenschaften) und registriert sie als Variablen.
     * Wenn ein Expose mehrere Features enthält, werden diese ebenfalls einzeln registriert.
     *
     * @param array $exposes Ein Array von Exposes, das die Geräteeigenschaften oder Sensoren beschreibt.
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::getVariableTypeFromFeature()
     * @see \Zigbee2MQTT\ModulBase::registerPresetVariables()
     * @see \IPSModule::SetBuffer()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     */
    protected function mapExposesToVariables(array $exposes): void
    {
        $this->BeginVariableCatalogBatch();
        try {
            $this->mapExposesToVariablesBatch($exposes);
        } finally {
            $this->EndVariableCatalogBatch();
        }
    }

    /**
     * Mappt Exposes innerhalb eines bereits gestarteten Katalog-Batches.
     */
    protected function mapExposesToVariablesBatch(array $exposes): void
    {
        $debugFunction = 'mapExposesToVariables';
        $this->SendDebug($debugFunction . ' :: Line ' . __LINE__ . ' :: All Exposes', json_encode($exposes), 0);

        // Geraetespezifische filtered_attributes aus Z2M laden
        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);

        // Durchlaufe alle Exposes
        foreach ($exposes as $expose) {
            // Prüfen, ob es sich um eine Gruppe handelt
            if (isset($expose['type']) && \in_array($expose['type'], ['light', 'switch', 'lock', 'cover', 'climate', 'fan', 'text'])) {
                $this->SendDebug($debugFunction . ' :: Line ' . __LINE__ . ' :: Found group: ', $expose['type'], 0);

                // Features in der Gruppe verarbeiten
                if (isset($expose['features']) && \is_array($expose['features'])) {
                    foreach ($expose['features'] as $feature) {
                        // Gruppentyp auch im Variablenkatalog erhalten, damit spaetere
                        // ausdrueckliche Benutzeraktionen die passende Darstellung erkennen.
                        $feature['group_type'] = $expose['type'];

                        // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                        $sProperty = $feature['property'] ?? '';
                        if ($sProperty !== '' && !$this->IsExposeCompositeContainer($feature) && !isset($feature['color_mode'])) {
                            $this->RememberVariableDefinition($sProperty, $feature, 'expose');
                        }
                        if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                            $this->SendDebug($debugFunction, 'Skipping filtered attribute: ' . $sProperty, 0);
                            continue;
                        }

                        $this->SendDebug($debugFunction . ' :: Line ' . __LINE__ . ' :: Processing feature in group: ', json_encode($feature), 0);
                        // Variablen für die einzelnen Features registrieren
                        $this->registerVariable($feature);

                        // Wenn es sich um brightness handelt, speichere die Min/Max Werte
                        if ($feature['property'] === 'brightness') {
                            $brightnessConfig = [
                                'min' => $feature['value_min'] ?? 0,
                                'max' => $feature['value_max'] ?? 255
                            ];
                            $this->brightnessConfig = $brightnessConfig;
                            $this->SendDebug($debugFunction, 'Brightness Config: ' . json_encode($brightnessConfig), 0);
                        }
                    }
                } else {
                    $this->registerVariable($expose);
                }
            } else {
                // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                $sProperty = $expose['property'] ?? '';
                if ($sProperty !== '' && !$this->IsExposeCompositeContainer($expose) && !isset($expose['color_mode'])) {
                    $this->RememberVariableDefinition($sProperty, $expose, 'expose');
                }
                if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                    $this->SendDebug($debugFunction, 'Skipping filtered attribute: ' . $sProperty, 0);
                    continue;
                }

                // registerVariable() verarbeitet vorhandene Presets bereits zentral.
                $this->registerVariable($expose);
            }
        }
        $this->RefreshExposeVariableCatalog($exposes);
        $this->UpdateCustomTileVisualizationType();
    }

    /**
     * convertLabelToName
     *
     * Konvertiert ein Label in einen formatierten Namen mit Großbuchstaben am Wortanfang
     * und behält bestimmte Abkürzungen in Großbuchstaben.
     *
     * @param string $label Das zu formatierende Label
     * @return string Das formatierte Label
     *
     * @see \Zigbee2MQTT\ModulBase::isValueInLocaleJson()
     * @see \Zigbee2MQTT\ModulBase::addValueToTranslationsBuffer()
     * @see \IPSModule::SendDebug()
     * @see str_replace()
     * @see str_ireplace()
     * @see strtolower()
     * @see ucwords()
     * @see ucfirst()
     */
    private function convertLabelToName(string $label): string
    {
        // Liste von Abkürzungen die in Großbuchstaben bleiben sollen
        $upperCaseWords = ['HS', 'RGB', 'XY', 'HSV', 'HSL', 'LED'];
        $this->SendDebug(__FUNCTION__, 'Initial Label: ' . $label, 0);

        // Alle Unterstriche (egal ob einfach oder mehrfach) durch ein einzelnes Leerzeichen ersetzen
        $label = preg_replace('/_+/', ' ', $label);
        $this->SendDebug(__FUNCTION__, 'After replacing underscores with spaces: ' . $label, 0);

        // Konvertiere jeden Wortanfang in Großbuchstaben
        $label = ucwords($label);

        // Ersetze bekannte Abkürzungen durch ihre Großbuchstaben-Version
        foreach ($upperCaseWords as $upperWord) {
            $label = str_ireplace(
                [" $upperWord", ' ' . ucfirst(strtolower($upperWord))],
                " $upperWord",
                $label
            );
        }

        $this->SendDebug(__FUNCTION__, 'Converted Label: ' . $label, 0);

        // Prüfe, ob der Name in der locale.json vorhanden ist
        // Füge den Namen zum missingTranslations Buffer hinzu
        $this->isValueInLocaleJson($label, 'label');
        return $label;
    }

    // Variablenmetadaten

    /**
     * Ergaenzt fehlende Expose-Kennungen typunabhaengig.
     *
     * Von Zigbee2MQTT berechnete oder nachgelieferte Werte koennen nur
     * `property` oder nur `name` enthalten. Nach der Normalisierung koennen
     * alle Darstellungs- und Variablenpfade beide Schluessel sicher verwenden.
     *
     * @param array $feature Expose- oder Payload-Definition.
     *
     * @return array Definition mit ergaenzter Property und ergaenztem Namen.
     */
    private static function normalizeExposeFeatureIdentity(array $feature): array
    {
        $property = trim((string) ($feature['property'] ?? ''));
        $name = trim((string) ($feature['name'] ?? ''));
        if ($property === '' && $name !== '') {
            $feature['property'] = $name;
            $property = $name;
        }
        if ($name === '' && $property !== '') {
            $feature['name'] = $property;
        }

        return $feature;
    }

    /**
     * getVariableTypeFromFeature
     *
     * Bestimmt den Variablentyp basierend auf verschiedenen Kriterien.
     *
     * @param string $type Der Expose-Typ (z.B. 'binary', 'numeric', 'enum', 'string', 'text', 'composite')
     * @param string $feature Name der Eigenschaft (z.B. 'state', 'brightness', 'temperature')
     * @param string $unit Optional - Die Einheit des Wertes (z.B. '°C', 'W', '%')
     * @param float $value_step Optional - Die Schrittweite für numerische Werte (Standard: 1.0)
     * @param string|null $groupType Optional - Gruppentyp für spezielle Mappings
     *
     * @return string Der ermittelte Variablentyp ('bool', 'int', 'float', 'string')
     *
     * @note Für 'numeric' Typen gilt folgende Logik:
     *       - Returns 'float' wenn:
     *         * Die Einheit in FLOAT_UNITS definiert ist (z.B. 'W', '°C', 'V')
     *         * value_step keine ganze Zahl ist (z.B. 0.5)
     *       - Returns 'int' wenn:
     *         * Keine der float-Bedingungen zutrifft
     *
     * Beispiel:
     * ```php
     * // Float Beispiel (Temperatur)
     * $type = $this->getVariableTypeFromFeature('numeric', 'temperature', '°C', 0.5);
     * // Ergebnis: 'float'
     *
     * // Integer Beispiel (Helligkeit)
     * $type = $this->getVariableTypeFromFeature('numeric', 'brightness', '%', 1.0);
     * // Ergebnis: 'int'
     * ```
     *
     * @see \IPSModule::SendDebug()
     * @see is_string()
     * @see str_replace()
     * @see in_array()
     * @see fmod()
     */
    private function getVariableTypeFromFeature(string $type, string $feature, string $unit = '', float $value_step = 1.0, ?string $groupType = null): string
    {
        // Prüfen, ob ein spezifisches Mapping existiert.
        // Wichtig: Nicht nur auf den Feature-Namen matchen, da z.B. "position"
        // je nach Gerätetyp numerisch (Cover) oder enum (Kontakt) sein kann.
        foreach (self::$VariableTypeMappings as $entry) {
            if (($entry['feature'] ?? '') !== $feature) {
                continue;
            }

            $typeMatches = !isset($entry['type']) || $entry['type'] === '' || $entry['type'] === $type;
            $groupMatches = !isset($entry['group_type']) || $entry['group_type'] === '' || $entry['group_type'] === $groupType;

            if (!$typeMatches || !$groupMatches) {
                continue;
            }

            $this->SendDebug(__FUNCTION__, 'Found specific mapping: ' . json_encode($entry), 0);

            switch ($entry['variableType']) {
                case VARIABLETYPE_BOOLEAN:
                    return 'bool';
                case VARIABLETYPE_INTEGER:
                    return 'int';
                case VARIABLETYPE_FLOAT:
                    return 'float';
                case VARIABLETYPE_STRING:
                    return 'string';
            }
        }

        // Prüfen, ob die Einheit in den Float-Einheiten enthalten ist
        if (!empty($unit) && \is_string($unit)) {
            // Debug der Original-Einheit
            $this->SendDebug(__FUNCTION__, 'Original unit: ' . bin2hex($unit), 0);

            // Unit kommt aus JSON und ist UTF-8; keine AUTO-Rekonvertierung durchführen.
            $unitTrimmed = str_replace(' ', '', $unit);

            // Erweiterte Debug-Ausgaben
            $this->SendDebug(__FUNCTION__, 'Unit normalized (hex): ' . bin2hex($unitTrimmed), 0);
            $this->SendDebug(__FUNCTION__, 'Unit normalized (readable): ' . $unitTrimmed, 0);
            $this->SendDebug(__FUNCTION__, 'FLOAT_UNITS content: ' . json_encode(self::FLOAT_UNITS), 0);

            if (\in_array($unitTrimmed, self::FLOAT_UNITS, true)) {
                // Wenn unit in FLOAT_UNITS und step eine Ganzzahl ist -> int
                if ($value_step != 1.0 && fmod($value_step, 1) === 0.0) {
                    $this->SendDebug(__FUNCTION__, 'Unit in FLOAT_UNITS but step is integer, returning int', 0);
                    return 'int';
                }
                // Sonst float
                return 'float';
            }
        }

        // Wenn unit nicht in FLOAT_UNITS, aber step eine Dezimalzahl
        if ($value_step != 1.0 && fmod($value_step, 1) !== 0.0) {
            $this->SendDebug(__FUNCTION__, 'Value step is not an integer, returning float', 0);
            return 'float';
        }

        // Allgemeines Typ-Mapping
        $typeMapping = [
            'binary'    => 'bool',
            'numeric'   => 'int',    // Standardmäßig 'numeric' auf 'int' abbilden
            'enum'      => 'string',
            'string'    => 'string',
            'text'      => 'string',
            'composite' => 'composite',
            // Weitere Mapping-Optionen hinzufügen
        ];

        $this->SendDebug(__FUNCTION__, 'Returning type from typeMapping: ' . ($typeMapping[$type] ?? 'string'), 0);
        return $typeMapping[$type] ?? 'string';
    }

    /**
     * checkExposeAttribute
     *
     * @return bool false wenn UpdateDeviceInfo ausgeführt wurde, sonst true
     */
    private function checkExposeAttribute(): bool
    {
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);

        // Erst prüfen ob MQTTTopic gesetzt ist
        if (empty($mqttTopic)) {
            $this->SendDebug(__FUNCTION__, 'MQTTTopic nicht gesetzt, überspringe Attribut Prüfung', 0);
            return true;
        }

        // Prüfe ob Expose-Attribute existiert und Daten enthält
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        if (\count($exposes)) {
            return true;
        }

        $this->SendDebug(__FUNCTION__, 'Expose-Attribute nicht gefunden für Instance: ' . $this->InstanceID, 0);

        if (!$this->HasActiveParent()) {
            $this->SendDebug(__FUNCTION__, 'Parent nicht aktiv, überspringe UpdateDeviceInfo', 0);
            return true;
        }

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return true;
        }

        $this->SendDebug(__FUNCTION__, 'Starte UpdateDeviceInfo für Topic: ' . $mqttTopic, 0);
        if (!$this->UpdateDeviceInfo()) {
            $this->SendDebug(__FUNCTION__, 'UpdateDeviceInfo fehlgeschlagen', 0);
        }
        return false;
    }

    /**
     * getKnownVariables
     *
     * Lädt und verarbeitet bekannte Variablen aus dem Exposes-Attribut.
     *
     * Die Methode extrahiert alle Features aus den gespeicherten Exposes und erstellt daraus ein Array von bekannten Variablen.
     *
     * Der Prozess beinhaltet:
     * - Laden der Exposes aus dem Instanzattribut
     * - Extraktion der Features aus den Exposes
     * - Filterung nach Features mit 'property'-Attribut
     * - Normalisierung der Feature-Namen (Kleinbuchstaben, getrimmt)
     *
     * @internal Diese Methode wird intern vom Modul verwendet
     *
     * @return array Ein assoziatives Array mit bekannten Variablen, wobei der Key der normalisierte Property-Name ist
     *               und der Value die komplette Feature-Definition enthält.
     *               Format: ['property_name' => ['property' => 'name', ...]]
     *               Leeres Array wenn keine Variablen gefunden wurden.
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable() Verwendet die zurückgegebenen Variablen zur Registrierung, über
     * @see \Zigbee2MQTT\ModulBase::processVariable()
     * @see \IPSModule::SendDebug()
     * @see array_map()
     * @see array_merge()
     * @see array_filter()
     * @see trim()
     * @see strtolower()
     */
    private function getKnownVariables(): array
    {
        $data = array_values($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES));
        if (!\count($data)) {
            $this->SendDebug(__FUNCTION__, 'Fehlende exposes oder features.', 0);
            return [];
        }

        $filteredFeatures = [];
        foreach ($data as $expose) {
            if (\is_array($expose)) {
                $this->CollectKnownVariableFeatures($expose, $filteredFeatures);
            }
        }

        $knownVariables = [];
        foreach ($filteredFeatures as $feature) {
            $variableName = trim(strtolower($feature['property']));
            $knownVariables[$variableName] = $feature;
            if ($variableName == 'occupancy') {
                $knownVariables['no_occupancy_since'] = [
                    'property'=> 'no_occupancy_since'
                ];
            }
        }

        $this->SendDebug(__FUNCTION__ . ' Known Variables Array:', json_encode($knownVariables), 0);

        return $knownVariables;
    }

    /**
     * Sammelt bekannte Expose-Features mit demselben Ident, der spaeter fuer Variablen genutzt wird.
     */
    private function CollectKnownVariableFeatures(array $feature, array &$features): void
    {
        if (isset($feature['color_mode'])) {
            return;
        }

        if ($this->IsExposeColorComposite($feature)) {
            $features[] = $feature;
            return;
        }

        if ($this->IsExposeCompositeContainer($feature)) {
            $parentIdent = $this->NormalizeVariableIdent((string) ($feature['property'] ?? ''));
            $parentLabel = (string) ($feature['label'] ?? $this->FormatVariableCatalogLabel($parentIdent));

            foreach ($feature['features'] as $subFeature) {
                if (\is_array($subFeature)) {
                    $this->CollectKnownVariableFeatures(
                        $this->BuildCompositeSubFeature($subFeature, $parentIdent, $parentLabel),
                        $features
                    );
                }
            }

            return;
        }

        if (isset($feature['property'])) {
            if ($feature['property'] === 'icon') {
                $this->SendDebug(__FUNCTION__, 'Icon-Property übersprungen: ' . json_encode($feature), 0);
                return;
            }
            if (strpos($feature['property'], 'Icon') !== false) {
                $this->SendDebug(__FUNCTION__, 'Icon im Namen gefunden - übersprungen: ' . json_encode($feature), 0);
                return;
            }
            $features[] = $feature;
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return;
        }

        foreach ($feature['features'] as $subFeature) {
            if (\is_array($subFeature)) {
                $this->CollectKnownVariableFeatures($subFeature, $features);
            }
        }
    }

    /**
     * registerVariable
     *
     * Registriert eine Variable basierend auf den Feature-Informationen
     *
     * @param array|string $feature Feature-Information als Array oder Feature-ID als String
     *                             Array-Format:
     *                             - 'property': (string) Identifikator der Variable
     *                             - 'type': (string) Datentyp (numeric, binary, enum, etc.)
     *                             - 'unit': (string, optional) Einheit der Variable
     *                             - 'value_step': (float, optional) Schrittweite für numerische Werte
     *                             - 'features': (array, optional) Sub-Features für composite Variablen
     *                             - 'presets': (array, optional) Voreingestellte Werte
     *                             - 'access': (int, optional) Zigbee2MQTT-Zugriffsrechte
     *                               (0b001=STATE, 0b010=SET, 0b100=GET)
     *                             - 'color_mode': (bool, optional) Für Farbvariablen
     * @param string|null $exposeType Optional, überschreibt den Feature-Typ
     *
     * @return void
     *
     * @throws \Exception Bei ungültigen Feature-Informationen
     *
     * Beispiele:
     * ```php
     * // Einfache Variable
     * $this->registerVariable(['property' => 'state', 'type' => 'binary']);
     *
     * // Composite Variable (z.B. weekly_schedule)
     * $this->registerVariable([
     *     'property' => 'weekly_schedule',
     *     'type' => 'composite',
     *     'features' => [
     *         ['property' => 'monday', 'type' => 'string']
     *     ]
     * ]);
     *
     * // Variable mit Presets
     * $this->registerVariable([
     *     'property' => 'mode',
     *     'type' => 'enum',
     *     'presets' => ['auto', 'manual']
     * ]);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::getStateConfiguration()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::registerSpecialVariable()
     * @see \Zigbee2MQTT\ModulBase::getVariableTypeFromFeature()
     * @see \Zigbee2MQTT\ModulBase::registerColorVariable()
     * @see \Zigbee2MQTT\ModulBase::registerPresetVariables()
     * @see \IPSModule::RegisterVariableBoolean()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableString()
     * @see \IPSModule::Translate()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::GetIDForIdent()
     * @see is_array()
     * @see json_encode()
     * @see ucfirst()
     * @see str_replace()
     */
    private function registerVariable(mixed $feature, ?string $exposeType = null): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        if (\is_array($feature)) {
            $feature = self::normalizeExposeFeatureIdentity($feature);
        }
        $featureProperty = \is_array($feature) ? (string) ($feature['property'] ?? '') : (string) $feature;

        // Frühe Validierung der Property
        if (empty($featureProperty)) {
            $this->SendDebug(__FUNCTION__, 'Error: Empty property/identifier provided', 0);
            return;
        }

        $shouldCheckVariableCreation = !\is_array($feature)
            || (!isset($feature['color_mode']) && !$this->IsExposeCompositeContainer($feature));
        if ($shouldCheckVariableCreation) {
            if (!$this->CanCreateVariable($featureProperty, \is_array($feature) ? $feature : null, 'expose')) {
                return;
            }
        }

        $this->SendDebug(__FUNCTION__ . ' Registriere Variable für Property: ', $featureProperty, 0);

        if (\is_array($feature) && $this->registerWriteOnlySingleEnumCommand($feature, $featureProperty)) {
            return;
        }

        if ($this->registerStateFeatureVariable($featureProperty, \is_array($feature) ? $feature : null)) {
            return;
        }

        if (!\is_array($feature)) {
            $this->SendDebug(__FUNCTION__, 'Error: Feature details missing for property: ' . $featureProperty, 0);
            return;
        }

        if ($this->registerSpecialFeatureVariable($feature)) {
            return;
        }

        // Setze den Typ auf den übergebenen Expose-Typ, falls vorhanden
        if ($exposeType !== null) {
            $feature['type'] = $exposeType;
        }

        // Berücksichtige den Gruppentyp, falls vorhanden, ohne den ursprünglichen Typ zu überschreiben
        $groupType = $feature['group_type'] ?? null;

        $this->SendDebug(__FUNCTION__ . ' :: Registering Feature', json_encode($feature), 0);

        $type = $feature['type'];
        $property = $featureProperty; // Bereits validiert
        $unit = $feature['unit'] ?? '';
        $step = isset($feature['value_step']) ? (float) $feature['value_step'] : 1.0;

        // Bestimmen des Variablentyps basierend auf Typ, Feature und Einheit
        $variableType = $this->getVariableTypeFromFeature($type, $property, $unit, $step, $groupType);

        $ident = str_replace('&', '_and_', $property);
        $profileOrPresentation = $this->BuildFeaturePresentation($feature, \is_string($groupType) ? $groupType : null, '') ?? '';
        if (!$this->registerFeatureVariableByType($feature, $ident, $property, $variableType, $profileOrPresentation, $exposeType)) {
            return;
        }

        // Standardaktion der Hauptvariable synchronisieren, ausser bei composite.
        if ($variableType != 'composite') {
            $this->synchronizeVariableAction($ident, $feature);
        }

        $this->registerColorTemperatureKelvinVariable($property, $feature);
        $this->registerFeaturePresetVariables($feature, $property, $type, $unit, $step, $groupType);
        return;
    }

    /**
     * Registriert ein write-only Enum mit genau einem Wert als ausloesbaren Schalter.
     *
     * Solche Exposes liefern keinen Rueckkanal, sollen in Symcon aber trotzdem als
     * Aktion verfuegbar sein.
     *
     * @param array $feature Expose-Feature.
     * @param string $featureProperty Urspruengliche Property.
     *
     * @return bool True, wenn das Feature verarbeitet wurde.
     */
    private function registerWriteOnlySingleEnumCommand(array $feature, string $featureProperty): bool
    {
        if (!$this->isWriteOnlySingleEnumCommand($feature)) {
            return false;
        }

        $ident = str_replace('&', '_and_', $featureProperty);
        $this->RegisterVariableBoolean($ident, $this->Translate($this->convertLabelToName($featureProperty)), '');
        $this->MarkVariableCreated($ident);
        $this->synchronizeVariableAction($ident, $feature, true);
        return true;
    }

    /**
     * Registriert bekannte State-Features mit eigener State-Konfiguration.
     *
     * @param string $featureProperty Feature-Property.
     * @param array|null $feature Optionale Expose-Daten fuer Access-Informationen.
     *
     * @return bool True, wenn eine State-Konfiguration gefunden und verarbeitet wurde.
     */
    private function registerStateFeatureVariable(string $featureProperty, ?array $feature): bool
    {
        $stateConfig = $this->getStateConfiguration($featureProperty, $feature);
        if ($stateConfig === null) {
            return false;
        }

        $formattedLabel = $this->convertLabelToName($featureProperty);
        $profileOrPresentation = $this->BuildStatePresentation($stateConfig, $feature);
        $this->RecordLegacyProfilePresentationReplacement((string) $stateConfig['ident'], $profileOrPresentation);
        switch ($stateConfig['dataType']) {
            case VARIABLETYPE_BOOLEAN:
                $this->RegisterVariableBoolean(
                    $stateConfig['ident'],
                    $this->Translate($formattedLabel),
                    $profileOrPresentation
                );
                $this->MarkVariableCreated($stateConfig['ident']);
                break;
            case VARIABLETYPE_STRING:
                $this->RegisterVariableString(
                    $stateConfig['ident'],
                    $this->Translate($formattedLabel),
                    $profileOrPresentation
                );
                $this->MarkVariableCreated($stateConfig['ident']);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'Unsupported state dataType: ' . $stateConfig['dataType'], 0);
                return true;
        }

        $this->synchronizeStateFeatureAction($stateConfig, $feature);
        return true;
    }

    /**
     * Erstellt fuer State-Enums eine native Darstellung.
     *
     * @param array $stateConfig State-Konfiguration.
     * @param array|null $feature Expose-Daten.
     * @return string|array Leerer String oder native Variablendarstellung.
     */
    private function BuildStatePresentation(array $stateConfig, ?array $feature): string|array
    {
        if (($stateConfig['dataType'] ?? null) !== VARIABLETYPE_STRING || !isset($stateConfig['values']) || !\is_array($stateConfig['values'])) {
            return '';
        }

        $enumFeature = [
            'type'     => 'enum',
            'property' => (string) ($stateConfig['ident'] ?? 'state'),
            'values'   => $stateConfig['values'],
            'access'   => $this->ShouldStateFeatureEnableAction($stateConfig, $feature) ? 2 : 0
        ];

        return $this->BuildEnumerationPresentation($enumFeature) ?? '';
    }

    /**
     * Ermittelt, ob eine State-Variable als Aktion angeboten wird.
     */
    private function ShouldStateFeatureEnableAction(array $stateConfig, ?array $feature): bool
    {
        if (isset($stateConfig['enableAction'])) {
            return (bool) $stateConfig['enableAction'];
        }

        return $feature !== null && isset($feature['access']) && (((int) $feature['access'] & 2) === 2);
    }

    /**
     * Aktiviert Aktionen fuer State-Features entsprechend der State-Konfiguration.
     *
     * Explizite `enableAction`-Angaben haben Vorrang vor den normalen Access-Rechten.
     *
     * @param array $stateConfig State-Konfiguration aus getStateConfiguration().
     * @param array|null $feature Optionale Expose-Daten.
     */
    private function synchronizeStateFeatureAction(array $stateConfig, ?array $feature): void
    {
        if (isset($stateConfig['enableAction'])) {
            if ($stateConfig['enableAction']) {
                $this->synchronizeVariableAction($stateConfig['ident'], $feature, true);
            } else {
                $this->DisableAction($stateConfig['ident']);
                $this->SendDebug(__FUNCTION__, 'Disabled action for ' . $stateConfig['ident'] . ' (explicit state configuration)', 0);
            }
            return;
        }

        $this->synchronizeVariableAction($stateConfig['ident'], $feature);
    }

    /**
     * Registriert eine bekannte Sondervariable, sofern sie nicht gefiltert ist.
     *
     * @param array $feature Expose-Feature.
     *
     * @return bool True, wenn das Feature ein bekannter Sonderfall war.
     */
    private function registerSpecialFeatureVariable(array $feature): bool
    {
        $property = (string) ($feature['property'] ?? '');
        if (!isset(self::$specialVariables[$property])) {
            return false;
        }

        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);
        if (\in_array($property, $aFiltered, true)) {
            $this->SendDebug(__FUNCTION__, 'Skipping filtered special variable: ' . $property, 0);
            return true;
        }

        $this->registerSpecialVariable($feature);
        return true;
    }

    /**
     * Registriert eine Variable anhand des ermittelten Modul-Variablentyps.
     *
     * @param array $feature Expose-Feature.
     * @param string $ident Symcon-Ident.
     * @param string $property Expose-Property.
     * @param string $variableType Interner Variablentyp (`bool`, `int`, `float`, `string`, `text`, `composite`, `list`).
     * @param string|array $profileOrPresentation Zu verwendendes Symcon-Profil oder initiale Darstellung.
     * @param string|null $exposeType Optionaler Expose-Typ fuer rekursive Sub-Features.
     *
     * @return bool True, wenn die normale Nachverarbeitung der Hauptvariable fortgesetzt werden soll.
     */
    private function registerFeatureVariableByType(array $feature, string $ident, string $property, string $variableType, string|array $profileOrPresentation, ?string $exposeType): bool
    {
        $this->RecordLegacyProfilePresentationReplacement($ident, $profileOrPresentation);

        switch ($variableType) {
            case 'bool':
                $this->SendDebug(__FUNCTION__, 'Registering Boolean Variable: ' . $property, 0);
                $this->RegisterVariableBoolean($ident, $this->Translate($this->convertLabelToName($property)), $profileOrPresentation);
                $this->MarkVariableCreated($ident);
                return true;
            case 'int':
                $this->SendDebug(__FUNCTION__, 'Registering Integer Variable: ' . $property, 0);
                $this->RegisterVariableInteger($ident, $this->Translate($this->convertLabelToName($property)), $profileOrPresentation);
                $this->MarkVariableCreated($ident);
                return true;
            case 'float':
                $this->SendDebug(__FUNCTION__, 'Registering Float Variable: ' . $property, 0);
                $this->RegisterVariableFloat($ident, $this->Translate($this->convertLabelToName($property)), $profileOrPresentation);
                $this->MarkVariableCreated($ident);
                return true;
            case 'string':
                $this->SendDebug(__FUNCTION__, 'Registering String Variable: ' . $property, 0);
                $this->RegisterVariableString($ident, $this->Translate($this->convertLabelToName($property)), $profileOrPresentation);
                $this->MarkVariableCreated($ident);
                return true;
            case 'text':
                $this->SendDebug(__FUNCTION__, 'Registering Text Variable: ' . $property, 0);
                $this->RegisterVariableString($ident, $this->Translate($this->convertLabelToName($property)));
                $this->MarkVariableCreated($ident);
                return true;
            case 'composite':
                return !$this->registerCompositeFeatureVariable($feature, $ident, $exposeType);
            case 'list':
                $this->registerListFeatureVariable($feature, $ident, $property);
                return true;
            default:
                $this->SendDebug(__FUNCTION__, 'Unsupported variable type: ' . $variableType, 0);
                return false;
        }
    }

    /**
     * Records that an existing variable changed from a legacy Z2M.* profile to a native presentation.
     *
     * The method only observes the module standard before RegisterVariable* applies the new
     * presentation. It never touches custom user profile or presentation settings.
     *
     * @param string $ident Variable ident.
     * @param string|array $profileOrPresentation New module standard presentation or empty string.
     */
    private function RecordLegacyProfilePresentationReplacement(string $ident, string|array $profileOrPresentation): void
    {
        if (!\is_array($profileOrPresentation)) {
            return;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return;
        }

        try {
            $variable = IPS_GetVariable((int) $variableID);
            $variableName = IPS_GetName((int) $variableID);
        } catch (\Throwable $e) {
            return;
        }

        $oldProfile = \is_string($variable['VariableProfile'] ?? null) ? (string) $variable['VariableProfile'] : '';
        if ($oldProfile === '' || !str_starts_with($oldProfile, 'Z2M.')) {
            return;
        }

        $customProfile = \is_string($variable['VariableCustomProfile'] ?? null) ? (string) $variable['VariableCustomProfile'] : '';
        $customPresentation = $variable['VariableCustomPresentation'] ?? null;
        $hasCustomPresentation = \is_array($customPresentation)
            ? $customPresentation !== []
            : (\is_string($customPresentation) && $customPresentation !== '');

        $log = $this->ReadAttributeArray(self::ATTRIBUTE_PRESENTATION_MIGRATION_LOG);
        $log[(string) $variableID] = [
            'time'            => time(),
            'variableID'      => (int) $variableID,
            'variable'        => $variableName,
            'ident'           => $ident,
            'oldProfile'      => $oldProfile,
            'newPresentation' => $this->DescribePresentationForMigrationLog($profileOrPresentation),
            'customSetting'   => $customProfile !== '' || $hasCustomPresentation,
        ];

        if (\count($log) > 250) {
            $log = array_slice($log, -250, null, true);
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PRESENTATION_MIGRATION_LOG, $log);
        $this->SendDebug(
            'Presentation migration',
            sprintf(
                'Variable #%d %s (%s): %s -> %s',
                (int) $variableID,
                $variableName,
                $ident,
                $oldProfile,
                $log[(string) $variableID]['newPresentation']
            ),
            0
        );
    }

    /**
     * Registriert Composite-Features oder delegiert Farb-Composite-Features.
     *
     * @param array $feature Composite-Expose.
     * @param string $ident Basis-Ident fuer Sub-Features.
     * @param string|null $exposeType Optionaler Expose-Typ fuer rekursive Registrierung.
     *
     * @return bool True, wenn das Composite vollstaendig behandelt wurde und die Hauptmethode abbrechen soll.
     */
    private function registerCompositeFeatureVariable(array $feature, string $ident, ?string $exposeType): bool
    {
        $property = (string) ($feature['property'] ?? '');
        $this->SendDebug(__FUNCTION__, 'Registering Composite Variable: ' . $property, 0);

        if (isset($feature['color_mode']) || $this->IsExposeColorComposite($feature)) {
            $this->registerColorVariable($feature);
            return true;
        }

        if (!isset($feature['features'])) {
            return false;
        }

        $parentLabel = (string) ($feature['label'] ?? $this->FormatVariableCatalogLabel($ident));
        foreach ($feature['features'] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }

            $subFeature = $this->BuildCompositeSubFeature($subFeature, $ident, $parentLabel);
            $this->registerSubFeaturePresetVariables($subFeature);
            $this->registerVariable($subFeature, $exposeType);
        }

        return false;
    }

    /**
     * Registriert Preset-Variablen fuer ein Sub-Feature, sofern vorhanden.
     *
     * @param array $subFeature Sub-Feature aus einem Composite-Expose.
     */
    private function registerSubFeaturePresetVariables(array $subFeature): void
    {
        if (!isset($subFeature['presets'])) {
            return;
        }

        $subPresetIdent = $subFeature['property'] . '_presets';
        $this->RememberVariableDefinition($subPresetIdent, ['property' => $subPresetIdent, 'type' => $subFeature['type'] ?? 'numeric'], 'expose');
        if (!$this->CanCreateVariable($subPresetIdent, ['property' => $subPresetIdent, 'type' => $subFeature['type'] ?? 'numeric'], 'expose')) {
            return;
        }

        $variableType = $this->getVariableTypeFromFeature(
            $subFeature['type'] ?? 'numeric',
            $subFeature['property'],
            $subFeature['unit'] ?? '',
            $subFeature['value_step'] ?? 1.0
        );
        $this->registerPresetVariables(
            $subFeature['presets'],
            $subFeature['property'],
            $variableType,
            $subFeature
        );
    }

    /**
     * Registriert ein List-Feature als JSON-String und optional dessen Item-Typ.
     *
     * @param array $feature List-Expose.
     * @param string $ident Symcon-Ident der Listenvariable.
     * @param string $property Expose-Property.
     */
    private function registerListFeatureVariable(array $feature, string $ident, string $property): void
    {
        if (!$this->CanCreateVariable($ident, $feature, 'expose')) {
            return;
        }

        $this->RegisterVariableString(
            $ident,
            $this->Translate($this->convertLabelToName($property))
        );
        $this->MarkVariableCreated($ident);

        if (isset($feature['item_type'])) {
            $itemFeature = $feature['item_type'];
            $itemFeature['property'] = $ident . '_item';
            $this->registerVariable($itemFeature);
        }
    }

    /**
     * Registriert die Kelvin-Hilfsvariable zur Farbtemperatur.
     *
     * Die Hilfsvariable ist eine moderne Tile-Visu-Ergaenzung zu `color_temp`
     * und erhaelt immer eine Aktion, sofern sie nicht gefiltert ist.
     *
     * @param string $property Expose-Property.
     * @param array $feature Farbtemperatur-Feature.
     */
    private function registerColorTemperatureKelvinVariable(string $property, array $feature): void
    {
        if ($property !== 'color_temp') {
            return;
        }

        $kelvinIdent = $property . '_kelvin';
        $kelvinFeature = ['property' => $kelvinIdent, 'type' => 'numeric', 'label' => 'Color Temperature Kelvin'];
        if (!$this->CanCreateVariable($kelvinIdent, $kelvinFeature, 'derived')) {
            return;
        }

        $profileOrPresentation = $this->BuildColorTemperaturePresentation($feature) ?? '';
        $this->RecordLegacyProfilePresentationReplacement($kelvinIdent, $profileOrPresentation);
        $this->RegisterVariableInteger($kelvinIdent, $this->Translate('Color Temperature Kelvin'), $profileOrPresentation);
        $this->MarkVariableCreated($kelvinIdent);
        $this->synchronizeVariableAction($kelvinIdent, null, true);

        $this->registerColorTemperatureWhiteColorVariable();
    }

    /**
     * Registriert fuer reine Tunable-White-Leuchten eine abgeleitete Farbe.
     */
    private function registerColorTemperatureWhiteColorVariable(): void
    {
        if ($this->HasNativeColorExpose()) {
            return;
        }
        if (!$this->CanCreateVariable('color', ['property' => 'color', 'type' => 'numeric', 'label' => 'Color'], 'derived')) {
            $this->SendDebug(__FUNCTION__, 'Skipping derived tunable-white color variable: color', 0);
            return;
        }

        $colorPresentation = $this->BuildColorPresentation() ?? '';
        $this->RecordLegacyProfilePresentationReplacement('color', $colorPresentation);
        $this->RegisterVariableInteger('color', $this->Translate($this->convertLabelToName('color')), $colorPresentation);
        $this->MarkVariableCreated('color');
    }

    /**
     * Aktualisiert die abgeleitete Weissfarb-Variable, sofern sie fuer Tunable White genutzt wird.
     */
    private function UpdateColorTemperatureWhiteColorVariable(int $kelvin): void
    {
        if ($this->HasNativeColorExpose()) {
            return;
        }
        if ($this->GetObjectIDByIdent('color') === false) {
            return;
        }

        $this->SetValueDirect('color', $this->convertKelvinToWhiteColor($this->ClampColorTemperatureKelvinToConfiguredRange($kelvin)));
    }

    /**
     * Prueft, ob das Geraet eine echte RGB/HS/XY-Farbsteuerung liefert.
     */
    private function HasNativeColorExpose(): bool
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if ($this->FeatureTreeContainsNativeColorExpose($expose)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Durchsucht ein Expose rekursiv nach nativen Farbfeatures.
     */
    private function FeatureTreeContainsNativeColorExpose(array $feature): bool
    {
        $property = strtolower((string) ($feature['property'] ?? ''));
        $name = strtolower((string) ($feature['name'] ?? ''));

        if (
            $property === 'color'
            || \in_array($property, ['color_hs', 'color_rgb', 'color_xy'], true)
            || \in_array($name, ['color_hs', 'color_rgb', 'color_xy'], true)
        ) {
            return true;
        }

        foreach ($feature['features'] ?? [] as $subFeature) {
            if (\is_array($subFeature) && $this->FeatureTreeContainsNativeColorExpose($subFeature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registriert Preset-Variablen fuer das Hauptfeature.
     *
     * @param array $feature Expose-Feature.
     * @param string $property Expose-Property.
     * @param string $type Expose-Typ.
     * @param string $unit Einheit des Werts.
     * @param float $step Schrittweite des Werts.
     * @param string|null $groupType Optionaler Gruppentyp.
     */
    private function registerFeaturePresetVariables(array $feature, string $property, string $type, string $unit, float $step, ?string $groupType): void
    {
        if (!isset($feature['presets']) || empty($feature['presets'])) {
            return;
        }

        $variableType = $this->getVariableTypeFromFeature($type, $property, $unit, $step, $groupType);
        $this->registerPresetVariables($feature['presets'], $feature['property'], $variableType, $feature);
        $this->SendDebug(__FUNCTION__, 'Registered presets for: ' . $feature['property'], 0);
    }

    /**
     * registerColorVariable
     *
     * Registriert Farbvariablen für verschiedene Farbmodelle.
     *
     * Diese Methode erstellt und registriert spezielle Variablen für die Farbsteuerung
     * von Zigbee-Geräten. Unterstützt werden die Farbmodelle:
     * - XY-Farbraum (color_xy)
     * - HSV-Farbraum (color_hs)
     * - RGB-Farbraum (color_rgb)
     *
     * @param array $feature Array mit Eigenschaften des Features:
     *                       - 'name': Name des Farbmodells ('color_xy', 'color_hs', 'color_rgb')
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::Translate()
     * @see debug_backtrace()
     */
    private function registerColorVariable(array $feature): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        $colorPresentation = $this->BuildColorPresentation() ?? '';

        switch ($feature['name']) {
            case 'color_xy':
                if (!$this->CanCreateVariable('color', ['property' => 'color', 'type' => 'composite', 'label' => 'Color'], 'derived')) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color', 0);
                    break;
                }
                $this->RecordLegacyProfilePresentationReplacement('color', $colorPresentation);
                $this->RegisterVariableInteger('color', $this->Translate($this->convertLabelToName('color')), $colorPresentation);
                $this->MarkVariableCreated('color');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->synchronizeVariableAction('color', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_xy', 'color', 0);
                break;
            case 'color_hs':
                if (!$this->CanCreateVariable('color_hs', ['property' => 'color_hs', 'type' => 'composite', 'label' => 'Color HS'], 'derived')) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color_hs', 0);
                    break;
                }
                $this->RecordLegacyProfilePresentationReplacement('color_hs', $colorPresentation);
                $this->RegisterVariableInteger('color_hs', $this->Translate($this->convertLabelToName('color_hs')), $colorPresentation);
                $this->MarkVariableCreated('color_hs');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->synchronizeVariableAction('color_hs', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_hs', 'color_hs', 0);
                break;
            case 'color_rgb':
                if (!$this->CanCreateVariable('color_rgb', ['property' => 'color_rgb', 'type' => 'composite', 'label' => 'Color RGB'], 'derived')) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color_rgb', 0);
                    break;
                }
                $this->RecordLegacyProfilePresentationReplacement('color_rgb', $colorPresentation);
                $this->RegisterVariableInteger('color_rgb', $this->Translate($this->convertLabelToName('color_rgb')), $colorPresentation);
                $this->MarkVariableCreated('color_rgb');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->synchronizeVariableAction('color_rgb', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_rgb', 'color_rgb', 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Unhandled composite type', $feature['name'], 0);
                break;
        }
    }

    /**
     * registerPresetVariables
     *
     * Registriert eine Preset-Variable fuer ein Feature.
     *
     * Diese Funktion erstellt fuer ein Feature eine zusätzliche Preset-Variable mit nativer
     * Aufzaehlungsdarstellung. Es werden keine dynamischen Preset-Profile mehr angelegt.
     * Sie wird verwendet, um vordefinierte Werte (Presets) für bestimmte Eigenschaften eines Geräts
     * zugänglich zu machen.
     *
     * @param array $presets Array mit Preset-Definitionen. Jedes Preset enthält:
     *                       - 'name': Name des Presets (string)
     *                       - 'value': Wert des Presets (mixed)
     * @param string $property Property/Ident der Preset-Hauptvariable.
     * @param string $variableType Typ der Variable ('float' oder 'int')
     * @param array $feature Feature-Definition mit zusätzlichen Eigenschaften wie:
     *                       - 'property': Name der Eigenschaft
     *                       - 'name': Anzeigename
     *                       - 'value_min': Minimaler Wert (optional)
     *                       - 'value_max': Maximaler Wert (optional)
     * @return void
     *
     * Beispiel:
     * ```php
     * $presets = [
     *     ['name' => 'low', 'value' => 20],
     *     ['name' => 'medium', 'value' => 50],
     *     ['name' => 'high', 'value' => 100]
     * ];
     * $this->registerPresetVariables($presets, 'Brightness', 'int', ['property' => 'brightness', 'name' => 'Brightness']);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::BuildPresetPresentation()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::Translate()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::EnableAction()
     */
    private function registerPresetVariables(array $presets, string $property, string $variableType, array $feature): void
    {
        $this->SendDebug(__FUNCTION__, 'Registriere Preset-Variablen für: ' . $property, 0);

        // Hole ident für Preset-Variable
        $presetIdent = $property . '_presets';
        $presetFeature = $this->BuildPresetCatalogFeature($feature, $property, $presets);
        if (!$this->CanCreateVariable($presetIdent, $presetFeature, 'expose')) {
            return;
        }

        // Name formatieren
        $formattedLabel = $this->convertLabelToName($property);

        $presentation = $this->BuildPresetPresentation($presets, $variableType, $feature);
        $profileOrPresentation = $presentation ?? '';
        $this->RecordLegacyProfilePresentationReplacement($presetIdent, $profileOrPresentation);

        // Variable anhand Typ registrieren
        if ($variableType === 'float') {
            $this->RegisterVariableFloat($presetIdent, $this->Translate($formattedLabel . ' Presets'), $profileOrPresentation);
        } else {
            $this->RegisterVariableInteger($presetIdent, $this->Translate($formattedLabel . ' Presets'), $profileOrPresentation);
        }
        $this->MarkVariableCreated($presetIdent);

        // Standardaktion der Preset-Variable synchronisieren.
        $this->synchronizeVariableAction($presetIdent, $feature);
    }

    /**
     * registerSpecialVariable
     *
     * Registriert spezielle Variablen.
     *
     * @param array $feature Feature-Eigenschaften
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::adjustSpecialValue()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::RegisterVariableString()
     * @see \IPSModule::RegisterVariableBoolean()
     * @see \IPSModule::Translate()
     * @see \IPSModule::EnableAction()
     * @see sprintf()
     * @see json_encode()
     */
    private function registerSpecialVariable($feature): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        $ident = $feature['property'];
        $this->SendDebug(__FUNCTION__, \sprintf('Checking special case for %s: %s', $ident, json_encode($feature)), 0);

        if (!isset(self::$specialVariables[$ident])) {
            return;
        }
        if (!$this->CanCreateVariable($ident, $feature, 'special')) {
            return;
        }

        $varDef = self::$specialVariables[$ident];
        $formattedLabel = $this->convertLabelToName($ident);

        // Wert anpassen wenn nötig
        if (isset($feature['value'])) {
            $value = $this->adjustSpecialValue($ident, $feature['value']);
        }

        $profileOrPresentation = '';
        switch ($ident) {
            case 'brightness':
                $profileOrPresentation = $this->BuildBrightnessFeaturePresentation($feature) ?? $profileOrPresentation;
                break;

            case 'update__remaining':
                $profileOrPresentation = $this->BuildDurationPresentation() ?? $profileOrPresentation;
                break;

            case 'last_seen':
                $profileOrPresentation = $this->BuildDateTimePresentation() ?? $profileOrPresentation;
                break;
        }
        $this->RecordLegacyProfilePresentationReplacement($ident, $profileOrPresentation);
        switch ($varDef['type']) {
            case VARIABLETYPE_FLOAT:
                $this->RegisterVariableFloat($ident, $this->Translate($formattedLabel), $profileOrPresentation);
                break;
            case VARIABLETYPE_INTEGER:
                $this->RegisterVariableInteger($ident, $this->Translate($formattedLabel), $profileOrPresentation);
                break;
            case VARIABLETYPE_STRING:
                $this->RegisterVariableString($ident, $this->Translate($formattedLabel), $profileOrPresentation);
                break;
            case VARIABLETYPE_BOOLEAN:
                $this->RegisterVariableBoolean($ident, $this->Translate($formattedLabel), $profileOrPresentation);
                break;
        }
        $this->MarkVariableCreated($ident);

        // Standardaktion der speziellen Variable synchronisieren.
        $this->synchronizeVariableAction($ident, $feature);
        return;
    }

    /**
     * getStateConfiguration
     *
     * Prüft und liefert die Konfiguration für State-basierte Features.
     *
     * Diese Methode analysiert ein Feature und bestimmt, ob es sich um ein State-Feature handelt.
     *
     * Sie prüft drei Szenarien:
     * 1. Vordefinierte States aus stateDefinitions
     * 2. Enum-Typ States (z.B. "state" mit definierten Werten)
     * 3. Standard State-Pattern als Boolean (z.B. "state", "state_left")
     *
     * Bei Enum-States wird eine native Aufzählungsdarstellung verwendet, damit
     * keine dynamischen Z2M.*-Profile angelegt werden muessen.
     *
     * Die zurückgegebene Konfiguration enthält:
     * - type: Typ des States (z.B. 'switch', 'enum')
     * - dataType: IPS Variablentyp (z.B. VARIABLETYPE_BOOLEAN, VARIABLETYPE_STRING)
     * - values: Mögliche Zustände (z.B. ['ON', 'OFF'] oder ['OPEN', 'CLOSE', 'STOP'])
     * - ident: Normalisierter Identifikator
     * - enableAction: Optional - Nur bei explizit definierten States aus stateDefinitions
     *
     * Hinweis: EnableAction wird nur zurückgegeben wenn explizit in stateDefinitions
     * definiert. Ansonsten wird EnableAction zentral in registerVariable() über
     * synchronizeVariableAction() basierend auf Access-Rechten bestimmt.
     *
     * @param string $featureId Feature-Identifikator (z.B. 'state', 'state_left')
     * @param array|null $feature Optionales Feature-Array mit weiteren Eigenschaften:
     *                           - type: Datentyp ('enum', 'binary')
     *                           - values: Array möglicher Enum-Werte
     *                           Hinweis: Access-Rechte werden für EnableAction-Entscheidung
     *                           an registerVariable() weitergegeben
     *
     * @return array|null Array mit State-Konfiguration oder null wenn kein State-Feature
     *
     * Beispiel:
     * ```php
     * // Standard boolean state
     * $config = $this->getStateConfiguration('state');
     * // Ergebnis: ['type' => 'switch', 'dataType' => VARIABLETYPE_BOOLEAN, 'ident' => 'state']
     *
     * // Enum state mit nativer Aufzaehlungsdarstellung
     * $config = $this->getStateConfiguration('state', [
     *     'type' => 'enum',
     *     'values' => ['OPEN', 'CLOSE', 'STOP']
     * ]);
     * // Ergebnis: ['type' => 'enum', 'dataType' => VARIABLETYPE_STRING, 'ident' => 'state']
     *
     * // Vordefinierter state
     * $config = $this->getStateConfiguration('valve_state');
     * // Ergebnis: Konfiguration aus stateDefinitions (inklusive enableAction falls definiert)
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable() Verwendet die Konfiguration und trifft EnableAction-Entscheidung
     * @see \Zigbee2MQTT\ModulBase::synchronizeVariableAction() Zentrale Aktionssynchronisierung
     * @see \IPSModule::SendDebug()
     * @see preg_match()
     */
    private function getStateConfiguration(string $featureId, ?array $feature = null): ?array
    {
        // Basis state-Pattern
        $statePattern = '/^state(?:_[a-z0-9]+)?$/i';

        if (preg_match($statePattern, $featureId)) {
            $this->SendDebug(__FUNCTION__, 'State-Konfiguration für: ' . $featureId, 0);

            // Prüfe ZUERST auf vordefinierte States
            if (isset(static::$stateDefinitions[$featureId])) {
                $stateConfig = static::$stateDefinitions[$featureId];
                $stateConfig['ident'] = $stateConfig['ident'] ?? $featureId;
                return $stateConfig;
            }

            // Dann auf enum type
            if (isset($feature['type']) && $feature['type'] === 'enum' && isset($feature['values'])) {

                // Daten zur Variablenregistrierung zurückgeben
                return [
                    'type'         => 'enum',
                    'dataType'     => VARIABLETYPE_STRING,
                    'values'       => $feature['values'],
                    'ident'        => $featureId
                ];
            }

            // Nur wenn kein enum type und kein vordefinierter state, dann boolean
            return [
                'type'         => 'switch',
                'dataType'     => VARIABLETYPE_BOOLEAN,
                'values'       => ['ON', 'OFF'],
                'ident'        => $featureId
            ];
        }

        // Prüfe auf vordefinierte States wenn kein state pattern matched
        if (isset(static::$stateDefinitions[$featureId])) {
            $stateConfig = static::$stateDefinitions[$featureId];
            $stateConfig['ident'] = $stateConfig['ident'] ?? $featureId;
            return $stateConfig;
        }

        return null;
    }

    /**
     * isCompositeKey
     *
     * Prüft ob ein Key ein Composite Key ist (enthält '__').
     * Zentrale Prüfmethode um Code-Duplikate zu vermeiden.
     *
     * @param string $key Der zu prüfende Key
     *
     * @return bool True wenn Key ein Composite Key ist, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::processVariable() Hauptnutzer
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable() Weiterer Nutzer
     * @see strpos() String Position Prüfung
     */
    private function isCompositeKey(string $key): bool
    {
        return strpos($key, '__') !== false;
    }

    /**
     * Prueft, ob ein Enum-Feature nur als write-only Einzelkommando dient.
     *
     * Diese Exposes besitzen genau einen moeglichen Wert, keinen Lesezugriff und
     * Schreibzugriff. Sie werden als ausloesbare Boolean-Aktion registriert.
     *
     * @param array $feature Expose-Feature.
     *
     * @return bool True fuer write-only Single-Enum-Kommandos.
     */
    private function isWriteOnlySingleEnumCommand(array $feature): bool
    {
        return ($feature['type'] ?? '') === 'enum'
            && isset($feature['values'])
            && \is_array($feature['values'])
            && \count($feature['values']) === 1
            && (($feature['access'] ?? 0) & 0b001) === 0
            && (($feature['access'] ?? 0) & 0b010) !== 0;
    }

    /**
     * Aktualisiert eine zugehörige Preset-Variable, falls vorhanden
     *
     * @param string $ident Identifikator der Hauptvariable
     * @param mixed $value Zu setzender Wert
     * @return void
     */
    private function updatePresetVariable(string $ident, mixed $value): void
    {
        $presetIdent = $ident . '_presets';

        // Prüfe ob die Preset-Variable existiert
        if ($this->GetObjectIDByIdent($presetIdent) !== false) {
            // Variable existiert, also aktualisieren wir direkt ihren Wert
            $this->SetValueDirect($presetIdent, $value);
            $this->SendDebug(__FUNCTION__, "Updated $presetIdent with value: " . (\is_array($value) ? json_encode($value) : $value), 0);
        }
    }

    /**
     * synchronizeVariableAction
     *
     * Synchronisiert die Standardaktion einer Variable mit ihren Schreibrechten.
     *
     * Diese Methode ermittelt den Sollzustand basierend auf:
     * 1. Access-Rechte aus Feature-Array (0b010 Flag für Schreibzugriff)
     * 2. Access-Rechte aus knownVariables
     * 3. Spezielle Variablen (color_temp_kelvin)
     *
     * Ohne Schreibzugriff wird eine eventuell noch vorhandene Standardaktion entfernt.
     *
     * @param string $ident Identifikator der Variable
     * @param array|null $feature Optional: Feature-Array mit Access-Informationen
     * @param bool $forceEnable Optional: Erzwingt EnableAction (für spezielle Variablen)
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::getKnownVariables()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::DisableAction()
     * @see \IPSModule::SendDebug()
     */
    private function synchronizeVariableAction(string $ident, ?array $feature = null, bool $forceEnable = false): void
    {
        // Spezielle Variablen oder erzwungene Aktivierung
        if ($forceEnable) {
            $this->EnableAction($ident);
            $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (forced/special variable)', 0);
            return;
        }

        // Explizite Access-Rechte des aktuellen Features sind verbindlich.
        if (isset($feature['access'])) {
            if (($feature['access'] & 0b010) != 0) {
                $this->EnableAction($ident);
                $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (has write access from feature)', 0);
            } else {
                $this->DisableAction($ident);
                $this->SendDebug(__FUNCTION__, 'Disabled action for ' . $ident . ' (feature has no write access)', 0);
            }
            return;
        }

        // Prüfe Access-Rechte aus knownVariables
        $knownVariables = $this->getKnownVariables();
        if (isset($knownVariables[$ident]['access']) && ($knownVariables[$ident]['access'] & 0b010) != 0) {
            $this->EnableAction($ident);
            $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (has write access from known variables)', 0);
            return;
        }

        // Keine Berechtigung gefunden: eventuell vorhandene Standardaktion entfernen.
        $this->DisableAction($ident);
        $this->SendDebug(__FUNCTION__, 'Disabled action for ' . $ident . ' (no write access)', 0);
    }
}
