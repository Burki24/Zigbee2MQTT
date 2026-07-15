<?php

declare(strict_types=1);

/**
 * Verwaltet Install-Codes fuer Zigbee2MQTT und das zugehoerige Bridge-Formular.
 */
trait BridgeInstallCodeHelper
{
    /**
     * AddInstallCode
     *
     * @param string $Code Install-Code aus QR-Code oder Geraeteaufdruck.
     *
     * @return bool
     */
    public function AddInstallCode(string $Code): bool
    {
        $Code = trim($Code);
        if ($Code === '') {
            trigger_error($this->Translate('Install code is required.'), E_USER_NOTICE);
            return false;
        }

        return $this->SendCheckedSensitiveBridgeRequest('/bridge/request/install_code/add', ['value' => $Code]) !== false;
    }

    /**
     * Sendet einen Install-Code aus dem Bridge-Formular einmalig an Zigbee2MQTT.
     */
    private function SendInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $code = trim((string) ($selection['code'] ?? ''));
        if ($code === '') {
            $this->ShowInstallCodeMessage('Input required', 'Install code is required.');
            return false;
        }

        if (!$this->AddInstallCode($code)) {
            $this->ShowInstallCodeMessage('Install code could not be sent', 'Zigbee2MQTT did not accept the install code.');
            return false;
        }

