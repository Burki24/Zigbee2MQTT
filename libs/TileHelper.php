<?php

trait TileHelper
{
    /* -----------------------------------------------------------
     * ENTRY POINT
     * ----------------------------------------------------------- */
    protected function getTile(): array
    {
        return match ($this->detectTileType()) {

            'light'  => $this->getLightTile(),
            'sensor' => $this->getSensorTile(),
            'switch' => $this->getSwitchTile(),

            default  => $this->getDefaultTile()
        };
    }

    /* -----------------------------------------------------------
     * TILE TYPE DETECTION
     * ----------------------------------------------------------- */
    protected function detectTileType(): string
    {
        $exposes = $this->ReadAttributeArray('exposes');

        if (empty($exposes)) {
            return 'default';
        }

        foreach ($exposes as $expose) {

            // GROUP TYPES
            if (isset($expose['type'])) {

                switch ($expose['type']) {

                    case 'light':
                        return 'light';

                    case 'switch':
                        return 'switch';

                    case 'sensor':
                        return 'sensor';
                }
            }

            // PROPERTY BASED FALLBACK
            if (isset($expose['property'])) {

                switch ($expose['property']) {

                    case 'temperature':
                    case 'humidity':
                    case 'co2':
                        return 'sensor';

                    case 'state':
                        return 'switch';
                }
            }
        }

        return 'default';
    }

    /* -----------------------------------------------------------
     * LIGHT TILE (DEIN HAUPTCASE)
     * ----------------------------------------------------------- */
    protected function getLightTile(): array
    {
        $items = [];

        // Header
        $items[] = [
            'type'    => 'label',
            'caption' => $this->InstanceName
        ];

        // ON/OFF
        if ($this->varExists('state')) {
            $items[] = [
                'type'     => 'switch',
                'variable' => $this->GetIDForIdent('state')
            ];
        }

        // Kelvin Slider
        if ($this->varExists('color_temp_kelvin')) {
            $items[] = [
                'type'     => 'slider',
                'caption'  => 'Farbtemperatur',
                'variable' => $this->GetIDForIdent('color_temp_kelvin')
            ];
        }

        // Presets
        if ($this->varExists('color_temp_preset')) {
            $items[] = [
                'type'     => 'buttonGroup',
                'caption'  => 'Presets',
                'variable' => $this->GetIDForIdent('color_temp_preset')
            ];
        }

        return [
            'type'   => 'container',
            'layout' => 'vertical',
            'items'  => $items
        ];
    }

    /* -----------------------------------------------------------
     * SENSOR TILE
     * ----------------------------------------------------------- */
    protected function getSensorTile(): array
    {
        $items = [
            [
                'type'    => 'label',
                'caption' => $this->InstanceName
            ]
        ];

        foreach (['temperature', 'humidity', 'co2'] as $var) {

            if ($this->varExists($var)) {
                $items[] = [
                    'type'     => 'value',
                    'variable' => $this->GetIDForIdent($var)
                ];
            }
        }

        return [
            'type'   => 'container',
            'layout' => 'vertical',
            'items'  => $items
        ];
    }

    /* -----------------------------------------------------------
     * SWITCH TILE
     * ----------------------------------------------------------- */
    protected function getSwitchTile(): array
    {
        if ($this->varExists('state')) {
            return [
                'type'     => 'switch',
                'variable' => $this->GetIDForIdent('state')
            ];
        }

        return $this->getDefaultTile();
    }

    /* -----------------------------------------------------------
     * DEFAULT TILE
     * ----------------------------------------------------------- */
    protected function getDefaultTile(): array
    {
        return [
            'type'    => 'label',
            'caption' => 'Keine passende Kachel'
        ];
    }

    /* -----------------------------------------------------------
     * HELPER: VARIABLE EXISTIERT?
     * ----------------------------------------------------------- */
    protected function varExists(string $ident): bool
    {
        return @\IPS_GetObjectIDByIdent($ident, $this->InstanceID) !== false;
    }
}
