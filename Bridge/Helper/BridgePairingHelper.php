<?php

declare(strict_types=1);

/**
 * Steuert Pairing-Zustand, Timer und Zielauswahl der Zigbee2MQTT-Bridge.
 */
trait BridgePairingHelper
{
    /**
     * Registriert den Timer fuer die laufende Pairing-Restzeit.
     */
    protected function RegisterPermitJoinTimer(): void
    {
        try {
            $this->RegisterTimer(
                self::TIMER_PERMIT_JOIN_STATUS,
                0,
                "IPS_RequestAction(\$_IPS['TARGET'], 'UpdatePermitJoinStatus', true);"
            );
        } catch (\Throwable) {
            // Timer operations can be temporarily unavailable while the module is being updated.
        }
    }

    /**
     * Aktiviert oder deaktiviert die laufende Pairing-Restzeit.
     */
    protected function SetPermitJoinTimerInterval(int $milliseconds): void
    {
        try {
            $this->SetTimerInterval(self::TIMER_PERMIT_JOIN_STATUS, $milliseconds);
        } catch (\Throwable) {
            // Timer operations can be temporarily unavailable while the module is being updated.
        }
    }

    /**
     * Startet den Pairing-Modus mit den Eingaben aus der Bridge-Konfiguration.
     */
    private function StartPairingFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        return $this->SetPermitJoinTarget(
            (int) ($selection['time'] ?? self::MAX_PERMIT_JOIN_DURATION),
            (string) ($selection['device'] ?? '')
        );
    }

    /**
     * Uebernimmt den Pairing-Zustand in Variablen, Buffer, Timer und Formular.
     */
    private function ApplyPermitJoinState(bool $active, int $end, string $target): void
    {
        $this->PermitJoinTarget = $active ? trim($target) : '';
        $this->SetValue('permit_join', $active);
        $this->SetValue('permit_join_end', $active ? $end : 0);
        $this->SetValue('permit_join_target', $active ? $this->FormatPairingTarget($target) : '');
        $this->UpdatePermitJoinStatus();
    }

    /**
     * Berechnet die verbleibende Pairing-Zeit und aktualisiert das Formular.
     */
    private function UpdatePermitJoinStatus(bool $updateForm = true): void
    {
        $active = (bool) $this->GetValue('permit_join');
        $end = (int) $this->GetValue('permit_join_end');
        if ($active && $this->PermitJoinTarget === '') {
            $this->PermitJoinTarget = trim((string) $this->GetValue('permit_join_target'));
        }
        $remaining = $active && $end > 0 ? max(0, $end - time()) : 0;

        if ($active && $end > 0 && $remaining === 0) {
            $active = false;
            $this->PermitJoinTarget = '';
            $this->SetValue('permit_join', false);
            $this->SetValue('permit_join_end', 0);
            $this->SetValue('permit_join_target', '');
        }

        $this->SetValue('permit_join_remaining', $remaining);
        $this->SetPermitJoinTimerInterval($active && $end > 0 ? 1000 : 0);

        if (!$updateForm) {
            return;
        }

        $this->TryUpdateFormField('PairingStatus', 'caption', $this->BuildPairingStatusCaption());
        $this->TryUpdateFormField('StartPairing', 'enabled', !$active);
        $this->TryUpdateFormField('StopPairing', 'enabled', $active);
    }

    /**
     * Normalisiert den von Zigbee2MQTT gelieferten Unix-Zeitstempel.
     */
    private function NormalizePermitJoinEnd(mixed $value): int
    {
        if (is_numeric($value)) {
            $timestamp = (int) $value;
            return $timestamp > 20000000000 ? (int) floor($timestamp / 1000) : max(0, $timestamp);
        }

        if (\is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? 0 : $timestamp;
        }

        return 0;
    }

    /**
     * Baut den lesbaren Pairing-Status fuer das Formular.
     */
    private function BuildPairingStatusCaption(?array $devices = null): string
    {
        if (!(bool) $this->GetValue('permit_join')) {
            return $this->Translate('Pairing mode is closed.');
        }

        $remaining = (int) $this->GetValue('permit_join_remaining');
        $target = $this->FormatPairingTarget($this->PermitJoinTarget, $devices);
        if ($remaining <= 0) {
            return sprintf($this->Translate('Pairing mode is open via %s.'), $target);
        }

        return sprintf(
            $this->Translate('Pairing mode is open via %s. Remaining time: %s.'),
            $target,
            $this->FormatOTARemaining($remaining)
        );
    }

    /**
     * Baut die Auswahl aus gesamtem Netzwerk, Coordinator und vorhandenen Routern.
     */
    private function BuildPairingTargetOptions(?array $devices = null): array
    {
        $options = [
            ['caption' => $this->Translate('Entire network'), 'value' => ''],
            ['caption' => $this->Translate('Coordinator'), 'value' => 'coordinator']
        ];
        $routers = [];
        foreach (($devices ?? $this->BuildNetworkSecurityDevices()) as $device) {
            if (!\is_array($device) || strcasecmp((string) ($device['type'] ?? ''), 'Router') !== 0) {
                continue;
            }

            $ieeeAddress = trim((string) ($device['ieee_address'] ?? ''));
            if ($ieeeAddress === '') {
                continue;
            }

            $caption = trim((string) ($device['friendly_name'] ?? $ieeeAddress));
            $model = trim((string) ($device['model'] ?? ''));
            if ($model !== '' && $model !== $caption) {
                $caption .= ' (' . $model . ')';
            }
            $routers[$ieeeAddress] = ['caption' => $caption, 'value' => $ieeeAddress];
        }

        uasort($routers, static fn (array $left, array $right): int => strnatcasecmp($left['caption'], $right['caption']));
        return array_merge($options, array_values($routers));
    }

    /**
     * Formatiert ein Pairing-Ziel fuer Statusvariable und Formular.
     */
    private function FormatPairingTarget(string $target, ?array $devices = null): string
    {
        $target = trim($target);
        if ($target === '') {
            return $this->Translate('Entire network');
        }
        if (strcasecmp($target, 'coordinator') === 0) {
            return $this->Translate('Coordinator');
        }

        foreach (($devices ?? $this->BuildNetworkSecurityDevices()) as $device) {
            if (!\is_array($device) || strcasecmp((string) ($device['ieee_address'] ?? ''), $target) !== 0) {
                continue;
            }

            $friendlyName = trim((string) ($device['friendly_name'] ?? ''));
            return $friendlyName !== '' ? $friendlyName : $target;
        }

        return $target;
    }

}
