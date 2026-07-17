<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/InstanceConnectionHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';
require_once dirname(__DIR__) . '/libs/MQTTHelper.php';
require_once dirname(__DIR__) . '/libs/AttributeArrayHelper.php';
require_once dirname(__DIR__) . '/libs/Maintenance/StaleVariableCleanupHelper.php';
require_once __DIR__ . '/Helper/BridgeRequestHelper.php';
require_once __DIR__ . '/Helper/BridgeNetworkSecurityHelper.php';
require_once __DIR__ . '/Helper/BridgeInstallCodeHelper.php';
require_once __DIR__ . '/Helper/BridgeBackupHelper.php';
require_once __DIR__ . '/Helper/BridgePairingHelper.php';
require_once __DIR__ . '/Helper/BridgeStaleVariableHelper.php';
require_once __DIR__ . '/Helper/BridgeTouchlinkHelper.php';
require_once __DIR__ . '/Helper/BridgeConfigurationCommandHelper.php';
require_once __DIR__ . '/Helper/BridgeGroupSceneCommandHelper.php';
require_once __DIR__ . '/Helper/BridgeOTACommandHelper.php';
require_once __DIR__ . '/Helper/BridgeOTAFormHelper.php';
require_once __DIR__ . '/Helper/BridgeDeviceCommandHelper.php';
require_once __DIR__ . '/Helper/BridgeDiagnosticHelper.php';

/**
 * Repräsentiert und verwaltet die zentrale Zigbee2MQTT-Bridge in Symcon.
 *
 * Die Klasse koordiniert den Symcon-Lebenszyklus, den Empfang der
 * `bridge/*`-Topics, die Bridge-Statusvariablen und das Konfigurationsformular.
 * Fachliche Funktionen sind in Traits unter `Bridge/Helper` ausgelagert. Die
 * angegebenen Pseudoeigenschaften werden über den `BufferHelper` im
 * Instanzpuffer gespeichert.
 *
 * @property float  $actualExtensionVersion Erwartete Version der zur installierten zigbee-herdsman-Version passenden Symcon-Extension.
 * @property float  $installedZhVersion      Von Zigbee2MQTT gemeldete Version von zigbee-herdsman.
 * @property string $ExtensionFilename       Dateiname der in Zigbee2MQTT gefundenen Symcon-Extension.
 * @property string $ConfigLastSeen          Aktuelle Zigbee2MQTT-Einstellung für `advanced.last_seen`.
 * @property bool   $ConfigPermitJoin        Aktuelle Zigbee2MQTT-Einstellung für dauerhaftes `permit_join`.
 * @property string $PermitJoinTarget        Zuletzt ausgewähltes Ziel für den Pairing-Modus.
 *
 * @see \BridgeRequestHelper Request-/Response-Verarbeitung in `Bridge/Helper/BridgeRequestHelper.php`.
 * @see \BridgeConfigurationCommandHelper Bridge-Konfiguration in `Bridge/Helper/BridgeConfigurationCommandHelper.php`.
 * @see \BridgeDeviceCommandHelper Gerätebefehle in `Bridge/Helper/BridgeDeviceCommandHelper.php`.
 * @see \BridgeGroupSceneCommandHelper Gruppen- und Szenenbefehle in `Bridge/Helper/BridgeGroupSceneCommandHelper.php`.
 * @see \BridgeDiagnosticHelper Diagnosefunktionen in `Bridge/Helper/BridgeDiagnosticHelper.php`.
 * @see \BridgeNetworkSecurityHelper Netzwerklisten in `Bridge/Helper/BridgeNetworkSecurityHelper.php`.
 * @see \BridgeInstallCodeHelper Installcodes in `Bridge/Helper/BridgeInstallCodeHelper.php`.
 * @see \BridgeBackupHelper Backups in `Bridge/Helper/BridgeBackupHelper.php`.
 * @see \BridgePairingHelper Pairing in `Bridge/Helper/BridgePairingHelper.php`.
 * @see \BridgeStaleVariableHelper Variablenwartung in `Bridge/Helper/BridgeStaleVariableHelper.php`.
 * @see \BridgeTouchlinkHelper Touchlink in `Bridge/Helper/BridgeTouchlinkHelper.php`.
 * @see \BridgeOTACommandHelper OTA-Befehle in `Bridge/Helper/BridgeOTACommandHelper.php`.
 * @see \BridgeOTAFormHelper OTA-Formular und Liveaktualisierung in `Bridge/Helper/BridgeOTAFormHelper.php`.
 */
class Zigbee2MQTTBridge extends IPSModuleStrict
{
    use BridgeRequestHelper;
    use BridgeNetworkSecurityHelper;
    use BridgeInstallCodeHelper;
    use BridgeBackupHelper;
    use BridgePairingHelper;
    use BridgeStaleVariableHelper;
    use BridgeTouchlinkHelper;
    use BridgeConfigurationCommandHelper;
    use BridgeGroupSceneCommandHelper;
    use BridgeOTACommandHelper;
    use BridgeOTAFormHelper;
    use BridgeDeviceCommandHelper;
    use BridgeDiagnosticHelper;
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\InstanceConnectionHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\SendData;
    use \Zigbee2MQTT\AttributeArrayHelper;

    /**
     * Ordnet unterstützten zigbee-herdsman-Hauptversionen die passende Extension-Datei zu.
     *
     * @var array<int,string>
     * @see \BridgeConfigurationCommandHelper Installation der Extension in `Bridge/Helper/BridgeConfigurationCommandHelper.php`.
     */
    private const EXTENSION_ZH_VERSION = [
        2  => 'IPSymconExtension.js',
        3  => 'IPSymconExtension2.js',
        4  => 'IPSymconExtension2.js',
        5  => 'IPSymconExtension2.js',
        6  => 'IPSymconExtension2.js',
        7  => 'IPSymconExtension2.js',
        8  => 'IPSymconExtension2.js',
        9  => 'IPSymconExtension2.js',
        10 => 'IPSymconExtension2.js',
        11 => 'IPSymconExtension2.js',
        12 => 'IPSymconExtension2.js',
        13 => 'IPSymconExtension2.js',
        14 => 'IPSymconExtension2.js',
        15 => 'IPSymconExtension2.js',
        16 => 'IPSymconExtension2.js',
        17 => 'IPSymconExtension2.js',
        18 => 'IPSymconExtension2.js'
    ];

    private const ATTRIBUTE_DIAGNOSTIC_HEALTH = 'DiagnosticHealth';
    private const ATTRIBUTE_DIAGNOSTIC_COORDINATOR = 'DiagnosticCoordinator';
    private const ATTRIBUTE_DIAGNOSTIC_EVENTS = 'DiagnosticEvents';
    private const ATTRIBUTE_DIAGNOSTIC_LOGS = 'DiagnosticLogs';
    private const ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES = 'DiagnosticUnsupportedDevices';
    private const ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES = 'DiagnosticInterviewDevices';
    private const ATTRIBUTE_TOUCHLINK_DEVICES = 'TouchlinkDevices';
    private const ATTRIBUTE_NETWORK_DEVICES = 'NetworkDevices';
    private const ATTRIBUTE_CONFIG_BLOCKLIST = 'ConfigBlocklist';
    private const ATTRIBUTE_CONFIG_PASSLIST = 'ConfigPasslist';
    private const ATTRIBUTE_PENDING_PASSLIST_CHANGE = 'PendingPasslistChange';
    private const ATTRIBUTE_STALE_VARIABLE_SCAN = 'StaleVariableScan';
    private const ATTRIBUTE_OTA_CHECK_RESULTS = 'OTACheckResults';
    private const ATTRIBUTE_OTA_UPDATE_RESULTS = 'OTAUpdateResults';
    private const ATTRIBUTE_PENDING_OTA_UPDATE = 'PendingOTAUpdate';
    private const ATTRIBUTE_OTA_PENDING_REQUESTS = 'OTAPendingRequests';
    private const ATTRIBUTE_OTA_MONITORED_DEVICES = 'OTAMonitoredDevices';
    private const ATTRIBUTE_OTA_MONITORED_VARIABLES = 'OTAMonitoredVariables';
    private const ATTRIBUTE_INSTALL_CODE_CATALOG = 'InstallCodeCatalog';
    private const ATTRIBUTE_PENDING_INSTALL_CODE_DELETE = 'PendingInstallCodeDelete';
    private const MAX_DIAGNOSTIC_ENTRIES = 50;
    private const MAX_OTA_RESULT_ENTRIES = 25;
    private const OTA_CHECK_RESULT_LIFETIME = 300;
    private const OTA_PENDING_REQUEST_LIFETIME = 300;
    private const MAX_PERMIT_JOIN_DURATION = 254;
    private const TIMER_PERMIT_JOIN_STATUS = 'UpdatePermitJoinStatus';
    private const TIMEOUT_BRIDGE_APPLY_OPTIONS_REQUEST = 5000;

