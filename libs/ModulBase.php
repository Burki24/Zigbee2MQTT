<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

require_once __DIR__ . '/AttributeArrayHelper.php';
require_once __DIR__ . '/BufferHelper.php';
require_once __DIR__ . '/ModulHelper/ModuleRuntimeSafetyHelper.php';
require_once __DIR__ . '/InstanceConnectionHelper.php';
require_once __DIR__ . '/SemaphoreHelper.php';
require_once __DIR__ . '/Localization/TranslationHelper.php';
require_once __DIR__ . '/Configuration/DeviceFormHelper.php';
require_once __DIR__ . '/Configuration/VariableCatalogHelper.php';
require_once __DIR__ . '/Maintenance/VariableMaintenanceHelper.php';
require_once __DIR__ . '/Visualization/VariablePresentationHelper.php';
require_once __DIR__ . '/Visualization/TileHelpers/MeteredSwitchTileHelper.php';
require_once __DIR__ . '/Visualization/TileHelpers/HeatingTileHelper.php';
require_once __DIR__ . '/Visualization/TileHelpers/SensorTileHelper.php';
require_once __DIR__ . '/Visualization/TileHelpers/SecurityTileHelper.php';
require_once __DIR__ . '/Visualization/TileHelpers/WindowHandleTileHelper.php';
require_once __DIR__ . '/Visualization/TileHelpers/ActionTileHelper.php';
require_once __DIR__ . '/MQTTHelper.php';
require_once __DIR__ . '/ColorHelper.php';
require_once __DIR__ . '/ModulHelper/DeviceCommandHelper.php';
require_once __DIR__ . '/ModulHelper/PayloadStructureHelper.php';
require_once __DIR__ . '/ModulHelper/PayloadProcessingHelper.php';
require_once __DIR__ . '/ModulHelper/PayloadVariableHelper.php';
require_once __DIR__ . '/ModulHelper/VariableValueProcessingHelper.php';
require_once __DIR__ . '/ModulHelper/VariableRuntimeHelper.php';
require_once __DIR__ . '/ModulHelper/DeviceActionHelper.php';
require_once __DIR__ . '/ModulHelper/ExposeVariableRegistrationHelper.php';

/**
 * Gemeinsame Basisklasse für Zigbee2MQTT-Geräte- und Gruppeninstanzen.
 *
 * Die Klasse koordiniert den Symcon-Lebenszyklus, den MQTT-Empfang, Migrationen
 * und gemeinsam benötigte Zustände. Die fachliche Verarbeitung ist überwiegend
 * in Traits ausgelagert. Modulbezogene Traits liegen unter `libs/ModulHelper`,
 * Konfigurations-, Wartungs- und Visualisierungshelfer in ihren jeweiligen
 * Unterverzeichnissen von `libs`.
 *
 * Die angegebenen Pseudoeigenschaften werden durch den `BufferHelper` über
 * `__get()` und `__set()` im Instanzpuffer gespeichert.
 *
 * @property bool  $BUFFER_MQTT_SUSPENDED          Sperrt die Verarbeitung eingehender MQTT-Nachrichten während Initialisierung oder Migration.
 * @property bool  $BUFFER_PROCESSING_MIGRATION    Kennzeichnet eine aktuell laufende Bestandsmigration.
 * @property array $lastPayload                    Enthält den über mehrere Nachrichten zusammengeführten Gerätezustand.
 * @property array $latestPayload                  Enthält ausschließlich das zuletzt empfangene Geräte-Payload.
 * @property array $missingTranslations            Sammelt während der Laufzeit erkannte, noch fehlende Übersetzungen.
 *
 * @see \Zigbee2MQTT\DeviceActionHelper Aktionsverarbeitung in `libs/ModulHelper/DeviceActionHelper.php`.
 * @see \Zigbee2MQTT\DeviceCommandHelper Gerätebefehle in `libs/ModulHelper/DeviceCommandHelper.php`.
 * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Expose- und Variablenregistrierung in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
 * @see \Zigbee2MQTT\PayloadProcessingHelper MQTT-Payload-Verarbeitung in `libs/ModulHelper/PayloadProcessingHelper.php`.
 * @see \Zigbee2MQTT\PayloadStructureHelper Aufbereitung verschachtelter Payloads in `libs/ModulHelper/PayloadStructureHelper.php`.
 * @see \Zigbee2MQTT\PayloadVariableHelper Zuordnung von Payloadwerten zu Variablen in `libs/ModulHelper/PayloadVariableHelper.php`.
 * @see \Zigbee2MQTT\VariableValueProcessingHelper Wertkonvertierung in `libs/ModulHelper/VariableValueProcessingHelper.php`.
 * @see \Zigbee2MQTT\VariableRuntimeHelper Variablenzugriffe zur Laufzeit in `libs/ModulHelper/VariableRuntimeHelper.php`.
 * @see \Zigbee2MQTT\ModuleRuntimeSafetyHelper Defensive Symcon-Zugriffe in `libs/ModulHelper/ModuleRuntimeSafetyHelper.php`.
 */
abstract class ModulBase extends \IPSModuleStrict
{
    use AttributeArrayHelper;
    use BufferHelper;
    use ModuleRuntimeSafetyHelper;
    use InstanceConnectionHelper;
    use Semaphore;
    use ColorHelper;
    use DeviceCommandHelper;
    use PayloadStructureHelper;
    use PayloadProcessingHelper;
    use PayloadVariableHelper;
    use VariableValueProcessingHelper;
    use VariableRuntimeHelper;
    use DeviceActionHelper;
    use ExposeVariableRegistrationHelper;
    use TranslationHelper;
    use VariableCatalogHelper;
    use \Zigbee2MQTT\Maintenance\VariableMaintenanceHelper;
    use DeviceFormHelper;
    use VariablePresentationHelper;
    use MeteredSwitchTileHelper;
    use HeatingTileHelper;
    use SensorTileHelper;
    use SecurityTileHelper;
    use WindowHandleTileHelper;
    use ActionTileHelper;
    use SendData;
    private const MINIMAL_MODUL_VERSION = 5.1;

