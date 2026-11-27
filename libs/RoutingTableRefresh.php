<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Reads the pages of one Zigbee routing table through the Zigbee2MQTT raw action.
 */
trait RoutingTableRefresh
{
    protected const ROUTING_TABLE_RESPONSE_TOPIC = '/bridge/response/action';
    private const ROUTING_TABLE_REFRESH = 'RoutingTableRefresh';
    private const ROUTING_TABLE_TIMEOUT = 60;

    abstract protected function RoutingTableRefreshStateChanged(bool $running): void;
    abstract protected function RoutingTableRefreshCompleted(string $context, array $routes): void;
    abstract protected function RoutingTableRefreshFailed(string $message): void;

    private function RegisterRoutingTableRefresh(): void
    {
        $this->RegisterAttributeArray(self::ROUTING_TABLE_REFRESH, []);
        try {
            $this->RegisterTimer(self::ROUTING_TABLE_REFRESH, 0, '@IPS_RequestAction($_IPS["TARGET"], "CheckRoutingTableRefresh", true);');
        } catch (\Throwable) {
            // The instance interface can be unavailable during a module reload.
        }
    }

    private function WithRoutingTableLock(\Closure $operation): mixed
    {
        $lock = __CLASS__ . '.RoutingTable.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 1000)) {
            return null;
        }

        try {
            return $operation();
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }

    private function HasRoutingTableRefresh(): bool
    {
        return $this->ReadAttributeArray(self::ROUTING_TABLE_REFRESH) !== [];
    }

    private function ClearRoutingTableRefresh(): void
    {
        $this->WriteAttributeArray(self::ROUTING_TABLE_REFRESH, []);
        $this->SetRoutingTableRefreshTimer(0);
    }

    private function StartRoutingTableRefresh(array $device, string $context): void
    {
        $this->RoutingTableRefreshStateChanged(true);
        $this->SetRoutingTableRefreshTimer(2000);
        $this->SendRoutingTablePage([
            'device'     => $device,
            'context'    => $context,
            'base_topic' => trim($this->ReadPropertyString(self::MQTT_BASE_TOPIC), '/'),
            'deadline'   => time() + self::ROUTING_TABLE_TIMEOUT,
            'offset'     => 0,
            'total'      => null,
            'routes'     => []
        ]);
    }

    private function SendRoutingTablePage(array $request): void
    {
        // Zigbee2MQTT echoes this ID. Use a fresh one for every page and avoid numeric bridge transactions.
        $request['transaction'] = 'symcon-routing-' . bin2hex(random_bytes(12));
        $this->WriteAttributeArray(self::ROUTING_TABLE_REFRESH, $request);
        if ($this->SendData('/bridge/request/action', [
            'action'      => 'raw',
            'params'      => [
                'ieee_address'    => $request['device']['ieee'],
                'network_address' => $request['device']['address'],
                'profile_id'      => 0,
                'cluster_key'     => 0x0032,
                'zdo_params'      => [$request['offset']]
            ],
            'transaction' => $request['transaction']
        ], 0) !== true) {
            $this->FinishRoutingTableRefresh('The routing table request could not be sent.');
        }
    }

    private function SetRoutingTableRefreshTimer(int $milliseconds): void
    {
        try {
            $this->SetTimerInterval(self::ROUTING_TABLE_REFRESH, $milliseconds);
        } catch (\Throwable) {
            // The instance interface can be unavailable during a module reload.
        }
    }

    private function ReceiveRoutingTableResponse(array $payload): void
    {
        $this->WithRoutingTableLock(fn () => $this->ProcessRoutingTableResponse($payload));
    }

