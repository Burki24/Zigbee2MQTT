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
            $this->TryUpdateFormField('InstallExtension', 'caption', $this->Translate('Symcon-Extension is up-to-date'));
            $this->TryUpdateFormField('InstallExtension', 'enabled', false);
        } else {
            $this->TryUpdateFormField('InstallExtension', 'caption', $this->Translate('Install or upgrade Symcon-Extension'));
            $this->TryUpdateFormField('InstallExtension', 'enabled', true);
            if (!empty($BaseTopic)) {
                if ($online) {
                    @$this->InstallSymconExtension();
                }
            }
        }
        $this->SynchronizeOTAMessageSubscriptions();
    }

    /**
     * Aktualisiert OTA-Beobachtungen bei Änderungen an beobachteten Device-Variablen.
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE && \in_array($SenderID, $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_VARIABLES), true)) {
            $this->TryUpdateOTAFormLists();
            return;
        }

        if (
            \in_array($Message, [OM_CHILDADDED, OM_CHILDREMOVED], true) &&
            \in_array($SenderID, $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_DEVICES), true)
        ) {
            $this->SynchronizeOTAMessageSubscriptions();
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
     * Aktualisiert ein Formularfeld, sofern die Symcon-Formularschnittstelle verfuegbar ist.
     *
     * MessageSink kann waehrend eines Modul-Updates durch VM_UPDATE ausgeloest werden.
     * In diesem Moment steht die InstanceInterface-Schnittstelle nicht immer bereit.
     *
     * @return bool True, wenn das Formularfeld aktualisiert wurde.
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

        $this->TryUpdateFormField('BridgeExpertActionParams', 'value', '');
        $this->ShowBridgeExpertActionMessage('Bridge action executed', 'The Zigbee2MQTT expert action was sent successfully.');
        return true;
    }

    /**
     * Zeigt eine Rueckmeldung fuer Bridge-Expertenaktionen im Formular an.
     */
    private function ShowBridgeExpertActionMessage(string $title, string $message): void
    {
        $this->TryUpdateFormField('BridgeExpertActionMessageTitle', 'caption', $this->Translate($title));
        $this->TryUpdateFormField('BridgeExpertActionMessageText', 'caption', $this->Translate($message));
        $this->TryUpdateFormField('BridgeExpertActionMessage', 'visible', true);
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

}
