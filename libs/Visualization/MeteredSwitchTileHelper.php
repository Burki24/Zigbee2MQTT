<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer Schaltaktoren mit optionalen Messwerten.
 */
trait MeteredSwitchTileHelper
{
    /**
     * Prueft, ob die Schalter-Kachel aktiv verwendet werden soll.
     */
    protected function ShouldUseMeteredSwitchTile(): bool
    {
        return !$this->ReadPropertyBoolean(self::PROPERTY_DISABLE_METERED_SWITCH_TILE) && $this->HasMeteredSwitchTileCapabilities();
    }

    /**
     * Aktualisiert den Visualisierungstyp passend zur aktuellen Konfiguration und Variablenlage.
     */
    protected function UpdateMeteredSwitchTileVisualizationType(): void
    {
        $this->SetVisualizationType($this->ShouldUseMeteredSwitchTile() ? 1 : 0);
    }

    /**
     * Prueft, ob diese Instanz als Schaltaktor dargestellt werden kann.
     */
    protected function HasMeteredSwitchTileCapabilities(): bool
    {
        if ($this->GetMeteredSwitchTileSwitchIdents() === []) {
            return false;
        }

        return $this->HasMeteredSwitchTileSwitchGroupCapability();
    }

    /**
     * Verarbeitet Aktionen der Metered-Switch-Kachel.
     */
    protected function HandleMeteredSwitchTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'MeteredSwitchTile.Toggle':
                $switchIdents = $this->GetMeteredSwitchTileSwitchIdents();
                $request = $this->DecodeMeteredSwitchTileRequest($value);
                $stateIdent = (string) ($request['ident'] ?? ($switchIdents[0] ?? 'state'));
                if (!\in_array($stateIdent, $switchIdents, true)) {
                    return true;
                }

                $stateID = $this->GetObjectIDByIdent($stateIdent);
                if ($stateID === false) {
                    return true;
                }

                $this->RequestAction($stateIdent, !((bool) GetValue($stateID)));
                return true;

            case 'MeteredSwitchTile.Refresh':
                $this->UpdateMeteredSwitchTileValue();
                return true;

            case 'MeteredSwitchTile.OpenArchive':
                $request = $this->DecodeMeteredSwitchTileRequest($value);
                $archiveIdent = (string) ($request['ident'] ?? '');
                $range = (string) ($request['range'] ?? 'day');

                if (!\in_array($archiveIdent, $this->GetMeteredSwitchTileArchiveIdents(), true)) {
                    return true;
                }

                $this->UpdateMeteredSwitchTileValue($this->BuildMeteredSwitchTileArchiveData($archiveIdent, $range));
                return true;
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateMeteredSwitchTileValue(?array $archiveData = null): void
    {
        if (!$this->ShouldUseMeteredSwitchTile()) {
            return;
        }

        $this->UpdateMeteredSwitchTileVisualizationValue($archiveData);
    }

    /**
     * Sendet den aktuellen Kachelzustand ohne erneute Capability-Pruefung.
     */
    private function UpdateMeteredSwitchTileVisualizationValue(?array $archiveData = null): void
    {
        $this->UpdateVisualizationValue(json_encode(
            $this->BuildMeteredSwitchTileData($archiveData),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    /**
     * Aktualisiert die Kachel nur, wenn ein relevanter Wert geaendert wurde.
     */
    protected function UpdateMeteredSwitchTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetMeteredSwitchTileIdents(), true)) {
            return;
        }

        $this->UpdateMeteredSwitchTileVisualizationValue();
    }

