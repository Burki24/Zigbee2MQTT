<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer Taster, Fernbedienungen und Szenen-Ausloeser.
 */
trait ActionTileHelper
{
    /**
     * Prueft, ob die Aktions-Kachel aktiv verwendet werden soll.
     */
    protected function ShouldUseActionTile(): bool
    {
        return !$this->ReadPropertyBoolean(self::PROPERTY_DISABLE_ACTION_TILE) && $this->HasActionTileCapabilities();
    }

    /**
     * Prueft, ob diese Instanz als Aktionsgeraet dargestellt werden kann.
     */
    protected function HasActionTileCapabilities(): bool
    {
        return $this->GetActionTileTriggerIdents() !== [];
    }

    /**
     * Verarbeitet Aktionen der Aktions-Kachel.
     */
    protected function HandleActionTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'ActionTile.Refresh':
                $this->UpdateActionTileValue();
                return true;
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateActionTileValue(): void
    {
        if (!$this->ShouldUseActionTile()) {
            return;
        }

        $this->UpdateCustomTileVisualizationValue(json_encode(
            $this->BuildActionTileData(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    /**
     * Aktualisiert die Kachel nur, wenn ein relevanter Wert geaendert wurde.
     */
    protected function UpdateActionTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetActionTileIdents(), true)) {
            return;
        }

        $this->UpdateActionTileValue();
    }

    /**
     * Baut die Datenstruktur fuer die HTML-Kachel.
     */
    protected function BuildActionTileData(): array
    {
        $triggers = [];
        foreach ($this->GetActionTileTriggerIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $feature = $this->FindActionTileFeature($ident);
            $triggers[$ident] = [
                'available' => true,
                'label'     => $this->GetActionTileLabel($ident),
                'value'     => $rawValue,
                'formatted' => $this->FormatActionTileValue($ident, $rawValue, $variableID),
                'category'  => $this->GetActionTileCategory((string) $rawValue),
                'options'   => $this->BuildActionTileOptions($feature, $variableID)
            ];
        }

        $values = [];
        foreach ($this->GetActionTileIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'label'     => $this->GetActionTileLabel($ident),
                'value'     => $rawValue,
                'formatted' => $this->FormatActionTileValue($ident, $rawValue, $variableID),
                'category'  => $this->GetActionTileCategory((string) $rawValue),
                'kind'      => $this->GetActionTileKind($ident)
            ];
        }

        $primary = $this->BuildActionTilePrimaryState($triggers);

        return [
            'type'     => 'action',
            'name'     => IPS_GetName($this->InstanceID),
            'primary'  => $primary,
            'triggers' => $triggers,
            'values'   => $values
        ];
    }

    /**
     * Liefert alle Idents der Aktions-Kachel.
     */
    protected function GetActionTileIdents(): array
    {
        return array_values(array_unique(array_merge(
            $this->GetActionTileTriggerIdents(),
            [
                'action_duration',
                'battery',
                'battery_low',
                'linkquality',
                'last_seen',
                'device_status'
            ]
        )));
    }

    /**
     * Liefert alle Aktions-Idents.
     */
    private function GetActionTileTriggerIdents(): array
    {
        $idents = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $object = IPS_GetObject((int) $childID);
            $ident = (string) ($object['ObjectIdent'] ?? '');
            if (!$this->IsActionTileTriggerIdent($ident)) {
                continue;
            }

            $variable = IPS_GetVariable((int) $childID);
            if (!\in_array($variable['VariableType'], [VARIABLETYPE_STRING, VARIABLETYPE_INTEGER], true)) {
                continue;
            }

            $idents[] = $ident;
        }

