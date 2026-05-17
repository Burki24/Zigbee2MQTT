<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer Kontakt- und Sicherheitsmelder.
 */
trait SecurityTileHelper
{
    /**
     * Prueft, ob die Sicherheits-Kachel aktiv verwendet werden soll.
     */
    protected function ShouldUseSecurityTile(): bool
    {
        return !$this->ReadPropertyBoolean(self::PROPERTY_DISABLE_SECURITY_TILE) && $this->HasSecurityTileCapabilities();
    }

    /**
     * Prueft, ob diese Instanz als Sicherheits-Kachel dargestellt werden kann.
     */
    protected function HasSecurityTileCapabilities(): bool
    {
        foreach ($this->GetSecurityTilePrimaryIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            if ($this->IsSecurityTileUsablePrimaryVariable($ident, $variableID)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verarbeitet Aktionen der Sicherheits-Kachel.
     */
    protected function HandleSecurityTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'SecurityTile.Refresh':
                $this->UpdateSecurityTileValue();
                return true;
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateSecurityTileValue(): void
    {
        if (!$this->ShouldUseSecurityTile()) {
            return;
        }

        $this->UpdateCustomTileVisualizationValue(json_encode(
            $this->BuildSecurityTileData(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    /**
     * Aktualisiert die Kachel nur, wenn ein relevanter Wert geaendert wurde.
     */
    protected function UpdateSecurityTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetSecurityTileIdents(), true)) {
            return;
        }

        $this->UpdateSecurityTileValue();
    }

    /**
     * Baut die Datenstruktur fuer die HTML-Kachel.
     */
    protected function BuildSecurityTileData(): array
    {
        $values = [];
        foreach ($this->GetSecurityTileIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'label'     => $this->GetSecurityTileLabel($ident),
                'value'     => $rawValue,
                'formatted' => $this->FormatSecurityTileValue($ident, $rawValue, $variableID),
                'level'     => $this->GetSecurityTileLevel($ident, $rawValue),
                'primary'   => \in_array($ident, $this->GetSecurityTilePrimaryIdents(), true)
            ];
        }

        $primary = $this->BuildSecurityTilePrimaryState($values);

        return [
            'type'    => 'security',
            'name'    => IPS_GetName($this->InstanceID),
            'primary' => $primary,
            'values'  => $values
        ];
    }

    /**
     * Liefert alle Idents, welche die Sicherheits-Kachel beobachten kann.
     */
    protected function GetSecurityTileIdents(): array
    {
        return array_values(array_unique(array_merge(
            $this->GetSecurityTilePrimaryIdents(),
            $this->GetSecurityTileDetailIdents()
        )));
    }

    /**
     * Liefert alle Hauptwerte, die eine Sicherheits-Kachel begruenden.
     */
    private function GetSecurityTilePrimaryIdents(): array
    {
        return [
            'contact',
            'window_open',
            'smoke',
            'carbon_monoxide',
            'gas',
            'water_leak',
            'tamper',
            'vibration',
            'alarm',
            'alarm_status'
        ];
    }

    /**
     * Liefert Detailwerte fuer die zweite Kachelseite.
     */
    private function GetSecurityTileDetailIdents(): array
    {
        return [
            'battery',
            'battery_low',
            'linkquality',
            'last_seen',
            'device_status',
            'voltage',
            'illuminance',
            'illuminance_lux',
            'temperature',
            'humidity'
        ];
    }

    /**
     * Schuetzt Schalter-/Heizungs-/Sensorgruppen davor, faelschlich als Sicherheitsgeraet zu gelten.
     */
    private function IsSecurityTileUsablePrimaryVariable(string $ident, int $variableID): bool
    {
        $variable = IPS_GetVariable($variableID);
        if (!\in_array($variable['VariableType'], [VARIABLETYPE_BOOLEAN, VARIABLETYPE_STRING], true)) {
            return false;
        }

        $feature = $this->FindSecurityTileFeature($ident);
        if ($feature === null) {
            return true;
        }

        $groupType = (string) ($feature['group_type'] ?? '');
        if (\in_array($groupType, ['switch', 'light', 'cover', 'climate', 'lock'], true)) {
            return false;
        }

        if (isset($feature['type']) && !\in_array((string) $feature['type'], ['binary', 'enum', 'text'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Baut den Hauptstatus fuer die erste Kachelseite.
     */
    private function BuildSecurityTilePrimaryState(array $values): array
    {
        foreach (['contact', 'window_open'] as $ident) {
            if (!isset($values[$ident])) {
                continue;
            }

            return $this->BuildSecurityTilePrimaryCandidate($ident, $values[$ident]);
        }

        $fallback = null;

        foreach ($this->GetSecurityTilePrimaryIdents() as $ident) {
            if (!isset($values[$ident])) {
                continue;
            }

            $item = $values[$ident];
            $candidate = $this->BuildSecurityTilePrimaryCandidate($ident, $item);

            if ($item['level'] === 'alert') {
                return $candidate;
            }

            $fallback ??= $candidate;
        }

        return $fallback ?? [
            'ident'     => '',
            'label'     => 'Status',
            'value'     => false,
            'formatted' => 'OK',
            'level'     => 'safe',
            'icon'      => 'shield'
        ];
    }

    /**
     * Baut einen einheitlichen Hauptstatus-Kandidaten.
     */
    private function BuildSecurityTilePrimaryCandidate(string $ident, array $item): array
    {
        return [
            'ident'     => $ident,
            'label'     => $item['label'],
            'value'     => $item['value'],
            'formatted' => $item['formatted'],
            'level'     => $item['level'],
            'icon'      => $this->GetSecurityTileIcon($ident)
        ];
    }

    /**
     * Formatiert einen Kachelwert.
     */
    private function FormatSecurityTileValue(string $ident, mixed $value, ?int $variableID = null): string
    {
        if ($ident === 'last_seen') {
            return \date('d.m.Y H:i:s', (int) $value);
        }

        if (\is_bool($value)) {
            return $this->FormatSecurityTileBooleanValue($ident, $value);
        }

        if ($ident === 'device_status') {
            return $value ? 'Online' : 'Offline';
        }

        if (\is_string($value) && $this->IsSecurityTileAlarmString($value)) {
            return $this->FormatSecurityTileAlarmString($ident, $value);
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
     * Formatiert boolesche Sicherheitswerte.
     */
    private function FormatSecurityTileBooleanValue(string $ident, bool $value): string
    {
        return match ($ident) {
            'contact'         => $value ? 'Geschlossen' : 'Geoeffnet',
            'window_open'     => $value ? 'Geoeffnet' : 'Geschlossen',
            'smoke'           => $value ? 'Rauch erkannt' : 'OK',
            'carbon_monoxide' => $value ? 'CO erkannt' : 'OK',
            'gas'             => $value ? 'Gas erkannt' : 'OK',
            'water_leak'      => $value ? 'Wasser erkannt' : 'OK',
            'tamper'          => $value ? 'Manipulation' : 'OK',
            'vibration'       => $value ? 'Erschuetterung' : 'OK',
            'alarm'           => $value ? 'Alarm' : 'OK',
            'battery_low'     => $value ? 'Batterie niedrig' : 'OK',
            'device_status'   => $value ? 'Online' : 'Offline',
            default           => $value ? $this->Translate('On') : $this->Translate('Off')
        };
    }

    /**
     * Ermittelt die Sicherheitsstufe fuer einen Wert.
     */
    private function GetSecurityTileLevel(string $ident, mixed $value): string
    {
        if ($ident === 'battery_low') {
            return $this->IsSecurityTileTruthy($value) ? 'warning' : 'safe';
        }

        if ($ident === 'device_status') {
            return $this->IsSecurityTileTruthy($value) ? 'safe' : 'warning';
        }

        if ($ident === 'contact') {
            return $this->IsSecurityTileTruthy($value) ? 'safe' : 'alert';
        }

        if ($ident === 'window_open') {
            return $this->IsSecurityTileTruthy($value) ? 'alert' : 'safe';
        }

        if (\in_array($ident, ['smoke', 'carbon_monoxide', 'gas', 'water_leak', 'tamper', 'vibration', 'alarm'], true)) {
            return $this->IsSecurityTileTruthy($value) ? 'alert' : 'safe';
        }

        if ($this->IsSecurityTileAlarmString((string) $value)) {
            return $this->IsSecurityTileSafeString((string) $value) ? 'safe' : 'alert';
        }

        return 'info';
    }

    /**
     * Liefert die Beschriftung fuer einen Wert.
     */
    private function GetSecurityTileLabel(string $ident): string
    {
        return match ($ident) {
            'contact'         => 'Kontakt',
            'window_open'     => 'Fenster',
            'smoke'           => 'Rauch',
            'carbon_monoxide' => 'Kohlenmonoxid',
            'gas'             => 'Gas',
            'water_leak'      => 'Wasser',
            'tamper'          => 'Manipulation',
            'vibration'       => 'Erschuetterung',
            'alarm'           => 'Alarm',
            'alarm_status'    => 'Alarmstatus',
            'battery'         => 'Batterie',
            'battery_low'     => 'Batterie niedrig',
            'linkquality'     => 'Verbindung',
            'last_seen'       => 'Zuletzt gesehen',
            'device_status'   => 'Verfuegbarkeit',
            'voltage'         => 'Spannung',
            'illuminance',
            'illuminance_lux' => 'Helligkeit',
            'temperature'     => 'Temperatur',
            'humidity'        => 'Luftfeuchtigkeit',
            default           => $ident
        };
    }

    /**
     * Liefert ein Icon-Schluesselwort fuer die HTML-Kachel.
     */
    private function GetSecurityTileIcon(string $ident): string
    {
        return match ($ident) {
            'contact',
            'window_open'     => 'door',
            'smoke',
            'carbon_monoxide',
            'gas'             => 'alert',
            'water_leak'      => 'drop',
            'tamper',
            'vibration',
            'alarm',
            'alarm_status'    => 'shield',
            'battery',
            'battery_low'     => 'battery',
            'linkquality'     => 'signal',
            'last_seen'       => 'clock',
            'device_status'   => 'plug',
            'voltage'         => 'bolt',
            'illuminance',
            'illuminance_lux' => 'sun',
            'temperature'     => 'thermo',
            'humidity'        => 'drop',
            default           => 'shield'
        };
    }

    /**
     * Sucht ein Expose-Feature anhand seiner Property.
     */
    private function FindSecurityTileFeature(string $property): ?array
    {
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            $found = $this->FindSecurityTileFeatureRecursive($expose, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Rekursive Suche in gruppierten Exposes.
     */
    private function FindSecurityTileFeatureRecursive(array $feature, string $property, ?string $groupType = null): ?array
    {
        $currentGroupType = $groupType;
        if (isset($feature['type']) && \in_array($feature['type'], ['light', 'switch', 'lock', 'cover', 'climate', 'fan', 'text'], true)) {
            $currentGroupType = (string) $feature['type'];
        }

        if (($feature['property'] ?? $feature['name'] ?? null) === $property) {
            $feature['group_type'] = $feature['group_type'] ?? $currentGroupType;
            return $feature;
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return null;
        }

        foreach ($feature['features'] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }

            $found = $this->FindSecurityTileFeatureRecursive($subFeature, $property, $currentGroupType);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Interpretiert bekannte boolesche/stringbasierte Alarmwerte.
     */
    private function IsSecurityTileTruthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_numeric($value)) {
            return ((float) $value) !== 0.0;
        }
        if (\is_string($value)) {
            return \in_array(strtolower($value), ['1', 'true', 'on', 'yes', 'open', 'opened', 'alarm', 'alert', 'detected', 'tamper', 'vibration', 'leak', 'smoke', 'gas', 'offline'], true);
        }

        return (bool) $value;
    }

    /**
     * Prueft, ob ein String wie ein Alarmstatus aussieht.
     */
    private function IsSecurityTileAlarmString(string $value): bool
    {
        $value = strtolower($value);
        return \in_array($value, ['ok', 'clear', 'cleared', 'normal', 'idle', 'safe', 'closed', 'close', 'off', 'alarm', 'alert', 'detected', 'open', 'opened', 'leak', 'smoke', 'gas', 'tamper', 'vibration'], true);
    }

    /**
     * Prueft, ob ein String einen sicheren Zustand beschreibt.
     */
    private function IsSecurityTileSafeString(string $value): bool
    {
        return \in_array(strtolower($value), ['ok', 'clear', 'cleared', 'normal', 'idle', 'safe', 'closed', 'close', 'off'], true);
    }

    /**
     * Formatiert stringbasierte Alarmwerte.
     */
    private function FormatSecurityTileAlarmString(string $ident, string $value): string
    {
        $isSafe = $this->IsSecurityTileSafeString($value);

        return match ($ident) {
            'contact'         => $isSafe ? 'Geschlossen' : 'Geoeffnet',
            'window_open'     => $isSafe ? 'Geschlossen' : 'Geoeffnet',
            'smoke'           => $isSafe ? 'OK' : 'Rauch erkannt',
            'carbon_monoxide' => $isSafe ? 'OK' : 'CO erkannt',
            'gas'             => $isSafe ? 'OK' : 'Gas erkannt',
            'water_leak'      => $isSafe ? 'OK' : 'Wasser erkannt',
            'tamper'          => $isSafe ? 'OK' : 'Manipulation',
            'vibration'       => $isSafe ? 'OK' : 'Erschuetterung',
            default           => $isSafe ? 'OK' : 'Alarm'
        };
    }
}