    /**
     * Initialisiert Properties, Puffer, Timer und persistente Bridge-Attribute.
     *
     * Registriert werden das MQTT-Basistopic, die Laufzeitinformationen der
     * Symcon-Extension, Pairing-Zustände sowie Speicher für Diagnose, Netzwerk,
     * Installcodes, OTA, Touchlink und die Variablenwartung. Ausstehende
     * MQTT-Transaktionen werden beim Start verworfen.
     *
     * @see \BridgePairingHelper Pairing-Timer in `Bridge/Helper/BridgePairingHelper.php`.
     * @see \Zigbee2MQTT\SendData Transaktionspuffer in `libs/MQTTHelper.php`.
     * @see \Zigbee2MQTT\BufferHelper Instanzpuffer in `libs/BufferHelper.php`.
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString(self::MQTT_BASE_TOPIC, '');

        // Init Buffers
        $this->actualExtensionVersion = 0;
        $this->installedZhVersion = 0;
        $this->ExtensionFilename = '';
        $this->ConfigLastSeen = 'epoch';
        $this->TraceHelperCall('MQTTHelper', 'ClearTransactionData', fn (): mixed => $this->ClearTransactionData());
        $this->ConfigPermitJoin = false;
        $this->PermitJoinTarget = '';

        $this->TraceHelperCall(
            'BridgePairingHelper',
            'RegisterPermitJoinTimer',
            fn (): mixed => $this->RegisterPermitJoinTimer()
        );

        $this->RegisterAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_HEALTH, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_COORDINATOR, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_EVENTS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_LOGS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_TOUCHLINK_DEVICES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_CONFIG_BLOCKLIST, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_CONFIG_PASSLIST, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_PENDING_PASSLIST_CHANGE, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_STALE_VARIABLE_SCAN, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_OTA_UPDATE_RESULTS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_PENDING_OTA_UPDATE, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_OTA_MONITORED_DEVICES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_OTA_MONITORED_VARIABLES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_PENDING_INSTALL_CODE_DELETE, []);
    }

    /**
     * Übernimmt die Bridge-Konfiguration und synchronisiert ihren Laufzeitzustand.
     *
     * Die Methode leert die Transaktionswarteschlange, setzt Instanzstatus,
     * Zusammenfassung und Empfangsfilter und registriert die Bridge-Variablen samt
     * Aktionen und nativen Darstellungen. Bei erreichbarer Bridge werden Optionen
     * und Extension-Stand geprüft; eine fehlende oder veraltete Extension wird
     * automatisch installiert. Abschließend werden die OTA-Beobachtungen
     * synchronisiert.
     *
     * @see \BridgeConfigurationCommandHelper Optionsabfrage und Extension-Installation in `Bridge/Helper/BridgeConfigurationCommandHelper.php`.
     * @see \BridgePairingHelper Pairing-Zustand in `Bridge/Helper/BridgePairingHelper.php`.
     * @see \BridgeOTAFormHelper OTA-Beobachtungen in `Bridge/Helper/BridgeOTAFormHelper.php`.
     * @see \Zigbee2MQTT\SendData Transaktionsverwaltung in `libs/MQTTHelper.php`.
     */
    public function ApplyChanges(): void
    {
        // Empty TransactionQueue
        $this->TraceHelperCall('MQTTHelper', 'ClearTransactionData', fn (): mixed => $this->ClearTransactionData());

        //Never delete this line!
        parent::ApplyChanges();

        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if (empty($BaseTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
        } else {
            $this->SetStatus(IS_ACTIVE);
            //Setze Filter für ReceiveData
            $this->SetReceiveDataFilter('.*"Topic":"' . $BaseTopic . '/bridge/.*');
        }

        $this->SetSummary($BaseTopic);

        $restartPresentation = $this->BuildBridgeEnumerationPresentation([
            $this->BuildBridgeEnumerationOption(0, 'Restart', 0xFF0000),
        ], 'rotate-cw', 1);
        $logLevelPresentation = $this->BuildBridgeEnumerationPresentation([
            $this->BuildBridgeEnumerationOption('error', 'Error'),
            $this->BuildBridgeEnumerationOption('warning', 'Warning'),
            $this->BuildBridgeEnumerationOption('info', 'Information'),
            $this->BuildBridgeEnumerationOption('debug', 'Debug'),
        ], 'list');
        $this->RegisterVariableBoolean('state', $this->Translate('State'), '');
        $this->RegisterVariableBoolean('extension_loaded', $this->Translate('Extension Loaded'));
        $this->RegisterVariableString('extension_version', $this->Translate('Extension Version'));
        $this->RegisterVariableBoolean('extension_is_current', $this->Translate('Extension is up to date'));
        $this->RegisterVariableString('log_level', $this->Translate('Log Level'), $logLevelPresentation);
        $this->EnableAction('log_level');
        $this->RegisterVariableBoolean('permit_join', $this->Translate('Allow joining the network'), '');
        $this->EnableAction('permit_join');
        $this->RegisterVariableInteger('permit_join_end', $this->Translate('Pairing mode ends'), '');
        $this->RegisterVariableInteger('permit_join_remaining', $this->Translate('Pairing time remaining'), '');
        $this->RegisterVariableString('permit_join_target', $this->Translate('Pairing target'));
        $this->RegisterVariableBoolean('restart_required', $this->Translate('Restart Required'));
        $this->RegisterVariableInteger('restart_request', $this->Translate('Perform a restart'), $restartPresentation);
        $this->EnableAction('restart_request');
        $this->RegisterVariableString('version', $this->Translate('Version'));
        $this->RegisterVariableString('zigbee_herdsman_converters', $this->Translate('Zigbee Herdsman Converters Version'));
        $this->RegisterVariableString('zigbee_herdsman', $this->Translate('Zigbee Herdsman Version'));
        $this->RegisterVariableInteger('network_channel', $this->Translate('Network Channel'));

        $this->UnregisterVariable('permit_join_timeout');
        $this->TraceHelperCall(
            'BridgePairingHelper',
            'UpdatePermitJoinStatus',
            fn (): mixed => $this->UpdatePermitJoinStatus(false)
        );

        $online = false;
        if (!empty($BaseTopic)) {
            if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                $online = $this->TraceHelperCall(
                    'BridgeConfigurationCommandHelper',
                    'RequestOptions',
                    fn (): mixed => @$this->RequestOptions(self::TIMEOUT_BRIDGE_APPLY_OPTIONS_REQUEST)
                );
            }
        }
        $this->SendDebug('Online', $online ? 'true' : 'false', 0);
        $installedExtVersion = (empty($this->GetValue('extension_version')) ? -1 : (float) $this->GetValue('extension_version'));
        $this->SetValue('extension_is_current', $this->actualExtensionVersion <= $installedExtVersion);
        if ($this->actualExtensionVersion <= $installedExtVersion) {
            $this->TryUpdateFormField('InstallExtension', 'caption', $this->Translate('Symcon-Extension is up-to-date'));
            $this->TryUpdateFormField('InstallExtension', 'enabled', false);
        } else {
            $this->TryUpdateFormField('InstallExtension', 'caption', $this->Translate('Install or upgrade Symcon-Extension'));
            $this->TryUpdateFormField('InstallExtension', 'enabled', true);
            if (!empty($BaseTopic)) {
                if ($online) {
                    $this->TraceHelperCall(
                        'BridgeConfigurationCommandHelper',
                        'InstallSymconExtension',
                        fn (): mixed => @$this->InstallSymconExtension()
                    );
                }
            }
        }
        $this->TraceHelperCall(
            'BridgeOTAFormHelper',
            'SynchronizeOTAMessageSubscriptions',
            fn (): mixed => $this->SynchronizeOTAMessageSubscriptions()
        );
    }

