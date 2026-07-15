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
require_once __DIR__ . '/DeviceCommandHelper.php';
require_once __DIR__ . '/PayloadProcessingHelper.php';
require_once __DIR__ . '/PayloadVariableHelper.php';
require_once __DIR__ . '/DeviceActionHelper.php';
require_once __DIR__ . '/ExposeVariableRegistrationHelper.php';

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
    use DeviceCommandHelper;
    use PayloadProcessingHelper;
    use PayloadVariableHelper;
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

            // Standardaktion fuer die migrierte Helligkeitsvariable synchronisieren.
            $this->synchronizeVariableAction('brightness', $brightnessFeature);
        }
        // Flag für beendete Migration wieder setzen
        $this->BUFFER_MQTT_SUSPENDED = false;
        $this->BUFFER_PROCESSING_MIGRATION = false;
        return json_encode($j);
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

}
