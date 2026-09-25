<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

/**
 * Prueft den variableType-Vertrag der Symcon-9.1-Darstellungsparameter.
 */
class PresentationParametersTest extends DumpInclude
{
    public static function deviceDumps(): iterable
    {
        foreach (glob(__DIR__ . '/TestDumps/*.json') as $file) {
            yield basename($file) => [basename($file)];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deviceDumps')]
    public function testDevicePresentationParameterTypes(string $dump): void
    {
        [$instanceID] = $this->createTestInstance($dump);
        $this->assertInstancePresentationTypes($instanceID);
        IPS_ApplyChanges($instanceID);
        $this->assertInstancePresentationTypes($instanceID);
    }

    public function testGroupPresentationParameterTypes(): void
    {
        $debug = json_decode(file_get_contents(__DIR__ . '/TestDumps/ColorLight.json'), true, 512, JSON_THROW_ON_ERROR);
        $instanceID = IPS_CreateInstance('{11BF3773-E940-469B-9DD7-FB9ACD7199A2}');
        IPS_SetConfiguration($instanceID, json_encode($debug['Config']));
        IPS_ApplyChanges($instanceID);
        $group = IPS\InstanceManager::getInstanceInterface($instanceID);
        $group->BUFFER_MQTT_SUSPENDED = false;
        $payload = $debug['LastPayload'];
        $payload['exposes'] = $debug['Exposes'];
        $group->ReceiveData(json_encode([
            'DataID'           => '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}',
            'PacketType'       => 3,
            'QualityOfService' => 0,
            'Retain'           => false,
            'Topic'            => $debug['Config']['MQTTBaseTopic'] . '/' . $debug['Config']['MQTTTopic'],
            'Payload'          => bin2hex(json_encode($payload))
        ]));
        $this->assertNotFalse(IPS_GetObjectIDByIdent('color_temp', $instanceID));
        $this->assertInstancePresentationTypes($instanceID);
    }

    public function testRegistrationKeepsValuesProfilesAndCustomPresentations(): void
    {
        $instanceID = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $device = IPS\InstanceManager::getInstanceInterface($instanceID);
        $register = new ReflectionMethod($device, 'RegisterVariableInteger');
        $register->invoke($device, 'test_integer', 'Integer', '~Valve');
        $variableID = IPS_GetObjectIDByIdent('test_integer', $instanceID);
        $this->assertSame('~Valve', IPS_GetVariable($variableID)['VariableProfile']);
        SetValue($variableID, 42);
        $custom = ['PRESENTATION' => VARIABLE_PRESENTATION_SLIDER, 'MIN' => 10, 'MAX' => 90];
        IPS_SetVariableCustomPresentation($variableID, $custom);
        $register->invoke($device, 'test_integer', 'Integer', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN'          => 0.0,
            'MAX'          => 100.0,
            'STEP_SIZE'    => 1.0,
            'DIGITS'       => 0
        ]);
        $variable = IPS_GetVariable($variableID);
        $this->assertSame($variableID, IPS_GetObjectIDByIdent('test_integer', $instanceID));
        $this->assertSame(42, GetValue($variableID));
        $this->assertSame($custom, $variable['VariableCustomPresentation']);
        $this->assertSame(0, $variable['VariablePresentation']['MIN']);
        $this->assertSame(100, $variable['VariablePresentation']['MAX']);
        $this->assertSame(1, $variable['VariablePresentation']['STEP_SIZE']);

        $registerFloat = new ReflectionMethod($device, 'RegisterVariableFloat');
        $registerFloat->invoke($device, 'test_float', 'Float', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN'          => -10,
            'MAX'          => 30,
            'STEP_SIZE'    => 0.5,
            'DIGITS'       => 1
        ]);
        $floatID = IPS_GetObjectIDByIdent('test_float', $instanceID);
        $presentation = IPS_GetVariable($floatID)['VariablePresentation'];
        $this->assertSame(-10.0, $presentation['MIN']);
        $this->assertSame(30.0, $presentation['MAX']);
        $this->assertSame(0.5, $presentation['STEP_SIZE']);
        $this->assertSame(1, $presentation['DIGITS']);
    }

    public function testFloatPresetsKeepWholeAndFractionalValuesInJson(): void
    {
        $instanceID = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $device = IPS\InstanceManager::getInstanceInterface($instanceID);
        $build = new ReflectionMethod($device, 'BuildPresetPresentation');
        $presentation = $build->invoke($device, [
            ['value' => 20, 'name' => 'Normal'],
            ['value' => 20.5, 'name' => 'Comfort']
        ], 'float', ['type' => 'numeric', 'property' => 'occupied_heating_setpoint']);
        $options = json_decode($presentation['OPTIONS'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([20.0, 20.5], array_column($options, 'Value'));
    }

    private function assertInstancePresentationTypes(int $instanceID): void
    {
        $checked = 0;
        foreach (IPS_GetChildrenIDs($instanceID) as $variableID) {
            if (!IPS_VariableExists($variableID)) {
                continue;
            }
            $variable = IPS_GetVariable($variableID);
            $presentation = $variable['VariablePresentation'];
            $type = $variable['VariableType'];
            $context = IPS_GetObject($variableID)['ObjectIdent'];
            if (isset($presentation['MIN'], $presentation['MAX'])) {
                $this->assertGreaterThan($presentation['MIN'], $presentation['MAX'], $context);
            }
            if (isset($presentation['STEP_SIZE'])) {
                $this->assertGreaterThan(0, $presentation['STEP_SIZE'], $context);
            }
            // IPS_GetPresentation marks these fields as "variableType".
            foreach (['MIN', 'MAX', 'STEP_SIZE', 'OPEN_OUTSIDE_VALUE', 'CLOSE_INSIDE_VALUE'] as $key) {
                if (array_key_exists($key, $presentation)) {
                    $this->assertValueType($type, $presentation[$key], $context . '.' . $key);
                    $checked++;
                }
            }
            foreach (['OPTIONS' => ['Value'], 'CUSTOM_GRADIENT' => ['Value'], 'INTERVALS' => ['IntervalMinValue', 'IntervalMaxValue']] as $key => $fields) {
                foreach (json_decode($presentation[$key] ?? '[]', true, 512, JSON_THROW_ON_ERROR) as $entry) {
                    foreach ($fields as $field) {
                        if (array_key_exists($field, $entry)) {
                            $this->assertValueType($type, $entry[$field], $context . '.' . $key . '.' . $field);
                            $checked++;
                        }
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $checked);
    }

    private function assertValueType(int $type, mixed $value, string $context): void
    {
        $this->assertSame(['boolean', 'integer', 'double', 'string'][$type], gettype($value), $context);
    }
}
