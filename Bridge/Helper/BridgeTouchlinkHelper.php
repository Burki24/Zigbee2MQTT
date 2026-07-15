<?php

declare(strict_types=1);

/**
 * Kapselt Touchlink-Scan, Zielauswahl und Geraeteaktionen der Bridge.
 */
trait BridgeTouchlinkHelper
{
    /**
     * TouchlinkScan
     *
     * @return string JSON-kodierte Scan-Antwort oder leer bei Fehler.
     */
    public function TouchlinkScan(): string
    {
        $data = $this->SendCheckedBridgeRequest('/bridge/request/touchlink/scan', [], 70000);
        if ($data === false) {
            return '';
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_TOUCHLINK_DEVICES, \is_array($data['found'] ?? null) ? $data['found'] : []);
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * TouchlinkIdentify
     *
     * @param string $IeeeAddress IEEE-Adresse aus dem Touchlink-Scan.
     * @param int    $Channel     Zigbee-Kanal aus dem Touchlink-Scan.
     *
     * @return bool
     */
    public function TouchlinkIdentify(string $IeeeAddress, int $Channel): bool
    {
        $payload = $this->BuildTouchlinkTargetPayload($IeeeAddress, $Channel, true);
        if ($payload === null) {
            return false;
        }

        return $this->SendCheckedBridgeRequest('/bridge/request/touchlink/identify', $payload, 10000) !== false;
    }

    /**
     * TouchlinkFactoryReset
     *
     * @param string $IeeeAddress Optionale IEEE-Adresse aus dem Touchlink-Scan.
     * @param int    $Channel     Optionaler Zigbee-Kanal aus dem Touchlink-Scan.
     *
     * @return bool
     */
    public function TouchlinkFactoryReset(string $IeeeAddress = '', int $Channel = 0): bool
    {
        $payload = $this->BuildTouchlinkTargetPayload($IeeeAddress, $Channel, false);
        if ($payload === null) {
            return false;
        }

        return $this->SendCheckedBridgeRequest('/bridge/request/touchlink/factory_reset', $payload, 70000) !== false;
    }

    /**
     * Baut die Formwerte fuer Touchlink-Scan-Ergebnisse.
     */
    private function BuildTouchlinkDeviceFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_TOUCHLINK_DEVICES) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $values[] = [
                'ieee_address' => (string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''),
                'channel'      => (string) ($device['channel'] ?? ''),
                'action'       => $this->Translate('Select')
            ];
        }

        return $values;
    }

    /**
     * Uebernimmt ein Touchlink-Geraet in die Eingabefelder.
     */
    private function SelectTouchlinkDeviceFromForm(mixed $value): bool
    {
        $target = $this->DecodeBridgeFormPayload($value);
        if ($target === null) {
            return false;
        }

        $this->TryUpdateFormField('TouchlinkIeeeAddress', 'value', (string) ($target['ieee_address'] ?? ''));
        $this->TryUpdateFormField('TouchlinkChannel', 'value', (int) ($target['channel'] ?? 0));
        return true;
    }

    /**
     * Baut und validiert einen Touchlink-Zielpayload.
     */
    private function BuildTouchlinkTargetPayload(string $IeeeAddress, int $Channel, bool $required): ?array
    {
        $IeeeAddress = trim($IeeeAddress);
        if ($IeeeAddress === '' && !$required) {
            return [];
        }
        if ($IeeeAddress === '' || $Channel <= 0) {
            trigger_error($this->Translate('Touchlink IEEE address and channel are required.'), E_USER_NOTICE);
            return null;
        }

        return [
            'ieee_address' => $IeeeAddress,
            'channel'      => $Channel
        ];
    }

}
