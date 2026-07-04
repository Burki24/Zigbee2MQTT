<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/InstanceConnectionHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';
require_once dirname(__DIR__) . '/libs/MQTTHelper.php';
require_once dirname(__DIR__) . '/libs/AttributeArrayHelper.php';
require_once dirname(__DIR__) . '/libs/Maintenance/StaleVariableCleanupHelper.php';

/**
 * Zigbee2MQTTBridge
 *
 * @property float $actualExtensionVersion Enthält die benötigte Version der Extension passend zu Z2M in einem InstanzBuffer
 * @property float $installedZhVersion Enthält die installierte Version des zigbee-herdsman Moduls
 * @property string $ExtensionFilename Enthält den Dateinamen der Extension in einem InstanzBuffer
 * @property string $ConfigLastSeen Enthält die Z2M Konfiguration der LastSeen Option in einem InstanzBuffer
 * @property bool $ConfigPermitJoin Enthält die Z2M Konfiguration der PermitJoin Option in einem InstanzBuffer
 * @property string $PermitJoinTarget Enthält das zuletzt gewählte Pairing-Ziel in einem InstanzBuffer
 */
class Zigbee2MQTTBridge extends IPSModuleStrict
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\InstanceConnectionHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\SendData;
    use \Zigbee2MQTT\AttributeArrayHelper;

    /** @var array ZH Version zu Erweiterung  */
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
     * Create
     *
     * @uses IPSModule::Create()
     * @uses IPSModule::RegisterPropertyString()
     *
     * @return void
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
        $this->ClearTransactionData();
        $this->ConfigPermitJoin = false;
        $this->PermitJoinTarget = '';

        $this->RegisterPermitJoinTimer();

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
     * ApplyChanges
     *
     * @return void
     *
     * @uses IPSModule::ApplyChanges()
     * @uses IPSModule::ReadPropertyString()
     * @uses IPSModule::SetStatus()
     * @uses IPSModule::SetReceiveDataFilter()
     * @uses IPSModule::SetSummary()
     * @uses IPSModule::UnregisterVariable()
     * @uses IPSModule::RegisterVariableBoolean()
     * @uses IPSModule::RegisterVariableString()
     * @uses IPSModule::RegisterVariableInteger()
     * @uses IPSModule::EnableAction()
     * @uses IPSModule::HasActiveParent()
     * @uses IPSModule::UpdateFormField()
     * @uses IPSModule::GetValue()
     * @uses IPSModule::SetValue()
     * @uses IPSModule::Translate()
     * @uses Zigbee2MQTTBridge::RequestOptions()
     * @uses Zigbee2MQTTBridge::InstallSymconExtension()
     * @uses IPS_GetKernelRunlevel()
     */
    public function ApplyChanges(): void
    {
        // Empty TransactionQueue
        $this->ClearTransactionData();

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
        $this->UpdatePermitJoinStatus(false);

        $online = false;
        if (!empty($BaseTopic)) {
            if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                $online = @$this->RequestOptions(self::TIMEOUT_BRIDGE_APPLY_OPTIONS_REQUEST);
            }
        }
        $this->SendDebug('Online', $online ? 'true' : 'false', 0);
        $installedExtVersion = (empty($this->GetValue('extension_version')) ? -1 : (float) $this->GetValue('extension_version'));
        $this->SetValue('extension_is_current', $this->actualExtensionVersion <= $installedExtVersion);
        if ($this->actualExtensionVersion <= $installedExtVersion) {
            $this->UpdateFormField('InstallExtension', 'caption', $this->Translate('Symcon-Extension is up-to-date'));
            $this->UpdateFormField('InstallExtension', 'enabled', false);
        } else {
            $this->UpdateFormField('InstallExtension', 'caption', $this->Translate('Install or upgrade Symcon-Extension'));
            $this->UpdateFormField('InstallExtension', 'enabled', true);
            if (!empty($BaseTopic)) {
                if ($online) {
                    @$this->InstallSymconExtension();
                }
            }
        }
        $this->SynchronizeOTAMessageSubscriptions();
    }

    /**
     * Erstellt eine native Aufzaehlungsdarstellung fuer Bridge-Variablen.
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
     * Erstellt eine Option fuer native Bridge-Aufzaehlungen.
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
     * Aktualisiert die OTA-Übersicht bei Änderungen an beobachteten Device-Variablen.
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE && \in_array($SenderID, $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_VARIABLES), true)) {
            $this->UpdateOTAFormLists();
            return;
        }

        if (
            \in_array($Message, [OM_CHILDADDED, OM_CHILDREMOVED], true) &&
            \in_array($SenderID, $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_DEVICES), true)
        ) {
            $this->SynchronizeOTAMessageSubscriptions();
            $this->UpdateOTAFormLists();
        }
    }

    /**
     * ReceiveData
     *
     * @param  string $JSONString
     *
     * @return string
     *
     * @uses IPSModule::GetStatus()
     * @uses IPSModule::ReadPropertyString()
     * @uses IPSModule::RegisterVariableString()
     * @uses IPSModule::SendDebug()
     * @uses IPSModule::SetValue()
     * @uses IPSModule::Translate()
     * @uses IPSModule::UpdateFormField()
     * @uses IPSModule::LogMessage()
     * @uses Zigbee2MQTTBridge::UpdateTransaction()
     * @uses json_decode()
     * @uses strpos()
     * @uses substr()
     * @uses strlen()
     * @uses explode()
     * @uses array_shift()
     * @uses Zigbee2MQTTBridge::DecodePayload()
     * @uses file_get_contents()
     * @uses preg_match()
     * @uses isset()
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
                    $this->AppendBridgeLog($Payload);
                }
                break;
            case 'event':
                if (\is_array($Payload)) {
                    $this->AppendBridgeEvent($Payload);
                }
                break;
            case 'devices':
                if (\is_array($Payload)) {
                    $this->UpdateDeviceDiagnostics($Payload);
                }
                break;
            case 'health':
                if (\is_array($Payload)) {
                    $this->StoreHealthCheckResult($Payload);
                }
                break;
            case 'request': //nothing
                break;
            case 'response': //response from request
                if (isset($Payload['transaction']) && $this->UpdateTransaction($Payload)) {
                    break;
                }
                if (\is_array($Payload) && $this->UpdateTransactionByResponseTopic('/bridge/response/' . implode('/', $Topics), $Payload)) {
                    break;
                }
                if (\is_array($Payload) && $this->HandleOTAUpdateResponse($Payload, $Topics)) {
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
                    $this->SetValue('permit_join_end', $this->NormalizePermitJoinEnd($Payload['permit_join_end']));
                }
                if (array_key_exists('permit_join', $Payload) && $Payload['permit_join'] === false) {
                    $this->PermitJoinTarget = '';
                    $this->SetValue('permit_join_end', 0);
                    $this->SetValue('permit_join_target', '');
                }
                if (isset($Payload['permit_join']) || array_key_exists('permit_join_end', $Payload)) {
                    $this->UpdatePermitJoinStatus();
                }
                if (isset($Payload['restart_required'])) {
                    $this->SetValue('restart_required', $Payload['restart_required']);
                }
                if (isset($Payload['version'])) {
                    $this->SetValue('version', $Payload['version']);
                }
                if (isset($Payload['config']['permit_join'])) {
                    $this->ConfigPermitJoin = $Payload['config']['permit_join'];
                    $this->UpdateFormField('PermitJoinOption', 'visible', $Payload['config']['permit_join']);
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
                        $this->UpdateFormField('SetLastSeen', 'caption', $this->Translate('last_seen setting is correct'));
                        $this->UpdateFormField('SetLastSeen', 'enabled', false);
                    } else {
                        $this->UpdateFormField('SetLastSeen', 'caption', $this->Translate('Set last_seen setting to epoch'));
                        $this->UpdateFormField('SetLastSeen', 'enabled', true);
                        $this->LogMessage($this->Translate('Wrong last_seen setting in Zigbee2MQTT. Please set last_seen to epoch.'), KL_ERROR);
                    }
                }
                if (isset($Payload['config']) && \is_array($Payload['config'])) {
                    $this->WriteAttributeArray(
                        self::ATTRIBUTE_CONFIG_BLOCKLIST,
                        $this->NormalizeNetworkSecurityDeviceList($Payload['config']['blocklist'] ?? [])
                    );
                    $this->WriteAttributeArray(
                        self::ATTRIBUTE_CONFIG_PASSLIST,
                        $this->NormalizeNetworkSecurityDeviceList($Payload['config']['passlist'] ?? [])
                    );
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
                            $this->UpdateFormField('InstallExtension', 'caption', $this->Translate('Symcon-Extension is up-to-date'));
                            $this->UpdateFormField('InstallExtension', 'enabled', false);
                        } else {
                            $this->UpdateFormField('InstallExtension', 'caption', $this->Translate('Install or upgrade Symcon-Extension'));
                            $this->UpdateFormField('InstallExtension', 'enabled', true);
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
     * RequestAction
     *
     * @param  string $ident
     * @param  mixed $value
     * @return void
     *
     * @uses Zigbee2MQTTBridge::SetPermitJoin()
     * @uses Zigbee2MQTTBridge::SetLogLevel()
     * @uses Zigbee2MQTTBridge::Restart()
     */
    public function RequestAction(string $ident, mixed $value): void
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
                $this->UpdateFormField('TouchlinkDeviceList', 'values', json_encode($this->BuildTouchlinkDeviceFormValues()));
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
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     *
     * @uses IPSModule::GetValue()
     * @uses IPSModule::Translate()
     * @uses json_decode()
     * @uses json_encode()
     * @uses file_get_contents()
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
        return json_encode($this->BuildBridgeConfigurationForm($Form));
    }

    /**
     * InstallSymconExtension
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses IPSModule::LogMessage()
     * @uses IPSModule::Translate()
     * @uses file_get_contents()
     * @uses dirname()
     * @uses trigger_error()
     * @uses isset()
     */
    public function InstallSymconExtension(): bool
    {
        if ($this->installedZhVersion == 0) {
            $this->LogMessage($this->Translate('Cannot determine ZH Version. No Extension installed.'), KL_WARNING);
            return false;
        }
        if (!isset(self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])) {
            return false;

        }
        $ExtensionFilename = $this->ExtensionFilename == '' ? 'IPSymconExtension.js' : $this->ExtensionFilename;
        $Topic = '/bridge/request/extension/save';
        $Payload = ['name'=>$ExtensionFilename, 'code'=>file_get_contents(dirname(__DIR__) . '/libs/' . self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * RequestOptions
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendQuietCheckedBridgeRequest()
     */
    public function RequestOptions(int $Timeout = self::TIMEOUT_ZIGBEE_OPTIONS_REQUEST): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> []
        ];
        return $this->SendQuietCheckedBridgeRequest($Topic, $Payload, $Timeout) !== false;
    }

    /**
     * SetLastSeen
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function SetLastSeen(): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> [
                'advanced'=> [
                    'last_seen'=> 'epoch'
                ]
            ]
        ];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * SetPermitJoinOption
     *
     * @param  bool $PermitJoin
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function SetPermitJoinOption(bool $PermitJoin): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options'=> ['permit_join' => $PermitJoin]];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * SetPermitJoin
     *
     * @param  bool $PermitJoin
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function SetPermitJoin(bool $PermitJoin): bool
    {
        return $this->SetPermitJoinTarget($PermitJoin ? self::MAX_PERMIT_JOIN_DURATION : 0);
    }

    /**
     * Aktiviert den Netzwerkbeitritt fuer eine begrenzte Dauer optional ueber
     * einen bestimmten Coordinator oder Router.
     *
     * @param int    $Duration Dauer in Sekunden von 0 bis 254. Mit 0 wird der Netzwerkbeitritt beendet.
     * @param string $Device Optionaler Friendly Name oder eine IEEE-Adresse des Pairing-Ziels.
     *
     * @return bool true, wenn Zigbee2MQTT den Request angenommen hat.
     */
    public function SetPermitJoinTarget(int $Duration, string $Device = ''): bool
    {
        $Duration = max(0, min(self::MAX_PERMIT_JOIN_DURATION, $Duration));
        $Device = trim($Device);
        $Payload = ['time' => $Duration];
        if ($Duration > 0 && $Device !== '') {
            $Payload['device'] = $Device;
        }

        $Data = $this->SendCheckedBridgeRequest('/bridge/request/permit_join', $Payload);
        if ($Data === false) {
            return false;
        }

        $Duration = max(0, min(self::MAX_PERMIT_JOIN_DURATION, (int) ($Data['time'] ?? $Duration)));
        $Target = $Duration > 0 ? trim((string) ($Data['device'] ?? $Device)) : '';
        $this->ApplyPermitJoinState($Duration > 0, $Duration > 0 ? time() + $Duration : 0, $Target);
        return true;
    }

    /**
     * SetBlocklist
     *
     * @param string $DevicesJSON JSON-Array mit IEEE-Adressen.
     *
     * @return bool
     */
    public function SetBlocklist(string $DevicesJSON): bool
    {
        return $this->SetNetworkSecurityList('blocklist', $DevicesJSON);
    }

    /**
     * SetPasslist
     *
     * @param string $DevicesJSON JSON-Array mit IEEE-Adressen.
     *
     * @return bool
     */
    public function SetPasslist(string $DevicesJSON): bool
    {
        return $this->SetNetworkSecurityList('passlist', $DevicesJSON);
    }

    /**
     * SetLogLevel
     *
     * @param  string $LogLevel
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function SetLogLevel(string $LogLevel): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options' =>['advanced' => ['log_level'=> $LogLevel]]];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * Restart
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function Restart(): bool
    {
        $Topic = '/bridge/request/restart';
        return $this->SendCheckedBridgeRequest($Topic) !== false;
    }

    /**
     * Liest Endpoints, Cluster und Basisattribute eines Geraets erneut ein.
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     */
    public function InterviewDevice(string $DeviceName): bool
    {
        return $this->SendQuietCheckedBridgeRequest(
            '/bridge/request/device/interview',
            ['id' => $DeviceName],
            self::TIMEOUT_ZIGBEE_DEVICE_MAINTENANCE_REQUEST
        ) !== false;
    }

    /**
     * Fuehrt die geraetespezifische Zigbee2MQTT-Konfiguration erneut aus.
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     */
    public function ConfigureDevice(string $DeviceName): bool
    {
        return $this->SendQuietCheckedBridgeRequest(
            '/bridge/request/device/configure',
            ['id' => $DeviceName],
            self::TIMEOUT_ZIGBEE_DEVICE_MAINTENANCE_REQUEST
        ) !== false;
    }

    /**
     * CreateGroup
     *
     * @param  string $GroupName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function CreateGroup(string $GroupName): bool
    {
        $Topic = '/bridge/request/group/add';
        $Payload = ['friendly_name' => $GroupName];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * DeleteGroup
     *
     * @param  string $GroupName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function DeleteGroup(string $GroupName): bool
    {
        $Topic = '/bridge/request/group/remove';
        $Payload = ['id' => $GroupName];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * RenameGroup
     *
     * @param  string $OldName
     * @param  string $NewName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function RenameGroup(string $OldName, string $NewName): bool
    {
        $Topic = '/bridge/request/group/rename';
        $Payload = ['from' => $OldName, 'to' => $NewName];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * AddDeviceToGroup
     *
     * @param  string $GroupName
     * @param  string $DeviceName
     * @param  string $Endpoint Optional Zigbee endpoint to add to the group.
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function AddDeviceToGroup(string $GroupName, string $DeviceName, string $Endpoint = ''): bool
    {
        $Topic = '/bridge/request/group/members/add';
        $Payload = ['group'=>$GroupName, 'device' => $DeviceName];
        $Endpoint = trim($Endpoint);
        if ($Endpoint !== '') {
            $Payload['endpoint'] = is_numeric($Endpoint) ? (int) $Endpoint : $Endpoint;
        }
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * RemoveDeviceFromGroup
     *
     * @param  string $GroupName
     * @param  string $DeviceName
     * @param  string $Endpoint Optional Zigbee endpoint to remove from the group.
     * @param  bool $SkipDisableReporting Whether Zigbee2MQTT should keep reporting while removing the member.
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function RemoveDeviceFromGroup(string $GroupName, string $DeviceName, string $Endpoint = '', bool $SkipDisableReporting = true): bool
    {
        $Topic = '/bridge/request/group/members/remove';
        $Payload = ['group'=>$GroupName, 'device' => $DeviceName, 'skip_disable_reporting'=>$SkipDisableReporting];
        $Endpoint = trim($Endpoint);
        if ($Endpoint !== '') {
            $Payload['endpoint'] = is_numeric($Endpoint) ? (int) $Endpoint : $Endpoint;
        }
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * RemoveAllDevicesFromGroup
     *
     * @param  string $GroupName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function RemoveAllDevicesFromGroup(string $GroupName): bool
    {
        $Topic = '/bridge/request/group/members/remove_all';
        $Payload = ['group'=>$GroupName];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * RemoveDeviceFromAllGroups
     *
     * @param string $DeviceName            Friendly Name oder IEEE-Adresse.
     * @param bool   $SkipDisableReporting  Reporting beim Entfernen nicht automatisch deaktivieren.
     *
     * @return bool
     */
    public function RemoveDeviceFromAllGroups(string $DeviceName, bool $SkipDisableReporting = true): bool
    {
        return $this->SendCheckedBridgeRequest('/bridge/request/group/members/remove_all', [
            'device'                 => $DeviceName,
            'skip_disable_reporting' => $SkipDisableReporting
        ]) !== false;
    }

    /**
     * SetGroupOptions
     *
     * @param string $GroupName   Friendly Name oder ID der Gruppe.
     * @param string $OptionsJSON JSON-Objekt mit den zu setzenden Gruppenoptionen.
     *
     * @return bool
     */
    public function SetGroupOptions(string $GroupName, string $OptionsJSON): bool
    {
        $options = $this->ParseRequiredJsonObject($OptionsJSON, 'Group options must be a JSON object.');
        if ($options === null) {
            return false;
        }

        $data = $this->SendCheckedBridgeRequest('/bridge/request/group/options', [
            'id'      => $GroupName,
            'options' => $options
        ]);
        if ($data === false) {
            return false;
        }
        if (($data['restart_required'] ?? false) === true) {
            $this->LogMessage($this->Translate('Zigbee2MQTT restart is required for the changed group options.'), KL_NOTIFY);
        }

        return true;
    }

    /**
     * StoreScene
     *
     * @param string $FriendlyName Friendly Name eines Geraets oder einer Gruppe.
     * @param int    $SceneID      Szenen-ID.
     * @param string $SceneName    Optionaler Szenenname.
     * @param int    $GroupID      Optionale Gruppen-ID beim Speichern einzelner Lampen fuer Gruppenszenen.
     *
     * @return bool
     */
    public function StoreScene(string $FriendlyName, int $SceneID, string $SceneName = '', int $GroupID = 0): bool
    {
        if ($SceneName === '' && $GroupID <= 0) {
            return $this->SendSceneCommand($FriendlyName, ['scene_store' => $SceneID]);
        }

        $payload = ['ID' => $SceneID];
        if ($SceneName !== '') {
            $payload['name'] = $SceneName;
        }
        if ($GroupID > 0) {
            $payload['group_id'] = $GroupID;
        }

        return $this->SendSceneCommand($FriendlyName, ['scene_store' => $payload]);
    }

    /**
     * AddScene
     *
     * @param string $FriendlyName Friendly Name eines Geraets oder einer Gruppe.
     * @param string $SceneJSON    JSON-Objekt fuer scene_add.
     *
     * @return bool
     */
    public function AddScene(string $FriendlyName, string $SceneJSON): bool
    {
        $scene = $this->ParseRequiredJsonObject($SceneJSON, 'Scene definition must be a JSON object.');
        if ($scene === null) {
            return false;
        }

        return $this->SendSceneCommand($FriendlyName, ['scene_add' => $scene]);
    }

    /**
     * RecallScene
     *
     * @param string $FriendlyName Friendly Name eines Geraets oder einer Gruppe.
     * @param int    $SceneID      Szenen-ID.
     *
     * @return bool
     */
    public function RecallScene(string $FriendlyName, int $SceneID): bool
    {
        return $this->SendSceneCommand($FriendlyName, ['scene_recall' => $SceneID]);
    }

    /**
     * RemoveScene
     *
     * @param string $FriendlyName Friendly Name eines Geraets oder einer Gruppe.
     * @param int    $SceneID      Szenen-ID.
     *
     * @return bool
     */
    public function RemoveScene(string $FriendlyName, int $SceneID): bool
    {
        return $this->SendSceneCommand($FriendlyName, ['scene_remove' => $SceneID]);
    }

    /**
     * RemoveAllScenes
     *
     * @param string $FriendlyName Friendly Name eines Geraets oder einer Gruppe.
     *
     * @return bool
     */
    public function RemoveAllScenes(string $FriendlyName): bool
    {
        return $this->SendSceneCommand($FriendlyName, ['scene_remove_all' => '']);
    }

    /**
     * RenameScene
     *
     * @param string $FriendlyName Friendly Name eines Geraets oder einer Gruppe.
     * @param int    $SceneID      Szenen-ID.
     * @param string $SceneName    Neuer Szenenname.
     *
     * @return bool
     */
    public function RenameScene(string $FriendlyName, int $SceneID, string $SceneName): bool
    {
        return $this->SendSceneCommand($FriendlyName, [
            'scene_rename' => [
                'ID'   => $SceneID,
                'name' => $SceneName
            ]
        ]);
    }

    /**
     * Bind
     *
     * @param  string $SourceDevice
     * @param  string $TargetDevice
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function Bind(string $SourceDevice, string $TargetDevice): bool
    {
        $Topic = '/bridge/request/device/bind';
        $Payload = ['from' => $SourceDevice, 'to' => $TargetDevice];
        return $this->SendCheckedBridgeRequest($Topic, $Payload, self::TIMEOUT_ZIGBEE_BINDING_REQUEST) !== false;
    }

    /**
     * BindWithOptions
     *
     * @param string $SourceDevice          Friendly Name oder IEEE-Adresse, optional mit Endpoint.
     * @param string $TargetDevice          Friendly Name, Gruppenname oder IEEE-Adresse, optional mit Endpoint.
     * @param string $ClustersJSON          JSON-Array oder kommaseparierte Clusterliste.
     * @param bool   $SkipDisableReporting  Reporting beim Unbind nicht automatisch entfernen.
     *
     * @return bool
     */
    public function BindWithOptions(string $SourceDevice, string $TargetDevice, string $ClustersJSON, bool $SkipDisableReporting): bool
    {
        return $this->SendCheckedBridgeRequest(
            '/bridge/request/device/bind',
            $this->BuildBindingPayload($SourceDevice, $TargetDevice, $ClustersJSON, $SkipDisableReporting),
            self::TIMEOUT_ZIGBEE_BINDING_REQUEST
        ) !== false;
    }

    /**
     * Unbind
     *
     * @param  string $SourceDevice
     * @param  string $TargetDevice
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function Unbind(string $SourceDevice, string $TargetDevice): bool
    {
        $Topic = '/bridge/request/device/unbind';
        $Payload = ['from' => $SourceDevice, 'to' => $TargetDevice];
        return $this->SendCheckedBridgeRequest($Topic, $Payload, self::TIMEOUT_ZIGBEE_BINDING_REQUEST) !== false;
    }

    /**
     * UnbindWithOptions
     *
     * @param string $SourceDevice          Friendly Name oder IEEE-Adresse, optional mit Endpoint.
     * @param string $TargetDevice          Friendly Name, Gruppenname oder IEEE-Adresse, optional mit Endpoint.
     * @param string $ClustersJSON          JSON-Array oder kommaseparierte Clusterliste.
     * @param bool   $SkipDisableReporting  Reporting beim Unbind nicht automatisch entfernen.
     *
     * @return bool
     */
    public function UnbindWithOptions(string $SourceDevice, string $TargetDevice, string $ClustersJSON, bool $SkipDisableReporting): bool
    {
        return $this->SendCheckedBridgeRequest(
            '/bridge/request/device/unbind',
            $this->BuildBindingPayload($SourceDevice, $TargetDevice, $ClustersJSON, $SkipDisableReporting),
            self::TIMEOUT_ZIGBEE_BINDING_REQUEST
        ) !== false;
    }

    /**
     * ClearBinds
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     *
     * @return bool
     */
    public function ClearBinds(string $DeviceName): bool
    {
        return $this->SendCheckedBridgeRequest(
            '/bridge/request/device/binds/clear',
            ['id' => $DeviceName],
            self::TIMEOUT_ZIGBEE_BINDING_REQUEST
        ) !== false;
    }

    /**
     * Liefert die aus bridge/devices bekannten Endpoint-Daten eines Geraets.
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     *
     * @return string JSON-Daten mit Endpoint-Daten oder leeres JSON-Array.
     */
    public function GetCachedDeviceEndpoints(string $DeviceName): string
    {
        $DeviceName = trim($DeviceName);
        if ($DeviceName === '') {
            return '[]';
        }

        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $friendlyName = trim((string) ($device['friendly_name'] ?? $device['friendlyName'] ?? ''));
            $ieeeAddress = trim((string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''));
            if ($DeviceName !== $friendlyName && strcasecmp($DeviceName, $ieeeAddress) !== 0) {
                continue;
            }

            $endpoints = \is_array($device['endpoints'] ?? null) ? $device['endpoints'] : [];
            $json = json_encode($endpoints, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return \is_string($json) ? $json : '[]';
        }

        return '[]';
    }

    /**
     * Liefert die aus bridge/devices bekannten Geraete aus dem Bridge-Cache.
     *
     * @return string JSON-Liste der gecachten Geraete oder leeres JSON-Array.
     */
    public function GetCachedNetworkDevices(): string
    {
        $json = json_encode($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return \is_string($json) ? $json : '[]';
    }

    /**
     * ConfigureReporting
     *
     * @param string $DeviceName            Friendly Name oder IEEE-Adresse.
     * @param string $Endpoint              Endpoint-ID oder Endpoint-Name.
     * @param string $Cluster               Zigbee-Cluster, z.B. genOnOff.
     * @param string $Attribute             Cluster-Attribut, z.B. onOff.
     * @param int    $MinimumReportInterval Minimales Reporting-Intervall in Sekunden.
     * @param int    $MaximumReportInterval Maximales Reporting-Intervall in Sekunden.
     * @param string $ReportableChange      Optionaler reportable_change-Wert.
     * @param string $OptionsJSON           Optionales JSON-Objekt fuer ZCL-Optionen.
     *
     * @return bool
     */
    public function ConfigureReporting(
        string $DeviceName,
        string $Endpoint,
        string $Cluster,
        string $Attribute,
        int $MinimumReportInterval,
        int $MaximumReportInterval,
        string $ReportableChange,
        string $OptionsJSON
    ): bool {
        $payload = $this->BuildReportingPayload($DeviceName, $Endpoint, $Cluster);
        $payload['attribute'] = $Attribute;
        $payload['minimum_report_interval'] = $MinimumReportInterval;
        $payload['maximum_report_interval'] = $MaximumReportInterval;

        $ReportableChange = trim($ReportableChange);
        if ($ReportableChange !== '') {
            $payload['reportable_change'] = $this->ParseBridgeScalarValue($ReportableChange);
        }

        $options = $this->ParseOptionalJsonObject($OptionsJSON);
        if ($options !== []) {
            $payload['options'] = $options;
        }

        return $this->SendCheckedBridgeRequest('/bridge/request/device/reporting/configure', $payload, 10000) !== false;
    }

    /**
     * ReadReporting
     *
     * @param string $DeviceName       Friendly Name oder IEEE-Adresse.
     * @param string $Endpoint         Endpoint-ID oder Endpoint-Name.
     * @param string $Cluster          Zigbee-Cluster, z.B. genOnOff.
     * @param string $AttributesJSON   JSON-Array oder kommaseparierte Attributliste.
     * @param string $ManufacturerCode Optionaler Hersteller-Code.
     *
     * @return string JSON-kodierte Antwortdaten oder leer bei Fehler.
     */
    public function ReadReporting(string $DeviceName, string $Endpoint, string $Cluster, string $AttributesJSON, string $ManufacturerCode): string
    {
        $payload = $this->BuildReportingPayload($DeviceName, $Endpoint, $Cluster);
        $payload['configs'] = $this->BuildReportingConfigList($AttributesJSON);

        $ManufacturerCode = trim($ManufacturerCode);
        if ($ManufacturerCode !== '') {
            $payload['manufacturer_code'] = $this->ParseBridgeScalarValue($ManufacturerCode);
        }

        $data = $this->SendCheckedBridgeRequest('/bridge/request/device/reporting/read', $payload, 10000);
        if ($data === false) {
            return '';
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * RequestNetworkmap
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     */
    public function RequestNetworkmap(): bool
    {
        $Topic = '/bridge/request/networkmap';
        $Payload = ['type' => 'graphviz', 'routes' => true];
        return $this->SendBridgeCommand($Topic, $Payload);
    }

    /**
     * HealthCheck
     *
     * @return bool true, wenn Zigbee2MQTT eine gesunde Bridge meldet.
     */
    public function HealthCheck(): bool
    {
        $data = $this->SendCheckedBridgeRequest('/bridge/request/health_check', [], 10000);
        if ($data === false) {
            return false;
        }

        $this->StoreHealthCheckResult($data);
        return (bool) ($data['healthy'] ?? false);
    }

    /**
     * CoordinatorCheck
     *
     * @return bool true, wenn keine fehlenden Router gemeldet wurden.
     */
    public function CoordinatorCheck(): bool
    {
        $data = $this->SendCheckedBridgeRequest('/bridge/request/coordinator_check', [], 10000);
        if ($data === false) {
            return false;
        }

        $this->StoreCoordinatorCheckResult($data);
        return \count($this->ReadDiagnosticMissingRouters()) === 0;
    }

    /**
     * ClearBridgeDiagnostics
     *
     * @return bool
     */
    public function ClearBridgeDiagnostics(): bool
    {
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_EVENTS, []);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_LOGS, []);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES, []);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES, []);
        return true;
    }

    /**
     * Erstellt ein Zigbee2MQTT-Backup und speichert es als ZIP-Datei im Symcon-Benutzerverzeichnis.
     *
     * @return string Absoluter Dateipfad oder leer bei Fehler.
     */
    public function CreateBackupFile(): string
    {
        $data = $this->RequestBackupData();
        if ($data === false) {
            return '';
        }

        $directory = $this->GetBackupDirectory();
        if (!$this->EnsureDirectory($directory)) {
            return '';
        }

        $filename = $directory . DIRECTORY_SEPARATOR . 'zigbee2mqtt-backup-' . date('Ymd-His') . '.zip';
        if (!$this->WriteBackupZipFile($data, $filename)) {
            @unlink($filename);
            return '';
        }

        return $filename;
    }

    /**
     * AddInstallCode
     *
     * @param string $Code Install-Code aus QR-Code oder Geraeteaufdruck.
     *
     * @return bool
     */
    public function AddInstallCode(string $Code): bool
    {
        $Code = trim($Code);
        if ($Code === '') {
            trigger_error($this->Translate('Install code is required.'), E_USER_NOTICE);
            return false;
        }

        return $this->SendCheckedSensitiveBridgeRequest('/bridge/request/install_code/add', ['value' => $Code]) !== false;
    }

    /**
     * TouchlinkScan
     *
     * @return string JSON-kodierte Scan-Antwort oder leer bei Fehler.
     */
    public function TouchlinkScan(): string
    {
        $data = $this->SendCheckedBridgeRequest('/bridge/request/touchlink/scan', [], 70000);
        if ($data === false) {
            return '';
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_TOUCHLINK_DEVICES, \is_array($data['found'] ?? null) ? $data['found'] : []);
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * TouchlinkIdentify
     *
     * @param string $IeeeAddress IEEE-Adresse aus dem Touchlink-Scan.
     * @param int    $Channel     Zigbee-Kanal aus dem Touchlink-Scan.
     *
     * @return bool
     */
    public function TouchlinkIdentify(string $IeeeAddress, int $Channel): bool
    {
        $payload = $this->BuildTouchlinkTargetPayload($IeeeAddress, $Channel, true);
        if ($payload === null) {
            return false;
        }

        return $this->SendCheckedBridgeRequest('/bridge/request/touchlink/identify', $payload, 10000) !== false;
    }

    /**
     * TouchlinkFactoryReset
     *
     * @param string $IeeeAddress Optionale IEEE-Adresse aus dem Touchlink-Scan.
     * @param int    $Channel     Optionaler Zigbee-Kanal aus dem Touchlink-Scan.
     *
     * @return bool
     */
    public function TouchlinkFactoryReset(string $IeeeAddress = '', int $Channel = 0): bool
    {
        $payload = $this->BuildTouchlinkTargetPayload($IeeeAddress, $Channel, false);
        if ($payload === null) {
            return false;
        }

        return $this->SendCheckedBridgeRequest('/bridge/request/touchlink/factory_reset', $payload, 70000) !== false;
    }

    /**
     * SendBridgeAction
     *
     * Sendet eine generische Zigbee2MQTT-Bridge-Aktion an bridge/request/action.
     *
     * @param string $Action Name der Zigbee2MQTT-Aktion.
     * @param array  $Params Parameter fuer das params-Objekt ohne transaction/action.
     *
     * @return bool
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

        return $this->SendCheckedSensitiveBridgeRequest('/bridge/request/action', $payload, 30000) !== false;
    }

    /**
     * RenameDevice
     *
     * @param  string $OldDeviceName
     * @param  string $NewDeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function RenameDevice(string $OldDeviceName, string $NewDeviceName): bool
    {
        $Topic = '/bridge/request/device/rename';
        $Payload = ['from' => $OldDeviceName, 'to' => $NewDeviceName];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
    }

    /**
     * RemoveDevice
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param bool   $Force      Entfernt das Geraet nur aus der Zigbee2MQTT-Datenbank.
     * @param bool   $Block      Blockiert das Geraet nach dem Entfernen.
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function RemoveDevice(string $DeviceName, bool $Force = false, bool $Block = false): bool
    {
        $Topic = '/bridge/request/device/remove';
        $Payload = ['id' => $DeviceName];
        if ($Force) {
            $Payload['force'] = true;
        }
        if ($Block) {
            $Payload['block'] = true;
        }

        return $this->SendCheckedBridgeRequest($Topic, $Payload, 11000) !== false;
    }

    /**
     * SetDeviceOptions
     *
     * @param string $DeviceName  Friendly Name oder IEEE-Adresse.
     * @param string $OptionsJSON JSON-Objekt mit den zu setzenden Geraeteoptionen.
     *
     * @return bool
     */
    public function SetDeviceOptions(string $DeviceName, string $OptionsJSON): bool
    {
        $options = json_decode($OptionsJSON, true);
        if (!\is_array($options) || !str_starts_with(ltrim($OptionsJSON), '{')) {
            trigger_error($this->Translate('Device options must be a JSON object.'), E_USER_NOTICE);
            return false;
        }

        $data = $this->SendCheckedBridgeRequest('/bridge/request/device/options', [
            'id'      => $DeviceName,
            'options' => $options
        ]);
        if ($data === false) {
            return false;
        }
        if (($data['restart_required'] ?? false) === true) {
            $this->LogMessage($this->Translate('Zigbee2MQTT restart is required for the changed device options.'), KL_NOTIFY);
        }

        return true;
    }

    /**
     * CheckOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function CheckOTAUpdate(string $DeviceName): bool
    {
        return $this->CheckOTAAvailability('/bridge/request/device/ota_update/check', $DeviceName, '');
    }

    /**
     * CheckOTAUpdateWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function CheckOTAUpdateWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->CheckOTAAvailability('/bridge/request/device/ota_update/check', $DeviceName, $Url);
    }

    /**
     * CheckOTADowngrade
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function CheckOTADowngrade(string $DeviceName): bool
    {
        return $this->CheckOTAAvailability('/bridge/request/device/ota_update/check/downgrade', $DeviceName, '');
    }

    /**
     * CheckOTADowngradeWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function CheckOTADowngradeWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->CheckOTAAvailability('/bridge/request/device/ota_update/check/downgrade', $DeviceName, $Url);
    }

    /**
     * PerformOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function PerformOTAUpdate(string $DeviceName): bool
    {
        return $this->SendOTABridgeCommand('/bridge/request/device/ota_update/update', $DeviceName, '');
    }

    /**
     * PerformOTAUpdateWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function PerformOTAUpdateWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->SendOTABridgeCommand('/bridge/request/device/ota_update/update', $DeviceName, $Url);
    }

    /**
     * PerformOTADowngrade
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function PerformOTADowngrade(string $DeviceName): bool
    {
        return $this->SendOTABridgeCommand('/bridge/request/device/ota_update/update/downgrade', $DeviceName, '');
    }

    /**
     * PerformOTADowngradeWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function PerformOTADowngradeWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->SendOTABridgeCommand('/bridge/request/device/ota_update/update/downgrade', $DeviceName, $Url);
    }

    /**
     * ScheduleOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function ScheduleOTAUpdate(string $DeviceName): bool
    {
        return $this->ScheduleOTARequest('/bridge/request/device/ota_update/schedule', $DeviceName, '');
    }

    /**
     * ScheduleOTAUpdateWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function ScheduleOTAUpdateWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->ScheduleOTARequest('/bridge/request/device/ota_update/schedule', $DeviceName, $Url);
    }

    /**
     * ScheduleOTADowngrade
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function ScheduleOTADowngrade(string $DeviceName): bool
    {
        return $this->ScheduleOTARequest('/bridge/request/device/ota_update/schedule/downgrade', $DeviceName, '');
    }

    /**
     * ScheduleOTADowngradeWithUrl
     *
     * @param  string $DeviceName
     * @param  string $Url
     *
     * @return bool
     */
    public function ScheduleOTADowngradeWithUrl(string $DeviceName, string $Url): bool
    {
        return $this->ScheduleOTARequest('/bridge/request/device/ota_update/schedule/downgrade', $DeviceName, $Url);
    }

    /**
     * UnscheduleOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function UnscheduleOTAUpdate(string $DeviceName): bool
    {
        $Data = $this->SendCheckedBridgeRequest('/bridge/request/device/ota_update/unschedule', $this->BuildOTAPayload($DeviceName, ''));

        return $Data !== false;
    }

    /**
     * AbortOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     */
    public function AbortOTAUpdate(string $DeviceName): bool
    {
        $Data = $this->SendCheckedBridgeRequest('/bridge/request/device/ota_update/update/abort', $this->BuildOTAPayload($DeviceName, ''));

        return $Data !== false;
    }

    /**
     * Setzt einen Variablenwert module-strict-konform.
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
     * Registriert den Timer fuer die laufende Pairing-Restzeit.
     */
    protected function RegisterPermitJoinTimer(): void
    {
        try {
            $this->RegisterTimer(
                self::TIMER_PERMIT_JOIN_STATUS,
                0,
                "IPS_RequestAction(\$_IPS['TARGET'], 'UpdatePermitJoinStatus', true);"
            );
        } catch (\Throwable) {
            // Timer operations can be temporarily unavailable while the module is being updated.
        }
    }

    /**
     * Aktiviert oder deaktiviert die laufende Pairing-Restzeit.
     */
    protected function SetPermitJoinTimerInterval(int $milliseconds): void
    {
        try {
            $this->SetTimerInterval(self::TIMER_PERMIT_JOIN_STATUS, $milliseconds);
        } catch (\Throwable) {
            // Timer operations can be temporarily unavailable while the module is being updated.
        }
    }

    /**
     * Fuehrt den Health-Check aus der Bridge-Konfiguration ohne technische Notice aus.
     */
    private function RunHealthCheckFromForm(): bool
    {
        $data = $this->SendQuietCheckedBridgeRequest('/bridge/request/health_check', [], 10000);
        if ($data === false) {
            $this->ShowDiagnosticMessage(
                'Zigbee2MQTT is not reachable',
                'Zigbee2MQTT did not respond to the health check. Please check whether Zigbee2MQTT is running and connected to MQTT.'
            );
            return false;
        }

        $this->StoreHealthCheckResult($data);
        $this->UpdateDiagnosticFormFields();
        return (bool) ($data['healthy'] ?? false);
    }

    /**
     * Fuehrt den Coordinator-Check aus der Bridge-Konfiguration ohne technische Notice aus.
     */
    private function RunCoordinatorCheckFromForm(): bool
    {
        $data = $this->SendQuietCheckedBridgeRequest('/bridge/request/coordinator_check', [], 10000);
        if ($data === false) {
            $this->ShowDiagnosticMessage(
                'Zigbee2MQTT is not reachable',
                'Zigbee2MQTT did not respond to the coordinator check. Please check whether Zigbee2MQTT is running and connected to MQTT.'
            );
            return false;
        }

        $this->StoreCoordinatorCheckResult($data);
        $this->UpdateDiagnosticFormFields();
        return \count($this->ReadDiagnosticMissingRouters()) === 0;
    }

    /**
     * Sendet einen Install-Code aus dem Bridge-Formular einmalig an Zigbee2MQTT.
     */
    private function SendInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $code = trim((string) ($selection['code'] ?? ''));
        if ($code === '') {
            $this->ShowInstallCodeMessage('Input required', 'Install code is required.');
            return false;
        }

        if (!$this->AddInstallCode($code)) {
            $this->ShowInstallCodeMessage('Install code could not be sent', 'Zigbee2MQTT did not accept the install code.');
            return false;
        }

        $this->ClearInstallCodeEditor();
        $this->ShowInstallCodeMessage('Install code sent', 'The install code was sent to Zigbee2MQTT.');
        return true;
    }

    /**
     * Speichert einen Install-Code lokal und sendet ihn anschliessend an Zigbee2MQTT.
     */
    private function SaveInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $id = trim((string) ($selection['id'] ?? ''));
        $label = trim((string) ($selection['label'] ?? ''));
        $code = trim((string) ($selection['code'] ?? ''));
        if ($label === '') {
            $this->ShowInstallCodeMessage('Input required', 'A label is required for stored install codes.');
            return false;
        }

        $catalog = $this->ReadAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG);
        $existingIndex = $this->FindStoredInstallCodeIndex($catalog, $id);
        if ($existingIndex !== null) {
            if ($code === '') {
                $code = (string) ($catalog[$existingIndex]['code'] ?? '');
            }
            $catalog[$existingIndex] = [
                'id'    => $id,
                'label' => $label,
                'code'  => $code
            ];
        } else {
            if ($code === '') {
                $this->ShowInstallCodeMessage('Input required', 'Install code is required.');
                return false;
            }
            $catalog[] = [
                'id'    => bin2hex(random_bytes(8)),
                'label' => $label,
                'code'  => $code
            ];
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG, $this->NormalizeStoredInstallCodeCatalog($catalog));
        $this->UpdateStoredInstallCodeFormList();
        $this->ClearInstallCodeEditor();

        if (!$this->AddInstallCode($code)) {
            $this->ShowInstallCodeMessage('Install code saved', 'The install code was saved locally but could not be sent to Zigbee2MQTT.');
            return false;
        }

        $this->ShowInstallCodeMessage('Install code saved', 'The install code was saved locally and sent to Zigbee2MQTT.');
        return true;
    }

    /**
     * Uebernimmt einen gespeicherten Install-Code zur Bearbeitung in das Formular.
     */
    private function SelectStoredInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $entry = $this->FindStoredInstallCode((string) ($selection['id'] ?? ''));
        if ($entry === null) {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        $this->UpdateFormField('InstallCodeCatalogID', 'value', (string) $entry['id']);
        $this->UpdateFormField('InstallCodeLabel', 'value', (string) $entry['label']);
        $this->UpdateFormField('InstallCode', 'value', '');
        $this->UpdateFormField('InstallCodeEditorHint', 'visible', true);
        return true;
    }

    /**
     * Sendet einen lokal gespeicherten Install-Code erneut.
     */
    private function SendStoredInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $entry = $this->FindStoredInstallCode((string) ($selection['id'] ?? ''));
        if ($entry === null) {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        if (!$this->AddInstallCode((string) $entry['code'])) {
            $this->ShowInstallCodeMessage('Install code could not be sent', 'Zigbee2MQTT did not accept the install code.');
            return false;
        }

        $this->ShowInstallCodeMessage('Install code sent', 'The install code was sent to Zigbee2MQTT.');
        return true;
    }

    /**
     * Oeffnet den Bestaetigungsdialog zum Loeschen eines gespeicherten Install-Codes.
     */
    private function RequestDeleteStoredInstallCodeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $entry = $this->FindStoredInstallCode((string) ($selection['id'] ?? ''));
        if ($entry === null) {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_INSTALL_CODE_DELETE, $entry);
        $this->UpdateFormField(
            'InstallCodeDeleteWarningText',
            'caption',
            \sprintf($this->Translate('Delete stored install code "%s"? This cannot be undone.'), (string) $entry['label'])
        );
        $this->UpdateFormField('InstallCodeDeleteWarning', 'visible', true);
        return true;
    }

    /**
     * Loescht den zuvor ausgewaehlten Install-Code nach Bestaetigung.
     */
    private function ConfirmPendingStoredInstallCodeDelete(): bool
    {
        $pending = $this->ReadAttributeArray(self::ATTRIBUTE_PENDING_INSTALL_CODE_DELETE);
        $id = (string) ($pending['id'] ?? '');
        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_INSTALL_CODE_DELETE, []);
        $this->UpdateFormField('InstallCodeDeleteWarning', 'visible', false);
        if ($id === '') {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        if ($this->FindStoredInstallCode($id) === null) {
            $this->ShowInstallCodeMessage('Install code not found', 'The selected stored install code no longer exists.');
            return false;
        }

        $catalog = array_values(array_filter(
            $this->ReadAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG),
            static fn (mixed $entry): bool => !\is_array($entry) || (string) ($entry['id'] ?? '') !== $id
        ));
        $this->WriteAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG, $this->NormalizeStoredInstallCodeCatalog($catalog));
        $this->UpdateStoredInstallCodeFormList();
        $this->ClearInstallCodeEditor();
        $this->ShowInstallCodeMessage('Install code deleted', 'The stored install code was deleted.');
        return true;
    }

    /**
     * Liefert einen gespeicherten Install-Code anhand seiner internen ID.
     */
    private function FindStoredInstallCode(string $id): ?array
    {
        $catalog = $this->NormalizeStoredInstallCodeCatalog($this->ReadAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG));
        $index = $this->FindStoredInstallCodeIndex($catalog, trim($id));
        return $index === null ? null : $catalog[$index];
    }

    /**
     * Liefert den Index eines gespeicherten Install-Codes.
     */
    private function FindStoredInstallCodeIndex(array $catalog, string $id): ?int
    {
        if ($id === '') {
            return null;
        }
        foreach ($catalog as $index => $entry) {
            if (\is_array($entry) && (string) ($entry['id'] ?? '') === $id) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Bereinigt den gespeicherten Install-Code-Katalog.
     */
    private function NormalizeStoredInstallCodeCatalog(array $catalog): array
    {
        $normalized = [];
        foreach ($catalog as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $id = trim((string) ($entry['id'] ?? ''));
            $label = trim((string) ($entry['label'] ?? ''));
            $code = trim((string) ($entry['code'] ?? ''));
            if ($id === '' || $label === '' || $code === '') {
                continue;
            }
            $normalized[] = [
                'id'    => $id,
                'label' => $label,
                'code'  => $code
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label']));
        return $normalized;
    }

    /**
     * Baut die maskierten Listenzeilen fuer das Bridge-Formular.
     */
    private function BuildStoredInstallCodeFormValues(): array
    {
        $values = [];
        foreach ($this->NormalizeStoredInstallCodeCatalog($this->ReadAttributeArray(self::ATTRIBUTE_INSTALL_CODE_CATALOG)) as $entry) {
            $values[] = [
                'id'          => $entry['id'],
                'label'       => $entry['label'],
                'masked_code' => $this->MaskInstallCode((string) $entry['code']),
                'send'        => $this->Translate('Send'),
                'edit'        => $this->Translate('Edit'),
                'delete'      => $this->Translate('Delete')
            ];
        }

        return $values;
    }

    /**
     * Maskiert einen Install-Code fuer die Anzeige.
     */
    private function MaskInstallCode(string $code): string
    {
        $length = \strlen($code);
        $visibleLength = min(4, $length);
        return str_repeat('*', max(4, $length - $visibleLength)) . substr($code, -$visibleLength);
    }

    /**
     * Aktualisiert die Install-Code-Liste in der geoeffneten Bridge-Konfiguration.
     */
    private function UpdateStoredInstallCodeFormList(): void
    {
        $values = $this->BuildStoredInstallCodeFormValues();
        $this->UpdateFormField('StoredInstallCodeList', 'values', json_encode($values));
        $this->UpdateFormField('StoredInstallCodeList', 'rowCount', min(8, max(3, \count($values) + 1)));
    }

    /**
     * Leert den Install-Code-Editor.
     */
    private function ClearInstallCodeEditor(): void
    {
        $this->UpdateFormField('InstallCodeCatalogID', 'value', '');
        $this->UpdateFormField('InstallCodeLabel', 'value', '');
        $this->UpdateFormField('InstallCode', 'value', '');
        $this->UpdateFormField('InstallCodeEditorHint', 'visible', false);
    }

    /**
     * Zeigt eine Install-Code-Rueckmeldung im Formular an.
     */
    private function ShowInstallCodeMessage(string $title, string $message): void
    {
        $this->UpdateFormField('InstallCodeMessageTitle', 'caption', $this->Translate($title));
        $this->UpdateFormField('InstallCodeMessageText', 'caption', $this->Translate($message));
        $this->UpdateFormField('InstallCodeMessage', 'visible', true);
    }

    /**
     * Erstellt ein Backup aus der Form und zeigt nur einen kurzen Status an.
     */
    private function CreateBackupFileFromForm(): bool
    {
        $filename = $this->CreateBackupFile();
        if ($filename === '') {
            $this->ShowBackupMessage(
                $this->Translate('Backup failed'),
                $this->Translate('No backup could be created. Please check the bridge debug log.')
            );
            return false;
        }

        $this->ShowBackupMessage($this->Translate('Backup created'), $this->Translate('Backup saved to:') . ' ' . $filename);
        return true;
    }

    /**
     * Fragt Zigbee2MQTT nach einem Backup.
     */
    private function RequestBackupData(): array|false
    {
        return $this->SendCheckedBridgeRequest('/bridge/request/backup', [], self::TIMEOUT_ZIGBEE_BACKUP_REQUEST);
    }

    /**
     * Dekodiert die von Zigbee2MQTT gelieferten Base64-Daten chunkweise in eine ZIP-Datei.
     *
     * @param array  $data     Antwortdaten der Zigbee2MQTT-Bridge.
     * @param string $filename Zieldatei fuer das dekodierte ZIP-Backup.
     */
    private function WriteBackupZipFile(array $data, string $filename): bool
    {
        $sourceFilename = $this->GetBackupBase64SourceFile($data);
        if ($sourceFilename === '') {
            return false;
        }

        try {
            return $this->DecodeBase64FileToFile($sourceFilename, $filename);
        } finally {
            @unlink($sourceFilename);
        }
    }

    /**
     * Liefert eine temporaere Quelldatei mit den Base64-kodierten Backupdaten.
     *
     * Der normale MQTT-Pfad legt die Daten bereits vor der Transaktionsspeicherung als Datei ab.
     * Der Inline-Fallback bleibt fuer Kompatibilitaet mit direkten Antworten erhalten.
     *
     * @param array $data Antwortdaten der Zigbee2MQTT-Bridge.
     */
    private function GetBackupBase64SourceFile(array $data): string
    {
        if (isset($data['zip_file']) && \is_string($data['zip_file']) && $this->IsBackupTransactionFile($data['zip_file'])) {
            return $data['zip_file'];
        }

        $base64 = $data['zip'] ?? null;
        if (!\is_string($base64) || $base64 === '') {
            return '';
        }

        $filename = tempnam(sys_get_temp_dir(), 'IPSZigbee2MQTT-backup-');
        if (!\is_string($filename)) {
            return '';
        }

        if (file_put_contents($filename, $base64, LOCK_EX) === false) {
            @unlink($filename);
            return '';
        }

        return $filename;
    }

    /**
     * Prueft, ob eine Transaktionsdatei aus dem internen Zigbee2MQTT-Temp-Verzeichnis stammt.
     */
    private function IsBackupTransactionFile(string $filename): bool
    {
        if (!is_file($filename)) {
            return false;
        }

        $directory = realpath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT');
        $resolvedFilename = realpath($filename);
        if (!\is_string($directory) || !\is_string($resolvedFilename)) {
            return false;
        }

        return str_starts_with($resolvedFilename, $directory . DIRECTORY_SEPARATOR);
    }

    /**
     * Dekodiert eine Base64-Datei blockweise in eine binaere Zieldatei.
     */
    private function DecodeBase64FileToFile(string $sourceFilename, string $targetFilename): bool
    {
        $source = @fopen($sourceFilename, 'rb');
        if ($source === false) {
            return false;
        }

        $target = @fopen($targetFilename, 'wb');
        if ($target === false) {
            fclose($source);
            return false;
        }

        $success = true;
        $remainder = '';

        try {
            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) {
                    $success = false;
                    break;
                }

                $chunk = $remainder . $chunk;
                $decodeLength = \strlen($chunk) - (\strlen($chunk) % 4);
                if ($decodeLength === 0) {
                    $remainder = $chunk;
                    continue;
                }

                $decoded = base64_decode(substr($chunk, 0, $decodeLength), true);
                if (!\is_string($decoded) || !$this->WriteStreamData($target, $decoded)) {
                    $success = false;
                    break;
                }

                $remainder = substr($chunk, $decodeLength);
            }

            if ($success && $remainder !== '') {
                $decoded = base64_decode($remainder, true);
                $success = \is_string($decoded) && $this->WriteStreamData($target, $decoded);
            }
        } finally {
            fclose($source);
            fclose($target);
        }

        return $success;
    }

    /**
     * Schreibt einen Datenblock vollstaendig in einen offenen Stream.
     *
     * @param resource $stream
     */
    private function WriteStreamData($stream, string $data): bool
    {
        $length = \strlen($data);
        $written = 0;

        while ($written < $length) {
            $bytes = fwrite($stream, substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                return false;
            }

            $written += $bytes;
        }

        return true;
    }

    /**
     * Zeigt das Ergebnis der Backup-Erstellung im Formular an.
     */
    private function ShowBackupMessage(string $title, string $message): void
    {
        $this->UpdateFormField('BackupMessageTitle', 'caption', $title);
        $this->UpdateFormField('BackupMessageText', 'caption', $message);
        $this->UpdateFormField('BackupMessage', 'visible', true);
    }

    /**
     * Liefert das Zielverzeichnis fuer lokal abgelegte Backups.
     */
    private function GetBackupDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), '\\/') . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT' . DIRECTORY_SEPARATOR . 'backups';
    }

    /**
     * Legt ein Verzeichnis bei Bedarf an.
     */
    private function EnsureDirectory(string $directory): bool
    {
        return is_dir($directory) || @mkdir($directory, 0777, true);
    }

    /**
     * Baut den Payload fuer Binding- und Unbinding-Requests.
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
     * Baut die gemeinsamen Reporting-Payload-Felder.
     */
    private function BuildReportingPayload(string $DeviceName, string $Endpoint, string $Cluster): array
    {
        $payload = [
            'id'      => $DeviceName,
            'cluster' => $Cluster
        ];

        $Endpoint = trim($Endpoint);
        if ($Endpoint !== '') {
            $payload['endpoint'] = is_numeric($Endpoint) ? (int) $Endpoint : $Endpoint;
        }

        return $payload;
    }

    /**
     * Baut die configs-Liste fuer Reporting-Read-Requests.
     */
    private function BuildReportingConfigList(string $AttributesJSON): array
    {
        $decoded = json_decode(trim($AttributesJSON), true);
        if (\is_array($decoded) && array_is_list($decoded)) {
            $configs = [];
            foreach ($decoded as $entry) {
                if (\is_array($entry)) {
                    $configs[] = $entry;
                    continue;
                }
                $attribute = trim((string) $entry);
                if ($attribute !== '') {
                    $configs[] = ['attribute' => $attribute];
                }
            }

            return $configs;
        }

        $attributes = $this->ParseStringList($AttributesJSON);
        if ($attributes === []) {
            return [];
        }

        $configs = [];
        foreach ($attributes as $attribute) {
            $configs[] = ['attribute' => $attribute];
        }

        return $configs;
    }

    /**
     * Liest ein JSON-Array oder eine kommaseparierte Liste.
     *
     * @return string[]
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
     * Liest ein optionales JSON-Objekt.
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
     * Liest ein verpflichtendes JSON-Objekt.
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
     * Wandelt einfache Formularwerte in JSON-, Boolean-, Integer-, Float- oder Stringwerte.
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
     * Startet den Pairing-Modus mit den Eingaben aus der Bridge-Konfiguration.
     */
    private function StartPairingFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        return $this->SetPermitJoinTarget(
            (int) ($selection['time'] ?? self::MAX_PERMIT_JOIN_DURATION),
            (string) ($selection['device'] ?? '')
        );
    }

    /**
     * Uebernimmt den Pairing-Zustand in Variablen, Buffer, Timer und Formular.
     */
    private function ApplyPermitJoinState(bool $active, int $end, string $target): void
    {
        $this->PermitJoinTarget = $active ? trim($target) : '';
        $this->SetValue('permit_join', $active);
        $this->SetValue('permit_join_end', $active ? $end : 0);
        $this->SetValue('permit_join_target', $active ? $this->FormatPairingTarget($target) : '');
        $this->UpdatePermitJoinStatus();
    }

    /**
     * Berechnet die verbleibende Pairing-Zeit und aktualisiert das Formular.
     */
    private function UpdatePermitJoinStatus(bool $updateForm = true): void
    {
        $active = (bool) $this->GetValue('permit_join');
        $end = (int) $this->GetValue('permit_join_end');
        if ($active && $this->PermitJoinTarget === '') {
            $this->PermitJoinTarget = trim((string) $this->GetValue('permit_join_target'));
        }
        $remaining = $active && $end > 0 ? max(0, $end - time()) : 0;

        if ($active && $end > 0 && $remaining === 0) {
            $active = false;
            $this->PermitJoinTarget = '';
            $this->SetValue('permit_join', false);
            $this->SetValue('permit_join_end', 0);
            $this->SetValue('permit_join_target', '');
        }

        $this->SetValue('permit_join_remaining', $remaining);
        $this->SetPermitJoinTimerInterval($active && $end > 0 ? 1000 : 0);

        if (!$updateForm) {
            return;
        }

        $this->UpdateFormField('PairingStatus', 'caption', $this->BuildPairingStatusCaption());
        $this->UpdateFormField('StartPairing', 'enabled', !$active);
        $this->UpdateFormField('StopPairing', 'enabled', $active);
    }

    /**
     * Normalisiert den von Zigbee2MQTT gelieferten Unix-Zeitstempel.
     */
    private function NormalizePermitJoinEnd(mixed $value): int
    {
        if (is_numeric($value)) {
            $timestamp = (int) $value;
            return $timestamp > 20000000000 ? (int) floor($timestamp / 1000) : max(0, $timestamp);
        }

        if (\is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? 0 : $timestamp;
        }

        return 0;
    }

    /**
     * Baut den lesbaren Pairing-Status fuer das Formular.
     */
    private function BuildPairingStatusCaption(?array $devices = null): string
    {
        if (!(bool) $this->GetValue('permit_join')) {
            return $this->Translate('Pairing mode is closed.');
        }

        $remaining = (int) $this->GetValue('permit_join_remaining');
        $target = $this->FormatPairingTarget($this->PermitJoinTarget, $devices);
        if ($remaining <= 0) {
            return sprintf($this->Translate('Pairing mode is open via %s.'), $target);
        }

        return sprintf(
            $this->Translate('Pairing mode is open via %s. Remaining time: %s.'),
            $target,
            $this->FormatOTARemaining($remaining)
        );
    }

    /**
     * Baut die Auswahl aus gesamtem Netzwerk, Coordinator und vorhandenen Routern.
     */
    private function BuildPairingTargetOptions(?array $devices = null): array
    {
        $options = [
            ['caption' => $this->Translate('Entire network'), 'value' => ''],
            ['caption' => $this->Translate('Coordinator'), 'value' => 'coordinator']
        ];
        $routers = [];
        foreach (($devices ?? $this->BuildNetworkSecurityDevices()) as $device) {
            if (!\is_array($device) || strcasecmp((string) ($device['type'] ?? ''), 'Router') !== 0) {
                continue;
            }

            $ieeeAddress = trim((string) ($device['ieee_address'] ?? ''));
            if ($ieeeAddress === '') {
                continue;
            }

            $caption = trim((string) ($device['friendly_name'] ?? $ieeeAddress));
            $model = trim((string) ($device['model'] ?? ''));
            if ($model !== '' && $model !== $caption) {
                $caption .= ' (' . $model . ')';
            }
            $routers[$ieeeAddress] = ['caption' => $caption, 'value' => $ieeeAddress];
        }

        uasort($routers, static fn (array $left, array $right): int => strnatcasecmp($left['caption'], $right['caption']));
        return array_merge($options, array_values($routers));
    }

    /**
     * Formatiert ein Pairing-Ziel fuer Statusvariable und Formular.
     */
    private function FormatPairingTarget(string $target, ?array $devices = null): string
    {
        $target = trim($target);
        if ($target === '') {
            return $this->Translate('Entire network');
        }
        if (strcasecmp($target, 'coordinator') === 0) {
            return $this->Translate('Coordinator');
        }

        foreach (($devices ?? $this->BuildNetworkSecurityDevices()) as $device) {
            if (!\is_array($device) || strcasecmp((string) ($device['ieee_address'] ?? ''), $target) !== 0) {
                continue;
            }

            $friendlyName = trim((string) ($device['friendly_name'] ?? ''));
            return $friendlyName !== '' ? $friendlyName : $target;
        }

        return $target;
    }

    /**
     * Ergaenzt die statische Bridge-Form um aktuelle Diagnosewerte.
     */
    private function BuildBridgeConfigurationForm(array $form): array
    {
        $this->SynchronizeOTAMessageSubscriptions();
        $networkSecurityDevices = $this->BuildNetworkSecurityDevices();
        $this->SetBridgeFormField($form, 'PairingTarget', 'options', $this->BuildPairingTargetOptions($networkSecurityDevices));
        $this->SetBridgeFormField($form, 'PairingStatus', 'caption', $this->BuildPairingStatusCaption($networkSecurityDevices));
        $this->SetBridgeFormField($form, 'StartPairing', 'enabled', !$this->GetValue('permit_join'));
        $this->SetBridgeFormField($form, 'StopPairing', 'enabled', $this->GetValue('permit_join'));
        $availableDevices = $this->BuildNetworkSecurityAvailableDeviceFormValues($networkSecurityDevices);
        $this->SetBridgeFormField($form, 'NetworkSecurityAvailableDeviceList', 'values', $availableDevices);
        $this->SetBridgeFormField($form, 'NetworkSecurityAvailableDeviceList', 'rowCount', min(10, max(4, \count($availableDevices) + 1)));
        $this->SetBridgeFormField($form, 'NetworkSecurityBlocklist', 'values', $this->BuildNetworkSecurityListFormValues('blocklist', $networkSecurityDevices));
        $this->SetBridgeFormField($form, 'NetworkSecurityPasslist', 'values', $this->BuildNetworkSecurityListFormValues('passlist', $networkSecurityDevices));
        $this->SetBridgeFormField($form, 'DiagnosticHealthStatus', 'caption', $this->BuildHealthStatusCaption());
        $this->SetBridgeFormField($form, 'DiagnosticCoordinatorStatus', 'caption', $this->BuildCoordinatorStatusCaption());
        $this->SetBridgeFormField($form, 'DiagnosticMissingRoutersList', 'values', $this->BuildMissingRouterFormValues());
        $this->SetBridgeFormField($form, 'DiagnosticUnsupportedDevicesList', 'values', $this->BuildDeviceDiagnosticFormValues(self::ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES));
        $this->SetBridgeFormField($form, 'DiagnosticInterviewDevicesList', 'values', $this->BuildDeviceDiagnosticFormValues(self::ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES));
        $this->SetBridgeFormField($form, 'DiagnosticEventList', 'values', $this->BuildEventFormValues());
        $this->SetBridgeFormField($form, 'DiagnosticLogList', 'values', $this->BuildLogFormValues());
        $storedInstallCodes = $this->BuildStoredInstallCodeFormValues();
        $this->SetBridgeFormField($form, 'StoredInstallCodeList', 'values', $storedInstallCodes);
        $this->SetBridgeFormField($form, 'StoredInstallCodeList', 'rowCount', min(8, max(3, \count($storedInstallCodes) + 1)));
        $this->SetBridgeFormField($form, 'TouchlinkDeviceList', 'values', $this->BuildTouchlinkDeviceFormValues());
        $otaRows = $this->BuildOTADeviceRows();
        $this->SetBridgeFormField($form, 'OTAStatus', 'caption', $this->BuildOTAStatusCaption($otaRows));
        $this->SetBridgeFormField($form, 'OTAKnownDevices', 'values', $this->BuildOTAKnownDeviceFormValues($otaRows));
        $this->SetBridgeFormField($form, 'OTAKnownDevices', 'rowCount', min(10, max(3, \count($otaRows) + 1)));
        $availableOTARows = $this->FilterOTADeviceRowsByState($otaRows, ['available']);
        $this->SetBridgeFormField($form, 'OTAAvailableUpdates', 'values', $this->BuildOTAAvailableUpdateFormValues($availableOTARows));
        $this->SetBridgeFormField($form, 'OTAAvailableUpdates', 'rowCount', min(8, max(3, \count($availableOTARows) + 1)));
        $activeOTARows = $this->FilterOTADeviceRowsByState($otaRows, ['requested', 'scheduled', 'updating']);
        $this->SetBridgeFormField($form, 'OTAActiveUpdates', 'values', $this->BuildOTAActiveUpdateFormValues($activeOTARows));
        $this->SetBridgeFormField($form, 'OTAActiveUpdates', 'rowCount', min(8, max(3, \count($activeOTARows) + 1)));
        $this->SetBridgeFormField($form, 'OTAUpdateResults', 'values', $this->BuildOTAUpdateResultFormValues());
        $staleVariableScan = $this->ReadStaleVariableScan();
        $this->SetBridgeFormField($form, 'StaleVariableStatus', 'caption', $this->BuildStaleVariableStatusCaption());
        $staleVariableSummary = $this->BuildStaleVariableInstanceSummaryFormValues($staleVariableScan);
        $this->SetBridgeFormField($form, 'StaleVariableInstanceSummary', 'values', $staleVariableSummary);
        $this->SetBridgeFormField($form, 'StaleVariableInstanceSummary', 'rowCount', min(12, max(3, \count($staleVariableSummary) + 1)));
        return $form;
    }

    /**
     * Baut die OTA-Statuszeilen aus den vorhandenen Device-Instanzen auf.
     */
    private function BuildOTADeviceRows(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if ($baseTopic === '') {
            return [];
        }

        $networkDevices = $this->BuildOTANetworkDeviceMap();
        $checkResults = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS);
        $pendingRequests = $this->ReadActiveOTAPendingRequests();
        $rows = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
            if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic
                || !$this->IsInstanceConnectedToSameSplitter($instanceID)
            ) {
                continue;
            }

            $deviceName = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC), '/');
            if ($deviceName === '') {
                continue;
            }

            $ieeeAddress = strtolower((string) @IPS_GetProperty($instanceID, 'IEEE'));
            $networkDevice = $networkDevices[$deviceName] ?? $networkDevices[$ieeeAddress] ?? [];
            if (!(bool) ($networkDevice['supports_ota'] ?? false)) {
                continue;
            }

            $state = strtolower((string) $this->ReadOTADeviceVariable($instanceID, 'update__state', ''));
            $checkResult = \is_array($checkResults[$deviceName] ?? null) ? $checkResults[$deviceName] : [];
            $hasRecentCheckResult = (int) ($checkResult['checked_at'] ?? 0) >= time() - self::OTA_CHECK_RESULT_LIFETIME;
            if ($hasRecentCheckResult && ($checkResult['state'] ?? '') === 'idle') {
                $state = 'idle';
            } elseif (($pendingRequests[$deviceName] ?? null) !== null && $state !== 'updating') {
                $state = 'requested';
            } elseif ($state !== 'updating' && $hasRecentCheckResult) {
                $state = (string) ($checkResult['state'] ?? ((bool) ($checkResult['update_available'] ?? false) ? 'available' : 'idle'));
            } elseif ($state === '') {
                $state = 'unknown';
            }

            $rows[] = [
                'instance_id'       => $instanceID,
                'instance'          => $this->GetOTAInstanceCaption($instanceID),
                'device_name'       => $deviceName,
                'model'             => (string) ($networkDevice['model'] ?? ''),
                'power_source'      => (string) ($networkDevice['powerSource'] ?? ''),
                'state'             => $state,
                'installed_version' => $this->FormatOTAValue($this->ReadOTADeviceVariable($instanceID, 'update__installed_version', '')),
                'latest_version'    => $this->FormatOTAValue($this->ReadOTADeviceVariable($instanceID, 'update__latest_version', '')),
                'progress'          => $this->FormatOTAProgress($this->ReadOTADeviceVariable($instanceID, 'update__progress', '')),
                'remaining'         => $this->FormatOTARemaining($this->ReadOTADeviceVariable($instanceID, 'update__remaining', '')),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strnatcasecmp($left['instance'], $right['instance']));
        return $rows;
    }

    /**
     * Baut eine nach Friendly Name und IEEE-Adresse indizierte OTA-Geraeteliste.
     */
    private function BuildOTANetworkDeviceMap(): array
    {
        $devices = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $friendlyName = trim((string) ($device['friendly_name'] ?? ''), '/');
            $ieeeAddress = strtolower((string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''));
            if ($friendlyName !== '') {
                $devices[$friendlyName] = $device;
            }
            if ($ieeeAddress !== '') {
                $devices[$ieeeAddress] = $device;
            }
        }

        return $devices;
    }

    /**
     * Liest eine optionale OTA-Variable einer Device-Instanz.
     */
    private function ReadOTADeviceVariable(int $instanceID, string $ident, mixed $default): mixed
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $instanceID);
        if ($variableID === false) {
            return $default;
        }

        return GetValue($variableID);
    }

    /**
     * Baut den Anzeigenamen einer Device-Instanz auf.
     */
    private function GetOTAInstanceCaption(int $instanceID): string
    {
        $location = \function_exists('IPS_GetLocation') ? (string) @IPS_GetLocation($instanceID) : '';
        return $location !== '' ? $location : (string) @IPS_GetName($instanceID);
    }

    /**
     * Formatiert einen einfachen OTA-Wert.
     */
    private function FormatOTAValue(mixed $value): string
    {
        return $value === '' || $value === null ? '-' : (string) $value;
    }

    /**
     * Formatiert den OTA-Fortschritt.
     */
    private function FormatOTAProgress(mixed $value): string
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',') . ' %';
    }

    /**
     * Formatiert die OTA-Restzeit.
     */
    private function FormatOTARemaining(mixed $value): string
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return '-';
        }

        $seconds = max(0, (int) $value);
        if ($seconds < 60) {
            return $seconds . ' s';
        }

        return intdiv($seconds, 60) . ' min';
    }

    /**
     * Filtert OTA-Zeilen nach Status.
     */
    private function FilterOTADeviceRowsByState(array $rows, array $states): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => \in_array((string) ($row['state'] ?? ''), $states, true)
        ));
    }

    /**
     * Baut die OTA-Uebersichtszeilen fuer alle bekannten OTA-Geraete auf.
     */
    private function BuildOTAKnownDeviceFormValues(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = array_merge($row, [
                'state_caption' => $this->TranslateOTAState((string) ($row['state'] ?? 'unknown')),
                'action'        => $this->TranslateOTAFormText('Check update')
            ]);
        }

        return $values;
    }

    /**
     * Baut die OTA-Uebersichtszeilen fuer verfuegbare Updates auf.
     */
    private function BuildOTAAvailableUpdateFormValues(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = array_merge($row, [
                'state_caption'   => $this->TranslateOTAState((string) ($row['state'] ?? 'unknown')),
                'update_action'   => $this->TranslateOTAFormText('Start update'),
                'schedule_action' => $this->TranslateOTAFormText('Schedule')
            ]);
        }

        return $values;
    }

    /**
     * Baut die OTA-Uebersichtszeilen fuer laufende und geplante Updates auf.
     */
    private function BuildOTAActiveUpdateFormValues(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $state = (string) ($row['state'] ?? '');
            $values[] = array_merge($row, [
                'state_caption'  => $this->TranslateOTAState((string) ($row['state'] ?? 'unknown')),
                'action'         => $this->BuildOTAActiveUpdateActionCaption($state),
                'action_request' => $this->BuildOTAActiveUpdateActionRequest($state)
            ]);
        }

        return $values;
    }

    /**
     * Baut die Beschriftung der Zeilenaktion fuer aktive OTA-Updates.
     */
    private function BuildOTAActiveUpdateActionCaption(string $state): string
    {
        return match ($state) {
            'scheduled'            => $this->TranslateOTAFormText('Unschedule'),
            'requested', 'updating' => $this->TranslateOTAFormText('Abort'),
            default                => ''
        };
    }

    /**
     * Baut die RequestAction der Zeilenaktion fuer aktive OTA-Updates.
     */
    private function BuildOTAActiveUpdateActionRequest(string $state): string
    {
        return match ($state) {
            'scheduled'            => 'UnscheduleOTAUpdate',
            'requested', 'updating' => 'AbortOTAUpdate',
            default                => ''
        };
    }

    /**
     * Baut den Statushinweis fuer die OTA-Zentrale.
     */
    private function BuildOTAStatusCaption(array $rows): string
    {
        return sprintf(
            $this->TranslateOTAFormText('Known OTA devices: %d, available updates: %d, active or scheduled: %d'),
            \count($rows),
            \count($this->FilterOTADeviceRowsByState($rows, ['available'])),
            \count($this->FilterOTADeviceRowsByState($rows, ['requested', 'scheduled', 'updating']))
        );
    }

    /**
     * Uebersetzt einen OTA-Status fuer die Bridge-Oberflaeche.
     */
    private function TranslateOTAState(string $state): string
    {
        return $this->TranslateOTAFormText(match ($state) {
            'available' => 'Available',
            'requested' => 'Requested',
            'scheduled' => 'Scheduled',
            'updating'  => 'Updating',
            'idle'      => 'Idle',
            default     => 'Unknown'
        });
    }

    /**
     * Uebersetzt OTA-Formulartexte mit Originaltext als Fallback waehrend Modul-Updates.
     */
    private function TranslateOTAFormText(string $text): string
    {
        try {
            $translation = @$this->Translate($text);
            return \is_string($translation) ? $translation : $text;
        } catch (\Throwable) {
            return $text;
        }
    }

    /**
     * Aktualisiert alle OTA-Listen der Bridge-Konfiguration.
     */
    private function UpdateOTAFormLists(): void
    {
        $this->SynchronizeOTAMessageSubscriptions();
        $rows = $this->BuildOTADeviceRows();
        $availableRows = $this->FilterOTADeviceRowsByState($rows, ['available']);
        $activeRows = $this->FilterOTADeviceRowsByState($rows, ['requested', 'scheduled', 'updating']);
        $this->UpdateFormField('OTAStatus', 'caption', $this->BuildOTAStatusCaption($rows));
        $this->UpdateFormField('OTAKnownDevices', 'values', json_encode($this->BuildOTAKnownDeviceFormValues($rows)));
        $this->UpdateFormField('OTAKnownDevices', 'rowCount', min(10, max(3, \count($rows) + 1)));
        $this->UpdateFormField('OTAAvailableUpdates', 'values', json_encode($this->BuildOTAAvailableUpdateFormValues($availableRows)));
        $this->UpdateFormField('OTAAvailableUpdates', 'rowCount', min(8, max(3, \count($availableRows) + 1)));
        $this->UpdateFormField('OTAActiveUpdates', 'values', json_encode($this->BuildOTAActiveUpdateFormValues($activeRows)));
        $this->UpdateFormField('OTAActiveUpdates', 'rowCount', min(8, max(3, \count($activeRows) + 1)));
        $this->UpdateFormField('OTAUpdateResults', 'values', json_encode($this->BuildOTAUpdateResultFormValues()));
    }

    /**
     * Beobachtet OTA-Variablen und neue Kinder der zugehörigen Device-Instanzen.
     */
    private function SynchronizeOTAMessageSubscriptions(): void
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $deviceIDs = [];
        $variableIDs = [];
        if ($baseTopic !== '') {
            $networkDevices = $this->BuildOTANetworkDeviceMap();
            foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
                if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic
                    || !$this->IsInstanceConnectedToSameSplitter($instanceID)
                ) {
                    continue;
                }

                $otaVariableIDs = [];
                foreach (IPS_GetChildrenIDs($instanceID) as $childID) {
                    if (!IPS_ObjectExists($childID)) {
                        continue;
                    }

                    $object = IPS_GetObject($childID);
                    if ($object['ObjectType'] === OBJECTTYPE_VARIABLE && str_starts_with((string) $object['ObjectIdent'], 'update__')) {
                        $otaVariableIDs[] = $childID;
                    }
                }

                $deviceName = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC), '/');
                $ieeeAddress = strtolower((string) @IPS_GetProperty($instanceID, 'IEEE'));
                $networkDevice = $networkDevices[$deviceName] ?? $networkDevices[$ieeeAddress] ?? [];
                if (!(bool) ($networkDevice['supports_ota'] ?? false)) {
                    continue;
                }

                $deviceIDs[] = $instanceID;
                array_push($variableIDs, ...$otaVariableIDs);
            }
        }

        sort($deviceIDs);
        sort($variableIDs);
        $previousDeviceIDs = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_DEVICES);
        $previousVariableIDs = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_VARIABLES);
        foreach (array_diff($previousDeviceIDs, $deviceIDs) as $instanceID) {
            $this->UnregisterMessage((int) $instanceID, OM_CHILDADDED);
            $this->UnregisterMessage((int) $instanceID, OM_CHILDREMOVED);
        }
        foreach (array_diff($previousVariableIDs, $variableIDs) as $variableID) {
            $this->UnregisterMessage((int) $variableID, VM_UPDATE);
        }
        foreach (array_diff($deviceIDs, $previousDeviceIDs) as $instanceID) {
            $this->RegisterMessage((int) $instanceID, OM_CHILDADDED);
            $this->RegisterMessage((int) $instanceID, OM_CHILDREMOVED);
        }
        foreach (array_diff($variableIDs, $previousVariableIDs) as $variableID) {
            $this->RegisterMessage((int) $variableID, VM_UPDATE);
        }

        if ($previousDeviceIDs !== $deviceIDs) {
            $this->WriteAttributeArray(self::ATTRIBUTE_OTA_MONITORED_DEVICES, $deviceIDs);
        }
        if ($previousVariableIDs !== $variableIDs) {
            $this->WriteAttributeArray(self::ATTRIBUTE_OTA_MONITORED_VARIABLES, $variableIDs);
        }
    }

    /**
     * Prueft ein einzelnes Geraet auf ein OTA-Update und aktualisiert die Liste.
     */
    private function CheckOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }

        $result = $this->SendDataQuiet('/bridge/request/device/ota_update/check', ['id' => $row['device_name']], 10000);
        if (!\is_array($result) || isset($result['error']) || (($result['status'] ?? 'ok') !== 'ok')) {
            $this->ShowOTAMessage('OTA check failed', 'Zigbee2MQTT did not return a successful OTA check result. Battery devices may need to be woken up first.');
            return false;
        }

        $data = \is_array($result['data'] ?? null) ? $result['data'] : [];
        $available = (bool) ($data['update_available'] ?? $data['updateAvailable'] ?? false);
        $this->StoreOTACheckResult($row['device_name'], $available, $available ? 'available' : 'idle');
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage(
            $available ? 'OTA update available' : 'No OTA update available',
            $available ? 'Zigbee2MQTT reports an available OTA update for the selected device.' : 'Zigbee2MQTT reports no available OTA update for the selected device.'
        );

        return true;
    }

    /**
     * Oeffnet die Sicherheitsabfrage fuer ein OTA-Update.
     */
    private function RequestOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }

        if (($row['state'] ?? '') !== 'available') {
            $this->ShowOTAMessage('OTA update cannot be started', 'The selected device does not currently report an available OTA update.');
            return false;
        }
        if ($this->HasRunningOTAUpdate()) {
            $this->ShowOTAMessage('OTA update cannot be started', 'Another OTA update is already running. Please wait until it has finished.');
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_OTA_UPDATE, $row);
        $this->UpdateFormField(
            'OTAUpdateWarningText',
            'caption',
            sprintf(
                $this->Translate('Start OTA update for %s? The device can be unavailable for a longer period and may restart.'),
                $row['instance']
            )
        );
        $this->UpdateFormField('OTAUpdateWarning', 'visible', true);
        return true;
    }

    /**
     * Startet das zuvor bestaetigte OTA-Update.
     */
    private function ConfirmPendingOTAUpdate(): bool
    {
        $pending = $this->ReadAttributeArray(self::ATTRIBUTE_PENDING_OTA_UPDATE);
        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_OTA_UPDATE, []);
        $this->UpdateFormField('OTAUpdateWarning', 'visible', false);
        $row = $this->ResolveOTADeviceRow($pending);
        if ($row === null) {
            return false;
        }

        if (($row['state'] ?? '') !== 'available') {
            $this->ShowOTAMessage('OTA update cannot be started', 'The selected device does not currently report an available OTA update.');
            return false;
        }
        if ($this->HasRunningOTAUpdate()) {
            $this->ShowOTAMessage('OTA update cannot be started', 'Another OTA update is already running. Please wait until it has finished.');
            return false;
        }
        if (!$this->PerformOTAUpdate($row['device_name'])) {
            $this->ShowOTAMessage('OTA update could not be submitted', 'Zigbee2MQTT did not accept the OTA update request.');
            return false;
        }

        $requests = $this->ReadActiveOTAPendingRequests();
        $requests[$row['device_name']] = [
            'instance_id'  => $row['instance_id'],
            'requested_at' => time()
        ];
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, $requests);
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage('OTA update submitted', 'The OTA update was submitted to Zigbee2MQTT. Use Refresh status to read the current progress.');
        return true;
    }

    /**
     * Plant ein OTA-Update fuer das ausgewaehlte Geraet.
     */
    private function ScheduleOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }
        if (($row['state'] ?? '') !== 'available') {
            $this->ShowOTAMessage('OTA update could not be scheduled', 'The selected device does not currently report an available OTA update.');
            return false;
        }
        if (!$this->ScheduleOTAUpdate($row['device_name'])) {
            $this->ShowOTAMessage('OTA update could not be scheduled', 'Zigbee2MQTT did not accept the OTA schedule request. Battery devices may need to be woken up first.');
            return false;
        }

        $this->StoreOTACheckResult($row['device_name'], false, 'scheduled');
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage('OTA update scheduled', 'The OTA update was scheduled for the selected device.');
        return true;
    }

    /**
     * Hebt eine OTA-Planung fuer das ausgewaehlte Geraet auf.
     */
    private function UnscheduleOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }
        if (($row['state'] ?? '') !== 'scheduled') {
            $this->ShowOTAMessage('OTA schedule could not be removed', 'The selected device does not currently report a scheduled OTA update.');
            return false;
        }
        if (!$this->UnscheduleOTAUpdate($row['device_name'])) {
            $this->ShowOTAMessage('OTA schedule could not be removed', 'Zigbee2MQTT did not accept the OTA unschedule request.');
            return false;
        }

        $this->StoreOTACheckResult($row['device_name'], false, 'idle');
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage('OTA schedule removed', 'The OTA schedule was removed for the selected device.');
        return true;
    }

    /**
     * Bricht ein laufendes oder angefordertes OTA-Update fuer das ausgewaehlte Geraet ab.
     */
    private function AbortOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }
        if (!\in_array((string) ($row['state'] ?? ''), ['requested', 'updating'], true)) {
            $this->ShowOTAMessage('OTA update cannot be aborted', 'The selected device does not currently report an active OTA update.');
            return false;
        }
        if ($this->SendQuietCheckedBridgeRequest('/bridge/request/device/ota_update/update/abort', $this->BuildOTAPayload($row['device_name'], '')) === false) {
            $this->ShowOTAMessage('OTA update could not be aborted', 'Zigbee2MQTT did not accept the OTA abort request.');
            return false;
        }

        $requests = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS);
        unset($requests[$row['device_name']]);
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, $requests);
        $this->StoreOTACheckResult($row['device_name'], false, 'idle');
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage('OTA update abort requested', 'The OTA abort request was sent to Zigbee2MQTT.');
        return true;
    }

    /**
     * Loest eine Formularauswahl gegen die bekannten OTA-Geraete auf.
     */
    private function ResolveOTADeviceRow(mixed $value): ?array
    {
        $selection = \is_array($value) ? $value : $this->DecodeBridgeFormPayload($value);
        $instanceID = (int) ($selection['instance_id'] ?? 0);
        foreach ($this->BuildOTADeviceRows() as $row) {
            if ((int) ($row['instance_id'] ?? 0) === $instanceID) {
                return $row;
            }
        }

        $this->ShowOTAMessage('OTA device not found', 'The selected OTA device is no longer available in this bridge instance.');
        return null;
    }

    /**
     * Prueft, ob bereits ein OTA-Update aktiv ist.
     */
    private function HasRunningOTAUpdate(): bool
    {
        return $this->FilterOTADeviceRowsByState($this->BuildOTADeviceRows(), ['requested', 'updating']) !== [];
    }

    /**
     * Liest noch relevante unmittelbar gestartete OTA-Requests.
     */
    private function ReadActiveOTAPendingRequests(): array
    {
        $requests = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS);
        $minimumTimestamp = time() - self::OTA_PENDING_REQUEST_LIFETIME;
        $activeRequests = array_filter(
            $requests,
            static fn (mixed $request): bool => \is_array($request) && (int) ($request['requested_at'] ?? 0) >= $minimumTimestamp
        );
        if ($activeRequests !== $requests) {
            $this->WriteAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, $activeRequests);
        }

        return $activeRequests;
    }

    /**
     * Speichert einen kurzlebigen OTA-Zustand aus einer direkten Bridge-Antwort.
     */
    private function StoreOTACheckResult(string $deviceName, bool $updateAvailable, string $state): void
    {
        $checks = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS);
        $checks[$deviceName] = [
            'checked_at'       => time(),
            'update_available' => $updateAvailable,
            'state'            => $state
        ];
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS, $checks);
    }

    /**
     * Verarbeitet die spaete Abschlussantwort eines asynchronen OTA-Updates.
     */
    private function HandleOTAUpdateResponse(array $payload, array $topics): bool
    {
        $responseTopic = implode('/', $topics);
        if (!\in_array($responseTopic, ['device/ota_update/update', 'device/ota_update/update/downgrade', 'device/ota_update/update/abort'], true)) {
            return false;
        }

        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $deviceName = (string) ($data['id'] ?? $payload['id'] ?? '');
        $success = ($payload['status'] ?? '') === 'ok';
        $isAbort = $responseTopic === 'device/ota_update/update/abort';
        $message = $success
            ? $this->Translate($isAbort ? 'The OTA update was aborted.' : 'The OTA update completed successfully.')
            : (string) ($payload['error'] ?? $data['error'] ?? $this->Translate('Zigbee2MQTT reported an OTA update error.'));
        $this->AppendOTAUpdateResult([
            'time'        => time(),
            'device_name' => $deviceName,
            'status'      => $isAbort && $success ? 'aborted' : ($success ? 'successful' : 'failed'),
            'message'     => $message
        ]);

        $requests = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS);
        unset($requests[$deviceName]);
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, $requests);
        $checks = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS);
        unset($checks[$deviceName]);
        if ($isAbort && $success && $deviceName !== '') {
            $checks[$deviceName] = [
                'checked_at'       => time(),
                'update_available' => false,
                'state'            => 'idle'
            ];
        }
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS, $checks);
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage($isAbort && $success ? 'OTA update aborted' : ($success ? 'OTA update successful' : 'OTA update failed'), $message);
        return true;
    }

    /**
     * Speichert ein OTA-Abschlussergebnis als Ringpuffer.
     */
    private function AppendOTAUpdateResult(array $result): void
    {
        $results = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_UPDATE_RESULTS);
        array_unshift($results, $result);
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_UPDATE_RESULTS, \array_slice($results, 0, self::MAX_OTA_RESULT_ENTRIES));
    }

    /**
     * Baut die OTA-Ergebnisliste fuer das Formular auf.
     */
    private function BuildOTAUpdateResultFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_OTA_UPDATE_RESULTS) as $result) {
            if (!\is_array($result)) {
                continue;
            }

            $values[] = [
                'time'        => date('d.m.Y H:i:s', (int) ($result['time'] ?? 0)),
                'device_name' => (string) ($result['device_name'] ?? ''),
                'status'      => $this->TranslateOTAResultStatus((string) ($result['status'] ?? 'failed')),
                'message'     => (string) ($result['message'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Uebersetzt einen OTA-Ergebnisstatus fuer die Ergebnisliste.
     */
    private function TranslateOTAResultStatus(string $status): string
    {
        return $this->TranslateOTAFormText(match ($status) {
            'successful' => 'Successful',
            'aborted'    => 'Aborted',
            default      => 'Failed'
        });
    }

    /**
     * Zeigt eine OTA-Rueckmeldung in der Bridge-Konfiguration an.
     */
    private function ShowOTAMessage(string $title, string $message): void
    {
        $this->UpdateFormField('OTAMessageTitle', 'caption', $this->Translate($title));
        $this->UpdateFormField('OTAMessageText', 'caption', $this->Translate($message));
        $this->UpdateFormField('OTAMessage', 'visible', true);
    }

    /**
     * Scans for stale Zigbee2MQTT variables and refreshes the bridge form lists.
     */
    private function ScanStaleVariablesFromForm(): void
    {
        $scan = \Zigbee2MQTT\Maintenance\StaleVariableCleanupHelper::Scan($this->GetStaleVariableCleanupOptions());
        $this->WriteAttributeArray(self::ATTRIBUTE_STALE_VARIABLE_SCAN, $scan);
        $this->UpdateStaleVariableFormLists($scan);
    }

    /**
     * Selects an owning instance from the central overview.
     */
    private function SelectStaleVariableMaintenanceInstanceFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $instanceID = (int) ($selection['instance_id'] ?? 0);
        if ($instanceID <= 0 || !\in_array($instanceID, $this->GetStaleVariableMaintenanceInstanceIDs(), true)) {
            return false;
        }

        try {
            $object = IPS_GetObject($instanceID);
        } catch (\Throwable) {
            return false;
        }

        if (($object['ObjectType'] ?? -1) !== OBJECTTYPE_INSTANCE) {
            return false;
        }

        $this->UpdateFormField('StaleVariableOpenInstance', 'objectID', $instanceID);
        $this->UpdateFormField('StaleVariableOpenInstance', 'visible', true);

        return true;
    }

    /**
     * Returns cleanup options used by the bridge UI.
     */
    private function GetStaleVariableCleanupOptions(): array
    {
        return [
            'includeGroups'              => true,
            'instanceIDs'                => $this->GetStaleVariableMaintenanceInstanceIDs(),
            'showPayloadOnlyReview'      => true,
            'protectArchivedVariables'   => true,
            'protectReferencedVariables' => true,
        ];
    }

    /**
     * Returns Device and Group instances owned by this bridge's MQTT system.
     */
    private function GetStaleVariableMaintenanceInstanceIDs(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        try {
            $connectionID = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        } catch (\Throwable) {
            return [];
        }

        if ($baseTopic === '' || $connectionID <= 0) {
            return [];
        }

        $instanceIDs = [];
        foreach ([self::GUID_MODULE_DEVICE, self::GUID_MODULE_GROUP] as $moduleID) {
            foreach (IPS_GetInstanceListByModuleID($moduleID) as $instanceID) {
                if ((int) IPS_GetInstance($instanceID)['ConnectionID'] !== $connectionID
                    || @IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic
                ) {
                    continue;
                }

                $instanceIDs[] = (int) $instanceID;
            }
        }

        sort($instanceIDs);

        return $instanceIDs;
    }

    /**
     * Returns the last stored stale variable scan result.
     */
    private function ReadStaleVariableScan(): array
    {
        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_STALE_VARIABLE_SCAN);
        return [
            'instanceCount'    => (int) ($scan['instanceCount'] ?? 0),
            'keptCount'        => (int) ($scan['keptCount'] ?? 0),
            'clearCandidates'  => \is_array($scan['clearCandidates'] ?? null) ? $scan['clearCandidates'] : [],
            'reviewCandidates' => \is_array($scan['reviewCandidates'] ?? null) ? $scan['reviewCandidates'] : [],
            'errors'           => \is_array($scan['errors'] ?? null) ? $scan['errors'] : [],
        ];
    }

    /**
     * Builds the status caption for the stale variable maintenance area.
     */
    private function BuildStaleVariableStatusCaption(): string
    {
        $scan = $this->ReadStaleVariableScan();
        if (($scan['instanceCount'] ?? 0) === 0
            && ($scan['clearCandidates'] ?? []) === []
            && ($scan['reviewCandidates'] ?? []) === []
            && ($scan['errors'] ?? []) === []
        ) {
            return $this->Translate('No scan has been run yet.');
        }

        return sprintf(
            $this->Translate('Checked instances: %d, clear candidates: %d, review candidates: %d'),
            $scan['instanceCount'],
            \count($scan['clearCandidates']),
            \count($scan['reviewCandidates'])
        );
    }

    /**
     * Builds the compact read-only bridge overview grouped by owning instance.
     */
    private function BuildStaleVariableInstanceSummaryFormValues(?array $scan = null): array
    {
        $scan ??= $this->ReadStaleVariableScan();
        $instances = [];

        foreach ([
            'clearCandidates'  => 'clear_count',
            'reviewCandidates' => 'review_count',
        ] as $source => $counter) {
            foreach (($scan[$source] ?? []) as $row) {
                $instanceID = (int) ($row['instanceID'] ?? 0);
                if ($instanceID <= 0) {
                    continue;
                }

                $instances[$instanceID] ??= [
                    'instance_id'  => $instanceID,
                    'instance'     => (string) ($row['instance'] ?? ''),
                    'clear_count'  => 0,
                    'review_count' => 0,
                    'hint_count'   => 0,
                    'action'       => $this->Translate('Select'),
                ];
                ++$instances[$instanceID][$counter];
            }
        }

        foreach (($scan['errors'] ?? []) as $error) {
            $instanceID = (int) ($error['instanceID'] ?? 0);
            if ($instanceID <= 0) {
                continue;
            }

            $instances[$instanceID] ??= [
                'instance_id'  => $instanceID,
                'instance'     => (string) ($error['path'] ?? ''),
                'clear_count'  => 0,
                'review_count' => 0,
                'hint_count'   => 0,
                'action'       => $this->Translate('Select'),
            ];
            ++$instances[$instanceID]['hint_count'];
        }

        uasort(
            $instances,
            static fn (array $left, array $right): int => strnatcasecmp($left['instance'], $right['instance'])
        );

        return array_values($instances);
    }

    /**
     * Refreshes all stale variable maintenance form fields.
     */
    private function UpdateStaleVariableFormLists(array $scan): void
    {
        $this->UpdateFormField('StaleVariableStatus', 'caption', $this->BuildStaleVariableStatusCaption());
        $summary = $this->BuildStaleVariableInstanceSummaryFormValues($scan);
        $this->UpdateFormField('StaleVariableInstanceSummary', 'values', json_encode($summary));
        $this->UpdateFormField('StaleVariableInstanceSummary', 'rowCount', min(12, max(3, \count($summary) + 1)));
        $this->UpdateFormField('StaleVariableOpenInstance', 'visible', false);
    }

    /**
     * Setzt eine globale Z2M-Netzwerksicherheitsliste.
     */
    private function SetNetworkSecurityList(string $listName, string $DevicesJSON): bool
    {
        $devices = $this->ParseNetworkSecurityDeviceList($DevicesJSON);
        if ($devices === null) {
            return false;
        }

        $attribute = $this->GetNetworkSecurityAttribute($listName);
        if ($attribute === '') {
            return false;
        }

        $data = $this->SendCheckedBridgeRequest('/bridge/request/options', [
            'options' => [
                $listName => $devices
            ]
        ]);
        if ($data === false) {
            return false;
        }

        $this->WriteAttributeArray($attribute, $devices);
        $this->UpdateNetworkSecurityFormLists();
        return true;
    }

    /**
     * Fuegt ein Geraet sofort zur Blocklist hinzu.
     */
    private function AddNetworkSecurityDeviceFromForm(string $listName, mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $ieeeAddress = $this->ResolveNetworkSecurityFormIeeeAddress($selection);
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('Please select a known device or enter a valid IEEE address.');
            return false;
        }

        return $this->ApplyNetworkSecurityListChange($listName, 'add', $ieeeAddress);
    }

    /**
     * Entfernt ein Geraet sofort aus der Blocklist.
     */
    private function RemoveNetworkSecurityDeviceFromForm(string $listName, mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) ($selection['ieee_address'] ?? ''));
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('The selected list entry does not contain a valid IEEE address.');
            return false;
        }

        return $this->ApplyNetworkSecurityListChange($listName, 'remove', $ieeeAddress);
    }

    /**
     * Speichert eine Passlist-Aenderung erst nach einer Bestaetigung.
     */
    private function RequestPasslistChangeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $operation = (string) ($selection['operation'] ?? '');
        $ieeeAddress = $operation === 'add'
            ? $this->ResolveNetworkSecurityFormIeeeAddress($selection)
            : $this->NormalizeNetworkSecurityIeeeAddress((string) ($selection['ieee_address'] ?? ''));
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('Please select a known device or enter a valid IEEE address.');
            return false;
        }
        if (!\in_array($operation, ['add', 'remove'], true)) {
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_PASSLIST_CHANGE, [
            'operation'    => $operation,
            'ieee_address' => $ieeeAddress
        ]);
        $this->UpdateFormField(
            'PasslistWarningText',
            'caption',
            \sprintf(
                $this->Translate('You are about to change the passlist for %s. Devices not contained in the passlist are removed from the network by Zigbee2MQTT.'),
                $ieeeAddress
            )
        );
        $this->UpdateFormField('PasslistWarning', 'visible', true);
        return true;
    }

    /**
     * Fuehrt eine zuvor bestaetigte Passlist-Aenderung aus.
     */
    private function ApplyPendingPasslistChange(): bool
    {
        $pending = $this->ReadAttributeArray(self::ATTRIBUTE_PENDING_PASSLIST_CHANGE);
        $operation = (string) ($pending['operation'] ?? '');
        $ieeeAddress = (string) ($pending['ieee_address'] ?? '');
        if (!\in_array($operation, ['add', 'remove'], true) || $ieeeAddress === '') {
            return false;
        }

        if (!$this->ApplyNetworkSecurityListChange('passlist', $operation, $ieeeAddress)) {
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_PASSLIST_CHANGE, []);
        $this->UpdateFormField('PasslistWarning', 'visible', false);
        return true;
    }

    /**
     * Aendert eine Sicherheitsliste und schreibt sie nach Zigbee2MQTT.
     */
    private function ApplyNetworkSecurityListChange(string $listName, string $operation, string $ieeeAddress): bool
    {
        $attribute = $this->GetNetworkSecurityAttribute($listName);
        if ($attribute === '') {
            return false;
        }

        $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress($ieeeAddress);
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('The IEEE address must start with 0x and contain 16 hexadecimal characters.');
            return false;
        }

        $devices = $this->ReadAttributeArray($attribute);
        if ($operation === 'add' && !\in_array($ieeeAddress, $devices, true)) {
            $devices[] = $ieeeAddress;
        }
        if ($operation === 'remove') {
            $devices = array_values(array_filter($devices, static fn (mixed $device): bool => (string) $device !== $ieeeAddress));
        }

        return $listName === 'passlist'
            ? $this->SetPasslist(json_encode($devices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            : $this->SetBlocklist(json_encode($devices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Uebernimmt ein Geraet in die Netzwerksicherheits-Eingabefelder.
     */
    private function SelectNetworkSecurityDeviceFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) ($selection['ieee_address'] ?? ''));
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('The selected list entry does not contain a valid IEEE address.');
            return false;
        }

        $this->UpdateFormField('NetworkSecuritySelectedDevice', 'value', (string) ($selection['device'] ?? ''));
        $this->UpdateFormField('NetworkSecuritySelectedIeeeAddress', 'value', $ieeeAddress);
        $this->UpdateFormField('NetworkSecurityIeeeAddress', 'value', '');
        return true;
    }

    /**
     * Baut die Device-Liste fuer Blocklist und Passlist.
     */
    private function BuildNetworkSecurityAvailableDeviceFormValues(?array $devices = null): array
    {
        $values = [];
        foreach (($devices ?? $this->BuildNetworkSecurityDevices()) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $ieeeAddress = (string) ($device['ieee_address'] ?? '');
            if ($ieeeAddress === '') {
                continue;
            }

            $caption = (string) ($device['friendly_name'] ?? $ieeeAddress);
            $model = (string) ($device['model'] ?? '');
            if ($model !== '' && $model !== $caption) {
                $caption .= ' (' . $model . ')';
            }

            $values[] = [
                'device'       => $caption,
                'ieee_address' => $ieeeAddress,
                'action'       => $this->Translate('Select')
            ];
        }

        return $values;
    }

    /**
     * Baut die Formularzeilen fuer blocklist oder passlist.
     */
    private function BuildNetworkSecurityListFormValues(string $listName, ?array $devices = null): array
    {
        $attribute = $this->GetNetworkSecurityAttribute($listName);
        if ($attribute === '') {
            return [];
        }

        $deviceNames = $this->BuildNetworkSecurityDeviceNameMap($devices);
        $values = [];
        foreach ($this->ReadAttributeArray($attribute) as $ieeeAddress) {
            $ieeeAddress = (string) $ieeeAddress;
            $values[] = [
                'device'       => $deviceNames[$ieeeAddress] ?? '',
                'ieee_address' => $ieeeAddress,
                'action'       => $this->Translate('Remove')
            ];
        }

        return $values;
    }

    /**
     * Baut eine IEEE-zu-Name-Map fuer Listenanzeigen.
     */
    private function BuildNetworkSecurityDeviceNameMap(?array $devices = null): array
    {
        $names = [];
        foreach (($devices ?? $this->BuildNetworkSecurityDevices()) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $ieeeAddress = (string) ($device['ieee_address'] ?? '');
            if ($ieeeAddress === '') {
                continue;
            }

            $names[$ieeeAddress] = (string) ($device['friendly_name'] ?? '');
        }

        return $names;
    }

    /**
     * Aktualisiert die Sicherheitslisten nach Aenderungen.
     */
    private function UpdateNetworkSecurityFormLists(): void
    {
        $devices = $this->BuildNetworkSecurityDevices(true);
        $availableDevices = $this->BuildNetworkSecurityAvailableDeviceFormValues($devices);
        $this->UpdateFormField('NetworkSecurityAvailableDeviceList', 'values', json_encode($availableDevices));
        $this->UpdateFormField('NetworkSecurityAvailableDeviceList', 'rowCount', min(10, max(4, \count($availableDevices) + 1)));
        $this->UpdateFormField('NetworkSecurityBlocklist', 'values', json_encode($this->BuildNetworkSecurityListFormValues('blocklist', $devices)));
        $this->UpdateFormField('NetworkSecurityPasslist', 'values', json_encode($this->BuildNetworkSecurityListFormValues('passlist', $devices)));
    }

    /**
     * Zeigt Formularfehler im Netzwerksicherheitsbereich als Popup.
     */
    private function ShowNetworkSecurityFormError(string $message): void
    {
        $this->UpdateFormField('NetworkSecurityErrorText', 'caption', $this->Translate($message));
        $this->UpdateFormField('NetworkSecurityError', 'visible', true);
    }

    /**
     * Baut die bekannten Geraete aus Cache, Instanzen und optionalem Extension-Fallback.
     */
    private function BuildNetworkSecurityDevices(bool $includeExtensionFallback = false): array
    {
        $devices = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES) as $device) {
            $this->AddNetworkSecurityDevice($devices, $device);
        }
        foreach ($this->LoadNetworkSecurityDevicesFromInstances() as $device) {
            $this->AddNetworkSecurityDevice($devices, $device);
        }
        if ($devices === [] && $includeExtensionFallback) {
            foreach ($this->LoadNetworkSecurityDevicesFromExtension() as $device) {
                $this->AddNetworkSecurityDevice($devices, $device);
            }
        }

        $devices = array_values($devices);
        usort($devices, static fn (array $left, array $right): int => strnatcasecmp($left['friendly_name'], $right['friendly_name']));
        return $devices;
    }

    /**
     * Fuegt ein Geraet in eine IEEE-indizierte Liste ein.
     */
    private function AddNetworkSecurityDevice(array &$devices, mixed $device): void
    {
        if (!\is_array($device)) {
            return;
        }

        $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''));
        if ($ieeeAddress === '') {
            return;
        }

        $devices[$ieeeAddress] = array_merge($devices[$ieeeAddress] ?? [], [
            'friendly_name' => (string) ($device['friendly_name'] ?? $device['friendlyName'] ?? $devices[$ieeeAddress]['friendly_name'] ?? ''),
            'ieee_address'  => $ieeeAddress,
            'model'         => (string) ($device['model'] ?? $devices[$ieeeAddress]['model'] ?? ''),
            'vendor'        => (string) ($device['vendor'] ?? $devices[$ieeeAddress]['vendor'] ?? ''),
            'type'          => (string) ($device['type'] ?? $devices[$ieeeAddress]['type'] ?? '')
        ]);
    }

    /**
     * Liest bekannte Device-Instanzen mit gleichem BaseTopic und MQTT-Splitter.
     */
    private function LoadNetworkSecurityDevicesFromInstances(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if ($baseTopic === '') {
            return [];
        }

        $devices = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
            if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic
                || !$this->IsInstanceConnectedToSameSplitter($instanceID)
            ) {
                continue;
            }

            $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) @IPS_GetProperty($instanceID, 'IEEE'));
            if ($ieeeAddress === '') {
                continue;
            }

            $topic = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC));
            $devices[] = [
                'friendly_name' => $topic !== '' ? $topic : @IPS_GetName($instanceID),
                'ieee_address'  => $ieeeAddress,
                'model'         => ''
            ];
        }

        return $devices;
    }

    /**
     * Fragt die Symcon-Extension nach bekannten Zigbee2MQTT-Geraeten.
     */
    private function LoadNetworkSecurityDevicesFromExtension(): array
    {
        try {
            if (!$this->HasActiveParent()) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $result = @$this->SendData(
            self::SYMCON_EXTENSION_LIST_REQUEST . 'getDevices',
            [],
            self::TIMEOUT_SYMCON_EXTENSION_LIST_REQUEST
        );
        if (!\is_array($result) || !\is_array($result['list'] ?? null)) {
            return [];
        }

        return array_values(array_filter(
            $result['list'],
            static fn (mixed $device): bool => \is_array($device) && ($device['type'] ?? '') !== 'Coordinator'
        ));
    }

    /**
     * Liest die manuelle oder selektierte IEEE-Adresse aus der Form.
     */
    private function ResolveNetworkSecurityFormIeeeAddress(array $selection): string
    {
        $manualAddress = trim((string) ($selection['ieee_address'] ?? ''));
        if ($manualAddress !== '') {
            return $this->NormalizeNetworkSecurityIeeeAddress($manualAddress);
        }

        return $this->NormalizeNetworkSecurityIeeeAddress((string) ($selection['selected_ieee_address'] ?? ''));
    }

    /**
     * Validiert und normalisiert eine JSON-Liste aus IEEE-Adressen.
     */
    private function ParseNetworkSecurityDeviceList(string $devicesJSON): ?array
    {
        $decoded = json_decode($devicesJSON, true);
        if (!\is_array($decoded) || !array_is_list($decoded)) {
            trigger_error($this->Translate('Device list must be a JSON array.'), E_USER_NOTICE);
            return null;
        }

        foreach ($decoded as $device) {
            if ((string) $device === '') {
                continue;
            }
            if ($this->NormalizeNetworkSecurityIeeeAddress((string) $device) === '') {
                trigger_error($this->Translate('Invalid IEEE address.'), E_USER_NOTICE);
                return null;
            }
        }

        $devices = $this->NormalizeNetworkSecurityDeviceList($decoded);

        return $devices;
    }

    /**
     * Normalisiert eine Liste von IEEE-Adressen.
     */
    private function NormalizeNetworkSecurityDeviceList(mixed $devices): array
    {
        if (!\is_array($devices)) {
            return [];
        }

        $normalized = [];
        foreach ($devices as $device) {
            $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) $device);
            if ($ieeeAddress === '') {
                continue;
            }

            $normalized[$ieeeAddress] = $ieeeAddress;
        }

        return array_values($normalized);
    }

    /**
     * Normalisiert eine einzelne IEEE-Adresse.
     */
    private function NormalizeNetworkSecurityIeeeAddress(string $ieeeAddress): string
    {
        $ieeeAddress = strtolower(trim($ieeeAddress));
        return preg_match('/^0x[0-9a-f]{16}$/', $ieeeAddress) === 1 ? $ieeeAddress : '';
    }

    /**
     * Liefert das Attribut fuer eine Sicherheitsliste.
     */
    private function GetNetworkSecurityAttribute(string $listName): string
    {
        return match ($listName) {
            'blocklist' => self::ATTRIBUTE_CONFIG_BLOCKLIST,
            'passlist'  => self::ATTRIBUTE_CONFIG_PASSLIST,
            default     => ''
        };
    }

    /**
     * Speichert das Ergebnis eines Health-Checks oder des bridge/health Topics.
     */
    private function StoreHealthCheckResult(array $data): void
    {
        $previous = $this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_HEALTH);
        if (!\array_key_exists('healthy', $data) && \array_key_exists('healthy', $previous)) {
            $data['healthy'] = $previous['healthy'];
        }
        $data['checked_at'] = time();
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_HEALTH, $data);
    }

    /**
     * Speichert das Ergebnis des Coordinator-Checks.
     */
    private function StoreCoordinatorCheckResult(array $data): void
    {
        $data['checked_at'] = time();
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_COORDINATOR, $data);
    }

    /**
     * Aktualisiert die sichtbaren Diagnosefelder in einer geoeffneten Bridge-Konfiguration.
     */
    private function UpdateDiagnosticFormFields(): void
    {
        $this->UpdateFormField('DiagnosticHealthStatus', 'caption', $this->BuildHealthStatusCaption());
        $this->UpdateFormField('DiagnosticCoordinatorStatus', 'caption', $this->BuildCoordinatorStatusCaption());
        $missingRouters = $this->BuildMissingRouterFormValues();
        $this->UpdateFormField('DiagnosticMissingRoutersList', 'values', json_encode($missingRouters));
        $this->UpdateFormField('DiagnosticMissingRoutersList', 'rowCount', min(8, max(4, \count($missingRouters) + 1)));
    }

    /**
     * Zeigt eine lesbare Diagnosemeldung im Bridge-Formular.
     */
    private function ShowDiagnosticMessage(string $title, string $message): void
    {
        $this->UpdateFormField('DiagnosticMessageTitle', 'caption', $this->Translate($title));
        $this->UpdateFormField('DiagnosticMessageText', 'caption', $this->Translate($message));
        $this->UpdateFormField('DiagnosticMessage', 'visible', true);
    }

    /**
     * Sammelt warnende und fehlerhafte Bridge-Logmeldungen.
     */
    private function AppendBridgeLog(array $payload): void
    {
        $level = strtolower((string) ($payload['level'] ?? ''));
        if (!\in_array($level, ['warning', 'warn', 'error'], true)) {
            return;
        }

        $this->AppendDiagnosticEntry(self::ATTRIBUTE_DIAGNOSTIC_LOGS, [
            'time'      => time(),
            'level'     => $level === 'warn' ? 'warning' : $level,
            'namespace' => (string) ($payload['namespace'] ?? ''),
            'message'   => $this->FormatDiagnosticValue($payload['message'] ?? $payload)
        ]);
    }

    /**
     * Sammelt Bridge-Events als Ringpuffer.
     */
    private function AppendBridgeEvent(array $payload): void
    {
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $this->AppendDiagnosticEntry(self::ATTRIBUTE_DIAGNOSTIC_EVENTS, [
            'time'    => time(),
            'type'    => (string) ($payload['type'] ?? ''),
            'device'  => (string) ($data['friendly_name'] ?? $data['device'] ?? $data['id'] ?? ''),
            'message' => $this->FormatDiagnosticValue($data === [] ? $payload : $data)
        ]);
    }

    /**
     * Aktualisiert Diagnose-Listen aus bridge/devices.
     */
    private function UpdateDeviceDiagnostics(array $devices): void
    {
        $networkDevices = [];
        $unsupported = [];
        $interviewIssues = [];
        foreach ($devices as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $row = $this->BuildDeviceDiagnosticRow($device);
            if (($row['status'] ?? '') !== 'Coordinator') {
                $networkDevices[] = $this->BuildCachedNetworkDeviceRow($device, $row);
            }
            if (($device['supported'] ?? true) === false || (\array_key_exists('definition', $device) && $device['definition'] === null)) {
                $unsupported[] = $row;
            }

            $interviewCompleted = $device['interview_completed'] ?? $device['interviewCompleted'] ?? null;
            $interviewing = (bool) ($device['interviewing'] ?? false);
            if ($interviewCompleted === false || $interviewing) {
                $row['status'] = $interviewing ? $this->Translate('Interview running') : $this->Translate('Interview incomplete');
                $interviewIssues[] = $row;
            }
        }

        usort($networkDevices, static fn (array $left, array $right): int => strnatcasecmp($left['friendly_name'], $right['friendly_name']));
        $this->WriteAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES, $networkDevices);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES, $unsupported);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES, $interviewIssues);
        $this->UpdateOTAFormLists();
    }

    /**
     * Fuegt einen Eintrag an den Anfang eines Diagnose-Ringpuffers.
     */
    private function AppendDiagnosticEntry(string $attribute, array $entry): void
    {
        $entries = $this->ReadAttributeArray($attribute);
        array_unshift($entries, $entry);
        $this->WriteAttributeArray($attribute, \array_slice($entries, 0, self::MAX_DIAGNOSTIC_ENTRIES));
    }

    /**
     * Erstellt eine Diagnose-Zeile fuer ein Geraet.
     */
    private function BuildDeviceDiagnosticRow(array $device): array
    {
        $definition = \is_array($device['definition'] ?? null) ? $device['definition'] : [];
        return [
            'friendly_name' => (string) ($device['friendly_name'] ?? $device['friendlyName'] ?? ''),
            'ieee_address'  => (string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''),
            'model'         => (string) ($definition['model'] ?? $device['model'] ?? ''),
            'vendor'        => (string) ($definition['vendor'] ?? $device['vendor'] ?? ''),
            'status'        => (string) ($device['type'] ?? '')
        ];
    }

    /**
     * Erstellt eine Cache-Zeile fuer den Konfigurator und lokale Auswahlfelder.
     */
    private function BuildCachedNetworkDeviceRow(array $device, array $diagnosticRow): array
    {
        $definition = \is_array($device['definition'] ?? null) ? $device['definition'] : [];
        $friendlyName = (string) ($device['friendly_name'] ?? $device['friendlyName'] ?? $diagnosticRow['friendly_name'] ?? '');
        $ieeeAddress = (string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? $diagnosticRow['ieee_address'] ?? '');

        return [
            'friendly_name'    => $friendlyName,
            'ieee_address'     => $ieeeAddress,
            'ieeeAddr'         => $ieeeAddress,
            'networkAddress'   => $device['networkAddress'] ?? $device['network_address'] ?? '',
            'type'             => (string) ($device['type'] ?? $diagnosticRow['status'] ?? ''),
            'model'            => (string) ($definition['model'] ?? $device['model'] ?? $diagnosticRow['model'] ?? ''),
            'vendor'           => (string) ($definition['vendor'] ?? $device['vendor'] ?? $diagnosticRow['vendor'] ?? ''),
            'description'      => (string) ($definition['description'] ?? $device['description'] ?? ''),
            'supports_ota'     => (bool) ($definition['supports_ota'] ?? $device['supports_ota'] ?? false),
            'manufacturerName' => (string) ($device['manufacturerName'] ?? $device['manufacturer_name'] ?? ''),
            'powerSource'      => (string) ($device['powerSource'] ?? $device['power_source'] ?? ''),
            'modelID'          => (string) ($device['modelID'] ?? $device['model_id'] ?? $definition['model'] ?? $device['model'] ?? ''),
            'endpoints'        => \is_array($device['endpoints'] ?? null) ? $device['endpoints'] : []
        ];
    }

    /**
     * Formatiert den Health-Status fuer die Form.
     */
    private function BuildHealthStatusCaption(): string
    {
        $health = $this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_HEALTH);
        if ($health === []) {
            return $this->Translate('Health status: not checked');
        }

        $status = \array_key_exists('healthy', $health)
            ? ((bool) $health['healthy'] ? $this->Translate('healthy') : $this->Translate('unhealthy'))
            : $this->Translate('health data received');

        return $this->Translate('Health status') . ': ' . $status . $this->FormatDiagnosticSuffix($health);
    }

    /**
     * Formatiert den Coordinator-Status fuer die Form.
     */
    private function BuildCoordinatorStatusCaption(): string
    {
        $coordinator = $this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_COORDINATOR);
        if ($coordinator === []) {
            return $this->Translate('Coordinator status: not checked');
        }

        $missingRouters = $this->ReadDiagnosticMissingRouters();
        $status = \count($missingRouters) === 0
            ? $this->Translate('no missing routers')
            : \sprintf($this->Translate('%d missing routers'), \count($missingRouters));

        return $this->Translate('Coordinator status') . ': ' . $status . $this->FormatDiagnosticSuffix($coordinator);
    }

    /**
     * Gibt die fehlenden Router aus dem letzten Coordinator-Check zurueck.
     */
    private function ReadDiagnosticMissingRouters(): array
    {
        $coordinator = $this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_COORDINATOR);
        $missingRouters = $coordinator['missing_routers'] ?? $coordinator['missingRouters'] ?? [];
        return \is_array($missingRouters) ? $missingRouters : [];
    }

    /**
     * Baut die Formwerte fuer fehlende Router.
     */
    private function BuildMissingRouterFormValues(): array
    {
        $values = [];
        foreach ($this->ReadDiagnosticMissingRouters() as $router) {
            if (!\is_array($router)) {
                continue;
            }

            $values[] = [
                'friendly_name' => (string) ($router['friendly_name'] ?? ''),
                'ieee_address'  => (string) ($router['ieee_address'] ?? $router['ieeeAddr'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Baut die Formwerte fuer Geraete-Diagnoselisten.
     */
    private function BuildDeviceDiagnosticFormValues(string $attribute): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray($attribute) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $values[] = [
                'friendly_name' => (string) ($entry['friendly_name'] ?? ''),
                'ieee_address'  => (string) ($entry['ieee_address'] ?? ''),
                'model'         => (string) ($entry['model'] ?? ''),
                'vendor'        => (string) ($entry['vendor'] ?? ''),
                'status'        => (string) ($entry['status'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Baut die Formwerte fuer Bridge-Events.
     */
    private function BuildEventFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_EVENTS) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $values[] = [
                'time'    => $this->FormatDiagnosticTimestamp($entry['time'] ?? 0),
                'type'    => (string) ($entry['type'] ?? ''),
                'device'  => (string) ($entry['device'] ?? ''),
                'message' => (string) ($entry['message'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Baut die Formwerte fuer Bridge-Warnungen und Fehler.
     */
    private function BuildLogFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_LOGS) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $values[] = [
                'time'      => $this->FormatDiagnosticTimestamp($entry['time'] ?? 0),
                'level'     => (string) ($entry['level'] ?? ''),
                'namespace' => (string) ($entry['namespace'] ?? ''),
                'message'   => (string) ($entry['message'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Formatiert einen Diagnose-Zeitstempel.
     */
    private function FormatDiagnosticTimestamp(mixed $timestamp): string
    {
        $timestamp = (int) $timestamp;
        return $timestamp > 0 ? date('d.m.Y H:i:s', $timestamp) : '';
    }

    /**
     * Liefert einen optionalen Zeitstempel-Suffix fuer Statuszeilen.
     */
    private function FormatDiagnosticSuffix(array $entry): string
    {
        $timestamp = $this->FormatDiagnosticTimestamp($entry['checked_at'] ?? 0);
        return $timestamp === '' ? '' : ' (' . $timestamp . ')';
    }

    /**
     * Formatiert Diagnosewerte kompakt fuer Formularlisten.
     */
    private function FormatDiagnosticValue(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $value = \is_string($encoded) ? $encoded : '';
        }

        $text = (string) $value;
        return \strlen($text) > 240 ? \substr($text, 0, 237) . '...' : $text;
    }

    /**
     * Baut die Formwerte fuer Touchlink-Scan-Ergebnisse.
     */
    private function BuildTouchlinkDeviceFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_TOUCHLINK_DEVICES) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $values[] = [
                'ieee_address' => (string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''),
                'channel'      => (string) ($device['channel'] ?? ''),
                'action'       => $this->Translate('Select')
            ];
        }

        return $values;
    }

    /**
     * Uebernimmt ein Touchlink-Geraet in die Eingabefelder.
     */
    private function SelectTouchlinkDeviceFromForm(mixed $value): bool
    {
        $target = $this->DecodeBridgeFormPayload($value);
        if ($target === null) {
            return false;
        }

        $this->UpdateFormField('TouchlinkIeeeAddress', 'value', (string) ($target['ieee_address'] ?? ''));
        $this->UpdateFormField('TouchlinkChannel', 'value', (int) ($target['channel'] ?? 0));
        return true;
    }

    /**
     * Fuehrt eine generische Bridge-Expertenaktion aus dem Formular aus.
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

        $this->UpdateFormField('BridgeExpertActionParams', 'value', '');
        $this->ShowBridgeExpertActionMessage('Bridge action executed', 'The Zigbee2MQTT expert action was sent successfully.');
        return true;
    }

    /**
     * Zeigt eine Rueckmeldung fuer Bridge-Expertenaktionen im Formular an.
     */
    private function ShowBridgeExpertActionMessage(string $title, string $message): void
    {
        $this->UpdateFormField('BridgeExpertActionMessageTitle', 'caption', $this->Translate($title));
        $this->UpdateFormField('BridgeExpertActionMessageText', 'caption', $this->Translate($message));
        $this->UpdateFormField('BridgeExpertActionMessage', 'visible', true);
    }

    /**
     * Dekodiert JSON-Payloads aus Bridge-Formularaktionen.
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
     * Baut und validiert einen Touchlink-Zielpayload.
     */
    private function BuildTouchlinkTargetPayload(string $IeeeAddress, int $Channel, bool $required): ?array
    {
        $IeeeAddress = trim($IeeeAddress);
        if ($IeeeAddress === '' && !$required) {
            return [];
        }
        if ($IeeeAddress === '' || $Channel <= 0) {
            trigger_error($this->Translate('Touchlink IEEE address and channel are required.'), E_USER_NOTICE);
            return null;
        }

        return [
            'ieee_address' => $IeeeAddress,
            'channel'      => $Channel
        ];
    }

    /**
     * Setzt ein Feld in der verschachtelten Form.
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

    /**
     * Sendet einen Bridge-Request und gibt bei erfolgreicher Antwort das data-Array zurueck.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param array  $Payload MQTT-Payload.
     * @param int    $Timeout Wartezeit auf die Bridge-Antwort in Millisekunden.
     *
     * @return array|false Antwortdaten oder false bei Fehler/Timeout.
     */
    private function SendCheckedBridgeRequest(string $Topic, array $Payload = [], int $Timeout = 5000): array|false
    {
        return $this->ValidateCheckedBridgeResponse($Topic, $this->SendData($Topic, $Payload, $Timeout));
    }

    /**
     * Sendet einen sensiblen Bridge-Request und maskiert dessen Payload im Debug-Protokoll.
     */
    private function SendCheckedSensitiveBridgeRequest(string $Topic, array $Payload = [], int $Timeout = 5000): array|false
    {
        return $this->ValidateCheckedBridgeResponse($Topic, $this->SendSensitiveData($Topic, $Payload, $Timeout), true);
    }

    /**
     * Wertet die Antwort eines Bridge-Requests einheitlich aus.
     */
    private function ValidateCheckedBridgeResponse(string $Topic, array|bool $Result, bool $Sensitive = false): array|false
    {
        if ($Result === false) {
            return false;
        }
        if (!is_array($Result)) {
            return [];
        }
        if (isset($Result['error'])) {
            trigger_error(
                $Sensitive
                    ? sprintf($this->Translate('Zigbee2MQTT request failed on Topic %s'), $Topic)
                    : (string) $Result['error'],
                E_USER_NOTICE
            );
            return false;
        }
        if (isset($Result['status']) && $Result['status'] !== 'ok') {
            trigger_error(sprintf($this->Translate('Zigbee2MQTT request failed on Topic %s'), $Topic), E_USER_NOTICE);
            return false;
        }
        if (isset($Result['data']) && is_array($Result['data'])) {
            return $Result['data'];
        }
        return [];
    }

    /**
     * Sendet einen Bridge-Request ohne technische Timeout-Notice fuer Formularaktionen.
     */
    private function SendQuietCheckedBridgeRequest(string $Topic, array $Payload = [], int $Timeout = 5000): array|false
    {
        set_error_handler(static function (): bool
        {
            return true;
        }, E_USER_NOTICE);

        try {
            return $this->ValidateCheckedBridgeResponse($Topic, $this->SendDataQuiet($Topic, $Payload, $Timeout));
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Sendet einen Bridge-Befehl ohne auf eine Antwort zu warten.
     *
     * Lange laufende Requests wie OTA-Updates oder Netzwerkkarten werden asynchron
     * angestossen, damit die Symcon-Ausfuehrung nicht blockiert.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param array  $Payload MQTT-Payload.
     *
     * @return bool true, wenn der Request an den MQTT-Parent uebergeben wurde.
     */
    private function SendBridgeCommand(string $Topic, array $Payload = []): bool
    {
        return $this->SendData($Topic, $Payload, 0) === true;
    }

    /**
     * Sendet einen Szenenbefehl an ein Geraet oder eine Gruppe.
     */
    private function SendSceneCommand(string $FriendlyName, array $Payload): bool
    {
        $FriendlyName = trim($FriendlyName, '/');
        if ($FriendlyName === '') {
            trigger_error($this->Translate('Friendly name is required.'), E_USER_NOTICE);
            return false;
        }

        return $this->SendBridgeCommand('/' . $FriendlyName . '/set', $Payload);
    }

    /**
     * Prueft die OTA-Verfuegbarkeit fuer Upgrade oder Downgrade.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param string $Url Optionaler OTA-Index.
     *
     * @return bool true, wenn Zigbee2MQTT ein passendes OTA-Image meldet.
     */
    private function CheckOTAAvailability(string $Topic, string $DeviceName, string $Url): bool
    {
        $Data = $this->SendCheckedBridgeRequest($Topic, $this->BuildOTAPayload($DeviceName, $Url), 10000);
        if ($Data === false) {
            return false;
        }

        return (bool) ($Data['update_available'] ?? $Data['updateAvailable'] ?? false);
    }

    /**
     * Baut den OTA-Payload fuer normale und Custom-URL-Requests.
     *
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param string $Url Optionaler OTA-Index, Firmware-URL oder lokaler Pfad.
     *
     * @return array
     */
    private function BuildOTAPayload(string $DeviceName, string $Url): array
    {
        $Payload = ['id' => $DeviceName];
        $Url = trim($Url);
        if ($Url !== '') {
            $Payload['url'] = $Url;
        }

        return $Payload;
    }

    /**
     * Plant ein OTA-Upgrade oder OTA-Downgrade fuer die naechste Geraeteanfrage.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param string $Url Optionaler OTA-Index, Firmware-URL oder lokaler Pfad.
     *
     * @return bool true, wenn Zigbee2MQTT die Planung akzeptiert.
     */
    private function ScheduleOTARequest(string $Topic, string $DeviceName, string $Url): bool
    {
        $Data = $this->SendCheckedBridgeRequest($Topic, $this->BuildOTAPayload($DeviceName, $Url));

        return $Data !== false;
    }

    /**
     * Sendet einen langen OTA-Request ohne auf dessen Abschluss zu warten.
     *
     * @param string $Topic MQTT-Request-Topic relativ zum Base Topic.
     * @param string $DeviceName Friendly Name oder IEEE-Adresse.
     * @param string $Url Optionaler OTA-Index, Firmware-URL oder lokaler Pfad.
     *
     * @return bool true, wenn der Request an den MQTT-Parent uebergeben wurde.
     */
    private function SendOTABridgeCommand(string $Topic, string $DeviceName, string $Url): bool
    {
        return $this->SendBridgeCommand($Topic, $this->BuildOTAPayload($DeviceName, $Url));
    }

}
