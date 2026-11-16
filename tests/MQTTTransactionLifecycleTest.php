<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

/** Prueft den persistenten ID-Zaehler in allen MQTT-verwendenden Modultypen. */
class MQTTTransactionLifecycleTest extends TestCase
{
    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');
        parent::setUp();
    }

    public function testEveryMqttModuleRegistersCounterAndRetainsItAcrossApplyChanges(): void
    {
        foreach ([
            '{00160D82-9E2F-D1BD-6D0B-952F945332C5}',
            '{D30BADA8-F261-4D9F-89A9-2E9961AF021F}',
            '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}',
            '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}',
        ] as $moduleID) {
            $id = IPS_CreateInstance($moduleID);
            $instance = IPS\InstanceManager::getInstanceInterface($id);
            $read = new ReflectionMethod($instance, 'ReadAttributeInteger');
            $write = new ReflectionMethod($instance, 'WriteAttributeInteger');
            $allocate = new ReflectionMethod($instance, 'AddTransaction');
            $this->assertSame(0, $read->invoke($instance, 'TransactionIdHighWaterMark'), $moduleID);
            $write->invoke($instance, 'TransactionIdHighWaterMark', 2000000000000000);
            $payload = [];
            $first = $allocate->invokeArgs($instance, [&$payload, '/bridge/request/options', microtime(true) - 1]);
            $this->assertSame(2000000000000001, $first, $moduleID);

            IPS_ApplyChanges($id);
            $this->assertSame($first, $read->invoke($instance, 'TransactionIdHighWaterMark'), $moduleID);
            $next = $allocate->invokeArgs($instance, [&$payload, '/bridge/request/options']);
            $this->assertSame($first + 1, $next, $moduleID);
            $this->assertSame($next, $payload['transaction'], $moduleID);
        }
    }
}
