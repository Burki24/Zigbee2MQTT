<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

/**
 * Tests conflict-safe variable profile registration.
 */
class VariableProfileHelperTest extends DumpInclude
{
    public function testWrongProfileTypeIsPreservedAndCompatibleProfileIsCreated(): void
    {
        $helper = $this->createProfileHelper();
        IPS_CreateVariableProfile('Z2M.ProfileConflict', VARIABLETYPE_BOOLEAN);
        IPS_SetVariableProfileText('Z2M.ProfileConflict', 'User ', '');
        $existingProfile = IPS_GetVariableProfile('Z2M.ProfileConflict');

        $profileName = $helper->registerIntegerProfile('Z2M.ProfileConflict', 0, 100, 5);

        $this->assertNotSame('Z2M.ProfileConflict', $profileName);
        $this->assertSame($existingProfile, IPS_GetVariableProfile('Z2M.ProfileConflict'));
        $this->assertTrue(IPS_VariableProfileExists($profileName));
        $this->assertSame(VARIABLETYPE_INTEGER, IPS_GetVariableProfile($profileName)['ProfileType']);
        $this->assertSame(100.0, IPS_GetVariableProfile($profileName)['MaxValue']);
        $this->assertStringContainsString('Profile type: 0 -> 1', $helper->logs[0] ?? '');
        $this->assertStringContainsString('Maximum: 0 -> 100', $helper->logs[0] ?? '');
    }

    public function testConflictingAssociationsArePreservedAndCompatibleProfileIsCreated(): void
    {
        $helper = $this->createProfileHelper();
        IPS_CreateVariableProfile('Z2M.AssociationConflict', VARIABLETYPE_STRING);
        IPS_SetVariableProfileAssociation('Z2M.AssociationConflict', 'legacy', 'Legacy', '', 0);
        $existingProfile = IPS_GetVariableProfile('Z2M.AssociationConflict');

        $profileName = $helper->registerStringProfile('Z2M.AssociationConflict', [
            ['new', 'New', '', 0x00FF00]
        ]);

        $this->assertNotSame('Z2M.AssociationConflict', $profileName);
        $this->assertSame($existingProfile, IPS_GetVariableProfile('Z2M.AssociationConflict'));
        $this->assertSame(
            [['Value' => 'new', 'Name' => 'New', 'Icon' => '', 'Color' => 0x00FF00]],
            IPS_GetVariableProfile($profileName)['Associations']
        );
    }

    public function testMatchingProfileIsReusedWithoutCreatingAnotherProfile(): void
    {
        $helper = $this->createProfileHelper();
        $associations = [
            [2, 'Two', '', 0x00FF00],
            [1, 'One', '', 0xFF0000]
        ];

        $firstProfileName = $helper->registerIntegerAssociationProfile('Z2M.ExactProfile', $associations);
        $profileCount = count(IPS_GetVariableProfileList());
        $secondProfileName = $helper->registerIntegerAssociationProfile('Z2M.ExactProfile', array_reverse($associations));

        $this->assertSame($firstProfileName, $secondProfileName);
        $this->assertCount($profileCount, IPS_GetVariableProfileList());
    }

    public function testDuplicateAssociationValuesDoNotCreateRepeatedCompatibleProfiles(): void
    {
        $helper = $this->createProfileHelper();
        $associations = [
            [153, 'Coolest', '', -1],
            [250, 'Cool', '', -1],
            [370, 'Neutral', '', -1],
            [454, 'Warm', '', -1],
            [454, 'Warmest', '', -1]
        ];

        $firstProfileName = $helper->registerIntegerAssociationProfile('Z2M.Color_Temp_153_454_Presets', $associations);
        $profileCount = count(IPS_GetVariableProfileList());
        $secondProfileName = $helper->registerIntegerAssociationProfile('Z2M.Color_Temp_153_454_Presets', $associations);

        $this->assertSame($firstProfileName, $secondProfileName);
        $this->assertCount($profileCount, IPS_GetVariableProfileList());
        $this->assertSame(
            [
                ['Value' => 153, 'Name' => 'Coolest', 'Icon' => '', 'Color' => -1],
                ['Value' => 250, 'Name' => 'Cool', 'Icon' => '', 'Color' => -1],
                ['Value' => 370, 'Name' => 'Neutral', 'Icon' => '', 'Color' => -1],
                ['Value' => 454, 'Name' => 'Warmest', 'Icon' => '', 'Color' => -1]
            ],
            IPS_GetVariableProfile($firstProfileName)['Associations']
        );
    }

    public function testExistingSemanticallyMatchingCompatibleProfileIsReused(): void
    {
        $helper = $this->createProfileHelper();
        IPS_CreateVariableProfile('Z2M.SemanticConflict', VARIABLETYPE_INTEGER);
        IPS_SetVariableProfileValues('Z2M.SemanticConflict', 0, 100, 1);

        IPS_CreateVariableProfile('Z2M.SemanticConflict.Z2M.aaaaaaaaaaaa', VARIABLETYPE_INTEGER);
        IPS_SetVariableProfileValues('Z2M.SemanticConflict.Z2M.aaaaaaaaaaaa', 153, 454, 0);
        IPS_SetVariableProfileAssociation('Z2M.SemanticConflict.Z2M.aaaaaaaaaaaa', 153, 'Coolest', '', -1);
        IPS_SetVariableProfileAssociation('Z2M.SemanticConflict.Z2M.aaaaaaaaaaaa', 454, 'Warmest', '', -1);
        $profileCount = count(IPS_GetVariableProfileList());

        $profileName = $helper->registerIntegerAssociationProfile('Z2M.SemanticConflict', [
            [153, 'Unused duplicate', '', -1],
            [153, 'Coolest', '', -1],
            [454, 'Warm', '', -1],
            [454, 'Warmest', '', -1]
        ]);

        $this->assertSame('Z2M.SemanticConflict.Z2M.aaaaaaaaaaaa', $profileName);
        $this->assertCount($profileCount, IPS_GetVariableProfileList());
    }

