<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Konvertiert zwischen verschachtelten MQTT-Payloads und flachen Symcon-Idents.
 */
trait PayloadStructureHelper
{
    /**
     * Wandelt ein verschachteltes Array in ein eindimensionales Array mit zusammengesetzten Schlüsseln um
     *
     * @param array  $payload Das zu verarbeitende Array mit verschachtelter Struktur
     * @param string $prefix  Optional, Prefix für die zusammengesetzten Schlüssel
     *
     * @return array Ein eindimensionales Array mit Schlüsseln in der Form 'parent__child'
     *
     * Beispiele:
     * ```php
     * // Verschachteltes Array
     * $input = [
     *     'weekly_schedule' => [
     *         'monday' => '00:00/7'
     *     ]
     * ];
     * $result = $this->flattenPayload($input);
     * // Ergebnis: ['weekly_schedule__monday' => '00:00/7']
     * ```
     *
     * @internal Wird von processPayload verwendet um verschachtelte Strukturen zu verarbeiten
     *
     * @see \Zigbee2MQTT\ModulBase::processPayload()
     */
    protected function flattenPayload(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {

            // Composite-Keys überspringen, die in SKIP_COMPOSITES definiert sind und auf oberster Ebene gesetzt sind
            if ($prefix === '' && \in_array($key, self::SKIP_COMPOSITES) && \is_array($value)) {
                $this->SendDebug(__FUNCTION__, "Überspringe Composite-Key auf oberster Ebene: $key", 0);
                continue;
            }

            // Spezialbehandlung für color-Properties, da zur Farbberechnung nicht als flatten benötigt
            if ($key === 'color' && \is_array($value)) {
                // Übernehme die color-Properties direkt ins color-Array
                $result['color'] = $value;
                continue;
            }

            $newKey = $prefix ? $prefix . '__' . $key : $key;
            if (\is_array($value)) {
                $result = array_merge($result, $this->flattenPayload($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Wandelt einen zusammengesetzten Identifikator in eine verschachtelte Array-Struktur um
     *
     * @param string $ident Der zusammengesetzte Identifikator (z.B. 'weekly_schedule__friday')
     * @param mixed $value Der Wert, der gesetzt werden soll
     *
     * @return array Das verschachtelte Array
     *
     * Beispiel:
     * ```php
     * $ident = 'weekly_schedule__friday';
     * $value = '00:00/7';
     * $result = $this->buildNestedPayload($ident, $value);
     * // Ergebnis: ['weekly_schedule' => ['friday' => '00:00/7']]
     * ```
     *
     * @internal Diese Methode wird von handleStandardVariable, handlePresetVariable und RequestAction verwendet
     *
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable()
     * @see \Zigbee2MQTT\ModulBase::handlePresetVariable()
     * @see \Zigbee2MQTT\ModulBase::RequestAction()
     */
    protected function buildNestedPayload(string $ident, mixed $value): array
    {
        $parts = explode('__', $ident);
        $result = [];
        $current = &$result;

        // Alle Teile außer dem letzten durchgehen
        for ($i = 0; $i < \count($parts) - 1; $i++) {
            $current[$parts[$i]] = [];
            $current = &$current[$parts[$i]];
        }

        // Letzten Wert setzen
        $current[$parts[\count($parts) - 1]] = $value;

        return $result;
    }

}

