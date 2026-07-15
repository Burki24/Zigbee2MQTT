<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

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
}
