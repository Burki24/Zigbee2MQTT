# Zigbee2MQTT Netzwerkkarte

Das Modul analysiert die Zigbee-Netzwerktopologie und stellt Geräte, Verbindungen, Routing-Tabellen und Scanfehler in IP-Symcon dar. Die letzte Analyse wird lokal gespeichert und als interaktive HTML-SDK-Kachel visualisiert.

## Einrichtung

1. Eine Instanz **Zigbee2MQTT Netzwerkkarte** anlegen.
2. Den gleichen MQTT-Parent wie bei der zugehörigen Zigbee2MQTT-Bridge auswählen.
3. Das MQTT-Basistopic der Zigbee2MQTT-Installation eintragen.
4. Eine Netzwerkanalyse starten.

## Netzwerkanalyse

Zigbee2MQTT fragt während einer Netzwerkanalyse jeden erreichbaren Router nacheinander ab. Dadurch kann das Zigbee-Netz vorübergehend stärker belastet werden.

- **Verbindungen analysieren** fordert LQI- und Nachbarschaftsdaten ohne Routing-Tabellen an.
- **Verbindungen und Routen analysieren** liest zusätzlich die Routing-Tabellen der Router.

Die Anfrage läuft vollständig asynchron. Die Symcon-Instanz wartet nicht blockierend auf die Antwort und zeigt währenddessen die verstrichene Zeit an. Bei großen Netzwerken kann die Analyse lange dauern. In einem Netzwerk mit ungefähr 100 Geräten und 69 Routern dauerte die Analyse ohne Routen etwa vier Minuten und mit Routen etwa 18 Minuten.

Während einer laufenden Analyse kann keine zweite Analyse gestartet werden. Falls Zigbee2MQTT keine Antwort mehr liefert, setzt **Scanstatus zurücksetzen** ausschließlich den lokalen Symcon-Status zurück.

## Diagnoseansichten

Die Konfiguration zeigt:

- Geräte mit Typ, Modell, Netzwerkadresse, IEEE-Adresse, `last_seen` und Scanstatus
- gerichtete Verbindungen mit LQI, Beziehung, Tiefe und Anzahl zugehöriger Routen
- Routing-Einträge mit Zieladresse, nächstem Hop und Status
- fehlgeschlagene LQI- oder Routing-Abfragen

LQI-Werte sind gerichtet und können für Hin- und Rückweg unterschiedlich sein. Ein niedriger LQI oder eine fehlende Verbindung ist ein Diagnosehinweis, aber nicht automatisch ein Gerätefehler.

## Visualisierung

Die HTML-SDK-Kachel bietet eine interaktive Darstellung der zuletzt gespeicherten Analyse:

- Coordinator, Router und Endgeräte werden unterschiedlich dargestellt.
- Schwache Verbindungen und Verbindungen mit aktiven Routen können hervorgehoben werden.
- Geräte lassen sich anklicken, verschieben, zoomen und gemeinsam in die Ansicht einpassen.
- **Vollbild** öffnet die Netzwerkkarte als bildschirmfüllende Darstellung und lässt sich über denselben Button oder `Esc` wieder schließen.
- Die Kachel startet selbst keine Netzwerkanalyse.

Der Vergrößerungspfeil von Symcon öffnet bei individuellen HTML-SDK-Kacheln die normale Detailansicht der Instanz. Das PHP-SDK bietet derzeit keine eigene HTML-Darstellung für diese maximierte Detailansicht. Da die Netzwerkkarten-Instanz keine darzustellenden Kindobjekte benötigt, bleibt diese Symcon-Ansicht leer. Für die große grafische Darstellung ist deshalb der Button **Vollbild** innerhalb der Netzwerkkarte vorgesehen.

Für die lokale Graphdarstellung wird [Cytoscape.js](https://js.cytoscape.org/) unter MIT-Lizenz mitgeliefert. Es werden keine externen Webressourcen nachgeladen.

## Exporte

Aus der gespeicherten RAW-Analyse erzeugt Symcon lokal:

- JSON-Rohdaten
- Graphviz-DOT
- PlantUML

Die Exporte starten keinen zusätzlichen Netzwerkscan. Wegen der Größe vollständiger Netzwerkanalysen werden die Dateien nicht über den begrenzten Symcon-Ausgabepuffer heruntergeladen, sondern unter `user/IPSZigbee2MQTT/networkmaps` auf dem Symcon-Server gespeichert.

## Skriptfunktionen

| Funktion | Beschreibung |
|---|---|
| `Z2M_StartNetworkScan(InstanceID, IncludeRoutes)` | Startet eine asynchrone RAW-Netzwerkanalyse. Mit `IncludeRoutes = true` werden zusätzlich Routing-Tabellen abgefragt. |
| `Z2M_ResetNetworkScanStatus(InstanceID)` | Setzt ausschließlich den lokalen Status einer vermeintlich hängen gebliebenen Analyse zurück. |
| `Z2M_ExportNetworkMapRaw(InstanceID)` | Gibt die zuletzt gespeicherte RAW-Topologie als JSON zurück. |
| `Z2M_ExportNetworkMapGraphviz(InstanceID)` | Erzeugt aus der gespeicherten Topologie eine Graphviz-DOT-Darstellung. |
| `Z2M_ExportNetworkMapPlantUML(InstanceID)` | Erzeugt aus der gespeicherten Topologie eine PlantUML-Darstellung. |
| `Z2M_CreateNetworkMapExportFiles(InstanceID)` | Speichert RAW-, Graphviz- und PlantUML-Dateien unter `user/IPSZigbee2MQTT/networkmaps`. |

Nur `Z2M_StartNetworkScan()` fordert neue Daten von Zigbee2MQTT an. Alle Exportfunktionen arbeiten lokal mit der zuletzt vollständig empfangenen Analyse.