    /**
     * Namensschema und reguläre Ausdrücke für Status-Identifikatoren.
     *
     * `BASE` bezeichnet den Stamm `state`. Die Suffixe bilden nummerierte,
     * richtungsbezogene und kombinierte Kanäle ab. `MQTT` validiert Property-Namen
     * aus Payloads, `SYMCON` zusätzlich historische Schreibweisen vorhandener
     * Symcon-Variablen.
     *
     * @var array{
     *     PREFIX:string,
     *     BASE:string,
     *     SUFFIX:array{NUMERIC:string,DIRECTION:string,COMBINED:string},
     *     MQTT:string,
     *     SYMCON:string
     * }
     * @see \Zigbee2MQTT\DeviceActionHelper Statusaktionen in `libs/ModulHelper/DeviceActionHelper.php`.
     * @see self::convertToSnakeCase()
     */
    private const STATE_PATTERN = [
        'PREFIX' => '',
        'BASE'   => 'state',
        'SUFFIX' => [
            'NUMERIC'   => '_[0-9]+',
            'DIRECTION' => '_(?:left|right)',
            'COMBINED'  => '_(?:left|right)_[0-9]+'
        ],
        'MQTT'   => '/^state(?:_[a-z0-9]+)?$/i',  // Für MQTT-Payload
        'SYMCON' => '/^[Ss]tate(?:_?(?:[Ll][0-9]+)|(?:[Ll]eft|[Rr]ight)(?:[Ll][0-9]+)?)?$/'
    ];

    /**
     * Einheiten, deren numerische Exposes standardmäßig als Float-Variable angelegt werden.
     *
     * @var string[]
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Typbestimmung in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     */
    private const FLOAT_UNITS = [
        '%',
        'A',
        'atm',
        'bar',
        'Bq',
        'cd',
        'cm',
        'cm3',
        'cm³',
        'd',
        'dB',
        'dB/m',
        'dBA',
        'dBC',
        'dl',
        'F',
        'g',
        'g/m3',
        'g/m³',
        'GHz',
        'GPa',
        'GW',
        'GWh',
        'Gy',
        'h',
        'H',
        'hPa',
        'Hz',
        'K',
        'kat',
        'kg',
        'kg/m3',
        'kg/m³',
        'kHz',
        'km/h',
        'kohm',
        'kPa',
        'kV',
        'kVA',
        'kvar',
        'kW',
        'kWh',
        'L',
        'l',
        'L/h',
        'l/h',
        'L/min',
        'l/min',
        'L/s',
        'l/s',
        'lb',
        'Liter',
        'liter',
        'm',
        'm/s',
        'm3',
        'm³',
        'm3/h',
        'm³/h',
        'm3/min',
        'm³/min',
        'm3/s',
        'm³/s',
        'mA',
        'mbar',
        'mF',
        'mg',
        'mg/L',
        'mg/m3',
        'mg/m³',
        'mH',
        'MHz',
        'min',
        'ml',
        'mm',
        'mm3',
        'mm³',
        'mohm',
        'mol',
        'mol/L',
        'mol/l',
        'MPa',
        'ms',
        'mS',
        'mV',
        'MW',
        'MWh',
        'N',
        'nF',
        'nm',
        'ns',
        'ohm',
        'Pa',
        'pF',
        'pH',
        'ppb',
        'ppm',
        'psi',
        'rad',
        's',
        'S',
        'sr',
        'Sv',
        'ton',
        'torr',
        'ug',
        'ug/m3',
        'ug/m³',
        'V',
        'VA',
        'var',
        'W',
        'W/m2',
        'W/m²',
        'Wh',
        '°C',
        '°F',
        'µA',
        'µF',
        'µg',
        'µg/m3',
        'µg/m³',
        'µH',
        'µm',
        'µmol/m²/s',
        'µS',
        'µs',
        'µV'
    ];

    /**
     * Bekannte Abkürzungen für die Migration historischer Identifikatoren.
     *
     * Die Einträge verhindern, dass zusammengehörige Kürzel wie `CO2`, `LED`
     * oder `RGB` bei der Umwandlung in `lower_snake_case` falsch zerlegt werden.
     *
     * @var string[]
     * @see self::convertToSnakeCase()
     */
    private const KNOWN_ABBREVIATIONS = [
        'VOC',
        'CO2',
        'PM25',
        'LED',
        'RGB',
        'HSV',
        'HSL',
        'XY',
        'MV',
        'KV',
        'MA',
        'KW',
        'MW',
        'GW',
        'kWH',
        'MWH',
        'GWH',
        'KHZ',
        'MHZ',
        'GHZ',
        'PH',
        'KPA',
        'MPA',
        'GPA',
        'MS',
        'MF',
        'NF',
        'PF',
        'MH',
        'DB',
        'DBA',
        'DBC'
    ];

    /**
     * Historische Z2M-Identifikatoren, die bei der Migration unverändert bleiben.
     *
     * Diese Sonderfälle können wegen eines abweichenden Variablentyps oder einer
     * nicht eindeutig ableitbaren Zielbezeichnung nicht automatisch konvertiert
     * werden. Beispielsweise entspricht `Z2M_ActionTransTime` fachlich
     * `action_transition_time`.
     *
     * @var string[]
     * @see self::Migrate()
     */
    private const SKIP_IDENTS = [
        'Z2M_ActionTransaction',
        'Z2M_ActionTransTime',
        'Z2M_XAxis',
        'Z2M_YAxis',
        'Z2M_ZAxis'
    ];

    /**
     * Composite-Properties, die nicht in einzelne Variablen aufgelöst werden.
     *
     * Die Einträge enthalten Metadatenstrukturen und keine eigenständigen
     * Gerätezustände.
     *
     * @var string[]
     * @see \Zigbee2MQTT\PayloadStructureHelper Payload-Strukturierung in `libs/ModulHelper/PayloadStructureHelper.php`.
     */
    private const SKIP_COMPOSITES = [
        'device',       // Geräteinformationen nicht als Einzelvariablen anlegen
        'endpoints',    // Endpoint-Informationen nicht als Einzelvariablen anlegen
        'options'       // Optionsstruktur nicht als Einzelvariablen anlegen
    ];

    /**
     * Erweiterung des Symcon-Extension-Topics für Geräte oder Gruppen.
     *
     * Die abgeleiteten Klassen müssen den jeweiligen Teilpfad bereitstellen,
     * beispielsweise `getDeviceInfo/` oder `getGroupInfo/`. Er wird beim Aufbau
     * des Empfangsfilters und beim Laden der Geräte- beziehungsweise
     * Gruppeninformationen verwendet.
     *
     * @var string
     * @see self::ApplyChanges()
     * @see self::LoadDeviceInfo()
     */
    protected static $ExtensionTopic = '';

