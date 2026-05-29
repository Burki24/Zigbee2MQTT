<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

require_once __DIR__ . '/ModuleConstants.php';

/**
 * @property array $TransactionData Legacy-Buffer fuer aktuelle Anfragen und Antworten von/zur Z2M Bridge
 * @property array $Multi_TransactionData Gesplitteter Buffer fuer aktuelle Anfragen und Antworten von/zur Z2M Bridge
 */
trait SendData
{
    use Constants;

    /** @var mixed $MQTTDataArray
     *  Vorlage Daten Array zum versenden an einen MQTT-Splitter
     */
    private static $MQTTDataArray = [
        'DataID'           => self::GUID_MQTT_SEND,
        'PacketType'       => 3,
        'QualityOfService' => 0,
        'Retain'           => false,
        'Topic'            => '',
        'Payload'          => ''
    ];

    /**
     * Command
     *
     * @param  string $topic
     * @param  string $value
     * @return bool
     */
    public function Command(string $topic, string $value): bool
    {
        $payload = $this->DecodeCommandPayload($value);
        if ($payload === null) {
            return false;
        }

        return $this->SendData('/' . $this->ReadPropertyString(self::MQTT_TOPIC) . '/' . $topic, $payload, 0);
    }

    /**
     * CommandExt
     *
     * @param  string $topic
     * @param  string $value
     * @return bool
     */
    public function CommandExt(string $topic, string $value): bool //ohne MQTTTopic
    {
        $payload = $this->DecodeCommandPayload($value);
        if ($payload === null) {
            return false;
        }

        return $this->SendData('/' . $topic, $payload, 0);
    }

    /**
     * SendData
     *
     * Sendet eine MQTT Nachricht an den Parent.
     * Bei aktivem Timeout wird die Nachtricht mit einer TransactionId versehen,
     * und auf eine eingehende Nachricht mit der entsprechenden TransactionId gewartet.
     * TransactionId wird nur zur Kommunikation mit dem Bridge Topic, sowie der Extension in Z2M verwendet,
     * durch die Funktionen UpdateDeviceInfo, getDevices und getGroups
     * @param  string $Topic
     * @param  array $Payload
     * @param  int $Timeout default 5000ms, 0 = senden ohne auf die Antwort zuw arten
     * @return array|bool Enthält die Antwort als Array, oder True bei inaktivem Timeout, oder false im Fehlerfall.
     */
    protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
    {
        if ($Timeout) {
            $TransactionId = $this->AddTransaction($Payload, $Topic);
        }

        $this->SendDebug(__FUNCTION__ . ':Topic', $Topic, 0);
        $this->SendDebug(__FUNCTION__ . ':Payload', json_encode($Payload), 0);
        $this->SendDebug(__FUNCTION__ . ':Timeout', (string) $Timeout, 0);
        $DataJSON = self::BuildRequest($this->ReadPropertyString(self::MQTT_BASE_TOPIC) . $Topic, $Payload);
        $this->SendDataToParent($DataJSON);

        if ($Timeout) {
            $Result = $this->WaitForTransactionEnd($TransactionId, $Timeout);
            $this->SendDebug(__FUNCTION__ . ' :Result', json_encode($Result), 0);
            if ($Result === false) {
                trigger_error(\sprintf($this->Translate('Zigbee2MQTT did not response on Topic %s'), $Topic), E_USER_NOTICE);
                return false;
            }
            return $Result;
        }
        return true;
    }

