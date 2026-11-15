# Dokumentations- und Migrationsnachweis / Verification

Stand: **15.11.2026**. Diese Datei dokumentiert Prüfungen und deren Grenzen, keine Veröffentlichung oder Freigabe durch Symcon.

## Vergleichsbasis und Changelog

- Letzte veröffentlichte öffentliche Beta im Store: **5.43**, Release-ID `18221`, Commit `ce8aba0de27c8e9a6ffbaa91759009fb34d11806` im Repository `Nall-chan/Zigbee2MQTT`.
- Geprüfter Quellstand dieser Bibliothek: Commit `49d2336` sowie die darauf aufbauenden Dokumentationsänderungen im Arbeitsbaum. Versionsmetadaten wurden nicht geändert.
- Im Git-Vergleich seit dieser Beta wurden **459 Nicht-Merge-Commits ohne automatische Metadaten-Commits** betrachtet. Dazu gehören auch Dokumentations-, Formatierungs-, Screenshot-, Test- und Refactoring-Commits. Die Commit-Zeitstempel allein sind kein geeigneter Filter für den abgezweigten 6.0-Entwicklungszweig.
- Der bisherige zentrale Changelog enthielt hauptsächlich die jüngeren Korrekturen. Die älteren 6.0-Neuerungen standen nur in der Haupt-README und fehlten dort zuletzt um neue Änderungen seit August. Der aktuelle [Changelog](../CHANGELOG.md) und sein [englisches Gegenstück](../CHANGELOG.en.md) fassen nun den benutzersichtbaren Unterschied gegenüber Beta 5.43 zusammen.
- Abgedeckte Bereiche: Symcon-9-/Strict-Migration, native Darstellungen, tatsächlich mitgelieferte Kacheln, Katalog/Wartung, Optionen, Binding/Reporting, Gerätemanagement, Gruppen/Szenen, Netzwerkkarte, Diagnose, Backup/Install-Codes/Touchlink, Anlernen/Netzwerksicherheit, OTA, Actions, MQTT-/Cache-/Update-Stabilität, Farb-/Zahlen-/Einheitenverträge, Datenschutz, Übersetzungen und Erweiterung 6.09.
- Zwischenzeitlich eingeführte und wieder entfernte Funktionen werden nicht als aktuelle Neuerungen angeboten: insbesondere separate RGB-/Tunable-White-HTML-Kacheln, globale Profil-Diagnose und das externe veraltete Variablen-Löschskript. Die bisherigen chronologischen Notizen bleiben unter [Historie](CHANGELOG_HISTORY.md) erhalten und sind ausdrücklich als Zwischenstände gekennzeichnet.

Reproduzierbarer Git-Vergleich:

```text
git log --reverse --no-merges --format="%h %s" ce8aba0de27c8e9a6ffbaa91759009fb34d11806..49d2336
git diff --stat ce8aba0de27c8e9a6ffbaa91759009fb34d11806..49d2336
```

Metadaten-, Merge- und reine Test-/Formatierungsänderungen benötigen keinen eigenen Benutzer-Changelog-Punkt. Sachlich zusammengehörige Änderungen und spätere Korrekturen werden nach dem aktuellen Ergebnis beschrieben, nicht als einzelne Commit-Liste angekündigt.

## Echter Test der Erkennen-Migration

Auf dem **Testsystem mit Symcon 9.1**, Revision `rust-2b3c9295995acc6734252298bbea89f3dfe0e161`, war die korrigierte Migration bereits installiert. Es war kein erneutes Ausrollen oder Bibliotheks-Reload erforderlich. Das Produktivsystem blieb unverändert.

Zwei temporäre Geräteinstanzen ohne MQTT-Parent wurden angelegt. Die echte installierte Registrierungs-/Migrationsmethode wurde mit kontrollierten Daten auf echten Symcon-Objekten aufgerufen. Für die Altvariable wurden Modul-Standardaktion, Name, Icon, Beschreibung, Position, Wert, Referenz und Benutzeraktion eingerichtet; ein Fall verwendete ein Benutzerprofil, der andere eine Benutzerdarstellung. Der erste Fall war sichtbar, der zweite ausgeblendet.

Beide Fälle bestanden jeweils **22 Prüfungen**:

- neue String-Variable mit anderer ID, dem Ident `identify` und passender Modulaktion;
- alte Bool-Variable mit unverändertem Wert und unveränderter Link-Referenz;
- unveränderte Benutzeraktion, Profile und Darstellungen;
- unveränderte Sichtbarkeit, Name, Icon, Beschreibung, Position und Aktivierung;
- nur die alte Modul-Standardaktion deaktiviert;
- Benutzeraktion über `RequestAction` tatsächlich ausführbar: Sie schrieb einen unabhängigen temporären Marker, keinen Gerätebefehl;
- erneute Migration mit identischer neuer ID und Erhalt nachträglicher Benutzeränderungen an Name, Sichtbarkeit und Benutzeraktion.

