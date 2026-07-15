<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * Prüft asynchrone Netzwerkkarten-Requests, Normalisierung und Exporte.
 */
class NetworkMapTest extends TestCase
{
    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');

        parent::setUp();
    }

    public function testQuickScanSendsAsynchronousRawRequestWithoutRoutes(): void
    {
        $map = $this->createMapTestDouble();

        $this->assertTrue($map->StartNetworkScan(false));
        $this->assertSame('/bridge/request/networkmap', $map->lastTopic);
        $this->assertSame('raw', $map->lastPayload['type']);
        $this->assertFalse($map->lastPayload['routes']);
        $this->assertIsInt($map->lastPayload['transaction']);
        $this->assertSame(0, $map->lastTimeout);
        $this->assertTrue($map->readMapAttribute('Scan')['running']);
    }

    public function testFullScanSendsAsynchronousRawRequestWithRoutes(): void
    {
        $map = $this->createMapTestDouble();

        $this->assertTrue($map->StartNetworkScan(true));
        $this->assertTrue($map->lastPayload['routes']);
    }

    public function testRunningScanPreventsAnotherRequest(): void
    {
        $map = $this->createMapTestDouble();

        $this->assertTrue($map->StartNetworkScan(false));
        $firstTransaction = $map->lastPayload['transaction'];
        $this->assertFalse($map->StartNetworkScan(true));
        $this->assertSame($firstTransaction, $map->lastPayload['transaction']);
        $this->assertFalse($map->lastPayload['routes']);
    }

    public function testRawResponseFinishesScanAndBuildsExports(): void
    {
        $map = $this->createMapTestDouble();
        $map->StartNetworkScan(true);
        $map->ReceiveData($this->buildRawResponse([
            'nodes' => [
                [
                    'ieeeAddr'       => '0x0000000000000001',
                    'friendlyName'   => 'Coordinator',
                    'type'           => 'Coordinator',
                    'networkAddress' => 0
                ],
                [
                    'ieeeAddr'       => '0x0000000000000002',
                    'friendlyName'   => 'Hall/Router',
                    'type'           => 'Router',
                    'networkAddress' => 4660,
                    'definition'     => ['model' => 'TEST-ROUTER']
                ]
            ],
            'links' => [
                [
                    'source'       => ['ieeeAddr' => '0x0000000000000002', 'networkAddress' => 4660],
                    'target'       => ['ieeeAddr' => '0x0000000000000001', 'networkAddress' => 0],
                    'relationship' => 2,
                    'depth'        => 1,
                    'lqi'          => 170,
                    'routes'       => [
                        ['destinationAddress' => 22136, 'nextHopAddress' => 4660, 'status' => 0]
                    ]
                ]
            ]
        ], true));

        $this->assertFalse($map->readMapAttribute('Scan')['running']);
        $this->assertTrue($map->readMapAttribute('Topology')['routes_included']);
        $this->assertStringContainsString('Hall/Router', $map->ExportNetworkMapGraphviz());
        $this->assertStringContainsString('LQI 170', $map->ExportNetworkMapGraphviz());
        $this->assertStringContainsString('@startuml', $map->ExportNetworkMapPlantUML());
        $this->assertStringContainsString('"nodes"', $map->ExportNetworkMapRaw());

        $directory = $map->CreateNetworkMapExportFiles();
        $this->assertDirectoryExists($directory);
        $this->assertNotSame([], glob($directory . DIRECTORY_SEPARATOR . 'zigbee-network-map-*.json'));
        $this->assertNotSame([], glob($directory . DIRECTORY_SEPARATOR . 'zigbee-network-map-*.dot'));
        $this->assertNotSame([], glob($directory . DIRECTORY_SEPARATOR . 'zigbee-network-map-*.puml'));
    }

    public function testConfigurationFormAndTileUseStoredTopology(): void
    {
        $map = $this->createMapTestDouble();
        $map->ReceiveData($this->buildRawResponse([
            'nodes' => [
                [
                    'ieeeAddr'       => '0x0000000000000001',
                    'friendlyName'   => 'Coordinator',
                    'type'           => 'Coordinator',
                    'networkAddress' => 0
                ]
            ],
            'links' => []
        ], false));

        $form = json_decode($map->GetConfigurationForm(), true);
        $nodeList = $this->findFormField($form, 'NodeList');
        $this->assertNotNull($nodeList);
        $this->assertSame('Coordinator', $nodeList['values'][0]['name']);
        $this->assertNotNull($this->findFormField($form, 'DefaultLayout'));
        $this->assertNotNull($this->findFormField($form, 'ShowLabels'));

        $tile = $map->GetVisualizationTile();
        $this->assertStringContainsString('cytoscape', $tile);
        $this->assertStringNotContainsString('"label":"Coordinator"', $tile);
        $this->assertStringContainsString('--tile-title-clearance: 58px', $tile);
        $this->assertStringContainsString('tools.hidden = !hasTopology', $tile);
        $this->assertStringContainsString('--z2m-font-family', $tile);
        $this->assertStringContainsString('height: 100vh', $tile);
        $this->assertStringContainsString('new ResizeObserver(scheduleGraphResize)', $tile);
        $this->assertStringContainsString("requestAction('RefreshVisualization', true)", $tile);
        $this->assertStringContainsString("window.addEventListener('pageshow', requestCurrentState)", $tile);
        $this->assertStringContainsString('setTimeout(requestCurrentState, 50)', $tile);
        $this->assertStringContainsString('document.documentElement.requestFullscreen()', $tile);
        $this->assertStringContainsString("fullscreenButton.addEventListener('click', toggleFullscreen)", $tile);
        $this->assertStringContainsString('prepareFullscreenTheme()', $tile);
        $this->assertStringContainsString('html:fullscreen::backdrop', $tile);
        $this->assertStringContainsString('--fullscreen-background', $tile);
        $this->assertStringContainsString('class CytoscapeViewController', $tile);
        $this->assertStringContainsString('viewController.runLayout(selectedLayout)', $tile);
        $this->assertStringContainsString("searchFields: ['id', 'label', 'model', 'type']", $tile);
        $this->assertStringContainsString('focusSelectedNeighborhood()', $tile);
        $this->assertStringContainsString('<option value="cose">Netzstruktur</option>', $tile);
        $this->assertStringContainsString('applyViewDefaults()', $tile);
        $this->assertStringContainsString('"view":{"layout":"concentric","labels":true}', $tile);
        $this->assertStringNotContainsString('__INITIAL_DATA__', $tile);
        $this->assertStringNotContainsString('__CYTOSCAPE__', $tile);
        $this->assertStringNotContainsString('__CYTOSCAPE_VIEW_CONTROLLER__', $tile);
        $this->assertStringNotContainsString('__THEME_SUPPORT__', $tile);
    }

    public function testFailedResponseStopsRunningScan(): void
    {
        $map = $this->createMapTestDouble();
        $map->StartNetworkScan(false);
        $map->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/response/networkmap',
            'Payload' => bin2hex(json_encode([
                'status' => 'error',
                'error'  => 'scan failed'
            ]))
        ]));

        $scan = $map->readMapAttribute('Scan');
        $this->assertFalse($scan['running']);
        $this->assertSame('scan failed', $scan['error']);
    }

    public function testVisibleDataUpdatesTileWithJsonString(): void
    {
        $map = $this->createMapTestDouble();

        $this->assertTrue($map->StartNetworkScan(false));
        $this->assertIsString($map->lastVisualizationValue);
        $visualizationData = json_decode($map->lastVisualizationValue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($visualizationData['scan']['running']);
        $this->assertStringContainsString('JSON.parse(data)', $map->GetVisualizationTile());
    }

    public function testVisualizationCanRequestCurrentStoredState(): void
    {
        $map = $this->createMapTestDouble();
        $map->ReceiveData($this->buildRawResponse([
            'nodes' => [
                [
                    'ieeeAddr'     => '0x0000000000000001',
                    'friendlyName' => 'Coordinator',
                    'type'         => 'Coordinator'
                ]
            ],
            'links' => []
        ], false));

        $map->RequestAction('RefreshVisualization', true);

        $this->assertIsString($map->lastVisualizationValue);
        $visualizationData = json_decode($map->lastVisualizationValue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Coordinator', $visualizationData['nodes'][0]['data']['label']);
        $this->assertSame([], $visualizationData['links']);
    }

    public function testInitialTileSizeDoesNotGrowWithStoredTopology(): void
    {
        $map = $this->createMapTestDouble();
        $emptyTileLength = strlen($map->GetVisualizationTile());
        $nodes = [];
        $links = [];
        for ($index = 0; $index < 100; $index++) {
            $nodes[] = [
                'ieeeAddr'     => \sprintf('0x%016x', $index + 1),
                'friendlyName' => 'Device ' . $index,
                'type'         => $index === 0 ? 'Coordinator' : 'Router'
            ];
            if ($index > 0) {
                $links[] = [
                    'source' => ['ieeeAddr' => \sprintf('0x%016x', $index + 1)],
                    'target' => ['ieeeAddr' => '0x0000000000000001'],
                    'lqi'    => 100
                ];
            }
        }
        $map->ReceiveData($this->buildRawResponse(['nodes' => $nodes, 'links' => $links], false));

        $this->assertSame($emptyTileLength, strlen($map->GetVisualizationTile()));
    }

    public function testVisualizationOmitsLinksToUnknownNodes(): void
    {
        $map = $this->createMapTestDouble();
        $map->ReceiveData($this->buildRawResponse([
            'nodes' => [
                ['ieeeAddr' => '0x0000000000000001', 'friendlyName' => 'Coordinator', 'type' => 'Coordinator']
            ],
            'links' => [
                [
                    'source' => ['ieeeAddr' => '0x0000000000000001'],
                    'target' => ['ieeeAddr' => '0x0000000000009999'],
                    'lqi'    => 100
                ]
            ]
        ], false));

        $visualizationData = json_decode($map->lastVisualizationValue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $visualizationData['nodes']);
        $this->assertCount(0, $visualizationData['links']);
        $this->assertSame(1, $visualizationData['summary']['omitted_links']);
    }

    private function createMapTestDouble(): Zigbee2MQTTNetworkMap
    {
        $map = new class(900002) extends Zigbee2MQTTNetworkMap {
            public string $lastTopic = '';
            public array $lastPayload = [];
            public int $lastTimeout = -1;
            public mixed $lastVisualizationValue = null;
            private string $baseTopic = 'zigbee2mqtt';

            protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
            {
                $this->lastTopic = $Topic;
                $this->lastPayload = $Payload;
                $this->lastTimeout = $Timeout;
                return true;
            }

            protected function ReadPropertyString(string $Name): string
            {
                if ($Name === self::MQTT_BASE_TOPIC) {
                    return $this->baseTopic;
                }
                return parent::ReadPropertyString($Name);
            }

            protected function ReadPropertyInteger(string $Name): int
            {
                if ($Name === 'WeakLQIThreshold') {
                    return 50;
                }
                return parent::ReadPropertyInteger($Name);
            }

            protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
            {
                return true;
            }

            protected function UpdateVisualizationValue(mixed $Value)
            {
                $this->lastVisualizationValue = $Value;
                return true;
            }

            public function readMapAttribute(string $name): array
            {
                return $this->ReadAttributeArray($name);
            }
        };
        $map->Create();
        return $map;
    }

    private function buildRawResponse(array $topology, bool $routes): string
    {
        return json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/response/networkmap',
            'Payload' => bin2hex(json_encode([
                'status' => 'ok',
                'data'   => [
                    'type'   => 'raw',
                    'routes' => $routes,
                    'value'  => $topology
                ]
            ]))
        ]);
    }

    private function findFormField(array $node, string $name): ?array
    {
        if (($node['name'] ?? null) === $name) {
            return $node;
        }
        foreach (['elements', 'actions', 'items', 'popup'] as $childKey) {
            $children = $node[$childKey] ?? null;
            if (!\is_array($children)) {
                continue;
            }
            if ($childKey === 'popup') {
                $result = $this->findFormField($children, $name);
                if ($result !== null) {
                    return $result;
                }
                continue;
            }
            foreach ($children as $child) {
                if (!\is_array($child)) {
                    continue;
                }
                $result = $this->findFormField($child, $name);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }
}