    public function testProfileDiagnosticsListCurrentDifferencesUsageAndIdenticalProfiles(): void
    {
        $helper = $this->createProfileHelper();
        IPS_CreateVariableProfile('Z2M.DiagnosticConflict', VARIABLETYPE_INTEGER);
        IPS_SetVariableProfileValues('Z2M.DiagnosticConflict', 0, 100, 1);

        $compatibleProfile = $helper->registerIntegerProfile('Z2M.DiagnosticConflict', 0, 200, 1);
        $duplicateProfile = $compatibleProfile . '.1';
        $this->copyProfile($compatibleProfile, $duplicateProfile);

        $variableID = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetVariableCustomProfile($variableID, $compatibleProfile);

        $rows = $helper->getProfileDiagnostics();
        $rowsByName = array_column($rows, null, 'compatible_profile');

        $this->assertArrayHasKey($compatibleProfile, $rowsByName);
        $this->assertArrayHasKey($duplicateProfile, $rowsByName);
        $this->assertStringContainsString('Maximum: 100 -> 200', $rowsByName[$compatibleProfile]['deviations']);
        $this->assertSame(1, $rowsByName[$compatibleProfile]['usage_count']);
        $this->assertSame(0, $rowsByName[$duplicateProfile]['usage_count']);
        $this->assertSame(2, $rowsByName[$compatibleProfile]['identical_count']);
        $this->assertSame(
            $rowsByName[$compatibleProfile]['definition_fingerprint'],
            $rowsByName[$duplicateProfile]['definition_fingerprint']
        );
    }

    public function testProfileDiagnosticsIncludeIdenticalCanonicalProfileInCount(): void
    {
        $helper = $this->createProfileHelper();
        IPS_CreateVariableProfile('Z2M.IdenticalCanonical', VARIABLETYPE_INTEGER);
        IPS_SetVariableProfileValues('Z2M.IdenticalCanonical', 0, 100, 1);

        $compatibleProfile = $helper->registerIntegerProfile('Z2M.IdenticalCanonical', 0, 200, 1);
        $duplicateProfile = $compatibleProfile . '.1';
        $this->copyProfile($compatibleProfile, $duplicateProfile);
        IPS_SetVariableProfileValues('Z2M.IdenticalCanonical', 0, 200, 1);

        $rowsByName = array_column($helper->getProfileDiagnostics(), null, 'compatible_profile');

        $this->assertSame(3, $rowsByName[$compatibleProfile]['identical_count']);
        $this->assertSame(3, $rowsByName[$duplicateProfile]['identical_count']);
        $this->assertSame('No current deviations', $rowsByName[$compatibleProfile]['deviations']);
    }

    private function createProfileHelper(): object
    {
        return new class() {
            use \Zigbee2MQTT\VariableProfileHelper;

            public array $logs = [];

            public function registerIntegerProfile(string $name, int $minValue, int $maxValue, float $stepSize): string
            {
                return $this->RegisterProfileInteger($name, '', '', '', $minValue, $maxValue, $stepSize);
            }

            public function registerStringProfile(string $name, array $associations): string
            {
                return $this->RegisterProfileStringEx($name, '', '', '', $associations);
            }

            public function registerIntegerAssociationProfile(string $name, array $associations): string
            {
                return $this->RegisterProfileIntegerEx($name, '', '', '', $associations);
            }

            public function getProfileDiagnostics(): array
            {
                return $this->BuildVariableProfileDiagnostics();
            }

            protected function Translate(string $text): string
            {
                return $text;
            }

            protected function LogMessage(string $message, int $type): bool
            {
                $this->logs[] = $message;

                return true;
            }
        };
    }

    private function copyProfile(string $sourceName, string $targetName): void
    {
        $source = IPS_GetVariableProfile($sourceName);
        IPS_CreateVariableProfile($targetName, $source['ProfileType']);
        IPS_SetVariableProfileIcon($targetName, $source['Icon']);
        IPS_SetVariableProfileText($targetName, $source['Prefix'], $source['Suffix']);
        if (!\in_array($source['ProfileType'], [VARIABLETYPE_BOOLEAN, VARIABLETYPE_STRING], true)) {
            IPS_SetVariableProfileValues($targetName, $source['MinValue'], $source['MaxValue'], $source['StepSize']);
        }
        if ($source['ProfileType'] === VARIABLETYPE_FLOAT) {
            IPS_SetVariableProfileDigits($targetName, $source['Digits']);
        }
        foreach ($source['Associations'] as $association) {
            IPS_SetVariableProfileAssociation(
                $targetName,
                $association['Value'],
                $association['Name'],
                $association['Icon'],
                $association['Color']
            );
        }
    }
}
