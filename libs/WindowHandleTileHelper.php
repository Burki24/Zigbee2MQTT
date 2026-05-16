<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer Fenstergriffe mit Griffposition und Tasten.
 */
trait WindowHandleTileHelper
{
    /**
     * Prueft, ob die Fenstergriff-Kachel aktiv verwendet werden soll.
     */
    protected function ShouldUseWindowHandleTile(): bool
    {
        return !$this->ReadPropertyBoolean(self::PROPERTY_DISABLE_WINDOW_HANDLE_TILE) && $this->HasWindowHandleTileCapabilities();
    }

    /**
     * Prueft, ob diese Instanz als Fenstergriff dargestellt werden kann.
     */
    protected function HasWindowHandleTileCapabilities(): bool
    {
        $positionID = $this->GetObjectIDByIdent('position');
        if ($positionID === false) {
            return false;
        }

        $feature = $this->FindWindowHandleTileFeature('position');
        if ($feature !== null) {
            if (($feature['type'] ?? '') !== 'enum') {
                return false;
            }

            $values = array_map('strtolower', array_map('strval', $feature['values'] ?? []));
            if (array_intersect($values, ['up', 'right', 'down', 'left']) === []) {
                return false;
            }
        } else {
            $position = $this->NormalizeWindowHandleTilePosition(GetValue($positionID), $positionID);
            if (!\in_array($position, ['up', 'right', 'down', 'left'], true)) {
                return false;
            }
        }

        return $this->GetObjectIDByIdent('button_left') !== false
            || $this->GetObjectIDByIdent('button_right') !== false
            || $this->GetObjectIDByIdent('alarm') !== false;
    }

    /**
     * Verarbeitet Aktionen der Fenstergriff-Kachel.
     */
    protected function HandleWindowHandleTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'WindowHandleTile.Refresh':
                $this->UpdateWindowHandleTileValue();
                return true;
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateWindowHandleTileValue(): void
    {
        if (!$this->ShouldUseWindowHandleTile()) {
            return;
        }

        $this->UpdateVisualizationValue(json_encode(
            $this->BuildWindowHandleTileData(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    /**
     * Aktualisiert die Kachel nur, wenn ein relevanter Wert geaendert wurde.
     */
    protected function UpdateWindowHandleTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetWindowHandleTileIdents(), true)) {
            return;
        }

        $this->UpdateWindowHandleTileValue();
    }

    /**
     * Baut die Datenstruktur fuer die HTML-Kachel.
     */
    protected function BuildWindowHandleTileData(): array
    {
        $values = [];
        foreach ($this->GetWindowHandleTileIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'label'     => $this->GetWindowHandleTileLabel($ident),
                'value'     => $rawValue,
                'formatted' => $this->FormatWindowHandleTileValue($ident, $rawValue, $variableID),
                'level'     => $this->GetWindowHandleTileLevel($ident, $rawValue, $variableID),
                'kind'      => $this->GetWindowHandleTileKind($ident)
            ];
        }

        $position = $this->BuildWindowHandleTilePositionState($values);
        $alarm = $this->BuildWindowHandleTileAlarmState($values);

        return [
            'type'     => 'windowHandle',
            'name'     => IPS_GetName($this->InstanceID),
            'position' => $position,
            'alarm'    => $alarm,
            'buttons'  => $this->BuildWindowHandleTileButtonStates($values),
            'values'   => $values
        ];
    }

    /**
     * Liefert alle Idents der Fenstergriff-Kachel.
     */
    protected function GetWindowHandleTileIdents(): array
    {
        return [
            'alarm',
            'position',
            'opening_mode',
            'button_left',
            'button_right',
            'battery',
            'battery_low',
            'linkquality',
            'last_seen',
            'temperature',
            'humidity',
            'alarm_switch',
            'handlesound',
            'keysound',
            'sensitivity',
            'duration',
            'update_frequency',
            'vibration',
            'vibration_limit'
        ];
    }

    /**
     * Baut den Griffpositionsstatus.
     */
    private function BuildWindowHandleTilePositionState(array $values): array
    {
        $positionID = $this->GetObjectIDByIdent('position');
        $rawValue = $positionID !== false ? GetValue($positionID) : '';
        $state = $this->NormalizeWindowHandleTilePosition($rawValue, $positionID);

        return [
            'ident'     => 'position',
            'value'     => $state,
            'formatted' => $this->FormatWindowHandleTilePosition($state),
            'caption'   => 'Griffposition',
            'level'     => $this->GetWindowHandleTilePositionLevel($state),
            'icon'      => $this->GetWindowHandleTilePositionIcon($state)
        ];
    }

