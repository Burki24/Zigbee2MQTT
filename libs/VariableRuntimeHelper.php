<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Kapselt die Symcon-Laufzeitoperationen fuer Modulvariablen.
 */
trait VariableRuntimeHelper
{
    protected function RegisterVariableBoolean(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->PrepareVariableRegistration($Ident);
        return parent::RegisterVariableBoolean($Ident, $Name, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableInteger(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->PrepareVariableRegistration($Ident);
        return parent::RegisterVariableInteger($Ident, $Name, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableFloat(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->PrepareVariableRegistration($Ident);
        return parent::RegisterVariableFloat($Ident, $Name, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableString(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->PrepareVariableRegistration($Ident);
        return parent::RegisterVariableString($Ident, $Name, $ProfileOrPresentation, $Position);
    }

    /**
     * Aktualisiert ausschliesslich die aktuell ausgewaehlte HTML-SDK-Kachel.
     *
     * Die Reihenfolge muss der Auswahl in Device::GetVisualizationTileDefinition()
     * entsprechen. Andernfalls kann eine weitere kompatible Kachel ihren anders
     * aufgebauten Datensatz an dieselbe Visualisierung senden und deren Zustand
     * ueberschreiben.
     */
    private function UpdateCustomTileValuesIfRelevant(string $ident): void
    {
        if ($this->ShouldForceSensorTile()) {
            $this->UpdateSensorTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseHeatingTile()) {
            $this->UpdateHeatingTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseMeteredSwitchTile()) {
            $this->UpdateMeteredSwitchTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseWindowHandleTile()) {
            $this->UpdateWindowHandleTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseSecurityTile()) {
            $this->UpdateSecurityTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseActionTile()) {
            $this->UpdateActionTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseSensorTile()) {
            $this->UpdateSensorTileValueIfRelevant($ident);
        }
    }

    /**
     * Setzt einen Variablenwert module-strict-konform.
     */
    private function SetModuleValue(string $ident, int $variableID, mixed $value, ?bool &$changed = null): bool
    {
        $changed = true;
        if ($this->IsModuleValueUnchanged($variableID, $value)) {
            $changed = false;
            return true;
        }

        if (\defined('PHPUNIT_TESTSUITE') && \constant('PHPUNIT_TESTSUITE')) {
            \SetValue($variableID, $value);
            return true;
        }

        set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            return parent::SetValue($ident, $value);
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Prüft, ob der gewünschte Wert bereits unveraendert in der Symcon-Variable steht.
     */
    private function IsModuleValueUnchanged(int $variableID, mixed $value): bool
    {
        \set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            $currentValue = \GetValue($variableID);
        } catch (\Throwable) {
            return false;
        } finally {
            \restore_error_handler();
        }

        if (\is_float($currentValue) || \is_float($value)) {
            return \abs((float) $currentValue - (float) $value) < 0.000000001;
        }

        return $currentValue === $value;
    }

    /**
     * IPSModuleStrict deklariert GetIDForIdent() als int. Für Existenzprüfungen
     * nutzen wir die globale Funktion, weil sie bei fehlendem Ident false liefern darf.
     */
    private function GetObjectIDByIdent(string $ident): int|false
    {
        return @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
    }

    /**
     * Entfernt verwaiste interne Variablenregistrierungen vor einer Neuanlage.
     *
     * Bei Modul-Updates kann Symcon noch eine alte Maintained-Variable kennen,
     * obwohl das Objekt bereits geloescht wurde. Ein RegisterVariable*-Aufruf
     * wuerde dann mit "Variable #... existiert nicht" abbrechen.
     */
    private function PrepareVariableRegistration(string $ident): void
    {
        if ($this->GetObjectIDByIdent($ident) !== false) {
            return;
        }

        set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            parent::UnregisterVariable($ident);
        } catch (\Throwable $exception) {
            $this->SendDebug(__FUNCTION__, 'Verwaiste Variablenregistrierung konnte nicht bereinigt werden: ' . $exception->getMessage(), 0);
        } finally {
            restore_error_handler();
        }
    }

}

