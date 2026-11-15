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

## Community-Nachweise und abschließende Bewertung

Ergänzt am **15.11.2026** für Kandidat `d94f05c77319ccdc9f29c2376a1e7d5f904b10a7` (6.00, Build 2473). Der vollständige öffentlich zugängliche [Testing-Thread](https://community.symcon.de/t/modul-zigbee2mqtt-v6-0-testing/144007) wurde gelesen: 211 abrufbare Beiträge bis Beitragsnummer 212; Nummer 196 war im öffentlichen Beitragsstrom nicht enthalten. Forum-Bildanhänge und verlinkte Debug-Dateien wurden für diese Bewertung nicht zusätzlich ausgewertet.

| Quelle | Praktischer Nachweis |
| --- | --- |
| [HarmonyFan, Beitrag 58](https://community.symcon.de/t/144007/58) | Unauffälliger Umstieg auf V6 und Betrieb ohne veränderte Bewegungsmelder-Reaktionszeit. |
| [brdiver677, Beitrag 133](https://community.symcon.de/t/144007/133) und [154](https://community.symcon.de/t/144007/154) | Wochen-/monatelanger Testing-Betrieb; später bestätigte Korrektur der Extension-Installation. |
| [doctee, Beitrag 155](https://community.symcon.de/t/144007/155) | Sauberes Update auf 6.00 Build 2413; zwei Bridges, fünf RGB-Einzelgeräte und eine Gruppe aus drei LEDs: Farben, Dimmen, Farbtemperatur und Alexa erfolgreich. |
| [doctee, Beitrag 164](https://community.symcon.de/t/144007/164) und [172](https://community.symcon.de/t/144007/172) | Explizit Symcon 9.1; nach Darstellungskorrektur fehlerfreier Betrieb bestätigt. |
| [hagi01, Beitrag 201](https://community.symcon.de/t/144007/201), [204–207](https://community.symcon.de/t/144007/204) | Explizit Symcon 9.0 und Modul 6.0, Geräteerstellung über den Konfigurator. WebFront-Darstellungsproblem reproduziert; Neuladen als Abhilfe dokumentiert. Dies ist kein pauschaler Fehlerfreiheitsnachweis. |
| [Hein09, Beiträge 189–190](https://community.symcon.de/t/144007/189) | Nach korrigierter Extension positive Rückmeldung zum zuvor gestörten Verbindungsbetrieb. |
| [Burki24, Beitrag 157](https://community.symcon.de/t/144007/157) und direkte Bestätigung im Prüfauftrag | Produktiver Betrieb mit zwei MQTT-Servern, zwei Bridges und zwei Konfiguratoren; Upgrade und anschließender Betrieb vom Betreiber bestätigt. |

Damit wird die frühere pauschale Einstufung „praktischer Upgrade-/Betriebsnachweis für Symcon 9.0/9.1 fehlt“ zurückgenommen. Die Community-Erfahrungen betreffen verschiedene Entwicklungsstände und ersetzen keinen identischen vollständigen Upgrade-Test des aktuellen Commits. Zusammen mit den Regressionstests und dem gezielten Live-Migrationstest reichen sie als Praxisnachweise für eine öffentliche Beta aus.

**Bewertung: keine bestätigten technischen Beta-Blocker; fachliche Empfehlung zur Beta bleibt bestehen.** Aktuelle reale Nachweise für alle sechs Neuanlagen ohne Voraussetzungen sowie sämtliche Discovery-/Konfigurator-Konstellationen sind weiterhin Abdeckungslücken, keine bestätigten Fehler. Die im Thread behandelten früheren Fehler werden anhand nachfolgender Korrekturen und Rückmeldungen bewertet; eine bloße Update-Ankündigung ist kein eigener erfolgreicher Laufzeittest.

Am Kandidaten wurden erneut 366 PHP-Tests / 4.844 Assertions unter PHP 8.5.10, 36 JavaScript-Tests, Syntax für 74 PHP-Dateien sowie Dokumentations-, JSON- und PHP-CS-Fixer-Prüfungen erfolgreich ausgeführt. GitHub Tests und Style sind für den direkten Vorgänger `ffbbc36` erfolgreich; `d94f05c` ändert danach ausschließlich `library.json`.

Im Store wurde ausschließlich der **Testing-Entwurf 21425** vorbereitet: Status `uploaded`, Paketierung `done`, Git-Commit `d94f05c77319ccdc9f29c2376a1e7d5f904b10a7`, deutsche und englische Hinweise mit Erkennen-Warnung, aktuelle Dokumentationslinks sowie Kategorien 1 und 5. Die veröffentlichte Testing 21085 bleibt unverändert; Stable- und Beta-Datensätze wurden vor und nach der Vorbereitung verglichen und sind unverändert. Ein Beta-Entwurf wird weder angelegt noch bearbeitet. Der Store-Kandidat ist der bereits verfügbare Commit `d94f05c`; dieser lokale Nachtrag wird erst nach einem späteren Commit/Push Bestandteil eines Store-Pakets. Keine Einreichung, Veröffentlichung oder Versionsänderung erfolgt durch diesen Nachtrag.

## Ergänzende Laufzeitprüfung: Neuanlage, Discovery und Trennung

Am **15.11.2026** auf dem Testsystem unter Symcon 9.1 (Revision wie oben) durchgeführt. Installiert war 6.00 Build 2473; der lokale Stand `d1e6207` ist Build 2475. Alle sechs Modul-Einstiegsdateien und `libs/ModulBase.php` stimmen nach Normalisierung der Zeilenenden per SHA-256 überein. Kein Deployment oder Reload war nötig.

- **Alle sechs Modultypen:** frisch ohne Parent, Topics, Zugangsdaten, Geräte oder Extension angelegt, `IPS_ApplyChanges` ausgeführt und das echte Konfigurationsformular geladen. Alle Formulare waren gültig und enthielten Elemente/Aktionen. Bridge, Configurator, Device, Group und Network Map blieben korrekt inaktiv (104); Discovery war aktiv (102). Keine Warnungen oder Exceptions.
- **Discovery ohne eigene Konfiguration:** echten Scan über `RequestAction('RefreshDiscoveryCache', true)` ausgeführt. Ein gültiger Cache mit zwei gefundenen MQTT-Anschlüssen wurde gespeichert und anschließend ein gültiges Formular geladen. Vorhandene Konfigurationen und Verbindungen der kontrollierten Instanzen blieben unverändert.
- **Konfigurator-Trennung:** echte temporäre, nicht mit einem Broker verbundene MQTT-Clients A/B und identische Basistopics verwendet. Installierte Zuordnungsmethoden wurden auf echten Kernel-Objekten per Reflection aufgerufen. Eigene Geräte, Gruppen und Bridges wurden erkannt; Instanzen am fremden Anschluss ausgeschlossen. Abweichendes Basistopic wurde ausgeschlossen. Geräte ohne physischen Gegenpart blieben anhand ihrer Instanz/IEEE sichtbar; Gruppen wurden zusätzlich nach GroupId korrekt getrennt.
- **Discovery-Zuordnung:** bei identischem Basistopic wurde je Anschluss der richtige Konfigurator ausgewählt; falsches Topic lieferte keinen Treffer. Unverbundene Konfiguratoren wurden bei dieser Anschlusszuordnung nicht versehentlich gewählt.
- **Ergebnis:** sechs erfolgreiche Neuanlagen und **18 erfolgreiche Zuordnungs-/Discovery-Prüfungen**; keine ununterdrückten Warnungen. Alle temporären Objekte entfernt; abschließende Suche nach `Codex Runtime` ohne Treffer. Keine Gerätebefehle, keine Änderungen an Produktivsystem oder Store.

Maschinenlesbare Ergebnisse: [runtime-readiness-2026-11-15.json](verification/runtime-readiness-2026-11-15.json).

**Grenzen:** Formulare wurden über die Kernel-API geprüft, ohne visuelle Browserprüfung. Die Trennung wurde mit den echten Auswahlmethoden und Kernel-Verbindungen geprüft, aber ohne vollständigen MQTT-Antwortdialog zur Befüllung sämtlicher Konfiguratorzeilen. Physische Geräteausfälle, ein tatsächlich ausgeführter Broker-Umzug und Symcon 9.0 wurden in diesem Lauf nicht getestet. Die entsprechenden zuvor pauschal offenen Punkte sind für Neuanlage und Discovery-Scan geschlossen; für die Trennung liegt nun ein Kernel-Integrationstest vor, kein vollständiger End-to-End-Nachweis aller Brokerkonstellationen. Ein früher Fixture-Lauf zeichnete auch eine absichtlich mit `@` unterdrückte fehlende Bridge-Eigenschaft auf; der finale Handler beachtet PHP `error_reporting`, ohne ununterdrückte Warnungen.

## English summary

The documentation and consolidated German/English changelogs were checked against the last public beta **5.43** (release `18221`, commit `ce8aba0de27c8e9a6ffbaa91759009fb34d11806`). The comparison includes 459 non-merge commits excluding automatic metadata updates. User-visible changes are grouped by their current behavior; superseded intermediate implementations are retained only as labelled historical notes.

The installed identify migration passed **22 checks in each of two real Symcon 9.1 cases**: custom legacy profile and custom native presentation, visible/hidden old variables, preserved IDs/values/references/user settings, disabled old module default action, working custom action and idempotent repetition. Tests used temporary instances without an MQTT parent and executed the real installed migration on real kernel objects. A custom action wrote only a separate test marker. All test objects were removed; production was unchanged.

Final local checks passed: **366 PHP tests / 4,844 assertions**, **36 JavaScript tests**, documentation checks covering **24 Markdown files / 377 local links / 10 language pairs**, JSON formatting, PHP-CS-Fixer (51 files, no changes required) and `git diff --check`.

The complete publicly available testing thread was reviewed (211 posts through post number 212). Community reports linked above and the maintainer's direct confirmation establish practical upgrade and operating experience, including explicit Symcon 9.0 and 9.1 reports, multiple bridges, lighting/group operations and positive feedback after extension fixes. The previous blanket finding of missing upgrade/operating evidence is withdrawn. Reports concern different development revisions and do not establish a complete identical upgrade test of the current commit. Together with regression tests and the focused live migration test, they support the beta recommendation with no confirmed technical blockers. Fresh real-kernel coverage of every module's missing-prerequisite creation and every discovery/configurator scenario remains incomplete.

Testing Store draft **21425** was prepared and verified: status `uploaded`, clone status `done`, exact candidate commit, updated German/English migration notes and documentation links, categories 1 and 5. Published Testing 21085 remains unchanged; Stable and Beta records were compared before and after and are unchanged. No Beta draft is created or edited. Its package targets the already available commit `d94f05c` (6.00, build 2473); this local report amendment will require a later owner commit/push to become part of a package. No release is submitted, no commit/push is performed and no version metadata is changed.


Additional runtime verification on Symcon 9.1 passed: all six fresh module types were created without prerequisites, applied and returned valid forms; five remained inactive and Discovery active. An unconfigured real Discovery scan persisted a valid cache with two MQTT connections. Eighteen integration checks passed for device/IEEE, group/GroupId and bridge isolation, same-topic Discovery matching and preservation of controlled configurations/connections. Temporary objects were removed and the final object search was empty. The installed build was 2473; normalized SHA-256 hashes of all six module entry files and ModulBase matched local build 2475. No deployment, Store change or hardware command was performed. Scope is kernel/API testing and real selection methods invoked through reflection; full MQTT response-driven Configurator rendering, visual browser checks, actual broker migration and Symcon 9.0 were not exercised. This supersedes the earlier missing-prerequisite creation and unconfigured Discovery evidence gaps while bounding the new isolation evidence precisely.
