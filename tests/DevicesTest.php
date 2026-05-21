<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

class DevicesTest extends DumpInclude
{
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
        $this->assertSame('~Shutter.Reversed', $variable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_SHUTTER, $variable['VariableCustomPresentation']['PRESENTATION'] ?? null);
        $this->assertSame(100.0, $variable['VariableCustomPresentation']['OPEN_OUTSIDE_VALUE'] ?? null);
        $this->assertSame(0.0, $variable['VariableCustomPresentation']['CLOSE_INSIDE_VALUE'] ?? null);
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
        $this->assertSame('~HexColor', $variable['VariableProfile']);
        $this->assertSame(0xFF9227, GetValue($colorID));

        $kelvinID = IPS_GetObjectIDByIdent('color_temp_kelvin', $iid);
        $this->assertNotFalse($kelvinID);
        $presentation = IPS_GetVariable($kelvinID)['VariableCustomPresentation'];
        $this->assertSame(1801, $presentation['MIN']);
        $this->assertSame(6535, $presentation['MAX']);

        $form = json_decode(IPS_GetConfigurationForm($iid), true);
        $this->assertFormItemVisible($form, 'ColorTemperatureVisualization');

        IPS_SetProperty($iid, 'ColorTemperaturePresentationMin', 2202);
        IPS_SetProperty($iid, 'ColorTemperaturePresentationMax', 5000);
        IPS_ApplyChanges($iid);

        $presentation = IPS_GetVariable($kelvinID)['VariableCustomPresentation'];
        $this->assertSame(2202, $presentation['MIN']);
        $this->assertSame(5000, $presentation['MAX']);

        $interface->ReceiveData(self::buildMqttRequest($topic, ['color_temp' => 153]));
        $this->assertNotSame(0xFF9227, GetValue($colorID));
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
        $this->assertFormItemVisible($form, 'DeviceOptionsSettings');

        $list = $this->findFormItemByName($form, 'DeviceOptionList');
        $this->assertNotNull($list);

        $filteredAttributes = $this->findDeviceOptionRow($list['values'], 'filtered_attributes');
        $this->assertNotNull($filteredAttributes);
        $this->assertSame('["battery"]', $filteredAttributes['current']);
        $this->assertSame('Bearbeiten', $filteredAttributes['action']);

        $temperaturePrecision = $this->findDeviceOptionRow($list['values'], 'temperature_precision');
        $this->assertNotNull($temperaturePrecision);
        $this->assertSame('Numerisch', $temperaturePrecision['type']);
    }

    public function testBindingAndReportingEndpointsAreShownInConfigurationForm(): void
    {
        [$iid] = $this->createTestInstance('MixedLightSensor.json');
        $this->writeStubAttributeArray($iid, 'DeviceEndpoints', [
            '1' => [
                'id'                    => '1',
                'name'                  => 'left',
                'bindings'              => [
                    ['cluster' => 'genOnOff', 'target' => ['type' => 'group', 'id' => 1]]
                ],
                'configured_reportings' => [
                    ['cluster' => 'genOnOff', 'attribute' => 'onOff']
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

        $list = $this->findFormItemByName($form, 'EndpointList');
        $this->assertNotNull($list);

        $endpoint = $this->findEndpointRow($list['values'], '1');
        $this->assertNotNull($endpoint);
        $this->assertSame('left', $endpoint['name']);
        $this->assertSame('genOnOff, genBasic', $endpoint['input']);
        $this->assertSame('genLevelCtrl', $endpoint['output']);
        $this->assertSame('1', $endpoint['bindings']);
        $this->assertSame('1', $endpoint['reportings']);
    }

    public function testColorCompositeCatalogUsesSingleColorVariable()
    {
        if (!IPS_VariableProfileExists('~HexColor')) {
            IPS_CreateVariableProfile('~HexColor', VARIABLETYPE_INTEGER);
        }

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
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('color', $iid));
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
}