    /**
     * Baut den Alarmstatus.
     */
    private function BuildWindowHandleTileAlarmState(array $values): array
    {
        if (!isset($values['alarm'])) {
            return [
                'ident'     => 'alarm',
                'value'     => false,
                'formatted' => 'OK',
                'caption'   => 'Alarm',
                'level'     => 'safe'
            ];
        }

        $value = $values['alarm']['value'];
        return [
            'ident'     => 'alarm',
            'value'     => $value,
            'formatted' => $this->FormatWindowHandleTileAlarmValue($value),
            'caption'   => 'Alarm',
            'level'     => $this->IsWindowHandleTileAlarmActive($value) ? 'alert' : 'safe'
        ];
    }

    /**
     * Baut die beiden Tastenstatuswerte fuer die erste Kachelseite.
     */
    private function BuildWindowHandleTileButtonStates(array $values): array
    {
        $buttons = [];
        foreach (['button_left', 'button_right'] as $ident) {
            if (!isset($values[$ident])) {
                continue;
            }

            $buttons[$ident] = [
                'ident'     => $ident,
                'label'     => $ident === 'button_left' ? 'Links' : 'Rechts',
                'value'     => $values[$ident]['value'],
                'formatted' => $this->FormatWindowHandleTileButtonValue($values[$ident]['value']),
                'level'     => $this->IsWindowHandleTileButtonPressed($values[$ident]['value']) ? 'active' : 'idle'
            ];
        }

        return $buttons;
    }

    /**
     * Normalisiert die Griffposition aus String, Enum-Index oder Profiltext.
     */
    private function NormalizeWindowHandleTilePosition(mixed $value, int|false $variableID = false): string
    {
        if (\is_int($value)) {
            $feature = $this->FindWindowHandleTileFeature('position');
            if (isset($feature['values'][$value])) {
                return strtolower((string) $feature['values'][$value]);
            }
        }

        $text = strtolower(trim((string) $value));
        if ($text !== '' && !is_numeric($text)) {
            return $this->MapWindowHandleTilePositionText($text);
        }

        if ($variableID !== false && \function_exists('GetValueFormatted')) {
            try {
                $formatted = strtolower(trim((string) GetValueFormatted($variableID)));
                if ($formatted !== '') {
                    return $this->MapWindowHandleTilePositionText($formatted);
                }
            } catch (\Throwable) {
                // Fallback auf Unknown.
            }
        }

        return 'unknown';
    }

    /**
     * Mappt bekannte Profil-/Payloadtexte auf Griffpositionen.
     */
    private function MapWindowHandleTilePositionText(string $text): string
    {
        return match ($text) {
            'up', 'rauf', 'oben', 'tilted', 'gekippt' => 'up',
            'down', 'runter', 'unten', 'closed', 'geschlossen' => 'down',
            'left', 'links' => 'left',
            'right', 'rechts' => 'right',
            default => $text
        };
    }

