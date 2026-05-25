<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

class BridgeTest extends TestCase
{
    private const DEVICE_MODULE_ID = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';

    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');

        parent::setUp();
    }

    public function testCheckOTAUpdateUsesCurrentZigbee2MqttResponseField(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'               => 'test_device',
                'update_available' => true
            ]
        ]);

        $this->assertTrue($bridge->CheckOTAUpdate('test_device'));
        $this->assertSame('/bridge/request/device/ota_update/check', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device'], $bridge->lastPayload);
        $this->assertSame(10000, $bridge->lastTimeout);
    }

    public function testCheckOTADowngradeWithUrlUsesDowngradeTopicAndUrlPayload(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'               => 'test_device',
                'update_available' => true
            ]
        ]);

        $this->assertTrue($bridge->CheckOTADowngradeWithUrl('test_device', 'ota/index.json'));
        $this->assertSame('/bridge/request/device/ota_update/check/downgrade', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device', 'url' => 'ota/index.json'], $bridge->lastPayload);
        $this->assertSame(10000, $bridge->lastTimeout);
    }

    public function testCheckOTAUpdateKeepsLegacyResponseFieldCompatible(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'              => 'test_device',
                'updateAvailable' => true
            ]
        ]);

        $this->assertTrue($bridge->CheckOTAUpdate('test_device'));
    }

    public function testPerformOTAUpdateIsSentAsAsyncBridgeCommand(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertTrue($bridge->PerformOTAUpdate('test_device'));
        $this->assertSame('/bridge/request/device/ota_update/update', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device'], $bridge->lastPayload);
        $this->assertSame(0, $bridge->lastTimeout);
    }

    public function testPerformOTADowngradeWithUrlIsSentAsAsyncBridgeCommand(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertTrue($bridge->PerformOTADowngradeWithUrl('test_device', 'firmware.ota'));
        $this->assertSame('/bridge/request/device/ota_update/update/downgrade', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device', 'url' => 'firmware.ota'], $bridge->lastPayload);
        $this->assertSame(0, $bridge->lastTimeout);
    }

    public function testScheduleOTAUpdateWithUrlWaitsForBridgeResponse(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['id' => 'test_device']
        ]);

        $this->assertTrue($bridge->ScheduleOTAUpdateWithUrl('test_device', 'ota/index.json'));
        $this->assertSame('/bridge/request/device/ota_update/schedule', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device', 'url' => 'ota/index.json'], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testScheduleOTADowngradeUsesDowngradeTopic(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['id' => 'test_device']
        ]);

        $this->assertTrue($bridge->ScheduleOTADowngrade('test_device'));
        $this->assertSame('/bridge/request/device/ota_update/schedule/downgrade', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device'], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testUnscheduleOTAUpdateUsesUnscheduleTopic(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['id' => 'test_device']
        ]);

        $this->assertTrue($bridge->UnscheduleOTAUpdate('test_device'));
        $this->assertSame('/bridge/request/device/ota_update/unschedule', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device'], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testSetDeviceOptionsUsesDeviceOptionsBridgeRequest(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'               => 'test_device',
                'restart_required' => false
            ]
        ]);

        $this->assertTrue($bridge->SetDeviceOptions('test_device', '{"transition":1,"filtered_attributes":["battery"]}'));
        $this->assertSame('/bridge/request/device/options', $bridge->lastTopic);
        $this->assertSame([
            'id'      => 'test_device',
            'options' => [
                'transition'          => 1,
                'filtered_attributes' => ['battery']
            ]
        ], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testSetDeviceOptionsRejectsInvalidJson(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertFalse(@$bridge->SetDeviceOptions('test_device', 'not json'));
        $this->assertSame('', $bridge->lastTopic);
    }

    public function testSetBlocklistUsesBridgeOptionsRequest(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => []
        ]);

        $this->assertTrue($bridge->SetBlocklist('["0x000b57fffec6a5b2"]'));
        $this->assertSame('/bridge/request/options', $bridge->lastTopic);
        $this->assertSame([
            'options' => [
                'blocklist' => ['0x000b57fffec6a5b2']
            ]
        ], $bridge->lastPayload);
        $this->assertSame(['0x000b57fffec6a5b2'], $bridge->readDiagnosticAttribute('ConfigBlocklist'));
    }

    public function testSetPasslistRejectsInvalidIeeeAddress(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertFalse(@$bridge->SetPasslist('["not-an-ieee-address"]'));
        $this->assertSame('', $bridge->lastTopic);
    }

    public function testNetworkSecurityFormShowsPopupForMissingIeeeAddress(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $bridge->RequestAction('AddBlocklistDevice', json_encode([
            'ieee_address'          => '',
            'selected_ieee_address' => ''
        ]));

        $this->assertSame('', $bridge->lastTopic);
        $this->assertTrue($bridge->updatedFields['NetworkSecurityError']['visible']);
        $this->assertSame(
            'Please select a known device or enter a valid IEEE address.',
            $bridge->updatedFields['NetworkSecurityErrorText']['caption']
        );
    }

    public function testNetworkSecurityAvailableDevicesUseConfiguredDeviceInstances(): void
    {
        $bridge = $this->createBridgeTestDouble(true);
        $bridge->setBaseTopicForTest('zigbee2mqtt');
        $deviceID = IPS_CreateInstance(self::DEVICE_MODULE_ID);
        IPS_SetName($deviceID, 'Kitchen light');
        IPS_SetConfiguration($deviceID, json_encode([
            'MQTTBaseTopic' => 'zigbee2mqtt',
            'MQTTTopic'     => 'Kitchen/Light',
            'IEEE'          => '0x000b57fffec6a5b2'
        ]));
        IPS_ApplyChanges($deviceID);

        $method = new ReflectionMethod(Zigbee2MQTTBridge::class, 'BuildNetworkSecurityAvailableDeviceFormValues');
        $values = $method->invoke($bridge);

        $this->assertContains('0x000b57fffec6a5b2', array_column($values, 'ieee_address'));
        $this->assertStringContainsString('Kitchen/Light', implode("\n", array_column($values, 'device')));
    }

    public function testSelectNetworkSecurityDeviceUpdatesSelectedFields(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $bridge->RequestAction('SelectNetworkSecurityDevice', json_encode([
            'device'       => 'Kitchen/Light',
            'ieee_address' => '0x000b57fffec6a5b2'
        ]));

        $this->assertSame('Kitchen/Light', $bridge->updatedFields['NetworkSecuritySelectedDevice']['value']);
        $this->assertSame('0x000b57fffec6a5b2', $bridge->updatedFields['NetworkSecuritySelectedIeeeAddress']['value']);
        $this->assertSame('', $bridge->updatedFields['NetworkSecurityIeeeAddress']['value']);
    }

    public function testBindWithOptionsUsesClustersAndSkipDisableReporting(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['from' => 'remote/1', 'to' => 'lamp']
        ]);

        $this->assertTrue($bridge->BindWithOptions('remote/1', 'lamp', 'genOnOff, genLevelCtrl', true));
        $this->assertSame('/bridge/request/device/bind', $bridge->lastTopic);
        $this->assertSame([
            'from'                   => 'remote/1',
            'to'                     => 'lamp',
            'clusters'               => ['genOnOff', 'genLevelCtrl'],
            'skip_disable_reporting' => true
        ], $bridge->lastPayload);
        $this->assertSame(15000, $bridge->lastTimeout);
    }

    public function testUnbindWithOptionsUsesBindingTimeout(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['from' => 'remote/1', 'to' => 'lamp']
        ]);

        $this->assertTrue($bridge->UnbindWithOptions('remote/1', 'lamp', 'genOnOff', false));
        $this->assertSame('/bridge/request/device/unbind', $bridge->lastTopic);
        $this->assertSame([
            'from'     => 'remote/1',
            'to'       => 'lamp',
            'clusters' => ['genOnOff']
        ], $bridge->lastPayload);
        $this->assertSame(15000, $bridge->lastTimeout);
    }

    public function testConfigureReportingUsesReportingConfigureTopic(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['id' => 'lamp']
        ]);

        $this->assertTrue($bridge->ConfigureReporting('lamp', '1', 'genLevelCtrl', 'currentLevel', 5, 600, '10', '{"manufacturerCode":1234}'));
        $this->assertSame('/bridge/request/device/reporting/configure', $bridge->lastTopic);
        $this->assertSame([
            'id'                      => 'lamp',
            'cluster'                 => 'genLevelCtrl',
            'endpoint'                => 1,
            'attribute'               => 'currentLevel',
            'minimum_report_interval' => 5,
            'maximum_report_interval' => 600,
            'reportable_change'       => 10,
            'options'                 => ['manufacturerCode' => 1234]
        ], $bridge->lastPayload);
        $this->assertSame(10000, $bridge->lastTimeout);
    }

    public function testReadReportingReturnsJsonData(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'      => 'lamp',
                'cluster' => 'genLevelCtrl'
            ]
        ]);

        $this->assertSame('{"id":"lamp","cluster":"genLevelCtrl"}', $bridge->ReadReporting('lamp', 'left', 'genLevelCtrl', 'currentLevel,currentFrequency', ''));
        $this->assertSame('/bridge/request/device/reporting/read', $bridge->lastTopic);
        $this->assertSame([
            'id'       => 'lamp',
            'cluster'  => 'genLevelCtrl',
            'endpoint' => 'left',
            'configs'  => [
                ['attribute' => 'currentLevel'],
                ['attribute' => 'currentFrequency']
            ]
        ], $bridge->lastPayload);
        $this->assertSame(10000, $bridge->lastTimeout);
    }

    public function testHealthCheckStoresDiagnosticResult(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'healthy' => true
            ]
        ]);

        $this->assertTrue($bridge->HealthCheck());
        $this->assertSame('/bridge/request/health_check', $bridge->lastTopic);
        $this->assertSame([], $bridge->lastPayload);
        $this->assertSame(10000, $bridge->lastTimeout);
        $this->assertTrue($bridge->readDiagnosticAttribute('DiagnosticHealth')['healthy']);
        $this->assertArrayHasKey('checked_at', $bridge->readDiagnosticAttribute('DiagnosticHealth'));
    }

    public function testCoordinatorCheckStoresMissingRouters(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'missing_routers' => [
                    [
                        'friendly_name' => 'router',
                        'ieee_address'  => '0x1234'
                    ]
                ]
            ]
        ]);

        $this->assertFalse($bridge->CoordinatorCheck());
        $this->assertSame('/bridge/request/coordinator_check', $bridge->lastTopic);
        $this->assertSame([], $bridge->lastPayload);
        $this->assertSame(10000, $bridge->lastTimeout);
        $this->assertSame('router', $bridge->readDiagnosticAttribute('DiagnosticCoordinator')['missing_routers'][0]['friendly_name']);
    }

    public function testReceiveDataCollectsBridgeDiagnostics(): void
    {
        $bridge = $this->createBridgeTestDouble(true);
        $bridge->setBaseTopicForTest('zigbee2mqtt');

        $bridge->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/logging',
            'Payload' => bin2hex(json_encode([
                'level'     => 'warning',
                'message'   => 'Something needs attention',
                'namespace' => 'z2m'
            ]))
        ]));
        $bridge->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/event',
            'Payload' => bin2hex(json_encode([
                'type' => 'device_joined',
                'data' => [
                    'friendly_name' => 'new_device'
                ]
            ]))
        ]));
        $bridge->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/devices',
            'Payload' => bin2hex(json_encode([
                [
                    'friendly_name'       => 'unsupported',
                    'ieee_address'        => '0x1111',
                    'supported'           => false,
                    'interview_completed' => true,
                    'definition'          => null
                ],
                [
                    'friendly_name'       => 'interviewing',
                    'ieee_address'        => '0x2222',
                    'supported'           => true,
                    'interview_completed' => false,
                    'endpoints'           => [
                        '1' => [
                            'id'       => 1,
                            'bindings' => [
                                ['cluster' => 'genOnOff', 'target' => ['type' => 'group', 'id' => 1]]
                            ]
                        ]
                    ],
                    'definition'          => [
                        'model'  => 'SENSOR',
                        'vendor' => 'Vendor'
                    ]
                ]
            ]))
        ]));

        $this->assertSame('Something needs attention', $bridge->readDiagnosticAttribute('DiagnosticLogs')[0]['message']);
        $this->assertSame('device_joined', $bridge->readDiagnosticAttribute('DiagnosticEvents')[0]['type']);
        $this->assertSame('unsupported', $bridge->readDiagnosticAttribute('DiagnosticUnsupportedDevices')[0]['friendly_name']);
        $this->assertSame('interviewing', $bridge->readDiagnosticAttribute('DiagnosticInterviewDevices')[0]['friendly_name']);
        $this->assertContains('unsupported', array_column($bridge->readDiagnosticAttribute('NetworkDevices'), 'friendly_name'));
        $this->assertContains('0x1111', array_column($bridge->readDiagnosticAttribute('NetworkDevices'), 'ieee_address'));

        $cachedEndpoints = json_decode($bridge->GetCachedDeviceEndpoints('interviewing'), true);
        $this->assertSame('genOnOff', $cachedEndpoints['1']['bindings'][0]['cluster']);
    }

    public function testReceiveDataStoresNetworkSecurityConfig(): void
    {
        $bridge = $this->createBridgeTestDouble(true);
        $bridge->setBaseTopicForTest('zigbee2mqtt');

        $bridge->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/info',
            'Payload' => bin2hex(json_encode([
                'config' => [
                    'blocklist' => ['0x000b57fffec6a5b2'],
                    'passlist'  => ['0x000b57fffec6a5b3']
                ]
            ]))
        ]));

        $this->assertSame(['0x000b57fffec6a5b2'], $bridge->readDiagnosticAttribute('ConfigBlocklist'));
        $this->assertSame(['0x000b57fffec6a5b3'], $bridge->readDiagnosticAttribute('ConfigPasslist'));
    }

    public function testCreateBackupReturnsBase64Zip(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'zip' => 'WklHQkVFMk1RVFQuUk9DS1M='
            ]
        ]);

        $this->assertSame('WklHQkVFMk1RVFQuUk9DS1M=', $bridge->CreateBackup());
        $this->assertSame('/bridge/request/backup', $bridge->lastTopic);
        $this->assertSame([], $bridge->lastPayload);
        $this->assertSame(30000, $bridge->lastTimeout);
    }

    public function testAddInstallCodeUsesInstallCodeRequest(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'value' => 'INSTALLCODE'
            ]
        ]);

        $this->assertTrue($bridge->AddInstallCode(' INSTALLCODE '));
        $this->assertSame('/bridge/request/install_code/add', $bridge->lastTopic);
        $this->assertSame(['value' => 'INSTALLCODE'], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testAddInstallCodeRejectsEmptyCode(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertFalse(@$bridge->AddInstallCode(''));
        $this->assertSame('', $bridge->lastTopic);
    }

    public function testTouchlinkScanStoresFoundDevices(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'found' => [
                    [
                        'ieee_address' => '0x1234',
                        'channel'      => 15
                    ]
                ]
            ]
        ]);

        $this->assertSame('{"found":[{"ieee_address":"0x1234","channel":15}]}', $bridge->TouchlinkScan());
        $this->assertSame('/bridge/request/touchlink/scan', $bridge->lastTopic);
        $this->assertSame([], $bridge->lastPayload);
        $this->assertSame(70000, $bridge->lastTimeout);
        $this->assertSame('0x1234', $bridge->readDiagnosticAttribute('TouchlinkDevices')[0]['ieee_address']);
    }

    public function testTouchlinkIdentifyUsesTargetPayload(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => []
        ]);

        $this->assertTrue($bridge->TouchlinkIdentify('0x1234', 15));
        $this->assertSame('/bridge/request/touchlink/identify', $bridge->lastTopic);
        $this->assertSame([
            'ieee_address' => '0x1234',
            'channel'      => 15
        ], $bridge->lastPayload);
        $this->assertSame(10000, $bridge->lastTimeout);
    }

    public function testTouchlinkFactoryResetSupportsTargetAndEmptyPayload(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => []
        ]);

        $this->assertTrue($bridge->TouchlinkFactoryReset('0x1234', 15));
        $this->assertSame('/bridge/request/touchlink/factory_reset', $bridge->lastTopic);
        $this->assertSame([
            'ieee_address' => '0x1234',
            'channel'      => 15
        ], $bridge->lastPayload);
        $this->assertSame(70000, $bridge->lastTimeout);

        $this->assertTrue($bridge->TouchlinkFactoryReset());
        $this->assertSame('/bridge/request/touchlink/factory_reset', $bridge->lastTopic);
        $this->assertSame([], $bridge->lastPayload);
        $this->assertSame(70000, $bridge->lastTimeout);
    }

    public function testAddDeviceToGroupUsesOptionalEndpoint(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['group' => 'lights']
        ]);

        $this->assertTrue($bridge->AddDeviceToGroup('lights', 'wall_switch', 'right'));
        $this->assertSame('/bridge/request/group/members/add', $bridge->lastTopic);
        $this->assertSame([
            'group'    => 'lights',
            'device'   => 'wall_switch',
            'endpoint' => 'right'
        ], $bridge->lastPayload);
    }

    public function testRemoveDeviceFromGroupUsesEndpointAndSkipReportingFlag(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['group' => 'lights']
        ]);

        $this->assertTrue($bridge->RemoveDeviceFromGroup('lights', 'wall_switch', '2', false));
        $this->assertSame('/bridge/request/group/members/remove', $bridge->lastTopic);
        $this->assertSame([
            'group'                  => 'lights',
            'device'                 => 'wall_switch',
            'skip_disable_reporting' => false,
            'endpoint'               => 2
        ], $bridge->lastPayload);
    }

    public function testRemoveDeviceFromAllGroupsUsesDevicePayload(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['device' => 'wall_switch']
        ]);

        $this->assertTrue($bridge->RemoveDeviceFromAllGroups('wall_switch', false));
        $this->assertSame('/bridge/request/group/members/remove_all', $bridge->lastTopic);
        $this->assertSame([
            'device'                 => 'wall_switch',
            'skip_disable_reporting' => false
        ], $bridge->lastPayload);
    }

    public function testSetGroupOptionsUsesGroupOptionsBridgeRequest(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'               => 'lights',
                'restart_required' => false
            ]
        ]);

        $this->assertTrue($bridge->SetGroupOptions('lights', '{"transition":1,"retain":false}'));
        $this->assertSame('/bridge/request/group/options', $bridge->lastTopic);
        $this->assertSame([
            'id'      => 'lights',
            'options' => [
                'transition' => 1,
                'retain'     => false
            ]
        ], $bridge->lastPayload);
    }

    public function testSetGroupOptionsRejectsInvalidJson(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertFalse(@$bridge->SetGroupOptions('lights', 'not json'));
        $this->assertSame('', $bridge->lastTopic);
    }

    public function testStoreSceneSendsSceneStoreCommand(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertTrue($bridge->StoreScene('livingroom/lights', 2, 'Evening'));
        $this->assertSame('/livingroom/lights/set', $bridge->lastTopic);
        $this->assertSame([
            'scene_store' => [
                'ID'   => 2,
                'name' => 'Evening'
            ]
        ], $bridge->lastPayload);
        $this->assertSame(0, $bridge->lastTimeout);
    }

    public function testAddSceneRejectsInvalidJson(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertFalse(@$bridge->AddScene('lights', 'not json'));
        $this->assertSame('', $bridge->lastTopic);
    }

    public function testRenameSceneSendsSceneRenameCommand(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertTrue($bridge->RenameScene('lights', 3, 'Dinner'));
        $this->assertSame('/lights/set', $bridge->lastTopic);
        $this->assertSame([
            'scene_rename' => [
                'ID'   => 3,
                'name' => 'Dinner'
            ]
        ], $bridge->lastPayload);
        $this->assertSame(0, $bridge->lastTimeout);
    }

    private function createBridgeTestDouble(array|bool $result): Zigbee2MQTTBridge
    {
        $bridge = new class(900001, $result) extends Zigbee2MQTTBridge {
            public string $lastTopic = '';
            public array $lastPayload = [];
            public int $lastTimeout = -1;
            public array $updatedFields = [];
            private string $testBaseTopic = '';

            public function __construct(int $InstanceID, private array|bool $result)
            {
                parent::__construct($InstanceID);
            }

            protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
            {
                $this->lastTopic = $Topic;
                $this->lastPayload = $Payload;
                $this->lastTimeout = $Timeout;

                return $this->result;
            }

            public function readDiagnosticAttribute(string $name): array
            {
                return $this->ReadAttributeArray($name);
            }

            public function setBaseTopicForTest(string $baseTopic): void
            {
                $this->testBaseTopic = $baseTopic;
            }

            protected function ReadPropertyString(string $Name): string
            {
                if ($Name === self::MQTT_BASE_TOPIC && $this->testBaseTopic !== '') {
                    return $this->testBaseTopic;
                }

                return parent::ReadPropertyString($Name);
            }

            protected function GetStatus(): int
            {
                return IS_ACTIVE;
            }

            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }

            protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
            {
                $this->updatedFields[$Field][$Parameter] = $Value;
                return true;
            }
        };
        $bridge->Create();
        return $bridge;
    }
}
