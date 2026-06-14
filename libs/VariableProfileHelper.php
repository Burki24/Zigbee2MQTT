<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          VariableProfileHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */

/**
 * Trait mit Hilfsfunktionen für Variablenprofile.
 */
trait VariableProfileHelper
{
    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ bool mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfileBooleanEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations): string
    {
        return $this->RegisterProfileEx(VARIABLETYPE_BOOLEAN, $Name, $Icon, $Prefix, $Suffix, $Associations);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ integer mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, int $MaxValue = -1, float $StepSize = 0): string
    {
        return $this->RegisterProfileEx(VARIABLETYPE_INTEGER, $Name, $Icon, $Prefix, $Suffix, $Associations, $MaxValue, $StepSize);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ float mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfileFloatEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0): string
    {
        return $this->RegisterProfileEx(VARIABLETYPE_FLOAT, $Name, $Icon, $Prefix, $Suffix, $Associations, $MaxValue, $StepSize, $Digits);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ string mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfileStringEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations): string
    {
        return $this->RegisterProfileEx(VARIABLETYPE_STRING, $Name, $Icon, $Prefix, $Suffix, $Associations);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ bool.
     *
     * @param string $Name   Name des Profils.
     * @param string $Icon   Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfileBoolean(string $Name, string $Icon, string $Prefix, string $Suffix): string
    {
        return $this->RegisterProfile(VARIABLETYPE_BOOLEAN, $Name, $Icon, $Prefix, $Suffix, 0, 0, 0);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ integer.
     *
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param int    $MinValue Minimaler Wert.
     * @param int    $MaxValue Maximaler wert.
     * @param float  $StepSize Schrittweite
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, int $MinValue, int $MaxValue, float $StepSize): string
    {
        return $this->RegisterProfile(VARIABLETYPE_INTEGER, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ float.
     *
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param float  $MinValue Minimaler Wert.
     * @param float  $MaxValue Maximaler wert.
     * @param float  $StepSize Schrittweite
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfileFloat(string $Name, string $Icon, string $Prefix, string $Suffix, float $MinValue, float $MaxValue, float $StepSize, int $Digits): string
    {
        return $this->RegisterProfile(VARIABLETYPE_FLOAT, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ VarType mit Assoziationen.
     *
     * @param int    $VarTyp       Typ der Variable
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param int|array $Associations Assoziationen der Werte als Array.
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfileEx(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, mixed $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0): string
    {
        if (is_int($Associations)) {
            return $this->RegisterProfile($VarTyp, $Name, $Icon, $Prefix, $Suffix, $Associations, $MaxValue, $StepSize, $Digits);
        }
        if ((count($Associations) === 0) || ($VarTyp === VARIABLETYPE_BOOLEAN) || ($VarTyp === VARIABLETYPE_STRING)) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinMax = array_column($Associations, 0);
            sort($MinMax);
            $MinValue = $MinMax[0];
            if ($MaxValue == -1) {
                $MaxValue = array_pop($MinMax);
            }
        }
        $translatedAssociations = [];
        foreach ($Associations as $Association) {
            $translatedAssociations[] = [
                'Value' => $Association[0],
                'Name'  => $this->Translate($Association[1]),
                'Icon'  => $Association[2],
                'Color' => $Association[3]
            ];
        }

        return $this->RegisterProfile(
            $VarTyp,
            $Name,
            $Icon,
            $Prefix,
            $Suffix,
            $MinValue,
            $MaxValue,
            $StepSize,
            $Digits,
            $translatedAssociations
        );
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ float.
     *
     * @param int    $VarTyp   Typ der Variable
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param float  $MinValue Minimaler Wert.
     * @param float  $MaxValue Maximaler wert.
     * @param float  $StepSize Schrittweite
     *
     * @param array $Associations Bereits übersetzte Profilassoziationen.
     *
     * @return string Tatsächlich verwendeter Profilname.
     */
    protected function RegisterProfile(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, float $MinValue, float $MaxValue, float $StepSize, int $Digits = 0, array $Associations = []): string
    {
        $definition = [
            'ProfileType'  => $VarTyp,
            'Icon'         => $Icon,
            'Prefix'       => $this->Translate($Prefix),
            'Suffix'       => $this->Translate($Suffix),
            'MinValue'     => $MinValue,
            'MaxValue'     => $MaxValue,
            'StepSize'     => $StepSize,
            'Digits'       => $Digits,
            'Associations' => array_values($Associations)
        ];
        $profileName = $this->ResolveCompatibleProfileName($Name, $definition);
        if (IPS_VariableProfileExists($profileName)) {
            return $profileName;
        }

        IPS_CreateVariableProfile($profileName, $VarTyp);
        IPS_SetVariableProfileIcon($profileName, $Icon);
        IPS_SetVariableProfileText($profileName, $definition['Prefix'], $definition['Suffix']);
        if (($VarTyp != VARIABLETYPE_BOOLEAN) && ($VarTyp != VARIABLETYPE_STRING)) {
            IPS_SetVariableProfileValues($profileName, $MinValue, $MaxValue, $StepSize);
        }
        if ($VarTyp == VARIABLETYPE_FLOAT) {
            IPS_SetVariableProfileDigits($profileName, $Digits);
        }
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation(
                $profileName,
                $Association['Value'],
                $Association['Name'],
                $Association['Icon'],
                $Association['Color']
            );
        }

        if ($profileName !== $Name) {
            $this->LogMessage(
                sprintf('Variable profile %s differs from the required definition. Created compatible profile %s.', $Name, $profileName),
                KL_WARNING
            );
        }

        return $profileName;
    }

    /**
     * Ermittelt einen vorhandenen kompatiblen Profilnamen oder einen freien,
     * deterministischen Alternativnamen.
     *
     * Bestehende Profile werden niemals verändert oder gelöscht. Stimmt das
     * gewünschte Profil nicht vollständig mit der vorhandenen Definition
     * überein, wird ein eigener Profilname aus der Soll-Definition abgeleitet.
     *
     * @param string $Name Gewünschter Profilname.
     * @param array $Definition Vollständige Soll-Definition.
     *
     * @return string Kompatibler oder freier Profilname.
     */
    private function ResolveCompatibleProfileName(string $Name, array $Definition): string
    {
        if (!IPS_VariableProfileExists($Name) || $this->ProfileMatchesDefinition(IPS_GetVariableProfile($Name), $Definition)) {
            return $Name;
        }

        $hash = substr(hash('sha256', serialize($Definition)), 0, 12);
        $attempt = 0;
        do {
            $suffix = '.Z2M.' . $hash . ($attempt === 0 ? '' : '.' . $attempt);
            $profileName = substr($Name, 0, max(1, 64 - strlen($suffix))) . $suffix;
            if (!IPS_VariableProfileExists($profileName)
                || $this->ProfileMatchesDefinition(IPS_GetVariableProfile($profileName), $Definition)
            ) {
                return $profileName;
            }
            ++$attempt;
        } while (true);
    }

    /**
     * Prüft, ob ein vorhandenes Profil vollständig der Soll-Definition entspricht.
     *
     * @param array $Profile Vorhandenes Symcon-Profil.
     * @param array $Definition Vollständige Soll-Definition.
     *
     * @return bool True bei vollständiger Übereinstimmung.
     */
    private function ProfileMatchesDefinition(array $Profile, array $Definition): bool
    {
        if ((int) ($Profile['ProfileType'] ?? -1) !== (int) $Definition['ProfileType']
            || (string) ($Profile['Icon'] ?? '') !== (string) $Definition['Icon']
            || (string) ($Profile['Prefix'] ?? '') !== (string) $Definition['Prefix']
            || (string) ($Profile['Suffix'] ?? '') !== (string) $Definition['Suffix']
            || !$this->ProfileNumbersMatch($Profile['MinValue'] ?? 0, $Definition['MinValue'])
            || !$this->ProfileNumbersMatch($Profile['MaxValue'] ?? 0, $Definition['MaxValue'])
            || !$this->ProfileNumbersMatch($Profile['StepSize'] ?? 0, $Definition['StepSize'])
            || (int) ($Profile['Digits'] ?? 0) !== (int) $Definition['Digits']
        ) {
            return false;
        }

        return $this->NormalizeProfileAssociations($Profile['Associations'] ?? [], (int) $Definition['ProfileType'])
            === $this->NormalizeProfileAssociations($Definition['Associations'], (int) $Definition['ProfileType']);
    }

    /**
     * Vergleicht numerische Profilwerte tolerant gegenüber int-/float-Typen.
     */
    private function ProfileNumbersMatch(mixed $Left, mixed $Right): bool
    {
        return abs((float) $Left - (float) $Right) < 0.000001;
    }

    /**
     * Normalisiert Profilassoziationen für einen stabilen Definitionsvergleich.
     *
     * @param array $Associations Symcon-Profilassoziationen.
     * @param int $ProfileType Symcon-Variablentyp des Profils.
     *
     * @return array Normalisierte Assoziationen.
     */
    private function NormalizeProfileAssociations(array $Associations, int $ProfileType): array
    {
        $normalized = [];
        $associationIndexes = [];
        foreach ($Associations as $Association) {
            $normalizedAssociation = [
                'Value' => match ($ProfileType) {
                    VARIABLETYPE_BOOLEAN => (bool) ($Association['Value'] ?? false),
                    VARIABLETYPE_INTEGER => (int) ($Association['Value'] ?? 0),
                    VARIABLETYPE_FLOAT   => (float) ($Association['Value'] ?? 0),
                    default              => (string) ($Association['Value'] ?? '')
                },
                'Name'  => (string) ($Association['Name'] ?? ''),
                'Icon'  => (string) ($Association['Icon'] ?? ''),
                'Color' => (int) ($Association['Color'] ?? 0)
            ];
            $valueKey = serialize($normalizedAssociation['Value']);
            if (isset($associationIndexes[$valueKey])) {
                // Symcon stores only one association per value; the last write wins.
                $normalized[$associationIndexes[$valueKey]] = $normalizedAssociation;
                continue;
            }
            $associationIndexes[$valueKey] = count($normalized);
            $normalized[] = $normalizedAssociation;
        }
        if ($ProfileType !== VARIABLETYPE_STRING) {
            usort(
                $normalized,
                static fn (array $Left, array $Right): int => $Left['Value'] <=> $Right['Value']
            );
        }

        return $normalized;
    }
}

/* @} */