    /**
     * Formatiert einen Kachelwert.
     */
    private function FormatWindowHandleTileValue(string $ident, mixed $value, ?int $variableID = null): string
    {
        if ($ident === 'position') {
            return $this->FormatWindowHandleTilePosition($this->NormalizeWindowHandleTilePosition($value, $variableID ?? false));
        }
        if ($ident === 'alarm') {
            return $this->FormatWindowHandleTileAlarmValue($value);
        }
        if ($ident === 'button_left' || $ident === 'button_right') {
            return $this->FormatWindowHandleTileButtonValue($value);
        }
        if ($ident === 'last_seen') {
            return \date('d.m.Y H:i:s', (int) $value);
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
     * Formatiert die Griffposition.
     */
    private function FormatWindowHandleTilePosition(string $position): string
    {
        return match ($position) {
            'up'    => 'Gekippt',
            'down'  => 'Geschlossen',
            'left',
            'right' => 'Offen',
            default => $position !== '' ? $position : 'Unbekannt'
        };
    }

    /**
     * Formatiert den Alarmwert.
     */
    private function FormatWindowHandleTileAlarmValue(mixed $value): string
    {
        return $this->IsWindowHandleTileAlarmActive($value) ? 'Alarm' : 'OK';
    }

    /**
     * Formatiert einen Tastenwert.
     */
    private function FormatWindowHandleTileButtonValue(mixed $value): string
    {
        return $this->IsWindowHandleTileButtonPressed($value) ? 'Gedrueckt' : 'Frei';
    }

    /**
     * Ermittelt die Sicherheitsstufe fuer Detailwerte.
     */
    private function GetWindowHandleTileLevel(string $ident, mixed $value, ?int $variableID = null): string
    {
        if ($ident === 'alarm') {
            return $this->IsWindowHandleTileAlarmActive($value) ? 'alert' : 'safe';
        }
        if ($ident === 'position') {
            return $this->GetWindowHandleTilePositionLevel($this->NormalizeWindowHandleTilePosition($value, $variableID ?? false));
        }
        if ($ident === 'button_left' || $ident === 'button_right') {
            return $this->IsWindowHandleTileButtonPressed($value) ? 'active' : 'idle';
        }
        if ($ident === 'battery_low') {
            return $this->IsWindowHandleTileTruthy($value) ? 'warning' : 'safe';
        }

        return 'info';
    }

    /**
     * Ermittelt die Farbebene fuer die Griffposition.
     */
    private function GetWindowHandleTilePositionLevel(string $position): string
    {
        return match ($position) {
            'down' => 'safe',
            'up' => 'tilted',
            'left',
            'right' => 'alert',
            default => 'info'
        };
    }

    /**
     * Liefert ein Icon-Schluesselwort fuer die Griffposition.
     */
    private function GetWindowHandleTilePositionIcon(string $position): string
    {
        return match ($position) {
            'up' => 'tilt',
            'left' => 'left',
            'right' => 'right',
            default => 'handle'
        };
    }

    /**
     * Liefert die Beschriftung fuer einen Wert.
     */
    private function GetWindowHandleTileLabel(string $ident): string
    {
        return match ($ident) {
            'alarm'           => 'Alarm',
            'position'        => 'Griffposition',
            'opening_mode'    => 'Oeffnungsmodus',
            'button_left'     => 'Taste links',
            'button_right'    => 'Taste rechts',
            'battery'         => 'Batterie',
            'battery_low'     => 'Batterie niedrig',
            'linkquality'     => 'Verbindung',
            'last_seen'       => 'Zuletzt gesehen',
            'temperature'     => 'Temperatur',
            'humidity'        => 'Luftfeuchtigkeit',
            'alarm_switch'    => 'Alarmschalter',
            'handlesound'     => 'Griff-Ton',
            'keysound'        => 'Tasten-Ton',
            'sensitivity'     => 'Empfindlichkeit',
            'duration'        => 'Dauer',
            'update_frequency'=> 'Aktualisierungsintervall',
            'vibration'       => 'Vibration',
            'vibration_limit' => 'Vibrationsgrenze',
            default           => $ident
        };
    }

    /**
     * Liefert ein Typ-Schluesselwort fuer die HTML-Kachel.
     */
    private function GetWindowHandleTileKind(string $ident): string
    {
        return match ($ident) {
            'alarm',
            'alarm_switch'    => 'alarm',
            'position',
            'opening_mode'    => 'position',
            'button_left',
            'button_right'    => 'button',
            'battery',
            'battery_low'     => 'battery',
            'linkquality'     => 'signal',
            'last_seen'       => 'clock',
            'temperature'     => 'temperature',
            'humidity'        => 'humidity',
            'vibration',
            'vibration_limit' => 'vibration',
            default           => 'setting'
        };
    }

    /**
     * Prueft, ob der Alarm aktiv ist.
     */
    private function IsWindowHandleTileAlarmActive(mixed $value): bool
    {
        if (\is_string($value)) {
            return \in_array(strtolower($value), ['alarm', 'on', 'true', '1'], true);
        }

        return $this->IsWindowHandleTileTruthy($value);
    }

    /**
     * Prueft, ob eine Taste gedrueckt ist.
     */
    private function IsWindowHandleTileButtonPressed(mixed $value): bool
    {
        if (\is_string($value)) {
            return \in_array(strtolower($value), ['pressed', 'press', 'on', 'true', '1'], true);
        }

        return $this->IsWindowHandleTileTruthy($value);
    }

    /**
     * Interpretiert boolesche/stringbasierte Werte.
     */
    private function IsWindowHandleTileTruthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_numeric($value)) {
            return ((float) $value) !== 0.0;
        }
        if (\is_string($value)) {
            return \in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * Sucht ein Expose-Feature anhand seiner Property.
     */
    private function FindWindowHandleTileFeature(string $property): ?array
    {
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            $found = $this->FindWindowHandleTileFeatureRecursive($expose, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Rekursive Suche in gruppierten Exposes.
     */
    private function FindWindowHandleTileFeatureRecursive(array $feature, string $property): ?array
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

            $found = $this->FindWindowHandleTileFeatureRecursive($subFeature, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
