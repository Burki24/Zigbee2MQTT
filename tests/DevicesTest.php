<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

/**
 * Tests device expose mapping, variable handling and visualization behaviour.
 */
class DevicesTest extends DumpInclude
{
    public function testDynamicVariableMaintenanceValuesUseGlobalTranslations(): void
    {
        $iid = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $device = IPS\InstanceManager::getInstanceInterface($iid);

        $this->assertSame('Ja', $device->Translate('Yes'));
        $this->assertSame('Nein', $device->Translate('No'));
        $this->assertSame('Geschützt', $device->Translate('Protected'));
        $this->assertSame('Löschen', $device->Translate('Delete'));
    }

    public function testDeviceConfigurationFormContainsOwnerScopedVariableMaintenance(): void
    {
        $iid = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $form = json_decode(IPS_GetConfigurationForm($iid), true);

        $this->assertNotNull($this->findFormItemByName($form, 'LocalVariableMaintenance'));
        $this->assertNotNull($this->findFormItemByName($form, 'LocalStaleVariableClearCandidates'));
        $this->assertNotNull($this->findFormItemByName($form, 'LocalStaleVariableDeleteWarning'));

        $expertTools = array_values(array_filter(
            $form['actions'],
            static fn (array $item): bool => ($item['caption'] ?? '') === 'Expert tools'
        ))[0];
        $expertItemKeys = array_map(
            static fn (array $item): string => (string) ($item['name'] ?? $item['type'] ?? ''),
            $expertTools['items']
        );

        $this->assertArrayNotHasKey('width', $expertTools);
        $this->assertSame(
            ['AdvancedDeviceRemovalSettings', 'LocalVariableMaintenance'],
            array_values(array_intersect(
                $expertItemKeys,
                ['AdvancedDeviceRemovalSettings', 'LocalVariableMaintenance']
            ))
        );
        $this->assertNotContains('TestCenter', $expertItemKeys);
        $this->assertSame(
            1,
            count(array_filter(
                $form['actions'],
                static fn (array $item): bool => ($item['type'] ?? '') === 'TestCenter'
            ))
        );
    }

    public function testDeviceInformationRefreshIsTopLevelAction(): void
    {
        $form = json_decode(file_get_contents(__DIR__ . '/../Device/form.json'), true);

        $this->assertSame('RefreshDeviceInfoButton', $form['elements'][2]['name']);
        $this->assertSame('Refresh device information', $form['elements'][2]['caption']);
    }

    public function testPersistedDeviceIconIsMigratedToSharedFileCache(): void
    {
        $iid = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $model = 'Icon cache test ' . $iid;
        $imageRaw = $this->getMinimalPng();
        $dataUri = 'data:image/png;base64,' . base64_encode($imageRaw);
        $cacheFile = rtrim(IPS_GetKernelDir(), '\\/')
            . DIRECTORY_SEPARATOR . 'user'
            . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT'
            . DIRECTORY_SEPARATOR . 'icons'
            . DIRECTORY_SEPARATOR . hash('sha256', $model) . '.png';

        $this->writeStubAttributeString($iid, 'Model', $model);
        $this->writeStubAttributeString($iid, 'Icon', $dataUri);

        IPS_ApplyChanges($iid);

        $this->assertSame('', $this->readStubAttributeString($iid, 'Icon'));
        $this->assertFileExists($cacheFile);
        $this->assertSame($imageRaw, file_get_contents($cacheFile));

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $deviceImage = $this->findFormItemByName($form, 'DeviceImage');
        $this->assertNotNull($deviceImage);
        $this->assertSame($dataUri, $deviceImage['image']);

        @unlink($cacheFile);
    }

    public function testDeviceIconValidationRejectsInvalidAndOversizedContent(): void
    {
        $iid = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $device = IPS\InstanceManager::getInstanceInterface($iid);
        $method = new ReflectionMethod($device, 'IsValidDeviceIcon');
        $reflection = new ReflectionClass($device);

        $this->assertSame(5, $reflection->getConstant('ICON_DOWNLOAD_TIMEOUT_SECONDS'));
        $this->assertSame(2 * 1024 * 1024, $reflection->getConstant('ICON_DOWNLOAD_MAX_BYTES'));
        $this->assertTrue($method->invoke($device, $this->getMinimalPng()));
        $this->assertFalse($method->invoke($device, '<html>Not an image</html>'));
        $this->assertFalse($method->invoke($device, "\x89PNG\r\n\x1a\n" . str_repeat('x', (2 * 1024 * 1024) + 1)));
    }

    public function testInvalidPersistedDeviceIconIsNotMigratedToSharedFileCache(): void
    {
        $iid = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $model = 'Invalid icon cache test ' . $iid;
        $dataUri = 'data:image/png;base64,' . base64_encode('not-a-png');
        $cacheFile = rtrim(IPS_GetKernelDir(), '\\/')
            . DIRECTORY_SEPARATOR . 'user'
            . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT'
            . DIRECTORY_SEPARATOR . 'icons'
            . DIRECTORY_SEPARATOR . hash('sha256', $model) . '.png';

        $this->writeStubAttributeString($iid, 'Model', $model);
        $this->writeStubAttributeString($iid, 'Icon', $dataUri);

        IPS_ApplyChanges($iid);

        $this->assertSame($dataUri, $this->readStubAttributeString($iid, 'Icon'));
        $this->assertFileDoesNotExist($cacheFile);
        $device = IPS\InstanceManager::getInstanceInterface($iid);
        $method = new ReflectionMethod($device, 'ReadDeviceIconForForm');
        $this->assertSame('', $method->invoke($device));
    }

    public function testTRV06()
    {
        [$iid,$Debug] = $this->createTestInstance('TRV06.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        // schedule_* Variablen fehlen in $Debug['Childs'] Neues Z2M_Debug benötigt
        $OffsetDebugChild = +7;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function test701721()
    {
        [$iid,$Debug] = $this->createTestInstance('701721.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        // schedule_* Variablen fehlen in $Debug['Childs'] Neues Z2M_Debug benötigt
        $OffsetDebugChild = 0;
        //$Debug['Childs'] ist leider unvollständig. Neues Z2M_Debug benötigt
        //$this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug ('.count($Debug['Childs']).') und Erzeugte Variablen ('.count(IPS_GetChildrenIDs($iid)).') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"heating"', $html);
        $this->assertStringContainsString('"presets":[18,20,22]', $html);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'VisualizationSettings');
        $status = $this->findFormItemByName($form, 'VisualizationStatus');
        $this->assertNotNull($status);
        $this->assertStringContainsString('Aktive Visualisierung:', $status['caption']);
        $this->assertStringContainsString('Heizungs-Kachel', $status['caption']);
        $this->assertFormItemVisible($form, 'DisableHeatingTile');
        $this->assertFormItemVisible($form, 'HeatingTilePresetSettings');
        $this->assertFormItemVisible($form, 'HeatingTilePreset1');
        $this->assertFormItemVisible($form, 'HeatingTilePreset2');
        $this->assertFormItemVisible($form, 'HeatingTilePreset3');

        IPS_SetProperty($iid, 'HeatingTilePreset1', 16.0);
        IPS_SetProperty($iid, 'HeatingTilePreset2', 19.5);
        IPS_SetProperty($iid, 'HeatingTilePreset3', 21.0);
        IPS_ApplyChanges($iid);

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"presets":[16,19.5,21]', $html);
    }

    public function testTS130F()
    {
        [$iid,$Debug] = $this->createTestInstance('TS130F.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertSame('', $html);

        $positionID = IPS_GetObjectIDByIdent('position', $iid);
        $this->assertNotFalse($positionID);
        $variable = IPS_GetVariable($positionID);
        $this->assertSame('', $variable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_SHUTTER, $variable['VariablePresentation']['PRESENTATION'] ?? null);
        $this->assertSame(100.0, $variable['VariablePresentation']['OPEN_OUTSIDE_VALUE'] ?? null);
        $this->assertSame(0.0, $variable['VariablePresentation']['CLOSE_INSIDE_VALUE'] ?? null);

        IPS_SetVariableCustomPresentation($positionID, [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN'          => 10,
            'MAX'          => 90
        ]);
        IPS_ApplyChanges($iid);

        $variable = IPS_GetVariable($positionID);
        $this->assertSame(VARIABLE_PRESENTATION_SLIDER, $variable['VariableCustomPresentation']['PRESENTATION'] ?? null);
        $this->assertSame(10, $variable['VariableCustomPresentation']['MIN'] ?? null);
        $this->assertSame(90, $variable['VariableCustomPresentation']['MAX'] ?? null);

        $variable = IPS_GetVariable($positionID);
        $this->assertSame(VARIABLE_PRESENTATION_SLIDER, $variable['VariableCustomPresentation']['PRESENTATION'] ?? null);
        $this->assertSame(10, $variable['VariableCustomPresentation']['MIN'] ?? null);
        $this->assertSame(90, $variable['VariableCustomPresentation']['MAX'] ?? null);
    }

    public function testCoverStateActionKeepsEnumValue(): void
    {
        $device = $this->createDeviceActionTestDouble();
        $device->setExposesForTest([
            [
                'type'     => 'cover',
                'features' => [
                    [
                        'name'     => 'state',
                        'access'   => 2,
                        'type'     => 'enum',
                        'property' => 'state',
                        'values'   => ['OPEN', 'CLOSE', 'STOP']
                    ]
                ]
            ]
        ]);

        $device->RequestAction('state', 'OPEN');
        $this->assertSame('/Wohnbereich/Beschattung/Terrassenfenster/set', $device->sentTopic);
        $this->assertSame(['state' => 'OPEN'], $device->sentPayload);

        $device->RequestAction('state', 'STOP');
        $this->assertSame(['state' => 'STOP'], $device->sentPayload);

        $device->sentPayload = [];
        $device->RequestAction('state', 'UNKNOWN');
        $this->assertSame([], $device->sentPayload);
    }

    public function testLanguageNeutralNumericValuesAreNotReportedAsMissingTranslations(): void
    {
        $instanceID = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $device = new class($instanceID) extends Zigbee2MQTTDevice {
            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }
        };
        $device->Create();

        $method = new ReflectionMethod($device, 'isLanguageNeutralTranslationValue');

        foreach (['1', '1x', '2x', '3.5', '4,5'] as $value) {
            $this->assertTrue($method->invoke($device, $value, 'value'));
        }
        $this->assertFalse($method->invoke($device, '1x', 'label'));
        $this->assertFalse($method->invoke($device, 'Double Pulse', 'value'));
    }

    public function testUpdateInfoShowsReadablePopupOnFailure(): void
    {
        $device = new class(990006) extends Zigbee2MQTTDevice {
            public array $updatedFields = [];
            public bool $updateDeviceInfoCalled = false;

            protected function UpdateDeviceInfo(): bool
            {
                $this->updateDeviceInfoCalled = true;
                return false;
            }

            protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
            {
                $this->updatedFields[$Field][$Parameter] = $Value;
                return true;
            }

            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }
        };
        $device->Create();

        $device->RequestAction('UpdateInfo', true);

        $this->assertTrue($device->updateDeviceInfoCalled);
        $this->assertSame(true, $device->updatedFields['DeviceInfoRequestError']['visible']);
    }

    public function testUpdateInfoShowsReadablePopupOnSuccess(): void
    {
        $device = new class(990007) extends Zigbee2MQTTDevice {
            public array $updatedFields = [];
            public bool $updateDeviceInfoCalled = false;

            protected function UpdateDeviceInfo(): bool
            {
                $this->updateDeviceInfoCalled = true;
                return true;
            }

            protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
            {
                $this->updatedFields[$Field][$Parameter] = $Value;
                return true;
            }

            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }
        };
        $device->Create();

        $device->RequestAction('UpdateInfo', true);

        $this->assertTrue($device->updateDeviceInfoCalled);
        $this->assertSame(true, $device->updatedFields['DeviceInfoRequestSuccess']['visible']);
        $this->assertArrayNotHasKey('DeviceInfoRequestError', $device->updatedFields);
    }

