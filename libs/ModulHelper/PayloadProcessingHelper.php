<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Validierung, Zuordnung und Vorverarbeitung eingehender MQTT-Payloads.
 */
trait PayloadProcessingHelper
{
    private function validateAndParseMessage(string $JSONString): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);

        if (empty($baseTopic) || empty($mqttTopic)) {
            $this->SendDebug(__FUNCTION__, 'BaseTopic oder MQTTTopic ist leer', 0);
            return [false, false];
        }

        $messageData = json_decode($JSONString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->SendDebug(__FUNCTION__, 'JSON Decodierung fehlgeschlagen: ' . json_last_error_msg(), 0);
            return [false, false];
        }

        if (!isset($messageData['Topic'])) {
            $this->SendDebug(__FUNCTION__, 'Topic nicht gefunden', 0);
            return [false, false];
        }

        $receivedTopic = (string) $messageData['Topic'];
        if (!$this->IsExpectedReceiveTopic($receivedTopic, $baseTopic, $mqttTopic)) {
            $this->SendDebug(__FUNCTION__, 'Ignoriere fremdes MQTT-Topic: ' . $receivedTopic, 0);
            return [false, false];
        }

        $topic = substr($receivedTopic, \strlen($baseTopic) + 1);
        $payloadData = json_decode(self::DecodePayload($messageData['Payload']), true);
        return [explode('/', $topic), $payloadData];
    }

    private function IsExpectedReceiveTopic(string $receivedTopic, string $baseTopic, string $mqttTopic): bool
    {
        $deviceTopic = $baseTopic . '/' . $mqttTopic;
        if (\in_array(
            $receivedTopic,
            [
                $deviceTopic,
                $deviceTopic . '/' . self::AVAILABILITY_TOPIC,
                $baseTopic . self::SYMCON_EXTENSION_RESPONSE . static::$ExtensionTopic . $mqttTopic
            ],
            true
        )) {
            return true;
        }

        return str_starts_with($receivedTopic, $baseTopic . self::SYMCON_EXTENSION_LIST_RESPONSE);
    }

    private function handleAvailability(array $topics, ?array $payload): bool
    {
        if (end($topics) !== self::AVAILABILITY_TOPIC) {
            return false;
        }
        $this->RememberVariableDefinition('device_status', ['property' => 'device_status', 'type' => 'binary', 'label' => 'Availability'], 'system');
        if (!$this->CanCreateVariable('device_status', ['property' => 'device_status', 'type' => 'binary', 'label' => 'Availability'], 'system')) {
            return true;
        }
        $deviceStatusPresentation = $this->BuildDeviceStatusPresentation() ?? '';
        $this->RecordLegacyProfilePresentationReplacement('device_status', $deviceStatusPresentation);
        $this->RegisterVariableBoolean('device_status', $this->Translate('Availability'), $deviceStatusPresentation);
        $this->MarkVariableCreated('device_status');
        if (isset($payload['state'])) {
            $this->SetValueDirect('device_status', $payload['state'] == 'online');
        } else {
            $this->SetValueDirect('device_status', false);
        }
        return true;
    }

    private function handleSymconExtensionResponses(array $topics, array $payload): bool
    {
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        $fullTopic = '/' . implode('/', $topics);
        if ($fullTopic === self::SYMCON_EXTENSION_RESPONSE . static::$ExtensionTopic . $mqttTopic) {
            if (isset($payload['transaction']) && $this->UpdateTransaction($payload)) {
                return true;
            }
            if ($this->UpdateTransactionByResponseTopic($fullTopic, $payload)) {
                return true;
            }
            return true;
        }
        if (str_starts_with($fullTopic, self::SYMCON_EXTENSION_LIST_RESPONSE)) {
            if (isset($payload['transaction']) && $this->UpdateTransaction($payload)) {
                return true;
            }
            if ($this->UpdateTransactionByResponseTopic($fullTopic, $payload)) {
                return true;
            }
            return true;
        }
        return false;
    }

    private function processPayload(array $payload): void
    {
        $this->BeginVariableCatalogBatch();
        try {
            $this->processPayloadBatch($payload);
        } finally {
            $this->EndVariableCatalogBatch();
        }
    }

    private function processPayloadBatch(array $payload): void
    {
        if (isset($payload['exposes'])) {
            if (\is_array($payload['exposes'])) {
                $this->WriteAttributeArray(self::ATTRIBUTE_EXPOSES, $payload['exposes']);
                $this->mapExposesToVariables($payload['exposes']);
            }
            unset($payload['exposes']);
        }

        $payload = $this->filterPayloadRootIdentEntries($payload);
        $this->latestPayload = $payload;
        if ($payload === []) {
            return;
        }

        $this->lastPayload = $this->MergeLastPayload($this->lastPayload, $payload);
        foreach ($this->flattenPayload($payload) as $key => $value) {
            if (!\is_string($key)) {
                $this->SendDebug(
                    'processPayload',
                    \sprintf(
                        'Ueberspringe Payload-Eintrag ohne Variablen-Ident: Key=%s, Value=%s',
                        (string) $key,
                        $this->formatPayloadDebugValue($value)
                    ),
                    0
                );
                continue;
            }
            $this->processPayloadEntry($key, $value);
        }
    }

    private function MergeLastPayload(array $currentPayload, array $newPayload): array
    {
        foreach ($newPayload as $key => $newValue) {
            $currentValue = $currentPayload[$key] ?? null;
            if (\is_array($currentValue)
                && \is_array($newValue)
                && !array_is_list($currentValue)
                && !array_is_list($newValue)
            ) {
                $currentPayload[$key] = $this->MergeLastPayload($currentValue, $newValue);
                continue;
            }

            $currentPayload[$key] = $newValue;
        }

        return $currentPayload;
    }

    private function filterPayloadRootIdentEntries(array $payload): array
    {
        $filteredPayload = [];
        foreach ($payload as $key => $value) {
            if (!\is_string($key)) {
                $this->SendDebug(
                    'processPayload',
                    \sprintf(
                        'Ueberspringe Payload-Eintrag ohne Variablen-Ident: Key=%s, Value=%s',
                        (string) $key,
                        $this->formatPayloadDebugValue($value)
                    ),
                    0
                );
                continue;
            }

            $filteredPayload[$key] = $value;
        }

        return $filteredPayload;
    }

    private function processPayloadEntry(string $key, mixed $value): void
    {
        if ($value === null) {
            $this->SendDebug('processPayload', \sprintf('Skip empty value for key=%s', $key), 0);
            return;
        }

        $this->SendDebug('processPayload', \sprintf('Verarbeite: Key=%s, Value=%s', $key, $this->formatPayloadDebugValue($value)), 0);
        if (!$this->processSpecialVariable($key, $value)) {
            $this->processVariable($key, $value);
        }
    }

    private function formatPayloadDebugValue(mixed $value): string
    {
        if (\is_array($value)) {
            return (string) json_encode($value);
        }
        if (\is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        return (string) $value;
    }
}
