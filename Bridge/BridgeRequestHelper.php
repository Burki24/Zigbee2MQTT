<?php

declare(strict_types=1);

/**
 * Kapselt die einheitliche MQTT-Request-/Response-Verarbeitung der Bridge.
 */
trait BridgeRequestHelper
{
    /**
     * Sendet einen Bridge-Request und gibt bei erfolgreicher Antwort das data-Array zurueck.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param array  $Payload MQTT-Payload.
     * @param int    $Timeout Wartezeit auf die Bridge-Antwort in Millisekunden.
     *
     * @return array|false Antwortdaten oder false bei Fehler/Timeout.
     */
    private function SendCheckedBridgeRequest(string $Topic, array $Payload = [], int $Timeout = 5000): array|false
    {
        return $this->ValidateCheckedBridgeResponse($Topic, $this->SendData($Topic, $Payload, $Timeout));
    }

    /**
     * Sendet einen sensiblen Bridge-Request und maskiert dessen Payload im Debug-Protokoll.
     */
    private function SendCheckedSensitiveBridgeRequest(string $Topic, array $Payload = [], int $Timeout = 5000): array|false
    {
        return $this->ValidateCheckedBridgeResponse($Topic, $this->SendSensitiveData($Topic, $Payload, $Timeout), true);
    }

    /**
     * Wertet die Antwort eines Bridge-Requests einheitlich aus.
     */
    private function ValidateCheckedBridgeResponse(string $Topic, array|bool $Result, bool $Sensitive = false): array|false
    {
        if ($Result === false) {
            return false;
        }
        if (!is_array($Result)) {
            return [];
        }
        if (isset($Result['error'])) {
            trigger_error(
                $Sensitive
                    ? sprintf($this->Translate('Zigbee2MQTT request failed on Topic %s'), $Topic)
                    : (string) $Result['error'],
                E_USER_NOTICE
            );
            return false;
        }
        if (isset($Result['status']) && $Result['status'] !== 'ok') {
            trigger_error(sprintf($this->Translate('Zigbee2MQTT request failed on Topic %s'), $Topic), E_USER_NOTICE);
            return false;
        }
        if (isset($Result['data']) && is_array($Result['data'])) {
            return $Result['data'];
        }
        return [];
    }

    /**
     * Sendet einen Bridge-Request ohne technische Timeout-Notice fuer Formularaktionen.
     */
    private function SendQuietCheckedBridgeRequest(string $Topic, array $Payload = [], int $Timeout = 5000): array|false
    {
        set_error_handler(static function (): bool
        {
            return true;
        }, E_USER_NOTICE);

        try {
            return $this->ValidateCheckedBridgeResponse($Topic, $this->SendDataQuiet($Topic, $Payload, $Timeout));
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Sendet einen Bridge-Befehl ohne auf eine Antwort zu warten.
     *
     * Lange laufende Requests wie OTA-Updates oder Netzwerkkarten werden asynchron
     * angestossen, damit die Symcon-Ausfuehrung nicht blockiert.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param array  $Payload MQTT-Payload.
     *
     * @return bool true, wenn der Request an den MQTT-Parent uebergeben wurde.
     */
    private function SendBridgeCommand(string $Topic, array $Payload = []): bool
    {
        return $this->SendData($Topic, $Payload, 0) === true;
    }

    /**
     * Sendet einen Szenenbefehl an ein Geraet oder eine Gruppe.
     */
    private function SendSceneCommand(string $FriendlyName, array $Payload): bool
    {
        $FriendlyName = trim($FriendlyName, '/');
        if ($FriendlyName === '') {
            trigger_error($this->Translate('Friendly name is required.'), E_USER_NOTICE);
            return false;
        }

        return $this->SendBridgeCommand('/' . $FriendlyName . '/set', $Payload);
    }

}

