<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Definition Konstanten
 */
trait Constants
{
    /** @var string Legacy-Verzeichnisname für die Migration alter Exposes-JSON-Dateien */
    protected const EXPOSES_DIRECTORY = 'Zigbee2MQTTExposes';
    /** @var string Basispfad für MQTT-Nachrichten */
    protected const MQTT_BASE_TOPIC = 'MQTTBaseTopic';
    /** @var string Spezifisches MQTT-Topic für dieses Gerät */
    protected const MQTT_TOPIC = 'MQTTTopic';
    /** @var string Topic für Verfügbarkeit */
    protected const AVAILABILITY_TOPIC = 'availability';
    /** @var string Topic für die Extension-Anfragen */
    protected const SYMCON_EXTENSION_REQUEST = '/SymconExtension/request/';
    /** @var string Topic für die Extension-Antworten */
    protected const SYMCON_EXTENSION_RESPONSE = '/SymconExtension/response/';
    /** @var string Topic für Extension Listen-Anfragen */
    protected const SYMCON_EXTENSION_LIST_REQUEST = '/SymconExtension/lists/request/';
    /** @var string Topic für Extension Listen-Anfragen */
    protected const SYMCON_EXTENSION_LIST_RESPONSE = '/SymconExtension/lists/response/';
    /** @var string GUID des MQTT Servers */
    protected const GUID_MQTT_SERVER = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    /** @var string GUID des MQTT Client */
    protected const GUID_MQTT_CLIENT = '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}';
    /** @var string GUID des Client Socket */
    protected const GUID_CLIENT_SOCKET = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';
    /** @var string GUID des Datenfluss zu einen MQTT Splitter */
    protected const GUID_MQTT_SEND = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
    /** @var string Name des Attribut welches die Modul-Version enthält */
    protected const ATTRIBUTE_MODUL_VERSION = 'Version';
    /** @var string GUID des Module Zigbee2MQTT Bridge */
    protected const GUID_MODULE_BRIDGE = '{00160D82-9E2F-D1BD-6D0B-952F945332C5}';
    /** @var string GUID des Module Zigbee2MQTT Konfigurator */
    protected const GUID_MODULE_CONFIGURATOR = '{D30BADA8-F261-4D9F-89A9-2E9961AF021F}';
    /** @var string GUID des Module Zigbee2MQTT Gerät */
    protected const GUID_MODULE_DEVICE = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';
    /** @var string GUID des Module Zigbee2MQTT Gruppe */
    protected const GUID_MODULE_GROUP = '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}';
    /** @var string summary of ATTRIBUTE_EXPOSES */
    protected const ATTRIBUTE_EXPOSES = 'Exposes';
    /** @var string Attribut fuer geraetespezifische filtered_attributes aus Z2M */
    protected const ATTRIBUTE_FILTERED = 'FilteredAttributes';
    /** @var string Attribut fuer die aktuellen geraetespezifischen Z2M-Optionen */
    protected const ATTRIBUTE_DEVICE_OPTIONS = 'DeviceOptions';
    /** @var string Attribut fuer die von Z2M gemeldeten Optionsdefinitionen */
    protected const ATTRIBUTE_DEVICE_OPTION_DEFINITIONS = 'DeviceOptionDefinitions';
    /** @var string Attribut fuer Endpoints, Bindings, Cluster und Reporting-Daten */
    protected const ATTRIBUTE_DEVICE_ENDPOINTS = 'DeviceEndpoints';
    /** @var string Attribut fuer die von Zigbee2MQTT gemeldete OTA-Faehigkeit */
    protected const ATTRIBUTE_DEVICE_SUPPORTS_OTA = 'DeviceSupportsOTA';
    /** @var string Attribut fuer den lokal bekannten Variablenkatalog */
    protected const ATTRIBUTE_VARIABLE_CATALOG = 'VariableCatalog';
    /** @var string Attribut fuer die aktuelle Mehrfachauswahl im Variablenkatalog */
    protected const ATTRIBUTE_VARIABLE_CATALOG_SELECTION = 'VariableCatalogSelection';
    /** @var string Attribut fuer vom Anwender deaktivierte Variablen */
    protected const ATTRIBUTE_DISABLED_VARIABLES = 'DisabledVariables';
    /** @var string Attribut fuer vom Anwender geloeschte Variablen */
    protected const ATTRIBUTE_DELETED_VARIABLES = 'DeletedVariables';
    /** @var string Attribut fuer aktuelle Zigbee2MQTT-Gruppenoptionen */
    protected const ATTRIBUTE_GROUP_OPTIONS = 'GroupOptions';
    /** @var string Attribut fuer aktuelle Zigbee2MQTT-Gruppenmitglieder */
    protected const ATTRIBUTE_GROUP_MEMBERS = 'GroupMembers';
    /** @var string Attribut fuer aktuelle Zigbee2MQTT-Gruppenszenen */
    protected const ATTRIBUTE_GROUP_SCENES = 'GroupScenes';
    /** @var string Property zum Deaktivieren der Mess-Schalter-Kachel */
    protected const PROPERTY_DISABLE_METERED_SWITCH_TILE = 'DisableMeteredSwitchTile';
    /** @var string Property zum Deaktivieren der Heizungs-Kachel */
    protected const PROPERTY_DISABLE_HEATING_TILE = 'DisableHeatingTile';
    /** @var string Property zum Deaktivieren der Sicherheits-Kachel */
    protected const PROPERTY_DISABLE_SECURITY_TILE = 'DisableSecurityTile';
    /** @var string Property zum Deaktivieren der Fenstergriff-Kachel */
    protected const PROPERTY_DISABLE_WINDOW_HANDLE_TILE = 'DisableWindowHandleTile';
    /** @var string Property zum Deaktivieren der Aktions-Kachel */
    protected const PROPERTY_DISABLE_ACTION_TILE = 'DisableActionTile';
    /** @var string Property zum Erzwingen der Sensor-Kachel bei Kombigeraeten */
    protected const PROPERTY_USE_SENSOR_TILE = 'UseSensorTile';
    /** @var string Property fuer den unteren Temperatur-Fallback der Sensor-Kachel */
    protected const PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MIN = 'TemperaturePresentationFallbackMin';
    /** @var string Property fuer den oberen Temperatur-Fallback der Sensor-Kachel */
    protected const PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MAX = 'TemperaturePresentationFallbackMax';
    /** @var string Property fuer den unteren Kelvin-Bereich der Farbtemperatur-Darstellung */
    protected const PROPERTY_COLOR_TEMPERATURE_PRESENTATION_MIN = 'ColorTemperaturePresentationMin';
    /** @var string Property fuer den oberen Kelvin-Bereich der Farbtemperatur-Darstellung */
    protected const PROPERTY_COLOR_TEMPERATURE_PRESENTATION_MAX = 'ColorTemperaturePresentationMax';
    /** @var string Property fuer das erste Heizungs-Kachel-Preset */
    protected const PROPERTY_HEATING_TILE_PRESET_1 = 'HeatingTilePreset1';
    /** @var string Property fuer das zweite Heizungs-Kachel-Preset */
    protected const PROPERTY_HEATING_TILE_PRESET_2 = 'HeatingTilePreset2';
    /** @var string Property fuer das dritte Heizungs-Kachel-Preset */
    protected const PROPERTY_HEATING_TILE_PRESET_3 = 'HeatingTilePreset3';
    /** @var int Wartezeit fuer Symcon-Extension Detailinformationen in Millisekunden */
    protected const TIMEOUT_SYMCON_EXTENSION_INFO = 20000;
    /** @var int Wartezeit fuer Zigbee2MQTT-Backup-Requests in Millisekunden */
    protected const TIMEOUT_ZIGBEE_BACKUP_REQUEST = 300000;
    /** @var int Wartezeit fuer Zigbee-Binding-Requests in Millisekunden */
    protected const TIMEOUT_ZIGBEE_BINDING_REQUEST = 15000;
}
