<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait mit Hilfsfunktionen fuer moderne Variablendarstellungen in der Tile-Visualisierung.
 */
trait VariablePresentationHelper
{
    /**
     * Setzt fuer die Kelvin-Farbtemperaturvariable die moderne Tile-Darstellung.
     *
     * Zigbee2MQTT liefert color_temp in Mired. Fuer die Bedienung in der Tile-Visu
     * wird die zusaetzliche color_temp_kelvin-Variable verwendet und der Bereich
     * aus den Mired-Grenzen des Exposes nach Kelvin umgerechnet.
     */
    protected function ApplyColorTemperaturePresentation(string $ident, array $feature): void
    {
        $minKelvin = 1000;
        $maxKelvin = 12000;

        if (isset($feature['value_min'], $feature['value_max']) && (int) $feature['value_min'] > 0 && (int) $feature['value_max'] > 0) {
            $minKelvin = $this->convertMiredToKelvin((int) $feature['value_max']);
            $maxKelvin = $this->convertMiredToKelvin((int) $feature['value_min']);

            if ($minKelvin > $maxKelvin) {
                [$minKelvin, $maxKelvin] = [$maxKelvin, $minKelvin];
            }
        }

        $this->ApplySliderPresentation($ident, [
            'MIN'                 => $minKelvin,
            'MAX'                 => $maxKelvin,
            'STEP_SIZE'           => 1,
            'GRADIENT_TYPE'       => 2,
            'USAGE_TYPE'          => 1,
            'SUFFIX'              => ' K',
            'DIGITS'              => 0,
            'ICON'                => 'temperature-half',
        ]);
    }

    /**
     * Setzt eine generische Schieberegler-Darstellung.
     *
     * Die Methode ist bewusst als Basis fuer weitere Tile-Visualisierungen gedacht.
     */
    protected function ApplySliderPresentation(string $ident, array $presentation): void
    {
        if (!$this->SupportsCustomPresentation()) {
            return;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return;
        }

        IPS_SetVariableCustomPresentation($variableID, array_merge([
            'PRESENTATION'          => \constant('VARIABLE_PRESENTATION_SLIDER'),
            'MIN'                   => 0,
            'MAX'                   => 100,
            'STEP_SIZE'             => 1,
            'GRADIENT_TYPE'         => 0,
            'CUSTOM_GRADIENT'       => [],
            'USAGE_TYPE'            => 0,
            'PREFIX'                => '',
            'SUFFIX'                => '',
            'PERCENTAGE'            => false,
            'THOUSANDS_SEPARATOR'   => '',
            'DIGITS'                => 0,
            'DECIMAL_SEPARATOR'     => 'Client',
            'ICON'                  => '',
            'INTERVALS_ACTIVE'      => false,
            'INTERVALS'             => []
        ], $presentation));
    }

    /**
     * Prueft, ob die laufende Symcon-Version moderne Custom Presentations unterstuetzt.
     */
    private function SupportsCustomPresentation(): bool
    {
        return \function_exists('IPS_SetVariableCustomPresentation') && \defined('VARIABLE_PRESENTATION_SLIDER');
    }
}