    public function testDetectedIEEEIsOnlyInsertedIntoFormUntilUserApplies(): void
    {
        $device = new class(990008) extends Zigbee2MQTTDevice {
            public array $updatedFields = [];

            protected function LoadDeviceInfo(): array
            {
                return [
                    'ieeeAddr' => '0x00124b0000000001',
                    'exposes'  => []
                ];
            }

            protected function mapExposesToVariables(array $exposes): void
            {
            }

            protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
            {
                $this->updatedFields[$Field][$Parameter] = $Value;
                return true;
            }

            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }
        };
        $device->Create();

        $device->RequestAction('UpdateInfo', true);

        $this->assertSame('', $device->GetProperty('IEEE'));
        $this->assertSame('0x00124b0000000001', $device->updatedFields['IEEE']['value']);
        $this->assertSame(true, $device->updatedFields['DeviceInfoRequestSuccess']['visible']);
    }

    public function testDeviceDoesNotProgrammaticallyPersistDetectedIEEE(): void
    {
        $source = file_get_contents(__DIR__ . '/../Device/module.php');
        $this->assertStringNotContainsString('IPS_SetProperty(', $source);
        $this->assertStringNotContainsString('IPS_ApplyChanges(', $source);
        $this->assertStringNotContainsString('PendingIEEE', $source);
    }

    public function testDeviceMaintenanceIsShownForConfiguredDevice(): void
    {
        [$iid] = $this->createTestInstance('MixedLightSensor.json');

        $form = json_decode(IPS_GetConfigurationForm($iid), true);

        $this->assertFormItemVisible($form, 'AdvancedDeviceSettings');
        $this->assertFormItemVisible($form, 'DeviceMaintenanceSettings');
        $this->assertFormItemVisible($form, 'AdvancedDeviceRemovalSettings');
    }

    public function testDeviceInterviewRequiresConfirmationAndUsesLongMaintenanceTimeout(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();

        $device->RequestAction('RequestDeviceInterview', true);

        $this->assertSame(true, $device->updatedFields['DeviceInterviewWarning']['visible']);
        $this->assertSame('', $device->sentTopic);

        $device->bridgeFunctionResult = true;
        $device->RequestAction('ConfirmDeviceInterview', true);

        $this->assertSame(false, $device->updatedFields['DeviceInterviewWarning']['visible']);
        $this->assertSame('InterviewDevice', $device->calledBridgeFunction);
        $this->assertSame(['Flur/Beleuchtung/Unten'], $device->calledBridgeArguments);
        $this->assertSame(true, $device->updatedFields['DeviceMaintenanceMessage']['visible']);
        $this->assertSame('Device interview successful', $device->updatedFields['DeviceMaintenanceMessageTitle']['caption']);
    }

    public function testDeviceConfigureShowsReadableZigbee2MQTTError(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->bridgeFunctionResult = false;

        $device->RequestAction('ConfirmDeviceConfigure', true);

        $this->assertSame('ConfigureDevice', $device->calledBridgeFunction);
        $this->assertSame(['Flur/Beleuchtung/Unten'], $device->calledBridgeArguments);
        $this->assertSame('Device configuration failed', $device->updatedFields['DeviceMaintenanceMessageTitle']['caption']);
        $this->assertStringContainsString(
            'Zigbee2MQTT did not complete the request.',
            $device->updatedFields['DeviceMaintenanceMessageText']['caption']
        );
    }

    public function testDeviceRemovalRequiresConfirmationAndUsesSelectedMode(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->bridgeFunctionResult = true;

        $device->RequestAction('RequestDeviceRemoval', true);
        $this->assertSame(true, $device->updatedFields['DeviceRemovalWarning']['visible']);

        $device->RequestAction('ConfirmDeviceRemoval', true);
        $this->assertSame(false, $device->updatedFields['DeviceRemovalWarning']['visible']);
        $this->assertSame('RemoveDevice', $device->calledBridgeFunction);
        $this->assertSame(['Flur/Beleuchtung/Unten', false, false], $device->calledBridgeArguments);
        $this->assertSame('Device removed', $device->updatedFields['DeviceMaintenanceMessageTitle']['caption']);

        $device->RequestAction('RequestForceDeviceRemoval', true);
        $this->assertSame(true, $device->updatedFields['ForceDeviceRemovalWarning']['visible']);

        $device->RequestAction('ConfirmForceDeviceRemoval', true);
        $this->assertSame(false, $device->updatedFields['ForceDeviceRemovalWarning']['visible']);
        $this->assertSame('RemoveDevice', $device->calledBridgeFunction);
        $this->assertSame(['Flur/Beleuchtung/Unten', true, false], $device->calledBridgeArguments);
        $this->assertSame('Device force removed', $device->updatedFields['DeviceMaintenanceMessageTitle']['caption']);

        $device->RequestAction('RequestBlockDeviceRemoval', true);
        $this->assertSame(true, $device->updatedFields['BlockDeviceRemovalWarning']['visible']);

        $device->RequestAction('ConfirmBlockDeviceRemoval', true);
        $this->assertSame(false, $device->updatedFields['BlockDeviceRemovalWarning']['visible']);
        $this->assertSame('RemoveDevice', $device->calledBridgeFunction);
        $this->assertSame(['Flur/Beleuchtung/Unten', false, true], $device->calledBridgeArguments);
        $this->assertSame('Device removed and blocked', $device->updatedFields['DeviceMaintenanceMessageTitle']['caption']);
    }

    public function testDeviceRemovalShowsReadableZigbee2MQTTError(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->bridgeFunctionResult = false;

        $device->RequestAction('ConfirmDeviceRemoval', true);

        $this->assertSame('Device removal failed', $device->updatedFields['DeviceMaintenanceMessageTitle']['caption']);
        $this->assertStringContainsString(
            'Zigbee2MQTT did not remove the device.',
            $device->updatedFields['DeviceMaintenanceMessageText']['caption']
        );
    }

    public function testSwitchStateActionStillMapsBooleanToOnOff(): void
    {
        $device = $this->createDeviceActionTestDouble();
        $stateID = $device->registerBooleanVariableForTest('state');
        $device->setExposesForTest([
            [
                'type'     => 'switch',
                'features' => [
                    [
                        'name'      => 'state',
                        'access'    => 7,
                        'type'      => 'binary',
                        'property'  => 'state',
                        'value_on'  => 'ON',
                        'value_off' => 'OFF'
                    ]
                ]
            ]
        ]);

        $device->RequestAction('state', true);
        $this->assertSame(['state' => 'ON'], $device->sentPayload);
        $this->assertTrue(GetValue($stateID));

        $device->RequestAction('state', false);
        $this->assertSame(['state' => 'OFF'], $device->sentPayload);
        $this->assertFalse(GetValue($stateID));
    }

    public function testCommandRejectsInvalidJsonPayloadWithoutTypeError(): void
    {
        $device = $this->createDeviceActionTestDouble();
        $notices = [];

        set_error_handler(static function (int $severity, string $message) use (&$notices): bool
        {
            if ($severity === E_USER_NOTICE) {
                $notices[] = $message;
                return true;
            }

            return false;
        }, E_USER_NOTICE);

        try {
            $result = $device->Command('set', '{invalid-json');
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result);
        $this->assertSame('', $device->sentTopic);
        $this->assertSame([], $device->sentPayload);
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('JSON', $notices[0]);
    }

    public function testColorTemperatureActionRequiresExposeSupport(): void
    {
        $device = $this->createDeviceActionTestDouble();
        $device->setExposesForTest([
            [
                'type'     => 'light',
                'features' => [
                    [
                        'name'      => 'state',
                        'access'    => 7,
                        'type'      => 'binary',
                        'property'  => 'state',
                        'value_on'  => 'ON',
                        'value_off' => 'OFF'
                    ],
                    [
                        'name'      => 'brightness',
                        'access'    => 7,
                        'type'      => 'numeric',
                        'property'  => 'brightness',
                        'value_min' => 0,
                        'value_max' => 254
                    ]
                ]
            ]
        ]);

        $device->RequestAction('color_temp_kelvin', 3000);
        $this->assertSame([], $device->sentPayload);
    }

    public function testColorTransitionRequiresNativeColorExpose(): void
    {
        $device = $this->createDeviceActionTestDouble();
        $device->setExposesForTest([
            [
                'type'     => 'light',
                'features' => [
                    [
                        'name'      => 'color_temp',
                        'access'    => 7,
                        'type'      => 'numeric',
                        'property'  => 'color_temp',
                        'value_min' => 153,
                        'value_max' => 500
                    ]
                ]
            ]
        ]);

        $this->assertFalse($device->SetColorExt(0xFF0000, 2));
        $this->assertSame([], $device->sentPayload);

        $device->setExposesForTest([
            [
                'type'     => 'light',
                'features' => [
                    [
                        'name'       => 'color_xy',
                        'access'     => 7,
                        'type'       => 'composite',
                        'property'   => 'color',
                        'color_mode' => 'xy'
                    ]
                ]
            ]
        ]);
        $device->setColorModeForTest('XY');

        $this->assertTrue($device->SetColorExt(0xFF0000, 2));
        $this->assertArrayHasKey('color', $device->sentPayload);
        $this->assertSame(2, $device->sentPayload['transition']);
    }

    public function testWHD02()
    {
        [$iid,$Debug] = $this->createTestInstance('WHD02.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"meteredSwitch"', $html);
        $this->assertStringContainsString('"switches":{"state"', $html);
        $this->assertStringContainsString('"values":[]', $html);
    }

    public function testDeletedVariableIsNotRecreatedAndCanBeRestored()
    {
        [$iid,$Debug] = $this->createTestInstance('BMCT-SLZ.json');
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $topic = $Debug['Config']['MQTTBaseTopic'] . '/' . $Debug['Config']['MQTTTopic'];

        $powerID = IPS_GetObjectIDByIdent('power', $iid);
        $this->assertNotFalse($powerID);
        IPS_DeleteVariable($powerID);

        $interface->ReceiveData(self::buildMqttRequest($topic, ['power' => 12.3]));
        $this->assertFalse(@IPS_GetObjectIDByIdent('power', $iid), 'Deleted variable must not be recreated automatically.');

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $list = $this->findFormItemByName($form, 'VariableSelectionList');
        $this->assertNotNull($list);
        $row = $this->findVariableSelectionRow($list['values'], 'power');
        $this->assertNotNull($row);
        $this->assertSame('Gelöscht', $row['state']);
        $this->assertSame('Anlegen', $row['action']);

        IPS_RequestAction($iid, 'ToggleVariableCreation', 'power');
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('power', $iid), 'Re-enabled variable should be created again.');
    }

    public function testVariableSelectionCreatesNumericVariableWithoutExposeName(): void
    {
        [$iid] = $this->createTestInstance('RTCGQ01LM.json');
        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        $catalog['dewpoint'] = [
            'ident'     => 'dewpoint',
            'property'  => 'dewpoint',
            'label'     => 'Dewpoint',
            'source'    => 'payload',
            'type'      => 'numeric',
            'created'   => false,
            'lastValue' => 10.5,
            'feature'   => [
                'property' => 'dewpoint',
                'type'     => 'numeric'
            ]
        ];
        $this->writeStubAttributeArray($iid, 'VariableCatalog', $catalog);

        IPS_RequestAction($iid, 'ToggleVariableCreation', 'dewpoint');

        $dewpointID = @IPS_GetObjectIDByIdent('dewpoint', $iid);
        $this->assertNotFalse($dewpointID);
        $this->assertSame('Taupunkt', IPS_GetName($dewpointID));
        $dewpointVariable = IPS_GetVariable($dewpointID);
        $this->assertSame(VARIABLETYPE_FLOAT, $dewpointVariable['VariableType']);
        $this->assertSame('', $dewpointVariable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $dewpointVariable['VariablePresentation']['PRESENTATION'] ?? null);
        $this->assertSame(' °C', $dewpointVariable['VariablePresentation']['SUFFIX'] ?? null);
    }

    public function testVariableSelectionCreatesPayloadOnlyDewpointWithTemperaturePresentation(): void
    {
        [$iid] = $this->createTestInstance('RTCGQ01LM.json');
        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        $catalog['dewpoint'] = [
            'ident'     => 'dewpoint',
            'property'  => 'dewpoint',
            'label'     => 'Dewpoint',
            'source'    => 'payload',
            'type'      => 'numeric',
            'created'   => false,
            'lastValue' => 10.5
        ];
        $this->writeStubAttributeArray($iid, 'VariableCatalog', $catalog);

        IPS_RequestAction($iid, 'ToggleVariableCreation', 'dewpoint');

        $dewpointID = @IPS_GetObjectIDByIdent('dewpoint', $iid);
        $this->assertNotFalse($dewpointID);
        $this->assertSame('Taupunkt', IPS_GetName($dewpointID));
        $this->assertSame(VARIABLETYPE_FLOAT, IPS_GetVariable($dewpointID)['VariableType']);
        $this->assertSame('', IPS_GetVariable($dewpointID)['VariableProfile']);
    }

    public function testVariableSelectionCreatesPayloadOnlySoilMoistureWithPercentagePresentation(): void
    {
        [$iid] = $this->createTestInstance('RTCGQ01LM.json');
        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        $catalog['soil_moisture'] = [
            'ident'     => 'soil_moisture',
            'property'  => 'soil_moisture',
            'label'     => 'Soil moisture',
            'source'    => 'payload',
            'type'      => 'numeric',
            'created'   => false,
            'lastValue' => 5.0,
            'feature'   => [
                'property' => 'soil_moisture',
                'type'     => 'numeric'
            ]
        ];
        $this->writeStubAttributeArray($iid, 'VariableCatalog', $catalog);

        IPS_RequestAction($iid, 'ToggleVariableCreation', 'soil_moisture');

        $soilMoistureID = @IPS_GetObjectIDByIdent('soil_moisture', $iid);
        $this->assertNotFalse($soilMoistureID);
        $soilMoistureVariable = IPS_GetVariable($soilMoistureID);
        $this->assertSame(VARIABLETYPE_FLOAT, $soilMoistureVariable['VariableType']);
        $this->assertSame('', $soilMoistureVariable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $soilMoistureVariable['VariablePresentation']['PRESENTATION'] ?? null);
        $this->assertSame(' %', $soilMoistureVariable['VariablePresentation']['SUFFIX'] ?? null);
        $this->assertTrue($soilMoistureVariable['VariablePresentation']['PERCENTAGE'] ?? false);
        $this->assertSame(0.0, $soilMoistureVariable['VariablePresentation']['MIN'] ?? null);
        $this->assertSame(100.0, $soilMoistureVariable['VariablePresentation']['MAX'] ?? null);
    }

    public function testReceiveDataIgnoresPayloadFromDifferentDeviceTopic(): void
    {
        [$iid, $debug] = $this->createTestInstance('RTCGQ01LM.json');
        $interface = IPS\InstanceManager::getInstanceInterface($iid);

        $interface->ReceiveData(self::buildMqttRequest(
            $debug['Config']['MQTTBaseTopic'] . '/foreign-device',
            [
                'countdown_l1' => 0,
                'countdown_l2' => 0
            ]
        ));

        $this->assertFalse(@IPS_GetObjectIDByIdent('countdown_l1', $iid));
        $this->assertFalse(@IPS_GetObjectIDByIdent('countdown_l2', $iid));
        $latestPayload = self::getExportDebugData($iid)['LatestPayload'];
        $this->assertArrayNotHasKey('countdown_l1', $latestPayload);
        $this->assertArrayNotHasKey('countdown_l2', $latestPayload);
    }

    public function testVariableSelectionCreatesBinaryAndEnumVariablesWithIncompleteFeatureIdentity(): void
    {
        [$iid] = $this->createTestInstance('RTCGQ01LM.json');
        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        $catalog['generated_binary'] = [
            'ident'     => 'generated_binary',
            'property'  => 'generated_binary',
            'label'     => 'Generated Binary',
            'source'    => 'payload',
            'type'      => 'binary',
            'created'   => false,
            'lastValue' => 'LOCK',
            'feature'   => [
                'property'  => 'generated_binary',
                'type'      => 'binary',
                'value_on'  => 'LOCK',
                'value_off' => 'UNLOCK'
            ]
        ];
        $catalog['generated_enum'] = [
            'ident'     => 'generated_enum',
            'property'  => 'generated_enum',
            'label'     => 'Generated Enum',
            'source'    => 'payload',
            'type'      => 'enum',
            'created'   => false,
            'lastValue' => 'local',
            'feature'   => [
                'name'   => 'generated_enum',
                'type'   => 'enum',
                'values' => ['local', 'remote']
            ]
        ];
        $catalog['generated_writable_enum'] = [
            'ident'     => 'generated_writable_enum',
            'property'  => 'generated_writable_enum',
            'label'     => 'Generated Writable Enum',
            'source'    => 'payload',
            'type'      => 'enum',
            'created'   => false,
            'lastValue' => 'local',
            'feature'   => [
                'name'   => 'generated_writable_enum',
                'type'   => 'enum',
                'access' => 7,
                'values' => ['local', 'remote']
            ]
        ];
        $this->writeStubAttributeArray($iid, 'VariableCatalog', $catalog);

        IPS_RequestAction($iid, 'ToggleVariableCreation', 'generated_binary');
        IPS_RequestAction($iid, 'ToggleVariableCreation', 'generated_enum');
        IPS_RequestAction($iid, 'ToggleVariableCreation', 'generated_writable_enum');

        $binaryID = @IPS_GetObjectIDByIdent('generated_binary', $iid);
        $enumID = @IPS_GetObjectIDByIdent('generated_enum', $iid);
        $writableEnumID = @IPS_GetObjectIDByIdent('generated_writable_enum', $iid);
        $this->assertNotFalse($binaryID);
        $this->assertNotFalse($enumID);
        $this->assertNotFalse($writableEnumID);
        $binaryVariable = IPS_GetVariable($binaryID);
        $this->assertSame('', $binaryVariable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $binaryVariable['VariablePresentation']['PRESENTATION'] ?? null);
        $enumVariable = IPS_GetVariable($enumID);
        $this->assertSame('', $enumVariable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $enumVariable['VariablePresentation']['PRESENTATION'] ?? null);
        $this->assertFalse(HasAction($enumID));
        $writableEnumVariable = IPS_GetVariable($writableEnumID);
        $this->assertSame('', $writableEnumVariable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_ENUMERATION, $writableEnumVariable['VariablePresentation']['PRESENTATION'] ?? null);
        $this->assertTrue(HasAction($writableEnumID));
    }

    public function testVariableSelectionRefreshRemovesHistoricalEntriesWithoutDeletingVariables(): void
    {
        [$iid, $Debug] = $this->createTestInstance('BMCT-SLZ.json');
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $topic = $Debug['Config']['MQTTBaseTopic'] . '/' . $Debug['Config']['MQTTTopic'];
        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        $catalog['stale_payload'] = [
            'ident'    => 'stale_payload',
            'property' => 'stale_payload',
            'label'    => 'Stale Payload',
            'source'   => 'payload',
            'type'     => 'numeric',
            'created'  => false
        ];
        $catalog['stale_existing'] = [
            'ident'    => 'stale_existing',
            'property' => 'stale_existing',
            'label'    => 'Stale Existing',
            'source'   => 'payload',
            'type'     => 'numeric',
            'created'  => true
        ];
        $catalog['update__progress'] = [
            'ident'    => 'update__progress',
            'property' => 'update__progress',
            'label'    => 'Update Progress',
            'source'   => 'payload',
            'type'     => 'numeric',
            'created'  => false
        ];
        $catalog['update__historic'] = [
            'ident'    => 'update__historic',
            'property' => 'update__historic',
            'label'    => 'Historic Update Value',
            'source'   => 'payload',
            'type'     => 'numeric',
            'created'  => false
        ];
        $this->writeStubAttributeArray($iid, 'VariableCatalog', $catalog);
        $this->writeStubAttributeArray($iid, 'DisabledVariables', ['stale_existing']);
        $staleVariableID = IPS_CreateVariable(VARIABLETYPE_FLOAT);
        IPS_SetParent($staleVariableID, $iid);
        IPS_SetIdent($staleVariableID, 'stale_existing');
        $interface->ReceiveData(self::buildMqttRequest($topic, ['update' => ['progress' => 12.5]]));

        IPS_RequestAction($iid, 'RefreshVariableSelection', true);

        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        $this->assertArrayNotHasKey('stale_payload', $catalog);
        $this->assertArrayNotHasKey('stale_existing', $catalog);
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('stale_existing', $iid));
        $this->assertSame([], $this->readStubAttributeArray($iid, 'DisabledVariables'));
        $this->assertArrayHasKey('update__progress', $catalog);
        $this->assertArrayNotHasKey('update__historic', $catalog);
        $this->assertArrayHasKey('power', $catalog);
    }

    public function testVariableSelectionRefreshKeepsPersistentOTAMetadataOnlyForOTACapableDevices(): void
    {
        [$iid, $Debug] = $this->createTestInstance('BMCT-SLZ.json');
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $topic = $Debug['Config']['MQTTBaseTopic'] . '/' . $Debug['Config']['MQTTTopic'];
        $this->writeStubAttributeBoolean($iid, 'DeviceSupportsOTA', true);
        $interface->ReceiveData(self::buildMqttRequest($topic, ['power' => 1.5]));

        IPS_RequestAction($iid, 'RefreshVariableSelection', true);

        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        $this->assertArrayHasKey('update__installed_version', $catalog);
        $this->assertArrayHasKey('update__latest_version', $catalog);
        $this->assertArrayHasKey('update__state', $catalog);
        $this->assertArrayNotHasKey('update__progress', $catalog);
        $this->assertArrayNotHasKey('update__remaining', $catalog);
    }

    public function testMissingNewSchemaValuesAreIgnoredDuringModuleUpdateWindow(): void
    {
        [$iid, $Debug] = $this->createTestInstance('RTCGQ01LM.json');
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $topic = $Debug['Config']['MQTTBaseTopic'] . '/' . $Debug['Config']['MQTTTopic'];

        $this->removeStubProperties($iid, [
            'DisableMeteredSwitchTile',
            'DisableHeatingTile',
            'DisableSecurityTile',
            'DisableWindowHandleTile',
            'DisableActionTile',
            'UseSensorTile',
            'TemperaturePresentationFallbackMin',
            'TemperaturePresentationFallbackMax',
            'ColorTemperaturePresentationMin',
            'ColorTemperaturePresentationMax',
            'HeatingTilePreset1',
            'HeatingTilePreset2',
            'HeatingTilePreset3'
        ]);
        $this->removeStubAttributes($iid, [
            'VariableCatalog',
            'DisabledVariables',
            'DeletedVariables',
            'DeviceSupportsOTA'
        ]);

        $payload = $Debug['LastPayload'];
        $payload['exposes'] = $Debug['Exposes'];
        $interface->ReceiveData(self::buildMqttRequest($topic, $payload));

        $this->assertIsString(IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile());
        $this->assertIsArray(json_decode(IPS_GetConfigurationForm($iid), true));
    }

    public function testCustomTileTemplatesUseInheritedThemeColors(): void
    {
        $templates = glob(dirname(__DIR__) . '/libs/Visualization/tiles/*_tile.html');
        $this->assertIsArray($templates);
        $this->assertNotSame([], $templates);

        foreach ($templates as $template) {
            $html = file_get_contents($template);
            $this->assertStringContainsString('__THEME_SUPPORT__', $html, basename($template));
            $this->assertStringNotContainsString('--text: #111111', $html, basename($template));
            $this->assertStringNotContainsString('--muted: #667085', $html, basename($template));
            $this->assertStringNotContainsString('background: transparent !important', $html, basename($template));
            $this->assertStringNotContainsString('background-color: transparent !important', $html, basename($template));
            $this->assertStringNotContainsString('font-family: system-ui', $html, basename($template));
            $this->assertDoesNotMatchRegularExpression('/font-family\s*:/', $html, basename($template));
            $this->assertDoesNotMatchRegularExpression('/font\s*:(?!\s*inherit\s*;)/', $html, basename($template));
        }

        [$iid] = $this->createTestInstance('WHD02.json');
        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('--z2m-font-family: "Roboto", "Noto", sans-serif', $html);
        $this->assertStringContainsString('font-family: var(--z2m-font-family)', $html);
        $this->assertStringContainsString('--text: currentColor', $html);
        $this->assertStringContainsString('--z2m-font-normal', $html);
        $this->assertStringContainsString('font-size: var(--z2m-font-normal)', $html);
        $this->assertStringNotContainsString('__THEME_SUPPORT__', $html);
    }

    public function testTS0203ContactTile()
    {
        [$iid] = $this->createTestInstance('TS0203_contact.json');

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"security"', $html);
        $this->assertStringContainsString('"primary":{"ident":"contact"', $html);
        $this->assertStringContainsString('"tamper"', $html);
        $this->assertStringContainsString('"level":"safe"', $html);
        $this->assertStringContainsString('Geschlossen', $html);
    }

    public function testTRVZB()
    {
        [$iid,$Debug] = $this->createTestInstance('TRVZB.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testTS0601_thermostat()
    {
        [$iid,$Debug] = $this->createTestInstance('TS0601_thermostat.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        //$Debug['LastPayload'] ist leider unvollständig. Neues Z2M_Debug benötigt
        //$this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload ('.self::count_recursive($Debug['LastPayload']) + $OffestLastPayload.') und Erzeugte Variablen ('.count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs.') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $calibrationID = IPS_GetObjectIDByIdent('local_temperature_calibration', $iid);
        $this->assertNotFalse($calibrationID);
        $presentation = IPS_GetVariable($calibrationID)['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_SLIDER, $presentation['PRESENTATION'] ?? null);
        $this->assertSame(-9.0, $presentation['MIN'] ?? null);
        $this->assertSame(9.0, $presentation['MAX'] ?? null);
        $this->assertSame(0.5, $presentation['STEP_SIZE'] ?? null);

        IPS\VariableManager::setVariablePresentation((int) $calibrationID, [
            'PRESENTATION' => VARIABLE_PRESENTATION_LEGACY,
            'PROFILE'      => 'Z2M.local_temperature_calibration_-9_9'
        ]);
        IPS_ApplyChanges($iid);

        $presentation = IPS_GetVariable($calibrationID)['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_SLIDER, $presentation['PRESENTATION'] ?? null);
        $this->assertSame(-9.0, $presentation['MIN'] ?? null);
        $this->assertSame(9.0, $presentation['MAX'] ?? null);
        $this->assertSame(0.5, $presentation['STEP_SIZE'] ?? null);
    }

    public function testRTCGQ01LM()
    {
        [$iid,$Debug] = $this->createTestInstance('RTCGQ01LM.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"sensor"', $html);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $status = $this->findFormItemByName($form, 'VisualizationStatus');
        $this->assertNotNull($status);
        $this->assertStringContainsString('Sensor-Kachel', $status['caption']);
    }

    public function testSensorTileTreatsSoilMoistureLikeHumidity(): void
    {
        [$iid] = $this->createTestInstance('RTCGQ01LM.json');
        $this->writeStubAttributeArray($iid, 'Exposes', [
            [
                'name'     => 'soil_moisture',
                'label'    => 'Soil moisture',
                'access'   => 5,
                'type'     => 'numeric',
                'property' => 'soil_moisture'
            ]
        ]);

        $soilMoistureID = IPS_CreateVariable(VARIABLETYPE_FLOAT);
        IPS_SetParent($soilMoistureID, $iid);
        IPS_SetIdent($soilMoistureID, 'soil_moisture');
        IPS_SetName($soilMoistureID, 'Bodenfeuchtigkeit');
        SetValue($soilMoistureID, 5.0);

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"soil_moisture":{"type":"numeric","unit":"%"', $html);
        $this->assertStringContainsString('"soil_moisture":{"available":true,"value":5,"formatted":"5 %"}', $html);
    }

    public function testMixedLightSensorKeepsStandardVisualization()
    {
        [$iid] = $this->createTestInstance('MixedLightSensor.json');

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertSame('', $html);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'VisualizationSettings');
        $this->assertFormItemVisible($form, 'UseSensorTile');
        $status = $this->findFormItemByName($form, 'VisualizationStatus');
        $this->assertNotNull($status);
        $this->assertStringContainsString('Standard-Visualisierung', $status['caption']);

        IPS_SetProperty($iid, 'UseSensorTile', true);
        IPS_ApplyChanges($iid);

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"sensor"', $html);
    }

    public function testTunableWhiteLightGetsDerivedWhiteColorVariable(): void
    {
        [$iid, $Debug] = $this->createTestInstance('TunableWhiteLight.json');
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $topic = $Debug['Config']['MQTTBaseTopic'] . '/' . $Debug['Config']['MQTTTopic'];

        $colorID = IPS_GetObjectIDByIdent('color', $iid);
        $this->assertNotFalse($colorID);
        $variable = IPS_GetVariable($colorID);
        $this->assertSame('', $variable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_COLOR, $variable['VariablePresentation']['PRESENTATION'] ?? null);
        $this->assertSame(0, $variable['VariablePresentation']['ENCODING'] ?? null);
        $this->assertSame(1, $variable['VariablePresentation']['COLOR_SPACE'] ?? null);
        $this->assertSame(0xFF9227, GetValue($colorID));

        $kelvinID = IPS_GetObjectIDByIdent('color_temp_kelvin', $iid);
        $this->assertNotFalse($kelvinID);
        $presentation = IPS_GetVariable($kelvinID)['VariablePresentation'];
        $this->assertSame(1801, $presentation['MIN']);
        $this->assertSame(6535, $presentation['MAX']);

        $presetID = IPS_GetObjectIDByIdent('color_temp_presets', $iid);
        $this->assertNotFalse($presetID);
        $presetVariable = IPS_GetVariable($presetID);
        $this->assertSame('', $presetVariable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_ENUMERATION, $presetVariable['VariablePresentation']['PRESENTATION'] ?? null);
        $presetOptions = json_decode($presetVariable['VariablePresentation']['OPTIONS'] ?? '[]', true);
        $this->assertSame([153, 250, 370, 454, 555], array_column($presetOptions, 'Value'));
        $this->assertSame(['Sehr kalt', 'Kalt', 'Neutral', 'Warm', 'Sehr warm'], array_column($presetOptions, 'Caption'));

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'ColorTemperatureVisualization');

        IPS_SetProperty($iid, 'ColorTemperaturePresentationMin', 2202);
        IPS_SetProperty($iid, 'ColorTemperaturePresentationMax', 5000);
        IPS_ApplyChanges($iid);

        $presentation = IPS_GetVariable($kelvinID)['VariablePresentation'];
        $this->assertSame(2202, $presentation['MIN']);
        $this->assertSame(5000, $presentation['MAX']);

        $interface->ReceiveData(self::buildMqttRequest($topic, ['color_temp' => 153]));
        $this->assertNotSame(0xFF9227, GetValue($colorID));
    }

    public function testColorTemperatureVisualizationRequiresExposeSupport(): void
    {
        [$iid] = $this->createTestInstance('TunableWhiteLight.json');
        $this->assertNotFalse(IPS_GetObjectIDByIdent('color_temp_kelvin', $iid));

        $this->writeStubAttributeArray($iid, 'Exposes', [
            [
                'type'     => 'light',
                'features' => [
                    [
                        'name'      => 'state',
                        'label'     => 'State',
                        'access'    => 7,
                        'type'      => 'binary',
                        'property'  => 'state',
                        'value_on'  => 'ON',
                        'value_off' => 'OFF'
                    ],
                    [
                        'name'      => 'brightness',
                        'label'     => 'Brightness',
                        'access'    => 7,
                        'type'      => 'numeric',
                        'property'  => 'brightness',
                        'value_min' => 0,
                        'value_max' => 254
                    ]
                ]
            ]
        ]);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemHidden($form, 'ColorTemperatureVisualization');
    }

    public function testDeviceOptionsAreShownInConfigurationForm(): void
    {
        [$iid] = $this->createTestInstance('MixedLightSensor.json');
        $this->writeStubAttributeArray($iid, 'DeviceOptions', [
            'transition'          => 1,
            'filtered_attributes' => ['battery']
        ]);
        $this->writeStubAttributeArray($iid, 'DeviceOptionDefinitions', [
            [
                'name'        => 'temperature_precision',
                'label'       => 'Temperature precision',
                'type'        => 'numeric',
                'description' => 'Controls the temperature precision.'
            ]
        ]);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'AdvancedDeviceSettings');
        $this->assertFormItemVisible($form, 'DeviceOptionsSettings');

        $list = $this->findFormItemByName($form, 'DeviceOptionList');
        $this->assertNotNull($list);

        $filteredAttributes = $this->findDeviceOptionRow($list['values'], 'filtered_attributes');
        $this->assertNotNull($filteredAttributes);
        $this->assertSame('["battery"]', $filteredAttributes['current']);
        $this->assertSame('Bearbeiten', $filteredAttributes['action']);

        $temperaturePrecision = $this->findDeviceOptionRow($list['values'], 'temperature_precision');
        $this->assertNotNull($temperaturePrecision);
        $this->assertSame('Zahl', $temperaturePrecision['type']);

        $qos = $this->findDeviceOptionRow($list['values'], 'qos');
        $this->assertNotNull($qos);
        $this->assertSame('Auswahl', $qos['type']);

        $disabled = $this->findDeviceOptionRow($list['values'], 'disabled');
        $this->assertNotNull($disabled);
        $this->assertSame('Wahr/Falsch', $disabled['type']);

        $updateCheck = $this->findDeviceOptionRow($list['values'], 'disable_automatic_update_check');
        $this->assertNotNull($updateCheck);

        $homeassistant = $this->findDeviceOptionRow($list['values'], 'homeassistant');
        $this->assertNotNull($homeassistant);
        $this->assertSame('Objekt', $homeassistant['type']);
    }

    public function testSelectingDeviceOptionShowsBooleanEditor(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();

        $device->RequestAction('SelectDeviceOption', json_encode([
            'name'  => 'disabled',
            'value' => 'true'
        ]));

        $this->assertSame('disabled', $device->updatedFields['DeviceOptionName']['value']);
        $this->assertSame('boolean', $device->updatedFields['DeviceOptionEditor']['value']);
        $this->assertSame(false, $device->updatedFields['DeviceOptionValue']['visible']);
        $this->assertSame(true, $device->updatedFields['DeviceOptionBoolean']['visible']);
        $this->assertSame(true, $device->updatedFields['DeviceOptionBoolean']['value']);
        $this->assertSame(false, $device->updatedFields['DeviceOptionSelect']['visible']);
    }

    public function testSelectingDeviceOptionShowsSelectEditor(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();

        $device->RequestAction('SelectDeviceOption', json_encode([
            'name'  => 'qos',
            'value' => '1'
        ]));

        $this->assertSame('select', $device->updatedFields['DeviceOptionEditor']['value']);
        $this->assertSame(false, $device->updatedFields['DeviceOptionValue']['visible']);
        $this->assertSame(false, $device->updatedFields['DeviceOptionBoolean']['visible']);
        $this->assertSame(true, $device->updatedFields['DeviceOptionSelect']['visible']);
        $this->assertSame('1', $device->updatedFields['DeviceOptionSelect']['value']);

        $options = json_decode($device->updatedFields['DeviceOptionSelect']['options'], true);
        $this->assertSame(['null', '0', '1', '2'], array_column($options, 'value'));
    }

    public function testSelectingDeviceAttributeOptionShowsAttributeEditor(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->setExposesForTest([
            [
                'type'     => 'light',
                'features' => [
                    ['property' => 'state'],
                    ['property' => 'brightness'],
                    ['property' => 'color_temp']
                ]
            ]
        ]);

        $device->RequestAction('SelectDeviceOption', json_encode([
            'name'  => 'filtered_attributes',
            'value' => '["brightness"]'
        ]));

        $this->assertSame('attributes', $device->updatedFields['DeviceOptionEditor']['value']);
        $this->assertSame(false, $device->updatedFields['DeviceOptionValue']['visible']);
        $this->assertSame(true, $device->updatedFields['DeviceOptionAttributeList']['visible']);
        $this->assertSame(true, $device->updatedFields['DeviceOptionAttributeEditor']['visible']);
        $this->assertSame('["brightness"]', $device->updatedFields['DeviceOptionValue']['value']);

        $listValues = json_decode($device->updatedFields['DeviceOptionAttributeList']['values'], true);
        $this->assertSame(['brightness'], array_column($listValues, 'attribute'));

        $candidateOptions = json_decode($device->updatedFields['DeviceOptionAttributeCandidate']['options'], true);
        $this->assertSame(['color_temp', 'state'], array_column($candidateOptions, 'value'));
    }

    public function testDeviceAttributeOptionEditorAddsAndRemovesAttributes(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->setExposesForTest([
            [
                'type'     => 'light',
                'features' => [
                    ['property' => 'state'],
                    ['property' => 'brightness']
                ]
            ]
        ]);

        $device->RequestAction('AddDeviceOptionAttribute', json_encode([
            'attribute' => 'state',
            'value'     => '["brightness"]'
        ]));

        $this->assertSame(['brightness', 'state'], json_decode($device->updatedFields['DeviceOptionValue']['value'], true));

        $device->RequestAction('RemoveDeviceOptionAttribute', json_encode([
            'attribute' => 'brightness',
            'value'     => $device->updatedFields['DeviceOptionValue']['value']
        ]));

        $this->assertSame(['state'], json_decode($device->updatedFields['DeviceOptionValue']['value'], true));
    }

    public function testApplyingTypedDeviceOptionsSendsTypedValues(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();

        $device->RequestAction('ApplyDeviceOption', json_encode([
            'name'    => 'disabled',
            'editor'  => 'boolean',
            'boolean' => true
        ]));

        $this->assertSame('/bridge/request/device/options', $device->sentTopic);
        $this->assertSame(['disabled' => true], $device->sentPayload['options']);

        $device->RequestAction('ApplyDeviceOption', json_encode([
            'name'      => 'qos',
            'editor'    => 'select',
            'selection' => 'null'
        ]));

        $this->assertSame(['qos' => null], $device->sentPayload['options']);

        $device->RequestAction('ApplyDeviceOption', json_encode([
            'name'  => 'transition',
            'value' => '1.5'
        ]));

        $this->assertSame(['transition' => 1.5], $device->sentPayload['options']);
    }

    public function testBindingAndReportingEndpointsAreShownInConfigurationForm(): void
    {
        [$iid] = $this->createTestInstance('MixedLightSensor.json');
        $this->writeStubAttributeArray($iid, 'DeviceEndpoints', [
            '1' => [
                'id'                    => '1',
                'name'                  => 'left',
                'bindings'              => [
                    ['cluster' => 'genOnOff', 'target' => ['type' => 'group', 'id' => 1]],
                    ['cluster' => ['name' => 'genLevelCtrl'], 'target' => ['type' => 'endpoint', 'deviceIeeeAddress' => '0xabcd', 'endpointID' => 2]]
                ],
                'configured_reportings' => [
                    [
                        'cluster'                 => 'genOnOff',
                        'attribute'               => 'onOff',
                        'minimum_report_interval' => 5,
                        'maximum_report_interval' => 3600,
                        'reportable_change'       => 1
                    ],
                    [
                        'cluster'               => ['name' => 'msTemperatureMeasurement'],
                        'attributeName'         => 'measuredValue',
                        'minimumReportInterval' => 10,
                        'maximumReportInterval' => 600,
                        'reportableChange'      => 25
                    ]
                ],
                'clusters'              => [
                    'input'  => ['genOnOff', 'genBasic'],
                    'output' => ['genLevelCtrl'],
                    'scenes' => []
                ]
            ]
        ]);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'BindingReportingSettings');
        $this->assertFormItemHidden($form, 'EndpointDataHint');

        $list = $this->findFormItemByName($form, 'EndpointList');
        $this->assertNotNull($list);

        $endpoint = $this->findEndpointRow($list['values'], '1');
        $this->assertNotNull($endpoint);
        $this->assertSame('left', $endpoint['name']);
        $this->assertSame('genOnOff, genBasic', $endpoint['input']);
        $this->assertSame('genLevelCtrl', $endpoint['output']);
        $this->assertSame('2', $endpoint['bindings']);
        $this->assertSame('2', $endpoint['reportings']);

        $bindingList = $this->findFormItemByName($form, 'BindingOverviewList');
        $this->assertNotNull($bindingList);
        $this->assertTrue($bindingList['visible']);
        $this->assertCount(2, $bindingList['values']);
        $this->assertSame('1', $bindingList['values'][0]['source_endpoint']);
        $this->assertSame('genOnOff', $bindingList['values'][0]['cluster']);
        $this->assertSame('1', $bindingList['values'][0]['target']);
        $this->assertSame('', $bindingList['values'][0]['target_endpoint']);
        $this->assertSame('genLevelCtrl', $bindingList['values'][1]['cluster']);
        $this->assertSame('0xabcd', $bindingList['values'][1]['target']);
        $this->assertSame('2', $bindingList['values'][1]['target_endpoint']);

        $reportingList = $this->findFormItemByName($form, 'ReportingOverviewList');
        $this->assertNotNull($reportingList);
        $this->assertTrue($reportingList['visible']);
        $this->assertCount(2, $reportingList['values']);
        $this->assertSame('1', $reportingList['values'][0]['endpoint']);
        $this->assertSame('genOnOff', $reportingList['values'][0]['cluster']);
        $this->assertSame('onOff', $reportingList['values'][0]['attribute']);
        $this->assertSame('5 s', $reportingList['values'][0]['minimum_interval']);
        $this->assertSame('3600 s', $reportingList['values'][0]['maximum_interval']);
        $this->assertSame('1', $reportingList['values'][0]['reportable_change']);
        $this->assertSame('msTemperatureMeasurement', $reportingList['values'][1]['cluster']);
        $this->assertSame('measuredValue', $reportingList['values'][1]['attribute']);
    }

    public function testBindingFormProvidesEndpointAndTargetSelections(): void
    {
        [$iid] = $this->createTestInstance('MixedLightSensor.json');
        $baseTopic = IPS_GetProperty($iid, 'MQTTBaseTopic');
        $this->writeStubAttributeArray($iid, 'DeviceEndpoints', [
            '1'   => [
                'id'       => '1',
                'name'     => 'left',
                'clusters' => ['input' => ['6'], 'output' => []]
            ],
            '242' => [
                'id'       => '242',
                'name'     => 'green-power',
                'clusters' => ['input' => [], 'output' => ['greenPower']]
            ]
        ]);

        $targetDeviceID = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        IPS_SetName($targetDeviceID, 'Target Device');
        IPS_SetProperty($targetDeviceID, 'MQTTBaseTopic', $baseTopic);
        IPS_SetProperty($targetDeviceID, 'MQTTTopic', 'Binding/Target/Device');
        IPS_ApplyChanges($targetDeviceID);

        $targetGroupID = IPS_CreateInstance('{11BF3773-E940-469B-9DD7-FB9ACD7199A2}');
        IPS_SetName($targetGroupID, 'Target Group');
        IPS_SetProperty($targetGroupID, 'MQTTBaseTopic', $baseTopic);
        IPS_SetProperty($targetGroupID, 'MQTTTopic', 'Binding/Target/Group');
        IPS_ApplyChanges($targetGroupID);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);

        $endpointSelect = $this->findFormItemByName($form, 'BindingSourceEndpoint');
        $this->assertNotNull($endpointSelect);
        $this->assertSame('Select', $endpointSelect['type']);
        $endpointValues = array_column($endpointSelect['options'], 'value');
        $this->assertContains('', $endpointValues);
        $this->assertContains('1', $endpointValues);
        $this->assertContains('242', $endpointValues);
        $this->assertStringContainsString('UpdateBindingClusters', $endpointSelect['onChange'] ?? '');

        $targetSelect = $this->findFormItemByName($form, 'BindingTarget');
        $this->assertNotNull($targetSelect);
        $this->assertSame('Select', $targetSelect['type']);
        $targetValues = array_column($targetSelect['options'], 'value');
        $this->assertContains('', $targetValues);
        $this->assertContains('Binding/Target/Device', $targetValues);
        $this->assertContains('Binding/Target/Group', $targetValues);
        $this->assertStringContainsString('UpdateBindingClusters', $targetSelect['onChange'] ?? '');

        $clusterSelect = $this->findFormItemByName($form, 'BindingClusters');
        $this->assertNotNull($clusterSelect);
        $this->assertSame('Select', $clusterSelect['type']);
        $clusterValues = array_column($clusterSelect['options'], 'value');
        $this->assertContains('', $clusterValues);
        $this->assertContains('genOnOff', $clusterValues);
        $this->assertNotContains('greenPower', $clusterValues);

        $reportingEndpointSelect = $this->findFormItemByName($form, 'ReportingEndpoint');
        $this->assertNotNull($reportingEndpointSelect);
        $this->assertSame('Select', $reportingEndpointSelect['type']);
        $this->assertContains('1', array_column($reportingEndpointSelect['options'], 'value'));
        $this->assertStringContainsString('UpdateReportingSelection', $reportingEndpointSelect['onChange'] ?? '');

        $reportingClusterSelect = $this->findFormItemByName($form, 'ReportingCluster');
        $this->assertNotNull($reportingClusterSelect);
        $this->assertSame('Select', $reportingClusterSelect['type']);
        $reportingClusterValues = array_column($reportingClusterSelect['options'], 'value');
        $this->assertContains('genOnOff', $reportingClusterValues);
        $this->assertNotContains('greenPower', $reportingClusterValues);
        $this->assertStringContainsString('UpdateReportingSelection', $reportingClusterSelect['onChange'] ?? '');

        $reportingAttributeSelect = $this->findFormItemByName($form, 'ReportingAttribute');
        $this->assertNotNull($reportingAttributeSelect);
        $this->assertSame('Select', $reportingAttributeSelect['type']);
        $this->assertContains('onOff', array_column($reportingAttributeSelect['options'], 'value'));

        $bindingList = $this->findFormItemByName($form, 'BindingOverviewList');
        $this->assertNotNull($bindingList);
        $this->assertTrue($bindingList['visible']);
        $this->assertSame('Keine Bindungen vorhanden', $bindingList['values'][0]['target']);

        $reportingList = $this->findFormItemByName($form, 'ReportingOverviewList');
        $this->assertNotNull($reportingList);
        $this->assertTrue($reportingList['visible']);
        $this->assertSame('Keine Reportings vorhanden', $reportingList['values'][0]['attribute']);
    }

    public function testBindingTargetOptionsDoNotRequestLiveLists(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();

        $method = new \ReflectionMethod($device, 'BuildBindingTargetOptions');
        $method->setAccessible(true);
        $options = $method->invoke($device);

        $this->assertIsArray($options);
        $this->assertSame('', $device->sentTopic);
    }

    public function testBridgeLookupAndBindingTargetsRequireSameSplitter(): void
    {
        [$iid] = $this->createTestInstance('MixedLightSensor.json');
        $baseTopic = IPS_GetProperty($iid, 'MQTTBaseTopic');
        $splitterID = IPS_CreateInstance('{6179ED6A-FC31-413C-BB8E-1204150CF376}');
        $otherSplitterID = IPS_CreateInstance('{6179ED6A-FC31-413C-BB8E-1204150CF376}');
        IPS_ConnectInstance($iid, $splitterID);

        $foreignBridgeID = IPS_CreateInstance('{00160D82-9E2F-D1BD-6D0B-952F945332C5}');
        IPS_SetProperty($foreignBridgeID, 'MQTTBaseTopic', $baseTopic);
        IPS_ApplyChanges($foreignBridgeID);
        IPS_ConnectInstance($foreignBridgeID, $otherSplitterID);

        $ownedBridgeID = IPS_CreateInstance('{00160D82-9E2F-D1BD-6D0B-952F945332C5}');
        IPS_SetProperty($ownedBridgeID, 'MQTTBaseTopic', $baseTopic);
        IPS_ApplyChanges($ownedBridgeID);
        IPS_ConnectInstance($ownedBridgeID, $splitterID);

        $ownedDeviceID = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        IPS_SetConfiguration($ownedDeviceID, json_encode([
            'MQTTBaseTopic' => $baseTopic,
            'MQTTTopic'     => 'Binding/Owned/Device'
        ]));
        IPS_ApplyChanges($ownedDeviceID);
        IPS_ConnectInstance($ownedDeviceID, $splitterID);

        $foreignDeviceID = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        IPS_SetConfiguration($foreignDeviceID, json_encode([
            'MQTTBaseTopic' => $baseTopic,
            'MQTTTopic'     => 'Binding/Foreign/Device'
        ]));
        IPS_ApplyChanges($foreignDeviceID);
        IPS_ConnectInstance($foreignDeviceID, $otherSplitterID);

        $ownedGroupID = IPS_CreateInstance('{11BF3773-E940-469B-9DD7-FB9ACD7199A2}');
        IPS_SetConfiguration($ownedGroupID, json_encode([
            'MQTTBaseTopic' => $baseTopic,
            'MQTTTopic'     => 'Binding/Owned/Group'
        ]));
        IPS_ApplyChanges($ownedGroupID);
        IPS_ConnectInstance($ownedGroupID, $splitterID);

        $foreignGroupID = IPS_CreateInstance('{11BF3773-E940-469B-9DD7-FB9ACD7199A2}');
        IPS_SetConfiguration($foreignGroupID, json_encode([
            'MQTTBaseTopic' => $baseTopic,
            'MQTTTopic'     => 'Binding/Foreign/Group'
        ]));
        IPS_ApplyChanges($foreignGroupID);
        IPS_ConnectInstance($foreignGroupID, $otherSplitterID);

        $device = IPS\InstanceManager::getInstanceInterface($iid);
        $bridgeMethod = new \ReflectionMethod($device, 'FindMatchingBridgeInstanceID');
        $this->assertSame($ownedBridgeID, $bridgeMethod->invoke($device));

        $targetMethod = new \ReflectionMethod($device, 'BuildBindingTargetOptions');
        $targetValues = array_column($targetMethod->invoke($device), 'value');
        $this->assertContains('Binding/Owned/Device', $targetValues);
        $this->assertContains('Binding/Owned/Group', $targetValues);
        $this->assertNotContains('Binding/Foreign/Device', $targetValues);
        $this->assertNotContains('Binding/Foreign/Group', $targetValues);
    }

    public function testBindingClusterSelectionUpdatesForSelectedEndpoint(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->setAttributeArrayForTest('DeviceEndpoints', [
            '1'   => [
                'id'       => '1',
                'name'     => 'left',
                'clusters' => ['input' => ['genBasic'], 'output' => ['8']]
            ],
            '242' => [
                'id'       => '242',
                'name'     => 'green-power',
                'clusters' => ['input' => [], 'output' => ['greenPower']]
            ]
        ]);

        $device->RequestAction('UpdateBindingClusters', json_encode([
            'endpoint' => '1',
            'target'   => '',
            'cluster'  => ''
        ]));

        $options = json_decode($device->updatedFields['BindingClusters']['options'], true);
        $this->assertIsArray($options);
        $clusterValues = array_column($options, 'value');
        $this->assertContains('', $clusterValues);
        $this->assertContains('genLevelCtrl', $clusterValues);
        $this->assertNotContains('genBasic', $clusterValues);
        $this->assertNotContains('greenPower', $clusterValues);
    }

    public function testBindingClusterSelectionUsesGroupMemberClusters(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->sendDataResponses = [
            'getDevices' => [
                'list' => [
                    [
                        'friendly_name' => 'Binding/Target/Member',
                        'ieeeAddr'      => '0x1234',
                        'endpoints'     => [
                            '1' => [
                                'id'       => '1',
                                'clusters' => ['input' => ['genOnOff'], 'output' => []]
                            ],
                            '2' => [
                                'id'       => '2',
                                'clusters' => ['input' => ['lightingColorCtrl'], 'output' => []]
                            ]
                        ]
                    ]
                ]
            ],
            'getGroups'  => [
                'list' => [
                    [
                        'friendly_name' => 'Binding/Target/Group',
                        'members'       => [
                            ['device' => 'Binding/Target/Member', 'endpoint' => '1']
                        ],
                        'devices'       => []
                    ]
                ]
            ]
        ];

        $device->RequestAction('UpdateBindingClusters', json_encode([
            'endpoint' => '',
            'target'   => 'Binding/Target/Group',
            'cluster'  => ''
        ]));

        $options = json_decode($device->updatedFields['BindingClusters']['options'], true);
        $this->assertIsArray($options);
        $clusterValues = array_column($options, 'value');
        $this->assertContains('genOnOff', $clusterValues);
        $this->assertNotContains('lightingColorCtrl', $clusterValues);
    }

    public function testReportingSelectionUpdatesClusterAndAttributeOptions(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->setAttributeArrayForTest('DeviceEndpoints', [
            '1' => [
                'id'                    => '1',
                'name'                  => 'power',
                'clusters'              => ['input' => ['2820', '1794'], 'output' => []],
                'configured_reportings' => [
                    [
                        'cluster'   => 'haElectricalMeasurement',
                        'attribute' => 'activePower'
                    ]
                ]
            ],
            '2' => [
                'id'       => '2',
                'name'     => 'switch',
                'clusters' => ['input' => ['6'], 'output' => []]
            ]
        ]);

        $device->RequestAction('UpdateReportingSelection', json_encode([
            'endpoint'  => '1',
            'cluster'   => 'haElectricalMeasurement',
            'attribute' => 'activePower'
        ]));

        $clusterOptions = json_decode($device->updatedFields['ReportingCluster']['options'], true);
        $this->assertIsArray($clusterOptions);
        $clusterValues = array_column($clusterOptions, 'value');
        $this->assertContains('haElectricalMeasurement', $clusterValues);
        $this->assertContains('seMetering', $clusterValues);
        $this->assertNotContains('genOnOff', $clusterValues);
        $this->assertSame('haElectricalMeasurement', $device->updatedFields['ReportingCluster']['value']);

        $attributeOptions = json_decode($device->updatedFields['ReportingAttribute']['options'], true);
        $this->assertIsArray($attributeOptions);
        $attributeValues = array_column($attributeOptions, 'value');
        $this->assertContains('activePower', $attributeValues);
        $this->assertContains('rmsVoltage', $attributeValues);
        $this->assertSame('activePower', $device->updatedFields['ReportingAttribute']['value']);
    }

    public function testBindingAndReportingSectionShowsEndpointHintWhenEndpointDataIsMissing(): void
    {
        [$iid] = $this->createTestInstance('MixedLightSensor.json');

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'BindingReportingSettings');
        $this->assertFormItemVisible($form, 'EndpointDataHint');

        $list = $this->findFormItemByName($form, 'EndpointList');
        $this->assertNotNull($list);
        $this->assertSame([], $list['values']);
    }

    public function testRefreshBindingReportingUsesBridgeCacheWithoutDeviceInfoRequest(): void
    {
        $device = $this->createDeviceOptionFormTestDouble();
        $device->setAttributeArrayForTest('DeviceEndpoints', [
            '11' => [
                'id'       => '11',
                'bindings' => [],
                'clusters' => [
                    'input'  => ['genOnOff'],
                    'output' => ['genOnOff']
                ]
            ]
        ]);
        $device->cachedEndpoints = [
            '11' => [
                'id'       => '11',
                'bindings' => [
                    ['cluster' => 'genOnOff', 'target' => ['type' => 'endpoint', 'deviceIeeeAddress' => '0xabcd', 'endpointID' => 1]]
                ]
            ]
        ];

        $device->RequestAction('RefreshBindingReportingInfo', true);

        $endpoints = $device->getAttributeArrayForTest('DeviceEndpoints');
        $this->assertSame('genOnOff', $endpoints['11']['bindings'][0]['cluster']);

        $bindingRows = json_decode($device->updatedFields['BindingOverviewList']['values'], true);
        $this->assertIsArray($bindingRows);
        $this->assertSame('genOnOff', $bindingRows[0]['cluster']);
        $this->assertSame('0xabcd', $bindingRows[0]['target']);
        $this->assertFalse($device->updateDeviceInfoCalled);
    }

    public function testColorCompositeCatalogUsesSingleColorVariable()
    {
        [$iid, $Debug] = $this->createTestInstance('MixedLightSensor.json');
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $topic = $Debug['Config']['MQTTBaseTopic'] . '/' . $Debug['Config']['MQTTTopic'];

        $exposes = $Debug['Exposes'];
        $exposes[0]['features'][] = [
            'name'     => 'color_xy',
            'label'    => 'Color (X/Y)',
            'access'   => 7,
            'type'     => 'composite',
            'property' => 'color',
            'features' => [
                ['name' => 'x', 'label' => 'X', 'access' => 7, 'type' => 'numeric', 'property' => 'x'],
                ['name' => 'y', 'label' => 'Y', 'access' => 7, 'type' => 'numeric', 'property' => 'y']
            ]
        ];
        $exposes[0]['features'][] = [
            'name'     => 'color_hs',
            'label'    => 'Color (HS)',
            'access'   => 7,
            'type'     => 'composite',
            'property' => 'color',
            'features' => [
                ['name' => 'hue', 'label' => 'Hue', 'access' => 7, 'type' => 'numeric', 'property' => 'hue'],
                ['name' => 'saturation', 'label' => 'Saturation', 'access' => 7, 'type' => 'numeric', 'property' => 'saturation']
            ]
        ];

        $staleColorSubFeatures = [
            'color__x',
            'color__y',
            'color__hue',
            'color__saturation'
        ];

        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        foreach ($staleColorSubFeatures as $ident) {
            $catalog[$ident] = [
                'ident'    => $ident,
                'property' => $ident,
                'label'    => $ident,
                'source'   => 'expose',
                'type'     => 'numeric',
                'created'  => false
            ];
        }
        $this->writeStubAttributeArray($iid, 'VariableCatalog', $catalog);
        $this->writeStubAttributeArray($iid, 'DisabledVariables', ['color__x']);
        $this->writeStubAttributeArray($iid, 'DeletedVariables', ['color__y']);

        $interface->ReceiveData(self::buildMqttRequest($topic, ['exposes' => $exposes]));

        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        $this->assertArrayHasKey('color', $catalog);
        $colorID = @IPS_GetObjectIDByIdent('color', $iid);
        $this->assertNotFalse($colorID);
        $colorVariable = IPS_GetVariable($colorID);
        $this->assertSame('', $colorVariable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_COLOR, $colorVariable['VariablePresentation']['PRESENTATION'] ?? null);
        $this->assertSame(0, $colorVariable['VariablePresentation']['ENCODING'] ?? null);
        $this->assertSame(1, $colorVariable['VariablePresentation']['COLOR_SPACE'] ?? null);
        foreach ($staleColorSubFeatures as $ident) {
            $this->assertArrayNotHasKey($ident, $catalog, 'Technical color subfeature must not stay in catalog: ' . $ident);
            $this->assertFalse(@IPS_GetObjectIDByIdent($ident, $iid), 'Technical color subfeature must not be created: ' . $ident);
        }
        $this->assertSame([], $this->readStubAttributeArray($iid, 'DisabledVariables'));
        $this->assertSame([], $this->readStubAttributeArray($iid, 'DeletedVariables'));
    }

    public function testMTD285_ZB()
    {
        [$iid,$Debug] = $this->createTestInstance('MTD285-ZB.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"sensor"', $html);
        $this->assertStringContainsString('"target_distance":{"available":true', $html);
        $this->assertStringContainsString('"presence_threshold":{"available":true', $html);
        $this->assertStringContainsString('"move_sensitivity":{"available":true', $html);
    }

    public function testAB3257001NJ()
    {
        [$iid,$Debug] = $this->createTestInstance('AB3257001NJ.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testPS_S04D()
    {
        [$iid,$Debug] = $this->createTestInstance('PS-S04D.json');
        // detection_range_prefix & schedule_time_raw fehlen im Expose, sind aber im Payload
        $OffestLastPayload = -2;
        // identify und device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -2;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $list = $this->findFormItemByName($form, 'VariableSelectionList');
        $this->assertNotNull($list);
        $this->assertNull($this->findVariableSelectionRow($list['values'], 'detection_range_composite'));
        $this->assertNull($this->findVariableSelectionRow($list['values'], 'detection_range_0'));

        $row = $this->findVariableSelectionRow($list['values'], 'detection_range_composite__detection_range_0');
        $this->assertNotNull($row);
        $this->assertSame('Angelegt', $row['state']);
        $this->assertSame('Deaktivieren', $row['action']);

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"sensor"', $html);
        $this->assertStringContainsString('"target_distance":{"available":true', $html);
        $this->assertStringContainsString('"presence_detection_options":{"available":true', $html);
        $this->assertStringContainsString('"ai_sensitivity_adaptive":{"available":true', $html);
        $this->assertStringContainsString('"track_target_distance":{"available":true', $html);
    }

    public function test501_40()
    {
        [$iid,$Debug] = $this->createTestInstance('501.40.json');
        $OffestLastPayload = 0;
        $OffsetChildrenIDs = 0;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        //$Debug['LastPayload'] ist leider unvollständig. Neues Z2M_Debug benötigt
        //$this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload ('.self::count_recursive($Debug['LastPayload']) + $OffestLastPayload.') und Erzeugte Variablen ('.count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs.') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"action"', $html);
        $this->assertStringContainsString('"primary":{"ident":"action"', $html);
        $this->assertStringContainsString('Bereit', $html);
        $this->assertStringContainsString('Helligkeit hoch', $html);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'VisualizationSettings');
        $this->assertFormItemVisible($form, 'DisableActionTile');
        $this->assertFormItemHidden($form, 'DisableMeteredSwitchTile');
        $this->assertFormItemHidden($form, 'DisableHeatingTile');
        $this->assertFormItemHidden($form, 'DisableSecurityTile');
        $this->assertFormItemHidden($form, 'DisableWindowHandleTile');
    }

    public function testBMCT_SLZ()
    {
        [$iid,$Debug] = $this->createTestInstance('BMCT-SLZ.json');
        $OffestLastPayload = 0;
        $OffsetChildrenIDs = 0;
        // remaining und progress aus den DebugChilds abziehen
        $OffsetDebugChild = -2;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        // Irgendwie fehlen Variablen aus dem Payload...
        // $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload ('.self::count_recursive($Debug['LastPayload']) + $OffestLastPayload.') und Erzeugte Variablen ('.count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs.') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"meteredSwitch"', $html);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'VisualizationSettings');
        $this->assertFormItemVisible($form, 'DisableMeteredSwitchTile');
        $this->assertFormItemHidden($form, 'DisableActionTile');
        $this->assertFormItemHidden($form, 'DisableHeatingTile');
        $this->assertFormItemHidden($form, 'DisableSecurityTile');
        $this->assertFormItemHidden($form, 'DisableWindowHandleTile');
    }

    public function testOTARemainingUsesDurationPresentation(): void
    {
        [$iid, $Debug] = $this->createTestInstance('BMCT-SLZ.json');
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $topic = $Debug['Config']['MQTTBaseTopic'] . '/' . $Debug['Config']['MQTTTopic'];

        $interface->ReceiveData(self::buildMqttRequest($topic, [
            'update' => [
                'state'     => 'updating',
                'progress'  => 3.2,
                'remaining' => 3285
            ]
        ]));

        $remainingID = IPS_GetObjectIDByIdent('update__remaining', $iid);
        $this->assertNotFalse($remainingID);
        $this->assertSame(3285.0, GetValue($remainingID));

        $presentation = IPS_GetVariable($remainingID)['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_DURATION, $presentation['PRESENTATION']);
        $this->assertSame(0, $presentation['COUNTDOWN_TYPE']);
        $this->assertSame(2, $presentation['FORMAT']);
        $this->assertFalse($presentation['MILLISECONDS']);

        IPS_SetVariableCustomPresentation($remainingID, []);
        IPS_ApplyChanges($iid);

        $presentation = IPS_GetVariable($remainingID)['VariableCustomPresentation'];
        $this->assertSame([], $presentation);

        $presentation = IPS_GetVariable($remainingID)['VariableCustomPresentation'];
        $this->assertSame([], $presentation);
    }

    public function testLastSeenUsesDateTimePresentation(): void
    {
        [$iid] = $this->createTestInstance('BMCT-SLZ.json');

        $lastSeenID = IPS_GetObjectIDByIdent('last_seen', $iid);
        $this->assertNotFalse($lastSeenID);

        $presentation = IPS_GetVariable($lastSeenID)['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_DATE_TIME, $presentation['PRESENTATION']);
    }

    public function testS4SW001P8EUMeteredSwitchShowsExtendedMeasurements()
    {
        [$iid] = $this->createTestInstance('S4SW-001P8EU.json');

        $powerFactorID = IPS_CreateVariable(VARIABLETYPE_FLOAT);
        IPS_SetParent($powerFactorID, $iid);
        IPS_SetIdent($powerFactorID, 'power_factor');
        IPS_SetName($powerFactorID, 'Power Factor');
        SetValue($powerFactorID, 0.97);

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"meteredSwitch"', $html);
        $this->assertStringContainsString('"ac_frequency":{"available":true', $html);
        $this->assertStringContainsString('"label":"Frequenz"', $html);
        $this->assertStringContainsString('"unit":"Hz"', $html);
        $this->assertStringContainsString('"produced_energy":{"available":true', $html);
        $this->assertStringContainsString('"label":"Erzeugte Energie"', $html);
        $this->assertStringContainsString('"power_factor":{"available":true,"label":"Leistungsfaktor"', $html);
        $this->assertStringContainsString('"formatted":"0.97"', $html);
        $this->assertStringContainsString('power_factor: "Leistungsfaktor"', $html);
    }

    public function testMeteredSwitchArchiveButtonRequiresActiveLogging(): void
    {
        [$iid] = $this->createTestInstance('BMCT-SLZ.json');
        $archiveID = IPS_CreateInstance('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        $powerID = IPS_GetObjectIDByIdent('power', $iid);

        AC_SetGraphStatus($archiveID, $powerID, true);

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"power":{"available":true', $html);
        $this->assertStringContainsString('"power":{"available":true,"label":"Leistung","unit":"W","value":0,"formatted":"0.0 W","archived":false}', $html);

        AC_SetLoggingStatus($archiveID, $powerID, true);

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"power":{"available":true,"label":"Leistung","unit":"W","value":0,"formatted":"0.0 W","archived":true}', $html);
    }

    public function testWT_A03E()
    {
        [$iid,$Debug] = $this->createTestInstance('WT-A03E.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        // 4x Update Variablen abziehen (remaining, progress, Latest Source und Release Notes)
        $OffsetDebugChild = -4;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testS8()
    {
        [$iid,$Debug] = $this->createTestInstance('S8.json');
        $OffestLastPayload = 0;
        // device_status und duration_presets bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -2;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"windowHandle"', $html);
        $this->assertStringContainsString('"ident":"position"', $html);
        $this->assertStringContainsString('"button_left"', $html);
        $this->assertStringContainsString('"button_right"', $html);
        $this->assertStringContainsString('Gekippt', $html);
        $this->assertStringNotContainsString('Oben gekippt', $html);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'VisualizationSettings');
        $this->assertFormItemVisible($form, 'DisableWindowHandleTile');
        $this->assertFormItemVisible($form, 'DisableSecurityTile');
        $this->assertFormItemHidden($form, 'DisableActionTile');
        $this->assertFormItemHidden($form, 'DisableMeteredSwitchTile');
        $this->assertFormItemHidden($form, 'DisableHeatingTile');
        $this->assertFormItemHidden($form, 'TemperatureVisualization');
    }
    public function testSenoroWinv2()
    {
        [$iid,$Debug] = $this->createTestInstance('Senoro.Win_v2.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));

        $html = IPS\InstanceManager::getInstanceInterface($iid)->GetVisualizationTile();
        $this->assertStringContainsString('"type":"security"', $html);
        $this->assertStringContainsString('"primary":{"ident":"opening_state"', $html);
        $this->assertStringContainsString('Geschlossen', $html);
        $this->assertStringContainsString('"alarm_state":{"available":true', $html);
        $this->assertStringContainsString('"alarm_siren":{"available":true', $html);
    }
    public function testS4PL00416EU()
    {
        [$iid,$Debug] = $this->createTestInstance('S4PL-00416EU.json');
        /** Fehlen im LastPayload (nur lesbar, keine Events)
         * led_mode
         * led_colors_* (8 Stück)
         * led_power_brightness
         * led_night_mode_* (4 Stück)
         * buttons_enabled_* (4 Stück)
         * wifi_config__ssid
         * wifi_config__password
         * wifi_config__static_ip
         * wifi_config__net_mask
         * wifi_config__gateway
         * wifi_config__name_server
         */
        $OffestLastPayload = +24;
        /** Fehlen im expose und werden somit nicht als Variablen angelegt
         * ac_frequency_* (4 Stück)
         * power_apparent_* (4 Stück)
         * power_factor_* (4 Stück)
         * produced_energy_* (4 Stück)
         * power_reactive_* (4 Stück)
         */
        $OffestLastPayload -= 20;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testStaleCompositeCatalogParentsAreCleanedOnApplyChanges()
    {
        [$iid] = $this->createTestInstance('S4PL-00416EU.json');

        $staleParents = [
            'led_colors',
            'led_night_mode',
            'buttons_enabled',
            'wifi_config'
        ];

        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        foreach ($staleParents as $ident) {
            $catalog[$ident] = [
                'ident'    => $ident,
                'property' => $ident,
                'label'    => $ident,
                'source'   => 'expose',
                'type'     => 'composite',
                'created'  => false
            ];
        }
        $this->writeStubAttributeArray($iid, 'VariableCatalog', $catalog);
        $this->writeStubAttributeArray($iid, 'DisabledVariables', ['buttons_enabled']);
        $this->writeStubAttributeArray($iid, 'DeletedVariables', ['wifi_config']);

        IPS_ApplyChanges($iid);

        $catalog = $this->readStubAttributeArray($iid, 'VariableCatalog');
        foreach ($staleParents as $ident) {
            $this->assertArrayNotHasKey($ident, $catalog, 'Stale composite parent must be removed from catalog: ' . $ident);
        }
        $this->assertArrayHasKey('led_colors__on_r', $catalog);
        $this->assertArrayHasKey('wifi_config__enabled', $catalog);
        $this->assertSame([], $this->readStubAttributeArray($iid, 'DisabledVariables'));
        $this->assertSame([], $this->readStubAttributeArray($iid, 'DeletedVariables'));
    }

    private function assertFormItemVisible(array $form, string $name): void
    {
        $item = $this->findFormItemByName($form, $name);
        $this->assertNotNull($item, 'Form item not found: ' . $name);
        $this->assertTrue($item['visible'] ?? true, 'Form item should be visible: ' . $name);
    }

    private function assertFormItemHidden(array $form, string $name): void
    {
        $item = $this->findFormItemByName($form, $name);
        $this->assertNotNull($item, 'Form item not found: ' . $name);
        $this->assertFalse($item['visible'] ?? true, 'Form item should be hidden: ' . $name);
    }

    private function findFormItemByName(array $node, string $name): ?array
    {
        if (($node['name'] ?? null) === $name) {
            return $node;
        }

        foreach ($node as $child) {
            if (!\is_array($child)) {
                continue;
            }

            $match = $this->findFormItemByName($child, $name);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    private function readStubAttributeArray(int $iid, string $name): array
    {
        $attributes = $this->readStubAttributes($iid);
        $value = (string) ($attributes[$name]['Current'] ?? '');
        $decoded = json_decode($value, true);
        return \is_array($decoded) ? $decoded : [];
    }

    private function writeStubAttributeArray(int $iid, string $name, array $value): void
    {
        $module = $this->getStubModule($iid);
        $reflection = new \ReflectionClass(IPSModule::class);
        $attributeProperty = $reflection->getProperty('attributes');
        $attributeProperty->setAccessible(true);

        $attributes = $attributeProperty->getValue($module);
        $this->assertArrayHasKey($name, $attributes, 'Attribute not found: ' . $name);
        $attributes[$name]['Current'] = json_encode($value);
        $attributeProperty->setValue($module, $attributes);
    }

    private function writeStubAttributeBoolean(int $iid, string $name, bool $value): void
    {
        $module = $this->getStubModule($iid);
        $reflection = new \ReflectionClass(IPSModule::class);
        $attributeProperty = $reflection->getProperty('attributes');
        $attributeProperty->setAccessible(true);

        $attributes = $attributeProperty->getValue($module);
        $this->assertArrayHasKey($name, $attributes, 'Attribute not found: ' . $name);
        $attributes[$name]['Current'] = $value;
        $attributeProperty->setValue($module, $attributes);
    }

    private function readStubAttributeString(int $iid, string $name): string
    {
        $attributes = $this->readStubAttributes($iid);
        return (string) ($attributes[$name]['Current'] ?? '');
    }

    private function writeStubAttributeString(int $iid, string $name, string $value): void
    {
        $module = $this->getStubModule($iid);
        $reflection = new \ReflectionClass(IPSModule::class);
        $attributeProperty = $reflection->getProperty('attributes');
        $attributeProperty->setAccessible(true);

        $attributes = $attributeProperty->getValue($module);
        $this->assertArrayHasKey($name, $attributes, 'Attribute not found: ' . $name);
        $attributes[$name]['Current'] = $value;
        $attributeProperty->setValue($module, $attributes);
    }

    private function removeStubProperties(int $iid, array $names): void
    {
        $module = $this->getStubModule($iid);
        $reflection = new \ReflectionClass(IPSModule::class);
        $property = $reflection->getProperty('properties');
        $property->setAccessible(true);

        $properties = $property->getValue($module);
        foreach ($names as $name) {
            unset($properties[$name]);
        }
        $property->setValue($module, $properties);
    }

    private function removeStubAttributes(int $iid, array $names): void
    {
        $module = $this->getStubModule($iid);
        $reflection = new \ReflectionClass(IPSModule::class);
        $property = $reflection->getProperty('attributes');
        $property->setAccessible(true);

        $attributes = $property->getValue($module);
        foreach ($names as $name) {
            unset($attributes[$name]);
        }
        $property->setValue($module, $attributes);
    }

    private function readStubAttributes(int $iid): array
    {
        $module = $this->getStubModule($iid);
        $reflection = new \ReflectionClass(IPSModule::class);
        $attributeProperty = $reflection->getProperty('attributes');
        $attributeProperty->setAccessible(true);
        $attributes = $attributeProperty->getValue($module);
        return \is_array($attributes) ? $attributes : [];
    }

    private function getStubModule(int $iid): object
    {
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $reflection = new \ReflectionClass(IPSModuleStrict::class);
        $moduleProperty = $reflection->getProperty('module');
        $moduleProperty->setAccessible(true);
        return $moduleProperty->getValue($interface);
    }

    private function findVariableSelectionRow(array $rows, string $ident): ?array
    {
        foreach ($rows as $row) {
            if (($row['ident'] ?? null) === $ident) {
                return $row;
            }
        }

        return null;
    }

    private function findDeviceOptionRow(array $rows, string $name): ?array
    {
        foreach ($rows as $row) {
            if (($row['name'] ?? null) === $name) {
                return $row;
            }
        }

        return null;
    }

    private function findEndpointRow(array $rows, string $endpoint): ?array
    {
        foreach ($rows as $row) {
            if (($row['endpoint'] ?? null) === $endpoint) {
                return $row;
            }
        }

        return null;
    }

    private function createDeviceOptionFormTestDouble(): Zigbee2MQTTDevice
    {
        $device = new class(990004) extends Zigbee2MQTTDevice {
            public array $updatedFields = [];
            public string $sentTopic = '';
            public array $sentPayload = [];
            public array $sendDataResponses = [];
            public array $cachedEndpoints = [];
            public bool $updateDeviceInfoCalled = false;
            public string $calledBridgeFunction = '';
            public array $calledBridgeArguments = [];
            public bool $bridgeFunctionResult = false;

            protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
            {
                $this->sentTopic = $Topic;
                $this->sentPayload = $Payload;
                $key = str_replace(self::SYMCON_EXTENSION_LIST_REQUEST, '', $Topic);
                if (\array_key_exists($key, $this->sendDataResponses)) {
                    return $this->sendDataResponses[$key];
                }

                return true;
            }

            protected function ReadPropertyString(string $Name): string
            {
                return match ($Name) {
                    self::MQTT_TOPIC      => 'Flur/Beleuchtung/Unten',
                    self::MQTT_BASE_TOPIC => 'zigbee2mqtt',
                    default               => parent::ReadPropertyString($Name)
                };
            }

            protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
            {
                $this->updatedFields[$Field][$Parameter] = $Value;
                return true;
            }

            protected function HasActiveParent(): bool
            {
                return true;
            }

            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }

            public function setExposesForTest(array $exposes): void
            {
                $this->WriteAttributeArray(self::ATTRIBUTE_EXPOSES, $exposes);
            }

            public function setAttributeArrayForTest(string $name, array $value): void
            {
                $this->WriteAttributeArray($name, $value);
            }

            public function getAttributeArrayForTest(string $name): array
            {
                return $this->ReadAttributeArray($name);
            }

            protected function UpdateDeviceInfo(): bool
            {
                $this->updateDeviceInfoCalled = true;
                return parent::UpdateDeviceInfo();
            }

            protected function ReadBridgeCachedDeviceEndpoints(): array
            {
                return $this->cachedEndpoints;
            }

            protected function CallMatchingBridgeFunction(string $function, array $arguments): mixed
            {
                $this->calledBridgeFunction = $function;
                $this->calledBridgeArguments = $arguments;

                return $this->bridgeFunctionResult;
            }
        };
        $device->Create();

        return $device;
    }

    private function createDeviceActionTestDouble(): Zigbee2MQTTDevice
    {
        $instanceID = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $device = new class($instanceID) extends Zigbee2MQTTDevice {
            public string $sentTopic = '';
            public array $sentPayload = [];

            protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
            {
                $this->sentTopic = $Topic;
                $this->sentPayload = $Payload;
                return true;
            }

            protected function ReadPropertyString(string $Name): string
            {
                return match ($Name) {
                    self::MQTT_TOPIC      => 'Wohnbereich/Beschattung/Terrassenfenster',
                    self::MQTT_BASE_TOPIC => 'zigbee2mqtt',
                    default               => parent::ReadPropertyString($Name)
                };
            }

            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }

            public function setExposesForTest(array $exposes): void
            {
                $this->WriteAttributeArray(self::ATTRIBUTE_EXPOSES, $exposes);
            }

            public function registerBooleanVariableForTest(string $ident): int
            {
                $this->RegisterVariableBoolean($ident, $ident);
                return $this->GetIDForIdent($ident);
            }

            public function setColorModeForTest(string $colorMode): void
            {
                $this->RegisterVariableString('color_mode', 'Color Mode');
                SetValue($this->GetIDForIdent('color_mode'), $colorMode);
            }
        };
        $device->Create();

        return $device;
    }

    private static function buildMqttRequest(string $topic, array $payload): string
    {
        return json_encode(
            [
                'DataID'           => '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}',
                'PacketType'       => 3,
                'QualityOfService' => 0,
                'Retain'           => false,
                'Topic'            => $topic,
                'Payload'          => bin2hex(json_encode($payload))
            ],
            JSON_UNESCAPED_SLASHES
        );
    }

    private function getMinimalPng(): string
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        $this->assertIsString($png);
        return $png;
    }
}
