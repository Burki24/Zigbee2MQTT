<?php

declare(strict_types=1);

class DiscoveryMqttClient extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('UserName', '');
        $this->RegisterPropertyString('Password', '');
    }
}