    /**
     * Baut die Datenstruktur fuer die HTML-Kachel.
     */
    protected function BuildMeteredSwitchTileData(?array $archiveData = null): array
    {
        $switches = [];
        foreach ($this->GetMeteredSwitchTileSwitchIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $switches[$ident] = [
                'available' => true,
                'label'     => $this->GetMeteredSwitchTileLabel($ident),
                'value'     => (bool) $rawValue,
                'formatted' => $this->FormatMeteredSwitchTileValue($ident, $rawValue)
            ];
        }

        $values = [];
        $archiveID = $this->GetMeteredSwitchTileArchiveID();
        foreach ($this->GetMeteredSwitchTileMeasurementIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'label'     => $this->GetMeteredSwitchTileLabel($ident),
                'unit'      => $this->GetMeteredSwitchTileUnit($ident),
                'value'     => $rawValue,
                'formatted' => $this->FormatMeteredSwitchTileValue($ident, $rawValue),
                'archived'  => $this->IsMeteredSwitchTileValueArchived($variableID, $archiveID)
            ];
        }

        $data = [
            'type'   => 'meteredSwitch',
            'name'   => IPS_GetName($this->InstanceID),
            'switches' => $switches,
            'values' => $values
        ];

        if ($archiveData !== null) {
            $data['view'] = 'archive';
            $data['archive'] = $archiveData;
        }

