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
            $differences = $this->FormatProfileDefinitionDifferences(
                $this->GetProfileDefinitionDifferences(IPS_GetVariableProfile($Name), $definition)
            );
            $this->LogMessage(
                sprintf(
                    'Variable profile %s differs from the required definition (%s). Created compatible profile %s.',
                    $Name,
                    $differences,
                    $profileName
                ),
                KL_WARNING
            );
        }

        return $profileName;
    }

    /**
     * Liefert die konkreten Abweichungen zwischen einem vorhandenen Profil und
     * einer benoetigten Profildefinition.
     *
     * @param array $Profile Vorhandenes Symcon-Profil.
     * @param array $Definition Vollstaendige Soll-Definition.
     *
     * @return array<string, array{existing: mixed, required: mixed}> Abweichungen nach Profilfeld.
     */
    protected function GetProfileDefinitionDifferences(array $Profile, array $Definition): array
    {
        $profileType = (int) ($Definition['ProfileType'] ?? $Profile['ProfileType'] ?? -1);
        $comparisons = [
            'ProfileType' => [
                'existing' => (int) ($Profile['ProfileType'] ?? -1),
                'required' => (int) ($Definition['ProfileType'] ?? -1)
            ],
            'Icon' => [
                'existing' => (string) ($Profile['Icon'] ?? ''),
                'required' => (string) ($Definition['Icon'] ?? '')
            ],
            'Prefix' => [
                'existing' => (string) ($Profile['Prefix'] ?? ''),
                'required' => (string) ($Definition['Prefix'] ?? '')
            ],
            'Suffix' => [
                'existing' => (string) ($Profile['Suffix'] ?? ''),
                'required' => (string) ($Definition['Suffix'] ?? '')
            ],
            'MinValue' => [
                'existing' => (float) ($Profile['MinValue'] ?? 0),
                'required' => (float) ($Definition['MinValue'] ?? 0)
            ],
            'MaxValue' => [
                'existing' => (float) ($Profile['MaxValue'] ?? 0),
                'required' => (float) ($Definition['MaxValue'] ?? 0)
            ],
            'StepSize' => [
                'existing' => (float) ($Profile['StepSize'] ?? 0),
                'required' => (float) ($Definition['StepSize'] ?? 0)
            ],
            'Digits' => [
                'existing' => (int) ($Profile['Digits'] ?? 0),
                'required' => (int) ($Definition['Digits'] ?? 0)
            ],
            'Associations' => [
                'existing' => $this->NormalizeProfileAssociations($Profile['Associations'] ?? [], $profileType),
                'required' => $this->NormalizeProfileAssociations($Definition['Associations'] ?? [], $profileType)
            ]
        ];

        $differences = [];
        foreach ($comparisons as $field => $comparison) {
            $matches = \in_array($field, ['MinValue', 'MaxValue', 'StepSize'], true)
                ? $this->ProfileNumbersMatch($comparison['existing'], $comparison['required'])
                : $comparison['existing'] === $comparison['required'];
            if (!$matches) {
                $differences[$field] = $comparison;
            }
        }

        return $differences;
    }

    /**
     * Formatiert Profilabweichungen fuer Protokoll und Diagnoseformular.
     *
     * @param array<string, array{existing: mixed, required: mixed}> $Differences Profilabweichungen.
     */
    protected function FormatProfileDefinitionDifferences(array $Differences): string
    {
        if ($Differences === []) {
            return $this->Translate('No current deviations');
        }

        $labels = [
            'ProfileType'  => $this->Translate('Profile type'),
            'Icon'         => $this->Translate('Icon'),
            'Prefix'       => $this->Translate('Prefix'),
            'Suffix'       => $this->Translate('Suffix'),
            'MinValue'     => $this->Translate('Minimum'),
            'MaxValue'     => $this->Translate('Maximum'),
            'StepSize'     => $this->Translate('Step size'),
            'Digits'       => $this->Translate('Digits'),
            'Associations' => $this->Translate('Associations')
        ];
        $formatted = [];
        foreach ($Differences as $field => $difference) {
            $existing = $difference['existing'] ?? null;
            $required = $difference['required'] ?? null;
            if ($field === 'Associations') {
                $formatted[] = sprintf(
                    '%s: %s',
                    $labels[$field],
                    $this->FormatProfileAssociationDifferences(
                        \is_array($existing) ? $existing : [],
                        \is_array($required) ? $required : []
                    )
                );
                continue;
            }

            $formatted[] = sprintf(
                '%s: %s -> %s',
                $labels[$field] ?? $field,
                $this->FormatProfileDifferenceValue($existing),
                $this->FormatProfileDifferenceValue($required)
            );
        }

        return implode('; ', $formatted);
    }

    /**
     * Baut eine globale, rein lesende Diagnose der konfliktbedingt erzeugten
     * kompatiblen Z2M-Profile auf.
     *
     * @return array<int, array<string, int|string>> Diagnosezeilen.
     */
    protected function BuildVariableProfileDiagnostics(): array
    {
        $profileNames = IPS_GetVariableProfileList();
        sort($profileNames, SORT_NATURAL | SORT_FLAG_CASE);
        $profileDefinitions = [];
        foreach ($profileNames as $profileName) {
            $profileDefinitions[$profileName] = IPS_GetVariableProfile($profileName);
        }

        $usage = $this->BuildVariableProfileUsage();
        $rows = [];
        foreach ($profileNames as $profileName) {
            if (preg_match('/^(.*)\.Z2M\.[a-f0-9]{12}(?:\.\d+)?$/i', $profileName, $matches) !== 1) {
                continue;
            }

            $canonicalName = $this->ResolveCanonicalProfileNameForDiagnostics(
                (string) $matches[1],
                $profileNames
            );
            $profile = $profileDefinitions[$profileName];
            $fingerprint = $this->BuildProfileDefinitionFingerprint($profile);
            $differences = IPS_VariableProfileExists($canonicalName)
                ? $this->GetProfileDefinitionDifferences($profileDefinitions[$canonicalName], $profile)
                : [];

            $rows[] = [
                'canonical_profile'  => $canonicalName,
                'compatible_profile' => $profileName,
                'deviations'         => IPS_VariableProfileExists($canonicalName)
                    ? $this->FormatProfileDefinitionDifferences($differences)
                    : $this->Translate('Canonical profile missing'),
                'usage_count'            => $usage[$profileName] ?? 0,
                'definition_fingerprint' => $fingerprint,
                'identical_count'        => 1
            ];
        }

        $fingerprintCounts = [];
        foreach ($rows as $row) {
            $key = $row['canonical_profile'] . "\0" . $row['definition_fingerprint'];
            $fingerprintCounts[$key] = ($fingerprintCounts[$key] ?? 0) + 1;
        }
        foreach ($rows as &$row) {
            $key = $row['canonical_profile'] . "\0" . $row['definition_fingerprint'];
            $identicalCount = $fingerprintCounts[$key] ?? 1;
            $canonicalName = (string) $row['canonical_profile'];
            if (isset($profileDefinitions[$canonicalName])
                && $this->BuildProfileDefinitionFingerprint($profileDefinitions[$canonicalName])
                    === $row['definition_fingerprint']
            ) {
                ++$identicalCount;
            }
            $row['identical_count'] = $identicalCount;
        }
        unset($row);

        usort(
            $rows,
            static fn (array $Left, array $Right): int => strnatcasecmp(
                $Left['canonical_profile'] . "\0" . $Left['compatible_profile'],
                $Right['canonical_profile'] . "\0" . $Right['compatible_profile']
            )
        );

        return $rows;
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

        $existingCompatibleProfile = $this->FindExistingCompatibleProfileName($Name, $Definition);
        if ($existingCompatibleProfile !== null) {
            return $existingCompatibleProfile;
        }

        $hash = substr($this->BuildProfileDefinitionFingerprint($Definition), 0, 12);
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
     * Sucht ein bereits vorhandenes konfliktbedingt erzeugtes Profil, dessen
     * gespeicherte Definition der benoetigten Definition entspricht.
     */
    private function FindExistingCompatibleProfileName(string $Name, array $Definition): ?string
    {
        $profileNames = IPS_GetVariableProfileList();
        sort($profileNames, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($profileNames as $profileName) {
            if (preg_match('/^(.*)\.Z2M\.[a-f0-9]{12}(?:\.\d+)?$/i', $profileName, $matches) !== 1) {
                continue;
            }

            $baseName = (string) $matches[1];
            $belongsToCanonicalProfile = $baseName === $Name
                || (strlen($profileName) === 64 && str_starts_with($Name, $baseName));
            if (!$belongsToCanonicalProfile
                || !$this->ProfileMatchesDefinition(IPS_GetVariableProfile($profileName), $Definition)
            ) {
                continue;
            }

            return $profileName;
        }

        return null;
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
        return $this->GetProfileDefinitionDifferences($Profile, $Definition) === [];
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

    /**
     * Formatiert einen einzelnen Profilwert kompakt.
     */
    private function FormatProfileDifferenceValue(mixed $Value): string
    {
        if (\is_string($Value)) {
            return $Value === '' ? '""' : '"' . $Value . '"';
        }
        if (\is_bool($Value)) {
            return $Value ? 'true' : 'false';
        }
        if (\is_float($Value) && floor($Value) === $Value) {
            return (string) (int) $Value;
        }
        if (\is_scalar($Value) || $Value === null) {
            return (string) ($Value ?? 'null');
        }

        return json_encode($Value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * Beschreibt hinzugefuegte, entfernte und geaenderte Assoziationen.
     */
    private function FormatProfileAssociationDifferences(array $Existing, array $Required): string
    {
        $existingByValue = [];
        foreach ($Existing as $association) {
            $existingByValue[serialize($association['Value'] ?? null)] = $association;
        }
        $requiredByValue = [];
        foreach ($Required as $association) {
            $requiredByValue[serialize($association['Value'] ?? null)] = $association;
        }

        $changes = [];
        foreach ($requiredByValue as $valueKey => $association) {
            if (!isset($existingByValue[$valueKey])) {
                $changes[] = '+' . $this->FormatProfileAssociation($association);
                continue;
            }
            if ($existingByValue[$valueKey] !== $association) {
                $changes[] = $this->FormatProfileAssociation($existingByValue[$valueKey])
                    . ' -> ' . $this->FormatProfileAssociation($association);
            }
        }
        foreach ($existingByValue as $valueKey => $association) {
            if (!isset($requiredByValue[$valueKey])) {
                $changes[] = '-' . $this->FormatProfileAssociation($association);
            }
        }

        return $changes === [] ? $this->Translate('Different order or representation') : implode(', ', $changes);
    }

    /**
     * Formatiert eine Profilassoziation fuer die Diagnose.
     */
    private function FormatProfileAssociation(array $Association): string
    {
        $parts = [
            $this->FormatProfileDifferenceValue($Association['Value'] ?? null)
                . '=' . $this->FormatProfileDifferenceValue($Association['Name'] ?? '')
        ];
        if (($Association['Icon'] ?? '') !== '') {
            $parts[] = 'icon=' . $this->FormatProfileDifferenceValue($Association['Icon']);
        }
        if ((int) ($Association['Color'] ?? 0) !== 0) {
            $parts[] = 'color=' . (string) (int) $Association['Color'];
        }

        return implode(' ', $parts);
    }

    /**
     * Zaehlt die Variablen, welche ein Profil regulaer oder benutzerdefiniert verwenden.
     *
     * @return array<string, int> Nutzung nach Profilname.
     */
    private function BuildVariableProfileUsage(): array
    {
        $usage = [];
        foreach (IPS_GetVariableList() as $variableID) {
            try {
                $variable = IPS_GetVariable($variableID);
            } catch (\Throwable) {
                continue;
            }

            foreach (array_unique([
                (string) ($variable['VariableProfile'] ?? ''),
                (string) ($variable['VariableCustomProfile'] ?? '')
            ]) as $profileName) {
                if ($profileName !== '') {
                    $usage[$profileName] = ($usage[$profileName] ?? 0) + 1;
                }
            }
        }

        return $usage;
    }

    /**
     * Ermittelt den kanonischen Profilnamen fuer ein kompatibles Hash-Profil.
     */
    private function ResolveCanonicalProfileNameForDiagnostics(string $BaseName, array $ProfileNames): string
    {
        if (IPS_VariableProfileExists($BaseName)) {
            return $BaseName;
        }

        $candidates = array_values(array_filter(
            $ProfileNames,
            static fn (string $profileName): bool => !str_contains($profileName, '.Z2M.')
                && str_starts_with($profileName, $BaseName)
        ));

        return \count($candidates) === 1 ? $candidates[0] : $BaseName;
    }

    /**
     * Erzeugt einen stabilen Fingerabdruck einer gespeicherten Profildefinition.
     */
    private function BuildProfileDefinitionFingerprint(array $Profile): string
    {
        $profileType = (int) ($Profile['ProfileType'] ?? -1);
        $definition = [
            'ProfileType'  => $profileType,
            'Icon'         => (string) ($Profile['Icon'] ?? ''),
            'Prefix'       => (string) ($Profile['Prefix'] ?? ''),
            'Suffix'       => (string) ($Profile['Suffix'] ?? ''),
            'MinValue'     => (float) ($Profile['MinValue'] ?? 0),
            'MaxValue'     => (float) ($Profile['MaxValue'] ?? 0),
            'StepSize'     => (float) ($Profile['StepSize'] ?? 0),
            'Digits'       => (int) ($Profile['Digits'] ?? 0),
            'Associations' => $this->NormalizeProfileAssociations($Profile['Associations'] ?? [], $profileType)
        ];

        return hash('sha256', serialize($definition));
    }
}

/* @} */
