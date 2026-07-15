<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer die lokale Verwaltung automatisch anlegbarer Variablen.
 *
 * Der Katalog bleibt bewusst getrennt von Zigbee2MQTT `filtered_attributes`,
 * damit Anwenderentscheidungen keine Z2M-Konfiguration veraendern.
 */
trait VariableCatalogHelper
{
    /** @var array<string,array>|null Im aktuellen Batch bearbeiteter Variablenkatalog. */
    private ?array $variableCatalogBatch = null;

    /** @var array<string,array>|null Stand des Variablenkatalogs beim Start des aeussersten Batches. */
    private ?array $variableCatalogBatchOriginal = null;

    /** Verschachtelungstiefe der laufenden Katalogverarbeitung. */
    private int $variableCatalogBatchDepth = 0;

    /** Merkt, ob der Katalog innerhalb des laufenden Batches veraendert wurde. */
    private bool $variableCatalogBatchDirty = false;

    /**
     * Startet eine verschachtelbare Katalogverarbeitung.
     *
     * Der aeusserste Aufruf liest das Attribut genau einmal. Alle inneren Aufrufe
     * arbeiten anschliessend auf demselben In-Memory-Stand.
     */
    protected function BeginVariableCatalogBatch(): void
    {
        if ($this->variableCatalogBatchDepth === 0) {
            $catalog = $this->ReadVariableCatalog();
            $this->variableCatalogBatch = $catalog;
            $this->variableCatalogBatchOriginal = $catalog;
            $this->variableCatalogBatchDirty = false;
        }

        ++$this->variableCatalogBatchDepth;
    }

    /**
     * Beendet eine Katalogverarbeitung und schreibt nur den final geaenderten Stand.
     */
    protected function EndVariableCatalogBatch(): void
    {
        if ($this->variableCatalogBatchDepth === 0) {
            return;
        }

        --$this->variableCatalogBatchDepth;
        if ($this->variableCatalogBatchDepth > 0) {
            return;
        }

        $catalog = $this->variableCatalogBatch ?? [];
        $originalCatalog = $this->variableCatalogBatchOriginal ?? [];
        $mustWrite = $this->variableCatalogBatchDirty && $catalog !== $originalCatalog;

        // Zustand vor dem Attributzugriff zuruecksetzen, damit auch ein Fehler beim
        // Schreiben keinen vermeintlich weiterlaufenden Batch hinterlaesst.
        $this->variableCatalogBatch = null;
        $this->variableCatalogBatchOriginal = null;
        $this->variableCatalogBatchDirty = false;

        if ($mustWrite) {
            $this->WriteAttributeArray(self::ATTRIBUTE_VARIABLE_CATALOG, $catalog);
        }
    }

    /**
     * Liest innerhalb eines Batches den aktuellen In-Memory-Stand.
     *
     * @return array<string,array>
     */
    protected function ReadVariableCatalog(): array
    {
        return $this->variableCatalogBatch
            ?? $this->ReadAttributeArray(self::ATTRIBUTE_VARIABLE_CATALOG);
    }

    /**
     * Aktualisiert innerhalb eines Batches nur den In-Memory-Stand.
     *
     * @param array<string,array> $catalog
     */
    protected function WriteVariableCatalog(array $catalog): void
    {
        if ($this->variableCatalogBatch !== null) {
            if ($catalog !== $this->variableCatalogBatch) {
                $this->variableCatalogBatch = $catalog;
                $this->variableCatalogBatchDirty = true;
            }
            return;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_VARIABLE_CATALOG, $catalog);
    }

    /**
     * Aktiviert oder deaktiviert die automatische Anlage einer Variable.
     */
    protected function SetVariableCreationEnabled(string $ident, bool $enabled): bool
    {
        $ident = $this->NormalizeVariableIdent($ident);
        if ($ident === '') {
            return false;
        }

        if ($enabled) {
            $this->RemoveVariableFromAttributeList(self::ATTRIBUTE_DISABLED_VARIABLES, $ident);
            $this->RemoveVariableFromAttributeList(self::ATTRIBUTE_DELETED_VARIABLES, $ident);

            $catalog = $this->ReadVariableCatalog();
            if (isset($catalog[$ident]) && $this->GetObjectIDByIdent($ident) === false) {
                $catalog[$ident]['created'] = false;
                unset($catalog[$ident]['deleted']);
                $this->WriteVariableCatalog($catalog);
            }

            $this->CreateVariableFromCatalog($ident);
        } else {
            $this->AddVariableToAttributeList(self::ATTRIBUTE_DISABLED_VARIABLES, $ident);
            $this->RemoveVariableFromAttributeList(self::ATTRIBUTE_DELETED_VARIABLES, $ident);
        }

        $this->UpdateVariableSelectionFormFields();
        return true;
    }

    /**
     * Schaltet die automatische Anlage einer Variable fuer die Formularaktion um.
     */
    protected function ToggleVariableCreation(string $ident): bool
    {
        if ($this->GetObjectIDByIdent($this->NormalizeVariableIdent($ident)) === false) {
            return $this->SetVariableCreationEnabled($ident, true);
        }

        return $this->SetVariableCreationEnabled($ident, !$this->IsVariableCreationEnabled($ident));
    }

    /**
     * Baut die Zeilen fuer die Variablenverwaltung im Instanzformular.
     */
    protected function BuildVariableSelectionFormValues(): array
    {
        $this->RefreshVariableCatalog();

        $catalog = $this->ReadVariableCatalog();
        $presentationMigrationByIdent = $this->ReadPresentationMigrationLogByIdent();
        ksort($catalog);

        $rows = [];
        foreach ($catalog as $ident => $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $ident = (string) $ident;
            $rows[] = $this->BuildVariableSelectionFormRow($ident, $entry, $presentationMigrationByIdent[$ident] ?? null);
        }

        return $rows;
    }

