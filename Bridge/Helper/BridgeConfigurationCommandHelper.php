<?php

declare(strict_types=1);

/**
 * Grundlegende Konfigurations- und Wartungsbefehle der Zigbee2MQTT-Bridge.
 */
trait BridgeConfigurationCommandHelper
{
    /**
     * Installiert die zur erkannten Zigbee-Herdsman-Version passende Symcon-Erweiterung.
     *
     * @return bool `true`, wenn die Erweiterung gespeichert wurde.
     */
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

    /**
     * Fragt die aktuelle Zigbee2MQTT-Konfiguration ab.
     *
     * @param int $Timeout Maximale Wartezeit auf die Bridge-Antwort in Millisekunden.
     *
     * @return bool `true`, wenn Zigbee2MQTT innerhalb des Timeouts geantwortet hat.
     */
    public function RequestOptions(int $Timeout = self::TIMEOUT_ZIGBEE_OPTIONS_REQUEST): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> []
        ];
        return $this->SendQuietCheckedBridgeRequest($Topic, $Payload, $Timeout) !== false;
    }

    /**
     * Aktiviert die Ausgabe von `last_seen` als Unix-Zeitstempel.
     */
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

    /**
     * Setzt die dauerhafte `permit_join`-Option in der Zigbee2MQTT-Konfiguration.
     *
     * @param bool $PermitJoin Neuer Zustand der Konfigurationsoption.
     */
    public function SetPermitJoinOption(bool $PermitJoin): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options'=> ['permit_join' => $PermitJoin]];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * Öffnet oder schließt das globale Anlernfenster mit der maximalen Laufzeit.
     *
     * @param bool $PermitJoin `true` zum Öffnen, `false` zum Schließen.
     */
    public function SetPermitJoin(bool $PermitJoin): bool
    {
        return $this->SetPermitJoinTarget($PermitJoin ? self::MAX_PERMIT_JOIN_DURATION : 0);
    }

    /**
     * Setzt Dauer und optionales Ziel des Zigbee2MQTT-Anlernfensters.
     *
     * Die von der Bridge bestätigten Werte werden in den lokalen Pairing-Zustand übernommen.
     *
     * @param int    $Duration Dauer in Sekunden; Werte werden auf den zulässigen Bereich begrenzt.
     * @param string $Device   Optionaler Friendly Name oder die IEEE-Adresse des Coordinators/Routers.
     */
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

    /**
     * Ersetzt die Zigbee2MQTT-Blockliste durch die übergebene JSON-Liste.
     */
    public function SetBlocklist(string $DevicesJSON): bool
    {
        return $this->SetNetworkSecurityList('blocklist', $DevicesJSON);
    }

    /**
     * Ersetzt die Zigbee2MQTT-Passliste durch die übergebene JSON-Liste.
     */
    public function SetPasslist(string $DevicesJSON): bool
    {
        return $this->SetNetworkSecurityList('passlist', $DevicesJSON);
    }

    /**
     * Ändert den Log-Level der Zigbee2MQTT-Bridge.
     */
    public function SetLogLevel(string $LogLevel): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options' =>['advanced' => ['log_level'=> $LogLevel]]];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * Fordert einen Neustart von Zigbee2MQTT an.
     */
    public function Restart(): bool
    {
        $Topic = '/bridge/request/restart';
        return $this->SendCheckedBridgeRequest($Topic) !== false;
    }

    /**
     * Startet ein erneutes Interview des angegebenen Geräts.
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse des Geräts.
     */
    public function InterviewDevice(string $DeviceName): bool
    {
        return $this->SendQuietCheckedBridgeRequest(
            '/bridge/request/device/interview',
            ['id' => $DeviceName],
            self::TIMEOUT_ZIGBEE_DEVICE_MAINTENANCE_REQUEST
        ) !== false;
    }

    /**
     * Startet die erneute Zigbee2MQTT-Konfiguration des angegebenen Geräts.
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse des Geräts.
     */
    public function ConfigureDevice(string $DeviceName): bool
    {
        return $this->SendQuietCheckedBridgeRequest(
            '/bridge/request/device/configure',
            ['id' => $DeviceName],
            self::TIMEOUT_ZIGBEE_DEVICE_MAINTENANCE_REQUEST
        ) !== false;
    }
}