        $this->ClearInstallCodeEditor();
        $this->ShowInstallCodeMessage('Install code sent', 'The install code was sent to Zigbee2MQTT.');
        return true;
    }

    /**
     * Speichert einen Install-Code lokal und sendet ihn anschliessend an Zigbee2MQTT.
     */
    private function SaveInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $id = trim((string) ($selection['id'] ?? ''));
        $label = trim((string) ($selection['label'] ?? ''));
        $code = trim((string) ($selection['code'] ?? ''));
        if ($label === '') {
            $this->ShowInstallCodeMessage('Input required', 'A label is required for stored install codes.');
            return false;
        }

        $catalog = $this->ReadAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG);
        $existingIndex = $this->FindStoredInstallCodeIndex($catalog, $id);
        if ($existingIndex !== null) {
            if ($code === '') {
                $code = (string) ($catalog[$existingIndex]['code'] ?? '');
            }
            $catalog[$existingIndex] = [
                'id'    => $id,
                'label' => $label,
                'code'  => $code
            ];
        } else {
            if ($code === '') {
                $this->ShowInstallCodeMessage('Input required', 'Install code is required.');
                return false;
            }
            $catalog[] = [
                'id'    => bin2hex(random_bytes(8)),
                'label' => $label,
                'code'  => $code
            ];
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG, $this->NormalizeStoredInstallCodeCatalog($catalog));
        $this->UpdateStoredInstallCodeFormList();
        $this->ClearInstallCodeEditor();

        if (!$this->AddInstallCode($code)) {
            $this->ShowInstallCodeMessage('Install code saved', 'The install code was saved locally but could not be sent to Zigbee2MQTT.');
            return false;
        }

        $this->ShowInstallCodeMessage('Install code saved', 'The install code was saved locally and sent to Zigbee2MQTT.');
        return true;
    }

    /**
     * Uebernimmt einen gespeicherten Install-Code zur Bearbeitung in das Formular.
     */
    private function SelectStoredInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $entry = $this->FindStoredInstallCode((string) ($selection['id'] ?? ''));
        if ($entry === null) {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        $this->TryUpdateFormField('InstallCodeCatalogID', 'value', (string) $entry['id']);
        $this->TryUpdateFormField('InstallCodeLabel', 'value', (string) $entry['label']);
        $this->TryUpdateFormField('InstallCode', 'value', '');
        $this->TryUpdateFormField('InstallCodeEditorHint', 'visible', true);
        return true;
    }

    /**
     * Sendet einen lokal gespeicherten Install-Code erneut.
     */
    private function SendStoredInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $entry = $this->FindStoredInstallCode((string) ($selection['id'] ?? ''));
        if ($entry === null) {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        if (!$this->AddInstallCode((string) $entry['code'])) {
            $this->ShowInstallCodeMessage('Install code could not be sent', 'Zigbee2MQTT did not accept the install code.');
            return false;
        }

        $this->ShowInstallCodeMessage('Install code sent', 'The install code was sent to Zigbee2MQTT.');
        return true;
    }

    /**
     * Oeffnet den Bestaetigungsdialog zum Loeschen eines gespeicherten Install-Codes.
     */
    private function RequestDeleteStoredInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $entry = $this->FindStoredInstallCode((string) ($selection['id'] ?? ''));
        if ($entry === null) {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_INSTALL_CODE_DELETE, $entry);
        $this->TryUpdateFormField(
            'InstallCodeDeleteWarningText',
            'caption',
            \sprintf($this->Translate('Delete stored install code "%s"? This cannot be undone.'), (string) $entry['label'])
        );
        $this->TryUpdateFormField('InstallCodeDeleteWarning', 'visible', true);
        return true;
    }

    /**
     * Loescht den zuvor ausgewaehlten Install-Code nach Bestaetigung.
     */
    private function ConfirmPendingStoredInstallCodeDelete(): bool
    {
        $pending = $this->ReadAttributeArray(self::ATTRIBUTE_PENDING_INSTALL_CODE_DELETE);
        $id = (string) ($pending['id'] ?? '');
        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_INSTALL_CODE_DELETE, []);
        $this->TryUpdateFormField('InstallCodeDeleteWarning', 'visible', false);
        if ($id === '') {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        if ($this->FindStoredInstallCode($id) === null) {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        $catalog = array_values(array_filter(
            $this->ReadAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG),
            static fn (mixed $entry): bool => !\is_array($entry) || (string) ($entry['id'] ?? '') !== $id
        ));
        $this->WriteAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG, $this->NormalizeStoredInstallCodeCatalog($catalog));
        $this->UpdateStoredInstallCodeFormList();
        $this->ClearInstallCodeEditor();
        $this->ShowInstallCodeMessage('Install code deleted', 'The stored install code was deleted.');
        return true;
    }

    /**
     * Liefert einen gespeicherten Install-Code anhand seiner internen ID.
     */
    private function FindStoredInstallCode(string $id): ?array
    {
        $catalog = $this->NormalizeStoredInstallCodeCatalog($this->ReadAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG));
        $index = $this->FindStoredInstallCodeIndex($catalog, trim($id));
        return $index === null ? null : $catalog[$index];
    }

    /**
     * Liefert den Index eines gespeicherten Install-Codes.
     */
    private function FindStoredInstallCodeIndex(array $catalog, string $id): ?int
    {
        if ($id === '') {
            return null;
        }
        foreach ($catalog as $index => $entry) {
            if (\is_array($entry) && (string) ($entry['id'] ?? '') === $id) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Bereinigt den gespeicherten Install-Code-Katalog.
     */
    private function NormalizeStoredInstallCodeCatalog(array $catalog): array
    {
        $normalized = [];
        foreach ($catalog as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $id = trim((string) ($entry['id'] ?? ''));
            $label = trim((string) ($entry['label'] ?? ''));
            $code = trim((string) ($entry['code'] ?? ''));
            if ($id === '' || $label === '' || $code === '') {
                continue;
            }
            $normalized[] = [
                'id'    => $id,
                'label' => $label,
                'code'  => $code
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label']));
        return $normalized;
    }

    /**
     * Baut die maskierten Listenzeilen fuer das Bridge-Formular.
     */
    private function BuildStoredInstallCodeFormValues(): array
    {
        $values = [];
        foreach ($this->NormalizeStoredInstallCodeCatalog($this->ReadAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG)) as $entry) {
            $values[] = [
                'id'          => $entry['id'],
                'label'       => $entry['label'],
                'masked_code' => $this->MaskInstallCode((string) $entry['code']),
                'send'        => $this->Translate('Send'),
                'edit'        => $this->Translate('Edit'),
                'delete'      => $this->Translate('Delete')
            ];
        }

        return $values;
    }

    /**
     * Maskiert einen Install-Code fuer die Anzeige.
     */
    private function MaskInstallCode(string $code): string
    {
        $length = \strlen($code);
        $visibleLength = min(4, $length);
        return str_repeat('*', max(4, $length - $visibleLength)) . substr($code, -$visibleLength);
    }

    /**
     * Aktualisiert die Install-Code-Liste in der geoeffneten Bridge-Konfiguration.
     */
    private function UpdateStoredInstallCodeFormList(): void
    {
        $values = $this->BuildStoredInstallCodeFormValues();
        $this->TryUpdateFormField('StoredInstallCodeList', 'values', json_encode($values));
        $this->TryUpdateFormField('StoredInstallCodeList', 'rowCount', min(8, max(3, \count($values) + 1)));
    }

    /**
     * Leert den Install-Code-Editor.
     */
    private function ClearInstallCodeEditor(): void
    {
        $this->TryUpdateFormField('InstallCodeCatalogID', 'value', '');
        $this->TryUpdateFormField('InstallCodeLabel', 'value', '');
        $this->TryUpdateFormField('InstallCode', 'value', '');
        $this->TryUpdateFormField('InstallCodeEditorHint', 'visible', false);
    }

    /**
     * Zeigt eine Install-Code-Rueckmeldung im Formular an.
     */
    private function ShowInstallCodeMessage(string $title, string $message): void
    {
        $this->TryUpdateFormField('InstallCodeMessageTitle', 'caption', $this->Translate($title));
        $this->TryUpdateFormField('InstallCodeMessageText', 'caption', $this->Translate($message));
        $this->TryUpdateFormField('InstallCodeMessage', 'visible', true);
    }

}