    /**
     * Ordnet ausgewählte Expose-Features einem festen Symcon-Variablentyp zu.
     *
     * Die Zuordnung entscheidet ausschließlich über den Datentyp. Profile und
     * native Darstellungen werden davon unabhängig ermittelt.
     *
     * @var array<int, array{group_type:string, feature:string, variableType:int}>
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Variablenregistrierung in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     * @see \Zigbee2MQTT\VariableValueProcessingHelper Typprüfung eingehender Werte in `libs/ModulHelper/VariableValueProcessingHelper.php`.
     */
    protected static $VariableTypeMappings = [
        ['group_type' => 'cover', 'feature' => 'position', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => 'cover', 'feature' => 'position_left', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => 'cover', 'feature' => 'position_right', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'temperature', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'dewpoint', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'humidity', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'soil_moisture', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'local_temperature', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'battery', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'current', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'energy', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'power', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'occupancy', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'motion', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'pi_heating_demand', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'presence', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'illuminance', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'illuminance_lux', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'child_lock', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'window_open', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'valve', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'window_detection', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'contact', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'tamper', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'smoke', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'battery_low', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'automatic_valve_adapt', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => 'light', 'feature' => 'color', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => 'climate', 'feature' => 'occupied_heating_setpoint', 'variableType' => VARIABLETYPE_FLOAT]
    ];

    /**
     * Definitionen bekannter Sondervariablen ohne vollständige Expose-Metadaten.
     *
     * @var array<string,array{type:int,name?:string,ident?:string}>
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Registrierung in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     * @see \Zigbee2MQTT\VariableValueProcessingHelper Verarbeitung eingehender Sonderwerte in `libs/ModulHelper/VariableValueProcessingHelper.php`.
     */
    protected static $specialVariables = [
        'last_seen'                  => ['type' => VARIABLETYPE_INTEGER, 'name' => 'Last Seen'],
        'color_mode'                 => ['type' => VARIABLETYPE_STRING, 'name' => 'Color Mode'],
        'update'                     => ['type' => VARIABLETYPE_STRING, 'name' => 'Firmware Update Status'],
        'device_temperature'         => ['type' => VARIABLETYPE_FLOAT, 'name' => 'Device Temperature'],
        'brightness'                 => ['type' => VARIABLETYPE_INTEGER, 'ident' => 'brightness'],
        'brightness_l1'              => ['type' => VARIABLETYPE_INTEGER, 'name' => 'brightness_l1'],
        'brightness_l2'              => ['type' => VARIABLETYPE_INTEGER, 'name' => 'brightness_l2'],
        'voltage'                    => ['type' => VARIABLETYPE_FLOAT, 'ident' => 'voltage'],
        'calibration_time'           => ['type' => VARIABLETYPE_FLOAT],
        'countdown'                  => ['type' => VARIABLETYPE_INTEGER],
        'countdown_l1'               => ['type' => VARIABLETYPE_INTEGER],
        'countdown_l2'               => ['type' => VARIABLETYPE_INTEGER],
        'update__installed_version'  => ['type' => VARIABLETYPE_INTEGER, 'name' => 'Installed Version'],
        'update__latest_version'     => ['type' => VARIABLETYPE_INTEGER, 'name' => 'Latest Version'],
        'update__state'              => ['type' => VARIABLETYPE_STRING, 'name' => 'Update State'],
        'update__progress'           => ['type' => VARIABLETYPE_FLOAT, 'name' => 'Update Progress'],
        'update__remaining'          => ['type' => VARIABLETYPE_FLOAT, 'name' => 'Update Remaining'],
        'no_occupancy_since'         => ['type' => VARIABLETYPE_INTEGER, 'name' => 'No occupancy since']
    ];

    /**
     * Definitionen bekannter Status-Features mit festen Werten.
     *
     * @var array<string,array{
     *     type:string,
     *     dataType:int,
     *     values:array<int,string>,
     *     ident:string,
     *     enableAction?:bool
     * }>
     * @see \Zigbee2MQTT\DeviceActionHelper Aktionsverarbeitung in `libs/ModulHelper/DeviceActionHelper.php`.
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Aktionssynchronisierung in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     */
    protected static $stateDefinitions = [
        'auto_lock'   => ['type' => 'automode', 'dataType' => VARIABLETYPE_STRING, 'values' => ['AUTO', 'MANUAL'], 'ident' => 'auto_lock', 'enableAction' => true],
        'valve_state' => ['type' => 'valve', 'dataType' => VARIABLETYPE_STRING, 'values' => ['OPEN', 'CLOSED'], 'ident' => 'valve_state', 'enableAction' => true],
    ];

    /**
     * String-Properties ohne zuverlässige Zustandsrückmeldung von Zigbee2MQTT.
     *
     * Nach einem erfolgreichen Set-Befehl wird die zugehörige Symcon-Variable
     * unmittelbar auf den gesendeten Wert gesetzt. Dies betrifft derzeit
     * insbesondere Effektmodi von Leuchtmitteln.
     *
     * @var string[]
     * @see \Zigbee2MQTT\DeviceActionHelper Behandlung optimistischer Stringwerte in `libs/ModulHelper/DeviceActionHelper.php`.
     */
    protected static $stringVariablesNoResponse = [
        'effect',
    ];

    /**
     * Modulweit bekannte Presets mit festen Wertzuordnungen.
     *
     * `values` ordnet numerische Werte ihren Bezeichnungen zu. Mit `redirect`
     * kann eine Presetvariable auf die fachlich zugehörige Zielvariable
     * umgeleitet werden.
     *
     * @var array<string,array{values:array<int,string>,redirect?:bool}>
     * @see \Zigbee2MQTT\DeviceActionHelper Presetaktionen in `libs/ModulHelper/DeviceActionHelper.php`.
     */
    protected static $presetDefinitions = [
        'level_config__current_level_startup' => [
            'values' => [
                0   => 'Minimum',    // Minimaler Wert
                255 => 'Previous'    // Vorheriger Wert
            ],
            'redirect' => true  // Zeigt an, dass diese Variable umgeleitet werden soll
        ]
    ];

    // Kernfunktionen