        return $data;
    }

    /**
     * Liefert die relevanten Idents fuer die Kachel.
     */
    protected function GetMeteredSwitchTileIdents(): array
    {
        return array_values(array_unique(array_merge(
            $this->GetMeteredSwitchTileSwitchIdents(),
            $this->GetMeteredSwitchTileMeasurementIdents()
        )));
    }

    /**
     * Liefert die Messwerte, fuer die eine Verlaufseite sinnvoll ist.
     */
    private function GetMeteredSwitchTileArchiveIdents(): array
    {
        return $this->GetMeteredSwitchTileMeasurementIdents();
    }

    /**
     * Liefert alle vorhandenen Schaltkanaele der Kachel.
     */
    private function GetMeteredSwitchTileSwitchIdents(): array
    {
        $idents = [];
        $candidates = ['state', 'state_left', 'state_right'];

        for ($index = 1; $index <= 16; $index++) {
            $candidates[] = 'state_' . $index;
            $candidates[] = 'state_l' . $index;
            $candidates[] = 'state_left_' . $index;
            $candidates[] = 'state_right_' . $index;
            $candidates[] = 'state_left_l' . $index;
            $candidates[] = 'state_right_l' . $index;
        }

        foreach ($candidates as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }
            if (IPS_GetVariable($variableID)['VariableType'] === VARIABLETYPE_BOOLEAN) {
                $idents[] = $ident;
            }
        }

        return $idents;
    }

    /**
     * Prueft, ob mindestens ein Schaltkanal aus einer Zigbee2MQTT-switch-Gruppe kommt.
     */
    private function HasMeteredSwitchTileSwitchGroupCapability(): bool
    {
        $foundFeature = false;

        foreach ($this->GetMeteredSwitchTileSwitchIdents() as $ident) {
            $feature = $this->FindMeteredSwitchTileFeature($ident);
            if ($feature === null) {
                continue;
            }

            $foundFeature = true;
            if (($feature['group_type'] ?? '') === 'switch') {
                return true;
            }
        }

        return !$foundFeature;
    }

    /**
     * Liefert alle vorhandenen Messwert-Idents passend zu den Schaltkanaelen.
     */
    private function GetMeteredSwitchTileMeasurementIdents(): array
    {
        $idents = [];
        $suffixes = $this->GetMeteredSwitchTileChannelSuffixes();
        $metricIdents = ['energy', 'power', 'voltage', 'current'];

        foreach ($suffixes as $suffix) {
            foreach ($metricIdents as $metricIdent) {
                $ident = $metricIdent . $suffix;
                if ($this->GetObjectIDByIdent($ident) !== false) {
                    $idents[] = $ident;
                }
            }
        }

        foreach ($metricIdents as $ident) {
            if (!\in_array($ident, $idents, true) && $this->GetObjectIDByIdent($ident) !== false) {
                $idents[] = $ident;
            }
        }

        return $idents;
    }

    /**
     * Liefert die Kanal-Suffixe anhand der vorhandenen Schaltkanaele.
     */
    private function GetMeteredSwitchTileChannelSuffixes(): array
    {
        $suffixes = [];
        foreach ($this->GetMeteredSwitchTileSwitchIdents() as $ident) {
            $suffixes[] = $ident === 'state' ? '' : substr($ident, 5);
        }

        return array_values(array_unique($suffixes));
    }

    /**
     * Sucht ein Feature in den gespeicherten Exposes.
     */
    private function FindMeteredSwitchTileFeature(string $property): ?array
    {
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);

        foreach ($exposes as $expose) {
            $found = $this->FindMeteredSwitchTileFeatureRecursive($expose, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Rekursive Feature-Suche fuer verschachtelte Zigbee2MQTT-Exposes.
     */
    private function FindMeteredSwitchTileFeatureRecursive(array $feature, string $property, ?string $groupType = null): ?array
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

            $found = $this->FindMeteredSwitchTileFeatureRecursive($subFeature, $property, $currentGroupType);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Dekodiert Nutzdaten aus HTML-SDK-Aktionen.
     */
    private function DecodeMeteredSwitchTileRequest(mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }
        if (!\is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Ermittelt die Archive-Control-Instanz.
     */
    private function GetMeteredSwitchTileArchiveID(): int|false
    {
        $archiveModuleID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';

        if (\function_exists('IPS_GetInstanceListByModuleID')) {
            $archiveIDs = IPS_GetInstanceListByModuleID($archiveModuleID);
            if (\is_array($archiveIDs) && $archiveIDs !== []) {
                return (int) $archiveIDs[0];
            }
        }

        if (\function_exists('IPS_GetInstanceList') && \function_exists('IPS_GetInstance')) {
            foreach (IPS_GetInstanceList() as $instanceID) {
                try {
                    $instance = IPS_GetInstance((int) $instanceID);
                } catch (\Throwable) {
                    continue;
                }

                if (($instance['ModuleInfo']['ModuleID'] ?? '') === $archiveModuleID) {
                    return (int) $instanceID;
                }
            }
        }

        return false;
    }

    /**
     * Prueft, ob ein Wert im Archive Control geloggt oder graphisch sichtbar ist.
     */
    private function IsMeteredSwitchTileValueArchived(int $variableID, int|false $archiveID): bool
    {
        if ($archiveID === false) {
            return false;
        }

        if (\function_exists('AC_GetLoggingStatus')) {
            try {
                if ((bool) AC_GetLoggingStatus($archiveID, $variableID)) {
                    return true;
                }
            } catch (\Throwable) {
                // Fallback auf GraphStatus
            }
        }

        if (\function_exists('AC_GetGraphStatus')) {
            try {
                return (bool) AC_GetGraphStatus($archiveID, $variableID);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Baut die Archivdaten fuer die interne Verlaufseite.
     */
    private function BuildMeteredSwitchTileArchiveData(string $ident, string $range): array
    {
        $range = $this->NormalizeMeteredSwitchTileArchiveRange($range);
        $variableID = $this->GetObjectIDByIdent($ident);
        $archiveID = $this->GetMeteredSwitchTileArchiveID();

        $data = [
            'ident'     => $ident,
            'label'     => $this->GetMeteredSwitchTileLabel($ident),
            'unit'      => $this->GetMeteredSwitchTileUnit($ident),
            'range'     => $range,
            'available' => false,
            'points'    => [],
            'message'   => $this->Translate('No archive data')
        ];

        if ($variableID === false || $archiveID === false || !$this->IsMeteredSwitchTileValueArchived($variableID, $archiveID)) {
            return $data;
        }

        $endTime = time();
        $startTime = $endTime - $this->GetMeteredSwitchTileArchiveRangeSeconds($range);

        try {
            $loggedValues = AC_GetLoggedValues($archiveID, $variableID, $startTime, $endTime, 700);
        } catch (\Throwable) {
            return $data;
        }

        $points = [];
        foreach (array_reverse($loggedValues) as $loggedValue) {
            if (!isset($loggedValue['TimeStamp'], $loggedValue['Value']) || !\is_numeric($loggedValue['Value'])) {
                continue;
            }

            $points[] = [
                'time'  => (int) $loggedValue['TimeStamp'],
                'value' => (float) $loggedValue['Value']
            ];
        }

        $data['available'] = true;
        $data['points'] = $points;
        $data['message'] = $points === [] ? $this->Translate('No values in selected period') : '';

        return $data;
    }

    /**
     * Normalisiert den angeforderten Zeitraum.
     */
    private function NormalizeMeteredSwitchTileArchiveRange(string $range): string
    {
        return \in_array($range, ['day', 'week', 'month'], true) ? $range : 'day';
    }

    /**
     * Liefert die Zeitspanne fuer eine Archivabfrage.
     */
    private function GetMeteredSwitchTileArchiveRangeSeconds(string $range): int
    {
        return match ($range) {
            'week'  => 7 * 24 * 60 * 60,
            'month' => 30 * 24 * 60 * 60,
            default => 24 * 60 * 60
        };
    }

    /**
     * Liefert die Anzeigeeinheit fuer einen Messwert.
     */
    private function GetMeteredSwitchTileUnit(string $ident): string
    {
        return match ($this->GetMeteredSwitchTileBaseIdent($ident)) {
            'power'   => 'W',
            'energy'  => 'kWh',
            'voltage' => 'V',
            'current' => 'A',
            default   => ''
        };
    }

    /**
     * Liefert die Beschriftung fuer einen Messwert.
     */
    private function GetMeteredSwitchTileLabel(string $ident): string
    {
        $baseIdent = $this->GetMeteredSwitchTileBaseIdent($ident);
        $suffix = $this->GetMeteredSwitchTileLabelSuffix($ident, $baseIdent);

        $label = match ($baseIdent) {
            'state'   => 'Status',
            'power'   => 'Leistung',
            'energy'  => 'Energie',
            'voltage' => 'Spannung',
            'current' => 'Strom',
            default   => $ident
        };

        return $label . $suffix;
    }

    /**
     * Formatiert Messwerte fuer die Kachel.
     */
    private function FormatMeteredSwitchTileValue(string $ident, mixed $value): string
    {
        switch ($this->GetMeteredSwitchTileBaseIdent($ident)) {
            case 'state':
                return $value ? $this->Translate('On') : $this->Translate('Off');
            case 'power':
                return \sprintf('%.1f W', (float) $value);
            case 'energy':
                return \sprintf('%.2f kWh', (float) $value);
            case 'voltage':
                return \sprintf('%.1f V', (float) $value);
            case 'current':
                return \sprintf('%.2f A', (float) $value);
        }

        return (string) $value;
    }

    /**
     * Liefert den Basis-Ident ohne Kanal-Suffix.
     */
    private function GetMeteredSwitchTileBaseIdent(string $ident): string
    {
        foreach (['state', 'energy', 'power', 'voltage', 'current'] as $baseIdent) {
            if ($ident === $baseIdent || str_starts_with($ident, $baseIdent . '_')) {
                return $baseIdent;
            }
        }

        return $ident;
    }

    /**
     * Liefert eine lesbare Kanalnummer als Label-Suffix.
     */
    private function GetMeteredSwitchTileLabelSuffix(string $ident, string $baseIdent): string
    {
        if ($ident === $baseIdent) {
            return '';
        }

        $suffix = substr($ident, \strlen($baseIdent));
        if (preg_match('/^_(\d+)$/', $suffix, $matches)) {
            return ' ' . $matches[1];
        }

        if (preg_match('/^_l(\d+)$/i', $suffix, $matches)) {
            return ' L' . $matches[1];
        }

        if (preg_match('/^_(left|right)$/i', $suffix, $matches)) {
            return $matches[1] === 'left' ? ' Links' : ' Rechts';
        }

        if (preg_match('/^_(left|right)_l?(\d+)$/i', $suffix, $matches)) {
            $side = strtolower($matches[1]) === 'left' ? 'Links' : 'Rechts';
            return ' ' . $side . ' ' . $matches[2];
        }

        return ' ' . strtoupper(ltrim($suffix, '_'));
    }
}
