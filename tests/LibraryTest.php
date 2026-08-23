<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';
include_once __DIR__ . '/../libs/ModuleUpdateGuard.php';

/**
 * Prüft Metadaten, Moduldefinitionen und Aktionsformulare der Bibliothek.
 */
class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../Configurator');
    }

    public function testValidateBridge(): void
    {
        $this->validateModule(__DIR__ . '/../Bridge');
    }

    public function testValidateDevice(): void
    {
        $this->validateModule(__DIR__ . '/../Device');
    }

    public function testValidateGroup(): void
    {
        $this->validateModule(__DIR__ . '/../Group');
    }

    public function testValidateNetworkMap(): void
    {
        $this->validateModule(__DIR__ . '/../NetworkMap');
    }

    public function testValidateActions(): void
    {
        $actionFiles = glob(__DIR__ . '/../actions/*.json');
        $this->assertIsArray($actionFiles);
        $this->assertNotSame([], $actionFiles);

        $actionIDs = [];
        foreach ($actionFiles as $actionFile) {
            $action = json_decode(file_get_contents($actionFile), true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($action, basename($actionFile));
            $this->assertMatchesRegularExpression(
                '/^\{[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}\}$/',
                (string) ($action['id'] ?? ''),
                basename($actionFile)
            );
            $this->assertArrayNotHasKey((string) $action['id'], $actionIDs, basename($actionFile));
            $actionIDs[(string) $action['id']] = true;
        }
    }

    public function testModuleTimersSuppressTransientUnavailableInterfaceWarnings(): void
    {
        foreach ([
            __DIR__ . '/../Bridge/Helper/BridgePairingHelper.php',
            __DIR__ . '/../Discovery/module.php',
            __DIR__ . '/../NetworkMap/module.php',
        ] as $file) {
            $source = file_get_contents($file);

            $this->assertStringContainsString('@IPS_RequestAction(', $source, $file);
        }
    }

    public function testModuleUpdateGuardStopsTransientInterfaceCallsWithoutWarning(): void
    {
        foreach ([
            'InstanceInterface is not available',
            'InstanceInterface is not a PHP module',
        ] as $message) {
            $continued = false;
            $result = \Zigbee2MQTT\ModuleUpdateGuard::Execute(
                static function () use ($message, &$continued): string
                {
                    trigger_error($message, E_USER_WARNING);
                    $continued = true;
                    return 'processed';
                },
                'skipped'
            );

            $this->assertSame('skipped', $result);
            $this->assertFalse($continued, $message);
        }
    }

    public function testModuleUpdateGuardDoesNotHideUnrelatedExceptions(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unrelated failure');

        \Zigbee2MQTT\ModuleUpdateGuard::Execute(
            static fn (): never => throw new RuntimeException('unrelated failure'),
            null
        );
    }

    public function testRuntimeEntryPointsUseModuleUpdateGuard(): void
    {
        foreach ([
            __DIR__ . '/../libs/ModulBase.php'      => 1,
            __DIR__ . '/../Bridge/module.php'       => 2,
            __DIR__ . '/../Configurator/module.php' => 2,
            __DIR__ . '/../Device/module.php'       => 1,
            __DIR__ . '/../Discovery/module.php'    => 1,
            __DIR__ . '/../Group/module.php'        => 1,
            __DIR__ . '/../NetworkMap/module.php'   => 2,
        ] as $file => $minimumGuardCalls) {
            $source = file_get_contents($file);

            $this->assertGreaterThanOrEqual(
                $minimumGuardCalls,
                substr_count($source, 'ModuleUpdateGuard::Execute('),
                $file
            );
        }
    }
}
