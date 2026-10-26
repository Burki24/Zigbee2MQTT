<?php

declare(strict_types=1);

class DiscoveryClientSocket extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyInteger('Port', 1883);
        $this->RegisterPropertyBoolean('UseSSL', false);
    }
}
