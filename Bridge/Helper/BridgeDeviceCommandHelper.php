<?php

declare(strict_types=1);

/**
 * Geraetebezogene Wartungs-, Reporting- und Optionsbefehle der Bridge.
 */
trait BridgeDeviceCommandHelper
{
    /**
     * Liefert die aus bridge/devices bekannten Endpoint-Daten eines Geraets.
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     *
     * @return string JSON-Daten mit Endpoint-Daten oder leeres JSON-Array.
     */
    public function GetCachedDeviceEndpoints(string $DeviceName): string
    {
        $DeviceName = trim($DeviceName);
        if ($DeviceName === '') {
            return '[]';
        }

        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $friendlyName = trim((string) ($device['friendly_name'] ?? $device['friendlyName'] ?? ''));
            $ieeeAddress = trim((string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''));
            if ($DeviceName !== $friendlyName && strcasecmp($DeviceName, $ieeeAddress) !== 0) {
                continue;
            }

            $endpoints = \is_array($device['endpoints'] ?? null) ? $device['endpoints'] : [];
            $json = json_encode($endpoints, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return \is_string($json) ? $json : '[]';
        }

        return '[]';
    }

    /**
     * Liefert die aus bridge/devices bekannten Geraete aus dem Bridge-Cache.
     *
     * @return string JSON-Liste der gecachten Geraete oder leeres JSON-Array.
     */
    public function GetCachedNetworkDevices(): string
    {
        $json = json_encode($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return \is_string($json) ? $json : '[]';
    }

    /**
     * ConfigureReporting
     *
     * @param string $DeviceName            Friendly Name oder IEEE-Adresse.
     * @param string $Endpoint              Endpoint-ID oder Endpoint-Name.
     * @param string $Cluster               Zigbee-Cluster, z.B. genOnOff.
     * @param string $Attribute             Cluster-Attribut, z.B. onOff.
     * @param int    $MinimumReportInterval Minimales Reporting-Intervall in Sekunden.
     * @param int    $MaximumReportInterval Maximales Reporting-Intervall in Sekunden.
     * @param string $ReportableChange      Optionaler reportable_change-Wert.
     * @param string $OptionsJSON           Optionales JSON-Objekt fuer ZCL-Optionen.
     *
     * @return bool
     */
    public function ConfigureReporting(
        string $DeviceName,
        string $Endpoint,
        string $Cluster,
        string $Attribute,
        int $MinimumReportInterval,
        int $MaximumReportInterval,
        string $ReportableChange,
        string $OptionsJSON
    ): bool {
        $payload = $this->BuildReportingPayload($DeviceName, $Endpoint, $Cluster);
        $payload['attribute'] = $Attribute;
        $payload['minimum_report_interval'] = $MinimumReportInterval;
        $payload['maximum_report_interval'] = $MaximumReportInterval;

        $ReportableChange = trim($ReportableChange);
        if ($ReportableChange !== '') {
            $payload['reportable_change'] = $this->ParseBridgeScalarValue($ReportableChange);
        }

        $options = $this->ParseOptionalJsonObject($OptionsJSON);
        if ($options !== []) {
            $payload['options'] = $options;
        }

        return $this->SendCheckedBridgeRequest('/bridge/request/device/reporting/configure', $payload, 10000) !== false;
    }

    /**
     * ReadReporting
     *
     * @param string $DeviceName       Friendly Name oder IEEE-Adresse.
     * @param string $Endpoint         Endpoint-ID oder Endpoint-Name.
     * @param string $Cluster          Zigbee-Cluster, z.B. genOnOff.
     * @param string $AttributesJSON   JSON-Array oder kommaseparierte Attributliste.
     * @param string $ManufacturerCode Optionaler Hersteller-Code.
     *
     * @return string JSON-kodierte Antwortdaten oder leer bei Fehler.
     */
    public function ReadReporting(string $DeviceName, string $Endpoint, string $Cluster, string $AttributesJSON, string $ManufacturerCode): string
    {
        $payload = $this->BuildReportingPayload($DeviceName, $Endpoint, $Cluster);
        $payload['configs'] = $this->BuildReportingConfigList($AttributesJSON);

        $ManufacturerCode = trim($ManufacturerCode);
        if ($ManufacturerCode !== '') {
            $payload['manufacturer_code'] = $this->ParseBridgeScalarValue($ManufacturerCode);
        }

        $data = $this->SendCheckedBridgeRequest('/bridge/request/device/reporting/read', $payload, 10000);
        if ($data === false) {
            return '';
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * RenameDevice
     *
     * @param  string $OldDeviceName
     * @param  string $NewDeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function RenameDevice(string $OldDeviceName, string $NewDeviceName): bool
    {
        $Topic = '/bridge/request/device/rename';
        $Payload = ['from' => $OldDeviceName, 'to' => $NewDeviceName];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * RemoveDevice
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param bool   $Force      Entfernt das Geraet nur aus der Zigbee2MQTT-Datenbank.
     * @param bool   $Block      Blockiert das Geraet nach dem Entfernen.
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function RemoveDevice(string $DeviceName, bool $Force = false, bool $Block = false): bool
    {
        $Topic = '/bridge/request/device/remove';
        $Payload = ['id' => $DeviceName];
        if ($Force) {
            $Payload['force'] = true;
        }
        if ($Block) {
            $Payload['block'] = true;
        }

        return $this->SendCheckedBridgeRequest($Topic, $Payload, 11000) !== false;
    }

    /**
     * SetDeviceOptions
     *
     * @param string $DeviceName  Friendly Name oder IEEE-Adresse.
     * @param string $OptionsJSON JSON-Objekt mit den zu setzenden Geraeteoptionen.
     *
     * @return bool
     */
    public function SetDeviceOptions(string $DeviceName, string $OptionsJSON): bool
    {
        $options = json_decode($OptionsJSON, true);
        if (!\is_array($options) || !str_starts_with(ltrim($OptionsJSON), '{')) {
            trigger_error($this->Translate('Device options must be a JSON object.'), E_USER_NOTICE);
            return false;
        }

        $data = $this->SendCheckedBridgeRequest('/bridge/request/device/options', [
            'id'      => $DeviceName,
            'options' => $options
        ]);
        if ($data === false) {
            return false;
        }
        if (($data['restart_required'] ?? false) === true) {
            $this->LogMessage($this->Translate('Zigbee2MQTT restart is required for the changed device options.'), KL_NOTIFY);
        }

        return true;
    }

    /**
     * Baut die gemeinsamen Reporting-Payload-Felder.
     */
    private function BuildReportingPayload(string $DeviceName, string $Endpoint, string $Cluster): array
    {
        $payload = [
            'id'      => $DeviceName,
            'cluster' => $Cluster
        ];

        $Endpoint = trim($Endpoint);
        if ($Endpoint !== '') {
            $payload['endpoint'] = is_numeric($Endpoint) ? (int) $Endpoint : $Endpoint;
        }

        return $payload;
    }

    /**
     * Baut die configs-Liste fuer Reporting-Read-Requests.
     */
    private function BuildReportingConfigList(string $AttributesJSON): array
    {
        $decoded = json_decode(trim($AttributesJSON), true);
        if (\is_array($decoded) && array_is_list($decoded)) {
            $configs = [];
            foreach ($decoded as $entry) {
                if (\is_array($entry)) {
                    $configs[] = $entry;
                    continue;
                }
                $attribute = trim((string) $entry);
                if ($attribute !== '') {
                    $configs[] = ['attribute' => $attribute];
                }
            }

            return $configs;
        }

        $attributes = $this->ParseStringList($AttributesJSON);
        if ($attributes === []) {
            return [];
        }

        $configs = [];
        foreach ($attributes as $attribute) {
            $configs[] = ['attribute' => $attribute];
        }

        return $configs;
    }
}
