<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Stellt wiederverwendbare Prüfungen für verbundene Symcon-Instanzen bereit.
 */
trait InstanceConnectionHelper
{
    /**
     * Prüft, ob eine Instanz mit demselben Splitter wie die aktuelle Instanz verbunden ist.
     */
    protected function IsInstanceConnectedToSameSplitter(int $instanceID): bool
    {
        if (!IPS_InstanceExists($instanceID)) {
            return false;
        }

        $connectionID = IPS_InstanceExists($this->InstanceID)
            ? (int) IPS_GetInstance($this->InstanceID)['ConnectionID']
            : 0;

        return (int) IPS_GetInstance($instanceID)['ConnectionID'] === $connectionID;
    }
}
