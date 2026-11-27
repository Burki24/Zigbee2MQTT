<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Prüft asynchrone Netzwerkkarten-Requests, Normalisierung und Exporte.
 */
class NetworkMapTest extends TestCase
{
    private Zigbee2MQTTNetworkMap $currentMap;
    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');

        parent::setUp();
    }

    public function testUnrelatedAndUnsolicitedScanResponsesAreIgnored(): void
    {
        $map = $this->createMapTestDouble();
        $map->StartNetworkScan(false);
        $scan = $map->readMapAttribute('Scan');
        $topology = $map->readMapAttribute('Topology');
        foreach ([null, $map->lastPayload['transaction'] + 1] as $transaction) {
            foreach (['ok', 'error'] as $status) {
                $payload = ['status' => $status, 'error' => 'old failure', 'data' => ['type' => 'raw', 'value' => ['nodes' => [], 'links' => []]]];
                if ($transaction !== null) {
                    $payload['transaction'] = $transaction;
                }
                $map->ReceiveData(json_encode(['Topic' => 'zigbee2mqtt/bridge/response/networkmap', 'Payload' => bin2hex(json_encode($payload))]));
                $this->assertSame($scan, $map->readMapAttribute('Scan'));
                $this->assertSame($topology, $map->readMapAttribute('Topology'));
            }
        }
        $response = $this->buildRawResponse(['nodes' => [], 'links' => []], false);
        $map->ReceiveData($response);
        $finished = $map->readMapAttribute('Scan');
        $map->ReceiveData($response);
        $this->assertSame($finished, $map->readMapAttribute('Scan'));
    }

    public function testLateFailureAfterResetDoesNotStopReplacementScan(): void
    {
        $map = $this->createMapTestDouble();
        $map->StartNetworkScan(false);
        $oldTransaction = $map->lastPayload['transaction'];
        $map->ResetNetworkScanStatus();
        $map->StartNetworkScan(true);
        $scan = $map->readMapAttribute('Scan');
        $map->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/response/networkmap',
            'Payload' => bin2hex(json_encode(['transaction' => $oldTransaction, 'status' => 'error', 'error' => 'old scan failed']))
        ]));
        $this->assertSame($scan, $map->readMapAttribute('Scan'));
        $map->ReceiveData($this->buildRawResponse(['nodes' => [], 'links' => []], true));
        $this->assertFalse($map->readMapAttribute('Scan')['running']);
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
        $this->assertStringNotContainsString('id="fullscreen"', $tile);
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

    public function testSelectingNetworkMapDeviceShowsOnlyItsCachedConnections(): void
    {
        $map = $this->createMapTestDouble();
        $topology = [
            'nodes' => [
                ['ieeeAddr' => '0x01', 'friendlyName' => 'Router'],
                ['ieeeAddr' => '0x02', 'friendlyName' => 'Router'],
                ['ieeeAddr' => '0x03', 'friendlyName' => 'Lamp'],
                ['ieeeAddr' => '0x04', 'friendlyName' => 'Sensor']
            ],
            'links' => [
                ['source' => ['ieeeAddr' => '0x01'], 'target' => ['ieeeAddr' => '0x03'], 'lqi' => 120],
                ['sourceIeeeAddr' => '0x03', 'targetIeeeAddr' => '0x01', 'linkquality' => 88],
                ['source' => ['ieeeAddr' => '0x02'], 'target' => ['ieeeAddr' => '0x03'], 'lqi' => 200]
            ]
        ];
        $map->ReceiveData($this->buildRawResponse($topology, false));
        $form = json_decode($map->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
        $nodeList = $this->findFormField($form, 'NodeList');
        $this->assertStringContainsString("'SelectNetworkMapNode'", $nodeList['onSelectionChanged'] ?? '');
        $heading = $this->findFormField($form, 'SelectedNodeLinksHeading');
        $this->assertSame('Label', $heading['type'] ?? null);
        $this->assertFalse($heading['visible']);
        $this->assertFalse($this->findFormField($form, 'SelectedNodeLinks')['visible']);
        $this->assertArrayNotHasKey('caption', $this->findFormField($form, 'SelectedNodeLinks'));
        $this->assertCount(3, $this->findFormField($form, 'LinkList')['values']);

        $storedTopology = $map->readMapAttribute('Topology');
        $storedScan = $map->readMapAttribute('Scan');
        $map->lastTopic = '';
        $map->updatedFields = [];
        $map->RequestAction('SelectNetworkMapNode', '0x01');
        $details = $map->updatedFields['SelectedNodeLinks'];
        $this->assertTrue($details['visible']);
        $this->assertTrue($map->updatedFields['SelectedNodeLinksHeading']['visible']);
        $this->assertSame(\sprintf($map->Translate('Connections for %s'), 'Router'), $map->updatedFields['SelectedNodeLinksHeading']['caption']);
        $links = json_decode($details['values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([120, 88], array_column($links, 'lqi'));
        $this->assertSame(['Router', 'Lamp'], array_column($links, 'source'));
        $this->assertSame(['Lamp', 'Router'], array_column($links, 'target'));
        $this->assertArrayNotHasKey('LinkList', $map->updatedFields);

        $map->RequestAction('SelectNetworkMapNode', '0x03');
        $this->assertSame(\sprintf($map->Translate('Connections for %s'), 'Lamp'), $map->updatedFields['SelectedNodeLinksHeading']['caption']);

        $map->RequestAction('SelectNetworkMapNode', '0x04');
        $this->assertFalse($map->updatedFields['SelectedNodeLinks']['visible']);
        $this->assertTrue($map->updatedFields['SelectedNodeLinksHeading']['visible']);
        $this->assertSame(\sprintf($map->Translate('No connections for %s in the last scan.'), 'Sensor'), $map->updatedFields['SelectedNodeLinksHeading']['caption']);
        $map->RequestAction('SelectNetworkMapNode', '0x99');
        $this->assertFalse($map->updatedFields['SelectedNodeLinks']['visible']);
        $this->assertFalse($map->updatedFields['SelectedNodeLinksHeading']['visible']);
        $this->assertSame('', $map->lastTopic);
        $this->assertSame($storedTopology, $map->readMapAttribute('Topology'));
        $this->assertSame($storedScan, $map->readMapAttribute('Scan'));

        $map->RequestAction('SelectNetworkMapNode', '0x01');
        $map->ReceiveData($this->buildRawResponse(['nodes' => [], 'links' => []], false));
        $this->assertFalse($map->updatedFields['SelectedNodeLinks']['visible']);
        $this->assertFalse($map->updatedFields['SelectedNodeLinksHeading']['visible']);
    }

    #[RunInSeparateProcess]
    public function testSelectingNetworkMapNodeOffersOnlyAnUnambiguousDeviceOnTheSameNetwork(): void
    {
        define('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN', 2);
        $mapID = IPS_CreateInstance('{C07834E7-9821-218C-C3EC-A4A87FFB8D7D}');
        $map = $this->createMapTestDouble($mapID);
        $splitter = IPS_CreateInstance('{6179ED6A-FC31-413C-BB8E-1204150CF376}');
        $otherSplitter = IPS_CreateInstance('{6179ED6A-FC31-413C-BB8E-1204150CF376}');
        IPS_ConnectInstance($mapID, $splitter);

        $address = '0x00124b00abcdef01';
        $unobservedAddress = '0x00124b00abcdef02';
        $makeDevice = static function (string $ieee, string $baseTopic, int $parent): int
        {
            $id = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
            if ($parent !== 0) {
                IPS_ConnectInstance($id, $parent);
            }
            IPS_SetProperty($id, 'IEEE', $ieee);
            IPS_SetProperty($id, 'MQTTBaseTopic', $baseTopic);
            IPS_SetProperty($id, 'MQTTTopic', 'Configured/Device');
            IPS_ApplyChanges($id);
            return $id;
        };
        $makeDevice($address, 'zigbee2mqtt', $otherSplitter);
        $makeDevice($address, 'other-network', $splitter);
        $makeDevice($address, 'zigbee2mqtt', 0);
        $matching = $makeDevice(strtoupper($address), 'zigbee2mqtt', $splitter);
        $makeDevice($unobservedAddress, 'zigbee2mqtt', $splitter);
        $device = IPS\InstanceManager::getInstanceInterface($matching);
        $device->BUFFER_MQTT_SUSPENDED = false;
        $map->ReceiveData($this->buildRawResponse([
            'nodes' => [
                ['ieeeAddr' => $address, 'friendlyName' => 'Cached device name'],
                ['ieeeAddr' => $unobservedAddress, 'friendlyName' => 'Unobserved device']
            ],
            'links' => []
        ], false));

        $form = json_decode($map->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
        $button = $this->findFormField($form, 'OpenSelectedNetworkDevice');
        $this->assertSame('OpenObjectButton', $button['type'] ?? null);
        $this->assertFalse($button['visible']);
        $this->assertSame(0, $button['objectID']);
        $this->assertArrayNotHasKey('onClick', $button);
        $this->assertFalse($this->findFormField($form, 'SelectedNodeObservedRoutePanel')['visible']);
        $map->lastTopic = '';
        $map->RequestAction('SelectNetworkMapNode', $address);
        $this->assertSame(['objectID' => $matching, 'visible' => true], $map->updatedFields['OpenSelectedNetworkDevice']);
        $this->assertTrue($map->updatedFields['SelectedNodeObservedRoutePanel']['visible']);
        $this->assertFalse($map->updatedFields['SelectedNodeObservedRouteTime']['visible']);

        $device->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/SymconExtension/route/' . $address,
            'Payload' => bin2hex(json_encode([
                'ieee_address' => $address,
                'observed_at'  => 1795767600000,
                'hops'         => [['network_address' => 0x2222, 'name' => 'Hall/Router']]
            ]))
        ]));
        $map->RequestAction('SelectNetworkMapNode', $address);
        $this->assertSame('Configured/Device → Hall/Router → Koordinator', $map->updatedFields['SelectedNodeObservedRoute']['caption']);
        $this->assertSame([
            'caption' => 'Beobachtet am ' . date('d.m.Y H:i:s', 1795767600),
            'visible' => true
        ], $map->updatedFields['SelectedNodeObservedRouteTime']);
        $map->RequestAction('SelectNetworkMapNode', $unobservedAddress);
        $this->assertTrue($map->updatedFields['SelectedNodeObservedRoutePanel']['visible']);
        $this->assertSame('No path observed yet.', $map->updatedFields['SelectedNodeObservedRoute']['caption']);
        $this->assertFalse($map->updatedFields['SelectedNodeObservedRouteTime']['visible']);
        $map->RequestAction('SelectNetworkMapNode', '0x00124b00abcdef99');
        $this->assertSame(['objectID' => 0, 'visible' => false], $map->updatedFields['OpenSelectedNetworkDevice']);
        $this->assertFalse($map->updatedFields['SelectedNodeObservedRoutePanel']['visible']);
        $this->assertFalse($map->updatedFields['SelectedNodeObservedRouteTime']['visible']);

        $duplicate = $makeDevice($address, 'zigbee2mqtt', $splitter);
        $map->RequestAction('SelectNetworkMapNode', $address);
        $this->assertSame(['objectID' => 0, 'visible' => false], $map->updatedFields['OpenSelectedNetworkDevice']);
        $this->assertFalse($map->updatedFields['SelectedNodeObservedRoutePanel']['visible']);
        IPS_DisconnectInstance($duplicate);
        $map->RequestAction('SelectNetworkMapNode', $address);
        $this->assertSame($matching, $map->updatedFields['OpenSelectedNetworkDevice']['objectID']);
        IPS_DisconnectInstance($mapID);
        $map->RequestAction('SelectNetworkMapNode', $address);
        $this->assertSame(['objectID' => 0, 'visible' => false], $map->updatedFields['OpenSelectedNetworkDevice']);
        $this->assertSame('', $map->lastTopic);
    }

    public function testSelectingNetworkMapLinkShowsOnlyItsCachedRoutes(): void
    {
        $map = $this->createMapTestDouble();
        $topology = [
            'nodes' => [
                ['ieeeAddr' => '0x01', 'friendlyName' => 'Router'],
                ['ieeeAddr' => '0x02', 'friendlyName' => 'Router'],
                ['ieeeAddr' => '0x03', 'friendlyName' => 'Lamp']
            ],
            'links' => [
                [
                    'source' => ['ieeeAddr' => '0x01'],
                    'target' => ['ieeeAddr' => '0x02'],
                    'routes' => [
                        ['destinationAddress' => 10, 'nextHopAddress' => 1, 'status' => 0]
                    ]
                ],
                [
                    'sourceIeeeAddr' => '0x02',
                    'targetIeeeAddr' => '0x01',
                    'routes'         => [
                        ['destinationAddress' => 20, 'nextHopAddress' => 2, 'status' => 3]
                    ]
                ],
                ['sourceIeeeAddr' => '0x01', 'targetIeeeAddr' => '0x03']
            ]
        ];
        $map->ReceiveData($this->buildRawResponse($topology, true));
        $form = json_decode($map->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
        $links = $this->findFormField($form, 'LinkList');
        $this->assertStringContainsString("'SelectNetworkMapLink'", $links['onSelectionChanged'] ?? '');
        $this->assertFalse($this->findFormField($form, 'SelectedLinkRoutesHeading')['visible']);
        $this->assertFalse($this->findFormField($form, 'SelectedLinkRoutes')['visible']);
        $nodeLinks = $this->findFormField($form, 'SelectedNodeLinks');
        $this->assertStringContainsString("'SelectNetworkMapNodeLink'", $nodeLinks['onSelectionChanged'] ?? '');
        $columns = array_column($nodeLinks['columns'], null, 'name');
        $this->assertFalse($columns['link_key']['visible']);
        $this->assertFalse($this->findFormField($form, 'SelectedNodeLinkRoutesHeading')['visible']);
        $this->assertFalse($this->findFormField($form, 'SelectedNodeLinkRoutes')['visible']);
        $this->assertCount(2, $this->findFormField($form, 'RouteList')['values']);
        $keys = array_column($links['values'], 'link_key');
        $this->assertCount(3, array_unique($keys));

        $storedTopology = $map->readMapAttribute('Topology');
        $storedScan = $map->readMapAttribute('Scan');
        $map->lastTopic = '';
        $map->updatedFields = [];
        $map->RequestAction('SelectNetworkMapLink', $keys[0]);
        $this->assertTrue($map->updatedFields['SelectedLinkRoutesHeading']['visible']);
        $this->assertTrue($map->updatedFields['SelectedLinkRoutes']['visible']);
        $outgoingRoutes = [
            ['router' => 'Router', 'destination' => '0x000A', 'next_hop' => '0x0001', 'status' => $map->Translate('Active')]
        ];
        $this->assertSame($outgoingRoutes, json_decode($map->updatedFields['SelectedLinkRoutes']['values'], true, 512, JSON_THROW_ON_ERROR));

        $map->RequestAction('SelectNetworkMapLink', $keys[1]);
        $incomingRoutes = [
            ['router' => 'Router', 'destination' => '0x0014', 'next_hop' => '0x0002', 'status' => $map->Translate('Inactive')]
        ];
        $this->assertSame($incomingRoutes, json_decode($map->updatedFields['SelectedLinkRoutes']['values'], true, 512, JSON_THROW_ON_ERROR));
        $map->RequestAction('SelectNetworkMapLink', $keys[2]);
        $this->assertFalse($map->updatedFields['SelectedLinkRoutes']['visible']);
        $this->assertTrue($map->updatedFields['SelectedLinkRoutesHeading']['visible']);
        $this->assertSame(
            \sprintf($map->Translate('No routes for %s → %s in the last scan.'), 'Router', 'Lamp'),
            $map->updatedFields['SelectedLinkRoutesHeading']['caption']
        );
        $this->assertArrayNotHasKey('RouteList', $map->updatedFields);

        $map->updatedFields = [];
        $map->RequestAction('SelectNetworkMapNode', '0x01');
        $nodeLinkRows = json_decode($map->updatedFields['SelectedNodeLinks']['values'], true, 512, JSON_THROW_ON_ERROR);
        $nodeLinkKeys = array_column($nodeLinkRows, 'link_key');
        $this->assertSame($keys, $nodeLinkKeys);
        $map->RequestAction('SelectNetworkMapNodeLink', $nodeLinkKeys[0]);
        $this->assertTrue($map->updatedFields['SelectedNodeLinkRoutesHeading']['visible']);
        $this->assertTrue($map->updatedFields['SelectedNodeLinkRoutes']['visible']);
        $this->assertSame($outgoingRoutes, json_decode($map->updatedFields['SelectedNodeLinkRoutes']['values'], true, 512, JSON_THROW_ON_ERROR));
        $map->RequestAction('SelectNetworkMapNodeLink', $nodeLinkKeys[1]);
        $this->assertSame($incomingRoutes, json_decode($map->updatedFields['SelectedNodeLinkRoutes']['values'], true, 512, JSON_THROW_ON_ERROR));
        $map->RequestAction('SelectNetworkMapNodeLink', $nodeLinkKeys[2]);
        $this->assertFalse($map->updatedFields['SelectedNodeLinkRoutes']['visible']);
        $this->assertTrue($map->updatedFields['SelectedNodeLinkRoutesHeading']['visible']);
        $this->assertSame(
            \sprintf($map->Translate('No routes for %s → %s in the last scan.'), 'Router', 'Lamp'),
            $map->updatedFields['SelectedNodeLinkRoutesHeading']['caption']
        );
        $map->RequestAction('SelectNetworkMapNodeLink', $nodeLinkKeys[0]);
        $map->RequestAction('SelectNetworkMapNode', '0x02');
        $this->assertFalse($map->updatedFields['SelectedNodeLinkRoutesHeading']['visible']);
        $this->assertFalse($map->updatedFields['SelectedNodeLinkRoutes']['visible']);
        $this->assertArrayNotHasKey('SelectedLinkRoutesHeading', $map->updatedFields);
        $this->assertArrayNotHasKey('SelectedLinkRoutes', $map->updatedFields);
        $this->assertSame('', $map->lastTopic);
        $this->assertSame($storedTopology, $map->readMapAttribute('Topology'));
        $this->assertSame($storedScan, $map->readMapAttribute('Scan'));

        $map->RequestAction('SelectNetworkMapLink', $keys[0]);
        $map->RequestAction('SelectNetworkMapNodeLink', $nodeLinkKeys[0]);
        $map->ReceiveData($this->buildRawResponse([
            'nodes' => $topology['nodes'],
            'links' => array_reverse($topology['links'])
        ], true));
        $this->assertFalse($map->updatedFields['SelectedLinkRoutesHeading']['visible']);
        $this->assertFalse($map->updatedFields['SelectedLinkRoutes']['visible']);
        $this->assertFalse($map->updatedFields['SelectedNodeLinkRoutesHeading']['visible']);
        $this->assertFalse($map->updatedFields['SelectedNodeLinkRoutes']['visible']);
        $map->RequestAction('SelectNetworkMapLink', $keys[0]);
        $this->assertFalse($map->updatedFields['SelectedLinkRoutesHeading']['visible']);
        $this->assertFalse($map->updatedFields['SelectedLinkRoutes']['visible']);
    }

    public function testRefreshingNetworkMapLinkReadsOneRouterAndUpdatesOnlyThatDirection(): void
    {
        $map = $this->createMapTestDouble();
        $source = '0x00124b0000000101';
        $target = '0x00124b0000000202';
        $map->ReceiveData($this->buildRawResponse([
            'nodes' => [
                ['ieeeAddr' => $source, 'type' => 'Router', 'networkAddress' => 0x1234],
                ['ieeeAddr' => $target, 'type' => 'Coordinator', 'networkAddress' => 0]
            ],
            'links' => [
                ['sourceIeeeAddr' => $source, 'targetIeeeAddr' => $target],
                ['sourceIeeeAddr' => $target, 'targetIeeeAddr' => $source, 'routes' => [
                    ['destinationAddress' => 0x1234, 'nextHopAddress' => 0, 'status' => 0]
                ]]
            ]
        ], false));
        $scan = $map->readMapAttribute('Scan');
        $original = $map->readMapAttribute('Topology');
        $form = json_decode($map->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
        $button = $this->findFormField($form, 'RefreshSelectedNodeLinkRoutes');
        $this->assertStringContainsString("'RefreshNetworkMapLinkRoutes'", $button['onClick'] ?? '');
        $this->assertFalse($button['visible']);
        $linkKey = $this->findFormField($form, 'LinkList')['values'][0]['link_key'];
        $map->lastTopic = '';
        $map->RequestAction('SelectNetworkMapNode', $source);
        $map->RequestAction('SelectNetworkMapNodeLink', $linkKey);
        $this->assertSame('', $map->lastTopic);
        $this->assertTrue($map->updatedFields['RefreshSelectedNodeLinkRoutes']['visible']);

        $map->RequestAction('RefreshNetworkMapLinkRoutes', $linkKey);
        $this->assertSame('/bridge/request/action', $map->lastTopic);
        $this->assertSame(0, $map->lastTimeout);
        $this->assertSame('raw', $map->lastPayload['action']);
        $this->assertSame([
            'ieee_address'    => $target,
            'network_address' => 0,
            'profile_id'      => 0,
            'cluster_key'     => 0x0032,
            'zdo_params'      => [0]
        ], $map->lastPayload['params']);
        $first = $map->lastPayload['transaction'];
        $map->RequestAction('RefreshNetworkMapLinkRoutes', $linkKey);
        $this->assertSame($first, $map->lastPayload['transaction']);
        $this->assertFalse($map->StartNetworkScan(true));
        $this->assertSame('/bridge/request/action', $map->lastTopic);

        $page = ['routingTableEntries' => 2, 'startIndex' => 0, 'entryList' => [
            ['destinationAddress' => 0x5555, 'nextHopAddress' => 0x4321, 'status' => 'ACTIVE']
        ]];
        $map->ReceiveData($this->buildRouteResponse('unrelated', [0, $page]));
        $this->assertSame($first, $map->lastPayload['transaction']);
        $map->ReceiveData($this->buildRouteResponse($first, [0, $page]));
        $second = $map->lastPayload['transaction'];
        $this->assertNotSame($first, $second);
        $this->assertSame('/bridge/request/action', $map->lastTopic);
        $this->assertSame($target, $map->lastPayload['params']['ieee_address']);
        $this->assertSame([1], $map->lastPayload['params']['zdo_params']);
        $this->assertSame($original, $map->readMapAttribute('Topology'));

        $map->ReceiveData($this->buildRouteResponse($first, [0, $page]));
        $this->assertSame($second, $map->lastPayload['transaction']);
        $route = ['destinationAddress' => 0x6666, 'nextHopAddress' => 0x1234, 'status' => 'INACTIVE'];
        $map->ReceiveData($this->buildRouteResponse($second, [0, [
            'routingTableEntries' => 2, 'startIndex' => 1, 'entryList' => [$route]
        ]]));
        $topology = $map->readMapAttribute('Topology');
        $this->assertSame([$route], $topology['links'][0]['routes']);
        $this->assertSame($original['links'][1], $topology['links'][1]);
        $this->assertSame($original['captured_at'], $topology['captured_at']);
        $this->assertSame($scan, $map->readMapAttribute('Scan'));
        $rows = json_decode($map->updatedFields['SelectedNodeLinkRoutes']['values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([[
            'router' => $target, 'destination' => '0x6666', 'next_hop' => '0x1234', 'status' => $map->Translate('Inactive')
        ]], $rows);
        $this->assertTrue($map->updatedFields['RefreshSelectedNodeLinkRoutes']['enabled']);
    }

    public function testFailedLinkRefreshPreservesTheCachedRoutesAndCanBeRetried(): void
    {
        $map = $this->createMapTestDouble();
        $source = '0x00124b0000000101';
        $target = '0x00124b0000000202';
        $map->ReceiveData($this->buildRawResponse([
            'nodes' => [
                ['ieeeAddr' => $source, 'type' => 'Router', 'networkAddress' => 0x1234],
                ['ieeeAddr' => $target, 'type' => 'Router', 'networkAddress' => 0x2345]
            ],
            'links' => [[
                'sourceIeeeAddr' => $source, 'targetIeeeAddr' => $target, 'routes' => [
                    ['destinationAddress' => 0x3456, 'nextHopAddress' => 0x1234, 'status' => 0]
                ]
            ]]
        ], true));
        $original = $map->readMapAttribute('Topology');
        $form = json_decode($map->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
        $linkKey = $this->findFormField($form, 'LinkList')['values'][0]['link_key'];
        $map->RequestAction('SelectNetworkMapLink', $linkKey);
        $map->RequestAction('RefreshNetworkMapLinkRoutes', $linkKey);
        $map->ReceiveData($this->buildRouteResponse($map->lastPayload['transaction'], [0x84, null]));
        $this->assertTrue($map->updatedFields['MessagePopup']['visible']);
        $this->assertSame(
            $map->Translate('The router does not support routing table requests.'),
            $map->updatedFields['MessageText']['caption']
        );
        $this->assertSame($original, $map->readMapAttribute('Topology'));
        $this->assertTrue($map->updatedFields['RefreshSelectedLinkRoutes']['enabled']);

        $map->RequestAction('RefreshNetworkMapLinkRoutes', $linkKey);
        $transaction = $map->lastPayload['transaction'];
        $map->ReceiveData($this->buildRouteResponse($transaction, [], '{"target":9029,"zdoResponseClusterId":32818} timed out after 15000ms'));
        $this->assertSame(
            $map->Translate('The router did not respond to the routing table request.'),
            $map->updatedFields['MessageText']['caption']
        );
        $this->assertSame($transaction, $map->lastPayload['transaction']);
        $this->assertSame($original, $map->readMapAttribute('Topology'));

        $map->RequestAction('RefreshNetworkMapLinkRoutes', $linkKey);
        $expired = $map->readMapAttribute('RoutingTableRefresh');
        $expired['deadline'] = time() - 1;
        $map->writeMapAttribute('RoutingTableRefresh', $expired);
        $map->RequestAction('CheckRoutingTableRefresh', true);
        $this->assertFalse($map->updatedFields['LinkRouteRefreshStatus']['visible']);
        $this->assertSame(
            $map->Translate('Zigbee2MQTT did not answer the routing table request.'),
            $map->updatedFields['MessageText']['caption']
        );
        $this->assertTrue($map->updatedFields['RefreshSelectedLinkRoutes']['enabled']);
        $this->assertSame($original, $map->readMapAttribute('Topology'));

        $map->RequestAction('RefreshNetworkMapLinkRoutes', $linkKey);
        $emptyPage = [0, ['routingTableEntries' => 0, 'startIndex' => 0, 'entryList' => []]];
        $map->ReceiveData($this->buildRouteResponse($expired['transaction'], $emptyPage));
        $this->assertSame($original, $map->readMapAttribute('Topology'));
        $map->ReceiveData($this->buildRouteResponse($map->lastPayload['transaction'], $emptyPage));
        $this->assertSame([], $map->readMapAttribute('Topology')['links'][0]['routes']);
        $this->assertFalse($map->updatedFields['SelectedLinkRoutes']['visible']);
        $this->assertTrue($map->updatedFields['SelectedLinkRoutesHeading']['visible']);
    }

    #[RunInSeparateProcess]
    public function testNativeFullscreenKeepsTheInteractiveTileOnSymcon91(): void
    {
        define('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN', 2);
        $id = IPS_CreateInstance('{C07834E7-9821-218C-C3EC-A4A87FFB8D7D}');
        IPS_ApplyChanges($id);

        $map = IPS\InstanceManager::getInstanceInterface($id);
        $moduleStub = (new ReflectionProperty(IPSModuleStrict::class, 'module'))->getValue($map);
        $this->assertSame(2, (new ReflectionProperty(IPSModule::class, 'visualizationType'))->getValue($moduleStub));

        $tile = $map->GetVisualizationTile();
        $this->assertStringContainsString('class CytoscapeViewController', $tile);
        $this->assertStringContainsString("requestAction('RefreshVisualization', true)", $tile);
    }

    public function testFailedResponseStopsRunningScan(): void
    {
        $map = $this->createMapTestDouble();
        $map->StartNetworkScan(false);
        $map->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/response/networkmap',
            'Payload' => bin2hex(json_encode([
                'transaction' => $map->lastPayload['transaction'],
                'status'      => 'error',
                'error'       => 'scan failed'
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

    private function createMapTestDouble(int $instanceID = 900002): Zigbee2MQTTNetworkMap
    {
        $map = new class($instanceID) extends Zigbee2MQTTNetworkMap {
            public string $lastTopic = '';
            public array $lastPayload = [];
            public int $lastTimeout = -1;
            public mixed $lastVisualizationValue = null;
            public array $updatedFields = [];
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
                $this->updatedFields[$Field][$Parameter] = $Value;
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

            public function writeMapAttribute(string $name, array $value): void
            {
                $this->WriteAttributeArray($name, $value);
            }
        };
        $map->Create();
        $this->currentMap = $map;
        return $map;
    }

    private function buildRawResponse(array $topology, bool $routes): string
    {
        if (!($this->currentMap->readMapAttribute('Scan')['running'] ?? false)) {
            $this->currentMap->StartNetworkScan($routes);
        }
        return json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/response/networkmap',
            'Payload' => bin2hex(json_encode([
                'status'      => 'ok',
                'transaction' => $this->currentMap->lastPayload['transaction'],
                'data'        => [
                    'type'   => 'raw',
                    'routes' => $routes,
                    'value'  => $topology
                ]
            ]))
        ]);
    }

    private function buildRouteResponse(string $transaction, array $data, string $error = ''): string
    {
        $response = ['transaction' => $transaction, 'status' => 'ok', 'data' => $data];
        if ($error !== '') {
            $response['status'] = 'error';
            $response['error'] = $error;
        }
        return json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/response/action',
            'Payload' => bin2hex(json_encode($response))
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
