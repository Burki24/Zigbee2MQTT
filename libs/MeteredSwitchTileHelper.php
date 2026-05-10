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
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateMeteredSwitchTileValue(): void
    {
        if (!$this->ShouldUseMeteredSwitchTile()) {
            return;
        }

        $this->UpdateVisualizationValue(json_encode(
            $this->BuildMeteredSwitchTileData(),
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
    protected function BuildMeteredSwitchTileData(): array
    {
        $values = [];
        foreach ($this->GetMeteredSwitchTileIdents() as $ident) {
            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'value'     => $rawValue,
                'formatted' => $this->FormatMeteredSwitchTileValue($ident, $rawValue)
            ];
        }

        return [
            'type'   => 'meteredSwitch',
            'name'   => IPS_GetName($this->InstanceID),
            'values' => $values
        ];
    }

    /**
     * Liefert die relevanten Idents fuer die Kachel.
     */
    protected function GetMeteredSwitchTileIdents(): array
    {
        return ['state', 'power', 'energy', 'voltage', 'current'];
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
