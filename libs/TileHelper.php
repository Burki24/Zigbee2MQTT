<?php

trait TileHelper
{
    protected function getLightTile(): array
    {
        $kelvinID = @$this->GetIDForIdent('color_temp_kelvin');
        $presetID = @$this->GetIDForIdent('color_temp_preset');
        $stateID  = @$this->GetIDForIdent('state');

        return [
            'type'   => 'container',
            'layout' => 'vertical',
            'items'  => [

                [
                    'type'    => 'label',
                    'caption' => $this->InstanceName
                ],

                [
                    'type'     => 'switch',
                    'variable' => $stateID
                ],

                [
                    'type'     => 'slider',
                    'caption'  => 'Farbtemperatur',
                    'variable' => $kelvinID
                ],

                [
                    'type'     => 'buttonGroup',
                    'caption'  => 'Presets',
                    'variable' => $presetID
                ]
            ]
        ];
    }
}