    /**
     * Baut den lokalen Katalog fuer die manuell ausgeloeste Formularaktualisierung neu auf.
     *
     * Vorhandene Symcon-Variablen werden nicht geloescht. Der neue Katalog enthaelt
     * nur aktuelle Exposes, das zuletzt empfangene Geraete-Payload sowie daraus
     * abgeleitete und systemseitige Variablen.
     */
    protected function RefreshVariableSelectionFromForm(): void
    {
        $this->RebuildVariableCatalogFromCurrentData();
        $this->UpdateVariableSelectionFormFields();
    }

    /**
     * Aktualisiert die sichtbaren Formularfelder der Variablenverwaltung ohne kompletten Formular-Neuaufbau.
     */
    private function UpdateVariableSelectionFormFields(): void
    {
        $values = $this->BuildVariableSelectionFormValues();
        $this->UpdateFormField('VariableSelectionSettings', 'visible', \count($values) > 0);
        $this->UpdateFormField('VariableSelectionList', 'values', json_encode($values));
        $this->UpdateFormField('VariableSelectionList', 'rowCount', min(12, max(4, \count($values) + 1)));
    }

    /**
     * Normalisiert ein Zigbee2MQTT-Property auf einen Symcon-Ident.
     */
    private function NormalizeVariableIdent(string $ident): string
    {
        return str_replace('&', '_and_', $ident);
    }

    /**
     * Ermittelt, ob ein Expose nur als Composite-Container fuer Sub-Features dient.
     */
    private function IsExposeCompositeContainer(array $feature): bool
    {
        return ($feature['type'] ?? '') === 'composite'
            && isset($feature['features'])
            && \is_array($feature['features'])
            && !$this->IsExposeColorComposite($feature)
            && !isset($feature['color_mode']);
    }

    /**
     * Ermittelt, ob ein Composite-Expose als einzelne Farbvariable abgebildet wird.
     */
    private function IsExposeColorComposite(array $feature): bool
    {
        return ($feature['type'] ?? '') === 'composite'
            && \in_array((string) ($feature['name'] ?? ''), ['color_xy', 'color_hs', 'color_rgb'], true)
            && (string) ($feature['property'] ?? '') === 'color'
            && isset($feature['features'])
            && \is_array($feature['features']);
    }

    /**
     * Baut ein Sub-Feature so um, wie es spaeter als Symcon-Variable angelegt wird.
     */
    private function BuildCompositeSubFeature(array $subFeature, string $parentIdent, string $parentLabel): array
    {
        $subProperty = $this->NormalizeVariableIdent((string) ($subFeature['property'] ?? ''));
        $parentIdent = $this->NormalizeVariableIdent($parentIdent);
        if ($subProperty === '' || $parentIdent === '') {
            return $subFeature;
        }

        $subFeature['property'] = $parentIdent . '__' . $subProperty;

        if ($parentLabel !== '') {
            $subFeature['label'] = $parentLabel . ': ' . (string) ($subFeature['label'] ?? $this->FormatVariableCatalogLabel($subProperty));
        }

        return $subFeature;
    }

    /**
     * Formatiert einen Ident fuer den Katalog, ohne die Uebersetzungspruefung anzustossen.
     */
    private function FormatVariableCatalogLabel(string $ident): string
    {
        $label = preg_replace('/_+/', ' ', $ident);
        return ucwords((string) $label);
    }

    /**
     * Merkt sich eine potenziell anlegbare Variable fuer die lokale Variablenverwaltung.
     */
    private function RememberVariableDefinition(string $ident, ?array $feature = null, string $source = 'payload', mixed $lastValue = null): void
    {
        $ident = $this->NormalizeVariableIdent($ident);
        if ($ident === '') {
            return;
        }

        $catalog = $this->ReadVariableCatalog();
        $entry = $catalog[$ident] ?? [
            'ident'   => $ident,
            'created' => false
        ];

        $property = (string) ($feature['property'] ?? ($entry['property'] ?? $ident));
        $currentSource = (string) ($entry['source'] ?? '');
        $entry['ident'] = $ident;
        $entry['property'] = $property;
        $entry['label'] = (string) ($feature['label'] ?? ($entry['label'] ?? $this->FormatVariableCatalogLabel($property)));
        $entry['source'] = ($currentSource !== '' && $source === 'payload') ? $currentSource : $source;
        $entry['type'] = (string) ($feature['type'] ?? ($entry['type'] ?? $this->GetPayloadValueTypeName($lastValue)));
        $entry['unit'] = (string) ($feature['unit'] ?? ($entry['unit'] ?? ''));
        $entry['created'] = (bool) ($entry['created'] ?? false) || $this->GetObjectIDByIdent($ident) !== false;

        if ($feature !== null && (!isset($entry['feature']) || \count($feature) > 2)) {
            $entry['feature'] = $feature;
        }

        if ($lastValue !== null) {
            $entry['payloadType'] = $this->GetPayloadValueTypeName($lastValue);
            if (!\is_array($lastValue)) {
                $entry['lastValue'] = $lastValue;
            }
        }

        if (($catalog[$ident] ?? null) === $entry) {
            return;
        }

        $catalog[$ident] = $entry;
        $this->WriteVariableCatalog($catalog);
    }

