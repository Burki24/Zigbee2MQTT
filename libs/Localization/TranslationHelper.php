<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer globale Moduluebersetzungen und fehlende Zigbee2MQTT-Uebersetzungen.
 */
trait TranslationHelper
{
    /**
     * Ueberschreibt Translate, um die globalen Moduluebersetzungen zu nutzen.
     */
    public function Translate(string $Text): string
    {
        $translation = array_replace_recursive(
            $this->readTranslationFile('locale.json'),
            $this->readTranslationFile('locale_z2m.json')
        );

        $language = IPS_GetSystemLanguage();
        $code = explode('_', $language)[0];
        if (isset($translation['translations'])) {
            if (isset($translation['translations'][$language])) {
                if (isset($translation['translations'][$language][$Text])) {
                    return $translation['translations'][$language][$Text];
                }
            } elseif (isset($translation['translations'][$code])) {
                if (isset($translation['translations'][$code][$Text])) {
                    return $translation['translations'][$code][$Text];
                }
            }
        }

        return $Text;
    }

    /**
     * Zeigt die gesammelten fehlenden Uebersetzungen in der Konfigurationsform.
     */
    protected function ShowMissingTranslations(): bool
    {
        $this->UpdateFormField('ShowMissingTranslations', 'visible', true);
        $values = [];
        foreach ($this->missingTranslations as $translation) {
            $key = array_key_first($translation);
            $values[] = [
                'type'  => $key,
                'value' => $translation[$key]
            ];
        }
        $this->UpdateFormField('MissingTranslationsList', 'values', json_encode($values));

        return true;
    }

    /**
     * Prueft, ob ein Wert in locale_z2m.json vorhanden ist.
     */
    private function isValueInLocaleJson(string $Text, string $Type): bool
    {
        $translation = $this->readTranslationFile('locale_z2m.json');
        $language = IPS_GetSystemLanguage();
        $code = explode('_', $language)[0];
        if (isset($translation['translations'])) {
            if (isset($translation['translations'][$language])) {
                if (isset($translation['translations'][$language][$Text])) {
                    return true;
                }
            } elseif (isset($translation['translations'][$code])) {
                if (isset($translation['translations'][$code][$Text])) {
                    return true;
                }
            }
        }

        $this->addValueToTranslationsBuffer($Text, $Type);
        return false;
    }

    /**
     * Fuegt einen Wert zum Missing-Translations-Buffer hinzu.
     */
    private function addValueToTranslationsBuffer(string $value, string $type): void
    {
        $translations = $this->missingTranslations;
        $missingKVP = [$type => $value];
        if (!\in_array($missingKVP, $translations)) {
            $translations[] = $missingKVP;
            $this->missingTranslations = $translations;
        }
    }

    /**
     * Liefert den absoluten Pfad zu einer globalen Uebersetzungsdatei.
     */
    private function getTranslationFilePath(string $filename): string
    {
        return dirname(__DIR__) . '/' . $filename;
    }

    /**
     * Liest eine globale Uebersetzungsdatei mit einem sicheren Fallback waehrend Modul-Updates.
     */
    private function readTranslationFile(string $filename): array
    {
        $content = @file_get_contents($this->getTranslationFilePath($filename));
        if (!\is_string($content)) {
            return [];
        }

        $translation = json_decode($content, true);
        return \is_array($translation) ? $translation : [];
    }
}