    /**
     * Initialisiert die gemeinsamen Eigenschaften und Laufzeitdaten einer Instanz.
     *
     * Symcon ruft diese Methode beim Erzeugen der Instanz und nach dem Laden des
     * Moduls auf. Registriert werden MQTT-Topics, Visualisierungsoptionen,
     * Geräte- und Variablenattribute, Wartungsdaten, Nachrichtenabonnements sowie
     * die benötigten Puffer. Die konkrete Geräte- oder Gruppenklasse ergänzt
     * anschließend ihre eigenen Definitionen.
     *
     * @see \Zigbee2MQTT\Maintenance\VariableMaintenanceHelper Variablenwartung in `libs/Maintenance/VariableMaintenanceHelper.php`.
     * @see \Zigbee2MQTT\BufferHelper Pufferzugriffe in `libs/BufferHelper.php`.
     * @see \Zigbee2MQTT\SendData Transaktionspuffer in `libs/MQTTHelper.php`.
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(self::MQTT_BASE_TOPIC, '');
        $this->RegisterPropertyString(self::MQTT_TOPIC, '');
        $this->RegisterPropertyBoolean(self::PROPERTY_DISABLE_METERED_SWITCH_TILE, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_DISABLE_HEATING_TILE, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_DISABLE_SECURITY_TILE, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_DISABLE_WINDOW_HANDLE_TILE, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_DISABLE_ACTION_TILE, false);
        $this->RegisterPropertyBoolean(self::PROPERTY_USE_SENSOR_TILE, false);
        $this->RegisterPropertyFloat(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MIN, -40.0);
        $this->RegisterPropertyFloat(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MAX, 80.0);
        $this->RegisterPropertyInteger(self::PROPERTY_COLOR_TEMPERATURE_PRESENTATION_MIN, 0);
        $this->RegisterPropertyInteger(self::PROPERTY_COLOR_TEMPERATURE_PRESENTATION_MAX, 0);
        $this->RegisterPropertyFloat(self::PROPERTY_HEATING_TILE_PRESET_1, 18.0);
        $this->RegisterPropertyFloat(self::PROPERTY_HEATING_TILE_PRESET_2, 20.0);
        $this->RegisterPropertyFloat(self::PROPERTY_HEATING_TILE_PRESET_3, 22.0);
        $this->RegisterAttributeArray(self::ATTRIBUTE_EXPOSES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_FILTERED, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DEVICE_OPTIONS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DEVICE_OPTION_DEFINITIONS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DEVICE_ENDPOINTS, []);
        $this->RegisterAttributeBoolean(self::ATTRIBUTE_DEVICE_SUPPORTS_OTA, false);
        $this->RegisterAttributeArray(self::ATTRIBUTE_VARIABLE_CATALOG, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_PRESENTATION_MIGRATION_LOG, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DISABLED_VARIABLES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DELETED_VARIABLES, []);
        $this->RegisterAttributeFloat(self::ATTRIBUTE_MODUL_VERSION, 5.0);
        $this->TraceHelperCall(
            'VariableMaintenanceHelper',
            'InitializeLocalVariableMaintenance',
            fn (): mixed => $this->InitializeLocalVariableMaintenance()
        );

        // Init Buffers
        $this->BUFFER_MQTT_SUSPENDED = true;
        $this->BUFFER_PROCESSING_MIGRATION = false;
        $this->TraceHelperCall('MQTTHelper', 'ClearTransactionData', fn (): mixed => $this->ClearTransactionData());
        $this->lastPayload = [];
        $this->latestPayload = [];
        $this->missingTranslations = [];

        $this->RegisterMessage($this->InstanceID, IM_CHANGESTATUS);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
    }

    /**
     * Übernimmt die gemeinsame Instanzkonfiguration.
     *
     * Die Methode leert ausstehende Transaktionen, validiert Basis- und
     * Instanztopic und baut daraus den Empfangsfilter für Zustand,
     * Verfügbarkeit und Symcon-Extension-Antworten auf. Ohne vollständige
     * Topic-Konfiguration bleibt die Instanz inaktiv und empfängt keine Daten.
     * Bei gültiger Konfiguration werden Variablenkatalog, vorhandene
     * Expose-Variablen und der benötigte Visualisierungstyp aktualisiert.
     *
     * @see \Zigbee2MQTT\VariableCatalogHelper Katalogpflege in `libs/Configuration/VariableCatalogHelper.php`.
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Expose-Registrierung in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     * @see self::UpdateCustomTileVisualizationType()
     * @see \Zigbee2MQTT\SendData Transaktionsverwaltung in `libs/MQTTHelper.php`.
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $MQTTTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        $this->TraceHelperCall('MQTTHelper', 'ClearTransactionData', fn (): mixed => $this->ClearTransactionData());
        if (empty($BaseTopic) || empty($MQTTTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
            $this->SetVisualizationType(0);
            return;
        }

        //Setze Filter für ReceiveData
        $Filter1 = preg_quote('"Topic":"' . $BaseTopic . '/' . $MQTTTopic . '/' . self::AVAILABILITY_TOPIC . '"');
        $Filter2 = preg_quote('"Topic":"' . $BaseTopic . '/' . $MQTTTopic . '"');
        $Filter3 = preg_quote('"Topic":"' . $BaseTopic . self::SYMCON_EXTENSION_RESPONSE . static::$ExtensionTopic . $MQTTTopic . '"');
        $this->SendDebug('Filter', '.*(' . $Filter1 . '|' . $Filter2 . '|' . $Filter3 . ').*', 0);
        $this->SetReceiveDataFilter('.*(' . $Filter1 . '|' . $Filter2 . '|' . $Filter3 . ').*');
        $this->SetStatus(IS_ACTIVE);
        $this->TraceHelperCall(
            'VariableCatalogHelper',
            'RefreshExposeVariableCatalog',
            fn (): mixed => $this->RefreshExposeVariableCatalog()
        );
        $this->TraceHelperCall(
            'VariableCatalogHelper',
            'RefreshExistingExposeVariableRegistrations',
            fn (): mixed => $this->RefreshExistingExposeVariableRegistrations()
        );
        $this->TraceHelperCall(
            'TileHelpers',
            'UpdateCustomTileVisualizationType',
            fn (): mixed => $this->UpdateCustomTileVisualizationType()
        );
    }

    /**
     * Verarbeitet Status- und Verbindungsereignisse der eigenen Instanz.
     *
     * Nach einer wiederhergestellten Verbindung beziehungsweise Aktivierung
     * wird die MQTT-Verarbeitung freigegeben. Sobald Parent und Kernel bereit
     * sind, prüft der Expose-Helper die gespeicherten Geräteinformationen und
     * registriert bei Bedarf die daraus abgeleiteten Variablen.
     *
     * @param int   $Time     Unix-Zeitstempel des Ereignisses.
     * @param int   $SenderID ID der sendenden Instanz.
     * @param int   $Message  Symcon-Nachrichtenkennung.
     * @param array $Data     Nachrichtenspezifische Zusatzdaten.
     *
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Expose-Prüfung und Variablenzuordnung in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     */
    public function MessageSink(int $Time, int $SenderID, int $Message, array $Data): void
    {
        parent::MessageSink($Time, $SenderID, $Message, $Data);
        if ($SenderID != $this->InstanceID) {
            return;
        }
        switch ($Message) {
            case FM_CONNECT:
                if ($this->GetStatus() == IS_ACTIVE) {
                    $this->BUFFER_MQTT_SUSPENDED = false;
                }
                if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                    $this->TraceHelperCall(
                        'ExposeVariableRegistrationHelper',
                        'checkExposeAttribute',
                        fn (): mixed => $this->checkExposeAttribute(),
                        'Message=FM_CONNECT'
                    );
                }
                break;
            case IM_CHANGESTATUS:
                if ($Data[0] == IS_ACTIVE) {
                    $this->BUFFER_MQTT_SUSPENDED = false;
                    // Nur ein UpdateDeviceInfo wenn Parent aktiv und System bereit
                    if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                        if ($this->TraceHelperCall(
                            'ExposeVariableRegistrationHelper',
                            'checkExposeAttribute',
                            fn (): mixed => $this->checkExposeAttribute(),
                            'Message=IM_CHANGESTATUS'
                        )) {
                            $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
                            $this->TraceHelperCall(
                                'ExposeVariableRegistrationHelper',
                                'mapExposesToVariables',
                                fn (): mixed => $this->mapExposesToVariables($exposes),
                                'Message=IM_CHANGESTATUS'
                            );
                        }
                    }
                }
                return;
        }
    }

    /**
     * Leitet eine Symcon-Aktionsanforderung an den Geräteaktions-Helper weiter.
     *
     * Unterstützt werden unter anderem die Aktualisierung der Geräteinformation,
     * Presets, Status-, Farb- und Standardvariablen sowie Stringwerte ohne
     * Zustandsrückmeldung. Die Methode protokolliert Aufruf und Ergebnis; Auswahl,
     * Validierung, Konvertierung und Versand der Aktion erfolgen im Helper.
     *
     * @param string $Ident Identifikator der Aktion oder Variable, beispielsweise `state` oder `UpdateInfo`.
     * @param mixed  $Value Zu verarbeitender Zielwert.
     *
     * @see \Zigbee2MQTT\DeviceActionHelper Vollständige Aktionsverarbeitung in `libs/ModulHelper/DeviceActionHelper.php`.
     * @see \Zigbee2MQTT\DeviceCommandHelper Versand der Gerätebefehle in `libs/ModulHelper/DeviceCommandHelper.php`.
     * @see self::UpdateDeviceInfo()
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->SendDebug(__FUNCTION__, 'Aufgerufen für Ident: ' . $Ident . ' mit Wert: ' . json_encode($Value), 0);

        $result = $this->TraceHelperCall(
            'DeviceActionHelper',
            'handleRequestAction',
            fn (): mixed => $this->handleRequestAction($Ident, $Value),
            'Ident=' . $Ident
        );

        if ($result === false) {
            //hier eine exception werfen?
            $this->SendDebug(__FUNCTION__, 'Fehler beim Verarbeiten der Aktion: ' . $Ident . ' (Rückgabewert false)', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'Aktion erfolgreich verarbeitet: ' . $Ident, 0);
        }

    }

    /**
     * Nimmt eine MQTT-Nachricht vom übergeordneten Symcon-Splitter entgegen.
     *
     * Während Initialisierung oder Migration sowie im Erstellungsstatus wird die
     * Nachricht verworfen. Andernfalls übernimmt der Payload-Helper Dekodierung
     * und Topic-Prüfung, behandelt Verfügbarkeits- und Symcon-Extension-Antworten
     * und verarbeitet reguläre Gerätezustände. Leere Payloads sind ausschließlich
     * für Verfügbarkeitsnachrichten zulässig.
     *
     * @param string $JSONString Vom Parent übergebener JSON-Datenrahmen.
     *
     * @return string Für die Symcon-Schnittstelle wird immer ein leerer String zurückgegeben.
     *
     * @see \Zigbee2MQTT\PayloadProcessingHelper Ablauf der Nachrichtenverarbeitung in `libs/ModulHelper/PayloadProcessingHelper.php`.
     * @see \Zigbee2MQTT\PayloadStructureHelper Aufbereitung verschachtelter Daten in `libs/ModulHelper/PayloadStructureHelper.php`.
     * @see \Zigbee2MQTT\PayloadVariableHelper Aktualisierung der Variablen in `libs/ModulHelper/PayloadVariableHelper.php`.
     * @see \Zigbee2MQTT\VariableValueProcessingHelper Wertkonvertierung in `libs/ModulHelper/VariableValueProcessingHelper.php`.
     */
    public function ReceiveData(string $JSONString): string
    {
        // Während Migration keine MQTT Nachrichten verarbeiten
        if ($this->BUFFER_MQTT_SUSPENDED) {
            return '';
        }
        // Instanz im CREATE-Status überspringen
        if ($this->GetStatus() == IS_CREATING) {
            return '';
        }
        // JSON-Nachricht dekodieren
        [$topics, $payload] = $this->TraceHelperCall(
            'PayloadProcessingHelper',
            'validateAndParseMessage',
            fn (): mixed => $this->validateAndParseMessage($JSONString)
        );
        if (!$topics) {
            return '';
        }
        // Behandelt Verfügbarkeit-Status
        if ($this->TraceHelperCall(
            'PayloadProcessingHelper',
            'handleAvailability',
            fn (): mixed => $this->handleAvailability($topics, $payload),
            'Topic=' . (string) ($topics[0] ?? '')
        )) {
            return '';
        }
        // Leere Payloads werden nur fuer handleAvailability benoetigt.
        if (\is_null($payload)) {
            return '';
        }

        // Behandelt Symcon Extension Antworten, auch wenn Instanz noch in IS_CREATING ist.
        if ($this->TraceHelperCall(
            'PayloadProcessingHelper',
            'handleSymconExtensionResponses',
            fn (): mixed => $this->handleSymconExtensionResponses($topics, $payload),
            'Topic=' . (string) ($topics[0] ?? '')
        )) {
            return '';
        }
        // Verarbeitet Payload
        $this->TraceHelperCall(
            'PayloadProcessingHelper',
            'processPayload',
            fn (): mixed => $this->processPayload($payload),
            'Properties=' . \count($payload)
        );
        return '';
    }

    /**
     * Migriert gespeicherte Instanzdaten auf den Mindeststand des Moduls.
     *
     * Bereits migrierte Instanzen werden unverändert zurückgegeben. Für ältere
     * Bestände wird die MQTT-Verarbeitung vorübergehend gesperrt, eine historische
     * Expose-Datei in das Instanzattribut übernommen und anschließend entfernt.
     * Variablen-Identifikatoren mit dem Präfix `Z2M_` werden – ausgenommen die
     * definierten Sonderfälle – nach `lower_snake_case` konvertiert. Abschließend
     * werden Darstellung und Aktionszustand einer vorhandenen
     * Helligkeitsvariable synchronisiert.
     *
     * @param string $JSONData Serialisierte Symcon-Konfiguration mit Properties und Attributen.
     *
     * @return string Migrierte oder unveränderte Symcon-Konfiguration.
     *
     * @see self::convertToSnakeCase()
     * @see \Zigbee2MQTT\DeviceActionHelper Ermittlung des Helligkeits-Exposes in `libs/ModulHelper/DeviceActionHelper.php`.
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Synchronisierung der Variablenaktion in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     * @see \Zigbee2MQTT\VariablePresentationHelper Helligkeitsdarstellung in `libs/Visualization/VariablePresentationHelper.php`.
     */
    public function Migrate(string $JSONData): string
    {
        // Prüfe Version diese Modul-Instanz
        $j = json_decode($JSONData);
        if (isset($j->attributes->{self::ATTRIBUTE_MODUL_VERSION})) {
            if ($j->attributes->{self::ATTRIBUTE_MODUL_VERSION} >= self::MINIMAL_MODUL_VERSION) {
                return $JSONData;
            }
        }
        $j->attributes->{self::ATTRIBUTE_MODUL_VERSION} = self::MINIMAL_MODUL_VERSION;

        // Flag für laufende Migration setzen
        $this->BUFFER_MQTT_SUSPENDED = true;
        $this->BUFFER_PROCESSING_MIGRATION = true;

        // Move Exposes from file to attribute
        $jsonFile = IPS_GetKernelDir() . self::EXPOSES_DIRECTORY . DIRECTORY_SEPARATOR . $this->InstanceID . '.json';
        if (file_exists($jsonFile)) {
            $exposeData = @file_get_contents($jsonFile);
            $data = json_decode($exposeData, true);
            if (isset($data['exposes'])) { //device
                $exposes = $data['exposes'];
            } else { //group
                $exposes = $data;
            }
            $this->LogMessage(__FUNCTION__ . ' : Convert ExposeFile to attribute', KL_NOTIFY);
            $j->attributes->{self::ATTRIBUTE_EXPOSES} = json_encode($exposes);
            @unlink($jsonFile);
        }

        // 1) Suche alle Kinder-Objekte dieser Instanz
        // 2) Prüfe, ob ihr Ident z. B. mit "Z2M_" beginnt
        // 3) Bilde den neuen Ident (snake_case) und setze ihn
        $childrenIDs = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($childrenIDs as $childID) {
            // Nur weitermachen, wenn es sich um eine Variable handelt
            $obj = IPS_GetObject($childID);
            if ($obj['ObjectType'] !== OBJECTTYPE_VARIABLE) {
                continue;
            }

            if ($obj['ObjectIdent'] == '') {
                // Hat keinen Ident, also ignorieren
                continue;
            }

            // Nur solche Idents, die mit 'Z2M_' beginnen:
            if (substr($obj['ObjectIdent'], 0, 4) !== 'Z2M_') {
                // Überspringen
                continue;
            }
            if (\in_array($obj['ObjectIdent'], self::SKIP_IDENTS)) {
                // Überspringen
                continue;
            }
            // Neuen Ident bilden
            $newIdent = self::convertToSnakeCase($obj['ObjectIdent']);
            // Versuchen zu setzen
            $result = @IPS_SetIdent($childID, $newIdent);
            if ($result === false) {
                $this->LogMessage(__FUNCTION__ . ' : Fehler: Ident "' . $newIdent . '" konnte nicht für Variable #' . $childID . ' gesetzt werden!', KL_ERROR);
            } else {
                $this->LogMessage(__FUNCTION__ . ' : Variable #' . $childID . ': "' . $obj['ObjectIdent'] . '" wurde geändert zu "' . $newIdent . '"', KL_NOTIFY);
            }
        }

        // Brightness-Variablenmigration
        $varID = $this->GetObjectIDByIdent('brightness');
        if ($varID !== false) {
            $brightnessFeature = $this->TraceHelperCall(
                'DeviceActionHelper',
                'findExposeFeatureByProperty',
                fn (): mixed => $this->findExposeFeatureByProperty('brightness'),
                'Property=brightness'
            ) ?? [
                'name'     => 'brightness',
                'property' => 'brightness',
                'type'     => 'numeric'
            ];
            $brightnessPresentation = $this->TraceHelperCall(
                'VariablePresentationHelper',
                'BuildBrightnessFeaturePresentation',
                fn (): mixed => $this->BuildBrightnessFeaturePresentation($brightnessFeature),
                'Property=brightness'
            ) ?? '';

            $this->RegisterVariableInteger(
                'brightness',
                $this->Translate('Brightness'),
                $brightnessPresentation,
                10
            );

            // Standardaktion fuer die migrierte Helligkeitsvariable synchronisieren.
            $this->TraceHelperCall(
                'ExposeVariableRegistrationHelper',
                'synchronizeVariableAction',
                fn (): mixed => $this->synchronizeVariableAction('brightness', $brightnessFeature),
                'Ident=brightness'
            );
        }
        // Flag für beendete Migration wieder setzen
        $this->BUFFER_MQTT_SUSPENDED = false;
        $this->BUFFER_PROCESSING_MIGRATION = false;
        return json_encode($j);
    }

    /**
     * Erstellt einen herunterladbaren Diagnoseexport der aktuellen Instanz.
     *
     * Der Export enthält Objekt- und Instanzdaten, Konfiguration, Exposes,
     * zusammengeführtes und letztes Payload, OTA-Fähigkeit, untergeordnete
     * Variablen, verwendete Profile und erkannte fehlende Übersetzungen. Bei
     * Geräteinstanzen wird zusätzlich der Link zur Zigbee2MQTT-Gerätedokumentation
     * erzeugt.
     *
     * @return string Base64-kodierte JSON-Datei als Data-URL.
     *
     * @see \Zigbee2MQTT\VariableCatalogHelper OTA-Erkennung in `libs/Configuration/VariableCatalogHelper.php`.
     * @see \Zigbee2MQTT\BufferHelper Payload- und Übersetzungspuffer in `libs/BufferHelper.php`.
     */
    public function UIExportDebugData(): string
    {
        $DebugData = [];
        $DebugData['Instance'] = IPS_GetObject($this->InstanceID) + IPS_GetInstance($this->InstanceID);
        if (IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID'] == self::GUID_MODULE_DEVICE) {
            $DebugData['Model'] = $this->ReadAttributeString('Model');
            $ModelUrl = str_replace([' ', '/'], '_', $DebugData['Model']);
            $DebugData['ModelUrl'] = 'https://www.zigbee2mqtt.io/devices/' . rawurlencode($ModelUrl) . '.html';
        }
        $DebugData['Config'] = json_decode(IPS_GetConfiguration($this->InstanceID), true);
        $DebugData['Exposes'] = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        $DebugData['LastPayload'] = $this->lastPayload;
        $DebugData['LatestPayload'] = $this->latestPayload;
        $DebugData['SupportsOTA'] = $this->TraceHelperCall(
            'VariableCatalogHelper',
            'IsDeviceOTACapable',
            fn (): mixed => $this->IsDeviceOTACapable()
        );
        $DebugData['Childs'] = [];
        $DebugData['Profile'] = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $obj = IPS_GetObject($childID);
            if ($obj['ObjectType'] !== OBJECTTYPE_VARIABLE) {
                continue;
            }
            $var = IPS_GetVariable($childID);
            $DebugData['Childs'][$childID] = IPS_GetObject($childID) + $var;
            if ($var['VariableCustomProfile'] != '' && IPS_VariableProfileExists($var['VariableCustomProfile'])) {
                $DebugData['Profile'][$var['VariableCustomProfile']] = IPS_GetVariableProfile($var['VariableCustomProfile']);
            }
            if ($var['VariableProfile'] != '' && IPS_VariableProfileExists($var['VariableProfile'])) {
                $DebugData['Profile'][$var['VariableProfile']] = IPS_GetVariableProfile($var['VariableProfile']);
            }
        }
        $DebugData['missingTranslations'] = $this->missingTranslations;

        return 'data:application/json;base64,' . base64_encode(json_encode($DebugData, JSON_PRETTY_PRINT));
    }

    /**
     * Führt einen Helper-Aufruf mit einheitlichen Start-, Ende- und Fehler-Debugs aus.
     *
     * Der Kontext darf ausschließlich nicht sensible Kennungen enthalten. Payloads,
     * Zugangsdaten und Installcodes werden bewusst nicht protokolliert.
     */
    private function TraceHelperCall(string $helper, string $operation, \Closure $callback, string $context = ''): mixed
    {
        $call = $helper . '::' . $operation;
        $context = trim((string) preg_replace('/[\r\n]+/', ' ', $context));
        if (strlen($context) > 160) {
            $context = substr($context, 0, 157) . '...';
        }
        $suffix = $context === '' ? '' : ' | ' . $context;
        $this->SendDebug('HelperTrace', $call . ' [START]' . $suffix, 0);

        try {
            $result = $callback();
        } catch (\Throwable $exception) {
            $this->SendDebug(
                'HelperTrace',
                $call . ' [ERROR] | Exception=' . $exception::class . ' | Code=' . $exception->getCode() . $suffix,
                0
            );
            throw $exception;
        }

        $resultDescription = match (true) {
            \is_bool($result) => $result ? 'true' : 'false',
            $result === null  => 'null',
            default           => get_debug_type($result)
        };
        $this->SendDebug('HelperTrace', $call . ' [END] | Result=' . $resultDescription . $suffix, 0);
        return $result;
    }

    /**
     * Aktiviert die HTML-SDK-Visualisierung, wenn mindestens eine passende Spezialkachel verwendet werden soll.
     *
     * Ohne verfügbare Tile-Schnittstelle oder ohne aktivierte passende Kachel wird
     * auf die native Symcon-Darstellung zurückgeschaltet.
     *
     * @see \Zigbee2MQTT\MeteredSwitchTileHelper Kachel in `libs/Visualization/TileHelpers/MeteredSwitchTileHelper.php`.
     * @see \Zigbee2MQTT\HeatingTileHelper Kachel in `libs/Visualization/TileHelpers/HeatingTileHelper.php`.
     * @see \Zigbee2MQTT\SensorTileHelper Kachel in `libs/Visualization/TileHelpers/SensorTileHelper.php`.
     * @see \Zigbee2MQTT\SecurityTileHelper Kachel in `libs/Visualization/TileHelpers/SecurityTileHelper.php`.
     * @see \Zigbee2MQTT\WindowHandleTileHelper Kachel in `libs/Visualization/TileHelpers/WindowHandleTileHelper.php`.
     * @see \Zigbee2MQTT\ActionTileHelper Kachel in `libs/Visualization/TileHelpers/ActionTileHelper.php`.
     */
    protected function UpdateCustomTileVisualizationType(): void
    {
        if (!method_exists($this, 'GetVisualizationTile')) {
            $this->SetVisualizationType(0);
            return;
        }

        $this->SetVisualizationType(($this->ShouldUseHeatingTile() || $this->ShouldUseMeteredSwitchTile() || $this->ShouldUseWindowHandleTile() || $this->ShouldUseSecurityTile() || $this->ShouldUseActionTile() || $this->ShouldUseSensorTile()) ? 1 : 0);
    }

    /**
     * Überträgt einen Wert an die aktive HTML-SDK-Kachel.
     *
     * Vorübergehende Fehler während eines Symcon-Modul-Reloads werden abgefangen.
     * Eine folgende reguläre Wertänderung aktualisiert die Kachel erneut.
     *
     * @param string $value Serialisierter Wert für die Visualisierung.
     *
     * @see self::UpdateCustomTileVisualizationType()
     */
    protected function UpdateCustomTileVisualizationValue(string $value): void
    {
        \set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            $this->UpdateVisualizationValue($value);
        } catch (\Throwable) {
            // Beim Modul-Reload kann das InstanceInterface kurz fehlen; der naechste Wert aktualisiert die Kachel erneut.
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * Lädt Geräte- oder Gruppeninformationen über die Zigbee2MQTT-Symcon-Extension.
     *
     * Ohne aktiven Parent oder konfiguriertes MQTT-Topic wird keine Anfrage
     * gesendet. Die Antwort wird anhand der vom MQTT-Helper verwalteten
     * Transaktions-ID erwartet; Zeitüberschreitungen werden protokolliert.
     *
     * @return array|false Dekodierte Extension-Antwort oder `false`, wenn die Anfrage nicht möglich war beziehungsweise fehlschlug.
     *
     * @see \Zigbee2MQTT\SendData Transaktionsbasierter Versand in `libs/MQTTHelper.php`.
     * @see static::$ExtensionTopic
     */
    protected function LoadDeviceInfo()
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        if (empty($mqttTopic)) {
            $this->LogMessage($this->Translate('MQTTTopic not configured.'), KL_WARNING);
            return false;
        }
        $topic = self::SYMCON_EXTENSION_REQUEST . static::$ExtensionTopic . $mqttTopic;
        $Result = $this->TraceHelperCall(
            'MQTTHelper',
            'SendDataQuiet',
            fn (): mixed => $this->SendDataQuiet(
                $topic,
                [],
                self::TIMEOUT_SYMCON_EXTENSION_INFO
            ),
            'Request=DeviceInfo'
        );
        if ($Result === false) {
            $this->LogMessage(\sprintf($this->Translate('Zigbee2MQTT did not response on Topic %s'), $topic), KL_WARNING);
        }
        return $Result;
    }

    /**
     * Aktualisiert die gespeicherten Informationen der konkreten Geräte- oder Gruppeninstanz.
     *
     * Die Implementierung der abgeleiteten Klasse lädt die Daten über
     * `LoadDeviceInfo()`, übernimmt Exposes und instanzspezifische Metadaten und
     * stößt die erforderliche Variablenaktualisierung an.
     *
     * @return bool `true` bei erfolgreicher Aktualisierung, andernfalls `false`.
     *
     * @see self::LoadDeviceInfo()
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Verarbeitung der Exposes in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     */
    abstract protected function UpdateDeviceInfo(): bool;

    /**
     * Liefert eine übersetzte, lesbare Bezeichnung für eine native Symcon-Darstellung.
     *
     * Die Bezeichnung wird in den Migrationsprotokollen des Variablenkatalogs und
     * der Expose-Registrierung verwendet. Unbekannte Darstellungen erhalten eine
     * neutrale Sammelbezeichnung.
     *
     * @param array $presentation Konfiguration einer nativen Variablendarstellung.
     *
     * @return string Übersetzte Darstellungsbezeichnung.
     *
     * @see \Zigbee2MQTT\VariableCatalogHelper Migrationsprotokoll in `libs/Configuration/VariableCatalogHelper.php`.
     * @see \Zigbee2MQTT\ExposeVariableRegistrationHelper Registrierungsprotokoll in `libs/ModulHelper/ExposeVariableRegistrationHelper.php`.
     */
    protected function DescribePresentationForMigrationLog(array $presentation): string
    {
        $presentationKey = \defined('PRESENTATION') ? \constant('PRESENTATION') : 'PRESENTATION';
        $presentationID = $presentation[$presentationKey] ?? $presentation['PRESENTATION'] ?? null;
        $presentations = [
            'VARIABLE_PRESENTATION_SLIDER'             => 'Slider',
            'VARIABLE_PRESENTATION_ENUMERATION'        => 'Enumeration',
            'VARIABLE_PRESENTATION_VALUE'              => 'Value display',
            'VARIABLE_PRESENTATION_VALUE_PRESENTATION' => 'Value display',
            'VARIABLE_PRESENTATION_SWITCH'             => 'Switch',
            'VARIABLE_PRESENTATION_COLOR'              => 'Color',
            'VARIABLE_PRESENTATION_SHUTTER'            => 'Shutter',
            'VARIABLE_PRESENTATION_DURATION'           => 'Duration',
            'VARIABLE_PRESENTATION_DATE_TIME'          => 'Date/time',
        ];

        foreach ($presentations as $constant => $caption) {
            if (\defined($constant) && $presentationID === \constant($constant)) {
                return $this->Translate($caption);
            }
        }

        return $this->Translate('Native presentation');
    }

    /**
     * Konvertiert einen historischen Z2M-Identifikator nach `lower_snake_case`.
     *
     * Das Präfix `Z2M_` wird entfernt. Statusvarianten und bekannte Abkürzungen
     * werden vor der allgemeinen CamelCase-Konvertierung gesondert behandelt.
     * Bereits korrekt formatierte Identifikatoren bleiben inhaltlich erhalten.
     *
     * Beispiele: `color_temp` wird zu `color_temp`, `brightnessABC` zu
     * `brightness_abc`.
     *
     * @param string $oldIdent Zu konvertierender historischer Identifikator.
     *
     * @return string Normalisierter Identifikator.
     *
     * @see self::Migrate()
     */
    private static function convertToSnakeCase(string $oldIdent): string
    {
        // 1) Z2M_ Prefix entfernen
        $withoutPrefix = preg_replace('/^Z2M_/', '', $oldIdent);

        // 2) State Pattern Check
        foreach ([self::STATE_PATTERN['MQTT'], self::STATE_PATTERN['SYMCON']] as $pattern) {
            if (preg_match($pattern, $withoutPrefix)) {
                $result = preg_replace('/^(state)([LlRr][0-9]+)$/i', '$1_$2', $withoutPrefix);
                return strtolower($result);
            }
        }

        // 3) Bekannte Abkürzungen prüfen
        foreach (self::KNOWN_ABBREVIATIONS as $abbr) {
            $pattern = '/\b' . preg_quote($abbr, '/') . '\b/';
            if (preg_match($pattern, $withoutPrefix)) {
                $withoutPrefix = preg_replace($pattern, strtolower($abbr), $withoutPrefix);
            }
        }

        // 4) Großbuchstaben verarbeiten
        $result = $withoutPrefix;
        // a) Einzelner Großbuchstabe am Wortanfang bleibt erhalten
        // b) Großbuchstabe nach Kleinbuchstaben bekommt Unterstrich
        $result = preg_replace('/([a-z])([A-Z])/', '$1_$2', $result);
        // c) Großbuchstabenblöcke im Wort
        $result = preg_replace('/([A-Z])([A-Z][a-z])/', '$1_$2', $result);

        // 5) Formatierung finalisieren
        $result = preg_replace('/_+/', '_', $result);
        $result = strtolower($result);

        return $result;
    }

}
