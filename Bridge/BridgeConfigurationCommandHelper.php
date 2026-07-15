<?php

declare(strict_types=1);

/**
 * Grundlegende Konfigurations- und Wartungsbefehle der Zigbee2MQTT-Bridge.
 */
trait BridgeConfigurationCommandHelper
{
    public function InstallSymconExtension(): bool
    {
        if ($this->installedZhVersion == 0) {
            $this->LogMessage($this->Translate('Cannot determine ZH Version. No Extension installed.'), KL_WARNING);
            return false;
        }
        if (!isset(self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])) {
            return false;
        }

        $ExtensionFilename = $this->ExtensionFilename == '' ? 'IPSymconExtension.js' : $this->ExtensionFilename;
        $Topic = '/bridge/request/extension/save';
        $Payload = ['name'=>$ExtensionFilename, 'code'=>file_get_contents(dirname(__DIR__) . '/libs/' . self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    public function RequestOptions(int $Timeout = self::TIMEOUT_ZIGBEE_OPTIONS_REQUEST): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> []
        ];
        return $this->SendQuietCheckedBridgeRequest($Topic, $Payload, $Timeout) !== false;
    }

    public function SetLastSeen(): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> [
                'advanced'=> [
                    'last_seen'=> 'epoch'
                ]
            ]
        ];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    public function SetPermitJoinOption(bool $PermitJoin): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options'=> ['permit_join' => $PermitJoin]];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    public function SetPermitJoin(bool $PermitJoin): bool
    {
        return $this->SetPermitJoinTarget($PermitJoin ? self::MAX_PERMIT_JOIN_DURATION : 0);
    }

    public function SetPermitJoinTarget(int $Duration, string $Device = ''): bool
    {
        $Duration = max(0, min(self::MAX_PERMIT_JOIN_DURATION, $Duration));
        $Device = trim($Device);
        $Payload = ['time' => $Duration];
        if ($Duration > 0 && $Device !== '') {
            $Payload['device'] = $Device;
        }

        $Data = $this->SendCheckedBridgeRequest('/bridge/request/permit_join', $Payload);
        if ($Data === false) {
            return false;
        }

        $Duration = max(0, min(self::MAX_PERMIT_JOIN_DURATION, (int) ($Data['time'] ?? $Duration)));
        $Target = $Duration > 0 ? trim((string) ($Data['device'] ?? $Device)) : '';
        $this->ApplyPermitJoinState($Duration > 0, $Duration > 0 ? time() + $Duration : 0, $Target);
        return true;
    }

    public function SetBlocklist(string $DevicesJSON): bool
    {
        return $this->SetNetworkSecurityList('blocklist', $DevicesJSON);
    }

    public function SetPasslist(string $DevicesJSON): bool
    {
        return $this->SetNetworkSecurityList('passlist', $DevicesJSON);
    }

    public function SetLogLevel(string $LogLevel): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options' =>['advanced' => ['log_level'=> $LogLevel]]];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    public function Restart(): bool
    {
        $Topic = '/bridge/request/restart';
        return $this->SendCheckedBridgeRequest($Topic) !== false;
    }

    public function InterviewDevice(string $DeviceName): bool
    {
        return $this->SendQuietCheckedBridgeRequest(
            '/bridge/request/device/interview',
            ['id' => $DeviceName],
            self::TIMEOUT_ZIGBEE_DEVICE_MAINTENANCE_REQUEST
        ) !== false;
    }

    public function ConfigureDevice(string $DeviceName): bool
    {
        return $this->SendQuietCheckedBridgeRequest(
            '/bridge/request/device/configure',
            ['id' => $DeviceName],
            self::TIMEOUT_ZIGBEE_DEVICE_MAINTENANCE_REQUEST
        ) !== false;
    }
}
