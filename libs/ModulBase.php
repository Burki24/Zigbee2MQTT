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

}
