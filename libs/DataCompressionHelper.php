<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Komprimiert grosse textbasierte Speicherwerte transparent mit Deflate.
 */
final class DataCompressionHelper
{
    private const PREFIX = 'Z2MZ1:';
    private const MINIMUM_INPUT_BYTES = 256;
    private const MAXIMUM_OUTPUT_BYTES = 67108864;

    /**
     * Komprimiert einen Wert nur, wenn die Base64-Huelle weiterhin kleiner ist.
     */
    public static function Encode(string $data): string
    {
        if (strlen($data) < self::MINIMUM_INPUT_BYTES || !function_exists('gzdeflate')) {
            return $data;
        }

        $compressed = @gzdeflate($data, 6);
        if (!\is_string($compressed)) {
            return $data;
        }

        $encoded = self::PREFIX . base64_encode($compressed);
        return strlen($encoded) < strlen($data) ? $encoded : $data;
    }

    /**
     * Erkennt Werte, die bereits in der aktuellen Kompressionshuelle vorliegen.
     */
    public static function IsEncoded(string $data): bool
    {
        return str_starts_with($data, self::PREFIX);
    }

    /**
     * Entpackt neue Werte und reicht unkomprimierte Bestandsdaten unveraendert durch.
     * Bei einer beschaedigten Komprimierung wird null geliefert.
     */
    public static function Decode(string $data): ?string
    {
        if (!self::IsEncoded($data)) {
            return $data;
        }
        if (!function_exists('gzinflate')) {
            return null;
        }

        $compressed = base64_decode(substr($data, strlen(self::PREFIX)), true);
        if (!\is_string($compressed)) {
            return null;
        }

        $decoded = @gzinflate($compressed, self::MAXIMUM_OUTPUT_BYTES);
        return \is_string($decoded) ? $decoded : null;
    }
}
