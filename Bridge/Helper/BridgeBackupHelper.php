<?php

declare(strict_types=1);

/**
 * Erstellt und speichert Zigbee2MQTT-Backups fuer die Bridge.
 */
trait BridgeBackupHelper
{
    /**
     * Erstellt ein Zigbee2MQTT-Backup im privaten Symcon-Datenverzeichnis.
     *
     * @return string Absoluter Dateipfad oder leer bei Fehler.
     */
    public function CreateBackupFile(): string
    {
        $directory = $this->GetBackupDirectory();
        if (!$this->EnsureBackupDirectory($directory)) {
            return '';
        }

        $temporaryFile = @tempnam($directory, 'backup-');
        if ($temporaryFile === false) {
            return '';
        }
        try {
            if (dirname($temporaryFile) !== realpath($directory)) {
                return '';
            }
            $data = $this->RequestBackupData();
            if ($data === false || !$this->WriteBackupZipFile($data, $temporaryFile)) {
                return '';
            }
            $filename = $directory . DIRECTORY_SEPARATOR . 'zigbee2mqtt-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.zip';
            return @rename($temporaryFile, $filename) ? $filename : '';
        } finally {
            @unlink($temporaryFile);
        }
    }

    protected function GetBackupKernelDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), '\\/');
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
        return $this->GetBackupKernelDirectory() . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT' . DIRECTORY_SEPARATOR . 'backups';
    }

    /**
     * Übernimmt vorhandene ZIPs aus dem bisherigen WebServer-Verzeichnis.
     */
    private function MigrateLegacyBackupFiles(): void
    {
        $legacy = $this->GetBackupKernelDirectory() . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($legacy)) {
            return;
        }

        $lock = 'Zigbee2MQTT.LegacyBackupMigration';
        if (!IPS_SemaphoreEnter($lock, 1000)) {
            return;
        }
        try {
            if (!$this->MoveLegacyBackupFiles($legacy)) {
                $this->LogMessage($this->Translate('Some Zigbee2MQTT backups could not be moved to the private directory. Check the diagnostic log and the old backup directory.'), KL_WARNING);
            }
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }

    private function MoveLegacyBackupFiles(string $legacy): bool
    {
        if (is_link($legacy) || is_link(dirname($legacy))) {
            return false;
        }
        $entries = @scandir($legacy);
        if ($entries === false) {
            return false;
        }

        $directory = $this->GetBackupDirectory();
        $success = true;
        foreach (preg_grep('/\Azigbee2mqtt-backup-\d{8}-\d{6}\.zip\z/', $entries) as $entry) {
            $source = $legacy . DIRECTORY_SEPARATOR . $entry;
            if (is_link($source) || !is_file($source)) {
                continue;
            }
            if (!$this->EnsureBackupDirectory($directory)) {
                return false;
            }
            $target = $directory . DIRECTORY_SEPARATOR . $entry;
            $stem = substr($target, 0, -4);
            for ($suffix = 1; file_exists($target) || is_link($target); $suffix++) {
                $target = $stem . '-' . $suffix . '.zip';
            }
            if (@rename($source, $target)) {
                @chmod($target, 0600);
            } else {
                $success = false;
            }
        }
        return $success;
    }

    private function EnsureBackupDirectory(string $directory): bool
    {
        return !is_link($directory) && !is_link(dirname($directory))
            && (is_dir($directory) || @mkdir($directory, 0700, true) || is_dir($directory));
    }

}
