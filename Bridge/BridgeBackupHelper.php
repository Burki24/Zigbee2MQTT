<?php

declare(strict_types=1);

/**
 * Erstellt und speichert Zigbee2MQTT-Backups fuer die Bridge.
 */
trait BridgeBackupHelper
{
    /**
     * Erstellt ein Zigbee2MQTT-Backup und speichert es als ZIP-Datei im Symcon-Benutzerverzeichnis.
     *
     * @return string Absoluter Dateipfad oder leer bei Fehler.
     */
    public function CreateBackupFile(): string
    {
        $data = $this->RequestBackupData();
        if ($data === false) {
            return '';
        }

        $directory = $this->GetBackupDirectory();
        if (!$this->EnsureDirectory($directory)) {
            return '';
        }

        $filename = $directory . DIRECTORY_SEPARATOR . 'zigbee2mqtt-backup-' . date('Ymd-His') . '.zip';
        if (!$this->WriteBackupZipFile($data, $filename)) {
            @unlink($filename);
            return '';
        }

        return $filename;
    }

    /**
     * Erstellt ein Backup aus der Form und zeigt nur einen kurzen Status an.
     */
    private function CreateBackupFileFromForm(): bool
    {
        $filename = $this->CreateBackupFile();
        if ($filename === '') {
            $this->ShowBackupMessage(
                $this->Translate('Backup failed'),
                $this->Translate('No backup could be created. Please check the bridge debug log.')
            );
            return false;
        }

        $this->ShowBackupMessage($this->Translate('Backup created'), $this->Translate('Backup saved to:') . ' ' . $filename);
        return true;
    }

    /**
     * Fragt Zigbee2MQTT nach einem Backup.
     */
    private function RequestBackupData(): array|false
    {
        return $this->SendCheckedBridgeRequest('/bridge/request/backup', [], self::TIMEOUT_ZIGBEE_BACKUP_REQUEST);
    }

    /**
     * Dekodiert die von Zigbee2MQTT gelieferten Base64-Daten chunkweise in eine ZIP-Datei.
     *
     * @param array  $data     Antwortdaten der Zigbee2MQTT-Bridge.
     * @param string $filename Zieldatei fuer das dekodierte ZIP-Backup.
     */
    private function WriteBackupZipFile(array $data, string $filename): bool
    {
        $sourceFilename = $this->GetBackupBase64SourceFile($data);
        if ($sourceFilename === '') {
            return false;
        }

        try {
            return $this->DecodeBase64FileToFile($sourceFilename, $filename);
        } finally {
            @unlink($sourceFilename);
        }
    }

    /**
     * Liefert eine temporaere Quelldatei mit den Base64-kodierten Backupdaten.
     *
     * Der normale MQTT-Pfad legt die Daten bereits vor der Transaktionsspeicherung als Datei ab.
     * Der Inline-Fallback bleibt fuer Kompatibilitaet mit direkten Antworten erhalten.
     *
     * @param array $data Antwortdaten der Zigbee2MQTT-Bridge.
     */
    private function GetBackupBase64SourceFile(array $data): string
    {
        if (isset($data['zip_file']) && \is_string($data['zip_file']) && $this->IsBackupTransactionFile($data['zip_file'])) {
            return $data['zip_file'];
        }

        $base64 = $data['zip'] ?? null;
        if (!\is_string($base64) || $base64 === '') {
            return '';
        }

        $filename = tempnam(sys_get_temp_dir(), 'IPSZigbee2MQTT-backup-');
        if (!\is_string($filename)) {
            return '';
        }

        if (file_put_contents($filename, $base64, LOCK_EX) === false) {
            @unlink($filename);
            return '';
        }

        return $filename;
    }

    /**
     * Prueft, ob eine Transaktionsdatei aus dem internen Zigbee2MQTT-Temp-Verzeichnis stammt.
     */
    private function IsBackupTransactionFile(string $filename): bool
    {
        if (!is_file($filename)) {
            return false;
        }

        $directory = realpath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT');
        $resolvedFilename = realpath($filename);
        if (!\is_string($directory) || !\is_string($resolvedFilename)) {
            return false;
        }

        return str_starts_with($resolvedFilename, $directory . DIRECTORY_SEPARATOR);
    }

    /**
     * Dekodiert eine Base64-Datei blockweise in eine binaere Zieldatei.
     */
    private function DecodeBase64FileToFile(string $sourceFilename, string $targetFilename): bool
    {
        $source = @fopen($sourceFilename, 'rb');
        if ($source === false) {
            return false;
        }

        $target = @fopen($targetFilename, 'wb');
        if ($target === false) {
            fclose($source);
            return false;
        }

        $success = true;
        $remainder = '';

        try {
            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) {
                    $success = false;
                    break;
                }

                $chunk = $remainder . $chunk;
                $decodeLength = \strlen($chunk) - (\strlen($chunk) % 4);
                if ($decodeLength === 0) {
                    $remainder = $chunk;
                    continue;
                }

                $decoded = base64_decode(substr($chunk, 0, $decodeLength), true);
                if (!\is_string($decoded) || !$this->WriteStreamData($target, $decoded)) {
                    $success = false;
                    break;
                }

                $remainder = substr($chunk, $decodeLength);
            }

            if ($success && $remainder !== '') {
                $decoded = base64_decode($remainder, true);
                $success = \is_string($decoded) && $this->WriteStreamData($target, $decoded);
            }
        } finally {
            fclose($source);
            fclose($target);
        }

        return $success;
    }

    /**
     * Schreibt einen Datenblock vollstaendig in einen offenen Stream.
     *
     * @param resource $stream
     */
    private function WriteStreamData($stream, string $data): bool
    {
        $length = \strlen($data);
        $written = 0;

        while ($written < $length) {
            $bytes = fwrite($stream, substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                return false;
            }

            $written += $bytes;
        }

        return true;
    }

    /**
     * Zeigt das Ergebnis der Backup-Erstellung im Formular an.
     */
    private function ShowBackupMessage(string $title, string $message): void
    {
        $this->TryUpdateFormField('BackupMessageTitle', 'caption', $title);
        $this->TryUpdateFormField('BackupMessageText', 'caption', $message);
        $this->TryUpdateFormField('BackupMessage', 'visible', true);
    }

    /**
     * Liefert das Zielverzeichnis fuer lokal abgelegte Backups.
     */
    private function GetBackupDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), '\\/') . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT' . DIRECTORY_SEPARATOR . 'backups';
    }

    /**
     * Legt ein Verzeichnis bei Bedarf an.
     */
    private function EnsureDirectory(string $directory): bool
    {
        return is_dir($directory) || @mkdir($directory, 0777, true);
    }

}