    /**
     * Verarbeitet Änderungen an den für OTA überwachten Geräteinstanzen und Variablen.
     *
     * Wertänderungen aktualisieren die sichtbaren OTA-Listen. Werden Variablen
     * unter einer überwachten Geräteinstanz hinzugefügt oder entfernt, werden die
     * Nachrichtenabonnements neu aufgebaut.
     *
     * @param int   $TimeStamp Zeitstempel der Symcon-Nachricht.
     * @param int   $SenderID  ID des auslösenden Objekts.
     * @param int   $Message   Symcon-Nachrichtenkennung.
     * @param array $Data      Nachrichtenspezifische Zusatzdaten.
     *
     * @see \BridgeOTAFormHelper OTA-Beobachtung und Formularaktualisierung in `Bridge/Helper/BridgeOTAFormHelper.php`.
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE && \in_array($SenderID, $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_VARIABLES), true)) {
            $this->TraceHelperCall(
                'BridgeOTAFormHelper',
                'TryUpdateOTAFormLists',
                fn (): mixed => $this->TryUpdateOTAFormLists(),
                'Message=VM_UPDATE'
            );
            return;
        }

        if (
            \in_array($Message, [OM_CHILDADDED, OM_CHILDREMOVED], true) &&
            \in_array($SenderID, $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_DEVICES), true)
        ) {
            $this->TraceHelperCall(
                'BridgeOTAFormHelper',
                'SynchronizeOTAMessageSubscriptions',
                fn (): mixed => $this->SynchronizeOTAMessageSubscriptions(),
                'Message=' . $Message
            );
        }
    }

    /**
     * Verarbeitet eingehende MQTT-Nachrichten unterhalb des Bridge-Topics.
     *
     * Der Symcon-Datenrahmen wird dekodiert und nach Bridge-Untertopic verteilt.
     * Unterstützt werden Logs, Ereignisse, Geräte- und Health-Diagnosen,
     * Request-Antworten, Netzwerkkarten, Bridge-Status und -Informationen sowie
     * die Liste installierter Extensions. Transaktionsantworten werden zuerst
     * dem gemeinsamen MQTT-Helper zugeordnet; OTA-Antworten und fachliche
     * Statusaktualisierungen folgen anschließend.
     *
     * @param string $JSONString Vom MQTT-Parent übergebener JSON-Datenrahmen.
     *
     * @return string Für die Symcon-Schnittstelle wird immer ein leerer String zurückgegeben.
     *
     * @see \BridgeDiagnosticHelper Diagnoseverarbeitung in `Bridge/Helper/BridgeDiagnosticHelper.php`.
     * @see \BridgePairingHelper Pairing-Status in `Bridge/Helper/BridgePairingHelper.php`.
     * @see \BridgeNetworkSecurityHelper Block- und Passlisten in `Bridge/Helper/BridgeNetworkSecurityHelper.php`.
     * @see \BridgeOTAFormHelper OTA-Antworten in `Bridge/Helper/BridgeOTAFormHelper.php`.
     * @see \Zigbee2MQTT\SendData Transaktionszuordnung und Payload-Dekodierung in `libs/MQTTHelper.php`.
     */
    public function ReceiveData(string $JSONString): string
    {
        if ($this->GetStatus() == IS_CREATING) {
            return '';
        }
        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if (empty($BaseTopic)) {
            return '';
        }
        $this->SendLimitedDebug('ReceiveData', $JSONString, 0);
        $Buffer = json_decode($JSONString, true);
        if (!isset($Buffer['Topic'])) {
            return '';
        }
        $ReceiveTopic = $Buffer['Topic'];
        $this->SendDebug('MQTT FullTopic', $ReceiveTopic, 0);
        $Topic = substr($ReceiveTopic, strlen($BaseTopic . '/bridge/'));
        $Topics = explode('/', $Topic);
        $Topic = array_shift($Topics);
        $this->SendDebug('MQTT Topic', $Topic, 0);
        $payloadJson = self::DecodePayload($Buffer['Payload']);
        $this->SendLimitedDebug('MQTT Payload', $payloadJson, 0);
        $Payload = json_decode($payloadJson, true);
        switch ($Topic) {
            case 'logging':
                if (\is_array($Payload)) {
                    $this->TraceHelperCall(
                        'BridgeDiagnosticHelper',
                        'AppendBridgeLog',
                        fn (): mixed => $this->AppendBridgeLog($Payload),
                        'Topic=logging'
                    );
                }
                break;
            case 'event':
                if (\is_array($Payload)) {
                    $this->TraceHelperCall(
                        'BridgeDiagnosticHelper',
                        'AppendBridgeEvent',
                        fn (): mixed => $this->AppendBridgeEvent($Payload),
                        'Topic=event'
                    );
                }
                break;
            case 'devices':
                if (\is_array($Payload)) {
                    $this->TraceHelperCall(
                        'BridgeDiagnosticHelper',
                        'UpdateDeviceDiagnostics',
                        fn (): mixed => $this->UpdateDeviceDiagnostics($Payload),
                        'Topic=devices'
                    );
                }
                break;
            case 'health':
                if (\is_array($Payload)) {
                    $this->TraceHelperCall(
                        'BridgeDiagnosticHelper',
                        'StoreHealthCheckResult',
                        fn (): mixed => $this->StoreHealthCheckResult($Payload),
                        'Topic=health'
                    );
                }
                break;
            case 'request': //nothing
                break;
            case 'response': //response from request
                if (isset($Payload['transaction']) && $this->TraceHelperCall(
                    'MQTTHelper',
                    'UpdateTransaction',
                    fn (): mixed => $this->UpdateTransaction($Payload),
                    'Topic=response'
                )) {
                    break;
                }
                if (\is_array($Payload) && $this->TraceHelperCall(
                    'MQTTHelper',
                    'UpdateTransactionByResponseTopic',
                    fn (): mixed => $this->UpdateTransactionByResponseTopic('/bridge/response/' . implode('/', $Topics), $Payload),
                    'Response=' . implode('/', $Topics)
                )) {
                    break;
                }
                if (\is_array($Payload) && $this->TraceHelperCall(
                    'BridgeOTAFormHelper',
                    'HandleOTAUpdateResponse',
                    fn (): mixed => $this->HandleOTAUpdateResponse($Payload, $Topics),
                    'Response=' . implode('/', $Topics)
                )) {
                    break;
                }
                if (is_array($Topics) && isset($Topics[0])) {
                    if ($Topics[0] == 'networkmap') {
                        if ($Payload['status'] == 'ok') {
                            $this->RegisterVariableString($Payload['data']['type'], $this->Translate('Network Map'));
                            $this->SetValue($Payload['data']['type'], $Payload['data']['value']);
                        }
                    }
                }
                break;
            case 'state':
                $this->SetValue('state', $Payload['state'] == 'online');
                break;
            case 'info':
                if (isset($Payload['log_level'])) {
                    $this->SetValue('log_level', $Payload['log_level']);
                }
                if (isset($Payload['permit_join'])) {
                    $this->SetValue('permit_join', $Payload['permit_join']);
                }
                if (array_key_exists('permit_join_end', $Payload)) {
                    $this->SetValue('permit_join_end', $this->TraceHelperCall(
                        'BridgePairingHelper',
                        'NormalizePermitJoinEnd',
                        fn (): mixed => $this->NormalizePermitJoinEnd($Payload['permit_join_end']),
                        'Topic=info'
                    ));
                }
                if (array_key_exists('permit_join', $Payload) && $Payload['permit_join'] === false) {
                    $this->PermitJoinTarget = '';
                    $this->SetValue('permit_join_end', 0);
                    $this->SetValue('permit_join_target', '');
                }
                if (isset($Payload['permit_join']) || array_key_exists('permit_join_end', $Payload)) {
                    $this->TraceHelperCall(
                        'BridgePairingHelper',
                        'UpdatePermitJoinStatus',
                        fn (): mixed => $this->UpdatePermitJoinStatus(),
                        'Topic=info'
                    );
                }
                if (isset($Payload['restart_required'])) {
                    $this->SetValue('restart_required', $Payload['restart_required']);
                }
                if (isset($Payload['version'])) {
                    $this->SetValue('version', $Payload['version']);
                }
                if (isset($Payload['config']['permit_join'])) {
                    $this->ConfigPermitJoin = $Payload['config']['permit_join'];
                    $this->TryUpdateFormField('PermitJoinOption', 'visible', $Payload['config']['permit_join']);
                    if ($Payload['config']['permit_join']) {
                        $this->LogMessage($this->Translate("Danger! In the Zigbee2MQTT configuration permit_join is activated.\r\nThis leads to a possible security risk!"), KL_ERROR);
                    }
                }
                if (isset($Payload['zigbee_herdsman_converters']['version'])) {
                    $this->SetValue('zigbee_herdsman_converters', $Payload['zigbee_herdsman_converters']['version']);
                }
                if (isset($Payload['zigbee_herdsman']['version'])) {
                    $this->installedZhVersion = $Payload['zigbee_herdsman']['version'];
                    if (isset(self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])) {
                        $Extension = file_get_contents(dirname(__DIR__) . '/libs/' . self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion]);
                        preg_match('/Version: (.*)/', $Extension, $matches);
                        if (isset($matches[1])) {
                            $this->actualExtensionVersion = (float) $matches[1];
                        }
                    } else {
                        $this->actualExtensionVersion = 0;
                    }
                    $this->SetValue('zigbee_herdsman', $Payload['zigbee_herdsman']['version']);
                }
                if (isset($Payload['config']['advanced']['last_seen'])) {
                    $this->ConfigLastSeen = $Payload['config']['advanced']['last_seen'];
                    if ($Payload['config']['advanced']['last_seen'] == 'epoch') {
                        $this->TryUpdateFormField('SetLastSeen', 'caption', $this->Translate('last_seen setting is correct'));
                        $this->TryUpdateFormField('SetLastSeen', 'enabled', false);
                    } else {
                        $this->TryUpdateFormField('SetLastSeen', 'caption', $this->Translate('Set last_seen setting to epoch'));
                        $this->TryUpdateFormField('SetLastSeen', 'enabled', true);
                        $this->LogMessage($this->Translate('Wrong last_seen setting in Zigbee2MQTT. Please set last_seen to epoch.'), KL_ERROR);
                    }
                }
                if (isset($Payload['config']) && \is_array($Payload['config'])) {
                    $networkLists = $this->TraceHelperCall(
                        'BridgeNetworkSecurityHelper',
                        'NormalizeNetworkSecurityDeviceList',
                        fn (): array => [
                            'blocklist' => $this->NormalizeNetworkSecurityDeviceList($Payload['config']['blocklist'] ?? []),
                            'passlist'  => $this->NormalizeNetworkSecurityDeviceList($Payload['config']['passlist'] ?? [])
                        ],
                        'Topic=info'
                    );
                    $this->WriteAttributeArray(self::ATTRIBUTE_CONFIG_BLOCKLIST, $networkLists['blocklist']);
                    $this->WriteAttributeArray(self::ATTRIBUTE_CONFIG_PASSLIST, $networkLists['passlist']);
                }
                if (isset($Payload['network'])) {
                    $this->SetValue('network_channel', $Payload['network']['channel']);
                }
                break;
            case 'extensions':
                if (!is_array($Payload)) {
                    break;
                }
                $foundExtension = false;
                $Version = 'unknown';
                foreach ($Payload as $Extension) {
                    if (strpos($Extension['code'], 'class IPSymconExtension') !== false) {
                        if ($foundExtension) {
                            $this->LogMessage($this->Translate("Danger! Several extensions for Symcon have been found.\r\nPlease delete outdated versions manually to avoid malfunctions."), KL_ERROR);
                            continue;
                        }
                        $foundExtension = true;
                        $this->ExtensionFilename = $Extension['name'];
                        $this->SendDebug('Found Extension', $this->ExtensionFilename, 0);
                        preg_match('/Version: (.*)/', $Extension['code'], $matches);
                        if (isset($matches[1])) {
                            $Version = $matches[1];
                        }
                        if ($this->actualExtensionVersion <= (float) $Version) {
                            $this->TryUpdateFormField('InstallExtension', 'caption', $this->Translate('Symcon-Extension is up-to-date'));
                            $this->TryUpdateFormField('InstallExtension', 'enabled', false);
                        } else {
                            $this->TryUpdateFormField('InstallExtension', 'caption', $this->Translate('Install or upgrade Symcon-Extension'));
                            $this->TryUpdateFormField('InstallExtension', 'enabled', true);
                            $this->LogMessage($this->Translate('Symcon Extension in Zigbee2MQTT is outdated. Please update the extension.'), KL_ERROR);
                        }
                    }
                }
                $this->SetValue('extension_loaded', $foundExtension);
                $this->SetValue('extension_version', $Version);
                $this->SetValue('extension_is_current', $this->actualExtensionVersion == (float) $Version);
                if (!$foundExtension) {
                    $this->LogMessage($this->Translate('No Symcon Extension in Zigbee2MQTT installed. Please install the extension.'), KL_ERROR);
                }
                break;
        }
        return '';
    }

    /**
     * Verteilt Variablen- und Formularaktionen auf die zuständigen Bridge-Helper.
     *
     * Dazu gehören Bridge-Konfiguration und Neustart, Pairing, Diagnose, Backup,
     * Installcodes, Touchlink, Netzwerklisten, Variablenwartung und OTA. Die
     * Methode selbst enthält nur die zentrale Zuordnung der Aktionskennung.
     *
     * @param string $ident Kennung der Variable oder Formularaktion.
     * @param mixed  $value Von Symcon übergebener Aktionswert oder Formular-Payload.
     *
     * @see \BridgeConfigurationCommandHelper Konfigurationsaktionen in `Bridge/Helper/BridgeConfigurationCommandHelper.php`.
     * @see \BridgePairingHelper Pairing-Aktionen in `Bridge/Helper/BridgePairingHelper.php`.
     * @see \BridgeDiagnosticHelper Diagnoseaktionen in `Bridge/Helper/BridgeDiagnosticHelper.php`.
     * @see \BridgeBackupHelper Backup-Erstellung in `Bridge/Helper/BridgeBackupHelper.php`.
     * @see \BridgeInstallCodeHelper Installcode-Verwaltung in `Bridge/Helper/BridgeInstallCodeHelper.php`.
     * @see \BridgeTouchlinkHelper Touchlink-Aktionen in `Bridge/Helper/BridgeTouchlinkHelper.php`.
     * @see \BridgeNetworkSecurityHelper Netzwerklisten in `Bridge/Helper/BridgeNetworkSecurityHelper.php`.
     * @see \BridgeStaleVariableHelper Variablenwartung in `Bridge/Helper/BridgeStaleVariableHelper.php`.
     * @see \BridgeOTACommandHelper OTA-Befehle in `Bridge/Helper/BridgeOTACommandHelper.php`.
     * @see \BridgeOTAFormHelper OTA-Formularaktionen in `Bridge/Helper/BridgeOTAFormHelper.php`.
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        $helper = match ($ident) {
            'permit_join', 'StopPairing', 'log_level', 'restart_request'      => 'BridgeConfigurationCommandHelper',
            'StartPairing', 'UpdatePermitJoinStatus'                          => 'BridgePairingHelper',
            'ClearBridgeDiagnostics', 'RunHealthCheck', 'RunCoordinatorCheck' => 'BridgeDiagnosticHelper',
            'CreateBackupFile'                                                => 'BridgeBackupHelper',
            'SendInstallCode', 'SaveInstallCode', 'SelectStoredInstallCode', 'SendStoredInstallCode',
            'RequestDeleteStoredInstallCode', 'ConfirmDeleteStoredInstallCode'                     => 'BridgeInstallCodeHelper',
            'TouchlinkScan', 'SelectTouchlinkDevice', 'TouchlinkIdentify', 'TouchlinkFactoryReset' => 'BridgeTouchlinkHelper',
            'ExecuteBridgeExpertAction'                                                            => 'BridgeRequestHelper',
            'SelectNetworkSecurityDevice', 'RefreshNetworkSecurityAvailableDevices', 'AddBlocklistDevice',
            'RemoveBlocklistDevice', 'RequestPasslistChange', 'ConfirmPendingPasslistChange' => 'BridgeNetworkSecurityHelper',
            'ScanStaleVariables', 'SelectStaleVariableMaintenanceInstance'                   => 'BridgeStaleVariableHelper',
            'RefreshOTAStatus', 'CheckOTAUpdate', 'RequestOTAUpdate', 'ConfirmOTAUpdate',
            'ScheduleOTAUpdate', 'UnscheduleOTAUpdate', 'AbortOTAUpdate' => 'BridgeOTAFormHelper',
            default                                                      => 'BridgeModule'
        };

        $this->TraceHelperCall($helper, $ident, function () use ($ident, $value): void
        {
            switch ($ident) {
                case 'permit_join':
                    $this->SetPermitJoin((bool) $value);
                    break;
                case 'StartPairing':
                    $this->StartPairingFromForm($value);
                    break;
                case 'StopPairing':
                    $this->SetPermitJoinTarget(0);
                    break;
                case 'UpdatePermitJoinStatus':
                    $this->UpdatePermitJoinStatus();
                    break;
                case 'log_level':
                    $this->SetLogLevel((string) $value);
                    break;
                case 'restart_request':
                    $this->Restart();
                    break;
                case 'ClearBridgeDiagnostics':
                    $this->ClearBridgeDiagnostics();
                    break;
                case 'RunHealthCheck':
                    $this->RunHealthCheckFromForm();
                    break;
                case 'RunCoordinatorCheck':
                    $this->RunCoordinatorCheckFromForm();
                    break;
                case 'CreateBackupFile':
                    $this->CreateBackupFileFromForm();
                    break;
                case 'SendInstallCode':
                    $this->SendInstallCodeFromForm($value);
                    break;
                case 'SaveInstallCode':
                    $this->SaveInstallCodeFromForm($value);
                    break;
                case 'SelectStoredInstallCode':
                    $this->SelectStoredInstallCodeFromForm($value);
                    break;
                case 'SendStoredInstallCode':
                    $this->SendStoredInstallCodeFromForm($value);
                    break;
                case 'RequestDeleteStoredInstallCode':
                    $this->RequestDeleteStoredInstallCodeFromForm($value);
                    break;
                case 'ConfirmDeleteStoredInstallCode':
                    $this->ConfirmPendingStoredInstallCodeDelete();
                    break;
                case 'TouchlinkScan':
                    $this->TouchlinkScan();
                    $this->TryUpdateFormField('TouchlinkDeviceList', 'values', json_encode($this->BuildTouchlinkDeviceFormValues()));
                    break;
                case 'SelectTouchlinkDevice':
                    $this->SelectTouchlinkDeviceFromForm($value);
                    break;
                case 'TouchlinkIdentify':
                    $target = $this->DecodeBridgeFormPayload($value);
                    if ($target !== null) {
                        $this->TouchlinkIdentify((string) ($target['ieee_address'] ?? ''), (int) ($target['channel'] ?? 0));
                    }
                    break;
                case 'TouchlinkFactoryReset':
                    $target = $this->DecodeBridgeFormPayload($value);
                    if ($target !== null) {
                        $this->TouchlinkFactoryReset((string) ($target['ieee_address'] ?? ''), (int) ($target['channel'] ?? 0));
                    }
                    break;
                case 'ExecuteBridgeExpertAction':
                    $this->ExecuteBridgeExpertActionFromForm($value);
                    break;
                case 'SelectNetworkSecurityDevice':
                    $this->SelectNetworkSecurityDeviceFromForm($value);
                    break;
                case 'RefreshNetworkSecurityAvailableDevices':
                    $this->UpdateNetworkSecurityFormLists();
                    break;
                case 'AddBlocklistDevice':
                    $this->AddNetworkSecurityDeviceFromForm('blocklist', $value);
                    break;
                case 'RemoveBlocklistDevice':
                    $this->RemoveNetworkSecurityDeviceFromForm('blocklist', $value);
                    break;
                case 'RequestPasslistChange':
                    $this->RequestPasslistChangeFromForm($value);
                    break;
                case 'ConfirmPendingPasslistChange':
                    $this->ApplyPendingPasslistChange();
                    break;
                case 'ScanStaleVariables':
                    $this->ScanStaleVariablesFromForm();
                    break;
                case 'SelectStaleVariableMaintenanceInstance':
                    $this->SelectStaleVariableMaintenanceInstanceFromForm($value);
                    break;
                case 'RefreshOTAStatus':
                    $this->UpdateOTAFormLists();
                    break;
                case 'CheckOTAUpdate':
                    $this->CheckOTAUpdateFromForm($value);
                    break;
                case 'RequestOTAUpdate':
                    $this->RequestOTAUpdateFromForm($value);
                    break;
                case 'ConfirmOTAUpdate':
                    $this->ConfirmPendingOTAUpdate();
                    break;
                case 'ScheduleOTAUpdate':
                    $this->ScheduleOTAUpdateFromForm($value);
                    break;
                case 'UnscheduleOTAUpdate':
                    $this->UnscheduleOTAUpdateFromForm($value);
                    break;
                case 'AbortOTAUpdate':
                    $this->AbortOTAUpdateFromForm($value);
                    break;
            }
        }, 'Ident=' . $ident);
    }

    /**
     * Erstellt das dynamisch ergänzte Konfigurationsformular der Bridge.
     *
     * Die statische `form.json` wird um den aktuellen Extension-, `last_seen`-
     * und Pairing-Zustand sowie um Diagnose-, Netzwerk-, Installcode-, Touchlink-,
     * OTA- und Variablenwartungsdaten ergänzt.
     *
     * @return string JSON-kodiertes Symcon-Konfigurationsformular.
     *
     * @see self::BuildBridgeConfigurationForm()
     * @see \BridgeOTAFormHelper OTA-Formulardaten in `Bridge/Helper/BridgeOTAFormHelper.php`.
     * @see \BridgeDiagnosticHelper Diagnosedaten in `Bridge/Helper/BridgeDiagnosticHelper.php`.
     * @see \BridgeNetworkSecurityHelper Netzwerkdaten in `Bridge/Helper/BridgeNetworkSecurityHelper.php`.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetValue('extension_loaded') && $this->GetValue('extension_is_current')) {
            $this->SetBridgeFormField($Form, 'InstallExtension', 'enabled', false);
            $this->SetBridgeFormField($Form, 'InstallExtension', 'caption', $this->Translate('Symcon-Extension is up-to-date'));
        }
        if ($this->ConfigLastSeen == 'epoch') {
            $this->SetBridgeFormField($Form, 'SetLastSeen', 'enabled', false);
            $this->SetBridgeFormField($Form, 'SetLastSeen', 'caption', $this->Translate('last_seen setting is correct'));
        }
        if ($this->ConfigPermitJoin) {
            $this->SetBridgeFormField($Form, 'PermitJoinOption', 'visible', true);
        }
        return json_encode($this->TraceHelperCall(
            'BridgeModule',
            'BuildBridgeConfigurationForm',
            fn (): mixed => $this->BuildBridgeConfigurationForm($Form)
        ));
    }

    /**
     * Fordert eine Netzwerkkarte im Graphviz-Format einschließlich Routen an.
     *
     * @return bool `true`, wenn Zigbee2MQTT den Befehl erfolgreich bestätigt hat.
     *
     * @see \BridgeRequestHelper Versand des Bridge-Befehls in `Bridge/Helper/BridgeRequestHelper.php`.
     */
    public function RequestNetworkmap(): bool
    {
        $Topic = '/bridge/request/networkmap';
        $Payload = ['type' => 'graphviz', 'routes' => true];
        return $this->TraceHelperCall(
            'BridgeRequestHelper',
            'SendBridgeCommand',
            fn (): mixed => $this->SendBridgeCommand($Topic, $Payload),
            'Request=networkmap'
        );
    }

    /**
     * Sendet eine generische Zigbee2MQTT-Aktion an `bridge/request/action`.
     *
     * Aktionsname und Parameter werden normalisiert. Vom Aufrufer übergebene
     * Felder für `transaction` und `action` werden entfernt, damit die
     * Transaktionsverwaltung und der angegebene Aktionsname verbindlich bleiben.
     *
     * @param string $Action Name der Zigbee2MQTT-Aktion.
     * @param array  $Params Inhalt des `params`-Objekts ohne `transaction` und `action`.
     *
     * @return bool `true`, wenn die Bridge die Aktion bestätigt hat.
     *
     * @see \BridgeRequestHelper Geschützter Request in `Bridge/Helper/BridgeRequestHelper.php`.
     */
    public function SendBridgeAction(string $Action, array $Params = []): bool
    {
        $action = trim($Action);
        if ($action === '') {
            trigger_error($this->Translate('Action name is required.'), E_USER_NOTICE);
            return false;
        }

        unset($Params['transaction'], $Params['action']);
        $payload = [
            'action' => $action,
            'params' => (object) $Params
        ];

        return $this->TraceHelperCall(
            'BridgeRequestHelper',
            'SendCheckedSensitiveBridgeRequest',
            fn (): mixed => $this->SendCheckedSensitiveBridgeRequest('/bridge/request/action', $payload, 30000),
            'Request=action'
        ) !== false;
    }

    /**
     * Setzt einen Bridge-Variablenwert kompatibel mit `IPSModuleStrict` und der Testumgebung.
     *
     * Nicht vorhandene Variablen führen zu `false`. Innerhalb der PHPUnit-Stubs
     * wird die globale Symcon-Funktion verwendet, im Produktivbetrieb die
     * Implementierung der Basisklasse.
     *
     * @param string $Ident Identifikator der Bridge-Variable.
     * @param mixed  $Value Zu speichernder Wert.
     *
     * @return bool `true`, wenn der Wert gesetzt werden konnte.
     */
    protected function SetValue(string $Ident, mixed $Value): bool
    {
        $variableID = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($variableID === false) {
            return false;
        }

        if (\defined('PHPUNIT_TESTSUITE') && \constant('PHPUNIT_TESTSUITE')) {
            \SetValue($variableID, $Value);
            return true;
        }

        return parent::SetValue($Ident, $Value);
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
     * Erstellt eine native Symcon-Aufzählungsdarstellung für Bridge-Variablen.
     *
     * Auf Symcon-Versionen ohne entsprechende Darstellungskonstante wird als
     * kompatibler Rückfall ein leerer Profilname geliefert.
     *
     * @param array  $options Aufzählungsoptionen im Format der nativen Symcon-Darstellung.
     * @param string $icon    Name des anzuzeigenden Icons.
     * @param int    $layout  Symcon-Layoutkennung der Aufzählung.
     *
     * @return string|array Leerer Profilname oder Konfiguration der nativen Darstellung.
     */
    private function BuildBridgeEnumerationPresentation(array $options, string $icon = 'list', int $layout = 0): string|array
    {
        if (!\defined('VARIABLE_PRESENTATION_ENUMERATION')) {
            return '';
        }

        return [
            'PRESENTATION' => \constant('VARIABLE_PRESENTATION_ENUMERATION'),
            'OPTIONS'      => json_encode($options),
            'LAYOUT'       => $layout,
            'DISPLAY'      => 0,
            'ICON'         => $icon
        ];
    }

    /**
     * Erstellt eine einzelne Option für eine native Bridge-Aufzählung.
     *
     * @param mixed  $value   Zu übermittelnder Optionswert.
     * @param string $caption Übersetzungsschlüssel der Beschriftung.
     * @param int    $color   Symcon-Farbwert oder `-1` für die Standardfarbe.
     *
     * @return array{Value:mixed,Caption:string,IconActive:bool,IconValue:string,Color:int}
     */
    private function BuildBridgeEnumerationOption(mixed $value, string $caption, int $color = -1): array
    {
        return [
            'Value'      => $value,
            'Caption'    => $this->Translate($caption),
            'IconActive' => false,
            'IconValue'  => '',
            'Color'      => $color
        ];
    }

    /**
     * Baut das gemeinsame Payload für Binding- und Unbinding-Anfragen auf.
     *
     * Cluster können als JSON-Liste oder kommaseparierter Text angegeben werden.
     * `skip_disable_reporting` wird nur bei ausdrücklicher Auswahl übertragen.
     *
     * @param string $SourceDevice         Quellgerät oder Quellendpunkt.
     * @param string $TargetDevice         Zielgerät, Zielendpunkt oder Gruppe.
     * @param string $ClustersJSON         Optionale Clusterliste.
     * @param bool   $SkipDisableReporting Reporting beim Unbinding nicht deaktivieren.
     *
     * @return array Für Zigbee2MQTT normalisiertes Request-Payload.
     *
     * @see \BridgeGroupSceneCommandHelper Binding-Befehle in `Bridge/Helper/BridgeGroupSceneCommandHelper.php`.
     */
    private function BuildBindingPayload(string $SourceDevice, string $TargetDevice, string $ClustersJSON, bool $SkipDisableReporting): array
    {
        $payload = [
            'from' => $SourceDevice,
            'to'   => $TargetDevice
        ];

        $clusters = $this->ParseStringList($ClustersJSON);
        if ($clusters !== []) {
            $payload['clusters'] = $clusters;
        }
        if ($SkipDisableReporting) {
            $payload['skip_disable_reporting'] = true;
        }

        return $payload;
    }

    /**
     * Normalisiert ein JSON-Array oder eine kommaseparierte Zeichenkette zu einer Liste.
     *
     * Leere Einträge werden entfernt und alle verbleibenden Werte beschnitten.
     *
     * @param string $Value Zu parsende Liste.
     *
     * @return string[] Normalisierte, möglicherweise leere Stringliste.
     *
     * @see \BridgeDeviceCommandHelper Attributlisten in `Bridge/Helper/BridgeDeviceCommandHelper.php`.
     * @see self::BuildBindingPayload()
     */
    private function ParseStringList(string $Value): array
    {
        $Value = trim($Value);
        if ($Value === '') {
            return [];
        }

        $decoded = json_decode($Value, true);
        if (\is_array($decoded) && array_is_list($decoded)) {
            return array_values(array_filter(array_map(
                static fn (mixed $entry): string => trim((string) $entry),
                $decoded
            ), static fn (string $entry): bool => $entry !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', $Value)), static fn (string $entry): bool => $entry !== ''));
    }

    /**
     * Dekodiert ein optionales JSON-Objekt aus einem Formularfeld.
     *
     * Eine leere Eingabe ergibt ein leeres Array. Andere gültige JSON-Typen
     * werden mit einem Symcon-Hinweis abgewiesen.
     *
     * @param string $OptionsJSON JSON-Text oder leere Eingabe.
     *
     * @return array Dekodiertes Objekt als assoziatives Array.
     *
     * @see \BridgeDeviceCommandHelper Geräteoptionen in `Bridge/Helper/BridgeDeviceCommandHelper.php`.
     */
    private function ParseOptionalJsonObject(string $OptionsJSON): array
    {
        $OptionsJSON = trim($OptionsJSON);
        if ($OptionsJSON === '') {
            return [];
        }

        $decoded = json_decode($OptionsJSON, true);
        if (!\is_array($decoded) || !str_starts_with($OptionsJSON, '{')) {
            trigger_error($this->Translate('Options must be a JSON object.'), E_USER_NOTICE);
            return [];
        }

        return $decoded;
    }

    /**
     * Dekodiert ein verpflichtendes JSON-Objekt aus einem Formularfeld.
     *
     * Ungültige Eingaben und andere JSON-Typen lösen den übergebenen
     * übersetzbaren Symcon-Hinweis aus.
     *
     * @param string $JSON         Zu dekodierender JSON-Text.
     * @param string $ErrorMessage Übersetzungsschlüssel der Fehlermeldung.
     *
     * @return array|null Dekodiertes Objekt oder `null` bei ungültiger Eingabe.
     *
     * @see \BridgeGroupSceneCommandHelper Gruppen- und Szenenoptionen in `Bridge/Helper/BridgeGroupSceneCommandHelper.php`.
     */
    private function ParseRequiredJsonObject(string $JSON, string $ErrorMessage): ?array
    {
        $JSON = trim($JSON);
        $decoded = json_decode($JSON, true);
        if (!\is_array($decoded) || !str_starts_with($JSON, '{')) {
            trigger_error($this->Translate($ErrorMessage), E_USER_NOTICE);
            return null;
        }

        return $decoded;
    }

    /**
     * Wandelt einen einfachen Formularwert in den passenden skalaren PHP-Typ um.
     *
     * Gültige skalare JSON-Werte werden direkt übernommen. Zahlen mit deutschem
     * Dezimalkomma werden als Integer oder Float interpretiert; alle übrigen
     * Eingaben bleiben Strings.
     *
     * @param string $Value Zu konvertierender Formularwert.
     *
     * @return bool|int|float|string|null Konvertierter skalarer Wert.
     *
     * @see \BridgeDeviceCommandHelper Geräteparameter in `Bridge/Helper/BridgeDeviceCommandHelper.php`.
     */
    private function ParseBridgeScalarValue(string $Value): mixed
    {
        $Value = trim($Value);
        $decoded = json_decode($Value, true);
        if (json_last_error() === JSON_ERROR_NONE && !\is_array($decoded)) {
            return $decoded;
        }

        $normalized = str_replace(',', '.', $Value);
        if (is_numeric($normalized)) {
            return str_contains($normalized, '.') ? (float) $normalized : (int) $normalized;
        }

        return $Value;
    }

    /**
     * Ergänzt das statische Bridge-Formular um sämtliche aktuellen Laufzeitdaten.
     *
     * Eingetragen werden Pairing-Ziele, Netzwerklisten, Diagnoseergebnisse,
     * Installcodes, Touchlink-Geräte, OTA-Status und Ergebnisse der
     * Variablenwartung. Gleichzeitig werden die benötigten OTA-Abonnements
     * synchronisiert und Tabellenhöhen an ihren Inhalt angepasst.
     *
     * @param array $form Dekodierte statische `form.json`.
     *
     * @return array Vollständig aufgebautes Bridge-Formular.
     *
     * @see \BridgePairingHelper Pairing-Daten in `Bridge/Helper/BridgePairingHelper.php`.
     * @see \BridgeNetworkSecurityHelper Netzwerklisten in `Bridge/Helper/BridgeNetworkSecurityHelper.php`.
     * @see \BridgeDiagnosticHelper Diagnosedaten in `Bridge/Helper/BridgeDiagnosticHelper.php`.
     * @see \BridgeInstallCodeHelper Installcode-Katalog in `Bridge/Helper/BridgeInstallCodeHelper.php`.
     * @see \BridgeTouchlinkHelper Touchlink-Geräte in `Bridge/Helper/BridgeTouchlinkHelper.php`.
     * @see \BridgeOTAFormHelper OTA-Daten in `Bridge/Helper/BridgeOTAFormHelper.php`.
     * @see \BridgeStaleVariableHelper Variablenwartung in `Bridge/Helper/BridgeStaleVariableHelper.php`.
     */
    private function BuildBridgeConfigurationForm(array $form): array
    {
        $this->TraceHelperCall(
            'BridgeOTAFormHelper',
            'SynchronizeOTAMessageSubscriptions',
            fn (): mixed => $this->SynchronizeOTAMessageSubscriptions(),
            'Form=Bridge'
        );
        $networkSecurityDevices = $this->TraceHelperCall(
            'BridgeNetworkSecurityHelper',
            'BuildNetworkSecurityDevices',
            fn (): mixed => $this->BuildNetworkSecurityDevices(),
            'Form=Bridge'
        );
        $this->SetBridgeFormField($form, 'PairingTarget', 'options', $this->TraceHelperCall(
            'BridgePairingHelper',
            'BuildPairingTargetOptions',
            fn (): mixed => $this->BuildPairingTargetOptions($networkSecurityDevices),
            'Form=Bridge'
        ));
        $this->SetBridgeFormField($form, 'PairingStatus', 'caption', $this->TraceHelperCall(
            'BridgePairingHelper',
            'BuildPairingStatusCaption',
            fn (): mixed => $this->BuildPairingStatusCaption($networkSecurityDevices),
            'Form=Bridge'
        ));
        $this->SetBridgeFormField($form, 'StartPairing', 'enabled', !$this->GetValue('permit_join'));
        $this->SetBridgeFormField($form, 'StopPairing', 'enabled', $this->GetValue('permit_join'));
        $availableDevices = $this->TraceHelperCall(
            'BridgeNetworkSecurityHelper',
            'BuildNetworkSecurityAvailableDeviceFormValues',
            fn (): mixed => $this->BuildNetworkSecurityAvailableDeviceFormValues($networkSecurityDevices),
            'Form=Bridge'
        );
        $this->SetBridgeFormField($form, 'NetworkSecurityAvailableDeviceList', 'values', $availableDevices);
        $this->SetBridgeFormField($form, 'NetworkSecurityAvailableDeviceList', 'rowCount', min(10, max(4, \count($availableDevices) + 1)));
        $this->SetBridgeFormField($form, 'NetworkSecurityBlocklist', 'values', $this->TraceHelperCall(
            'BridgeNetworkSecurityHelper',
            'BuildNetworkSecurityListFormValues',
            fn (): mixed => $this->BuildNetworkSecurityListFormValues('blocklist', $networkSecurityDevices),
            'List=blocklist'
        ));
        $this->SetBridgeFormField($form, 'NetworkSecurityPasslist', 'values', $this->TraceHelperCall(
            'BridgeNetworkSecurityHelper',
            'BuildNetworkSecurityListFormValues',
            fn (): mixed => $this->BuildNetworkSecurityListFormValues('passlist', $networkSecurityDevices),
            'List=passlist'
        ));
        $this->SetBridgeFormField($form, 'DiagnosticHealthStatus', 'caption', $this->TraceHelperCall(
            'BridgeDiagnosticHelper',
            'BuildHealthStatusCaption',
            fn (): mixed => $this->BuildHealthStatusCaption(),
            'Form=Bridge'
        ));
        $this->SetBridgeFormField($form, 'DiagnosticCoordinatorStatus', 'caption', $this->TraceHelperCall(
            'BridgeDiagnosticHelper',
            'BuildCoordinatorStatusCaption',
            fn (): mixed => $this->BuildCoordinatorStatusCaption(),
            'Form=Bridge'
        ));
        $this->SetBridgeFormField($form, 'DiagnosticMissingRoutersList', 'values', $this->TraceHelperCall(
            'BridgeDiagnosticHelper',
            'BuildMissingRouterFormValues',
            fn (): mixed => $this->BuildMissingRouterFormValues(),
            'Form=Bridge'
        ));
        $this->SetBridgeFormField($form, 'DiagnosticUnsupportedDevicesList', 'values', $this->BuildDeviceDiagnosticFormValues(self::ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES));
        $this->SetBridgeFormField($form, 'DiagnosticInterviewDevicesList', 'values', $this->BuildDeviceDiagnosticFormValues(self::ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES));
        $this->SetBridgeFormField($form, 'DiagnosticEventList', 'values', $this->BuildEventFormValues());
        $this->SetBridgeFormField($form, 'DiagnosticLogList', 'values', $this->BuildLogFormValues());
        $storedInstallCodes = $this->TraceHelperCall(
            'BridgeInstallCodeHelper',
            'BuildStoredInstallCodeFormValues',
            fn (): mixed => $this->BuildStoredInstallCodeFormValues(),
            'Form=Bridge'
        );
        $this->SetBridgeFormField($form, 'StoredInstallCodeList', 'values', $storedInstallCodes);
        $this->SetBridgeFormField($form, 'StoredInstallCodeList', 'rowCount', min(8, max(3, \count($storedInstallCodes) + 1)));
        $this->SetBridgeFormField($form, 'TouchlinkDeviceList', 'values', $this->TraceHelperCall(
            'BridgeTouchlinkHelper',
            'BuildTouchlinkDeviceFormValues',
            fn (): mixed => $this->BuildTouchlinkDeviceFormValues(),
            'Form=Bridge'
        ));
        $otaRows = $this->TraceHelperCall(
            'BridgeOTAFormHelper',
            'BuildOTADeviceRows',
            fn (): mixed => $this->BuildOTADeviceRows(),
            'Form=Bridge'
        );
        $this->SetBridgeFormField($form, 'OTAStatus', 'caption', $this->TraceHelperCall(
            'BridgeOTAFormHelper',
            'BuildOTAStatusCaption',
            fn (): mixed => $this->BuildOTAStatusCaption($otaRows),
            'Form=Bridge'
        ));
        $this->SetBridgeFormField($form, 'OTAKnownDevices', 'values', $this->TraceHelperCall(
            'BridgeOTAFormHelper',
            'BuildOTAKnownDeviceFormValues',
            fn (): mixed => $this->BuildOTAKnownDeviceFormValues($otaRows),
            'Form=Bridge'
        ));
        $this->SetBridgeFormField($form, 'OTAKnownDevices', 'rowCount', min(10, max(3, \count($otaRows) + 1)));
        $availableOTARows = $this->FilterOTADeviceRowsByState($otaRows, ['available']);
        $this->SetBridgeFormField($form, 'OTAAvailableUpdates', 'values', $this->BuildOTAAvailableUpdateFormValues($availableOTARows));
        $this->SetBridgeFormField($form, 'OTAAvailableUpdates', 'rowCount', min(8, max(3, \count($availableOTARows) + 1)));
        $activeOTARows = $this->FilterOTADeviceRowsByState($otaRows, ['requested', 'scheduled', 'updating']);
        $this->SetBridgeFormField($form, 'OTAActiveUpdates', 'values', $this->BuildOTAActiveUpdateFormValues($activeOTARows));
        $this->SetBridgeFormField($form, 'OTAActiveUpdates', 'rowCount', min(8, max(3, \count($activeOTARows) + 1)));
        $this->SetBridgeFormField($form, 'OTAUpdateResults', 'values', $this->BuildOTAUpdateResultFormValues());
        $staleVariableScan = $this->TraceHelperCall(
            'BridgeStaleVariableHelper',
            'ReadStaleVariableScan',
            fn (): mixed => $this->ReadStaleVariableScan(),
            'Form=Bridge'
        );
        $this->SetBridgeFormField($form, 'StaleVariableStatus', 'caption', $this->TraceHelperCall(
            'BridgeStaleVariableHelper',
            'BuildStaleVariableStatusCaption',
            fn (): mixed => $this->BuildStaleVariableStatusCaption(),
            'Form=Bridge'
        ));
        $staleVariableSummary = $this->TraceHelperCall(
            'BridgeStaleVariableHelper',
            'BuildStaleVariableInstanceSummaryFormValues',
            fn (): mixed => $this->BuildStaleVariableInstanceSummaryFormValues($staleVariableScan),
            'Form=Bridge'
        );
        $this->SetBridgeFormField($form, 'StaleVariableInstanceSummary', 'values', $staleVariableSummary);
        $this->SetBridgeFormField($form, 'StaleVariableInstanceSummary', 'rowCount', min(12, max(3, \count($staleVariableSummary) + 1)));
        return $form;
    }

    /**
     * Aktualisiert ein Formularfeld defensiv, sofern die Symcon-Schnittstelle verfügbar ist.
     *
     * Während eines Modul-Reloads kann `MessageSink()` durch `VM_UPDATE`
     * aufgerufen werden, obwohl das Instanzinterface vorübergehend fehlt. Warnungen
     * und Exceptions werden deshalb abgefangen und als Debugmeldung protokolliert.
     *
     * @param string $name  Name des Formularelements.
     * @param string $field Zu ändernde Feldeigenschaft.
     * @param mixed  $value Neuer Feldwert.
     *
     * @return bool `true`, wenn Symcon das Formularfeld erfolgreich aktualisiert hat.
     *
     * @see \BridgeOTAFormHelper OTA-Liveaktualisierung in `Bridge/Helper/BridgeOTAFormHelper.php`.
     * @see \BridgeDiagnosticHelper Diagnose-Liveaktualisierung in `Bridge/Helper/BridgeDiagnosticHelper.php`.
     * @see \BridgeNetworkSecurityHelper Aktualisierung der Netzwerklisten in `Bridge/Helper/BridgeNetworkSecurityHelper.php`.
     */
    private function TryUpdateFormField(string $name, string $field, mixed $value): bool
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool
        {
            $warning = $message;
            return true;
        });
        $updated = false;
        try {
            $updated = $this->UpdateFormField($name, $field, $value);
        } catch (\Throwable $exception) {
            $this->SendDebug(__FUNCTION__, 'Formularaktualisierung uebersprungen: ' . $exception->getMessage(), 0);
        } finally {
            restore_error_handler();
        }

        if ($warning !== null) {
            $this->SendDebug(__FUNCTION__, 'Formularaktualisierung uebersprungen: ' . $warning, 0);
        }

        return $updated && $warning === null;
    }

    /**
     * Validiert und versendet eine generische Bridge-Expertenaktion aus dem Formular.
     *
     * Aktionsname und optionales Parameterobjekt werden geprüft. Der Parametertext
     * muss ein JSON-Objekt sein; Arrays und skalare Werte sind nicht zulässig.
     * Erfolg oder Fehler werden direkt im Formular angezeigt.
     *
     * @param mixed $value Formular-Payload mit `action` und optionalem `params`-Text.
     *
     * @return bool `true`, wenn Zigbee2MQTT die Expertenaktion bestätigt hat.
     *
     * @see self::SendBridgeAction()
     * @see self::DecodeBridgeFormPayload()
     * @see self::ShowBridgeExpertActionMessage()
     */
    private function ExecuteBridgeExpertActionFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            $this->ShowBridgeExpertActionMessage('Bridge action failed', 'Action params must be a JSON object.');
            return false;
        }

        $action = trim((string) ($selection['action'] ?? ''));
        if ($action === '') {
            $this->ShowBridgeExpertActionMessage('Input required', 'Action name is required.');
            return false;
        }

        $paramsText = trim((string) ($selection['params'] ?? ''));
        $params = [];
        if ($paramsText !== '') {
            try {
                $decodedParams = json_decode($paramsText, true, 512, JSON_THROW_ON_ERROR);
                $decodedObject = json_decode($paramsText, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $this->ShowBridgeExpertActionMessage('Bridge action failed', 'Action params must be a JSON object.');
                return false;
            }

            if (!\is_array($decodedParams) || !($decodedObject instanceof \stdClass)) {
                $this->ShowBridgeExpertActionMessage('Bridge action failed', 'Action params must be a JSON object.');
                return false;
            }

            $params = $decodedParams;
        }

        if (!$this->SendBridgeAction($action, $params)) {
            $this->ShowBridgeExpertActionMessage('Bridge action failed', 'Zigbee2MQTT did not accept the expert action.');
            return false;
        }

        $this->TryUpdateFormField('BridgeExpertActionParams', 'value', '');
        $this->ShowBridgeExpertActionMessage('Bridge action executed', 'The Zigbee2MQTT expert action was sent successfully.');
        return true;
    }

    /**
     * Zeigt eine übersetzte Rückmeldung zu einer Bridge-Expertenaktion im Formular an.
     *
     * @param string $title   Übersetzungsschlüssel der Überschrift.
     * @param string $message Übersetzungsschlüssel des Meldungstextes.
     *
     * @see self::TryUpdateFormField()
     */
    private function ShowBridgeExpertActionMessage(string $title, string $message): void
    {
        $this->TryUpdateFormField('BridgeExpertActionMessageTitle', 'caption', $this->Translate($title));
        $this->TryUpdateFormField('BridgeExpertActionMessageText', 'caption', $this->Translate($message));
        $this->TryUpdateFormField('BridgeExpertActionMessage', 'visible', true);
    }

    /**
     * Normalisiert den Payload einer Bridge-Formularaktion zu einem Array.
     *
     * Bereits dekodierte Arrays werden unverändert übernommen. JSON-Strings
     * werden dekodiert; andere Typen und ungültige JSON-Daten ergeben `null`.
     * Diese zentrale Konvertierung wird von mehreren Bridge-Helpern verwendet.
     *
     * @param mixed $value Von Symcon übergebener Formularwert.
     *
     * @return array|null Dekodiertes Formular-Payload oder `null`.
     *
     * @see \BridgeInstallCodeHelper Formularaktionen in `Bridge/Helper/BridgeInstallCodeHelper.php`.
     * @see \BridgeNetworkSecurityHelper Formularaktionen in `Bridge/Helper/BridgeNetworkSecurityHelper.php`.
     * @see \BridgePairingHelper Formularaktionen in `Bridge/Helper/BridgePairingHelper.php`.
     * @see \BridgeTouchlinkHelper Formularaktionen in `Bridge/Helper/BridgeTouchlinkHelper.php`.
     * @see \BridgeOTAFormHelper Formularaktionen in `Bridge/Helper/BridgeOTAFormHelper.php`.
     * @see \BridgeStaleVariableHelper Formularaktionen in `Bridge/Helper/BridgeStaleVariableHelper.php`.
     */
    private function DecodeBridgeFormPayload(mixed $value): ?array
    {
        if (\is_array($value)) {
            return $value;
        }
        if (!\is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);
        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * Setzt rekursiv eine Eigenschaft eines benannten Elements im Formularbaum.
     *
     * Die Suche endet beim ersten passenden Element. Das Formular wird per
     * Referenz verändert und muss nicht erneut zurückgegeben werden.
     *
     * @param array  $node  Aktueller Knoten des Formularbaums.
     * @param string $name  Name des gesuchten Formularelements.
     * @param string $field Zu setzende Eigenschaft.
     * @param mixed  $value Neuer Eigenschaftswert.
     *
     * @return bool `true`, wenn das Element gefunden und geändert wurde.
     *
     * @see self::BuildBridgeConfigurationForm()
     */
    private function SetBridgeFormField(array &$node, string $name, string $field, mixed $value): bool
    {
        if (($node['name'] ?? null) === $name) {
            $node[$field] = $value;
            return true;
        }

        foreach ($node as &$child) {
            if (!\is_array($child)) {
                continue;
            }
            if ($this->SetBridgeFormField($child, $name, $field, $value)) {
                return true;
            }
        }

        return false;
    }

}