    private function ProcessRoutingTableResponse(array $payload): void
    {
        $request = $this->ReadAttributeArray(self::ROUTING_TABLE_REFRESH);
        if ($request === [] || ($payload['transaction'] ?? null) !== $request['transaction']
            || $request['base_topic'] !== trim($this->ReadPropertyString(self::MQTT_BASE_TOPIC), '/')) {
            return;
        }
        if ($request['deadline'] <= time()) {
            $this->FinishRoutingTableRefresh('The routing table request timed out. Check that the router is reachable and Zigbee2MQTT supports the raw action.');
            return;
        }

        $data = $payload['data'] ?? [];
        if (($payload['status'] ?? '') !== 'ok' || (\is_array($data) && isset($data[0]) && $data[0] !== 0)) {
            $this->FinishRoutingTableRefresh('Zigbee2MQTT could not read the routing table. Check that the router is reachable and Zigbee2MQTT supports the raw action.');
            return;
        }

        $page = \is_array($data) && ($data[0] ?? null) === 0 ? ($data[1] ?? null) : null;
        $total = $page['routingTableEntries'] ?? null;
        $entries = $page['entryList'] ?? null;
        if (!\is_int($total) || $total < 0 || $total > 255
            || ($page['startIndex'] ?? null) !== $request['offset'] || ($request['total'] ?? $total) !== $total
            || !\is_array($entries) || !array_is_list($entries)) {
            $this->FinishRoutingTableRefresh('The router returned an invalid routing table.');
            return;
        }

        $next = $request['offset'] + \count($entries);
        if ($next > $total || ($entries === [] && $next < $total)) {
            $this->FinishRoutingTableRefresh('The router returned an invalid routing table.');
            return;
        }
        foreach ($entries as $entry) {
            if (self::RoutingNetworkAddress($entry['destinationAddress'] ?? null, 0xFFFF) === null
                || self::RoutingNetworkAddress($entry['nextHopAddress'] ?? null, 0xFFFF) === null
                || !(\is_string($entry['status'] ?? null) || \is_int($entry['status'] ?? null))) {
                $this->FinishRoutingTableRefresh('The router returned an invalid routing table.');
                return;
            }
        }

        $request['routes'] = array_merge($request['routes'], $entries);
        if ($next < $total) {
            $request['offset'] = $next;
            $request['total'] = $total;
            $this->SendRoutingTablePage($request);
            return;
        }
        $this->FinishRoutingTableRefresh();
        $this->RoutingTableRefreshCompleted($request['context'], $request['routes']);
    }

    private function CheckRoutingTableRefresh(): void
    {
        $this->WithRoutingTableLock(function (): void
        {
            $request = $this->ReadAttributeArray(self::ROUTING_TABLE_REFRESH);
            if ($request !== [] && $request['deadline'] <= time()) {
                $this->FinishRoutingTableRefresh('The routing table request timed out. Check that the router is reachable and Zigbee2MQTT supports the raw action.');
            }
        });
    }

    private function FinishRoutingTableRefresh(string $error = ''): void
    {
        $this->ClearRoutingTableRefresh();
        $this->RoutingTableRefreshStateChanged(false);
        if ($error !== '') {
            $this->RoutingTableRefreshFailed($error);
        }
    }

    private static function RoutingTableTarget(array $device): ?array
    {
        $ieee = $device['ieee_address'] ?? $device['ieeeAddr'] ?? '';
        $address = self::RoutingNetworkAddress($device['network_address'] ?? $device['networkAddress'] ?? null);
        if (!\in_array($device['type'] ?? '', ['Router', 'Coordinator'], true) || ($device['disabled'] ?? false)
            || !\is_string($ieee) || !preg_match('/^0x(?!0{16}$)[a-f0-9]{16}$/iD', $ieee) || $address === null) {
            return null;
        }
        return ['ieee' => strtolower($ieee), 'address' => $address];
    }

    private static function RoutingNetworkAddress(mixed $address, int $maximum = 0xFFF7): ?int
    {
        if (!\is_int($address) && !(\is_string($address) && ctype_digit($address))) {
            return null;
        }
        $address = (int) $address;
        return $address >= 0 && $address <= $maximum ? $address : null;
    }

    private static function FormatNetworkAddress(mixed $address): string
    {
        return $address === '' || $address === null ? '' : \sprintf('0x%04X', (int) $address);
    }

    private function FormatRouteStatus(mixed $status): string
    {
        $value = match (\is_string($status) ? strtoupper($status) : $status) {
            0, '0', 'ACTIVE'               => 'Active',
            1, '1', 'DISCOVERY_UNDERWAY'   => 'Discovery underway',
            2, '2', 'DISCOVERY_FAILED'     => 'Discovery failed',
            3, '3', 'INACTIVE'             => 'Inactive',
            4, '4', 'VALIDATION_UNDERWAY'  => 'Validation underway',
            default                        => (string) $status
        };
        return $this->Translate($value);
    }
}
