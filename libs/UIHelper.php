<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 *
 * 
 */

trait UIHelper
{
    /* -----------------------------------------------------------
     * 🔥 PRESET FINDER
     * ----------------------------------------------------------- */
    protected function findClosestPreset(int $value, string $property): ?int
    {
        $presets = [];

        if (isset(self::$presetDefinitions[$property]['values'])) {
            $presets = self::$presetDefinitions[$property]['values'];
        }

        if (empty($presets)) {
            return null;
        }

        $closestValue = null;
        $smallestDiff = PHP_INT_MAX;

        foreach ($presets as $presetValue => $label) {

            $diff = abs($value - (int)$presetValue);

            if ($diff < $smallestDiff) {
                $smallestDiff = $diff;
                $closestValue = (int)$presetValue;
            }
        }

        return $closestValue;
    }

    /* -----------------------------------------------------------
     * 🔥 PRESET SYNC HELPER
     * ----------------------------------------------------------- */
    protected function syncPresetVariable(string $property, int $value): void
    {
        $presetIdent = $property . '_preset';

        if (@$this->GetIDForIdent($presetIdent) === false) {
            return;
        }

        $closest = $this->findClosestPreset($value, $property);

        if ($closest !== null) {
            $this->SetValueDirect($presetIdent, $closest);

            $this->SendDebug(__FUNCTION__, 'Preset synced: ' . $property . ' → ' . $closest, 0);
        }
    }
}
