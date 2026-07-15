<?php

declare(strict_types=1);

/**
 * Oeffentliche und gemeinsame OTA-Befehle der Zigbee2MQTT-Bridge.
 */
trait BridgeOTACommandHelper
{
    /**
     * CheckOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function CheckOTAUpdate(string $DeviceName): bool
    {
        return $this->CheckOTAAvailability('/bridge/request/device/ota_update/check', $DeviceName, '');
    }

    /**
     * CheckOTAUpdateWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function CheckOTAUpdateWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->CheckOTAAvailability('/bridge/request/device/ota_update/check', $DeviceName, $Url);
    }

    /**
     * CheckOTADowngrade
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function CheckOTADowngrade(string $DeviceName): bool
    {
        return $this->CheckOTAAvailability('/bridge/request/device/ota_update/check/downgrade', $DeviceName, '');
    }

    /**
     * CheckOTADowngradeWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function CheckOTADowngradeWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->CheckOTAAvailability('/bridge/request/device/ota_update/check/downgrade', $DeviceName, $Url);
    }

    /**
     * PerformOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function PerformOTAUpdate(string $DeviceName): bool
    {
        return $this->SendOTABridgeCommand('/bridge/request/device/ota_update/update', $DeviceName, '');
    }

    /**
     * PerformOTAUpdateWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function PerformOTAUpdateWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->SendOTABridgeCommand('/bridge/request/device/ota_update/update', $DeviceName, $Url);
    }

    /**
     * PerformOTADowngrade
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function PerformOTADowngrade(string $DeviceName): bool
    {
        return $this->SendOTABridgeCommand('/bridge/request/device/ota_update/update/downgrade', $DeviceName, '');
    }

    /**
     * PerformOTADowngradeWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function PerformOTADowngradeWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->SendOTABridgeCommand('/bridge/request/device/ota_update/update/downgrade', $DeviceName, $Url);
    }

    /**
     * ScheduleOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function ScheduleOTAUpdate(string $DeviceName): bool
    {
        return $this->ScheduleOTARequest('/bridge/request/device/ota_update/schedule', $DeviceName, '');
    }

    /**
     * ScheduleOTAUpdateWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function ScheduleOTAUpdateWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->ScheduleOTARequest('/bridge/request/device/ota_update/schedule', $DeviceName, $Url);
    }

    /**
     * ScheduleOTADowngrade
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function ScheduleOTADowngrade(string $DeviceName): bool
    {
        return $this->ScheduleOTARequest('/bridge/request/device/ota_update/schedule/downgrade', $DeviceName, '');
    }

    /**
     * ScheduleOTADowngradeWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function ScheduleOTADowngradeWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->ScheduleOTARequest('/bridge/request/device/ota_update/schedule/downgrade', $DeviceName, $Url);
    }

    /**
     * UnscheduleOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function UnscheduleOTAUpdate(string $DeviceName): bool
    {
        $Data = $this->SendCheckedBridgeRequest('/bridge/request/device/ota_update/unschedule', $this->BuildOTAPayload($DeviceName, ''));

        return $Data !== false;
    }

    /**
     * AbortOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function AbortOTAUpdate(string $DeviceName): bool
    {
        $Data = $this->SendCheckedBridgeRequest('/bridge/request/device/ota_update/update/abort', $this->BuildOTAPayload($DeviceName, ''));

        return $Data !== false;
    }

    /**
     * Prueft die OTA-Verfuegbarkeit fuer Upgrade oder Downgrade.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param string $Url Optionaler OTA-Index.
     *
     * @return bool true, wenn Zigbee2MQTT ein passendes OTA-Image meldet.
     */
    private function CheckOTAAvailability(string $Topic, string $DeviceName, string $Url): bool
    {
        $Data = $this->SendCheckedBridgeRequest($Topic, $this->BuildOTAPayload($DeviceName, $Url), 10000);
        if ($Data === false) {
            return false;
        }

        return (bool) ($Data['update_available'] ?? $Data['updateAvailable'] ?? false);
    }

    /**
     * Baut den OTA-Payload fuer normale und Custom-URL-Requests.
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param string $Url Optionaler OTA-Index, Firmware-URL oder lokaler Pfad.
     *
     * @return array
     */
    private function BuildOTAPayload(string $DeviceName, string $Url): array
    {
        $Payload = ['id' => $DeviceName];
        $Url = trim($Url);
        if ($Url !== '') {
            $Payload['url'] = $Url;
        }

        return $Payload;
    }

    /**
     * Plant ein OTA-Upgrade oder OTA-Downgrade fuer die naechste Geraeteanfrage.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param string $Url Optionaler OTA-Index, Firmware-URL oder lokaler Pfad.
     *
     * @return bool true, wenn Zigbee2MQTT die Planung akzeptiert.
     */
    private function ScheduleOTARequest(string $Topic, string $DeviceName, string $Url): bool
    {
        $Data = $this->SendCheckedBridgeRequest($Topic, $this->BuildOTAPayload($DeviceName, $Url));

        return $Data !== false;
    }

    /**
     * Sendet einen langen OTA-Request ohne auf dessen Abschluss zu warten.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param string $Url Optionaler OTA-Index, Firmware-URL oder lokaler Pfad.
     *
     * @return bool true, wenn der Request an den MQTT-Parent uebergeben wurde.
     */
    private function SendOTABridgeCommand(string $Topic, string $DeviceName, string $Url): bool
    {
        return $this->SendBridgeCommand($Topic, $this->BuildOTAPayload($DeviceName, $Url));
    }

}