        return $this->SortActionTileTriggerIdents(array_values(array_unique($idents)));
    }

    /**
     * Prueft auf bekannte Action-/Taster-Idents.
     */
    private function IsActionTileTriggerIdent(string $ident): bool
    {
        if (\in_array($ident, ['action', 'scene', 'click', 'button'], true)) {
            return true;
        }

        if (preg_match('/^(action|scene|click|button)_(?!duration|hold_time|switch|mode|type|calibration|enabled|state)([a-z0-9]+)$/i', $ident)) {
            return true;
        }

        return false;
    }

    /**
     * Sortiert Haupt-Action vor kanalisierten Actionwerten.
     */
    private function SortActionTileTriggerIdents(array $idents): array
    {
        $order = ['action', 'scene', 'click', 'button'];
        usort($idents, static function (string $left, string $right) use ($order): int {
            $leftIndex = array_search($left, $order, true);
            $rightIndex = array_search($right, $order, true);
            $leftRank = $leftIndex === false ? 99 : $leftIndex;
            $rightRank = $rightIndex === false ? 99 : $rightIndex;

            return [$leftRank, $left] <=> [$rightRank, $right];
        });

        return $idents;
    }

    /**
     * Baut den Hauptstatus fuer die erste Kachelseite.
     */
    private function BuildActionTilePrimaryState(array $triggers): array
    {
        foreach ($triggers as $ident => $trigger) {
            $value = (string) ($trigger['value'] ?? '');
            if ($value !== '') {
                return [
                    'ident'     => $ident,
                    'label'     => $trigger['label'],
                    'value'     => $trigger['value'],
                    'formatted' => $trigger['formatted'],
                    'category'  => $trigger['category'],
                    'icon'      => $this->GetActionTileIcon($trigger['category'])
                ];
            }
        }

        $firstIdent = array_key_first($triggers);
        return [
            'ident'     => $firstIdent ?? '',
            'label'     => $firstIdent !== null ? $triggers[$firstIdent]['label'] : 'Aktion',
            'value'     => '',
            'formatted' => 'Bereit',
            'category'  => 'button',
            'icon'      => 'button'
        ];
    }

    /**
     * Baut die moeglichen Aktionswerte aus Expose oder Variablenprofil.
     */
    private function BuildActionTileOptions(?array $feature, int $variableID): array
    {
        $values = [];
        if ($feature !== null && isset($feature['values']) && \is_array($feature['values'])) {
            $values = array_map('strval', $feature['values']);
        }

        if ($values === []) {
            $variable = IPS_GetVariable($variableID);
            $profileName = $variable['VariableCustomProfile'] !== '' ? $variable['VariableCustomProfile'] : $variable['VariableProfile'];
            if ($profileName !== '' && IPS_VariableProfileExists($profileName)) {
                foreach (IPS_GetVariableProfile($profileName)['Associations'] as $association) {
                    if (isset($association['Value'])) {
                        $values[] = (string) $association['Value'];
                    }
                }
            }
        }

        if ($values === []) {
            return [];
        }

        $options = [];
        foreach (array_values(array_unique($values)) as $value) {
            $value = (string) $value;
            $category = $this->GetActionTileCategory($value);
            $options[] = [
                'value'     => $value,
                'formatted' => $this->FormatActionTileActionText($value),
                'category'  => $category,
                'icon'      => $this->GetActionTileIcon($category)
            ];
        }

        return $options;
    }

    /**
     * Formatiert einen Kachelwert.
     */
    private function FormatActionTileValue(string $ident, mixed $value, ?int $variableID = null): string
    {
        if ($ident === 'last_seen') {
            return \date('d.m.Y H:i:s', (int) $value);
        }

        if ($this->IsActionTileTriggerIdent($ident)) {
            $value = (string) $value;
            return $value === '' ? 'Bereit' : $this->FormatActionTileActionText($value);
        }

        if (\is_bool($value)) {
            return $value ? $this->Translate('On') : $this->Translate('Off');
        }

        if ($variableID !== null && \function_exists('GetValueFormatted')) {
            try {
                return GetValueFormatted($variableID);
            } catch (\Throwable) {
                // Bei sehr neuen Presentations koennen Test-Stubs oder aeltere Systeme
                // die Formatierung noch nicht kennen. Die Kachel bleibt trotzdem nutzbar.
            }
        }

        return (string) $value;
    }

    /**
     * Formatiert bekannte Zigbee2MQTT-Aktionswerte lesbar.
     */
    private function FormatActionTileActionText(string $value): string
    {
        $lower = strtolower($value);
        $labels = [
            'on'                              => 'An',
            'off'                             => 'Aus',
            'toggle'                          => 'Umschalten',
            'brightness_move_to_level'        => 'Helligkeit setzen',
            'brightness_move_up'              => 'Helligkeit hoch',
            'brightness_move_down'            => 'Helligkeit runter',
            'brightness_step_up'              => 'Helligkeit Schritt hoch',
            'brightness_step_down'            => 'Helligkeit Schritt runter',
            'brightness_stop'                 => 'Helligkeit Stop',
            'color_temperature_move_stop'     => 'Farbtemperatur Stop',
            'color_temperature_move_up'       => 'Farbtemperatur waermer',
            'color_temperature_move_down'     => 'Farbtemperatur kuehler',
            'color_temperature_step_up'       => 'Farbtemperatur Schritt waermer',
            'color_temperature_step_down'     => 'Farbtemperatur Schritt kuehler',
            'enhanced_move_to_hue_and_saturation' => 'Farbe setzen',
            'move_to_hue_and_saturation'      => 'Farbe setzen',
            'color_hue_step_up'               => 'Farbton hoch',
            'color_hue_step_down'             => 'Farbton runter',
            'color_saturation_step_up'        => 'Saettigung hoch',
            'color_saturation_step_down'      => 'Saettigung runter',
            'color_loop_set'                  => 'Farbwechsel',
            'color_temperature_move'          => 'Farbtemperatur bewegen',
            'color_move'                      => 'Farbe bewegen',
            'hue_move'                        => 'Farbton bewegen',
            'hue_stop'                        => 'Farbton Stop',
            'move_to_saturation'              => 'Saettigung setzen',
            'move_to_hue'                     => 'Farbton setzen',
            'recall'                          => 'Szene abrufen',
            'store'                           => 'Szene speichern',
            'add'                             => 'Szene hinzufuegen',
            'remove'                          => 'Szene entfernen',
            'remove_all'                      => 'Alle Szenen entfernen',
            'opened_left'                     => 'Links geoeffnet',
            'opened_right'                    => 'Rechts geoeffnet',
            'closed_left'                     => 'Links geschlossen',
            'closed_right'                    => 'Rechts geschlossen',
            'single'                          => 'Einfach',
            'double'                          => 'Doppelt',
            'triple'                          => 'Dreifach',
            'quadruple'                       => 'Vierfach',
            'hold'                            => 'Gehalten',
            'release'                         => 'Losgelassen',
            'pressed'                         => 'Gedrueckt',
            'released'                        => 'Frei'
        ];

        if (isset($labels[$lower])) {
            return $labels[$lower];
        }

        if (preg_match('/^(.+)_([0-9]+)$/', $lower, $matches)) {
            $base = $this->FormatActionTileActionText($matches[1]);
            return $base . ' ' . $matches[2];
        }

        return ucwords(str_replace('_', ' ', $value));
    }

    /**
     * Kategorisiert Aktionswerte fuer Icon und Gruppierung.
     */
    private function GetActionTileCategory(string $value): string
    {
        $value = strtolower($value);
        if ($value === '' || \in_array($value, ['pressed', 'released', 'single', 'double', 'triple', 'quadruple', 'hold', 'release'], true)) {
            return 'button';
        }
        if (\in_array($value, ['on', 'off', 'toggle'], true)) {
            return 'power';
        }
        if (str_contains($value, 'brightness')) {
            return 'brightness';
        }
        if (str_contains($value, 'color') || str_contains($value, 'hue') || str_contains($value, 'saturation')) {
            return 'color';
        }
        if (\in_array($value, ['recall', 'store', 'add', 'remove', 'remove_all'], true)) {
            return 'scene';
        }
        if (str_contains($value, 'open') || str_contains($value, 'closed')) {
            return 'switch';
        }

        return 'action';
    }

    /**
     * Liefert die Beschriftung fuer einen Wert.
     */
    private function GetActionTileLabel(string $ident): string
    {
        return match ($ident) {
            'action'          => 'Aktion',
            'scene'           => 'Szene',
            'click'           => 'Klick',
            'button'          => 'Taste',
            'action_duration' => 'Dauer',
            'battery'         => 'Batterie',
            'battery_low'     => 'Batterie niedrig',
            'linkquality'     => 'Verbindung',
            'last_seen'       => 'Zuletzt gesehen',
            'device_status'   => 'Verfuegbarkeit',
            default           => $this->FormatActionTileIdent($ident)
        };
    }

    /**
     * Formatiert unbekannte Idents.
     */
    private function FormatActionTileIdent(string $ident): string
    {
        return ucwords(str_replace('_', ' ', $ident));
    }

    /**
     * Liefert ein Typ-Schluesselwort fuer die HTML-Kachel.
     */
    private function GetActionTileKind(string $ident): string
    {
        if ($this->IsActionTileTriggerIdent($ident)) {
            return 'trigger';
        }

        return match ($ident) {
            'battery',
            'battery_low'     => 'battery',
            'linkquality'     => 'signal',
            'last_seen'       => 'clock',
            'device_status'   => 'device',
            'action_duration' => 'duration',
            default           => 'detail'
        };
    }

    /**
     * Liefert ein Icon-Schluesselwort fuer eine Kategorie.
     */
    private function GetActionTileIcon(string $category): string
    {
        return match ($category) {
            'power'      => 'power',
            'brightness' => 'brightness',
            'color'      => 'color',
            'scene'      => 'scene',
            'switch'     => 'switch',
            default      => 'button'
        };
    }

    /**
     * Sucht ein Expose-Feature anhand seiner Property.
     */
    private function FindActionTileFeature(string $property): ?array
    {
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            $found = $this->FindActionTileFeatureRecursive($expose, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Rekursive Suche in gruppierten Exposes.
     */
    private function FindActionTileFeatureRecursive(array $feature, string $property): ?array
    {
        if (($feature['property'] ?? $feature['name'] ?? null) === $property) {
            return $feature;
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return null;
        }

        foreach ($feature['features'] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }

            $found = $this->FindActionTileFeatureRecursive($subFeature, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
