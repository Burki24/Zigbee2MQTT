<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';
require_once dirname(__DIR__) . '/libs/VariableProfileHelper.php';
require_once dirname(__DIR__) . '/libs/MQTTHelper.php';
require_once dirname(__DIR__) . '/libs/AttributeArrayHelper.php';

/**
 * Zigbee2MQTTBridge
 *
 * @property float $actualExtensionVersion Enthält die benötigte Version der Extension passend zu Z2M in einem InstanzBuffer
 * @property float $installedZhVersion Enthält die installierte Version des zigbee-herdsman Moduls
 * @property string $ExtensionFilename Enthält den Dateinamen der Extension in einem InstanzBuffer
 * @property string $ConfigLastSeen Enthält die Z2M Konfiguration der LastSeen Option in einem InstanzBuffer
 * @property bool $ConfigPermitJoin Enthält die Z2M Konfiguration der PermitJoin Option in einem InstanzBuffer
 */
class Zigbee2MQTTBridge extends IPSModuleStrict
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\VariableProfileHelper;
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
    private const MAX_DIAGNOSTIC_ENTRIES = 50;

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
        $this->TransactionData = [];
        $this->ConfigPermitJoin = false;

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
     * @uses Zigbee2MQTTBridge::RegisterProfileIntegerEx()
     * @uses Zigbee2MQTTBridge::RegisterProfileStringEx()
     * @uses Zigbee2MQTTBridge::RequestOptions()
     * @uses Zigbee2MQTTBridge::InstallSymconExtension()
     * @uses IPS_GetKernelRunlevel()
     */
    public function ApplyChanges(): void
    {
        // Empty TransactionQueue
        $this->TransactionData = [];

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

        $this->RegisterProfileIntegerEx('Z2M.bridge.restart', '', '', '', [
            [0, $this->Translate('Restart'), '', 0xFF0000],
        ]);
        $this->RegisterProfileStringEx('Z2M.brigde.loglevel', '', '', '', [
            ['error', $this->Translate('Error'), '', 0x00FF00],
            ['warning', $this->Translate('Warning'), '', 0x00FF00],
            ['info', $this->Translate('Information'), '', 0x00FF00],
            ['debug', $this->Translate('Debug'), '', 0x00FF00],
        ]);
        $this->RegisterVariableBoolean('state', $this->Translate('State'), '~Alert.Reversed');
        $this->RegisterVariableBoolean('extension_loaded', $this->Translate('Extension Loaded'));
        $this->RegisterVariableString('extension_version', $this->Translate('Extension Version'));
        $this->RegisterVariableBoolean('extension_is_current', $this->Translate('Extension is up to date'));
        $this->RegisterVariableString('log_level', $this->Translate('Log Level'), 'Z2M.brigde.loglevel');
        $this->EnableAction('log_level');
        $this->RegisterVariableBoolean('permit_join', $this->Translate('Allow joining the network'), '~Switch');
        $this->EnableAction('permit_join');
        $this->RegisterVariableBoolean('restart_required', $this->Translate('Restart Required'));
        $this->RegisterVariableInteger('restart_request', $this->Translate('Perform a restart'), 'Z2M.bridge.restart');
        $this->EnableAction('restart_request');
        $this->RegisterVariableString('version', $this->Translate('Version'));
        $this->RegisterVariableString('zigbee_herdsman_converters', $this->Translate('Zigbee Herdsman Converters Version'));
        $this->RegisterVariableString('zigbee_herdsman', $this->Translate('Zigbee Herdsman Version'));
        $this->RegisterVariableInteger('network_channel', $this->Translate('Network Channel'));

        $this->UnregisterVariable('permit_join_timeout');

        $online = false;
        if (!empty($BaseTopic)) {
            if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                $online = @$this->RequestOptions();
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
        $this->SendDebug('ReceiveData', $JSONString, 0);
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
        $this->SendDebug('MQTT Payload', $payloadJson, 0);
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
                if (isset($Payload['transaction'])) {
                    $this->UpdateTransaction($Payload);
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
            case 'log_level':
                $this->SetLogLevel((string) $value);
                break;
            case 'restart_request':
                $this->Restart();
                break;
            case 'ClearBridgeDiagnostics':
                $this->ClearBridgeDiagnostics();
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
            case 'SelectNetworkSecurityDevice':
                $this->SelectNetworkSecurityDeviceFromForm($value);
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
            $Form['actions'][0]['enabled'] = false;
            $Form['actions'][0]['caption'] = $this->Translate('Symcon-Extension is up-to-date');
        }
        if ($this->ConfigLastSeen == 'epoch') {
            $Form['actions'][1]['enabled'] = false;
            $Form['actions'][1]['caption'] = $this->Translate('last_seen setting is correct');
        }
        if ($this->ConfigPermitJoin) {
            $Form['actions'][2]['visible'] = true;
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
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function RequestOptions(): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> []
        ];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
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
        $Topic = '/bridge/request/permit_join';
        $Payload = ['time'=> ($PermitJoin ? 254 : 0)];
        return $this->SendCheckedBridgeRequest($Topic, $Payload) !== false;
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
     * CreateBackup
     *
     * @return string Base64-kodiertes ZIP-Backup oder leer bei Fehler.
     */
    public function CreateBackup(): string
    {
        $data = $this->SendCheckedBridgeRequest('/bridge/request/backup', [], 30000);
        if ($data === false) {
            return '';
        }

        return (string) ($data['zip'] ?? '');
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

        return $this->SendCheckedBridgeRequest('/bridge/request/install_code/add', ['value' => $Code]) !== false;
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
     * @param  string $DeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function RemoveDevice(string $DeviceName): bool
    {
        $Topic = '/bridge/request/device/remove';
        $Payload = ['id'=>$DeviceName];
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
     * Setzt einen Variablenwert module-strict-konform.
     */
    protected function SetValue(string $Ident, mixed $Value): bool
    {
        $variableID = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($variableID === false) {
            return false;
        }

        if (\defined('PHPUNIT_TESTSUITE') && PHPUNIT_TESTSUITE) {
            \SetValue($variableID, $Value);
            return true;
        }

        return parent::SetValue($Ident, $Value);
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
     * Ergaenzt die statische Bridge-Form um aktuelle Diagnosewerte.
     */
    private function BuildBridgeConfigurationForm(array $form): array
    {
        $availableDevices = $this->BuildNetworkSecurityAvailableDeviceFormValues();
        $this->SetBridgeFormField($form, 'NetworkSecurityAvailableDeviceList', 'values', $availableDevices);
        $this->SetBridgeFormField($form, 'NetworkSecurityAvailableDeviceList', 'rowCount', min(10, max(4, \count($availableDevices) + 1)));
        $this->SetBridgeFormField($form, 'NetworkSecurityBlocklist', 'values', $this->BuildNetworkSecurityListFormValues('blocklist'));
        $this->SetBridgeFormField($form, 'NetworkSecurityPasslist', 'values', $this->BuildNetworkSecurityListFormValues('passlist'));
        $this->SetBridgeFormField($form, 'DiagnosticHealthStatus', 'caption', $this->BuildHealthStatusCaption());
        $this->SetBridgeFormField($form, 'DiagnosticCoordinatorStatus', 'caption', $this->BuildCoordinatorStatusCaption());
        $this->SetBridgeFormField($form, 'DiagnosticMissingRoutersList', 'values', $this->BuildMissingRouterFormValues());
        $this->SetBridgeFormField($form, 'DiagnosticUnsupportedDevicesList', 'values', $this->BuildDeviceDiagnosticFormValues(self::ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES));
        $this->SetBridgeFormField($form, 'DiagnosticInterviewDevicesList', 'values', $this->BuildDeviceDiagnosticFormValues(self::ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES));
        $this->SetBridgeFormField($form, 'DiagnosticEventList', 'values', $this->BuildEventFormValues());
        $this->SetBridgeFormField($form, 'DiagnosticLogList', 'values', $this->BuildLogFormValues());
        $this->SetBridgeFormField($form, 'TouchlinkDeviceList', 'values', $this->BuildTouchlinkDeviceFormValues());

        return $form;
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
    private function BuildNetworkSecurityAvailableDeviceFormValues(): array
    {
        $values = [];
        foreach ($this->BuildNetworkSecurityDevices() as $device) {
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
    private function BuildNetworkSecurityListFormValues(string $listName): array
    {
        $attribute = $this->GetNetworkSecurityAttribute($listName);
        if ($attribute === '') {
            return [];
        }

        $deviceNames = $this->BuildNetworkSecurityDeviceNameMap();
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
    private function BuildNetworkSecurityDeviceNameMap(): array
    {
        $names = [];
        foreach ($this->BuildNetworkSecurityDevices() as $device) {
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
        $this->UpdateFormField('NetworkSecurityBlocklist', 'values', json_encode($this->BuildNetworkSecurityListFormValues('blocklist')));
        $this->UpdateFormField('NetworkSecurityPasslist', 'values', json_encode($this->BuildNetworkSecurityListFormValues('passlist')));
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
     * Baut die bekannten Geraete aus Cache, Instanzen und Extension-Fallback.
     */
    private function BuildNetworkSecurityDevices(): array
    {
        $devices = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES) as $device) {
            $this->AddNetworkSecurityDevice($devices, $device);
        }
        foreach ($this->LoadNetworkSecurityDevicesFromInstances() as $device) {
            $this->AddNetworkSecurityDevice($devices, $device);
        }
        if ($devices === []) {
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
     * Liest bekannte Device-Instanzen mit gleichem BaseTopic.
     */
    private function LoadNetworkSecurityDevicesFromInstances(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if ($baseTopic === '') {
            return [];
        }

        $devices = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
            if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic) {
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

        $result = @$this->SendData(self::SYMCON_EXTENSION_LIST_REQUEST . 'getDevices', [], 2500);
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
                $networkDevices[] = [
                    'friendly_name' => $row['friendly_name'],
                    'ieee_address'  => $row['ieee_address'],
                    'model'         => $row['model'],
                    'vendor'        => $row['vendor'],
                    'type'          => $row['status']
                ];
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
        $Result = $this->SendData($Topic, $Payload, $Timeout);
        if ($Result === false) {
            return false;
        }
        if (!is_array($Result)) {
            return [];
        }
        if (isset($Result['error'])) {
            trigger_error((string) $Result['error'], E_USER_NOTICE);
            return false;
        }
        if (($Result['status'] ?? '') !== 'ok') {
            trigger_error(sprintf($this->Translate('Zigbee2MQTT request failed on Topic %s'), $Topic), E_USER_NOTICE);
            return false;
        }
        if (isset($Result['data']) && is_array($Result['data'])) {
            return $Result['data'];
        }
        return [];
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