    /**
     * Liefert eine stabile Typbeschreibung fuer Payload-basierte Katalogeintraege.
     */
    private function GetPayloadValueTypeName(mixed $value): string
    {
        return match (true) {
            \is_bool($value)  => 'binary',
            \is_int($value)   => 'integer',
            \is_float($value) => 'numeric',
            \is_array($value) => 'array',
            default           => 'text'
        };
    }

    /**
     * Prueft, ob eine Variable neu angelegt werden darf.
     */
    private function CanCreateVariable(string $ident, ?array $feature = null, string $source = 'payload', mixed $lastValue = null): bool
    {
        $ident = $this->NormalizeVariableIdent($ident);
        if ($ident === '') {
            return false;
        }

        $this->RememberVariableDefinition($ident, $feature, $source, $lastValue);

        if ($this->GetObjectIDByIdent($ident) !== false) {
            return true;
        }

        if ($this->IsVariableCreationSuppressed($ident)) {
            $this->SendDebug(__FUNCTION__, 'Variable creation suppressed: ' . $ident, 0);
            return false;
        }

        $catalog = $this->ReadVariableCatalog();
        if ((bool) ($catalog[$ident]['created'] ?? false)) {
            $this->MarkVariableAsDeleted($ident);
            $this->SendDebug(__FUNCTION__, 'Known variable was deleted by user, not recreated: ' . $ident, 0);
            return false;
        }

        return true;
    }

