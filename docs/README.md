# Dokumentation / Documentation

## Benutzeranleitungen / User guides

- [Deutsch](../README.md) / [English](../README.en.md): Installation, Upgrade von 5.x auf 6.0, Voraussetzungen und Übersicht.
- [Beta-Hinweise / Beta release notes](BETA_RELEASE_NOTES.md): synchronisierte Texte in Deutsch und Englisch, einschließlich Erkennen-Migration und WebFront-Einschränkung.
- [Changelog Deutsch](../CHANGELOG.md) / [English changelog](../CHANGELOG.en.md): konsolidierter Vergleich mit der letzten Beta 5.43.
- [Historische Änderungsnotizen](CHANGELOG_HISTORY.md): deutsche Entwicklungschronik und ältere 5.x-Einträge; Zwischenstände sind keine aktuelle Bedienungsanleitung.

Alle sechs Modulverzeichnisse und `actions/` enthalten deutsche und englische READMEs. Die englischen Texte beschreiben denselben aktuellen Funktionsumfang; Screenshots können deutsche Oberflächen zeigen.

Each of the six module directories and `actions/` includes German and English READMEs covering the current functionality. Screenshots may show the German interface. Historical German development notes are retained as history rather than presented as current release behavior.

## Entwicklung und Nachweise / Development and evidence

- [Prüfnachweis / Verification](VERIFICATION.md): realer Test der Erkennen-Migration, Dokumentationsprüfung und Abgleich mit Beta 5.43; Grenzen der Nachweise.
- [Simulator Deutsch](../tests/DeviceSimulator/README.md) / [English simulator guide](../tests/DeviceSimulator/README.en.md): isolierte MQTT-Simulation ohne Funkhardware.
- [Übersetzungssuche](SymconScripts/FindMissingTranslations.php): bewusst auszuführendes Symcon-Skript, das standardmäßig einen Bericht als Stringvariable unterhalb des Skripts erzeugt. `refreshInstancesBeforeScan` ist standardmäßig aus; die Aktivierung kann Instanzen neu übernehmen und MQTT-Kommunikation auslösen. Nur nach Prüfung der Optionen verwenden.

The translation scanner is a manually executed Symcon script. By default it creates a report variable beneath the script. Its optional pre-scan refresh is disabled; enabling it reapplies instance configuration and can cause MQTT traffic. Review the settings before running it. It does not automatically install translations or publish anything.

`tests/stubs` and `.style` are external submodules. Their upstream READMEs are maintained by their respective projects and are not translated or rewritten as part of the library's user documentation.
