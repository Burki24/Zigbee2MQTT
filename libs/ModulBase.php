<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

require_once __DIR__ . '/AttributeArrayHelper.php';
require_once __DIR__ . '/BufferHelper.php';
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

/**
 * ModulBase
 *
 * Basisklasse für Geräte (Devices module.php) und Gruppen (Groups module.php)
 *
 * Pseudo Variablen, welche über BufferHelper und die Magic-Functions __get und __set
 * direkt typsichere Werte, Arrays und Objekte in einem Instanz-Buffer schreiben und lesen.
 * @property bool $BUFFER_MQTT_SUSPENDED Zugriff auf den Buffer für laufende Migration
 * @property bool $BUFFER_PROCESSING_MIGRATION Zugriff auf den Buffer für MQTT Nachrichten nicht verarbeiten
 * @property array $lastPayload Zugriff auf den Buffer, welcher die zusammengeführten Payload-Werte enthält
 * @property array $latestPayload Zugriff auf den Buffer welcher das zuletzt empfangene Geräte-Payload enthält
 * @property array $missingTranslations Zugriff auf den Buffer, welcher ein Array von fehlenden Übersetzungen enthält
 */
abstract class ModulBase extends \IPSModuleStrict
{
    use AttributeArrayHelper;
    use BufferHelper;
    use InstanceConnectionHelper;
    use Semaphore;
    use ColorHelper;
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
     * @var array STATE_PATTERN
     * Definiert Nomenklatur für State-Variablen
     *      KEY:
     *      - BASE     'state' (Basisbezeichner)
     *      - SUFFIX:   Zusatzbezeichner
     *          - NUMERIC:   statel1, state_l1, StateL1, state_L1
     *          - DIRECTION: state_left, state_right, State_Left
     *          - COMBINED:  state_left_l1, State_Right_L1
     *      - MQTT:    Validiert MQTT-Payload (state, state_l1)
     *      - SYMCON:  Validiert Symcon-Variablen (state, State, statel1, state_l1, State_Left, state_right_l1)
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
     * @var string[] FLOAT_UNITS
     * Entscheidet über Float- oder Integer-Variablen.
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
     * Liste bekannter Abkürzungen, die bei der Konvertierung von Identifikatoren
     * in snake_case beibehalten werden sollen.
     *
     * Diese Konstante wird im convertToSnakeCase() verwendet, um sicherzustellen,
     * dass gängige Abkürzungen (z.B. CO2, LED) korrekt formatiert werden.
     *
     * @var string[]
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
     * @var string[]
     * Liste von alten Z2M Idents, welche bei der Konvertierung übersprungen werden müssen
     * damit sie erhalten bleiben.
     * Weil sich entweder der VariablenTyp ändert, oder der alte Name nicht konvertiert werden kann.
     * z.B. Z2M_ActionTransTime, was eigentlich action_transition_time ist.
     */
    private const SKIP_IDENTS = [
        'Z2M_ActionTransaction',
        'Z2M_ActionTransTime',
        'Z2M_XAxis',
        'Z2M_YAxis',
        'Z2M_ZAxis'
    ];

    /**
     * @var string[]
     * Liste von Composite-Keys, die beim Flattening übersprungen werden sollen.
     * Diese Composites werden nicht in einzelne Variablen aufgelöst.
     */
    private const SKIP_COMPOSITES = [
        'device',       // Geräteinformationen nicht als Einzelvariablen anlegen
        'endpoints',    // Endpoint-Informationen nicht als Einzelvariablen anlegen
        'options'       // Optionsstruktur nicht als Einzelvariablen anlegen
    ];

    /**
     * @var string $ExtensionTopic
     * Muss überschrieben werden.
     * - für den ReceiveFilter
     * - für LoadDeviceInfo
     * - überall wo das Topic der Extension genutzt wird
     *
     */
    protected static $ExtensionTopic = '';

    /**
     * Ein Array, das bekannte Features auf Symcon-Variablentypen abbildet.
     *
     * Die Liste erzwingt nur den Symcon-Variablentyp fuer bekannte Exposes. Es
     * werden hier keine Symcon-Profile definiert oder gesetzt.
     *
     * @var array<int, array{group_type:string, feature:string, variableType:int}>
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
     * Definitionen fuer bekannte Sondervariablen, die ohne vollstaendige
     * Expose-Metadaten registriert werden.
     *
     * @var array<string,array{type:int,name?:string,ident?:string}>
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
     * Definitionen fuer bekannte State-Features mit festen Werten.
     *
     * @var array<string,array{
     *     type:string,
     *     dataType:int,
     *     values:array<int,string>,
     *     ident:string,
     *     enableAction?:bool
     * }>
     */
    protected static $stateDefinitions = [
        'auto_lock'   => ['type' => 'automode', 'dataType' => VARIABLETYPE_STRING, 'values' => ['AUTO', 'MANUAL'], 'ident' => 'auto_lock', 'enableAction' => true],
        'valve_state' => ['type' => 'valve', 'dataType' => VARIABLETYPE_STRING, 'values' => ['OPEN', 'CLOSED'], 'ident' => 'valve_state', 'enableAction' => true],
    ];

    /**
     *  @var array $stringVariablesNoResponse
     *
     * Erkennt String-Variablen ohne Rückmeldung seitens Z2M
     * Aktualisiert die in Symcon angelegte Variable direkt nach dem Senden des Set-Befehls
     * Zur einfacheren Wartung als table angelegt. Somit muss der Code bei späteren Ergänzungen nicht angepasst werden.
     *
     * Typische Anwendungsfälle:
     * - Effekt-Modi bei Leuchtmitteln (z.B. "EFFECT"), bei denen der zuletzt verwendete Effekt
     *   angezeigt werden soll.
     *
     * Beispiel:
     * - 'effect': Aktualisiert den zuletzt gesetzten Effekt.
     */
    protected static $stringVariablesNoResponse = [
        'effect',
    ];

