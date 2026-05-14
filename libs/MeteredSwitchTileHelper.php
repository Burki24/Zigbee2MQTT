<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer Schaltaktoren mit Messwerten.
 */
trait MeteredSwitchTileHelper
{
    /**
     * Prueft, ob die Mess-Schalter-Kachel aktiv verwendet werden soll.
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
     * Prueft, ob diese Instanz als Schaltaktor mit Messwerten dargestellt werden kann.
     */
    protected function HasMeteredSwitchTileCapabilities(): bool
    {
        if ($this->GetObjectIDByIdent('state') === false) {
            return false;
        }

        foreach (['power', 'energy', 'voltage', 'current'] as $ident) {
            if ($this->GetObjectIDByIdent($ident) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verarbeitet Aktionen der Metered-Switch-Kachel.
     */
    protected function HandleMeteredSwitchTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'MeteredSwitchTile.Toggle':
                $stateID = $this->GetObjectIDByIdent('state');
                if ($stateID === false) {
                    return true;
                }

                $this->RequestAction('state', !((bool) GetValue($stateID)));
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

        $this->UpdateMeteredSwitchTileValue();
    }

    /**
     * Baut die Datenstruktur fuer die HTML-Kachel.
     */
    protected function BuildMeteredSwitchTileData(?array $archiveData = null): array
    {
        $values = [];
        $archiveID = $this->GetMeteredSwitchTileArchiveID();
        foreach ($this->GetMeteredSwitchTileIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'value'     => $rawValue,
                'formatted' => $this->FormatMeteredSwitchTileValue($ident, $rawValue),
                'archived'  => $this->IsMeteredSwitchTileValueArchived($variableID, $archiveID)
            ];
        }

        $data = [
            'type'   => 'meteredSwitch',
            'name'   => IPS_GetName($this->InstanceID),
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
        return ['state', 'power', 'energy', 'voltage', 'current'];
    }

    /**
     * Liefert die Messwerte, fuer die eine Verlaufseite sinnvoll ist.
     */
    private function GetMeteredSwitchTileArchiveIdents(): array
    {
        return ['power', 'energy', 'voltage', 'current'];
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
        if (!\function_exists('IPS_GetInstanceListByModuleID')) {
            return false;
        }

        $archiveIDs = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        if (!\is_array($archiveIDs) || $archiveIDs === []) {
            return false;
        }

        return (int) $archiveIDs[0];
    }

    /**
     * Prueft, ob ein Wert im Archive Control geloggt wird.
     */
    private function IsMeteredSwitchTileValueArchived(int $variableID, int|false $archiveID): bool
    {
        if ($archiveID === false || !\function_exists('AC_GetLoggingStatus')) {
            return false;
        }

        try {
            return (bool) AC_GetLoggingStatus($archiveID, $variableID);
        } catch (\Throwable) {
            return false;
        }
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
        return match ($ident) {
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
        return match ($ident) {
            'power'   => 'Leistung',
            'energy'  => 'Energie',
            'voltage' => 'Spannung',
            'current' => 'Strom',
            default   => $ident
        };
    }

    /**
     * Formatiert Messwerte fuer die Kachel.
     */
    private function FormatMeteredSwitchTileValue(string $ident, mixed $value): string
    {
        switch ($ident) {
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
}
