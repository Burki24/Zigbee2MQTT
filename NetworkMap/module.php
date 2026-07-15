<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/AttributeArrayHelper.php';
require_once dirname(__DIR__) . '/libs/MQTTHelper.php';

/**
 * Visualisiert und analysiert eine von Zigbee2MQTT gelieferte RAW-Netzwerkkarte.
 */
class Zigbee2MQTTNetworkMap extends IPSModuleStrict
{
    use \Zigbee2MQTT\AttributeArrayHelper;
    use \Zigbee2MQTT\SendData;

    private const ATTRIBUTE_TOPOLOGY = 'Topology';
    private const ATTRIBUTE_SCAN = 'Scan';
    private const TIMER_SCAN_STATUS = 'UpdateScanStatus';
    private const SCAN_TIMER_INTERVAL = 5000;

    /**
     * Registriert Eigenschaften, Attribute und den Statustimer.
     */
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString(self::MQTT_BASE_TOPIC, '');
        $this->RegisterPropertyInteger('WeakLQIThreshold', 50);
        $this->RegisterPropertyString('DefaultLayout', 'concentric');
        $this->RegisterPropertyBoolean('ShowLabels', true);
        $this->RegisterAttributeArray(self::ATTRIBUTE_TOPOLOGY, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_SCAN, []);
        $this->RegisterScanStatusTimer();
    }

    /**
     * Aktiviert MQTT-Empfang und HTML-SDK-Kachel.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $baseTopic = trim($this->ReadPropertyString(self::MQTT_BASE_TOPIC), '/');
        $this->SetSummary($baseTopic);
        $this->SetVisualizationType(1);

        if ($baseTopic === '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE');
            $this->SetScanStatusTimerInterval(0);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
        $this->SetReceiveDataFilter('.*"Topic":"' . preg_quote($baseTopic, '/') . '/bridge/response/networkmap".*');
        $this->UpdateScanTimer();
    }

    /**
     * Baut die Konfigurationsoberfläche aus den gespeicherten Analysewerten auf.
     */
    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (!\is_array($form)) {
            return '{}';
        }

        $this->PopulateConfigurationForm($form);
        $json = json_encode($form);
        return \is_string($json) ? $json : '{}';
    }

    /**
     * Liefert die interaktive HTML-SDK-Netzwerkkarte.
     */
    public function GetVisualizationTile(): string
    {
        $html = file_get_contents(__DIR__ . '/module.html');
        $cytoscape = file_get_contents(__DIR__ . '/assets/cytoscape.min.js');
        $viewController = file_get_contents(__DIR__ . '/assets/cytoscape-view-controller.js');
        $themePath = dirname(__DIR__) . '/libs/Visualization/tiles/theme_support.html';
        $themeSupport = is_file($themePath) ? file_get_contents($themePath) : '';
        if (!\is_string($html)) {
            return '';
        }
        if (!\is_string($cytoscape)) {
            $cytoscape = '';
        }
        if (!\is_string($viewController)) {
            $viewController = '';
        }
        if (!\is_string($themeSupport)) {
            $themeSupport = '';
        }

        $initialData = json_encode([
            'scan'      => [],
            'summary'   => [],
            'threshold' => $this->ReadPropertyInteger('WeakLQIThreshold'),
            'view'      => $this->BuildVisualizationViewSettings(),
            'nodes'     => [],
            'links'     => []
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        return str_replace(
            ['__THEME_SUPPORT__', '__CYTOSCAPE__', '__CYTOSCAPE_VIEW_CONTROLLER__', '__INITIAL_DATA__'],
            [
                $themeSupport,
                $cytoscape,
                $viewController,
                \is_string($initialData) ? $initialData : '{}'
            ],
            $html
        );
    }

    /**
     * Startet einen vollständig asynchronen RAW-Netzwerkscan.
     */
    public function StartNetworkScan(bool $IncludeRoutes): bool
    {
        if ($this->IsScanRunning()) {
            $this->ShowMessage('Network scan already running', 'Wait for the current scan to finish or reset its status.');
            return false;
        }

        $transaction = mt_rand(10000, 999999);
        $scan = [
            'running'     => true,
            'routes'      => $IncludeRoutes,
            'started_at'  => time(),
            'finished_at' => 0,
            'transaction' => $transaction,
            'error'       => ''
        ];
        $this->WriteAttributeArray(self::ATTRIBUTE_SCAN, $scan);
        $this->SetScanStatusTimerInterval(self::SCAN_TIMER_INTERVAL);
        $this->UpdateVisibleData();

        $sent = $this->SendData('/bridge/request/networkmap', [
            'type'        => 'raw',
            'routes'      => $IncludeRoutes,
            'transaction' => $transaction
        ], 0);
        if ($sent !== true) {
            $scan['running'] = false;
            $scan['error'] = 'Request could not be sent';
            $this->WriteAttributeArray(self::ATTRIBUTE_SCAN, $scan);
            $this->SetScanStatusTimerInterval(0);
            $this->UpdateVisibleData();
            return false;
        }

        return true;
    }

    /**
     * Setzt nur einen hängengebliebenen lokalen Scanstatus zurück.
     */
    public function ResetNetworkScanStatus(): bool
    {
        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_SCAN);
        $scan['running'] = false;
        $scan['error'] = '';
        $this->WriteAttributeArray(self::ATTRIBUTE_SCAN, $scan);
        $this->SetScanStatusTimerInterval(0);
        $this->UpdateVisibleData();
        return true;
    }

    /**
     * Exportiert die letzte RAW-Topologie als JSON.
     */
    public function ExportNetworkMapRaw(): string
    {
        $json = json_encode($this->ReadAttributeArray(self::ATTRIBUTE_TOPOLOGY), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return \is_string($json) ? $json : '{}';
    }

    /**
     * Exportiert die letzte Topologie im Graphviz-DOT-Format.
     */
    public function ExportNetworkMapGraphviz(): string
    {
        $data = $this->ReadAttributeArray(self::ATTRIBUTE_TOPOLOGY);
        $lines = ['digraph ZigbeeNetwork {', '  graph [overlap=false, splines=true];', '  node [shape=box];'];
        foreach ($data['nodes'] ?? [] as $node) {
            if (!\is_array($node)) {
                continue;
            }
            $id = self::EscapeGraphValue((string) ($node['ieeeAddr'] ?? ''));
            $label = self::EscapeGraphValue((string) ($node['friendlyName'] ?? $id));
            $shape = match ((string) ($node['type'] ?? '')) {
                'Coordinator' => 'doublecircle',
                'Router'      => 'box',
                default       => 'ellipse'
            };
            $lines[] = \sprintf('  "%s" [label="%s", shape=%s];', $id, $label, $shape);
        }
        foreach ($data['links'] ?? [] as $link) {
            if (!\is_array($link)) {
                continue;
            }
            $source = self::EscapeGraphValue((string) ($link['source']['ieeeAddr'] ?? $link['sourceIeeeAddr'] ?? ''));
            $target = self::EscapeGraphValue((string) ($link['target']['ieeeAddr'] ?? $link['targetIeeeAddr'] ?? ''));
            if ($source === '' || $target === '') {
                continue;
            }
            $lqi = (int) ($link['lqi'] ?? $link['linkquality'] ?? 0);
            $routes = \is_array($link['routes'] ?? null) ? \count($link['routes']) : 0;
            $lines[] = \sprintf('  "%s" -> "%s" [label="LQI %d%s"];', $source, $target, $lqi, $routes > 0 ? ', routes ' . $routes : '');
        }
        $lines[] = '}';
        return implode(PHP_EOL, $lines);
    }

    /**
     * Exportiert die letzte Topologie im PlantUML-Format.
     */
    public function ExportNetworkMapPlantUML(): string
    {
        $data = $this->ReadAttributeArray(self::ATTRIBUTE_TOPOLOGY);
        $lines = ['@startuml', 'left to right direction'];
        $aliases = [];
        $index = 0;
        foreach ($data['nodes'] ?? [] as $node) {
            if (!\is_array($node)) {
                continue;
            }
            $ieee = (string) ($node['ieeeAddr'] ?? '');
            $alias = 'N' . $index++;
            $aliases[$ieee] = $alias;
            $label = str_replace(['"', "\r", "\n"], ['\\"', ' ', ' '], (string) ($node['friendlyName'] ?? $ieee));
            $lines[] = \sprintf('rectangle "%s" as %s', $label, $alias);
        }
        foreach ($data['links'] ?? [] as $link) {
            if (!\is_array($link)) {
                continue;
            }
            $source = (string) ($link['source']['ieeeAddr'] ?? $link['sourceIeeeAddr'] ?? '');
            $target = (string) ($link['target']['ieeeAddr'] ?? $link['targetIeeeAddr'] ?? '');
            if (!isset($aliases[$source], $aliases[$target])) {
                continue;
            }
            $lqi = (int) ($link['lqi'] ?? $link['linkquality'] ?? 0);
            $lines[] = \sprintf('%s --> %s : LQI %d', $aliases[$source], $aliases[$target], $lqi);
        }
        $lines[] = '@enduml';
        return implode(PHP_EOL, $lines);
    }

    /**
     * Schreibt alle Exportformate ohne Begrenzung durch den Symcon-Ausgabepuffer auf den Server.
     *
     * @return string Zielverzeichnis oder ein leerer String bei einem Fehler.
     */
    public function CreateNetworkMapExportFiles(): string
    {
        $directory = $this->GetExportDirectory();
        if (!$this->EnsureDirectory($directory)) {
            return '';
        }

        $timestamp = date('Ymd-His');
        $files = [
            $directory . DIRECTORY_SEPARATOR . 'zigbee-network-map-' . $timestamp . '.json' => $this->ExportNetworkMapRaw(),
            $directory . DIRECTORY_SEPARATOR . 'zigbee-network-map-' . $timestamp . '.dot'  => $this->ExportNetworkMapGraphviz(),
            $directory . DIRECTORY_SEPARATOR . 'zigbee-network-map-' . $timestamp . '.puml' => $this->ExportNetworkMapPlantUML()
        ];
        foreach ($files as $filename => $contents) {
            if (file_put_contents($filename, $contents, LOCK_EX) === false) {
                return '';
            }
        }

        return $directory;
    }

    /**
     * Verarbeitet Formular- und Timeraktionen.
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'StartQuickScan':
                $this->StartNetworkScan(false);
                break;
            case 'RequestFullScan':
                $this->UpdateFormField('FullScanWarning', 'visible', true);
                break;
            case 'ConfirmFullScan':
                $this->UpdateFormField('FullScanWarning', 'visible', false);
                $this->StartNetworkScan(true);
                break;
            case 'ResetScanStatus':
                $this->ResetNetworkScanStatus();
                break;
            case 'CreateExportFiles':
                if (!$this->HasTopology()) {
                    $this->ShowMessage('No network analysis available yet', 'Start a network analysis before creating exports.');
                    break;
                }
                $directory = $this->CreateNetworkMapExportFiles();
                if ($directory === '') {
                    $this->ShowMessage('Network map export failed', 'The export files could not be written.');
                    break;
                }
                $this->ShowMessage(
                    'Network map exports created',
                    $this->Translate('The export files were stored in:') . ' ' . $directory
                );
                break;
            case 'UpdateScanStatus':
                $this->UpdateVisibleData();
                break;
            case 'RefreshVisualization':
                $this->UpdateVisibleData();
                break;
            default:
                throw new InvalidArgumentException('Invalid ident: ' . $Ident);
        }
    }

    /**
     * Nimmt RAW-Netzwerkkartenantworten asynchron entgegen.
     */
    public function ReceiveData(string $JSONString): string
    {
        $buffer = json_decode($JSONString, true);
        if (!\is_array($buffer) || !isset($buffer['Topic'], $buffer['Payload'])) {
            return '';
        }

        $baseTopic = trim($this->ReadPropertyString(self::MQTT_BASE_TOPIC), '/');
        if ((string) $buffer['Topic'] !== $baseTopic . '/bridge/response/networkmap') {
            return '';
        }

        $payload = json_decode(self::DecodePayload((string) $buffer['Payload']), true);
        if (!\is_array($payload)) {
            return '';
        }

        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if (($payload['status'] ?? '') !== 'ok') {
            $this->FinishScanWithError((string) ($payload['error'] ?? 'Network scan failed'));
            return '';
        }
        if (($data['type'] ?? '') !== 'raw' || !\is_array($data['value'] ?? null)) {
            return '';
        }

        $topology = $this->NormalizeTopology($data['value']);
        $topology['captured_at'] = time();
        $topology['routes_included'] = (bool) ($data['routes'] ?? false);
        $this->WriteAttributeArray(self::ATTRIBUTE_TOPOLOGY, $topology);

        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_SCAN);
        $scan['running'] = false;
        $scan['routes'] = $topology['routes_included'];
        $scan['finished_at'] = time();
        $scan['error'] = '';
        $this->WriteAttributeArray(self::ATTRIBUTE_SCAN, $scan);
        $this->SetScanStatusTimerInterval(0);
        $this->UpdateVisibleData();
        return '';
    }

    /**
     * Ergänzt die statische Form um Tabellen- und Statuswerte.
     */
    private function PopulateConfigurationForm(array &$form): void
    {
        $data = $this->BuildFormData();
        $this->SetFormField($form, 'ScanStatus', 'caption', $data['status']);
        $this->SetFormField($form, 'StartQuickScan', 'enabled', !$data['running']);
        $this->SetFormField($form, 'RequestFullScan', 'enabled', !$data['running']);
        $this->SetFormField($form, 'ResetScanStatus', 'visible', $data['running']);
        foreach (['NodeList' => 'nodes', 'LinkList' => 'links', 'RouteList' => 'routes', 'FailureList' => 'failures'] as $field => $key) {
            $this->SetFormField($form, $field, 'values', $data[$key]);
            $this->SetFormField($form, $field, 'rowCount', min(12, max(3, \count($data[$key]) + 1)));
        }
    }

    /**
     * Aktualisiert eine geöffnete Form und die HTML-Kachel.
     */
    private function UpdateVisibleData(): void
    {
        $data = $this->BuildFormData();
        $this->UpdateFormField('ScanStatus', 'caption', $data['status']);
        $this->UpdateFormField('StartQuickScan', 'enabled', !$data['running']);
        $this->UpdateFormField('RequestFullScan', 'enabled', !$data['running']);
        $this->UpdateFormField('ResetScanStatus', 'visible', $data['running']);
        foreach (['NodeList' => 'nodes', 'LinkList' => 'links', 'RouteList' => 'routes', 'FailureList' => 'failures'] as $field => $key) {
            $this->UpdateFormField($field, 'values', json_encode($data[$key]));
            $this->UpdateFormField($field, 'rowCount', min(12, max(3, \count($data[$key]) + 1)));
        }
        $visualizationData = json_encode($this->BuildVisualizationData(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        if (\is_string($visualizationData)) {
            $this->UpdateVisualizationValue($visualizationData);
        }
    }

    /**
     * Erstellt normalisierte Formularwerte.
     */
    private function BuildFormData(): array
    {
        $topology = $this->ReadAttributeArray(self::ATTRIBUTE_TOPOLOGY);
        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_SCAN);
        $names = [];
        $nodes = [];
        $failures = [];
        foreach ($topology['nodes'] ?? [] as $node) {
            if (!\is_array($node)) {
                continue;
            }
            $ieee = (string) ($node['ieeeAddr'] ?? '');
            $name = (string) ($node['friendlyName'] ?? $ieee);
            $names[$ieee] = $name;
            $definition = \is_array($node['definition'] ?? null) ? $node['definition'] : [];
            $failed = \is_array($node['failed'] ?? null) ? $node['failed'] : [];
            $nodes[] = [
                'name'         => $name,
                'type'         => (string) ($node['type'] ?? ''),
                'model'        => (string) ($definition['model'] ?? $node['modelID'] ?? ''),
                'ieee'         => $ieee,
                'network'      => self::FormatNetworkAddress($node['networkAddress'] ?? ''),
                'last_seen'    => self::FormatTimestamp($node['lastSeen'] ?? 0),
                'scan_status'  => $failed === [] ? $this->Translate('OK') : implode(', ', $failed)
            ];
            foreach ($failed as $failure) {
                $failures[] = ['device' => $name, 'operation' => (string) $failure];
            }
        }

        $links = [];
        $routes = [];
        foreach ($topology['links'] ?? [] as $link) {
            if (!\is_array($link)) {
                continue;
            }
            $sourceIeee = (string) ($link['source']['ieeeAddr'] ?? $link['sourceIeeeAddr'] ?? '');
            $targetIeee = (string) ($link['target']['ieeeAddr'] ?? $link['targetIeeeAddr'] ?? '');
            $lqi = (int) ($link['lqi'] ?? $link['linkquality'] ?? 0);
            $linkRoutes = \is_array($link['routes'] ?? null) ? $link['routes'] : [];
            $links[] = [
                'source'       => $names[$sourceIeee] ?? $sourceIeee,
                'target'       => $names[$targetIeee] ?? $targetIeee,
                'lqi'          => $lqi,
                'relationship' => $this->FormatRelationship($link['relationship'] ?? ''),
                'depth'        => (int) ($link['depth'] ?? 0),
                'routes'       => \count($linkRoutes)
            ];
            foreach ($linkRoutes as $route) {
                if (!\is_array($route)) {
                    continue;
                }
                $routes[] = [
                    'router'      => $names[$targetIeee] ?? $targetIeee,
                    'destination' => self::FormatNetworkAddress($route['destinationAddress'] ?? ''),
                    'next_hop'    => self::FormatNetworkAddress($route['nextHopAddress'] ?? ''),
                    'status'      => $this->FormatRouteStatus($route['status'] ?? '')
                ];
            }
        }

        return [
            'running'  => (bool) ($scan['running'] ?? false),
            'status'   => $this->BuildScanStatus($scan, $topology),
            'nodes'    => $nodes,
            'links'    => $links,
            'routes'   => $routes,
            'failures' => $failures
        ];
    }

    /**
     * Erstellt das kompakte Datenmodell für die HTML-SDK-Kachel.
     */
    private function BuildVisualizationData(): array
    {
        $topology = $this->ReadAttributeArray(self::ATTRIBUTE_TOPOLOGY);
        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_SCAN);
        $nodes = [];
        $nodeIDs = [];
        foreach ($topology['nodes'] ?? [] as $node) {
            if (!\is_array($node)) {
                continue;
            }
            $id = (string) ($node['ieeeAddr'] ?? '');
            if ($id === '' || isset($nodeIDs[$id])) {
                continue;
            }
            $nodeIDs[$id] = true;
            $definition = \is_array($node['definition'] ?? null) ? $node['definition'] : [];
            $nodes[] = [
                'data' => [
                    'id'       => $id,
                    'label'    => (string) ($node['friendlyName'] ?? $node['ieeeAddr'] ?? ''),
                    'type'     => (string) ($node['type'] ?? ''),
                    'model'    => (string) ($definition['model'] ?? $node['modelID'] ?? ''),
                    'failed'   => \is_array($node['failed'] ?? null) ? implode(', ', $node['failed']) : ''
                ]
            ];
        }
        $links = [];
        $omittedLinks = 0;
        $index = 0;
        foreach ($topology['links'] ?? [] as $link) {
            if (!\is_array($link)) {
                continue;
            }
            $source = (string) ($link['source']['ieeeAddr'] ?? $link['sourceIeeeAddr'] ?? '');
            $target = (string) ($link['target']['ieeeAddr'] ?? $link['targetIeeeAddr'] ?? '');
            if ($source === '' || $target === '' || !isset($nodeIDs[$source], $nodeIDs[$target])) {
                $omittedLinks++;
                continue;
            }
            $links[] = [
                'data' => [
                    'id'     => 'L' . $index++,
                    'source' => $source,
                    'target' => $target,
                    'lqi'    => (int) ($link['lqi'] ?? $link['linkquality'] ?? 0),
                    'routes' => \is_array($link['routes'] ?? null) ? \count($link['routes']) : 0
                ]
            ];
        }

        $summary = $this->BuildSummary($topology);
        $summary['omitted_links'] = $omittedLinks;

        return [
            'scan'      => [
                'running'    => (bool) ($scan['running'] ?? false),
                'routes'     => (bool) ($scan['routes'] ?? false),
                'started_at' => (int) ($scan['started_at'] ?? 0),
                'status'     => $this->BuildScanStatus($scan, $topology)
            ],
            'summary'   => $summary,
            'threshold' => $this->ReadPropertyInteger('WeakLQIThreshold'),
            'view'      => $this->BuildVisualizationViewSettings(),
            'nodes'     => $nodes,
            'links'     => $links
        ];
    }

    /**
     * Liefert ausschließlich dauerhaft konfigurierbare Vorgaben für die Kachel.
     */
    private function BuildVisualizationViewSettings(): array
    {
        $layout = $this->ReadPropertyString('DefaultLayout');
        if (!\in_array($layout, ['concentric', 'breadthfirst', 'circle', 'grid', 'cose'], true)) {
            $layout = 'concentric';
        }

        return [
            'layout' => $layout,
            'labels' => $this->ReadPropertyBoolean('ShowLabels')
        ];
    }

    /**
     * Normalisiert nur die für Analyse und Export benötigten Topologiefelder.
     */
    private function NormalizeTopology(array $topology): array
    {
        return [
            'nodes' => \is_array($topology['nodes'] ?? null) ? array_values($topology['nodes']) : [],
            'links' => \is_array($topology['links'] ?? null) ? array_values($topology['links']) : []
        ];
    }

    /**
     * Erstellt Zähler für Kachel und Statuszeile.
     */
    private function BuildSummary(array $topology): array
    {
        $summary = ['devices' => 0, 'coordinators' => 0, 'routers' => 0, 'end_devices' => 0, 'links' => 0, 'routes' => 0, 'failures' => 0];
        foreach ($topology['nodes'] ?? [] as $node) {
            if (!\is_array($node)) {
                continue;
            }
            $summary['devices']++;
            $type = (string) ($node['type'] ?? '');
            if ($type === 'Coordinator') {
                $summary['coordinators']++;
            } elseif ($type === 'Router') {
                $summary['routers']++;
            } else {
                $summary['end_devices']++;
            }
            $summary['failures'] += \is_array($node['failed'] ?? null) ? \count($node['failed']) : 0;
        }
        foreach ($topology['links'] ?? [] as $link) {
            if (!\is_array($link)) {
                continue;
            }
            $summary['links']++;
            $summary['routes'] += \is_array($link['routes'] ?? null) ? \count($link['routes']) : 0;
        }
        return $summary;
    }

    /**
     * Formatiert den sichtbaren Scanstatus.
     */
    private function BuildScanStatus(array $scan, array $topology): string
    {
        if ((bool) ($scan['running'] ?? false)) {
            $seconds = max(0, time() - (int) ($scan['started_at'] ?? time()));
            return \sprintf(
                $this->Translate('Network scan running for %s (%s)'),
                self::FormatDuration($seconds),
                (bool) ($scan['routes'] ?? false) ? $this->Translate('with routes') : $this->Translate('without routes')
            );
        }
        if ((string) ($scan['error'] ?? '') !== '') {
            return $this->Translate('Last network scan failed') . ': ' . (string) $scan['error'];
        }
        if (($topology['captured_at'] ?? 0) > 0) {
            $summary = $this->BuildSummary($topology);
            return \sprintf(
                $this->Translate('Last analysis: %s | %d devices | %d links | %d routes | %d scan errors'),
                self::FormatTimestamp($topology['captured_at']),
                $summary['devices'],
                $summary['links'],
                $summary['routes'],
                $summary['failures']
            );
        }
        return $this->Translate('No network analysis available yet');
    }

    /**
     * Beendet einen Scan mit lesbarer Fehlermeldung.
     */
    private function FinishScanWithError(string $error): void
    {
        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_SCAN);
        $scan['running'] = false;
        $scan['finished_at'] = time();
        $scan['error'] = $error;
        $this->WriteAttributeArray(self::ATTRIBUTE_SCAN, $scan);
        $this->SetScanStatusTimerInterval(0);
        $this->UpdateVisibleData();
        $this->ShowMessage('Network scan failed', $error);
    }

    /**
     * Zeigt eine Meldung in der geöffneten Konfiguration.
     */
    private function ShowMessage(string $title, string $message): void
    {
        $this->UpdateFormField('MessageTitle', 'caption', $this->Translate($title));
        $this->UpdateFormField('MessageText', 'caption', $this->Translate($message));
        $this->UpdateFormField('MessagePopup', 'visible', true);
    }

    /**
     * Aktiviert den Statustimer nur während eines laufenden Scans.
     */
    private function UpdateScanTimer(): void
    {
        $this->SetScanStatusTimerInterval($this->IsScanRunning() ? self::SCAN_TIMER_INTERVAL : 0);
    }

    /**
     * Prüft den persistierten Scanstatus.
     */
    private function IsScanRunning(): bool
    {
        return (bool) ($this->ReadAttributeArray(self::ATTRIBUTE_SCAN)['running'] ?? false);
    }

    /**
     * Prüft, ob bereits eine verwertbare Topologie gespeichert ist.
     */
    private function HasTopology(): bool
    {
        $topology = $this->ReadAttributeArray(self::ATTRIBUTE_TOPOLOGY);
        return ($topology['nodes'] ?? []) !== [] || ($topology['links'] ?? []) !== [];
    }

    /**
     * Registriert den Statustimer tolerant gegenüber laufenden Modulupdates.
     */
    private function RegisterScanStatusTimer(): void
    {
        try {
            $this->RegisterTimer(self::TIMER_SCAN_STATUS, 0, 'IPS_RequestAction($_IPS["TARGET"], "UpdateScanStatus", true);');
        } catch (\Throwable) {
            // Timer operations can be temporarily unavailable while the module is being updated.
        }
    }

    /**
     * Aktualisiert den Statustimer tolerant gegenüber laufenden Modulupdates.
     */
    private function SetScanStatusTimerInterval(int $milliseconds): void
    {
        try {
            $this->SetTimerInterval(self::TIMER_SCAN_STATUS, $milliseconds);
        } catch (\Throwable) {
            // Timer operations can be temporarily unavailable while the module is being updated.
        }
    }

    /**
     * Setzt ein Feld rekursiv in einer Konfigurationsform.
     */
    private function SetFormField(array &$node, string $name, string $property, mixed $value): bool
    {
        if (($node['name'] ?? '') === $name) {
            $node[$property] = $value;
            return true;
        }
        foreach (['elements', 'actions', 'items', 'popup'] as $key) {
            if (!\is_array($node[$key] ?? null)) {
                continue;
            }
            if ($key === 'popup') {
                if ($this->SetFormField($node[$key], $name, $property, $value)) {
                    return true;
                }
                continue;
            }
            foreach ($node[$key] as &$child) {
                if (\is_array($child) && $this->SetFormField($child, $name, $property, $value)) {
                    return true;
                }
            }
            unset($child);
        }
        return false;
    }

    /**
     * Formatiert einen Zeitstempel in Sekunden oder Millisekunden für die Anzeige.
     */
    private static function FormatTimestamp(mixed $timestamp): string
    {
        $timestamp = (int) $timestamp;
        if ($timestamp > 1000000000000) {
            $timestamp = intdiv($timestamp, 1000);
        }
        return $timestamp > 0 ? date('d.m.Y H:i:s', $timestamp) : '';
    }

    /**
     * Formatiert eine Dauer als Stunden, Minuten und Sekunden.
     */
    private static function FormatDuration(int $seconds): string
    {
        return \sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Formatiert eine Zigbee-Netzwerkadresse als vierstelligen Hexadezimalwert.
     */
    private static function FormatNetworkAddress(mixed $address): string
    {
        if ($address === '' || $address === null) {
            return '';
        }
        return \sprintf('0x%04X', (int) $address);
    }

    /**
     * Übersetzt den numerischen Zigbee-Beziehungstyp für die Darstellung.
     */
    private function FormatRelationship(mixed $relationship): string
    {
        $value = match ((int) $relationship) {
            0       => 'Parent',
            1       => 'Child',
            2       => 'Sibling',
            3       => 'Previous child',
            default => (string) $relationship
        };
        return $this->Translate($value);
    }

    /**
     * Übersetzt den numerischen Zigbee-Routenstatus für die Darstellung.
     */
    private function FormatRouteStatus(mixed $status): string
    {
        $value = match ((int) $status) {
            0       => 'Active',
            1       => 'Discovery underway',
            2       => 'Discovery failed',
            3       => 'Inactive',
            4       => 'Validation underway',
            default => (string) $status
        };
        return $this->Translate($value);
    }

    /**
     * Maskiert einen Textwert für den sicheren Einsatz im Graphviz-DOT-Export.
     */
    private static function EscapeGraphValue(string $value): string
    {
        return str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', ' ', ' '], $value);
    }

    /**
     * Liefert das Zielverzeichnis für lokal abgelegte Netzwerkkartenexporte.
     */
    private function GetExportDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), '\\/') . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT' . DIRECTORY_SEPARATOR . 'networkmaps';
    }

    /**
     * Legt ein Exportverzeichnis bei Bedarf an.
     */
    private function EnsureDirectory(string $directory): bool
    {
        return is_dir($directory) || @mkdir($directory, 0777, true);
    }
}
