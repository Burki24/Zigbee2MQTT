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

    private function createProfileHelper(): object
    {
        return new class() {
            use \Zigbee2MQTT\VariableProfileHelper;

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

            protected function Translate(string $text): string
            {
                return $text;
            }

            protected function LogMessage(string $message, int $type): bool
            {
                return true;
            }
        };
    }
}