    /**
     * Prueft alle Quellen, die eine automatische Neuanlage verhindern.
     */
    private function IsVariableCreationSuppressed(string $ident): bool
    {
        $ident = $this->NormalizeVariableIdent($ident);
        return \in_array($ident, $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED), true)
            || \in_array($ident, $this->ReadAttributeArray(self::ATTRIBUTE_DISABLED_VARIABLES), true)
            || \in_array($ident, $this->ReadAttributeArray(self::ATTRIBUTE_DELETED_VARIABLES), true);
    }

    /**
     * Markiert eine Variable im Katalog als erfolgreich angelegt.
     */
    private function MarkVariableCreated(string $ident): void
    {
        $ident = $this->NormalizeVariableIdent($ident);
        if ($ident === '') {
            return;
        }

        $catalog = $this->ReadVariableCatalog();
        $entry = $catalog[$ident] ?? [
            'ident'    => $ident,
            'property' => $ident,
            'label'    => $this->FormatVariableCatalogLabel($ident),
            'source'   => 'existing'
        ];

        $entry['created'] = true;
        unset($entry['deleted']);
        if (($catalog[$ident] ?? null) !== $entry) {
            $catalog[$ident] = $entry;
            $this->WriteVariableCatalog($catalog);
        }
        $this->RemoveVariableFromAttributeList(self::ATTRIBUTE_DELETED_VARIABLES, $ident);
    }

    /**
     * Merkt sich, dass eine bekannte Variable vom Anwender entfernt wurde.
     */
    private function MarkVariableAsDeleted(string $ident): void
    {
        $ident = $this->NormalizeVariableIdent($ident);
        if ($ident === '') {
            return;
        }

        $this->AddVariableToAttributeList(self::ATTRIBUTE_DELETED_VARIABLES, $ident);
        $catalog = $this->ReadVariableCatalog();
        if (isset($catalog[$ident])) {
            $catalog[$ident]['deleted'] = true;
            $this->WriteVariableCatalog($catalog);
        }
    }

    /**
     * Fuegt einen Ident einmalig zu einem Array-Attribut hinzu.
     */
    private function AddVariableToAttributeList(string $attribute, string $ident): void
    {
        $ident = $this->NormalizeVariableIdent($ident);
        $values = $this->ReadAttributeArray($attribute);
        if (\in_array($ident, $values, true)) {
            return;
        }

        $values[] = $ident;
        sort($values);
        $this->WriteAttributeArray($attribute, $values);
    }

    /**
     * Entfernt einen Ident aus einem Array-Attribut.
     */
    private function RemoveVariableFromAttributeList(string $attribute, string $ident): void
    {
        $ident = $this->NormalizeVariableIdent($ident);
        $currentValues = $this->ReadAttributeArray($attribute);
        $values = array_values(array_filter(
            $currentValues,
            static fn (mixed $value): bool => $value !== $ident
        ));
        if ($values === $currentValues) {
            return;
        }

        $this->WriteAttributeArray($attribute, $values);
    }

    /**
     * Liefert true, wenn eine Variable aktuell automatisch angelegt werden darf.
     */
    private function IsVariableCreationEnabled(string $ident): bool
    {
        return !$this->IsVariableCreationSuppressed($ident);
    }

    /**
     * Versucht, eine wieder aktivierte Variable sofort aus Katalog oder letztem Payload anzulegen.
     */
    private function CreateVariableFromCatalog(string $ident): bool
    {
        $ident = $this->NormalizeVariableIdent($ident);
        $catalog = $this->ReadVariableCatalog();
        $entry = $catalog[$ident] ?? null;
        if (!\is_array($entry)) {
            return false;
        }

        if (isset($entry['feature']) && \is_array($entry['feature'])) {
            if (str_ends_with($ident, '_presets')
                && isset($entry['feature']['presets'])
                && \is_array($entry['feature']['presets'])
                && $entry['feature']['presets'] !== []
            ) {
                $feature = $entry['feature'];
                $property = (string) ($feature['preset_property'] ?? substr($ident, 0, -8));
                if ($property === '') {
                    return false;
                }

                // Fuer Darstellung und Aktion wird wieder das urspruengliche Expose-Feature benoetigt.
                $feature['property'] = $property;
                unset($feature['preset_property']);
                $variableType = $this->getVariableTypeFromFeature(
                    (string) ($feature['type'] ?? 'numeric'),
                    $property,
                    isset($feature['unit']) && \is_string($feature['unit']) ? $feature['unit'] : '',
                    isset($feature['value_step']) ? (float) $feature['value_step'] : 1.0,
                    isset($feature['group_type']) && \is_string($feature['group_type']) ? $feature['group_type'] : null
                );
                $this->registerPresetVariables($feature['presets'], $property, $variableType, $feature);

                if ($this->GetObjectIDByIdent($ident) !== false) {
                    if (isset($entry['lastValue'])) {
                        $this->SetValue($ident, $entry['lastValue']);
                    } else {
                        $mainVariableID = $this->GetObjectIDByIdent($property);
                        if ($mainVariableID !== false) {
                            $this->SetValue($ident, \GetValue($mainVariableID));
                        }
                    }
                }

                return $this->GetObjectIDByIdent($ident) !== false;
            }

            $this->registerVariable($entry['feature']);
            if ($this->GetObjectIDByIdent($ident) !== false && isset($entry['lastValue'])) {
                $this->SetValue($ident, $entry['lastValue']);
            }
            return $this->GetObjectIDByIdent($ident) !== false;
        }

        $payload = $this->lastPayload;
        $property = (string) ($entry['property'] ?? $ident);
        if (\array_key_exists($property, $payload)) {
            $this->processPayloadEntry($property, $payload[$property]);
            return $this->GetObjectIDByIdent($ident) !== false;
        }
        if (\array_key_exists($ident, $payload)) {
            $this->processPayloadEntry($ident, $payload[$ident]);
            return $this->GetObjectIDByIdent($ident) !== false;
        }

        if (isset($entry['lastValue'])) {
            $this->RegisterPayloadOnlyVariable($ident, $entry['lastValue']);
            return $this->GetObjectIDByIdent($ident) !== false;
        }

        return false;
    }

    /**
     * Legt eine reine Payload-Variable mit dem zuletzt bekannten Wert an.
     */
    private function RegisterPayloadOnlyVariable(string $ident, mixed $value): void
    {
        if (!$this->CanCreateVariable($ident, null, 'payload', $value)) {
            return;
        }

        $varType = $this->getPayloadVariableTypeDefinition($value, $ident);
        $registerFunc = $varType['registerFunc'];
        $this->$registerFunc(
            $ident,
            $this->Translate($this->convertLabelToName($ident)),
            $varType['presentation']
        );
        $this->MarkVariableCreated($ident);
        $this->SetValue($ident, $value);
    }

    /**
     * Aktualisiert den Katalog aus Exposes und bereits vorhandenen Kindvariablen.
     */
    private function RefreshVariableCatalog(): void
    {
        $this->BeginVariableCatalogBatch();
        try {
            $this->RefreshVariableCatalogBatch();
        } finally {
            $this->EndVariableCatalogBatch();
        }
    }

    /**
     * Aktualisiert den Variablenkatalog innerhalb eines bereits gestarteten Batches.
     */
    private function RefreshVariableCatalogBatch(): void
    {
        $this->RefreshExposeVariableCatalog();
        $validIdents = array_fill_keys($this->CollectCurrentVariableCatalogIdents(), true);

        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $object = IPS_GetObject($childID);
            if (($object['ObjectType'] ?? -1) !== OBJECTTYPE_VARIABLE) {
                continue;
            }

            $ident = (string) ($object['ObjectIdent'] ?? '');
            if ($ident === '') {
                continue;
            }

            $catalog = $this->ReadVariableCatalog();
            if (!isset($catalog[$ident]) && isset($validIdents[$ident])) {
                $this->RememberVariableDefinition($ident, ['property' => $ident], 'existing');
                $catalog = $this->ReadVariableCatalog();
            }
            if (isset($catalog[$ident])) {
                $this->MarkVariableCreated($ident);
            }
        }

        $this->RefreshDeletedVariableCatalogState();
    }

    /**
     * Ersetzt den bisherigen Katalog durch den fachlich aktuellen Geraeteumfang.
     *
     * Historische Kindvariablen bleiben im Symcon-Objektbaum erhalten. Sie werden
     * lediglich nicht erneut in die Device-Auswahlliste aufgenommen und koennen
     * ueber die Bridge-Variablen-Wartung gezielt geprueft werden.
     */
    private function RebuildVariableCatalogFromCurrentData(): void
    {
        $this->BeginVariableCatalogBatch();
        try {
            $this->RebuildVariableCatalogFromCurrentDataBatch();
        } finally {
            $this->EndVariableCatalogBatch();
        }
    }

    /**
     * Baut den Variablenkatalog innerhalb eines bereits gestarteten Batches neu auf.
     */
    private function RebuildVariableCatalogFromCurrentDataBatch(): void
    {
        $previousCatalog = $this->ReadVariableCatalog();
        $this->WriteVariableCatalog([]);

        $validIdents = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if (\is_array($expose)) {
                $validIdents = array_merge($validIdents, $this->RememberExposeFeatureRecursive($expose));
            }
        }

        $payload = $this->latestPayload;
        if (\is_array($payload)) {
            foreach ($this->flattenPayload($payload) as $ident => $value) {
                if ($value === null) {
                    continue;
                }

                $ident = $this->NormalizeVariableIdent((string) $ident);
                $this->RememberVariableDefinition($ident, ['property' => $ident, 'type' => $this->GetPayloadValueTypeName($value)], 'payload', $value);
                $validIdents[] = $ident;
            }
        }

        $this->RememberVariableDefinition('device_status', ['property' => 'device_status', 'type' => 'binary', 'label' => 'Availability'], 'system');
        $validIdents[] = 'device_status';
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_FILTERED) as $ident) {
            $validIdents[] = $this->NormalizeVariableIdent((string) $ident);
        }

        $validIdents = array_fill_keys(array_unique(array_filter($validIdents)), true);
        $catalog = $this->ReadVariableCatalog();
        foreach ($previousCatalog as $ident => $entry) {
            $ident = (string) $ident;
            if (!\is_array($entry)
                || (!isset($validIdents[$ident]) && !$this->IsVariableCatalogEntryCurrentlyValid($ident, $entry, $validIdents))
            ) {
                continue;
            }

            $currentEntry = $catalog[$ident] ?? [];
            $catalog[$ident] = array_replace($entry, $currentEntry);
            $catalog[$ident]['created'] = (bool) ($entry['created'] ?? false) || (bool) ($currentEntry['created'] ?? false);
        }

        $this->WriteVariableCatalog($catalog);
        $knownIdents = array_fill_keys(array_keys($catalog), true);
        $this->RestrictVariableAttributeList(self::ATTRIBUTE_DISABLED_VARIABLES, $knownIdents);
        $this->RestrictVariableAttributeList(self::ATTRIBUTE_DELETED_VARIABLES, $knownIdents);
        $this->RefreshDeletedVariableCatalogState();
    }

    /**
     * Entfernt Entscheidungen fuer Variablen, die nicht mehr im aktuellen Katalog stehen.
     *
     * @param array<string,bool> $knownIdents
     */
    private function RestrictVariableAttributeList(string $attribute, array $knownIdents): void
    {
        $currentValues = $this->ReadAttributeArray($attribute);
        $values = array_values(array_filter(
            $currentValues,
            static fn (mixed $ident): bool => isset($knownIdents[(string) $ident])
        ));
        if ($values !== $currentValues) {
            $this->WriteAttributeArray($attribute, $values);
        }
    }

    /**
     * Liefert die aktuell belegten Idents aus Exposes und letztem Einzel-Payload.
     *
     * @return array<int,string>
     */
    private function CollectCurrentVariableCatalogIdents(): array
    {
        $idents = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if (\is_array($expose)) {
                $idents = array_merge($idents, $this->RememberExposeFeatureRecursive($expose));
            }
        }

        $payload = $this->latestPayload;
        if (\is_array($payload)) {
            foreach (array_keys($this->flattenPayload($payload)) as $ident) {
                $idents[] = $this->NormalizeVariableIdent((string) $ident);
            }
        }

        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_FILTERED) as $ident) {
            $idents[] = $this->NormalizeVariableIdent((string) $ident);
        }

        return array_values(array_unique(array_filter($idents)));
    }

    /**
     * Erhaelt gueltige Preset- und abgeleitete Variablen im Katalog.
     */
    private function IsVariableCatalogEntryCurrentlyValid(string $ident, mixed $entry, array $validIdents): bool
    {
        if ($this->IsPersistentOTAVariableIdent($ident) && $this->IsDeviceOTACapable()) {
            return true;
        }

        if (str_ends_with($ident, '_presets')) {
            return isset($validIdents[substr($ident, 0, -8)]);
        }

        if (!\is_array($entry) || ($entry['source'] ?? '') !== 'derived') {
            return false;
        }

        if ($ident === 'color_temp_kelvin') {
            return isset($validIdents['color_temp']);
        }
        if ($ident === 'color') {
            return isset($validIdents['color']) || isset($validIdents['color_temp']);
        }
        if (\in_array($ident, ['color_hs', 'color_rgb'], true)) {
            return isset($validIdents['color']);
        }

        return false;
    }

    /**
     * Prueft, ob ein OTA-Wert bei OTA-faehigen Geraeten dauerhaft sichtbar bleiben darf.
     *
     * Fortschritt und Restzeit sind bewusst ausgenommen, da Zigbee2MQTT sie nur
     * waehrend eines laufenden Updates veroeffentlicht.
     */
    private function IsPersistentOTAVariableIdent(string $ident): bool
    {
        return ($ident === 'update' || str_starts_with($ident, 'update__'))
            && !\in_array($ident, ['update__progress', 'update__remaining'], true);
    }

    /**
     * Ermittelt die OTA-Faehigkeit aus Geraeteattribut oder Bridge-Cache.
     */
    private function IsDeviceOTACapable(): bool
    {
        return $this->ReadAttributeBooleanSafe(self::ATTRIBUTE_DEVICE_SUPPORTS_OTA, false)
            || $this->ReadBridgeCachedDeviceSupportsOTA() === true;
    }

    /**
     * Aktualisiert den expose-basierten Teil des Variablenkatalogs.
     *
     * Alte reine Katalogeintraege fuer Composite-Eltern werden dabei entfernt,
     * ohne vorhandene Variablen oder Payload-only Eintraege anzufassen.
     *
     * @param array|null $exposes Optional bereits geladene Exposes, sonst wird das Attribut genutzt.
     */
    private function RefreshExposeVariableCatalog(?array $exposes = null): void
    {
        $this->BeginVariableCatalogBatch();
        try {
            $this->RefreshExposeVariableCatalogBatch($exposes);
        } finally {
            $this->EndVariableCatalogBatch();
        }
    }

    /**
     * Aktualisiert den expose-basierten Katalog innerhalb eines laufenden Batches.
     */
    private function RefreshExposeVariableCatalogBatch(?array $exposes = null): void
    {
        $exposes ??= $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        if ($exposes === []) {
            return;
        }

        $validExposeIdents = [];
        foreach ($exposes as $expose) {
            if (!\is_array($expose)) {
                continue;
            }

            $validExposeIdents = array_merge($validExposeIdents, $this->RememberExposeFeatureRecursive($expose));
        }
        $this->RemoveStaleExposeCatalogEntries($validExposeIdents);
    }

    /**
     * Wendet aktuelle Modul-Standardprofile und -darstellungen erneut auf bereits vorhandene Expose-Variablen an.
     *
     * Dabei werden keine fehlenden Variablen neu erzeugt. Die erneute Registrierung nutzt
     * nur vorhandene Expose-Daten und bleibt damit innerhalb der Modul-Standarddarstellung;
     * benutzerdefinierte Darstellungen in Symcon behalten ihre hoehere Prioritaet.
     */
    private function RefreshExistingExposeVariableRegistrations(?array $exposes = null): void
    {
        $exposes ??= $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            if (\is_array($expose)) {
                $this->RefreshExistingExposeFeatureRegistration($expose);
            }
        }
    }

    /**
     * Aktualisiert ein vorhandenes Expose-Feature rekursiv.
     */
    private function RefreshExistingExposeFeatureRegistration(array $feature, ?string $groupType = null): void
    {
        if (isset($feature['group_type']) && \is_string($feature['group_type'])) {
            $groupType = $feature['group_type'];
        } elseif (isset($feature['features'])
            && \is_array($feature['features'])
            && \in_array($feature['type'] ?? '', ['light', 'switch', 'lock', 'cover', 'climate', 'fan', 'text'], true)
        ) {
            $groupType = (string) $feature['type'];
        }
        if ($groupType !== null) {
            $feature['group_type'] = $groupType;
        }

        if (isset($feature['color_mode'])) {
            return;
        }

        if ($this->IsExposeCompositeContainer($feature)) {
            $parentIdent = $this->NormalizeVariableIdent((string) ($feature['property'] ?? ''));
            $parentLabel = (string) ($feature['label'] ?? $this->FormatVariableCatalogLabel($parentIdent));
            foreach ($feature['features'] as $subFeature) {
                if (\is_array($subFeature)) {
                    $this->RefreshExistingExposeFeatureRegistration(
                        $this->BuildCompositeSubFeature($subFeature, $parentIdent, $parentLabel),
                        $groupType
                    );
                }
            }

            return;
        }

        $property = (string) ($feature['property'] ?? '');
        $ident = $this->NormalizeVariableIdent($property);
        $hasExistingVariable = $ident !== '' && $this->GetObjectIDByIdent($ident) !== false;
        $hasExistingDerivedVariable = $property === 'color_temp'
            && $this->GetObjectIDByIdent('color_temp_kelvin') !== false;
        $hasExistingPresetVariable = $property !== ''
            && isset($feature['presets'])
            && \is_array($feature['presets'])
            && $this->GetObjectIDByIdent($property . '_presets') !== false;

        if ($hasExistingVariable || $hasExistingDerivedVariable) {
            $this->registerVariable($feature);
        }
        if ($hasExistingPresetVariable) {
            $variableType = $this->getVariableTypeFromFeature(
                (string) ($feature['type'] ?? 'numeric'),
                $property,
                isset($feature['unit']) && \is_string($feature['unit']) ? $feature['unit'] : '',
                isset($feature['value_step']) ? (float) $feature['value_step'] : 1.0,
                $groupType
            );
            $this->registerPresetVariables($feature['presets'], $property, $variableType, $feature);
        }

        foreach ($feature['features'] ?? [] as $subFeature) {
            if (\is_array($subFeature)) {
                $this->RefreshExistingExposeFeatureRegistration($subFeature, $groupType);
            }
        }
    }

    /**
     * Markiert bekannte, frueher angelegte und nun fehlende Variablen als geloescht.
     */
    private function RefreshDeletedVariableCatalogState(): void
    {
        foreach ($this->ReadVariableCatalog() as $ident => $entry) {
            if (!\is_array($entry) || !(bool) ($entry['created'] ?? false)) {
                continue;
            }

            if ($this->GetObjectIDByIdent((string) $ident) !== false || $this->IsVariableCreationSuppressed((string) $ident)) {
                continue;
            }

            $this->MarkVariableAsDeleted((string) $ident);
        }
    }

    /**
     * Uebernimmt Expose-Features rekursiv in den lokalen Variablenkatalog.
     *
     * @return array<int,string> Gueltige Variablen-Idents aus den Exposes.
     */
    private function RememberExposeFeatureRecursive(array $feature, ?string $groupType = null): array
    {
        if (isset($feature['group_type']) && \is_string($feature['group_type'])) {
            $groupType = $feature['group_type'];
        } elseif (isset($feature['features'])
            && \is_array($feature['features'])
            && \in_array($feature['type'] ?? '', ['light', 'switch', 'lock', 'cover', 'climate', 'fan', 'text'], true)
        ) {
            $groupType = (string) $feature['type'];
        }
        if ($groupType !== null) {
            $feature['group_type'] = $groupType;
        }

        if (isset($feature['color_mode'])) {
            return [];
        }

        if ($this->IsExposeColorComposite($feature)) {
            $property = (string) ($feature['property'] ?? '');
            $this->RememberVariableDefinition($property, $feature, 'expose');
            return [$this->NormalizeVariableIdent($property)];
        }

        if ($this->IsExposeCompositeContainer($feature)) {
            $parentIdent = $this->NormalizeVariableIdent((string) ($feature['property'] ?? ''));
            $parentLabel = (string) ($feature['label'] ?? $this->FormatVariableCatalogLabel($parentIdent));
            $idents = [];

            foreach ($feature['features'] as $subFeature) {
                if (!\is_array($subFeature)) {
                    continue;
                }

                $idents = array_merge($idents, $this->RememberExposeFeatureRecursive(
                    $this->BuildCompositeSubFeature($subFeature, $parentIdent, $parentLabel),
                    $groupType
                ));
            }

            return $idents;
        }

        $idents = [];
        $property = (string) ($feature['property'] ?? '');
        if ($property !== '' && !isset($feature['color_mode'])) {
            $this->RememberVariableDefinition($property, $feature, 'expose');
            $idents[] = $this->NormalizeVariableIdent($property);

            if (isset($feature['presets']) && \is_array($feature['presets']) && $feature['presets'] !== []) {
                $presetIdent = $property . '_presets';
                $this->RememberVariableDefinition(
                    $presetIdent,
                    $this->BuildPresetCatalogFeature($feature, $property, $feature['presets']),
                    'expose'
                );
                $idents[] = $this->NormalizeVariableIdent($presetIdent);
            }
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return $idents;
        }

        foreach ($feature['features'] as $subFeature) {
            if (\is_array($subFeature)) {
                $idents = array_merge($idents, $this->RememberExposeFeatureRecursive($subFeature, $groupType));
            }
        }

        return $idents;
    }

    /**
     * Baut die vollstaendige Katalogdefinition einer abgeleiteten Preset-Variable.
     */
    private function BuildPresetCatalogFeature(array $feature, string $property, array $presets): array
    {
        $presetFeature = $feature;
        $presetFeature['property'] = $property . '_presets';
        $presetFeature['preset_property'] = $property;
        $presetFeature['presets'] = $presets;
        $presetFeature['label'] = $this->FormatVariableCatalogLabel($property) . ' Presets';
        return $presetFeature;
    }

    /**
     * Entfernt alte Katalogeintraege fuer Composite-Container oder unpraefixte Sub-Features.
     *
     * Payload-only Eintraege und bereits vorhandene Variablen bleiben erhalten.
     */
    private function RemoveStaleExposeCatalogEntries(array $validExposeIdents): void
    {
        $validExposeIdents = array_unique($validExposeIdents);
        $catalog = $this->ReadVariableCatalog();
        $changed = false;
        $removedIdents = [];

        foreach ($catalog as $ident => $entry) {
            if (!\is_array($entry) || ($entry['source'] ?? '') !== 'expose') {
                continue;
            }

            if (\in_array((string) $ident, $validExposeIdents, true) || $this->GetObjectIDByIdent((string) $ident) !== false) {
                continue;
            }

            if ((bool) ($entry['created'] ?? false) || isset($entry['lastValue'])) {
                continue;
            }

            unset($catalog[$ident]);
            $removedIdents[] = (string) $ident;
            $changed = true;
        }

        if ($changed) {
            $this->WriteVariableCatalog($catalog);
            foreach ($removedIdents as $removedIdent) {
                $this->RemoveVariableFromAttributeList(self::ATTRIBUTE_DISABLED_VARIABLES, $removedIdent);
                $this->RemoveVariableFromAttributeList(self::ATTRIBUTE_DELETED_VARIABLES, $removedIdent);
            }
        }
    }

    /**
     * Baut eine einzelne Zeile fuer die Variablenverwaltung.
     */
    private function BuildVariableSelectionFormRow(string $ident, array $entry, ?array $presentationMigration): array
    {
        $exists = $this->GetObjectIDByIdent($ident) !== false;
        $filtered = \in_array($ident, $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED), true);
        $disabled = \in_array($ident, $this->ReadAttributeArray(self::ATTRIBUTE_DISABLED_VARIABLES), true);
        $deleted = \in_array($ident, $this->ReadAttributeArray(self::ATTRIBUTE_DELETED_VARIABLES), true);

        $state = $exists ? 'Created' : 'Not created';
        $action = $exists ? 'Disable' : 'Create';
        $rowColor = '';

        if ($filtered) {
            $state = 'Filtered by Zigbee2MQTT';
            $action = '';
            $rowColor = '#DFDFDF';
        } elseif ($deleted) {
            $state = 'Deleted';
            $action = 'Create';
            $rowColor = '#FFFFC0';
        } elseif ($disabled) {
            $state = $exists ? 'Disabled, exists' : 'Disabled';
            $action = 'Enable';
            $rowColor = '#DFDFDF';
        }

        return [
            'ident'            => $ident,
            'label'            => (string) ($entry['label'] ?? $this->FormatVariableCatalogLabel($ident)),
            'source'           => $this->Translate((string) ($entry['source'] ?? 'payload')),
            'type'             => $this->Translate((string) ($entry['type'] ?? '')),
            'old_profile'      => (string) ($presentationMigration['oldProfile'] ?? ''),
            'new_presentation' => $this->ResolveVariableSelectionPresentation($ident, $entry, $presentationMigration),
            'state'            => $this->Translate($state),
            'action'           => $action === '' ? '' : $this->Translate($action),
            'rowColor'         => $rowColor
        ];
    }

    /**
     * Ermittelt die in der Variablenliste angezeigte Darstellung.
     *
     * Bei migrierten Variablen bleibt der protokollierte Zielwert fuehrend.
     * Frisch angelegte Variablen lesen ihre aktuelle Symcon-Darstellung aus
     * oder leiten sie aus den gespeicherten Expose-/Payload-Informationen ab.
     */
    private function ResolveVariableSelectionPresentation(string $ident, array $entry, ?array $presentationMigration): string
    {
        $migratedPresentation = (string) ($presentationMigration['newPresentation'] ?? '');
        if ($migratedPresentation !== '') {
            return $migratedPresentation;
        }

        $currentPresentation = $this->ReadCurrentVariablePresentation($ident);
        if ($currentPresentation !== '') {
            return $currentPresentation;
        }

        return $this->InferVariableSelectionPresentation($ident, $entry);
    }

    /**
     * Liest die aktuell an der Symcon-Variable hinterlegte Darstellung.
     */
    private function ReadCurrentVariablePresentation(string $ident): string
    {
        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false || !\function_exists('IPS_GetVariable')) {
            return '';
        }

        try {
            $variable = IPS_GetVariable((int) $variableID);
        } catch (\Throwable $exception) {
            return '';
        }

        foreach (['VariableCustomPresentation', 'VariablePresentation'] as $field) {
            if (!\array_key_exists($field, $variable)) {
                continue;
            }

            $description = $this->DescribeVariableSelectionPresentationValue($variable[$field]);
            if ($description !== '') {
                return $description;
            }
        }

        return '';
    }

    /**
     * Uebersetzt eine rohe Symcon-Darstellung in die Tabellenbeschreibung.
     */
    private function DescribeVariableSelectionPresentationValue(mixed $presentation): string
    {
        if (\is_array($presentation) && $presentation !== []) {
            return $this->DescribePresentationForMigrationLog($presentation);
        }

        if (!\is_string($presentation) || $presentation === '') {
            return '';
        }

        $decoded = \json_decode($presentation, true);
        if (!\is_array($decoded) || $decoded === []) {
            return '';
        }

        return $this->DescribePresentationForMigrationLog($decoded);
    }

    /**
     * Leitet die zu erwartende Darstellung aus dem Variablenkatalog ab.
     */
    private function InferVariableSelectionPresentation(string $ident, array $entry): string
    {
        $feature = $this->BuildPresentationFeatureFromCatalogEntry($ident, $entry);
        $presentation = null;

        if ($feature !== null) {
            if (isset($feature['presets']) && \is_array($feature['presets']) && $feature['presets'] !== []) {
                if (isset($feature['preset_property']) && \is_string($feature['preset_property'])) {
                    $feature['property'] = $feature['preset_property'];
                }
                $presentation = $this->BuildPresetPresentation(
                    $feature['presets'],
                    $this->GetVariableSelectionPresetType($entry),
                    $feature
                );
            } else {
                $presentation = $this->BuildFeaturePresentation($feature);
            }
        }

        if ($presentation === null) {
            switch ($ident) {
                case 'last_seen':
                    $presentation = $this->BuildDateTimePresentation();
                    break;
                case 'update__remaining':
                    $presentation = $this->BuildDurationPresentation();
                    break;
                case 'device_status':
                    $presentation = $this->BuildDeviceStatusPresentation();
                    break;
            }
        }

        return \is_array($presentation) ? $this->DescribePresentationForMigrationLog($presentation) : '';
    }

    /**
     * Baut aus einem Katalogeintrag ein minimales Feature fuer die Darstellungslogik.
     */
    private function BuildPresentationFeatureFromCatalogEntry(string $ident, array $entry): ?array
    {
        if (isset($entry['feature']) && \is_array($entry['feature'])) {
            return $entry['feature'];
        }

        $featureType = $this->NormalizeCatalogPresentationFeatureType((string) ($entry['type'] ?? ''));
        if ($featureType === '') {
            return null;
        }

        $feature = [
            'name'     => (string) ($entry['name'] ?? $ident),
            'property' => (string) ($entry['property'] ?? $ident),
            'label'    => (string) ($entry['label'] ?? $this->FormatVariableCatalogLabel($ident)),
            'type'     => $featureType,
        ];

        foreach (['unit', 'value_min', 'value_max', 'value_step', 'access', 'values', 'presets'] as $key) {
            if (\array_key_exists($key, $entry)) {
                $feature[$key] = $entry[$key];
            }
        }

        return $feature;
    }

    /**
     * Normalisiert Katalogtypen auf Zigbee2MQTT-Featuretypen.
     */
    private function NormalizeCatalogPresentationFeatureType(string $type): string
    {
        switch (\strtolower($type)) {
            case 'numeric':
            case 'float':
            case 'integer':
            case 'int':
                return 'numeric';
            case 'binary':
            case 'boolean':
            case 'bool':
            case 'switch':
                return 'binary';
            case 'enum':
            case 'enumeration':
                return 'enum';
            case 'text':
            case 'string':
                return 'text';
            default:
                return '';
        }
    }

    /**
     * Liefert den Variablentyp fuer Preset-Darstellungen aus dem Katalog.
     */
    private function GetVariableSelectionPresetType(array $entry): string
    {
        switch (\strtolower((string) ($entry['type'] ?? ''))) {
            case 'float':
            case 'numeric':
                return 'float';
            case 'integer':
            case 'int':
                return 'integer';
            default:
                return 'string';
        }
    }

    /**
     * Liefert den letzten protokollierten Darstellungswechsel je Variablen-Ident.
     *
     * @return array<string,array>
     */
    private function ReadPresentationMigrationLogByIdent(): array
    {
        $rowsByIdent = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_PRESENTATION_MIGRATION_LOG) as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $ident = (string) ($row['ident'] ?? '');
            if ($ident === '') {
                continue;
            }

            $changed = (int) ($row['time'] ?? 0);
            $previousChanged = (int) ($rowsByIdent[$ident]['time'] ?? 0);
            if (!isset($rowsByIdent[$ident]) || $changed >= $previousChanged) {
                $rowsByIdent[$ident] = $row;
            }
        }

        return $rowsByIdent;
    }
}