    /**
     * @var array<string,array{values: array<int,string>}> $presetDefinitions
     *
     * Definiert vordefinierte Presets mit festen Wertzuordnungen
     *
     * Struktur:
     * [
     *   'PresetName' => [
     *     'values' => [
     *       Wert => 'Bezeichnung'
     *     ]
     *   ]
     * ]
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
     * Create
     *
     * Wird einmalig beim Erstellen einer Instanz aufgerufen
     *
     * Führt folgende Aktionen aus:
     * - Verbindet mit der erstbesten MQTT-Server-Instanz
     * - Registriert Properties für MQTT-Basis-Topic und MQTT-Topic
     * - Initialisiert TransactionData Array
     * - Registriert Properties, Attribute und Buffer
     *
     * @return void
     *
     * @see \IPSModule::RegisterPropertyString()
     * @see \IPSModule::RegisterAttributeFloat()
     * @see \IPSModule::RegisterAttributeArray()
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
        $this->InitializeLocalVariableMaintenance();

        // Init Buffers
        $this->BUFFER_MQTT_SUSPENDED = true;
        $this->BUFFER_PROCESSING_MIGRATION = false;
        $this->ClearTransactionData();
        $this->lastPayload = [];
        $this->latestPayload = [];
        $this->missingTranslations = [];

        $this->RegisterMessage($this->InstanceID, IM_CHANGESTATUS);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
    }

    /**
     * ApplyChanges
     *
     * Wird aufgerufen bei übernehmen der Modulkonfiguration
     *
     * Führt folgende Aktionen aus:
     * - Verbindet mit MQTT-Parent
     * - Liest MQTT Basis- und Geräte-Topic
     * - Setzt Filter für eingehende MQTT-Nachrichten
     * - Aktualisiert Instanz-Status (aktiv/inaktiv)
     * - Prüft und aktualisiert Geräteinformationen (expose attribute)
     *
     * Bedingungen für Aktivierung:
     * - Basis-Topic und MQTT-Topic müssen gesetzt sein
     * - Parent muss aktiv sein
     * - System muss bereit sein (KR_READY)
     *
     * @return void
     *
     * @see \IPSModule::ApplyChanges()
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::SetReceiveDataFilter()
     * @see \IPSModule::HasActiveParent()
     * @see \IPSModule::GetStatus()
     * @see \IPSModule::SetStatus()
     * @see IPS_GetKernelRunlevel()
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $MQTTTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        $this->ClearTransactionData();
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
        $this->RefreshExposeVariableCatalog();
        $this->RefreshExistingExposeVariableRegistrations();
        $this->UpdateCustomTileVisualizationType();
    }

    /**
     * MessageSink
     *
     * @param  mixed $Time
     * @param  mixed $SenderID
     * @param  mixed $Message
     * @param  mixed $Data
     * @return void
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
                    $this->checkExposeAttribute();
                }
                break;
            case IM_CHANGESTATUS:
                if ($Data[0] == IS_ACTIVE) {
                    $this->BUFFER_MQTT_SUSPENDED = false;
                    // Nur ein UpdateDeviceInfo wenn Parent aktiv und System bereit
                    if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                        if ($this->checkExposeAttribute()) {
                            $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
                            $this->mapExposesToVariables($exposes);
                        }
                    }
                }
                return;
        }
    }

    /**
     * RequestAction
     *
     * Verarbeitet Aktionsanforderungen für Variablen
     *
     * Diese Methode wird automatisch aufgerufen, wenn eine Aktion einer Variable
     * oder IPS_RequestAction ausgeführt wird.
     *
     * Sie verarbeitet verschiedene Arten von Aktionstypen:
     *
     * - UpdateInfo: Aktualisiert Geräteinformationen
     * - presets: Verarbeitet vordefinierte Einstellungen
     * - String-Variablen ohne Rückmeldung: Direkte Aktualisierung
     * - Farbvariablen: Spezielle Behandlung von RGB/HSV/etc.
     * - Status-Variablen: ON/OFF und andere Zustände
     * - Standard-Variablen: Allgemeine Werteänderungen
     *
     * @param string $Ident Identifikator der Variable (z.B. 'state', 'UpdateInfo')
     * @param mixed $Value Neuer Wert für die Variable
     *
     * @return void
     *
     * @see \IPSModule::RequestAction()
     * @see \IPSModule::SendDebug()
     * @see \Zigbee2MQTT\ModulBase::UpdateDeviceInfo()
     * @see \Zigbee2MQTT\ModulBase::handlePresetVariable()
     * @see \Zigbee2MQTT\ModulBase::handleStringVariableNoResponse()
     * @see \Zigbee2MQTT\ModulBase::handleColorVariable()
     * @see \Zigbee2MQTT\ModulBase::handleStateVariable()
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable()
     * @see json_encode()
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->SendDebug(__FUNCTION__, 'Aufgerufen für Ident: ' . $Ident . ' mit Wert: ' . json_encode($Value), 0);

        $result = $this->handleRequestAction($Ident, $Value);

        if ($result === false) {
            //hier eine exception werfen?
            $this->SendDebug(__FUNCTION__, 'Fehler beim Verarbeiten der Aktion: ' . $Ident . ' (Rückgabewert false)', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'Aktion erfolgreich verarbeitet: ' . $Ident, 0);
        }

    }

    /**
     * ReceiveData
     *
     * Verarbeitet eingehende MQTT-Nachrichten
     *
     * Diese Methode wird automatisch aufgerufen, wenn eine MQTT-Nachricht empfangen wird.
     * Der Verarbeitungsablauf ist wie folgt:
     * 1. Prüft ob die Instanz noch bei der Migration ist
     * 2. Prüft ob Instanz im CREATE-Status ist
     * 3. Lässt den JSONString prüfen und zerlegen
     * 4. Verarbeitet spezielle Nachrichtentypen:
     *    - Verfügbarkeitsstatus (availability)
     *    - Symcon Extension Antworten
     * 5. Wenn keine spezielle Nachricht, dann Payload verarbeiten lassen
     *
     * @param string $JSONString Die empfangene MQTT-Nachricht im JSON-Format
     *
     * @return string Leerer String als Rückgabewert
     *
     * @see \IPSModule::ReceiveData()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::GetStatus()
     * @see \Zigbee2MQTT\ModulBase::validateAndParseMessage()
     * @see \Zigbee2MQTT\ModulBase::handleAvailability()
     * @see \Zigbee2MQTT\ModulBase::handleSymconExtensionResponses()
     * @see \Zigbee2MQTT\ModulBase::processPayload()
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
        [$topics, $payload] = $this->validateAndParseMessage($JSONString);
        if (!$topics) {
            return '';
        }
        // Behandelt Verfügbarkeit-Status
        if ($this->handleAvailability($topics, $payload)) {
            return '';
        }
        // Leere Payloads werden nur fuer handleAvailability benoetigt.
        if (\is_null($payload)) {
            return '';
        }

        // Behandelt Symcon Extension Antworten, auch wenn Instanz noch in IS_CREATING ist.
        if ($this->handleSymconExtensionResponses($topics, $payload)) {
            return '';
        }
        // Verarbeitet Payload
        $this->processPayload($payload);
        return '';
    }

    /**
     * Migrate
     *
     * Prüft über ein Attribute ob die Modul-Instanz ein Update benötigt.
     *
     * Führt anschließend eine Migration von Objekt-Idents durch, indem es Kinder-Objekte dieser Instanz durchsucht,
     * auf definierte Kriterien überprüft und bei Bedarf umbenennt.
     *
     * - Überprüfung, ob der Ident mit "Z2M_" beginnt
     * - Konvertierung des Ident ins snake_case
     * - Loggt sowohl Fehler als auch erfolgreiche Änderungen
     *
     * @param string $JSONData JSON-Daten mit allen Properties und Attributen
     * @return string JSON-Daten mit allen Properties und Attributen
     *
     * @see \IPSModule::Migrate()
     * @see \IPSModule::SetBuffer()
     * @see \IPSModule::LogMessage()
     * @see IPS_GetChildrenIDs()
     * @see IPS_GetObject()
     * @see IPS_SetIdent()
     * @see json_decode()
     * @see json_encode()
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
            $brightnessFeature = $this->findExposeFeatureByProperty('brightness') ?? [
                'name'     => 'brightness',
                'property' => 'brightness',
                'type'     => 'numeric'
            ];
            $brightnessPresentation = $this->BuildBrightnessFeaturePresentation($brightnessFeature) ?? '';

            $this->RegisterVariableInteger(
                'brightness',
                $this->Translate('Brightness'),
                $brightnessPresentation,
                10
            );

            // Zentrale EnableAction-Prüfung für brightness Migration
            $this->checkAndEnableAction('brightness', $brightnessFeature);
        }
        // Flag für beendete Migration wieder setzen
        $this->BUFFER_MQTT_SUSPENDED = false;
        $this->BUFFER_PROCESSING_MIGRATION = false;
        return json_encode($j);
    }

    /**
     * Z2M_WriteValueBoolean Instanz Funktion
     *
     * Damit auch ein Senden an einen Ident möglich ist, wenn die Standardaktion überschrieben wurde.
     *
     * @param  string $ident
     * @param  bool $value
     * @return bool
     */
    public function WriteValueBoolean(string $ident, bool $value): bool
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Z2M_WriteValueInteger Instanz Funktion
     *
     * Damit auch ein Senden an einen Ident möglich ist, wenn die Standardaktion überschrieben wurde.
     *
     * @param  string $ident
     * @param  int $value
     * @return bool
     */
    public function WriteValueInteger(string $ident, int $value): bool
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Z2M_WriteValueFloat Instanz Funktion
     *
     * Damit auch ein Senden an einen Ident möglich ist, wenn die Standardaktion überschrieben wurde.
     *
     * @param  string $ident
     * @param  float $value
     * @return bool
     */
    public function WriteValueFloat(string $ident, float $value): bool
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Z2M_WriteValueString Instanz Funktion
     *
     * Damit auch ein Senden an einen Ident möglich ist, wenn die Standardaktion überschrieben wurde.
     *
     * @param  string $ident
     * @param  string $value
     * @return bool
     */
    public function WriteValueString(string $ident, string $value): bool
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Z2M_ReadValue Instanz Funktion
     *
     * Leseanforderung für ein Value an das Gerät senden.
     *
     * @param  string $Property
     * @return mixed Ergebnis der Leseanforderung oder false, wenn kein gueltiges Topic aufgebaut werden konnte
     *
     * @throws \Exception Bei Fehlern während des Sendens
     *
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::SendDebug()
     * @see \Zigbee2MQTT\SendData::SendData()
     * @see json_encode()
     */
    public function ReadValue(string $Property): mixed
    {
        $Payload = [$Property => ''];

        // MQTT-Topic für den Get-Befehl generieren
        $Topic = $this->BuildConfiguredMQTTTopic(self::MQTT_TOPIC, 'get');
        if ($Topic === null) {
            return false;
        }

        // Debug-Ausgabe des zu sendenden Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);

        // Sende die Daten an das Gerät
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * SendSetCommand
     *
     * Sendet einen Set-Befehl an das Gerät über MQTT
     *
     * Diese Methode generiert das MQTT-Topic für den Set-Befehl basierend auf der Konfiguration
     * und sendet das übergebene Array über SendData an das Gerät.
     *
     * @param array $Payload Array mit Schlüssel-Wert-Paaren, das an das Gerät gesendet werden soll
     *
     * @return bool True wenn die Daten versendet werden konnten, sonst false
     *
     * @throws \Exception Bei Fehlern während des Sendens
     *
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::SendDebug()
     * @see \Zigbee2MQTT\SendData::SendData()
     * @see json_encode()
     */
    public function SendSetCommand(array $Payload): bool
    {
        // MQTT-Topic für den Set-Befehl generieren
        $Topic = $this->BuildConfiguredMQTTTopic(self::MQTT_TOPIC, 'set');
        if ($Topic === null) {
            return false;
        }

        // Debug-Ausgabe des zu sendenden Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);

        // Sende die Daten an das Gerät
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * SendGetCommand
     *
     * Sendet einen Get-Befehl an das Gerät über MQTT
     *
     * Diese Methode generiert das MQTT-Topic für den Get-Befehl basierend auf der Konfiguration
     * und sendet ein Array mit allen Features/Propertys über SendData an das Gerät.
     *
     * @return bool True wenn die Daten versendet werden konnten, sonst false
     *
     * @throws \Exception Bei Fehlern während des Sendens
     *
     * @see \IPSModule::ReadPropertyString()
     * @see Zigbee2MQTT\AttributeArrayHelper::ReadAttributeArray()
     * @see \IPSModule::SendDebug()
     * @see \Zigbee2MQTT\SendData::SendData()
     * @see json_encode()
     * @see in_array()
     */
    public function SendGetCommand(): bool
    {
        // MQTT-Topic für den Get-Befehl generieren
        $Topic = $this->BuildConfiguredMQTTTopic(self::MQTT_TOPIC, 'get');
        if ($Topic === null) {
            return false;
        }

        // Geraetespezifische filtered_attributes aus Z2M laden
        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);

        // Payload bauen
        $Payload = [];
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            // Config Features werden nur über den Namen des Config property abgefragt und nicht einzeln.
            if (isset($expose['category']) && ($expose['category'] == 'config')) {
                // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                $sProperty = $expose['property'] ?? '';
                if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                    continue;
                }
                $Payload[$sProperty] = '';
                continue;
            }
            // Einzelne Features durchgehen (z.B. Endpoints state_1 usw...)
            if (isset($expose['features']) && \is_array($expose['features'])) {
                foreach ($expose['features'] as $feature) {
                    // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                    $sProperty = $feature['property'] ?? '';
                    if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                        $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                        continue;
                    }
                    $Payload[$sProperty] = '';
                }
                continue;
            }
            // Rest
            // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
            $sProperty = $expose['property'] ?? '';
            if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                continue;
            }
            $Payload[$sProperty] = '';
        }

        // Debug-Ausgabe des zu sendenden Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);

        // Sende die Daten an das Gerät
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * SetColorExt
     *
     * Ermöglicht es eine Farbe (INT) mit Transition zu setzen.
     *
     * @param  int $color
     * @param  int $TransitionTime
     * @return bool
     */
    public function SetColorExt(int $color, int $TransitionTime): bool
    {
        if (!$this->HasNativeColorExpose()) {
            $this->SendDebug(__FUNCTION__, 'Skip color transition action without native color expose support', 0);
            return false;
        }

        return $this->setColor($color, $this->getColorMode(), 'color', $TransitionTime);
    }

    /**
     * UIExportDebugData
     *
     * @return string
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
        $DebugData['SupportsOTA'] = $this->IsDeviceOTACapable();
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
     * Liest eine boolesche Property mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadPropertyBooleanSafe(string $name, bool $default): bool
    {
        return (bool) $this->ReadPropertySafe(fn (): bool => $this->ReadPropertyBoolean($name), $default);
    }

    /**
     * Liest eine Integer-Property mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadPropertyIntegerSafe(string $name, int $default): int
    {
        return (int) $this->ReadPropertySafe(fn (): int => $this->ReadPropertyInteger($name), $default);
    }

    /**
     * Liest eine Float-Property mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadPropertyFloatSafe(string $name, float $default): float
    {
        return (float) $this->ReadPropertySafe(fn (): float => $this->ReadPropertyFloat($name), $default);
    }

    /**
     * Liest eine String-Property mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadPropertyStringSafe(string $name, string $default): string
    {
        return (string) $this->ReadPropertySafe(fn (): string => $this->ReadPropertyString($name), $default);
    }

    /**
     * Liest ein boolesches Attribut mit Defaultwert fuer Update-/Migrationsfenster.
     */
    protected function ReadAttributeBooleanSafe(string $name, bool $default): bool
    {
        \set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            return $this->ReadAttributeBoolean($name);
        } catch (\Throwable) {
            return $default;
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * Aktiviert die HTML-SDK-Kachel, wenn eine passende Spezialkachel verfuegbar ist.
     */
    protected function UpdateCustomTileVisualizationType(): void
    {
        if (!method_exists($this, 'GetVisualizationTile')) {
            $this->SetVisualizationType(0);
            return;
        }

        $this->SetVisualizationType(($this->ShouldUseHeatingTile() || $this->ShouldUseMeteredSwitchTile() || $this->ShouldUseWindowHandleTile() || $this->ShouldUseSecurityTile() || $this->ShouldUseActionTile() || $this->ShouldUseSensorTile()) ? 1 : 0);
    }

    // Variablenmanagement

    /**
     * SetValue
     *
     * Setzt den Wert einer Variable unter Berücksichtigung verschiedener Typen und Formatierungen
     *
     * Verarbeitung:
     * 1. Prüft Existenz der Variable, Abbruch wenn Variable nicht vorhanden
     * 2. Konvertiert Wert entsprechend Variablentyp (adjustValueByType)
     * 3. Beruecksichtigt Legacy-Profilzuordnungen vorhandener Variablen
     * 4. Behandelt Spezialfälle (z.B. ColorTemp, Color)
     *
     * Unterstützte Variablentypen:
     * 1. State-Variablen:
     *    - state: ON/OFF -> true/false
     *    - stateL1: Nummerierte States
     *    - stateLeft: Richtungs-States
     *    - stateLeftL1: Kombinierte States
     *
     * 2. Spezielle Variablen:
     *    - color: RGB-Farbwerte oder XY-Farbwerte mit Brightness
     *      Format RGB: Integer (0xRRGGBB)
     *      Format XY: Array ['x' => float, 'y' => float, 'brightness' => int]
     *    - color_temp: Farbtemperatur mit Kelvin-Konvertierung
     *    - preset: Vordefinierte Werte
     *
     * 3. Standard-Variablen:
     *    - Boolean: Automatische ON/OFF Konvertierung
     *    - Integer/Float: Typkonvertierung mit Einheitenbehandlung
     *    - String: Direkte Wertzuweisung
     *
     * @param string $ident Identifier der Variable (z.B. "state", "color_temp", "color")
     * @param mixed $value Zu setzender Wert
     *                    Bool: true/false oder "ON"/"OFF"
     *                    Int/Float: Numerischer Wert
     *                    String: Textwert
     *                    Array: Spezielle Behandlung für Farben und Presets
     *                    Array: Andere Payloads werden nur von expliziten Sonderpfaden verarbeitet
     *
     * @return bool True, wenn der Wert verarbeitet wurde, sonst false.
     *
     * Beispiel:
     * ```php
     * // States
     * $this->SetValue("state", "ON");         // Setzt bool true
     * $this->SetValue("stateL1", false);      // Setzt "OFF"
     *
     * // Farben & Temperatur
     * $this->SetValue("color_temp", 4000);    // Setzt Farbtemp + Kelvin
     * $this->SetValue("color", 0xFF0000);     // Setzt Rot als RGB
     * $this->SetValue("color", [              // Setzt Farbe im XY Format
     *     'x' => 0.7006,
     *     'y' => 0.2993,
     *     'brightness' => 254
     * ]);
     *
     * // Legacy-Profile
     * $this->SetValue("mode", "auto");        // Beruecksichtigt vorhandene Profilassoziationen
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::adjustValueByType()
     * @see \Zigbee2MQTT\ModulBase::convertMiredToKelvin()
     * @see \Zigbee2MQTT\ModulBase::handleColorVariable()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \IPSModule::SetValue()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see IPS_GetVariable()
     * @see IPS_VariableProfileExists()
     * @see IPS_GetVariableProfile()
     */
    protected function SetValue(string $ident, mixed $value): bool
    {
        $variableID = $this->GetObjectIDByIdent($ident);
        if (!$variableID) {
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden: ' . $ident, 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'Verarbeite Variable: ' . $ident . ' mit Wert: ' . json_encode($value), 0);

        // Array Spezialbehandlung für
        if (\is_array($value)) {
            // Color-Arrays
            if (strtolower($ident) === 'color') {
                $this->handleColorVariable($ident, $value);
                return true;
            }
            $this->SendDebug(__FUNCTION__, 'Wert ist ein Array, übersprungen: ' . $ident, 0);
            return false;
        }
        $var = IPS_GetVariable($variableID);
        $varType = $var['VariableType'];
        $adjustedValue = $this->adjustValueByType($var, $value);

        // Legacy-Profilverarbeitung nur für nicht-boolesche Werte
        if ($varType !== 0) {
            $profileName = ($var['VariableCustomProfile'] != '' ? $var['VariableCustomProfile'] : $var['VariableProfile']);
            if ($profileName && IPS_VariableProfileExists($profileName)) {
                $profileAssociations = IPS_GetVariableProfile($profileName)['Associations'];
                foreach ($profileAssociations as $association) {
                    if ($association['Name'] == $value) {
                        $adjustedValue = $association['Value'];
                        $this->SendDebug(__FUNCTION__, 'Profilwert gefunden: ' . $value . ' -> ' . $adjustedValue, 0);
                        $changed = false;
                        $result = $this->SetModuleValue($ident, $variableID, $adjustedValue, $changed);
                        if ($changed) {
                            $this->UpdateCustomTileValuesIfRelevant($ident);
                        }
                        return $result;
                    }
                }
            }
        }

        $changed = false;
        $result = $this->SetModuleValue($ident, $variableID, $adjustedValue, $changed);
        if ($changed) {
            $this->SendDebug(__FUNCTION__, 'Setze Variable: ' . $ident . ' auf Wert: ' . json_encode($adjustedValue), 0);
        }

        // Spezialbehandlung für ColorTemp
        if ($changed && $ident === 'color_temp') {
            $kelvinIdent = 'color_temp_kelvin';
            $kelvinValue = $this->convertMiredToKelvin($value);
            $this->SetValueDirect($kelvinIdent, $kelvinValue);
            $this->UpdateColorTemperatureWhiteColorVariable($kelvinValue);
        }
        if ($changed) {
            $this->UpdateCustomTileValuesIfRelevant($ident);
        }
        return $result;
    }

    /**
     * SetValueDirect
     *
     * Setzt den Wert einer Variable direkt ohne weitere Verarbeitung.
     *
     * Diese Methode setzt den Wert einer Variable direkt mit minimaler Verarbeitung:
     * - Keine Profile-Verarbeitung
     * - Keine Spezialbehandlung von States
     * - Basale Konvertierung der Typen für grundlegende Datentypen
     *
     * Verarbeitung:
     * 1. Array-Werte werden zu JSON konvertiert
     * 2. Grundlegende Konvertierung des Typs (bool, int, float, string)
     * 3. Debug-Ausgaben für Fehleranalyse
     *
     * @param string $ident Der Identifikator der Variable, deren Wert gesetzt werden soll
     * @param mixed $value Der zu setzende Wert
     *                    - Array: Wird zu JSON konvertiert
     *                    - Bool: Wird zu bool konvertiert
     *                    - Int/Float: Wird zum entsprechenden Typ konvertiert
     *                    - String: Wird zu string konvertiert
     *
     * @return void
     *
     * Beispiel:
     * ```php
     * // Boolean setzen
     * $this->SetValueDirect("state", true);
     *
     * // Array als JSON
     * $this->SetValueDirect("data", ["temp" => 22]);
     * ```
     *
     * @internal Diese Methode wird hauptsächlich intern verwendet für:
     *          - Direkte Wertzuweisung ohne Profile
     *          - Array zu JSON Konvertierung
     *          - Debug-Werte setzen
     *
     * @see \IPSModule::SetValue()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see IPS_GetVariable()
     */
    protected function SetValueDirect(string $ident, mixed $value): void
    {
        $variableID = $this->GetObjectIDByIdent($ident);

        if (!$variableID) {
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden: ' . $ident, 0);
            return;
        }

        // Typ-Prüfung und Konvertierung
        if (\is_array($value)) {
            $this->SendDebug(__FUNCTION__, 'Array-Wert erkannt, konvertiere zu JSON', 0);
            $value = json_encode($value);
        }

        // Wert entsprechend Variablentyp konvertieren
        $debugVarType = 'unknown';
        switch (IPS_GetVariable($variableID)['VariableType']) {
            case VARIABLETYPE_BOOLEAN:
                $value = $this->adjustBooleanValueByType($ident, $value);
                $debugVarType = 'bool';
                break;
            case VARIABLETYPE_INTEGER:
                $value = (int) $value;
                $debugVarType = 'integer';
                break;
            case VARIABLETYPE_FLOAT:
                $value = (float) $value;
                $debugVarType = 'float';
                break;
            case VARIABLETYPE_STRING:
                $value = (string) $value;
                $debugVarType = 'string';
                break;
        }

        // Setze den Wert der Variable
        $changed = false;
        $this->SetModuleValue($ident, $variableID, $value, $changed);
        if ($changed) {
            $this->SendDebug(__FUNCTION__, \sprintf('Setze Variable: %s, Typ: %s, Wert: %s', $ident, $debugVarType, json_encode($value)), 0);
            $this->UpdateCustomTileValuesIfRelevant($ident);
        }
    }

    /**
     * Sendet HTML-SDK-Kachelwerte und ignoriert temporaere Symcon-Reload-Fenster.
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
     * Liefert konservative Defaults, wenn Symcon waehrend eines Modul-Reloads keinen Buffer lesen kann.
     */
    protected function GetDefaultBufferValue(string $name): mixed
    {
        return match ($name) {
            'BUFFER_MQTT_SUSPENDED',
            'BUFFER_PROCESSING_MIGRATION' => true,
            'lastPayload',
            'latestPayload',
            'missingTranslations',
            'brightnessConfig',
            'TransactionData',
            'Multi_TransactionData'       => [],
            default                       => false
        };
    }

    // Feature & Expose Handling

    /**
     * mapExposesToVariables
     *
     * Mappt die übergebenen Exposes auf Variablen und registriert diese.
     * Diese Funktion verarbeitet die übergebenen Exposes (z.B. Sensoreigenschaften) und registriert sie als Variablen.
     * Wenn ein Expose mehrere Features enthält, werden diese ebenfalls einzeln registriert.
     *
     * @param array $exposes Ein Array von Exposes, das die Geräteeigenschaften oder Sensoren beschreibt.
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::getVariableTypeFromFeature()
     * @see \Zigbee2MQTT\ModulBase::registerPresetVariables()
     * @see \IPSModule::SetBuffer()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     */
    protected function mapExposesToVariables(array $exposes): void
    {
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: All Exposes', json_encode($exposes), 0);

        // Geraetespezifische filtered_attributes aus Z2M laden
        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);

        // Durchlaufe alle Exposes
        foreach ($exposes as $expose) {
            // Prüfen, ob es sich um eine Gruppe handelt
            if (isset($expose['type']) && \in_array($expose['type'], ['light', 'switch', 'lock', 'cover', 'climate', 'fan', 'text'])) {
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Found group: ', $expose['type'], 0);

                // Features in der Gruppe verarbeiten
                if (isset($expose['features']) && \is_array($expose['features'])) {
                    foreach ($expose['features'] as $feature) {
                        // Gruppentyp auch im Variablenkatalog erhalten, damit spaetere
                        // ausdrueckliche Benutzeraktionen die passende Darstellung erkennen.
                        $feature['group_type'] = $expose['type'];

                        // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                        $sProperty = $feature['property'] ?? '';
                        if ($sProperty !== '' && !$this->IsExposeCompositeContainer($feature) && !isset($feature['color_mode'])) {
                            $this->RememberVariableDefinition($sProperty, $feature, 'expose');
                        }
                        if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                            $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                            continue;
                        }

                        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Processing feature in group: ', json_encode($feature), 0);
                        // Variablen für die einzelnen Features registrieren
                        $this->registerVariable($feature);

                        // Wenn es sich um brightness handelt, speichere die Min/Max Werte
                        if ($feature['property'] === 'brightness') {
                            $brightnessConfig = [
                                'min' => $feature['value_min'] ?? 0,
                                'max' => $feature['value_max'] ?? 255
                            ];
                            $this->brightnessConfig = $brightnessConfig;
                            $this->SendDebug(__FUNCTION__, 'Brightness Config: ' . json_encode($brightnessConfig), 0);
                        }
                    }
                } else {
                    $this->registerVariable($expose);
                }
            } else {
                // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                $sProperty = $expose['property'] ?? '';
                if ($sProperty !== '' && !$this->IsExposeCompositeContainer($expose) && !isset($expose['color_mode'])) {
                    $this->RememberVariableDefinition($sProperty, $expose, 'expose');
                }
                if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                    continue;
                }

                // registerVariable() verarbeitet vorhandene Presets bereits zentral.
                $this->registerVariable($expose);
            }
        }
        $this->RefreshExposeVariableCatalog($exposes);
        $this->UpdateCustomTileVisualizationType();
    }

    /**
     * LoadDeviceInfo
     *
     * Lädt die Geräte oder Gruppen Infos über die SymconExtension von Zigbee2MQTT
     *
     * @return array|false Enthält die Antwort als Array, oder false im Fehlerfall.
     *
     * @see \Zigbee2MQTT\SendData::SendData()
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::LogMessage()
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
        $Result = $this->SendDataQuiet(
            $topic,
            [],
            self::TIMEOUT_SYMCON_EXTENSION_INFO
        );
        if ($Result === false) {
            $this->LogMessage(\sprintf($this->Translate('Zigbee2MQTT did not response on Topic %s'), $topic), KL_WARNING);
        }
        return $Result;
    }

    /**
     * UpdateDeviceInfo
     *
     * Muss überschrieben werden
     * Muss die Exposes per LoadDeviceInfo laden und verarbeiten.
     *
     * @return bool
     */
    abstract protected function UpdateDeviceInfo(): bool;

    /**
     * Wandelt ein verschachteltes Array in ein eindimensionales Array mit zusammengesetzten Schlüsseln um
     *
     * @param array  $payload Das zu verarbeitende Array mit verschachtelter Struktur
     * @param string $prefix  Optional, Prefix für die zusammengesetzten Schlüssel
     *
     * @return array Ein eindimensionales Array mit Schlüsseln in der Form 'parent__child'
     *
     * Beispiele:
     * ```php
     * // Verschachteltes Array
     * $input = [
     *     'weekly_schedule' => [
     *         'monday' => '00:00/7'
     *     ]
     * ];
     * $result = $this->flattenPayload($input);
     * // Ergebnis: ['weekly_schedule__monday' => '00:00/7']
     * ```
     *
     * @internal Wird von processPayload verwendet um verschachtelte Strukturen zu verarbeiten
     *
     * @see \Zigbee2MQTT\ModulBase::processPayload()
     */
    protected function flattenPayload(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {

            // Composite-Keys überspringen, die in SKIP_COMPOSITES definiert sind und auf oberster Ebene gesetzt sind
            if ($prefix === '' && \in_array($key, self::SKIP_COMPOSITES) && \is_array($value)) {
                $this->SendDebug(__FUNCTION__, "Überspringe Composite-Key auf oberster Ebene: $key", 0);
                continue;
            }

            // Spezialbehandlung für color-Properties, da zur Farbberechnung nicht als flatten benötigt
            if ($key === 'color' && \is_array($value)) {
                // Übernehme die color-Properties direkt ins color-Array
                $result['color'] = $value;
                continue;
            }

            $newKey = $prefix ? $prefix . '__' . $key : $key;
            if (\is_array($value)) {
                $result = array_merge($result, $this->flattenPayload($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Wandelt einen zusammengesetzten Identifikator in eine verschachtelte Array-Struktur um
     *
     * @param string $ident Der zusammengesetzte Identifikator (z.B. 'weekly_schedule__friday')
     * @param mixed $value Der Wert, der gesetzt werden soll
     *
     * @return array Das verschachtelte Array
     *
     * Beispiel:
     * ```php
     * $ident = 'weekly_schedule__friday';
     * $value = '00:00/7';
     * $result = $this->buildNestedPayload($ident, $value);
     * // Ergebnis: ['weekly_schedule' => ['friday' => '00:00/7']]
     * ```
     *
     * @internal Diese Methode wird von handleStandardVariable, handlePresetVariable und RequestAction verwendet
     *
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable()
     * @see \Zigbee2MQTT\ModulBase::handlePresetVariable()
     * @see \Zigbee2MQTT\ModulBase::RequestAction()
     */
    protected function buildNestedPayload(string $ident, mixed $value): array
    {
        $parts = explode('__', $ident);
        $result = [];
        $current = &$result;

        // Alle Teile außer dem letzten durchgehen
        for ($i = 0; $i < \count($parts) - 1; $i++) {
            $current[$parts[$i]] = [];
            $current = &$current[$parts[$i]];
        }

        // Letzten Wert setzen
        $current[$parts[\count($parts) - 1]] = $value;

        return $result;
    }

    /**
     * Returns a human-readable name for a native Symcon presentation.
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
     * Fuehrt Property-Lesezugriffe aus, ohne fehlende neue Properties als Warning weiterzugeben.
     */
    private function ReadPropertySafe(\Closure $reader, bool|int|float|string $default): bool|int|float|string
    {
        \set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            return $reader();
        } catch (\Throwable) {
            return $default;
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * Leitet eine Aktion an den passenden internen Handler weiter.
     */
    private function handleRequestAction(string $ident, mixed $value): bool
    {
        $maintenanceResult = $this->HandleLocalVariableMaintenanceAction($ident, $value);
        if ($maintenanceResult !== null) {
            return $maintenanceResult;
        }

        $tileResult = $this->handleTileRequestAction($ident, $value);
        if ($tileResult !== null) {
            return $tileResult;
        }

        switch ($ident) {
            case 'UpdateInfo':
                $this->SendDebug(__FUNCTION__, 'Verarbeite UpdateInfo', 0);
                return $this->UpdateDeviceInfo();

            case 'ShowMissingTranslations':
                $this->SendDebug(__FUNCTION__, 'Verarbeite ShowMissingTranslations', 0);
                return $this->ShowMissingTranslations();

            case 'ToggleVariableCreation':
                $this->SendDebug(__FUNCTION__, 'Verarbeite ToggleVariableCreation: ' . (string) $value, 0);
                return $this->ToggleVariableCreation((string) $value);

            case 'RefreshVariableSelection':
                $this->SendDebug(__FUNCTION__, 'Aktualisiere Variablenkatalog aus aktuellen Gerätedaten', 0);
                $this->RefreshVariableSelectionFromForm();
                return true;
        }

        return $this->handleVariableRequestAction($ident, $value);
    }

    /**
     * Leitet HTML-SDK-Kachelaktionen an die jeweilige Kachel-Logik weiter.
     */
    private function handleTileRequestAction(string $ident, mixed $value): ?bool
    {
        return match (true) {
            str_starts_with($ident, 'HeatingTile.')        => $this->HandleHeatingTileAction($ident, $value),
            str_starts_with($ident, 'SensorTile.')         => $this->HandleSensorTileAction($ident, $value),
            str_starts_with($ident, 'SecurityTile.')       => $this->HandleSecurityTileAction($ident, $value),
            str_starts_with($ident, 'WindowHandleTile.')   => $this->HandleWindowHandleTileAction($ident, $value),
            str_starts_with($ident, 'ActionTile.')         => $this->HandleActionTileAction($ident, $value),
            str_starts_with($ident, 'MeteredSwitchTile.')  => $this->HandleMeteredSwitchTileAction($ident, $value),
            default                                        => null
        };
    }

    /**
     * Verarbeitet Variablenaktionen, die als MQTT-Set-Befehl enden.
     */
    private function handleVariableRequestAction(string $ident, mixed $value): bool
    {
        // Presets muessen vor Composite Keys verarbeitet werden.
        if (strpos($ident, 'presets') !== false) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite Preset: ' . $ident, 0);
            return $this->handlePresetVariable($ident, $value);
        }

        if (strpos($ident, '_and_') !== false) {
            $ident = str_replace('_and_', '&', $ident);
            $this->SendDebug(__FUNCTION__, 'recall action: ' . $ident, 0);
            $this->RequestAction($ident, $value);
            return true;
        }

        if (strpos($ident, '__') !== false) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite Composite Key: ' . $ident, 0);
            return $this->SendSetCommand($this->buildNestedPayload($ident, $value));
        }

        if (\in_array($ident, self::$stringVariablesNoResponse, true)) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite String ohne Rückmeldung: ' . $ident, 0);
            return $this->handleStringVariableNoResponse($ident, (string) $value);
        }

        if (\in_array($ident, ['color', 'color_hs', 'color_rgb', 'color_temp_kelvin'], true)) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite Farbvariable: ' . $ident, 0);
            return $this->handleColorVariable($ident, $value);
        }

        if (preg_match(self::STATE_PATTERN['SYMCON'], $ident)) {
            $this->SendDebug(__FUNCTION__, 'Verarbeite Status-Variable: ' . $ident, 0);
            return $this->handleStateVariable($ident, $value);
        }

        $this->SendDebug(__FUNCTION__, 'Verarbeite Standard-Variable: ' . $ident, 0);
        return $this->handleStandardVariable($ident, $value);
    }

    /**
     * Aktualisiert ausschliesslich die aktuell ausgewaehlte HTML-SDK-Kachel.
     *
     * Die Reihenfolge muss der Auswahl in Device::GetVisualizationTileDefinition()
     * entsprechen. Andernfalls kann eine weitere kompatible Kachel ihren anders
     * aufgebauten Datensatz an dieselbe Visualisierung senden und deren Zustand
     * ueberschreiben.
     */
    private function UpdateCustomTileValuesIfRelevant(string $ident): void
    {
        if ($this->ShouldForceSensorTile()) {
            $this->UpdateSensorTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseHeatingTile()) {
            $this->UpdateHeatingTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseMeteredSwitchTile()) {
            $this->UpdateMeteredSwitchTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseWindowHandleTile()) {
            $this->UpdateWindowHandleTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseSecurityTile()) {
            $this->UpdateSecurityTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseActionTile()) {
            $this->UpdateActionTileValueIfRelevant($ident);
            return;
        }
        if ($this->ShouldUseSensorTile()) {
            $this->UpdateSensorTileValueIfRelevant($ident);
        }
    }

    /**
     * Setzt einen Variablenwert module-strict-konform.
     */
    private function SetModuleValue(string $ident, int $variableID, mixed $value, ?bool &$changed = null): bool
    {
        $changed = true;
        if ($this->IsModuleValueUnchanged($variableID, $value)) {
            $changed = false;
            return true;
        }

        if (\defined('PHPUNIT_TESTSUITE') && \constant('PHPUNIT_TESTSUITE')) {
            \SetValue($variableID, $value);
            return true;
        }

        set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            return parent::SetValue($ident, $value);
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Prüft, ob der gewünschte Wert bereits unveraendert in der Symcon-Variable steht.
     */
    private function IsModuleValueUnchanged(int $variableID, mixed $value): bool
    {
        \set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            $currentValue = \GetValue($variableID);
        } catch (\Throwable) {
            return false;
        } finally {
            \restore_error_handler();
        }

        if (\is_float($currentValue) || \is_float($value)) {
            return \abs((float) $currentValue - (float) $value) < 0.000000001;
        }

        return $currentValue === $value;
    }

    /**
     * IPSModuleStrict deklariert GetIDForIdent() als int. Für Existenzprüfungen
     * nutzen wir die globale Funktion, weil sie bei fehlendem Ident false liefern darf.
     */
    private function GetObjectIDByIdent(string $ident): int|false
    {
        return @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
    }

    /**
     * Entfernt verwaiste interne Variablenregistrierungen vor einer Neuanlage.
     *
     * Bei Modul-Updates kann Symcon noch eine alte Maintained-Variable kennen,
     * obwohl das Objekt bereits geloescht wurde. Ein RegisterVariable*-Aufruf
     * wuerde dann mit "Variable #... existiert nicht" abbrechen.
     */
    private function PrepareVariableRegistration(string $ident): void
    {
        if ($this->GetObjectIDByIdent($ident) !== false) {
            return;
        }

        set_error_handler(static function (): bool
        {
            return true;
        });
        try {
            parent::UnregisterVariable($ident);
        } catch (\Throwable $exception) {
            $this->SendDebug(__FUNCTION__, 'Verwaiste Variablenregistrierung konnte nicht bereinigt werden: ' . $exception->getMessage(), 0);
        } finally {
            restore_error_handler();
        }
    }

    protected function RegisterVariableBoolean(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->PrepareVariableRegistration($Ident);
        return parent::RegisterVariableBoolean($Ident, $Name, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableInteger(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->PrepareVariableRegistration($Ident);
        return parent::RegisterVariableInteger($Ident, $Name, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableFloat(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->PrepareVariableRegistration($Ident);
        return parent::RegisterVariableFloat($Ident, $Name, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableString(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $this->PrepareVariableRegistration($Ident);
        return parent::RegisterVariableString($Ident, $Name, $ProfileOrPresentation, $Position);
    }

    /**
     * convertToSnakeCase
     *
     * Diese Hilfsfunktion entfernt das Prefix "Z2M_" und
     * wandelt CamelCase in lower_snake_case um.
     *
     * Beispiele:
     * - "color_temp" -> "color_temp"
     * - "brightnessABC" -> "brightness_a_b_c"
     * @param  string $oldIdent
     * @return string
     *
     * @see preg_replace()
     * @see ltrim()
     * @see strtolower()
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

    // MQTT Kommunikation

    /**
     * validateAndParseMessage
     *
     * Dekodiert und validiert eine MQTT-JSON-Nachricht
     *
     * Verarbeitung:
     * - Dekodiert JSON-String in Array
     * - Prüft auf JSON-Decodierung-Fehler
     * - Validiert Vorhandensein des Topic-Felds
     * - Zerlegt Topic in Array
     *
     * @param string $JSONString Die zu dekodierende MQTT-Nachricht
     *
     * @return array Decodiertes Topic und Payload-Array oder false,false Array bei Fehlern
     *
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::SendDebug()
     * @see json_decode()
     * @see json_last_error()
     * @see json_last_error_msg()
     * @see substr()
     * @see strlen()
     * @see \Zigbee2MQTT\SendData::DecodePayload()
     */
    private function validateAndParseMessage(string $JSONString): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);

        if (empty($baseTopic) || empty($mqttTopic)) {
            $this->SendDebug(__FUNCTION__, 'BaseTopic oder MQTTTopic ist leer', 0);
            return [false, false];
        }

        $messageData = json_decode($JSONString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->SendDebug(__FUNCTION__, 'JSON Decodierung fehlgeschlagen: ' . json_last_error_msg(), 0);
            return [false, false];
        }

        if (!isset($messageData['Topic'])) {
            $this->SendDebug(__FUNCTION__, 'Topic nicht gefunden', 0);
            return [false, false];
        }

        $receivedTopic = (string) $messageData['Topic'];
        if (!$this->IsExpectedReceiveTopic($receivedTopic, $baseTopic, $mqttTopic)) {
            $this->SendDebug(__FUNCTION__, 'Ignoriere fremdes MQTT-Topic: ' . $receivedTopic, 0);
            return [false, false];
        }

        $topic = substr($receivedTopic, \strlen($baseTopic) + 1);

        $payloadData = json_decode(self::DecodePayload($messageData['Payload']), true);
        return [
            explode('/', $topic),
            $payloadData
        ];
    }

    /**
     * Prueft eingehende MQTT-Nachrichten nochmals unabhaengig vom Symcon-Datenfilter.
     *
     * Der Datenfilter reduziert die Last im Datenfluss. Diese zweite Pruefung
     * verhindert, dass fremde Geraete-Payloads versehentlich Variablen in der
     * falschen Instanz anlegen, falls ein Parent eine Nachricht dennoch zustellt.
     */
    private function IsExpectedReceiveTopic(string $receivedTopic, string $baseTopic, string $mqttTopic): bool
    {
        $deviceTopic = $baseTopic . '/' . $mqttTopic;
        if (\in_array(
            $receivedTopic,
            [
                $deviceTopic,
                $deviceTopic . '/' . self::AVAILABILITY_TOPIC,
                $baseTopic . self::SYMCON_EXTENSION_RESPONSE . static::$ExtensionTopic . $mqttTopic
            ],
            true
        )) {
            return true;
        }

        return str_starts_with($receivedTopic, $baseTopic . self::SYMCON_EXTENSION_LIST_RESPONSE);
    }

    /**
     * handleAvailability
     *
     * Verarbeitet den Verfügbarkeitsstatus eines Zigbee-Geräts
     *
     * Funktionen:
     * - Prüft ob Topic ein Verfügbarkeits-Topic ist
     * - Registriert/Aktualisiert Verfügbarkeits-Variable mit nativer Darstellung
     *
     * @param array $topics Array mit Topic-Bestandteilen
     * @param array|null $payload Array mit MQTT-Nachrichtendaten oder null fuer eine leere Verfuegbarkeitsmeldung
     *
     * @return bool True wenn Verfügbarkeit verarbeitet wurde, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::RegisterVariableBoolean()
     * @see \IPSModule::Translate()
     * @see \IPSModule::SetValue()
     * @see end()
     */
    private function handleAvailability(array $topics, ?array $payload): bool
    {
        if (end($topics) !== self::AVAILABILITY_TOPIC) {
            return false;
        }
        $this->RememberVariableDefinition('device_status', ['property' => 'device_status', 'type' => 'binary', 'label' => 'Availability'], 'system');
        if (!$this->CanCreateVariable('device_status', ['property' => 'device_status', 'type' => 'binary', 'label' => 'Availability'], 'system')) {
            return true;
        }
        $deviceStatusPresentation = $this->BuildDeviceStatusPresentation() ?? '';
        $this->RecordLegacyProfilePresentationReplacement('device_status', $deviceStatusPresentation);
        $this->RegisterVariableBoolean('device_status', $this->Translate('Availability'), $deviceStatusPresentation);
        $this->MarkVariableCreated('device_status');
        if (isset($payload['state'])) {
            $this->SetValueDirect('device_status', $payload['state'] == 'online');
        } else { // leeren Payload, wenn z.B. Gerät gelöscht oder umbenannt wurde
            $this->SetValueDirect('device_status', false);
        }
        return true;
    }

    /**
     * handleSymconExtensionResponses
     *
     * Verarbeitet Antworten von Symcon Extension Anfragen
     *
     * Funktionalität:
     * - Prüft ob Topic eine Symcon Extension Antwort ist
     * - Verarbeitet Device/Group Info Antworten
     * - Aktualisiert Transaktionsdaten wenn vorhanden
     *
     * Antwort-Typen:
     * - getDeviceInfo: Informationen über ein einzelnes Gerät
     * - getGroupInfo: Informationen über eine Gerätegruppe
     *
     * @param array $topics Array mit Topic-Bestandteilen
     * @param array $payload Array mit MQTT-Nachrichtendaten
     *
     * @return bool True wenn eine Symcon-Antwort verarbeitet wurde, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::UpdateTransaction()
     * @see \IPSModule::ReadPropertyString()
     * @see implode()
     */
    private function handleSymconExtensionResponses(array $topics, array $payload): bool
    {
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        $fullTopic = '/' . implode('/', $topics);
        if ($fullTopic === self::SYMCON_EXTENSION_RESPONSE . static::$ExtensionTopic . $mqttTopic) {
            if (isset($payload['transaction']) && $this->UpdateTransaction($payload)) {
                return true;
            }
            if ($this->UpdateTransactionByResponseTopic($fullTopic, $payload)) {
                return true;
            }
            return true;
        }
        if (str_starts_with($fullTopic, self::SYMCON_EXTENSION_LIST_RESPONSE)) {
            if (isset($payload['transaction']) && $this->UpdateTransaction($payload)) {
                return true;
            }
            if ($this->UpdateTransactionByResponseTopic($fullTopic, $payload)) {
                return true;
            }
            return true;
        }
        return false;
    }

    /**
     * Verarbeitet die empfangenen MQTT-Payload-Daten
     *
     * @param array $payload Array mit den MQTT-Nachrichtendaten
     *                      Unterstützt sowohl Array [] als auch Object {} Payload-Formate
     *
     * @return void
     *
     * Beispiele:
     * ```php
     * // Payload mit einem unbrauchbaren numerischen Root-Eintrag
     * $payload = [0 => 'value', 'temperature' => 21.5];
     * $this->processPayload($payload);
     * // Verarbeitet wird nur "temperature"; Root-Eintrag 0 hat keinen Variablen-Ident.
     *
     * // Object Payload mit Composite-Struktur
     * $payload = [
     *     'weekly_schedule' => [
     *         'monday' => '00:00/7'
     *     ]
     * ];
     * $this->processPayload($payload);
     * ```
     *
     * @internal Diese Methode wird von ReceiveData aufgerufen
     *
     * @see \Zigbee2MQTT\ModulBase::ReceiveData()
     * @see \Zigbee2MQTT\ModulBase::mapExposesToVariables()
     * @see \Zigbee2MQTT\ModulBase::processSpecialVariable()
     * @see \Zigbee2MQTT\ModulBase::processVariable()
     * @see \IPSModule::SendDebug()
     * @see strpos()
     * @see is_array()
     * @see json_encode()
     */
    private function processPayload(array $payload): void
    {
        // Exposes verarbeiten wenn vorhanden
        if (isset($payload['exposes'])) {
            if (\is_array($payload['exposes'])) {
                $this->WriteAttributeArray(self::ATTRIBUTE_EXPOSES, $payload['exposes']);
                $this->mapExposesToVariables($payload['exposes']);
            }
            unset($payload['exposes']);
        }

        $payload = $this->filterPayloadRootIdentEntries($payload);

        $this->latestPayload = $payload;
        if ($payload === []) {
            return;
        }

        $this->lastPayload = $this->lastPayload + $payload;

        // Verschachtelte Strukturen flach machen
        $flattenedPayload = $this->flattenPayload($payload);

        // Payload-Daten verarbeiten
        foreach ($flattenedPayload as $key => $value) {
            if (!\is_string($key)) {
                $this->SendDebug(
                    'processPayload',
                    \sprintf(
                        'Ueberspringe Payload-Eintrag ohne Variablen-Ident: Key=%s, Value=%s',
                        (string) $key,
                        $this->formatPayloadDebugValue($value)
                    ),
                    0
                );
                continue;
            }
            $this->processPayloadEntry($key, $value);
        }
    }

    /**
     * Entfernt numerisch indizierte Root-Eintraege aus MQTT-Payloads.
     *
     * Zigbee2MQTT-Geraete-Payloads muessen Property-Namen enthalten, damit sie
     * auf Symcon-Variablen abgebildet werden koennen. Ein reines JSON-Array wie
     * [9] liefert nur den numerischen Key 0 und ist fuer die Variablenlogik nicht
     * verarbeitbar.
     *
     * @param array<mixed,mixed> $payload
     *
     * @return array<string,mixed>
     */
    private function filterPayloadRootIdentEntries(array $payload): array
    {
        $filteredPayload = [];
        foreach ($payload as $key => $value) {
            if (!\is_string($key)) {
                $this->SendDebug(
                    'processPayload',
                    \sprintf(
                        'Ueberspringe Payload-Eintrag ohne Variablen-Ident: Key=%s, Value=%s',
                        (string) $key,
                        $this->formatPayloadDebugValue($value)
                    ),
                    0
                );
                continue;
            }

            $filteredPayload[$key] = $value;
        }

        return $filteredPayload;
    }

    /**
     * Verarbeitet einen einzelnen Eintrag des flach gemachten MQTT-Payloads.
     */
    private function processPayloadEntry(string $key, mixed $value): void
    {
        if ($value === null) {
            $this->SendDebug('processPayload', \sprintf('Skip empty value for key=%s', $key), 0);
            return;
        }

        $this->SendDebug('processPayload', \sprintf('Verarbeite: Key=%s, Value=%s', $key, $this->formatPayloadDebugValue($value)), 0);

        if (!$this->processSpecialVariable($key, $value)) {
            $this->processVariable($key, $value);
        }
    }

    /**
     * Formatiert einen Payload-Wert fuer Debugausgaben.
     */
    private function formatPayloadDebugValue(mixed $value): string
    {
        if (\is_array($value)) {
            return (string) json_encode($value);
        }
        if (\is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        return (string) $value;
    }

    /**
     * getOrRegisterVariable
     *
     * Holt oder registriert eine Variable basierend auf dem Identifikator.
     *
     * Diese Methode prüft, ob eine Variable mit dem angegebenen Identifikator existiert. Wenn nicht,
     * wird die Variable registriert und die ID der neu registrierten Variable zurückgegeben.
     *
     * @param string $ident Der Identifikator der Variable.
     * @param array|null $variableProps Die Eigenschaften der Variable, die registriert werden sollen, falls sie nicht existiert.
     * @param string|null $formattedLabel Das formatierte Label der Variable, falls vorhanden.
     *
     * @return ?int Die ID der Variable oder NULL, wenn die Registrierung fehlschlägt.
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::GetIDForIdent()
     * @see debug_backtrace()
     */
    private function getOrRegisterVariable(string $ident, ?array $variableProps = null, ?string $formattedLabel = null): ?int
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return null;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $this->SendDebug(__FUNCTION__, 'Aufruf von getOrRegisterVariable für Ident: ' . $ident . ' von Funktion: ' . $caller, 0);

        $variableID = $this->GetObjectIDByIdent($ident);
        if (!$variableID && $variableProps !== null) {
            if (!$this->CanCreateVariable($ident, $variableProps, 'payload')) {
                return null;
            }
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden, Registrierung: ' . $ident, 0);
            $this->registerVariable($variableProps, $formattedLabel);
            $variableID = $this->GetObjectIDByIdent($ident);
            if (!$variableID) {
                $this->SendDebug(__FUNCTION__, 'Fehler beim Registrieren der Variable: ' . $ident, 0);
                return null;
            }
        }
        $this->SendDebug(__FUNCTION__, 'Variable gefunden: ' . $ident . ' (ID: ' . $variableID . ')', 0);
        return $variableID;
    }

    /**
     * processVariable
     *
     * Verarbeitet eine einzelne Variable mit ihrem Wert.
     *
     * Diese Methode wird aufgerufen, um eine einzelne Variable aus dem empfangenen Payload zu verarbeiten.
     * Sie prüft, ob die Variable bekannt ist, registriert sie gegebenenfalls und setzt den Wert.
     *
     * @param string $key Der Schlüssel im empfangenen Payload.
     * @param mixed $value Der Wert, der mit dem Schlüssel verbunden ist.
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::processSpecialVariable()
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::handleColorVariable()
     * @see \Zigbee2MQTT\ModulBase::convertMillivoltToVolt()
     * @see \Zigbee2MQTT\ModulBase::getKnownVariables()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::GetIDForIdent()
     * @see strtolower()
     * @see is_array()
     * @see strpos()
     */
    private function processVariable(string $key, mixed $value): void
    {
        if ($this->processCompositeKeyVariable($key, $value)) {
            return;
        }

        if ($this->processStructuredArrayVariable($key, $value)) {
            return;
        }

        $ident = $key;

        if ($this->updateExistingPayloadVariable($ident, $value)) {
            return;
        }

        // Bekannte Variablen laden und prüfen
        $lowerKey = strtolower($key);
        $knownVariables = $this->getKnownVariables();
        if (!isset($knownVariables[$lowerKey])) {
            $this->RememberVariableDefinition($ident, ['property' => $ident, 'type' => $this->GetPayloadValueTypeName($value)], 'payload', $value);
            $this->CanCreateVariable($ident, ['property' => $ident, 'type' => $this->GetPayloadValueTypeName($value)], 'payload', $value);
            $this->SendDebug(__FUNCTION__, 'Variable nicht bekannt: ' . $key, 0);
            return;
        }

        $variableProps = $knownVariables[$lowerKey];
        $this->RememberVariableDefinition($ident, $variableProps, 'payload', $value);

        // Array-Werte verarbeiten
        if (\is_array($value)) {
            $this->processArrayValue($ident, $value);
            return;
        }

        // Spezialbehandlungen durchführen
        if ($this->processSpecialCases($key, $value, $lowerKey, $variableProps)) {
            return;
        }

        $this->registerKnownPayloadVariable($ident, $value, $variableProps);
        $this->updatePayloadPresetVariable($ident, $value);
    }

    /**
     * Verarbeitet Composite-Key-Variablen wie color_options__execute_if_off.
     */
    private function processCompositeKeyVariable(string $key, mixed $value): bool
    {
        if (!$this->isCompositeKey($key)) {
            return false;
        }

        $varType = $this->getPayloadVariableTypeDefinition($value, $key);
        if (!$this->GetObjectIDByIdent($key)) {
            if (!$this->CanCreateVariable($key, ['property' => $key, 'type' => $this->GetPayloadValueTypeName($value)], 'payload', $value)) {
                return true;
            }
            $registerFunc = $varType['registerFunc'];
            $this->$registerFunc(
                $key,
                $this->Translate($this->convertLabelToName($key)),
                $varType['presentation']
            );
            $this->MarkVariableCreated($key);
            $this->checkAndEnableAction($key);
        }

        $this->SetValue($key, $value);
        return true;
    }

    /**
     * Ermittelt Registrierungsdaten fuer dynamisch angelegte Payload-Variablen.
     *
     * Bekannte Feature-Idents werden auch dann fuer den Variablentyp beruecksichtigt,
     * wenn Zigbee2MQTT einen Wert nur im Payload liefert und keine vollstaendigen
     * Expose-Metadaten vorliegen. Eine Profilzuweisung erfolgt hier bewusst nicht.
     */
    private function getPayloadVariableTypeDefinition(mixed $value, string $ident = ''): array
    {
        if ($ident !== '') {
            $payloadType = $this->GetPayloadValueTypeName($value);
            $registerFunc = match ($this->getVariableTypeFromFeature($payloadType, $ident)) {
                'bool'  => 'RegisterVariableBoolean',
                'int'   => 'RegisterVariableInteger',
                'float' => 'RegisterVariableFloat',
                default => 'RegisterVariableString'
            };

            return [
                'presentation' => '',
                'registerFunc' => $registerFunc
            ];
        }

        return match (true) {
            \is_bool($value) => [
                'presentation' => '',
                'registerFunc' => 'RegisterVariableBoolean'
            ],
            \is_int($value) => [
                'presentation' => '',
                'registerFunc' => 'RegisterVariableInteger'
            ],
            \is_float($value) => [
                'presentation' => '',
                'registerFunc' => 'RegisterVariableFloat'
            ],
            default => [
                'presentation' => '',
                'registerFunc' => 'RegisterVariableString'
            ]
        };
    }

    /**
     * Verarbeitet Array-Werte mit besonderer Zigbee2MQTT-Struktur.
     */
    private function processStructuredArrayVariable(string $key, mixed $value): bool
    {
        if (!\is_array($value)) {
            return false;
        }

        if (strpos($key, 'color') === 0) {
            $this->handleColorVariable($key, $value);
            return true;
        }

        if (isset($value['composite'])) {
            foreach ($value['composite'] as $compositeKey => $compositeValue) {
                $this->processVariable($compositeKey, $compositeValue);
            }
            return true;
        }

        if (isset($value['type']) && $value['type'] === 'list') {
            $this->processListPayloadVariable($key, $value);
            return true;
        }

        return false;
    }

    /**
     * Speichert Listen als JSON und verarbeitet deren Items einzeln.
     */
    private function processListPayloadVariable(string $key, array $value): void
    {
        $this->SetValueDirect($key, json_encode($value));
        if (!isset($value['items']) || !\is_array($value['items'])) {
            return;
        }

        foreach ($value['items'] as $index => $item) {
            $this->processVariable($key . '_item_' . $index, $item);
        }
    }

    /**
     * Aktualisiert eine bereits vorhandene Variable aus einem Payload-Wert.
     */
    private function updateExistingPayloadVariable(string $ident, mixed $value): bool
    {
        if ($this->GetObjectIDByIdent($ident) === false) {
            return false;
        }

        $this->SendDebug('processVariable', 'Existierende Variable gefunden: ' . $ident, 0);
        $this->SetValue($ident, $value);
        $this->updatePresetVariable($ident, $value);
        return true;
    }

    /**
     * Registriert eine bekannte Variable bei Bedarf und setzt ihren Wert.
     */
    private function registerKnownPayloadVariable(string $ident, mixed $value, array $variableProps): void
    {
        $variableID = $this->getOrRegisterVariable($ident, $variableProps);
        if (!$variableID) {
            return;
        }

        $this->checkAndEnableAction($ident, $variableProps);
        $this->SetValue($ident, $value);
    }

    /**
     * Aktualisiert die zugehoerige Preset-Variable, wenn sie vorhanden ist.
     */
    private function updatePayloadPresetVariable(string $ident, mixed $value): void
    {
        $presetIdent = $ident . '_presets';
        if ($this->GetObjectIDByIdent($presetIdent) !== false) {
            $this->SetValue($presetIdent, $value);
        }
    }

    /**
     * Verarbeitet Array-Werte aus dem Payload, die keine eigene Variable abbilden.
     *
     * Farb-Arrays werden an die Farbverarbeitung weitergereicht; andere Array-Werte
     * werden nur ins Debug geschrieben, damit sie nicht ungefiltert serialisiert werden.
     *
     * @param string $ident Ident der Payload-Variable.
     * @param array $value Array-Wert aus dem Zigbee2MQTT-Payload.
     */
    private function processArrayValue(string $ident, array $value): void
    {
        if (strpos($ident, 'color') === 0) {
            $this->handleColorVariable($ident, $value);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'Array-Wert für: ' . $ident, 0);
        $this->SendDebug(__FUNCTION__, 'Inhalt: ' . json_encode($value), 0);
    }

    /**
     * Behandelt Sonderfaelle, bevor ein Payload-Wert normal geschrieben wird.
     *
     * Dazu gehoeren zum Beispiel Helligkeit in Lichtgruppen und die automatische
     * Spannungskonvertierung von Millivolt nach Volt.
     *
     * @param string $key Originaler Payload-Key.
     * @param mixed $value Payload-Wert, der bei Bedarf angepasst wird.
     * @param string $lowerKey Kleingeschriebener Payload-Key fuer Vergleiche.
     * @param array $variableProps Expose-/Variableninformationen der bekannten Variable.
     *
     * @return bool True, wenn der Sonderfall vollstaendig verarbeitet wurde.
     */
    private function processSpecialCases(string $key, mixed &$value, string $lowerKey, array $variableProps): bool
    {
        // Brightness in Lichtgruppen
        foreach (self::$VariableTypeMappings as $entry) {
            if (
                $entry['feature'] === $lowerKey &&
                isset($entry['group_type'], $variableProps['group_type']) &&
                $entry['group_type'] === 'light' &&
                $variableProps['group_type'] === 'light'
            ) {

                $this->SendDebug(__FUNCTION__, 'Brightness in Lichtgruppe - Variablenmapping', 0);
                return $this->processSpecialVariable($key, $value);
            }
        }

        // Voltage Behandlung
        if ($lowerKey === 'voltage') {
            $this->SendDebug(__FUNCTION__, 'Voltage vor Konvertierung: ' . $value, 0);
            if ($this->processSpecialVariable($key, $value)) {
                return true;
            }
            $value = self::convertMillivoltToVolt($value);
            $this->SendDebug(__FUNCTION__, 'Voltage nach Konvertierung: ' . $value, 0);
        }

        return false;
    }

    /**
     * handleStandardVariable
     *
     * Verarbeitet Standard-Variablenaktionen und sendet diese an das Zigbee-Gerät.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Standard-Variable angefordert wird.
     * Sie konvertiert den Wert bei Bedarf und sendet den entsprechenden Set-Befehl.
     *
     * Spezielle Wertkonvertierungen:
     * - child_lock: bool true/false wird zu 'LOCK'/'UNLOCK' konvertiert
     * - Boolesche Werte: true/false wird zu 'ON'/'OFF' konvertiert
     * - brightness: Prozentwert (0-100) wird in Gerätewert (0-254) konvertiert
     *
     * @param string $ident Der Identifikator der Standard-Variable (z.B. 'state', 'brightness', 'child_lock')
     * @param mixed $value Der zu setzende Wert:
     *                    - bool für ON/OFF oder LOCK/UNLOCK
     *                    - int für Helligkeitswerte (0-100)
     *                    - mixed für andere Werte
     *
     * @return bool True wenn der Set-Befehl erfolgreich gesendet wurde, False bei Fehlern
     *
     * @example
     * handleStandardVariable('state', true)      // Sendet: {"state": "ON"}
     * handleStandardVariable('child_lock', true) // Sendet: {"child_lock": "LOCK"}
     * handleStandardVariable('brightness', 50)   // Sendet: {"brightness": 127}
     *
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::normalizeValueToRange()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_bool()
     */
    private function handleStandardVariable(string $ident, mixed $value): bool
    {
        $variableID = $this->getOrRegisterVariable($ident);
        if (!$variableID) {
            return false;
        }

        if (\is_bool($value)) {
            $boolResult = $this->handleStandardBooleanVariable($ident, $value);
            if ($boolResult !== null) {
                return $boolResult;
            }
            $value = $this->convertOnOffValue($value, false);
        }

        if ($this->isCompositeKey($ident)) {
            $payload = $this->buildNestedPayload($ident, $value);
            $this->SendDebug(__FUNCTION__, 'Sende composite payload: ' . json_encode($payload), 0);
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $value, $payload, __FUNCTION__);
        }

        if ($ident === 'brightness') {
            return $this->sendBrightnessAction($value);
        }

        if ($ident === 'color_temp' && !$this->HasExposeProperty('color_temp')) {
            $this->SendDebug(__FUNCTION__, 'Skip color_temp action without color_temp expose support', 0);
            return false;
        }

        return $this->sendStandardActionPayload($ident, $value);
    }

    /**
     * Verarbeitet boolesche Standard-Aktionen mit Expose-spezifischem Mapping.
     */
    private function handleStandardBooleanVariable(string $ident, bool $value): ?bool
    {
        $feature = $this->findExposeFeatureByProperty($ident);
        if ($feature === null) {
            return null;
        }

        if ($this->isWriteOnlySingleEnumCommand($feature)) {
            if (!$value) {
                return true;
            }
            return $this->SendSetCommand([$ident => $feature['values'][0]]);
        }

        if (($feature['type'] ?? '') === 'binary' && isset($feature['value_on'], $feature['value_off'])) {
            $payloadValue = $value ? $feature['value_on'] : $feature['value_off'];
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $value, [$ident => $payloadValue], __FUNCTION__);
        }

        return null;
    }

    /**
     * Sucht ein gespeichertes Expose-Feature anhand seiner Property.
     */
    private function findExposeFeatureByProperty(string $property): ?array
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            $feature = $this->findExposeFeatureByPropertyRecursive($expose, $property);
            if ($feature !== null) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * Prueft, ob die aktuelle Zigbee2MQTT-Expose-Liste eine Property anbietet.
     */
    private function HasExposeProperty(string $property): bool
    {
        return $this->findExposeFeatureByProperty($property) !== null;
    }

    /**
     * Prueft, ob Zigbee2MQTT fuer eine Aktion voraussichtlich wieder einen Wert publiziert.
     */
    private function ShouldWaitForZigbee2MQTTFeedback(string $ident): bool
    {
        $feature = $this->findExposeFeatureByProperty($ident);
        if ($feature === null && $this->isCompositeKey($ident)) {
            $parts = explode('__', $ident);
            $childIdent = end($parts);
            if (\is_string($childIdent) && $childIdent !== '') {
                $feature = $this->findExposeFeatureByProperty($childIdent);
            }
        }

        if ($feature === null) {
            return true;
        }

        return (((int) ($feature['access'] ?? 0)) & 0b001) !== 0;
    }

    /**
     * Merkt nur reine Schreib- und Befehlswerte lokal, die keine Rueckmeldung liefern.
     */
    private function UpdateLocalValueAfterSetIfNoFeedback(string $ident, mixed $value, string $context): void
    {
        if ($this->ShouldWaitForZigbee2MQTTFeedback($ident)) {
            $this->SendDebug($context, 'Set-Befehl gesendet; lokaler Wert wird erst nach Zigbee2MQTT-Rueckmeldung aktualisiert.', 0);
            return;
        }

        $this->SendDebug($context, 'Set-Befehl ohne erwartete Rueckmeldung; lokaler Wert wird lokal gemerkt.', 0);
        $this->SetValueDirect($ident, $value);
    }

    /**
     * Sendet ein Set-Payload und merkt Werte ohne Zigbee2MQTT-Rueckmeldung lokal.
     */
    private function SendSetCommandAndUpdateLocalIfNoFeedback(string $ident, mixed $localValue, array $payload, string $context): bool
    {
        if (!$this->SendSetCommand($payload)) {
            return false;
        }

        $this->UpdateLocalValueAfterSetIfNoFeedback($ident, $localValue, $context);
        return true;
    }

    /**
     * Rekursive Suche in gruppierten Exposes.
     */
    private function findExposeFeatureByPropertyRecursive(array $feature, string $property): ?array
    {
        if (($feature['property'] ?? null) === $property) {
            return $feature;
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return null;
        }

        foreach ($feature['features'] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }

            $found = $this->findExposeFeatureByPropertyRecursive($subFeature, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Sendet eine Helligkeitsaktion im Wertebereich des Zigbee2MQTT-Geraets.
     */
    private function sendBrightnessAction(mixed $value): bool
    {
        $payload = ['brightness' => $this->normalizeValueToRange($value, true)];
        return $this->SendSetCommandAndUpdateLocalIfNoFeedback('brightness', $value, $payload, __FUNCTION__);
    }

    /**
     * Sendet ein einfaches Set-Payload fuer Standard-Aktionen.
     */
    private function sendStandardActionPayload(string $ident, mixed $value): bool
    {
        $payload = [$ident => $value];
        $this->SendDebug('handleStandardVariable', 'Sende payload: ' . json_encode($payload), 0);
        return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $value, $payload, 'handleStandardVariable');
    }

    /**
     * handleStateVariable
     *
     * Verarbeitet State-bezogene Aktionen und sendet entsprechende MQTT-Befehle.
     *
     * Diese Methode überprüft verschiedene State-Szenarien:
     * 1. Standard State-Pattern (ON/OFF)
     * 2. Vordefinierte States aus stateDefinitions
     * 3. States aus dem STATE_PATTERN
     *
     * @param string $ident Identifikator der State-Variable
     * @param mixed $value Zu setzender Wert (bool|string|int)
     *
     * @return bool True wenn State erfolgreich verarbeitet wurde, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::convertOnOffValue() Konvertiert Werte zwischen ON/OFF und bool
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand() Sendet MQTT Befehle
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect() Setzt Variablenwert direkt
     * @see \IPSModule::SendDebug() Debug Ausgaben
     * @see \IPSModule::GetValue() Aktuellen Wert abfragen
     * @see preg_match() Pattern Matching für State-Erkennung
     * @see strtoupper() String zu Großbuchstaben
     * @see json_encode() JSON Konvertierung für Debug
     * @see isset() Array Key Prüfung
     */
    private function handleStateVariable(string $ident, mixed $value): bool
    {
        $this->SendDebug(__FUNCTION__, 'State-Handler für: ' . $ident . ' mit Wert: ' . json_encode($value), 0);

        $stateFeature = $this->findExposeFeatureByProperty($ident);
        if ($this->isEnumStateFeature($stateFeature)) {
            $enumStateValue = $this->normalizeEnumStateActionValue($stateFeature, $value);
            if ($enumStateValue === null) {
                $this->SendDebug(__FUNCTION__, 'Unbekannter Enum-State-Wert: ' . json_encode($value), 0);
                return false;
            }

            $payload = [$ident => $enumStateValue];
            $this->SendDebug(__FUNCTION__, 'Enum-State-Payload wird gesendet: ' . json_encode($payload), 0);
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $enumStateValue, $payload, __FUNCTION__);
        }

        // State Pattern Prüfung
        if (preg_match(self::STATE_PATTERN['SYMCON'], $ident)) {
            $stateValue = $this->convertOnOffValue($value, false);
            $payload = [$ident => $stateValue];
            $this->SendDebug(__FUNCTION__, 'State-Payload wird gesendet: ' . json_encode($payload), 0);
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $stateValue, $payload, __FUNCTION__);
        }

        // Prüfe auf vordefinierte States
        if (isset(static::$stateDefinitions[$ident])) {
            $stateInfo = static::$stateDefinitions[$ident];
            if (isset($stateInfo['values'])) {
                $index = \is_bool($value) ? (int) $value : $value;
                if (isset($stateInfo['values'][$index])) {
                    $stateValue = $stateInfo['values'][$index];
                    $payload = [$ident => $stateValue];
                    $this->SendDebug(__FUNCTION__, 'Vordefinierter State-Payload wird gesendet: ' . json_encode($payload), 0);
                    return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $stateValue, $payload, __FUNCTION__);
                }
            }
        }

        // Überprüfen, ob der Wert in STATE_PATTERN definiert ist
        $stringValue = (string) $value;
        if (isset(self::STATE_PATTERN[strtoupper($stringValue)])) {
            $adjustedValue = self::STATE_PATTERN[strtoupper($stringValue)];
            $this->SendDebug(__FUNCTION__, 'State-Wert gefunden: ' . $stringValue . ' -> ' . json_encode($adjustedValue), 0);
            $payload = [$ident => $adjustedValue];
            $this->SendDebug(__FUNCTION__, 'State-Payload wird gesendet: ' . json_encode($payload), 0);
            return $this->SendSetCommandAndUpdateLocalIfNoFeedback($ident, $adjustedValue, $payload, __FUNCTION__);
        }

        $this->SendDebug(__FUNCTION__, 'Kein passender State-Handler gefunden', 0);
        return false;
    }

    /**
     * Ermittelt den exakten Enum-Wert fuer State-Aktionen wie OPEN/CLOSE/STOP.
     */
    private function normalizeEnumStateActionValue(array $feature, mixed $value): ?string
    {
        $values = array_values(array_map(static fn (mixed $entry): string => (string) $entry, $feature['values']));
        if (\is_int($value) && isset($values[$value])) {
            return $values[$value];
        }

        $stringValue = (string) $value;
        foreach ($values as $enumValue) {
            if ($enumValue === $stringValue) {
                return $enumValue;
            }
        }

        return null;
    }

    /**
     * Prueft, ob ein State-Expose als Enum definiert ist.
     */
    private function isEnumStateFeature(?array $feature): bool
    {
        return $feature !== null
            && ($feature['type'] ?? '') === 'enum'
            && \is_array($feature['values'] ?? null);
    }

    /**
     * handleColorVariable
     *
     * Verarbeitet Farbvariablenaktionen.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Farbvariable angefordert wird.
     * Sie verarbeitet verschiedene Arten von Farbvariablen basierend auf dem Identifikator der Variable.
     * Der Identifikator color kann verschiedene Modi abbilden.
     * Der aktuelle Modus wird aus der Variable color_mode über getColorMode ermittelt.
     *
     * @param string $ident Der Identifikator der Farbvariable.
     * @param mixed $value Der Wert, der mit der Farbvariablen-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück, wenn die Aktion erfolgreich verarbeitet wurde, andernfalls false.
     *
     * @see \Zigbee2MQTT\ColorHelper::getColorMode()
     * @see \Zigbee2MQTT\ColorHelper::xyToInt()
     * @see \Zigbee2MQTT\ColorHelper::convertKelvinToMired()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \Zigbee2MQTT\ModulBase::setColor()
     * @see \IPSModule::GetValue()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_int()
     * @see is_array()
     * @see sprintf()
     */
    private function handleColorVariable(string $ident, mixed $value): bool
    {
        return match ($ident) {
            'color'             => $this->handleMainColorVariable($value),
            'color_hs'          => $this->handleColorSpaceAction($value, 'hs'),
            'color_rgb'         => $this->handleColorSpaceAction($value, 'cie', 'color_rgb'),
            'color_temp_kelvin' => $this->handleColorTemperatureKelvinAction($value),
            'color_temp'        => $this->handleColorTemperatureAction($value),
            default             => false,
        };
    }

    /**
     * Verarbeitet die Haupt-Farbvariable fuer Aktion und Datenempfang.
     */
    private function handleMainColorVariable(mixed $value): bool
    {
        $this->SendDebug('handleColorVariable', 'Color Value: ' . json_encode($value), 0);

        if (\is_int($value)) {
            return $this->handleIntegerColorAction($value);
        }

        if (\is_array($value)) {
            return $this->updateColorVariableFromPayload($value);
        }

        $this->SendDebug('handleColorVariable', 'Ungültiger Wert für color: ' . json_encode($value), 0);
        return false;
    }

    /**
     * Sendet eine Farbe aus der Symcon-Farbauswahl an Zigbee2MQTT.
     */
    private function handleIntegerColorAction(int $value): bool
    {
        if ($this->GetValue('color') === $value) {
            return false;
        }

        return $this->setColor($value, $this->getColorMode());
    }

    /**
     * Aktualisiert die lokale Farbvariable aus einem Zigbee2MQTT-Farbpayload.
     */
    private function updateColorVariableFromPayload(array $value): bool
    {
        $hexValue = $this->getColorIntFromPayload($value);
        if ($hexValue !== null) {
            $this->SetValueDirect('color', $hexValue);
        }

        return true;
    }

    /**
     * Wandelt bekannte Zigbee2MQTT-Farbpayloads in einen Symcon-Integer-Farbwert.
     */
    private function getColorIntFromPayload(array $value): ?int
    {
        $brightness = $value['brightness'] ?? 254;
        $this->SendDebug('handleColorVariable', 'Processing color with brightness: ' . $brightness, 0);

        if (isset($value['color']['x'], $value['color']['y'])) {
            return $this->xyToInt($value['color']['x'], $value['color']['y'], $brightness);
        }

        if (isset($value['x'], $value['y'])) {
            return $this->xyToInt($value['x'], $value['y'], $brightness);
        }

        if (isset($value['hue'], $value['saturation'])) {
            return $this->HSVToInt($value['hue'], $value['saturation'], $brightness);
        }

        return null;
    }

    /**
     * Sendet eine Farbaktion in einem expliziten Farbraum.
     */
    private function handleColorSpaceAction(mixed $value, string $mode, string $z2mMode = 'color'): bool
    {
        $this->SendDebug('handleColorVariable', 'Color mode: ' . $mode . ', Z2M mode: ' . $z2mMode, 0);
        return $this->setColor($value, $mode, $z2mMode);
    }

    /**
     * Sendet eine Kelvin-Farbtemperatur als Zigbee2MQTT-Mired-Wert und aktualisiert die Mired-Variable.
     */
    private function handleColorTemperatureKelvinAction(mixed $value): bool
    {
        if (!$this->HasExposeProperty('color_temp')) {
            $this->SendDebug('handleColorVariable', 'Skip color_temp_kelvin action without color_temp expose support', 0);
            return false;
        }

        $kelvinValue = $this->ClampColorTemperatureKelvinToConfiguredRange((int) $value);
        $convertedValue = $this->convertKelvinToMired($kelvinValue);
        $this->SendDebug('handleColorVariable', \sprintf('Converting %dK to %d Mired', $kelvinValue, $convertedValue), 0);

        if (!$this->SendSetCommand(['color_temp' => $convertedValue])) {
            return false;
        }

        $this->UpdateColorTemperatureLocallyIfNoFeedback($convertedValue, $kelvinValue);
        return true;
    }

    /**
     * Sendet eine Farbtemperaturaktion an Zigbee2MQTT.
     */
    private function handleColorTemperatureAction(mixed $value): bool
    {
        if (!$this->HasExposeProperty('color_temp')) {
            $this->SendDebug('handleColorVariable', 'Skip color_temp action without color_temp expose support', 0);
            return false;
        }

        $convertedValue = $this->convertKelvinToMired($value);
        $this->SendDebug('handleColorVariable', 'Converted Color Temp: ' . $convertedValue, 0);

        if (!$this->SendSetCommand(['color_temp' => $convertedValue])) {
            return false;
        }

        $kelvinValue = $this->convertMiredToKelvin($convertedValue);
        $this->UpdateColorTemperatureLocallyIfNoFeedback($convertedValue, $kelvinValue);

        return true;
    }

    /**
     * Aktualisiert abgeleitete Farbtemperaturwerte nur bei Befehlen ohne Zigbee2MQTT-Rueckmeldung.
     */
    private function UpdateColorTemperatureLocallyIfNoFeedback(int $miredValue, int $kelvinValue): void
    {
        if ($this->ShouldWaitForZigbee2MQTTFeedback('color_temp')) {
            $this->SendDebug('handleColorVariable', 'Farbtemperatur wird erst nach Zigbee2MQTT-Rueckmeldung aktualisiert.', 0);
            return;
        }

        $this->SetValueDirect('color_temp', $miredValue);
        $this->SetValueDirect('color_temp_kelvin', $kelvinValue);
        $this->UpdateColorTemperatureWhiteColorVariable($kelvinValue);
    }

    /**
     * handlePresetVariable
     *
     * Verarbeitet Preset-Variablenaktionen.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Preset-Variable angefordert wird.
     * Sie leitet die Aktion an die Hauptvariable weiter und sendet den entsprechenden Set-Befehl.
     *
     * @param string $ident Der Identifikator der Preset-Variable.
     * @param mixed $value Der Wert, der mit der Preset-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück, wenn die Aktion erfolgreich verarbeitet wurde, andernfalls false.
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \Zigbee2MQTT\ModulBase::convertMiredToKelvin()
     * @see \IPSModule::SendDebug()
     * @see str_replace()
     */
    private function handlePresetVariable(string $ident, mixed $value): bool
    {
        $mainIdent = $this->getPresetMainIdent($ident);
        if ($this->shouldRedirectPresetAction($mainIdent)) {
            $this->SendDebug(__FUNCTION__, 'Preset-Variable wird direkt umgeleitet: ' . $mainIdent, 0);
            return $this->sendPresetAction($ident, $mainIdent, $value);
        }

        $this->SendDebug(__FUNCTION__, 'Aktion über presets erfolgt, Weiterleitung zur eigentlichen Variable: ' . $mainIdent, 0);
        return $this->sendPresetAction($ident, $mainIdent, $value);
    }

    /**
     * Liefert die Hauptvariable zu einer Preset-Variable.
     */
    private function getPresetMainIdent(string $ident): string
    {
        return str_replace('_presets', '', $ident);
    }

    /**
     * Prueft, ob ein Preset laut vordefinierter Konfiguration direkt umgeleitet wird.
     */
    private function shouldRedirectPresetAction(string $mainIdent): bool
    {
        return isset(self::$presetDefinitions[$mainIdent]['redirect']);
    }

    /**
     * Sendet eine Preset-Aktion an die Hauptvariable und aktualisiert beide lokalen Variablen.
     */
    private function sendPresetAction(string $presetIdent, string $mainIdent, mixed $value): bool
    {
        if (!$this->SendSetCommand($this->buildPresetActionPayload($mainIdent, $value))) {
            return false;
        }

        $this->SetValueDirect($presetIdent, $value);
        $this->UpdateLocalValueAfterSetIfNoFeedback($mainIdent, $value, __FUNCTION__);
        return true;
    }

    /**
     * Baut das MQTT-Payload fuer eine Preset-Aktion.
     */
    private function buildPresetActionPayload(string $mainIdent, mixed $value): array
    {
        if ($this->isCompositeKey($mainIdent)) {
            return $this->buildNestedPayload($mainIdent, $value);
        }

        return [$mainIdent => $value];
    }

    /**
     * handleStringVariableNoResponse
     *
     * Verarbeitet String-Variablen, die keine Rückmeldung von Zigbee2MQTT erfordern.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine String-Variable angefordert wird,
     * die keine Rückmeldung von Zigbee2MQTT erfordert. Sie sendet den entsprechenden Set-Befehl
     * und aktualisiert die Variable direkt, wenn der Set-Befehl erfolgreich gesendet wurde.
     *
     * @param string $ident Der Identifikator der String-Variablen.
     * @param string $value Der Wert, der mit der String-Variablen-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück wenn der Set-Befehl abgesetzt wurde, sonder false.
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     */
    private function handleStringVariableNoResponse(string $ident, string $value): bool
    {
        $this->SendDebug(__FUNCTION__, 'Behandlung String ohne Rückmeldung: ' . $ident, 0);
        $payload = [$ident => $value];
        if ($this->SendSetCommand($payload)) {
            $this->SetValue($ident, $value);
            return true;
        }
        return false;
    }

    /**
     * adjustValueByType
     *
     * Passt den Wert basierend auf dem Variablentyp an.
     * Diese Methode konvertiert den übergebenen Wert in den entsprechenden Typ der Variable.
     *
     * Spezielle Behandlungen:
     * - Bei child_lock: 'LOCK' wird zu true, 'UNLOCK' zu false konvertiert
     * - Boolesche Werte: 'ON' wird zu true, 'OFF' zu false konvertiert
     *
     * @param array $variableObject Ein Array von IPS_GetVariable() mit folgenden Schlüsseln:
     *                             - 'VariableType': int - Der Typ der Variable (0=Bool, 1=Int, 2=Float, 3=String)
     *                             - 'VariableID': int - Die ID der Variable
     * @param mixed $value Der Wert, der angepasst werden soll
     *
     * @return mixed Der konvertierte Wert:
     *               - bool für VARIABLETYPE_BOOLEAN (0)
     *               - int für VARIABLETYPE_INTEGER (1)
     *               - float für VARIABLETYPE_FLOAT (2)
     *               - string für VARIABLETYPE_STRING (3)
     *               - original $value bei unbekanntem Typ
     *
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_bool()
     * @see is_string()
     * @see strtoupper()
     * @see IPS_GetObject()
     * @see VARIABLETYPE_BOOLEAN
     */
    private function adjustValueByType(array $variableObject, mixed $value): mixed
    {
        $varType = $variableObject['VariableType'];
        $varID = $variableObject['VariableID'];
        $ident = IPS_GetObject($varID)['ObjectIdent'];

        $this->SendDebug(__FUNCTION__, 'Variable ID: ' . $varID . ', Typ: ' . $varType . ', Ursprünglicher Wert: ' . json_encode($value), 0);

        switch ($varType) {
            case VARIABLETYPE_BOOLEAN:
                return $this->adjustBooleanValueByType($ident, $value);
            case VARIABLETYPE_INTEGER:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu int: ' . (int) $value, 0);
                return (int) $value;
            case VARIABLETYPE_FLOAT:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu float: ' . (float) $value, 0);
                return (float) $value;
            case VARIABLETYPE_STRING:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu string: ' . (string) $value, 0);
                return (string) $value;
            default:
                $this->SendDebug(__FUNCTION__, 'Unbekannter Variablentyp für ID ' . $varID . ', Wert: ' . json_encode($value), 0);
                return $value;
        }
    }

    /**
     * Konvertiert empfangene Werte passend fuer boolesche IPS-Variablen.
     */
    private function adjustBooleanValueByType(string $ident, mixed $value): bool
    {
        if (\is_bool($value)) {
            $this->SendDebug('adjustValueByType', 'Wert ist bereits bool: ' . json_encode($value), 0);
            return $value;
        }

        if (\is_string($value)) {
            $exposeValue = $this->getBooleanValueFromExpose($ident, $value);
            if ($exposeValue !== null) {
                return $exposeValue;
            }

            $knownValue = $this->getBooleanValueFromKnownString($value);
            if ($knownValue !== null) {
                return $knownValue;
            }

            $this->SendDebug('adjustValueByType', 'Unbekannter boolescher Stringwert für ' . $ident . ': ' . json_encode($value) . ' -> false', 0);
            return false;
        }

        return (bool) $value;
    }

    /**
     * Nutzt value_on/value_off aus dem Expose, wenn vorhanden.
     */
    private function getBooleanValueFromExpose(string $ident, string $value): ?bool
    {
        $feature = $this->findExposeFeatureByProperty($ident);
        if (
            $feature === null ||
            ($feature['type'] ?? '') !== 'binary' ||
            !isset($feature['value_on'], $feature['value_off'])
        ) {
            return null;
        }

        if ($value === $feature['value_on']) {
            return true;
        }
        if ($value === $feature['value_off']) {
            return false;
        }

        return null;
    }

    /**
     * Interpretiert bekannte Zigbee2MQTT-/Symcon-Textwerte als Boolean.
     */
    private function getBooleanValueFromKnownString(string $value): ?bool
    {
        $normalizedValue = strtoupper(trim($value, " \t\n\r\0\x0B\"'"));
        if (\in_array($normalizedValue, ['ON', 'TRUE', 'YES', '1', 'LOCK', 'OPEN'], true)) {
            return true;
        }
        if (\in_array($normalizedValue, ['OFF', 'FALSE', 'NO', '0', 'UNLOCK', 'CLOSE', 'CLOSED'], true)) {
            return false;
        }

        return null;
    }

    // Farbmanagement

    /**
     * setColor
     *
     * Setzt die Farbe des Geräts basierend auf dem angegebenen Farbmodus.
     *
     * Diese Methode unterstützt verschiedene Farbmodi und konvertiert die Farbe in das entsprechende Format,
     * bevor sie an das Gerät gesendet wird. Unterstützte Modi sind:
     * - **cie**: Konvertiert RGB in den XY-Farbraum (CIE 1931).
     * - **hs**: Verwendet den Hue-Saturation-Modus (HS), um die Farbe zu setzen.
     * - **hsl**: Nutzt den Farbton, Sättigung und Helligkeit (HSL), um die Farbe zu setzen.
     * - **hsv**: Nutzt den Farbton, Sättigung und den Wert (HSV), um die Farbe zu setzen.
     *
     * @param int $color Der Farbwert in Hexadezimal- oder RGB-Format.
     *                   Die Farbe wird intern in verschiedene Farbmodelle umgerechnet.
     * @param string $mode Der Farbmodus, der verwendet werden soll. Unterstützte Werte:
     *                     - 'cie': Konvertiert die RGB-Werte in den XY-Farbraum.
     *                     - 'hs': Verwendet den Hue-Saturation-Modus.
     *                     - 'hsl': Nutzt den HSL-Modus für die Umrechnung.
     *                     - 'hsv': Nutzt den HSV-Modus für die Umrechnung.
     * @param string $Z2MMode Der Zigbee2MQTT-Modus, standardmäßig 'color'. Kann auch 'color_rgb' sein.
     *                        - 'color': Setzt den Farbwert im XY-Farbraum.
     *                        - 'color_rgb': Setzt den Farbwert im RGB-Modus (nur für 'cie' relevant).
     * @param int|null $TransitionTime Optionale Übergangszeit in Sekunden.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException Wenn der Modus ungültig ist.
     *
     * Beispiel:
     * ```php
     * // Setze eine Farbe im HSL-Modus.
     * $this->setColor(0xFF5733, 'hsl', 'color');
     *
     * // Setze eine Farbe im HSV-Modus.
     * $this->setColor(0x4287f5, 'hsv', 'color');
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::IntToRGB()
     * @see \Zigbee2MQTT\ModulBase::RGBToXy()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSB()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSL()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSV()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     */
    private function setColor(int $color, string $mode, string $Z2MMode = 'color', ?int $TransitionTime = null): bool
    {
        $Payload = match ($mode) {
            'cie' => function () use ($color, $Z2MMode)
            {
                $RGB = $this->IntToRGB($color);
                $cie = $this->RGBToXy($RGB);

                if ($Z2MMode === 'color') {
                    // Entferne 'bri' aus dem 'color'-Objekt und füge es separat als 'brightness' hinzu
                    $brightness = $cie['bri'];
                    unset($cie['bri']);
                    return ['color' => $cie, 'brightness' => $brightness];
                } elseif ($Z2MMode === 'color_rgb') {
                    return ['color_rgb' => $cie];
                }
            },
            'hs' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSB = $this->RGBToHSB($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSB Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSB['hue'],
                            'saturation' => $HSB['saturation'],
                        ],
                        'brightness' => $HSB['brightness']
                    ];
                } else {
                    return null;
                }
            },
            'hsl' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSL = $this->RGBToHSL($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSL Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSL['hue'],
                            'saturation' => $HSL['saturation'],
                            'lightness'  => $HSL['lightness']
                        ]
                    ];
                } else {
                    return null;
                }
            },
            'hsv' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSV = $this->RGBToHSV($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSV Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSV['hue'],
                            'saturation' => $HSV['saturation'],
                        ],
                        'brightness' => $HSV['brightness']
                    ];
                } else {
                    return null;
                }
            },
            default => throw new \InvalidArgumentException('Invalid color mode: ' . $mode),
        };

        $result = $Payload();
        if ($result !== null) {

            if ($result === false) {
                return true; // Wert hat sich nicht geändert
            }
            if ($TransitionTime !== null) {
                $result['transition'] = $TransitionTime;
            }
            return $this->SendSetCommand($result);
        }
        return false;
    }

    // Spezialvariablen & Konvertierung

    /**
     * processSpecialVariable
     *
     * Verarbeitet spezielle Variablen mit besonderen Anforderungen
     *
     * Verarbeitungsschritte:
     * 1. Prüft ob Variable in specialVariables definiert
     * 2. Konvertiert Property zu Ident und Label
     * 3. Registriert Variable falls nicht vorhanden
     * 4. Passt Wert entsprechend Variablentyp an
     * 5. Setzt Wert mit Debug-Ausgaben
     *
     * @param string $key Name der zu verarbeitenden Property
     * @param mixed $value Zu setzender Wert
     *                    Kann sein:
     *                    - String: Direkter Wert
     *                    - Array: Wird konvertiert
     *                    - Bool: Wird angepasst
     *                    - Int/Float: Wird skaliert
     *
     * @return bool True wenn Variable verarbeitet wurde,
     *              False wenn keine Spezialvariable
     *
     * Beispiel:
     * ```php
     * // Verarbeitet Farbtemperatur
     * $this->processSpecialVariable("color_temp", 4000);
     *
     * // Verarbeitet RGB-Farbe
     * $this->processSpecialVariable("color", ["r" => 255, "g" => 0, "b" => 0]);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::processPayload() Ruft diese Methode auf
     * @see \Zigbee2MQTT\ModulBase::processVariable() Ruft diese Methode auf
     *
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::adjustSpecialValue()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see sprintf()
     * @see gettype()
     */
    private function processSpecialVariable(string $key, mixed $value): bool
    {
        if (!isset(self::$specialVariables[$key])) {
            return false;
        }

        if (!$this->ensureSpecialVariable($key)) {
            return true;
        }

        $adjustedValue = $this->adjustSpecialValue($key, $value);
        $this->storeSpecialVariableValue($key, $adjustedValue);
        return true;
    }

    /**
     * Stellt sicher, dass eine Spezialvariable vorhanden ist.
     */
    private function ensureSpecialVariable(string $ident): bool
    {
        return (bool) $this->getOrRegisterVariable($ident, ['property' => $ident], $this->convertLabelToName($ident));
    }

    /**
     * Speichert den angepassten Wert einer Spezialvariable.
     */
    private function storeSpecialVariableValue(string $ident, mixed $adjustedValue): void
    {
        $debugValue = $this->formatPayloadDebugValue($adjustedValue);
        $this->SendDebug('processSpecialVariable' . ' :: ' . __LINE__ . ' :: ', $ident . ' verarbeitet: ' . $ident . ' => ' . $debugValue, 0);

        $this->SetValueDirect($ident, $adjustedValue);
        $this->SendDebug(
            'processSpecialVariable',
            \sprintf('SetValueDirect aufgerufen für %s mit Wert: %s (Typ: %s)', $ident, $debugValue, gettype($adjustedValue)),
            0
        );
        $this->updatePresetVariable($ident, $adjustedValue);
    }

    /**
     * adjustSpecialValue
     *
     * Passt den Wert spezieller Variablen entsprechend ihrer Anforderungen an
     *
     * Verarbeitungsschritte:
     * 1. Debug-Ausgabe des Eingangswerts
     * 2. Spezifische Konvertierung je nach Variablentyp
     * 3. Debug-Ausgabe des konvertierten Werts
     *
     * Unterstützte Variablentypen:
     * - last_seen: Konvertiert Millisekunden zu Sekunden
     * - color_mode: Wandelt Farbmodus in Großbuchstaben (hs->HS, xy->XY)
     * - color_temp_kelvin: Rechnet Kelvin in Mired um (1.000.000/K)
     *
     * @param string $ident Identifikator der Variable (last_seen, color_mode, color_temp_kelvin)
     * @param mixed $value Zu konvertierender Wert
     *                    - LastSeen: Integer (Millisekunden)
     *                    - ColorMode: String (hs, xy)
     *                    - ColorTempKelvin: Integer (2000-6500K)
     *
     * @return mixed Konvertierter Wert
     *               - LastSeen: Integer (Sekunden)
     *               - ColorMode: String (HS, XY)
     *               - ColorTempKelvin: String (Mired)
     *               - Default: Originalwert
     *
     * Beispiel:
     * ```php
     * // LastSeen konvertieren
     * $this->adjustSpecialValue("last_seen", 1600000000000); // Returns: 1600000000
     *
     * // ColorMode konvertieren
     * $this->adjustSpecialValue("color_mode", "hs"); // Returns: "HS"
     *
     * // Kelvin zu Mired
     * $this->adjustSpecialValue("color_temp_kelvin", 4000); // Returns: "250"
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::convertKelvinToMired()
     * @see \Zigbee2MQTT\ModulBase::normalizeValueToRange()
     * @see \Zigbee2MQTT\ModulBase::convertMillivoltToVolt()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see intdiv()
     * @see strtoupper()
     */
    private function adjustSpecialValue(string $ident, mixed $value): mixed
    {
        $debugValue = \is_array($value) ? json_encode($value) : $value;
        $this->SendDebug(__FUNCTION__, 'Processing special variable: ' . $ident . ' with value: ' . $debugValue, 0);
        switch ($ident) {
            case 'last_seen':
                // Umrechnung von Millisekunden auf Sekunden
                // $value nur mit Gleitkommazahlen Division durchführen um 32Bit-Systeme zu unterstützen
                // Anschließend zu INT casten.
                $adjustedValue = (int) ($value / 1000);
                $this->SendDebug(__FUNCTION__, 'Converted value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'color_mode':
                // Konvertierung von 'hs' zu 'HS' und 'xy' zu 'XY'
                $adjustedValue = strtoupper($value);
                $this->SendDebug(__FUNCTION__, 'Converted value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'color_temp_kelvin':
                // Umrechnung von Kelvin zu Mired
                return $this->convertKelvinToMired($value);
            case 'brightness':
                // Konvertiere Gerätewert in Prozentwert (0-100)
                $adjustedValue = $this->normalizeValueToRange($value, false);
                $this->SendDebug(__FUNCTION__, 'Converted brightness value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'voltage':
                // Konvertiere mV zu V
                $adjustedValue = self::convertMillivoltToVolt($value);
                $this->SendDebug(__FUNCTION__, 'Converted voltage value: ' . $adjustedValue, 0);
                return $adjustedValue;
            default:
                return $value;
        }
    }

    /**
     * convertMillivoltToVolt
     *
     * Konvertiert Millivolt in Volt, wenn der Wert größer als 400 ist.
     *
     * @param float $value Der zu konvertierende Wert in Millivolt.
     * @return float Der konvertierte Wert in Volt.
     */
    private static function convertMillivoltToVolt(float $value): float
    {
        if ($value > 400) { // Werte über 400 sind in mV
            return $value * 0.001; // Umrechnung von mV in V mit Faktor 0.001
        }
        return $value; // Werte <= 400 sind bereits in V
    }

    /**
     * convertOnOffValue
     *
     * Konvertiert Werte zwischen ON/OFF und bool.
     * Zentrale Konvertierungsfunktion für State-Handler.
     *
     * @param mixed $value Zu konvertierender Wert:
     *                    - String: "ON"/"OFF" wird zu true/false
     *                    - Bool: true/false wird zu "ON"/"OFF"
     *                    - Andere: Direkte Bool-Konvertierung
     * @param bool $toBool True wenn Konvertierung zu Boolean, False wenn zu ON/OFF String
     *
     * @return mixed Konvertierter Wert:
     *              - Bei toBool=true: Boolean true/false
     *              - Bei toBool=false: String "ON"/"OFF"
     *
     * @see \Zigbee2MQTT\ModulBase::handleStateVariable() Hauptnutzer der Funktion
     * @see \Zigbee2MQTT\ModulBase::processSpecialCases() Weitere Nutzung
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable() Weitere Nutzung
     * @see is_string() Prüft ob Wert ein String ist
     * @see strtoupper() Konvertiert String zu Großbuchstaben
     * @see bool() Boolean Typkonvertierung
     */
    private function convertOnOffValue($value, bool $toBool = true): mixed
    {
        if ($toBool) {
            if (\is_string($value)) {
                return strtoupper($value) === 'ON';
            }
            return (bool) $value;
        } else {
            return $value ? 'ON' : 'OFF';
        }
    }

    /**
     * convertLabelToName
     *
     * Konvertiert ein Label in einen formatierten Namen mit Großbuchstaben am Wortanfang
     * und behält bestimmte Abkürzungen in Großbuchstaben.
     *
     * @param string $label Das zu formatierende Label
     * @return string Das formatierte Label
     *
     * @see \Zigbee2MQTT\ModulBase::isValueInLocaleJson()
     * @see \Zigbee2MQTT\ModulBase::addValueToTranslationsBuffer()
     * @see \IPSModule::SendDebug()
     * @see str_replace()
     * @see str_ireplace()
     * @see strtolower()
     * @see ucwords()
     * @see ucfirst()
     */
    private function convertLabelToName(string $label): string
    {
        // Liste von Abkürzungen die in Großbuchstaben bleiben sollen
        $upperCaseWords = ['HS', 'RGB', 'XY', 'HSV', 'HSL', 'LED'];
        $this->SendDebug(__FUNCTION__, 'Initial Label: ' . $label, 0);

        // Alle Unterstriche (egal ob einfach oder mehrfach) durch ein einzelnes Leerzeichen ersetzen
        $label = preg_replace('/_+/', ' ', $label);
        $this->SendDebug(__FUNCTION__, 'After replacing underscores with spaces: ' . $label, 0);

        // Konvertiere jeden Wortanfang in Großbuchstaben
        $label = ucwords($label);

        // Ersetze bekannte Abkürzungen durch ihre Großbuchstaben-Version
        foreach ($upperCaseWords as $upperWord) {
            $label = str_ireplace(
                [" $upperWord", ' ' . ucfirst(strtolower($upperWord))],
                " $upperWord",
                $label
            );
        }

        $this->SendDebug(__FUNCTION__, 'Converted Label: ' . $label, 0);

        // Prüfe, ob der Name in der locale.json vorhanden ist
        // Füge den Namen zum missingTranslations Buffer hinzu
        $this->isValueInLocaleJson($label, 'label');
        return $label;
    }

    // Variablenmetadaten

    /**
     * Ergaenzt fehlende Expose-Kennungen typunabhaengig.
     *
     * Von Zigbee2MQTT berechnete oder nachgelieferte Werte koennen nur
     * `property` oder nur `name` enthalten. Nach der Normalisierung koennen
     * alle Darstellungs- und Variablenpfade beide Schluessel sicher verwenden.
     *
     * @param array $feature Expose- oder Payload-Definition.
     *
     * @return array Definition mit ergaenzter Property und ergaenztem Namen.
     */
    private static function normalizeExposeFeatureIdentity(array $feature): array
    {
        $property = trim((string) ($feature['property'] ?? ''));
        $name = trim((string) ($feature['name'] ?? ''));
        if ($property === '' && $name !== '') {
            $feature['property'] = $name;
            $property = $name;
        }
        if ($name === '' && $property !== '') {
            $feature['name'] = $property;
        }

        return $feature;
    }

    /**
     * getVariableTypeFromFeature
     *
     * Bestimmt den Variablentyp basierend auf verschiedenen Kriterien.
     *
     * @param string $type Der Expose-Typ (z.B. 'binary', 'numeric', 'enum', 'string', 'text', 'composite')
     * @param string $feature Name der Eigenschaft (z.B. 'state', 'brightness', 'temperature')
     * @param string $unit Optional - Die Einheit des Wertes (z.B. '°C', 'W', '%')
     * @param float $value_step Optional - Die Schrittweite für numerische Werte (Standard: 1.0)
     * @param string|null $groupType Optional - Gruppentyp für spezielle Mappings
     *
     * @return string Der ermittelte Variablentyp ('bool', 'int', 'float', 'string')
     *
     * @note Für 'numeric' Typen gilt folgende Logik:
     *       - Returns 'float' wenn:
     *         * Die Einheit in FLOAT_UNITS definiert ist (z.B. 'W', '°C', 'V')
     *         * value_step keine ganze Zahl ist (z.B. 0.5)
     *       - Returns 'int' wenn:
     *         * Keine der float-Bedingungen zutrifft
     *
     * Beispiel:
     * ```php
     * // Float Beispiel (Temperatur)
     * $type = $this->getVariableTypeFromFeature('numeric', 'temperature', '°C', 0.5);
     * // Ergebnis: 'float'
     *
     * // Integer Beispiel (Helligkeit)
     * $type = $this->getVariableTypeFromFeature('numeric', 'brightness', '%', 1.0);
     * // Ergebnis: 'int'
     * ```
     *
     * @see \IPSModule::SendDebug()
     * @see is_string()
     * @see str_replace()
     * @see in_array()
     * @see fmod()
     */
    private function getVariableTypeFromFeature(string $type, string $feature, string $unit = '', float $value_step = 1.0, ?string $groupType = null): string
    {
        // Prüfen, ob ein spezifisches Mapping existiert.
        // Wichtig: Nicht nur auf den Feature-Namen matchen, da z.B. "position"
        // je nach Gerätetyp numerisch (Cover) oder enum (Kontakt) sein kann.
        foreach (self::$VariableTypeMappings as $entry) {
            if (($entry['feature'] ?? '') !== $feature) {
                continue;
            }

            $typeMatches = !isset($entry['type']) || $entry['type'] === '' || $entry['type'] === $type;
            $groupMatches = !isset($entry['group_type']) || $entry['group_type'] === '' || $entry['group_type'] === $groupType;

            if (!$typeMatches || !$groupMatches) {
                continue;
            }

            $this->SendDebug(__FUNCTION__, 'Found specific mapping: ' . json_encode($entry), 0);

            switch ($entry['variableType']) {
                case VARIABLETYPE_BOOLEAN:
                    return 'bool';
                case VARIABLETYPE_INTEGER:
                    return 'int';
                case VARIABLETYPE_FLOAT:
                    return 'float';
                case VARIABLETYPE_STRING:
                    return 'string';
            }
        }

        // Prüfen, ob die Einheit in den Float-Einheiten enthalten ist
        if (!empty($unit) && \is_string($unit)) {
            // Debug der Original-Einheit
            $this->SendDebug(__FUNCTION__, 'Original unit: ' . bin2hex($unit), 0);

            // Unit kommt aus JSON und ist UTF-8; keine AUTO-Rekonvertierung durchführen.
            $unitTrimmed = str_replace(' ', '', $unit);

            // Erweiterte Debug-Ausgaben
            $this->SendDebug(__FUNCTION__, 'Unit normalized (hex): ' . bin2hex($unitTrimmed), 0);
            $this->SendDebug(__FUNCTION__, 'Unit normalized (readable): ' . $unitTrimmed, 0);
            $this->SendDebug(__FUNCTION__, 'FLOAT_UNITS content: ' . json_encode(self::FLOAT_UNITS), 0);

            if (\in_array($unitTrimmed, self::FLOAT_UNITS, true)) {
                // Wenn unit in FLOAT_UNITS und step eine Ganzzahl ist -> int
                if ($value_step != 1.0 && fmod($value_step, 1) === 0.0) {
                    $this->SendDebug(__FUNCTION__, 'Unit in FLOAT_UNITS but step is integer, returning int', 0);
                    return 'int';
                }
                // Sonst float
                return 'float';
            }
        }

        // Wenn unit nicht in FLOAT_UNITS, aber step eine Dezimalzahl
        if ($value_step != 1.0 && fmod($value_step, 1) !== 0.0) {
            $this->SendDebug(__FUNCTION__, 'Value step is not an integer, returning float', 0);
            return 'float';
        }

        // Allgemeines Typ-Mapping
        $typeMapping = [
            'binary'    => 'bool',
            'numeric'   => 'int',    // Standardmäßig 'numeric' auf 'int' abbilden
            'enum'      => 'string',
            'string'    => 'string',
            'text'      => 'string',
            'composite' => 'composite',
            // Weitere Mapping-Optionen hinzufügen
        ];

        $this->SendDebug(__FUNCTION__, 'Returning type from typeMapping: ' . ($typeMapping[$type] ?? 'string'), 0);
        return $typeMapping[$type] ?? 'string';
    }

    /**
     * checkExposeAttribute
     *
     * @return bool false wenn UpdateDeviceInfo ausgeführt wurde, sonst true
     */
    private function checkExposeAttribute(): bool
    {
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);

        // Erst prüfen ob MQTTTopic gesetzt ist
        if (empty($mqttTopic)) {
            $this->SendDebug(__FUNCTION__, 'MQTTTopic nicht gesetzt, überspringe Attribut Prüfung', 0);
            return true;
        }

        // Prüfe ob Expose-Attribute existiert und Daten enthält
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        if (\count($exposes)) {
            return true;
        }

        $this->SendDebug(__FUNCTION__, 'Expose-Attribute nicht gefunden für Instance: ' . $this->InstanceID, 0);

        if (!$this->HasActiveParent()) {
            $this->SendDebug(__FUNCTION__, 'Parent nicht aktiv, überspringe UpdateDeviceInfo', 0);
            return true;
        }

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return true;
        }

        $this->SendDebug(__FUNCTION__, 'Starte UpdateDeviceInfo für Topic: ' . $mqttTopic, 0);
        if (!$this->UpdateDeviceInfo()) {
            $this->SendDebug(__FUNCTION__, 'UpdateDeviceInfo fehlgeschlagen', 0);
        }
        return false;
    }

    /**
     * getKnownVariables
     *
     * Lädt und verarbeitet bekannte Variablen aus dem Exposes-Attribut.
     *
     * Die Methode extrahiert alle Features aus den gespeicherten Exposes und erstellt daraus ein Array von bekannten Variablen.
     *
     * Der Prozess beinhaltet:
     * - Laden der Exposes aus dem Instanzattribut
     * - Extraktion der Features aus den Exposes
     * - Filterung nach Features mit 'property'-Attribut
     * - Normalisierung der Feature-Namen (Kleinbuchstaben, getrimmt)
     *
     * @internal Diese Methode wird intern vom Modul verwendet
     *
     * @return array Ein assoziatives Array mit bekannten Variablen, wobei der Key der normalisierte Property-Name ist
     *               und der Value die komplette Feature-Definition enthält.
     *               Format: ['property_name' => ['property' => 'name', ...]]
     *               Leeres Array wenn keine Variablen gefunden wurden.
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable() Verwendet die zurückgegebenen Variablen zur Registrierung, über
     * @see \Zigbee2MQTT\ModulBase::processVariable()
     * @see \IPSModule::SendDebug()
     * @see array_map()
     * @see array_merge()
     * @see array_filter()
     * @see trim()
     * @see strtolower()
     */
    private function getKnownVariables(): array
    {
        $data = array_values($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES));
        if (!\count($data)) {
            $this->SendDebug(__FUNCTION__, 'Fehlende exposes oder features.', 0);
            return [];
        }

        $filteredFeatures = [];
        foreach ($data as $expose) {
            if (\is_array($expose)) {
                $this->CollectKnownVariableFeatures($expose, $filteredFeatures);
            }
        }

        $knownVariables = [];
        foreach ($filteredFeatures as $feature) {
            $variableName = trim(strtolower($feature['property']));
            $knownVariables[$variableName] = $feature;
            if ($variableName == 'occupancy') {
                $knownVariables['no_occupancy_since'] = [
                    'property'=> 'no_occupancy_since'
                ];
            }
        }

        $this->SendDebug(__FUNCTION__ . ' Known Variables Array:', json_encode($knownVariables), 0);

        return $knownVariables;
    }

    /**
     * Sammelt bekannte Expose-Features mit demselben Ident, der spaeter fuer Variablen genutzt wird.
     */
    private function CollectKnownVariableFeatures(array $feature, array &$features): void
    {
        if (isset($feature['color_mode'])) {
            return;
        }

        if ($this->IsExposeColorComposite($feature)) {
            $features[] = $feature;
            return;
        }

        if ($this->IsExposeCompositeContainer($feature)) {
            $parentIdent = $this->NormalizeVariableIdent((string) ($feature['property'] ?? ''));
            $parentLabel = (string) ($feature['label'] ?? $this->FormatVariableCatalogLabel($parentIdent));

            foreach ($feature['features'] as $subFeature) {
                if (\is_array($subFeature)) {
                    $this->CollectKnownVariableFeatures(
                        $this->BuildCompositeSubFeature($subFeature, $parentIdent, $parentLabel),
                        $features
                    );
                }
            }

            return;
        }

        if (isset($feature['property'])) {
            if ($feature['property'] === 'icon') {
                $this->SendDebug(__FUNCTION__, 'Icon-Property übersprungen: ' . json_encode($feature), 0);
                return;
            }
            if (strpos($feature['property'], 'Icon') !== false) {
                $this->SendDebug(__FUNCTION__, 'Icon im Namen gefunden - übersprungen: ' . json_encode($feature), 0);
                return;
            }
            $features[] = $feature;
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return;
        }

        foreach ($feature['features'] as $subFeature) {
            if (\is_array($subFeature)) {
                $this->CollectKnownVariableFeatures($subFeature, $features);
            }
        }
    }

    /**
     * registerVariable
     *
     * Registriert eine Variable basierend auf den Feature-Informationen
     *
     * @param array|string $feature Feature-Information als Array oder Feature-ID als String
     *                             Array-Format:
     *                             - 'property': (string) Identifikator der Variable
     *                             - 'type': (string) Datentyp (numeric, binary, enum, etc.)
     *                             - 'unit': (string, optional) Einheit der Variable
     *                             - 'value_step': (float, optional) Schrittweite für numerische Werte
     *                             - 'features': (array, optional) Sub-Features für composite Variablen
     *                             - 'presets': (array, optional) Voreingestellte Werte
     *                             - 'access': (int, optional) Zugriffsrechte (0b001=read, 0b010=write, 0b100=notify)
     *                             - 'color_mode': (bool, optional) Für Farbvariablen
     * @param string|null $exposeType Optional, überschreibt den Feature-Typ
     *
     * @return void
     *
     * @throws \Exception Bei ungültigen Feature-Informationen
     *
     * Beispiele:
     * ```php
     * // Einfache Variable
     * $this->registerVariable(['property' => 'state', 'type' => 'binary']);
     *
     * // Composite Variable (z.B. weekly_schedule)
     * $this->registerVariable([
     *     'property' => 'weekly_schedule',
     *     'type' => 'composite',
     *     'features' => [
     *         ['property' => 'monday', 'type' => 'string']
     *     ]
     * ]);
     *
     * // Variable mit Presets
     * $this->registerVariable([
     *     'property' => 'mode',
     *     'type' => 'enum',
     *     'presets' => ['auto', 'manual']
     * ]);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::getStateConfiguration()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::registerSpecialVariable()
     * @see \Zigbee2MQTT\ModulBase::getVariableTypeFromFeature()
     * @see \Zigbee2MQTT\ModulBase::registerColorVariable()
     * @see \Zigbee2MQTT\ModulBase::registerPresetVariables()
     * @see \IPSModule::RegisterVariableBoolean()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableString()
     * @see \IPSModule::Translate()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::GetIDForIdent()
     * @see is_array()
     * @see json_encode()
     * @see ucfirst()
     * @see str_replace()
     */
    private function registerVariable(mixed $feature, ?string $exposeType = null): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        if (\is_array($feature)) {
            $feature = self::normalizeExposeFeatureIdentity($feature);
        }
        $featureProperty = \is_array($feature) ? (string) ($feature['property'] ?? '') : (string) $feature;

        // Frühe Validierung der Property
        if (empty($featureProperty)) {
            $this->SendDebug(__FUNCTION__, 'Error: Empty property/identifier provided', 0);
            return;
        }

        $shouldCheckVariableCreation = !\is_array($feature)
            || (!isset($feature['color_mode']) && !$this->IsExposeCompositeContainer($feature));
        if ($shouldCheckVariableCreation) {
            if (!$this->CanCreateVariable($featureProperty, \is_array($feature) ? $feature : null, 'expose')) {
                return;
            }
        }

        $this->SendDebug(__FUNCTION__ . ' Registriere Variable für Property: ', $featureProperty, 0);

        if (\is_array($feature) && $this->registerWriteOnlySingleEnumCommand($feature, $featureProperty)) {
            return;
        }

        if ($this->registerStateFeatureVariable($featureProperty, \is_array($feature) ? $feature : null)) {
            return;
        }

        if (!\is_array($feature)) {
            $this->SendDebug(__FUNCTION__, 'Error: Feature details missing for property: ' . $featureProperty, 0);
            return;
        }

        if ($this->registerSpecialFeatureVariable($feature)) {
            return;
        }

        // Setze den Typ auf den übergebenen Expose-Typ, falls vorhanden
        if ($exposeType !== null) {
            $feature['type'] = $exposeType;
        }

        // Berücksichtige den Gruppentyp, falls vorhanden, ohne den ursprünglichen Typ zu überschreiben
        $groupType = $feature['group_type'] ?? null;

        $this->SendDebug(__FUNCTION__ . ' :: Registering Feature', json_encode($feature), 0);

        $type = $feature['type'];
        $property = $featureProperty; // Bereits validiert
        $unit = $feature['unit'] ?? '';
        $step = isset($feature['value_step']) ? (float) $feature['value_step'] : 1.0;

        // Bestimmen des Variablentyps basierend auf Typ, Feature und Einheit
        $variableType = $this->getVariableTypeFromFeature($type, $property, $unit, $step, $groupType);

        $ident = str_replace('&', '_and_', $property);
        $profileOrPresentation = $this->BuildFeaturePresentation($feature, \is_string($groupType) ? $groupType : null, '') ?? '';
        if (!$this->registerFeatureVariableByType($feature, $ident, $property, $variableType, $profileOrPresentation, $exposeType)) {
            return;
        }

        // Zentrale EnableAction-Prüfung für die Hauptvariable, außer bei composite
        if ($variableType != 'composite') {
            $this->checkAndEnableAction($ident, $feature);
        }

        $this->registerColorTemperatureKelvinVariable($property, $feature);
        $this->registerFeaturePresetVariables($feature, $property, $type, $unit, $step, $groupType);
        return;
    }

    /**
     * Registriert ein write-only Enum mit genau einem Wert als ausloesbaren Schalter.
     *
     * Solche Exposes liefern keinen Rueckkanal, sollen in Symcon aber trotzdem als
     * Aktion verfuegbar sein.
     *
     * @param array $feature Expose-Feature.
     * @param string $featureProperty Urspruengliche Property.
     *
     * @return bool True, wenn das Feature verarbeitet wurde.
     */
    private function registerWriteOnlySingleEnumCommand(array $feature, string $featureProperty): bool
    {
        if (!$this->isWriteOnlySingleEnumCommand($feature)) {
            return false;
        }

        $ident = str_replace('&', '_and_', $featureProperty);
        $this->RegisterVariableBoolean($ident, $this->Translate($this->convertLabelToName($featureProperty)), '');
        $this->MarkVariableCreated($ident);
        $this->checkAndEnableAction($ident, $feature, true);
        return true;
    }

    /**
     * Registriert bekannte State-Features mit eigener State-Konfiguration.
     *
     * @param string $featureProperty Feature-Property.
     * @param array|null $feature Optionale Expose-Daten fuer Access-Informationen.
     *
     * @return bool True, wenn eine State-Konfiguration gefunden und verarbeitet wurde.
     */
    private function registerStateFeatureVariable(string $featureProperty, ?array $feature): bool
    {
        $stateConfig = $this->getStateConfiguration($featureProperty, $feature);
        if ($stateConfig === null) {
            return false;
        }

        $formattedLabel = $this->convertLabelToName($featureProperty);
        $profileOrPresentation = $this->BuildStatePresentation($stateConfig, $feature);
        $this->RecordLegacyProfilePresentationReplacement((string) $stateConfig['ident'], $profileOrPresentation);
        switch ($stateConfig['dataType']) {
            case VARIABLETYPE_BOOLEAN:
                $this->RegisterVariableBoolean(
                    $stateConfig['ident'],
                    $this->Translate($formattedLabel),
                    $profileOrPresentation
                );
                $this->MarkVariableCreated($stateConfig['ident']);
                break;
            case VARIABLETYPE_STRING:
                $this->RegisterVariableString(
                    $stateConfig['ident'],
                    $this->Translate($formattedLabel),
                    $profileOrPresentation
                );
                $this->MarkVariableCreated($stateConfig['ident']);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'Unsupported state dataType: ' . $stateConfig['dataType'], 0);
                return true;
        }

        $this->enableStateFeatureAction($stateConfig, $feature);
        return true;
    }

    /**
     * Erstellt fuer State-Enums eine native Darstellung.
     *
     * @param array $stateConfig State-Konfiguration.
     * @param array|null $feature Expose-Daten.
     * @return string|array Leerer String oder native Variablendarstellung.
     */
    private function BuildStatePresentation(array $stateConfig, ?array $feature): string|array
    {
        if (($stateConfig['dataType'] ?? null) !== VARIABLETYPE_STRING || !isset($stateConfig['values']) || !\is_array($stateConfig['values'])) {
            return '';
        }

        $enumFeature = [
            'type'     => 'enum',
            'property' => (string) ($stateConfig['ident'] ?? 'state'),
            'values'   => $stateConfig['values'],
            'access'   => $this->ShouldStateFeatureEnableAction($stateConfig, $feature) ? 2 : 0
        ];

        return $this->BuildEnumerationPresentation($enumFeature) ?? '';
    }

    /**
     * Ermittelt, ob eine State-Variable als Aktion angeboten wird.
     */
    private function ShouldStateFeatureEnableAction(array $stateConfig, ?array $feature): bool
    {
        if (isset($stateConfig['enableAction'])) {
            return (bool) $stateConfig['enableAction'];
        }

        return $feature !== null && isset($feature['access']) && (((int) $feature['access'] & 2) === 2);
    }

    /**
     * Aktiviert Aktionen fuer State-Features entsprechend der State-Konfiguration.
     *
     * Explizite `enableAction`-Angaben haben Vorrang vor den normalen Access-Rechten.
     *
     * @param array $stateConfig State-Konfiguration aus getStateConfiguration().
     * @param array|null $feature Optionale Expose-Daten.
     */
    private function enableStateFeatureAction(array $stateConfig, ?array $feature): void
    {
        if (isset($stateConfig['enableAction'])) {
            if ($stateConfig['enableAction']) {
                $this->checkAndEnableAction($stateConfig['ident'], $feature, true);
            }
            return;
        }

        $this->checkAndEnableAction($stateConfig['ident'], $feature);
    }

    /**
     * Registriert eine bekannte Sondervariable, sofern sie nicht gefiltert ist.
     *
     * @param array $feature Expose-Feature.
     *
     * @return bool True, wenn das Feature ein bekannter Sonderfall war.
     */
    private function registerSpecialFeatureVariable(array $feature): bool
    {
        $property = (string) ($feature['property'] ?? '');
        if (!isset(self::$specialVariables[$property])) {
            return false;
        }

        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);
        if (\in_array($property, $aFiltered, true)) {
            $this->SendDebug(__FUNCTION__, 'Skipping filtered special variable: ' . $property, 0);
            return true;
        }

        $this->registerSpecialVariable($feature);
        return true;
    }

    /**
     * Registriert eine Variable anhand des ermittelten Modul-Variablentyps.
     *
     * @param array $feature Expose-Feature.
     * @param string $ident Symcon-Ident.
     * @param string $property Expose-Property.
     * @param string $variableType Interner Variablentyp (`bool`, `int`, `float`, `string`, `text`, `composite`, `list`).
     * @param string|array $profileOrPresentation Zu verwendendes Symcon-Profil oder initiale Darstellung.
     * @param string|null $exposeType Optionaler Expose-Typ fuer rekursive Sub-Features.
     *
     * @return bool True, wenn die normale Nachverarbeitung der Hauptvariable fortgesetzt werden soll.
     */
    private function registerFeatureVariableByType(array $feature, string $ident, string $property, string $variableType, string|array $profileOrPresentation, ?string $exposeType): bool
    {
        $this->RecordLegacyProfilePresentationReplacement($ident, $profileOrPresentation);

        switch ($variableType) {
            case 'bool':
                $this->SendDebug(__FUNCTION__, 'Registering Boolean Variable: ' . $property, 0);
                $this->RegisterVariableBoolean($ident, $this->Translate($this->convertLabelToName($property)), $profileOrPresentation);
                $this->MarkVariableCreated($ident);
                return true;
            case 'int':
                $this->SendDebug(__FUNCTION__, 'Registering Integer Variable: ' . $property, 0);
                $this->RegisterVariableInteger($ident, $this->Translate($this->convertLabelToName($property)), $profileOrPresentation);
                $this->MarkVariableCreated($ident);
                return true;
            case 'float':
                $this->SendDebug(__FUNCTION__, 'Registering Float Variable: ' . $property, 0);
                $this->RegisterVariableFloat($ident, $this->Translate($this->convertLabelToName($property)), $profileOrPresentation);
                $this->MarkVariableCreated($ident);
                return true;
            case 'string':
                $this->SendDebug(__FUNCTION__, 'Registering String Variable: ' . $property, 0);
                $this->RegisterVariableString($ident, $this->Translate($this->convertLabelToName($property)), $profileOrPresentation);
                $this->MarkVariableCreated($ident);
                return true;
            case 'text':
                $this->SendDebug(__FUNCTION__, 'Registering Text Variable: ' . $property, 0);
                $this->RegisterVariableString($ident, $this->Translate($this->convertLabelToName($property)));
                $this->MarkVariableCreated($ident);
                return true;
            case 'composite':
                return !$this->registerCompositeFeatureVariable($feature, $ident, $exposeType);
            case 'list':
                $this->registerListFeatureVariable($feature, $ident, $property);
                return true;
            default:
                $this->SendDebug(__FUNCTION__, 'Unsupported variable type: ' . $variableType, 0);
                return false;
        }
    }

    /**
     * Records that an existing variable changed from a legacy Z2M.* profile to a native presentation.
     *
     * The method only observes the module standard before RegisterVariable* applies the new
     * presentation. It never touches custom user profile or presentation settings.
     *
     * @param string $ident Variable ident.
     * @param string|array $profileOrPresentation New module standard presentation or empty string.
     */
    private function RecordLegacyProfilePresentationReplacement(string $ident, string|array $profileOrPresentation): void
    {
        if (!\is_array($profileOrPresentation)) {
            return;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return;
        }

        try {
            $variable = IPS_GetVariable((int) $variableID);
            $variableName = IPS_GetName((int) $variableID);
        } catch (\Throwable $e) {
            return;
        }

        $oldProfile = \is_string($variable['VariableProfile'] ?? null) ? (string) $variable['VariableProfile'] : '';
        if ($oldProfile === '' || !str_starts_with($oldProfile, 'Z2M.')) {
            return;
        }

        $customProfile = \is_string($variable['VariableCustomProfile'] ?? null) ? (string) $variable['VariableCustomProfile'] : '';
        $customPresentation = $variable['VariableCustomPresentation'] ?? null;
        $hasCustomPresentation = \is_array($customPresentation)
            ? $customPresentation !== []
            : (\is_string($customPresentation) && $customPresentation !== '');

        $log = $this->ReadAttributeArray(self::ATTRIBUTE_PRESENTATION_MIGRATION_LOG);
        $log[(string) $variableID] = [
            'time'            => time(),
            'variableID'      => (int) $variableID,
            'variable'        => $variableName,
            'ident'           => $ident,
            'oldProfile'      => $oldProfile,
            'newPresentation' => $this->DescribePresentationForMigrationLog($profileOrPresentation),
            'customSetting'   => $customProfile !== '' || $hasCustomPresentation,
        ];

        if (\count($log) > 250) {
            $log = array_slice($log, -250, null, true);
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PRESENTATION_MIGRATION_LOG, $log);
        $this->SendDebug(
            'Presentation migration',
            sprintf(
                'Variable #%d %s (%s): %s -> %s',
                (int) $variableID,
                $variableName,
                $ident,
                $oldProfile,
                $log[(string) $variableID]['newPresentation']
            ),
            0
        );
    }

    /**
     * Registriert Composite-Features oder delegiert Farb-Composite-Features.
     *
     * @param array $feature Composite-Expose.
     * @param string $ident Basis-Ident fuer Sub-Features.
     * @param string|null $exposeType Optionaler Expose-Typ fuer rekursive Registrierung.
     *
     * @return bool True, wenn das Composite vollstaendig behandelt wurde und die Hauptmethode abbrechen soll.
     */
    private function registerCompositeFeatureVariable(array $feature, string $ident, ?string $exposeType): bool
    {
        $property = (string) ($feature['property'] ?? '');
        $this->SendDebug(__FUNCTION__, 'Registering Composite Variable: ' . $property, 0);

        if (isset($feature['color_mode']) || $this->IsExposeColorComposite($feature)) {
            $this->registerColorVariable($feature);
            return true;
        }

        if (!isset($feature['features'])) {
            return false;
        }

        $parentLabel = (string) ($feature['label'] ?? $this->FormatVariableCatalogLabel($ident));
        foreach ($feature['features'] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }

            $subFeature = $this->BuildCompositeSubFeature($subFeature, $ident, $parentLabel);
            $this->registerSubFeaturePresetVariables($subFeature);
            $this->registerVariable($subFeature, $exposeType);
        }

        return false;
    }

    /**
     * Registriert Preset-Variablen fuer ein Sub-Feature, sofern vorhanden.
     *
     * @param array $subFeature Sub-Feature aus einem Composite-Expose.
     */
    private function registerSubFeaturePresetVariables(array $subFeature): void
    {
        if (!isset($subFeature['presets'])) {
            return;
        }

        $subPresetIdent = $subFeature['property'] . '_presets';
        $this->RememberVariableDefinition($subPresetIdent, ['property' => $subPresetIdent, 'type' => $subFeature['type'] ?? 'numeric'], 'expose');
        if (!$this->CanCreateVariable($subPresetIdent, ['property' => $subPresetIdent, 'type' => $subFeature['type'] ?? 'numeric'], 'expose')) {
            return;
        }

        $variableType = $this->getVariableTypeFromFeature(
            $subFeature['type'] ?? 'numeric',
            $subFeature['property'],
            $subFeature['unit'] ?? '',
            $subFeature['value_step'] ?? 1.0
        );
        $this->registerPresetVariables(
            $subFeature['presets'],
            $subFeature['property'],
            $variableType,
            $subFeature
        );
    }

    /**
     * Registriert ein List-Feature als JSON-String und optional dessen Item-Typ.
     *
     * @param array $feature List-Expose.
     * @param string $ident Symcon-Ident der Listenvariable.
     * @param string $property Expose-Property.
     */
    private function registerListFeatureVariable(array $feature, string $ident, string $property): void
    {
        if (!$this->CanCreateVariable($ident, $feature, 'expose')) {
            return;
        }

        $this->RegisterVariableString(
            $ident,
            $this->Translate($this->convertLabelToName($property))
        );
        $this->MarkVariableCreated($ident);

        if (isset($feature['item_type'])) {
            $itemFeature = $feature['item_type'];
            $itemFeature['property'] = $ident . '_item';
            $this->registerVariable($itemFeature);
        }
    }

    /**
     * Registriert die Kelvin-Hilfsvariable zur Farbtemperatur.
     *
     * Die Hilfsvariable ist eine moderne Tile-Visu-Ergaenzung zu `color_temp`
     * und erhaelt immer eine Aktion, sofern sie nicht gefiltert ist.
     *
     * @param string $property Expose-Property.
     * @param array $feature Farbtemperatur-Feature.
     */
    private function registerColorTemperatureKelvinVariable(string $property, array $feature): void
    {
        if ($property !== 'color_temp') {
            return;
        }

        $kelvinIdent = $property . '_kelvin';
        $kelvinFeature = ['property' => $kelvinIdent, 'type' => 'numeric', 'label' => 'Color Temperature Kelvin'];
        if (!$this->CanCreateVariable($kelvinIdent, $kelvinFeature, 'derived')) {
            return;
        }

        $profileOrPresentation = $this->BuildColorTemperaturePresentation($feature) ?? '';
        $this->RecordLegacyProfilePresentationReplacement($kelvinIdent, $profileOrPresentation);
        $this->RegisterVariableInteger($kelvinIdent, $this->Translate('Color Temperature Kelvin'), $profileOrPresentation);
        $this->MarkVariableCreated($kelvinIdent);
        $this->checkAndEnableAction($kelvinIdent, null, true);

        $this->registerColorTemperatureWhiteColorVariable();
    }

    /**
     * Registriert fuer reine Tunable-White-Leuchten eine abgeleitete Farbe.
     */
    private function registerColorTemperatureWhiteColorVariable(): void
    {
        if ($this->HasNativeColorExpose()) {
            return;
        }
        if (!$this->CanCreateVariable('color', ['property' => 'color', 'type' => 'numeric', 'label' => 'Color'], 'derived')) {
            $this->SendDebug(__FUNCTION__, 'Skipping derived tunable-white color variable: color', 0);
            return;
        }

        $colorPresentation = $this->BuildColorPresentation() ?? '';
        $this->RecordLegacyProfilePresentationReplacement('color', $colorPresentation);
        $this->RegisterVariableInteger('color', $this->Translate($this->convertLabelToName('color')), $colorPresentation);
        $this->MarkVariableCreated('color');
    }

    /**
     * Aktualisiert die abgeleitete Weissfarb-Variable, sofern sie fuer Tunable White genutzt wird.
     */
    private function UpdateColorTemperatureWhiteColorVariable(int $kelvin): void
    {
        if ($this->HasNativeColorExpose()) {
            return;
        }
        if ($this->GetObjectIDByIdent('color') === false) {
            return;
        }

        $this->SetValueDirect('color', $this->convertKelvinToWhiteColor($this->ClampColorTemperatureKelvinToConfiguredRange($kelvin)));
    }

    /**
     * Prueft, ob das Geraet eine echte RGB/HS/XY-Farbsteuerung liefert.
     */
    private function HasNativeColorExpose(): bool
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if ($this->FeatureTreeContainsNativeColorExpose($expose)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Durchsucht ein Expose rekursiv nach nativen Farbfeatures.
     */
    private function FeatureTreeContainsNativeColorExpose(array $feature): bool
    {
        $property = strtolower((string) ($feature['property'] ?? ''));
        $name = strtolower((string) ($feature['name'] ?? ''));

        if (
            $property === 'color'
            || \in_array($property, ['color_hs', 'color_rgb', 'color_xy'], true)
            || \in_array($name, ['color_hs', 'color_rgb', 'color_xy'], true)
        ) {
            return true;
        }

        foreach ($feature['features'] ?? [] as $subFeature) {
            if (\is_array($subFeature) && $this->FeatureTreeContainsNativeColorExpose($subFeature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registriert Preset-Variablen fuer das Hauptfeature.
     *
     * @param array $feature Expose-Feature.
     * @param string $property Expose-Property.
     * @param string $type Expose-Typ.
     * @param string $unit Einheit des Werts.
     * @param float $step Schrittweite des Werts.
     * @param string|null $groupType Optionaler Gruppentyp.
     */
    private function registerFeaturePresetVariables(array $feature, string $property, string $type, string $unit, float $step, ?string $groupType): void
    {
        if (!isset($feature['presets']) || empty($feature['presets'])) {
            return;
        }

        $variableType = $this->getVariableTypeFromFeature($type, $property, $unit, $step, $groupType);
        $this->registerPresetVariables($feature['presets'], $feature['property'], $variableType, $feature);
        $this->SendDebug(__FUNCTION__, 'Registered presets for: ' . $feature['property'], 0);
    }

    /**
     * registerColorVariable
     *
     * Registriert Farbvariablen für verschiedene Farbmodelle.
     *
     * Diese Methode erstellt und registriert spezielle Variablen für die Farbsteuerung
     * von Zigbee-Geräten. Unterstützt werden die Farbmodelle:
     * - XY-Farbraum (color_xy)
     * - HSV-Farbraum (color_hs)
     * - RGB-Farbraum (color_rgb)
     *
     * @param array $feature Array mit Eigenschaften des Features:
     *                       - 'name': Name des Farbmodells ('color_xy', 'color_hs', 'color_rgb')
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::Translate()
     * @see debug_backtrace()
     */
    private function registerColorVariable(array $feature): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        $colorPresentation = $this->BuildColorPresentation() ?? '';

        switch ($feature['name']) {
            case 'color_xy':
                if (!$this->CanCreateVariable('color', ['property' => 'color', 'type' => 'composite', 'label' => 'Color'], 'derived')) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color', 0);
                    break;
                }
                $this->RecordLegacyProfilePresentationReplacement('color', $colorPresentation);
                $this->RegisterVariableInteger('color', $this->Translate($this->convertLabelToName('color')), $colorPresentation);
                $this->MarkVariableCreated('color');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->checkAndEnableAction('color', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_xy', 'color', 0);
                break;
            case 'color_hs':
                if (!$this->CanCreateVariable('color_hs', ['property' => 'color_hs', 'type' => 'composite', 'label' => 'Color HS'], 'derived')) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color_hs', 0);
                    break;
                }
                $this->RecordLegacyProfilePresentationReplacement('color_hs', $colorPresentation);
                $this->RegisterVariableInteger('color_hs', $this->Translate($this->convertLabelToName('color_hs')), $colorPresentation);
                $this->MarkVariableCreated('color_hs');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->checkAndEnableAction('color_hs', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_hs', 'color_hs', 0);
                break;
            case 'color_rgb':
                if (!$this->CanCreateVariable('color_rgb', ['property' => 'color_rgb', 'type' => 'composite', 'label' => 'Color RGB'], 'derived')) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color_rgb', 0);
                    break;
                }
                $this->RecordLegacyProfilePresentationReplacement('color_rgb', $colorPresentation);
                $this->RegisterVariableInteger('color_rgb', $this->Translate($this->convertLabelToName('color_rgb')), $colorPresentation);
                $this->MarkVariableCreated('color_rgb');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->checkAndEnableAction('color_rgb', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_rgb', 'color_rgb', 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Unhandled composite type', $feature['name'], 0);
                break;
        }
    }

    /**
     * registerPresetVariables
     *
     * Registriert eine Preset-Variable fuer ein Feature.
     *
     * Diese Funktion erstellt fuer ein Feature eine zusätzliche Preset-Variable mit nativer
     * Aufzaehlungsdarstellung. Es werden keine dynamischen Preset-Profile mehr angelegt.
     * Sie wird verwendet, um vordefinierte Werte (Presets) für bestimmte Eigenschaften eines Geräts
     * zugänglich zu machen.
     *
     * @param array $presets Array mit Preset-Definitionen. Jedes Preset enthält:
     *                       - 'name': Name des Presets (string)
     *                       - 'value': Wert des Presets (mixed)
     * @param string $property Property/Ident der Preset-Hauptvariable.
     * @param string $variableType Typ der Variable ('float' oder 'int')
     * @param array $feature Feature-Definition mit zusätzlichen Eigenschaften wie:
     *                       - 'property': Name der Eigenschaft
     *                       - 'name': Anzeigename
     *                       - 'value_min': Minimaler Wert (optional)
     *                       - 'value_max': Maximaler Wert (optional)
     * @return void
     *
     * Beispiel:
     * ```php
     * $presets = [
     *     ['name' => 'low', 'value' => 20],
     *     ['name' => 'medium', 'value' => 50],
     *     ['name' => 'high', 'value' => 100]
     * ];
     * $this->registerPresetVariables($presets, 'Brightness', 'int', ['property' => 'brightness', 'name' => 'Brightness']);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::BuildPresetPresentation()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::Translate()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::EnableAction()
     */
    private function registerPresetVariables(array $presets, string $property, string $variableType, array $feature): void
    {
        $this->SendDebug(__FUNCTION__, 'Registriere Preset-Variablen für: ' . $property, 0);

        // Hole ident für Preset-Variable
        $presetIdent = $property . '_presets';
        $presetFeature = $this->BuildPresetCatalogFeature($feature, $property, $presets);
        if (!$this->CanCreateVariable($presetIdent, $presetFeature, 'expose')) {
            return;
        }

        // Name formatieren
        $formattedLabel = $this->convertLabelToName($property);

        $presentation = $this->BuildPresetPresentation($presets, $variableType, $feature);
        $profileOrPresentation = $presentation ?? '';
        $this->RecordLegacyProfilePresentationReplacement($presetIdent, $profileOrPresentation);

        // Variable anhand Typ registrieren
        if ($variableType === 'float') {
            $this->RegisterVariableFloat($presetIdent, $this->Translate($formattedLabel . ' Presets'), $profileOrPresentation);
        } else {
            $this->RegisterVariableInteger($presetIdent, $this->Translate($formattedLabel . ' Presets'), $profileOrPresentation);
        }
        $this->MarkVariableCreated($presetIdent);

        // Zentrale EnableAction-Prüfung für Preset-Variable
        $this->checkAndEnableAction($presetIdent, $feature);
    }

    /**
     * registerSpecialVariable
     *
     * Registriert spezielle Variablen.
     *
     * @param array $feature Feature-Eigenschaften
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::adjustSpecialValue()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::RegisterVariableString()
     * @see \IPSModule::RegisterVariableBoolean()
     * @see \IPSModule::Translate()
     * @see \IPSModule::EnableAction()
     * @see sprintf()
     * @see json_encode()
     */
    private function registerSpecialVariable($feature): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        $ident = $feature['property'];
        $this->SendDebug(__FUNCTION__, \sprintf('Checking special case for %s: %s', $ident, json_encode($feature)), 0);

        if (!isset(self::$specialVariables[$ident])) {
            return;
        }
        if (!$this->CanCreateVariable($ident, $feature, 'special')) {
            return;
        }

        $varDef = self::$specialVariables[$ident];
        $formattedLabel = $this->convertLabelToName($ident);

        // Wert anpassen wenn nötig
        if (isset($feature['value'])) {
            $value = $this->adjustSpecialValue($ident, $feature['value']);
        }

        $profileOrPresentation = '';
        switch ($ident) {
            case 'brightness':
                $profileOrPresentation = $this->BuildBrightnessFeaturePresentation($feature) ?? $profileOrPresentation;
                break;

            case 'update__remaining':
                $profileOrPresentation = $this->BuildDurationPresentation() ?? $profileOrPresentation;
                break;

            case 'last_seen':
                $profileOrPresentation = $this->BuildDateTimePresentation() ?? $profileOrPresentation;
                break;
        }
        $this->RecordLegacyProfilePresentationReplacement($ident, $profileOrPresentation);
        switch ($varDef['type']) {
            case VARIABLETYPE_FLOAT:
                $this->RegisterVariableFloat($ident, $this->Translate($formattedLabel), $profileOrPresentation);
                break;
            case VARIABLETYPE_INTEGER:
                $this->RegisterVariableInteger($ident, $this->Translate($formattedLabel), $profileOrPresentation);
                break;
            case VARIABLETYPE_STRING:
                $this->RegisterVariableString($ident, $this->Translate($formattedLabel), $profileOrPresentation);
                break;
            case VARIABLETYPE_BOOLEAN:
                $this->RegisterVariableBoolean($ident, $this->Translate($formattedLabel), $profileOrPresentation);
                break;
        }
        $this->MarkVariableCreated($ident);

        // Zentrale EnableAction-Prüfung für spezielle Variable
        $this->checkAndEnableAction($ident, $feature);
        return;
    }

    /**
     * getStateConfiguration
     *
     * Prüft und liefert die Konfiguration für State-basierte Features.
     *
     * Diese Methode analysiert ein Feature und bestimmt, ob es sich um ein State-Feature handelt.
     *
     * Sie prüft drei Szenarien:
     * 1. Vordefinierte States aus stateDefinitions
     * 2. Enum-Typ States (z.B. "state" mit definierten Werten)
     * 3. Standard State-Pattern als Boolean (z.B. "state", "state_left")
     *
     * Bei Enum-States wird eine native Aufzählungsdarstellung verwendet, damit
     * keine dynamischen Z2M.*-Profile angelegt werden muessen.
     *
     * Die zurückgegebene Konfiguration enthält:
     * - type: Typ des States (z.B. 'switch', 'enum')
     * - dataType: IPS Variablentyp (z.B. VARIABLETYPE_BOOLEAN, VARIABLETYPE_STRING)
     * - values: Mögliche Zustände (z.B. ['ON', 'OFF'] oder ['OPEN', 'CLOSE', 'STOP'])
     * - ident: Normalisierter Identifikator
     * - enableAction: Optional - Nur bei explizit definierten States aus stateDefinitions
     *
     * Hinweis: EnableAction wird nur zurückgegeben wenn explizit in stateDefinitions
     * definiert. Ansonsten wird EnableAction zentral in registerVariable() über
     * checkAndEnableAction() basierend auf Access-Rechten bestimmt.
     *
     * @param string $featureId Feature-Identifikator (z.B. 'state', 'state_left')
     * @param array|null $feature Optionales Feature-Array mit weiteren Eigenschaften:
     *                           - type: Datentyp ('enum', 'binary')
     *                           - values: Array möglicher Enum-Werte
     *                           Hinweis: Access-Rechte werden für EnableAction-Entscheidung
     *                           an registerVariable() weitergegeben
     *
     * @return array|null Array mit State-Konfiguration oder null wenn kein State-Feature
     *
     * Beispiel:
     * ```php
     * // Standard boolean state
     * $config = $this->getStateConfiguration('state');
     * // Ergebnis: ['type' => 'switch', 'dataType' => VARIABLETYPE_BOOLEAN, 'ident' => 'state']
     *
     * // Enum state mit nativer Aufzaehlungsdarstellung
     * $config = $this->getStateConfiguration('state', [
     *     'type' => 'enum',
     *     'values' => ['OPEN', 'CLOSE', 'STOP']
     * ]);
     * // Ergebnis: ['type' => 'enum', 'dataType' => VARIABLETYPE_STRING, 'ident' => 'state']
     *
     * // Vordefinierter state
     * $config = $this->getStateConfiguration('valve_state');
     * // Ergebnis: Konfiguration aus stateDefinitions (inklusive enableAction falls definiert)
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable() Verwendet die Konfiguration und trifft EnableAction-Entscheidung
     * @see \Zigbee2MQTT\ModulBase::checkAndEnableAction() Zentrale EnableAction-Logik
     * @see \IPSModule::SendDebug()
     * @see preg_match()
     */
    private function getStateConfiguration(string $featureId, ?array $feature = null): ?array
    {
        // Basis state-Pattern
        $statePattern = '/^state(?:_[a-z0-9]+)?$/i';

        if (preg_match($statePattern, $featureId)) {
            $this->SendDebug(__FUNCTION__, 'State-Konfiguration für: ' . $featureId, 0);

            // Prüfe ZUERST auf vordefinierte States
            if (isset(static::$stateDefinitions[$featureId])) {
                $stateConfig = static::$stateDefinitions[$featureId];
                $stateConfig['ident'] = $stateConfig['ident'] ?? $featureId;
                return $stateConfig;
            }

            // Dann auf enum type
            if (isset($feature['type']) && $feature['type'] === 'enum' && isset($feature['values'])) {

                // Daten zur Variablenregistrierung zurückgeben
                return [
                    'type'         => 'enum',
                    'dataType'     => VARIABLETYPE_STRING,
                    'values'       => $feature['values'],
                    'ident'        => $featureId
                ];
            }

            // Nur wenn kein enum type und kein vordefinierter state, dann boolean
            return [
                'type'         => 'switch',
                'dataType'     => VARIABLETYPE_BOOLEAN,
                'values'       => ['ON', 'OFF'],
                'ident'        => $featureId
            ];
        }

        // Prüfe auf vordefinierte States wenn kein state pattern matched
        if (isset(static::$stateDefinitions[$featureId])) {
            $stateConfig = static::$stateDefinitions[$featureId];
            $stateConfig['ident'] = $stateConfig['ident'] ?? $featureId;
            return $stateConfig;
        }

        return null;
    }

    /**
     * isCompositeKey
     *
     * Prüft ob ein Key ein Composite Key ist (enthält '__').
     * Zentrale Prüfmethode um Code-Duplikate zu vermeiden.
     *
     * @param string $key Der zu prüfende Key
     *
     * @return bool True wenn Key ein Composite Key ist, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::processVariable() Hauptnutzer
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable() Weiterer Nutzer
     * @see strpos() String Position Prüfung
     */
    private function isCompositeKey(string $key): bool
    {
        return strpos($key, '__') !== false;
    }

    /**
     * Prueft, ob ein Enum-Feature nur als write-only Einzelkommando dient.
     *
     * Diese Exposes besitzen genau einen moeglichen Wert, keinen Lesezugriff und
     * Schreibzugriff. Sie werden als ausloesbare Boolean-Aktion registriert.
     *
     * @param array $feature Expose-Feature.
     *
     * @return bool True fuer write-only Single-Enum-Kommandos.
     */
    private function isWriteOnlySingleEnumCommand(array $feature): bool
    {
        return ($feature['type'] ?? '') === 'enum'
            && isset($feature['values'])
            && \is_array($feature['values'])
            && \count($feature['values']) === 1
            && (($feature['access'] ?? 0) & 0b001) === 0
            && (($feature['access'] ?? 0) & 0b010) !== 0;
    }

    /**
     * Aktualisiert eine zugehörige Preset-Variable, falls vorhanden
     *
     * @param string $ident Identifikator der Hauptvariable
     * @param mixed $value Zu setzender Wert
     * @return void
     */
    private function updatePresetVariable(string $ident, mixed $value): void
    {
        $presetIdent = $ident . '_presets';

        // Prüfe ob die Preset-Variable existiert
        if ($this->GetObjectIDByIdent($presetIdent) !== false) {
            // Variable existiert, also aktualisieren wir direkt ihren Wert
            $this->SetValueDirect($presetIdent, $value);
            $this->SendDebug(__FUNCTION__, "Updated $presetIdent with value: " . (\is_array($value) ? json_encode($value) : $value), 0);
        }
    }

    /**
     * checkAndEnableAction
     *
     * Zentrale Hilfsfunktion zur konsistenten EnableAction-Prüfung
     *
     * Diese Methode implementiert die einheitliche Logik für EnableAction basierend auf:
     * 1. Access-Rechte aus Feature-Array (0b010 Flag für Schreibzugriff)
     * 2. Access-Rechte aus knownVariables
     * 3. Spezielle Variablen (color_temp_kelvin)
     *
     * @param string $ident Identifikator der Variable
     * @param array|null $feature Optional: Feature-Array mit Access-Informationen
     * @param bool $forceEnable Optional: Erzwingt EnableAction (für spezielle Variablen)
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::getKnownVariables()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::SendDebug()
     */
    private function checkAndEnableAction(string $ident, ?array $feature = null, bool $forceEnable = false): void
    {
        // Spezielle Variablen oder erzwungene Aktivierung
        if ($forceEnable) {
            $this->EnableAction($ident);
            $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (forced/special variable)', 0);
            return;
        }

        // Prüfe Access-Rechte aus Feature-Array
        if (isset($feature['access']) && ($feature['access'] & 0b010) != 0) {
            $this->EnableAction($ident);
            $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (has write access from feature)', 0);
            return;
        }

        // Prüfe Access-Rechte aus knownVariables
        $knownVariables = $this->getKnownVariables();
        if (isset($knownVariables[$ident]['access']) && ($knownVariables[$ident]['access'] & 0b010) != 0) {
            $this->EnableAction($ident);
            $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (has write access from known variables)', 0);
            return;
        }

        // Keine Berechtigung gefunden
        $this->SendDebug(__FUNCTION__, 'Skipped EnableAction for ' . $ident . ' (no write access)', 0);
    }
}