Die Migration selbst führte keinen Identify-/MQTT-Befehl aus. Alle temporären Instanzen, Variablen, Links, Skripte und die Testkategorie wurden entfernt. Die abschließende Suche nach Testobjekten war leer; die Suche nach fehlerhaften Zigbee2MQTT-Instanzen ergab keine Treffer.

Maschinenlesbares Endergebnis: [identify-migration-2026-11-15.json](verification/identify-migration-2026-11-15.json).

**Grenzen:** Dies ist ein echter Kernel-/Migrationstest, aber kein vollständiges Backup-basiertes Upgrade sämtlicher 5.x-Instanzen, kein Test aller sechs Modultypen mit Funkhardware und kein zusätzlicher Symcon-9.0-Lauf. Der neue Identify-Befehl wurde nicht an ein echtes Gerät gesendet. Bereits in älteren Testing-Versionen entfernte Benutzeranpassungen können ohne Originaldaten nicht automatisch wiederhergestellt werden.

## Dokumentationsprüfung

Die Hauptanleitung, alle sechs Modul-READMEs, Actions und Simulator wurden gegen Code, Formulare, öffentliche API-Namen und aktuelle Verträge geprüft. Englische Gegenstücke decken denselben aktuellen Bedienumfang ab; deutsche Screenshots bleiben auch in englischen Anleitungen verwendbar.

Korrigiert wurden insbesondere veraltete 5.x-Verweise und Markenbezeichnungen, fehlende Migrationshinweise, die nicht mehr vorhandenen eigenen Lichtkacheln, asynchrone GET-Semantik, Rückgabewerte der Schreib- und Exportfunktionen, Debugexport als Data-URI, aktuelle Extension-Funktionen, manuelle Installation und Broker-/Instanztrennung. Die Beta-Store-Texte bleiben lokale Vorlagen; der englische Dokumentationslink zeigt nun auf `README.en.md`.

Abschließende Ergebnisse: **366 PHP-Tests mit 4.844 Assertions**, **36 JavaScript-Tests**, Dokumentationsprüfung mit **24 Markdown-Dateien, 377 lokalen Links und zehn Sprachpaaren** bestanden. JSON-Formatprüfung, PHP-CS-Fixer (51 Dateien, keine Änderungen erforderlich) und `git diff --check` ebenfalls ohne Fehler.

Prüfbefehle:

```text
python tests/check_documentation.py
php .style/json-check.php
node --test tests/ExtensionsTest.cjs
phpunit --configuration tests/phpunit.xml tests
php-cs-fixer check --config=.style/.php-cs-fixer.php --allow-risky=yes --using-cache=no --sequential
git diff --check
```

`check_documentation.py` prüft vorhandene lokale Ziele, Markdown-Anker, zehn Sprachpaare und die DE-/EN-Abdeckung öffentlicher `Z2M_*`-Aufrufe. Es prüft keine externen HTTP-Ziele und ersetzt keine sprachliche oder visuelle Prüfung.

Die PHP-Suite benötigt einen sauberen Bibliotheksbaum mit vorhandenen Stub-Submodulen. Ein zusätzliches lokales `state`-Verzeichnis ohne `module.json` wird vom vorhandenen Stub-Loader als Modul behandelt und erzeugt Testumgebungsfehler. Deshalb wurde die Suite in einer temporären Kopie der versionierten Dateien einschließlich der vorhandenen Submodule ausgeführt. Es wurde weder die Testinfrastruktur noch das private lokale Verzeichnis dafür verändert.

Externe Submodule `.style` und `tests/stubs` wurden gelesen, aber nicht redaktionell verändert. Ausführliche Laufzeit-/Testanleitungen stehen hier statt in Benutzer-READMEs.

## English summary

The documentation and consolidated German/English changelogs were checked against the last public beta **5.43** (release `18221`, commit `ce8aba0de27c8e9a6ffbaa91759009fb34d11806`). The comparison includes 459 non-merge commits excluding automatic metadata updates. User-visible changes are grouped by their current behavior; superseded intermediate implementations are retained only as labelled historical notes.

The installed identify migration passed **22 checks in each of two real Symcon 9.1 cases**: custom legacy profile and custom native presentation, visible/hidden old variables, preserved IDs/values/references/user settings, disabled old module default action, working custom action and idempotent repetition. Tests used temporary instances without an MQTT parent and executed the real installed migration on real kernel objects. A custom action wrote only a separate test marker. All test objects were removed; production was unchanged.

Final local checks passed: **366 PHP tests / 4,844 assertions**, **36 JavaScript tests**, documentation checks covering **24 Markdown files / 377 local links / 10 language pairs**, JSON formatting, PHP-CS-Fixer (51 files, no changes required) and `git diff --check`.

This does not establish a complete 5.x installation upgrade, all hardware operations or Symcon 9.0 runtime behavior. No release was submitted, no Store text was changed, no commit/push was performed and no version metadata was updated. See the commands and machine-readable evidence above for reproducible checks.