    /**
     * Sendet eine Anfrage ohne technische Timeout-Notice.
     */
    protected function SendDataQuiet(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
    {
        set_error_handler(static function (): bool
        {
            return true;
        }, E_USER_NOTICE);

        try {
            return $this->SendData($Topic, $Payload, $Timeout);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Dekodiert Payloads nach IPSModuleStrict-Regel (HEX) und bleibt tolerant
     * gegen alte UTF-8 Test- oder Installationsdaten.
     */
    protected static function DecodePayload(string $Payload): string
    {
        if ((\strlen($Payload) % 2) === 0 && ctype_xdigit($Payload)) {
            $decoded = hex2bin($Payload);
            if ($decoded !== false) {
                return $decoded;
            }
        }
        return utf8_decode($Payload);
    }

    /**
     * Leert den Transaction-Buffer inklusive Legacy-Buffer.
     */
    protected function ClearTransactionData(): void
    {
        $this->SetTransactionDataBuffer([]);
    }

    /**
     * Dekodiert Command-Payloads robust, damit oeffentliche Z2M_Command-Aufrufe
     * bei ungueltigem JSON nicht in einen TypeError laufen.
     */
    private function DecodeCommandPayload(string $value): ?array
    {
        $payload = json_decode($value, true);
        if (\is_array($payload)) {
            return $payload;
        }

        trigger_error($this->Translate('Command payload must be a valid JSON object or array.'), E_USER_NOTICE);
        return null;
    }

    /**
     * WaitForTransactionEnd
     *
     * Liefert die Antwort aus dem Buffer TransactionData.
     *
     * @param  int $TransactionId
     * @param  int $Timeout
     * @return array|false Enthält die Antwort, oder false beim erreichen des Timeout oder im Fehlerfall.
     */
    private function WaitForTransactionEnd(int $TransactionId, int $Timeout): array|false
    {
        $Deadline = microtime(true) + ($Timeout / 1000);
        $Sleep = max(10, min(250, intdiv($Timeout, 100)));

        $this->SendDebug(
            __FUNCTION__ . ':Start',
            \sprintf('Transaction %d, Timeout %d ms, Sleep %d ms', $TransactionId, $Timeout, $Sleep),
            0
        );

        while (microtime(true) < $Deadline) {
            $Buffer = $this->GetTransactionDataBuffer();
            if (!isset($Buffer[$TransactionId])) {
                $this->SendDebug(
                    __FUNCTION__ . ':Abort',
                    \sprintf('Transaction %d missing before timeout', $TransactionId),
                    0
                );
                return false;
            }
            $Transaction = $Buffer[$TransactionId];
            if (!\is_array($Transaction)) {
                $this->SendDebug(
                    __FUNCTION__ . ':Abort',
                    \sprintf('Transaction %d has invalid buffer data', $TransactionId),
                    0
                );
                return false;
            }
            if (\count($Transaction)) {
                if (!$this->HasTransactionResult($Transaction)) {
                    IPS_Sleep($Sleep);
                    continue;
                }
                $this->RemoveTransaction($TransactionId);
                $Result = $this->GetTransactionResult($Transaction);
                unset($Result['transaction']);
                $this->SendDebug(
                    __FUNCTION__ . ':Done',
                    \sprintf('Transaction %d finished', $TransactionId),
                    0
                );
                return $Result;
            }
            IPS_Sleep($Sleep);
        }
        $this->RemoveTransaction($TransactionId);
        $this->SendDebug(
            __FUNCTION__ . ':Timeout',
            \sprintf('Transaction %d timed out after %d ms', $TransactionId, $Timeout),
            0
        );
        return false;
    }

    /**
     * AddTransaction
     *
     * Generiert eine TransactionId, fügt diese dem Payload hinzu und erzeugt einen Eintrag im Buffer TransactionData.
     *
     * @param  array $Payload MQTT Payload als Referenz
     * @param  string $Topic Request-Topic zur Zuordnung von Antworten ohne TransactionId
     * @return int Erzeugte TransactionId
     */
    private function AddTransaction(array &$Payload, string $Topic): int
    {
        if (!$this->lock('TransactionData')) {
            throw new \Exception($this->Translate('Transaction Data is locked'), E_USER_NOTICE);
        }
        $TransactionId = mt_rand(1, 10000);
        $Payload['transaction'] = $TransactionId;
        $TransactionData = $this->GetTransactionDataBuffer();
        $TransactionData[$TransactionId] = [
            '__meta'   => [
                'requestTopic'  => self::NormalizeTransactionTopic($Topic),
                'responseTopic' => self::GetResponseTopicForRequest($Topic)
            ],
            '__result' => null
        ];
        $this->SetTransactionDataBuffer($TransactionData);
        $this->unlock('TransactionData');
        return $TransactionId;
    }

    /**
     * UpdateTransaction
     *
     * Aktualisiert einen Eintrag im TransactionData Buffer.
     *
     * @param  array $Data Payload welches im Buffer abgelegt werden soll.
     * @return void
     */
    private function UpdateTransaction(array $Data): void
    {
        if (!$this->lock('TransactionData')) {
            throw new \Exception($this->Translate('Transaction Data is locked'), E_USER_NOTICE);
        }
        $TransactionData = $this->GetTransactionDataBuffer();
        if (isset($TransactionData[$Data['transaction']])) {
            $TransactionData[$Data['transaction']] = $this->SetTransactionResult($TransactionData[$Data['transaction']], $Data);
            $this->SetTransactionDataBuffer($TransactionData);
            $this->unlock('TransactionData');
            return;
        }
        $this->unlock('TransactionData');
    }

    /**
     * Aktualisiert eine offene Transaktion ueber das Response-Topic.
     *
     * Einige Zigbee2MQTT-Antworten, z.B. Backup-Downloads, enthalten keine
     * TransactionId. In diesem Fall wird die Antwort ueber das erwartete
     * bridge/response-Topic der offenen Anfrage zugeordnet.
     *
     * @param string $ResponseTopic MQTT-Response-Topic relativ zum Base Topic.
     * @param array  $Data         Payload welches im Buffer abgelegt werden soll.
     *
     * @return bool true, wenn eine offene Transaktion gefunden wurde.
     */
    private function UpdateTransactionByResponseTopic(string $ResponseTopic, array $Data): bool
    {
        $ResponseTopic = self::NormalizeTransactionTopic($ResponseTopic);

        if (!$this->lock('TransactionData')) {
            throw new \Exception($this->Translate('Transaction Data is locked'), E_USER_NOTICE);
        }

        $TransactionData = $this->GetTransactionDataBuffer();
        foreach ($TransactionData as $TransactionId => $Transaction) {
            if (!\is_array($Transaction)) {
                continue;
            }

            $Meta = $Transaction['__meta'] ?? null;
            if (!\is_array($Meta) || (($Meta['responseTopic'] ?? '') !== $ResponseTopic)) {
                continue;
            }

            $Data['transaction'] = (int) $TransactionId;
            $TransactionData[$TransactionId] = $this->SetTransactionResult($Transaction, $Data);
            $this->SetTransactionDataBuffer($TransactionData);
            $this->unlock('TransactionData');
            $this->SendDebug(
                __FUNCTION__,
                \sprintf('Transaction %d matched by response topic %s', $TransactionId, $ResponseTopic),
                0
            );
            return true;
        }

        $this->unlock('TransactionData');
        $this->SendDebug(__FUNCTION__, \sprintf('No pending transaction for response topic %s', $ResponseTopic), 0);
        return false;
    }

    /**
     * Speichert das Ergebnis, ohne die Metadaten der Transaktion zu verlieren.
     */
    private function SetTransactionResult(array $Transaction, array $Data): array
    {
        if (array_key_exists('__meta', $Transaction)) {
            $Transaction['__result'] = $Data;
            return $Transaction;
        }

        return $Data;
    }

    /**
     * Prueft, ob eine Transaktion bereits ein Ergebnis enthaelt.
     */
    private function HasTransactionResult(array $Transaction): bool
    {
        if (array_key_exists('__result', $Transaction)) {
            return \is_array($Transaction['__result']);
        }

        return \count($Transaction) > 0;
    }

    /**
     * Liefert das gespeicherte Ergebnis einer Transaktion.
     */
    private function GetTransactionResult(array $Transaction): array
    {
        if (isset($Transaction['__result']) && \is_array($Transaction['__result'])) {
            return $Transaction['__result'];
        }

        return $Transaction;
    }

    /**
     * Ermittelt das erwartete Response-Topic fuer ein Request-Topic.
     */
    private static function GetResponseTopicForRequest(string $Topic): string
    {
        $Topic = self::NormalizeTransactionTopic($Topic);
        return str_replace('/request/', '/response/', $Topic);
    }

    /**
     * Normalisiert MQTT-Topics fuer robuste Vergleiche.
     */
    private static function NormalizeTransactionTopic(string $Topic): string
    {
        return '/' . trim($Topic, '/');
    }

    /**
     * Liest Transaktionen aus dem gesplitteten Buffer und faellt bei alten
     * Installationen auf den Legacy-Buffer zurueck.
     */
    private function GetTransactionDataBuffer(): array
    {
        $TransactionData = $this->Multi_TransactionData;
        if (\is_array($TransactionData)) {
            return $TransactionData;
        }

        $LegacyTransactionData = $this->TransactionData;
        if (\is_array($LegacyTransactionData)) {
            return $LegacyTransactionData;
        }

        return [];
    }

    /**
     * Speichert Transaktionen im gesplitteten Buffer, damit grosse Antworten
     * wie Backup-ZIPs den Symcon-Buffer nicht ungueltig machen.
     */
    private function SetTransactionDataBuffer(array $TransactionData): void
    {
        $this->Multi_TransactionData = $TransactionData;
        $this->TransactionData = [];
    }

    /**
     * RemoveTransaction
     *
     * Entfernt den Eintrag der TransactionId aus dem Buffer TransactionData.
     *
     * @param  int $TransactionId
     * @return void
     */
    private function RemoveTransaction(int $TransactionId): void
    {
        if (!$this->lock('TransactionData')) {
            throw new \Exception($this->Translate('Transaction Data is locked'), E_USER_NOTICE);
        }
        $TransactionData = $this->GetTransactionDataBuffer();
        unset($TransactionData[$TransactionId]);
        $this->SetTransactionDataBuffer($TransactionData);
        $this->unlock('TransactionData');
    }

    /**
     * BuildRequest
     *
     * Erzeugt ein JSON-String für den Datenaustausch mit einem MQTT-Splitter
     *
     * @param  string $Topic MQTT Topic
     * @param  array $Payload MQTT Payload welches als JSON kodierter Payload gesetzt wird.
     * @return string JSON-String des Datenaustausch
     */
    private static function BuildRequest(string $Topic, array $Payload): string
    {
        return json_encode(
            array_merge(
                self::$MQTTDataArray,
                [
                    'Topic'  => $Topic,
                    'Payload'=> bin2hex(json_encode($Payload))
                ]
            ),
            JSON_UNESCAPED_SLASHES
        );
    }
}
