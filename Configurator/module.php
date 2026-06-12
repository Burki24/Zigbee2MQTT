<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';
require_once dirname(__DIR__) . '/libs/MQTTHelper.php';

/**
 * Zigbee2MQTTConfigurator
 */
class Zigbee2MQTTConfigurator extends IPSModuleStrict
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\SendData;

    /** @var array $DeviceValues */
    private static $DeviceValues = [
        'name'               => '',
        'id'                 => '',
        'parent'             => '',
        'instanceID'         => 0,
        'ieee_address'       => '',
        'topic'              => '',
        'networkAddress'     => '',
        'type'               => '',
        'vendor'             => '',
        'modelID'            => '',
        'description'        => '',
        'power_source'       => ''
    ];
    /** @var array $GroupValues */
    private static $GroupValues = [
        'name'               => '',
        'id'                 => '',
        'parent'             => '',
        'instanceID'         => 0,
        'ID'                 => 0,
        'topic'              => '',
        'DevicesCount'       => ''
    ];

    /**
     * Create
     *
     * @return void
     *
     * @uses IPSModule::Create()
     * @uses IPSModule::RegisterPropertyString()
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString(self::MQTT_BASE_TOPIC, '');
        // Init Buffers
        $this->ClearTransactionData();
    }

    /**
     * ApplyChanges
     *
     * @return void
     *
     * @uses IPSModule::ApplyChanges()
     * @uses IPSModule::ReadPropertyString()
     * @uses IPSModule::SetReceiveDataFilter()
     * @uses IPSModule::SetStatus()
     * @uses IPSModule::SendDebug()
     * @uses preg_quote()
     * @uses empty()
     */
    public function ApplyChanges(): void
    {
        $this->ClearTransactionData();

        //Never delete this line!
        parent::ApplyChanges();

        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if (empty($BaseTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
            return;
        }
        $this->SetStatus(IS_ACTIVE);
        //Setze Filter für ReceiveData
        $Filter1 = preg_quote('"Topic":"' . $BaseTopic . '/bridge/response');
        $Filter2 = preg_quote('"Topic":"' . $BaseTopic . self::SYMCON_EXTENSION_LIST_RESPONSE);
        $this->SendDebug('Filter', '.*(' . $Filter1 . '|' . $Filter2 . ').*', 0);
        $this->SetReceiveDataFilter('.*(' . $Filter1 . '|' . $Filter2 . ').*');
    }

    /**
     * RequestAction
     *
     * @param  string $ident
     * @param  mixed $value
     *
     * @return void
     *
     * @uses Zigbee2MQTTConfigurator::ReloadForm();
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        switch ($ident) {
            case 'ReloadForm':
                $this->ReloadForm();
                break;
            case 'RepairWrongConnection':
                $this->RepairWrongConnection((int) $value);
                break;
        }
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     *
     * @uses IPSModule::GetStatus()
     * @uses IPSModule::HasActiveParent()
     * @uses IPSModule::SendDebug()
     * @uses IPSModule::ReadPropertyString()
     * @uses IPSModule::Translate()
     * @uses Zigbee2MQTTConfigurator::RequestOptions()
     * @uses Zigbee2MQTTConfigurator::getDevices()
     * @uses Zigbee2MQTTConfigurator::getGroups()
     * @uses Zigbee2MQTTConfigurator::GetIPSInstancesByIEEE()
     * @uses Zigbee2MQTTConfigurator::GetIPSInstancesByBaseTopic()
     * @uses Zigbee2MQTTConfigurator::GetIPSInstancesByGroupId()
     * @uses Zigbee2MQTTConfigurator::AddParentElement()
     * @uses IPS_GetKernelRunlevel()
     * @uses IPS_GetInstance()
     * @uses IPS_GetConfiguration()
     * @uses IPS_GetName()
     * @uses IPS_GetProperty()
     * @uses json_decode()
     * @uses json_encode()
     * @uses file_get_contents()
     * @uses count()
     * @uses explode()
     * @uses isset()
     * @uses unset()
     * @uses empty()
     * @uses array_key_first()
     * @uses array_merge()
     * @uses array_push()
     * @uses array_pop()
     * @uses array_search()
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (($this->GetStatus() == IS_CREATING) || (IPS_GetKernelRunlevel() != KR_READY)) {
            return json_encode($Form);
        }
        if (!$this->HasActiveParent()) {
            $Form['actions'][0]['expanded'] = false;
            $Form['actions'][1]['expanded'] = false;
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Instance has no active parent.'
                    ]]
                ]
            ];
            $this->SendDebug('Form', json_encode($Form), 0);
            return json_encode($Form);
        }
        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);

        if (empty($BaseTopic)) {
            $this->SendDebug('Form', json_encode($Form), 0);
            return json_encode($Form);
        }
        if (!@$this->RequestOptions()) {
            $Form['actions'][0]['expanded'] = false;
            $Form['actions'][1]['expanded'] = false;
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'color'   => 16711680,
                        'bold'    => true,
                        'caption' => 'Zigbee2MQTT did not response!'
                    ]]
                ]
            ];
            $this->SendDebug('Form', json_encode($Form), 0);
            return json_encode($Form);
        }

        $WrongDeviceInstances = $this->GetIPSInstancesWithWrongConnectionByBaseTopic(self::GUID_MODULE_DEVICE, $BaseTopic);
        $WrongGroupInstances = $this->GetIPSInstancesWithWrongConnectionByBaseTopic(self::GUID_MODULE_GROUP, $BaseTopic);
        $WrongBridgeInstances = $this->GetIPSInstancesWithWrongConnectionByBaseTopic(self::GUID_MODULE_BRIDGE, $BaseTopic);
        $InstancedWithWrongIo = $this->BuildWrongConnectedInstanceValues(
            array_keys($WrongDeviceInstances + $WrongGroupInstances + $WrongBridgeInstances)
        );
        $WrongDevicesByIEEE = [];
        foreach (array_keys($WrongDeviceInstances) as $instanceID) {
            $WrongDevicesByIEEE[$instanceID] = (string) @IPS_GetProperty($instanceID, 'IEEE');
        }
        $WrongGroupsById = [];
        foreach (array_keys($WrongGroupInstances) as $instanceID) {
            $WrongGroupsById[$instanceID] = (int) @IPS_GetProperty($instanceID, 'GroupId');
        }
        $Devices = [];
        $Groups = [];
        $Devices = $this->getDevices(); // Alle Geräte von Z2M laden
        $this->SendDebug('NetworkDevices', json_encode($Devices), 0);
        $IPSDevicesByIEEE = $this->GetIPSInstancesByIEEE(); // ALLE Device-Instanzen mit IEEE holen
        $this->SendDebug('IPS Devices IEEE', json_encode($IPSDevicesByIEEE), 0);
        $IPSDevicesByTopic = $this->GetIPSInstancesByBaseTopic(self::GUID_MODULE_DEVICE, $BaseTopic); // Nur Device-Instanzen mit gleichem BaseTopic holen
        $this->SendDebug('IPS Devices Topic', json_encode($IPSDevicesByTopic), 0);
        $IPSBridgeIDs = $this->GetIPSInstancesByBaseTopic(self::GUID_MODULE_BRIDGE, $BaseTopic); // Bridge-Instanzen mit gleichem BaseTopic holen
        $this->SendDebug('IPS Bridge Instances', json_encode($IPSBridgeIDs), 0);
        if (!count($Devices)) {
            // Gruppen ausblenden, die Bridge aber ueber den regulaeren Konfigurator-Mechanismus anbieten.
            $Form['actions'][1]['expanded'] = false;

            if ($WrongBridgeInstances !== []) {
                $this->ConfigureWrongConnectedInstancesPopup($Form, $InstancedWithWrongIo);
                return json_encode($Form);
            }

            $bridgeValue = self::$DeviceValues;
            $bridgeValue['id'] = 1;
            $bridgeValue['name'] = 'Zigbee2MQTT Bridge';
            $bridgeValue['topic'] = 'bridge';
            $bridgeValue['type'] = 'Bridge';
            $bridgeValue['vendor'] = 'Zigbee2MQTT';
            $bridgeValue['description'] = 'Zigbee2MQTT Bridge';
            if (count($IPSBridgeIDs)) {
                $bridgeID = array_key_first($IPSBridgeIDs);
                $bridgeValue['name'] = IPS_GetName($bridgeID);
                $bridgeValue['instanceID'] = $bridgeID;
                $Form['actions'][2]['popup']['items'][2]['objectID'] = $bridgeID;
                $Form['actions'][2]['popup']['items'][2]['visible'] = true;
            } else {
                $bridgeValue['create'] = $this->BuildBridgeCreateDescriptor($BaseTopic);
            }

            $Form['actions'][0]['items'][0]['values'] = [$bridgeValue];
            $Form['actions'][0]['items'][0]['rowCount'] = 2;
            $Form['actions'][2]['visible'] = true;
            return json_encode($Form);
        }
        // Devices
        $CoordinatorFound = false;
        $valuesDevices = [];
        $valueId = 1;

        foreach ($Devices as $device) { // Geräte von Z2M durchgehen
            $value = self::$DeviceValues; // Array leeren
            $Location = explode('/', $device['friendly_name']);
            $Name = array_pop($Location);
            /** Coordinator Sonderbehandlung */
            if (($device['type'] == 'Coordinator') && !$CoordinatorFound) { //Coordinator Eintrag wird für die Bridge benutzt
                if (count($IPSBridgeIDs)) {
                    $BridgeId = array_key_first($IPSBridgeIDs);
                    unset($IPSBridgeIDs[$BridgeId]);
                    $value['name'] = IPS_GetName($BridgeId);
                    $value['instanceID'] = $BridgeId;
                } else {
                    $value['name'] = 'Zigbee2MQTT Bridge';
                }
                $value['parent'] = $this->AddParentElement($valueId, $valuesDevices, $Location, self::$DeviceValues);
                $value['id'] = $valueId++;
                $value['topic'] = 'bridge';
                $value['type'] = 'Bridge';
                $value['vendor'] = 'Zigbee2MQTT';
                $value['description'] = 'Zigbee2MQTT Bridge';
                if ($WrongBridgeInstances === []) {
                    $value['create'] = $this->BuildBridgeCreateDescriptor($BaseTopic, $Location);
                }
                array_push($valuesDevices, $value);
                $CoordinatorFound = true;
                continue;
            }
            // erst nach IEEE suchen
            $instanceID = array_search($device['ieeeAddr'], $IPSDevicesByIEEE);
            if ($instanceID) {
                unset($IPSDevicesByIEEE[$instanceID]);
                if (isset($IPSDevicesByTopic[$instanceID])) { // wenn auch in IPSDevicesByTopic vorhanden, hier löschen
                    unset($IPSDevicesByTopic[$instanceID]);
                }
            } else { // dann nach Topic suchen
                $instanceID = array_search($device['friendly_name'], $IPSDevicesByTopic);
                if ($instanceID) {
                    unset($IPSDevicesByTopic[$instanceID]);
                    if (isset($IPSDevicesByIEEE[$instanceID])) { // wenn auch in IPSDevicesByIEEE vorhanden, hier löschen
                        unset($IPSDevicesByIEEE[$instanceID]);
                    }
                }
            }
            $wrongInstanceID = array_search($device['ieeeAddr'], $WrongDevicesByIEEE);
            if (!$wrongInstanceID) {
                $wrongInstanceID = array_search($device['friendly_name'], $WrongDeviceInstances);
            }

            if ($instanceID) {
                $value['name'] = IPS_GetName($instanceID);
                $value['instanceID'] = $instanceID;

            } else {
                $value['name'] = $Name;
                $value['instanceID'] = 0;
            }
            $value['parent'] = $this->AddParentElement($valueId, $valuesDevices, $Location, self::$DeviceValues);
            $value['id'] = $valueId++;
            $value['ieee_address'] = $device['ieeeAddr'];
            $value['topic'] = $device['friendly_name'];
            $value['networkAddress'] = $device['networkAddress'];
            $value['type'] = $device['type'];
            $value['vendor'] = $device['vendor'] ?? $this->Translate('Unknown');
            $value['modelID'] = $device['modelID'] ?? $this->Translate('Unknown');
            $value['description'] = $device['description'] ?? $this->Translate('Unknown');
            $value['power_source'] = isset($device['powerSource']) ? $this->Translate($device['powerSource']) : $this->Translate('Unknown');
            if (!$instanceID && !$wrongInstanceID) {
                $value['create'] = [
                    'moduleID'      => self::GUID_MODULE_DEVICE,
                    'location'      => $Location,
                    'configuration' => [
                        self::MQTT_BASE_TOPIC    => $BaseTopic,
                        self::MQTT_TOPIC         => $device['friendly_name'],
                        'IEEE'                   => $device['ieeeAddr']
                    ]
                ];
            }
            array_push($valuesDevices, $value);
        }
        /** Coordinator Sonderbehandlung */
        foreach ($IPSBridgeIDs as $instanceID => $Topic) { // Alle restlichen Bridge Instanzen mit gleichem BaseTopic anzeigen
            $valuesDevices[] = [
                'name'               => IPS_GetName($instanceID),
                'id'                 => $valueId++,
                'parent'             => $this->AddParentElement($valueId, $valuesDevices, [], self::$DeviceValues),
                'instanceID'         => $instanceID,
                'ieee_address'       => '',
                'topic'              => 'bridge',
                'networkAddress'     => '',
                'type'               => '',
                'vendor'             => 'Zigbee2MQTT',
                'modelID'            => '',
                'description'        => 'Zigbee2MQTT Bridge',
                'power_source'       => ''

            ];
        }

        foreach ($IPSDevicesByIEEE as $instanceID => $IEEE) { // Nur die restlichen IEEE Einträge anzeigen welche an unserem Splitter hängen
            if ((IPS_GetInstance($instanceID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) ||
            (IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) != $BaseTopic)) {
                continue;
            }
            $Topic = '';
            if (isset($IPSDevicesByTopic[$instanceID])) { // wenn auch in IPSDevicesByTopic vorhanden, hier löschen
                $Topic = $IPSDevicesByTopic[$instanceID];
                unset($IPSDevicesByTopic[$instanceID]);
            }
            $Location = explode('/', $Topic);
            array_pop($Location);
            $valuesDevices[] = [
                'name'               => IPS_GetName($instanceID),
                'id'                 => $valueId++,
                'parent'             => $this->AddParentElement($valueId, $valuesDevices, $Location, self::$DeviceValues),
                'instanceID'         => $instanceID,
                'ieee_address'       => $IEEE,
                'topic'              => $Topic,
                'networkAddress'     => '',
                'type'               => '',
                'vendor'             => '',
                'modelID'            => '',
                'description'        => '',
                'power_source'       => ''

            ];
        }
        foreach ($IPSDevicesByTopic as $instanceID => $Topic) { // Die restlichen Einträge der Topic Liste unserer Instanzen (also mit gleichem BaseTopic) anzeigen
            $Location = explode('/', $Topic);
            array_pop($Location);
            $valuesDevices[] = [
                'name'               => IPS_GetName($instanceID),
                'id'                 => $valueId++,
                'parent'             => $this->AddParentElement($valueId, $valuesDevices, $Location, self::$DeviceValues),
                'instanceID'         => $instanceID,
                'ieee_address'       => @IPS_GetProperty($instanceID, 'IEEE'),
                'topic'              => $Topic,
                'networkAddress'     => '',
                'type'               => '',
                'vendor'             => '',
                'modelID'            => '',
                'description'        => '',
                'power_source'       => ''

            ];
        }

        $Groups = $this->getGroups();

        //Groups
        $this->SendDebug('NetworkGroups', json_encode($Groups), 0);
        $IPSGroupById = $this->GetIPSInstancesByGroupId(); // Alle Gruppen Instanzen holen wo BaseTopic und Splitter zu uns passen
        $this->SendDebug('IPS Group Id', json_encode($IPSGroupById), 0);
        $IPSGroupByTopic = $this->GetIPSInstancesByBaseTopic(self::GUID_MODULE_GROUP, $BaseTopic); // Alle Gruppen Instanzen holen wo das BaseTopic zu uns passt
        $this->SendDebug('IPS Group Topic', json_encode($IPSGroupByTopic), 0);

        $valuesGroups = [];
        $valueId = 1;
        foreach ($Groups as $group) {
            $value = []; //Array leeren
            $instanceID = array_search($group['ID'], $IPSGroupById);
            if ($instanceID) { //erst nach ID suchen
                unset($IPSGroupById[$instanceID]);
                if (isset($IPSGroupByTopic[$instanceID])) { //wenn auch in IPSGroupByTopic vorhanden, hier löschen
                    unset($IPSGroupByTopic[$instanceID]);
                }
            } else { // dann nach Topic suchen
                $instanceID = array_search($group['friendly_name'], $IPSGroupByTopic);
                if ($instanceID) {
                    unset($IPSGroupByTopic[$instanceID]);
                    if (isset($IPSGroupById[$instanceID])) { //wenn auch in IPSGroupById vorhanden, hier löschen
                        unset($IPSGroupById[$instanceID]);
                    }
                }
            }
            $wrongInstanceID = array_search((int) $group['ID'], $WrongGroupsById, true);
            if (!$wrongInstanceID) {
                $wrongInstanceID = array_search($group['friendly_name'], $WrongGroupInstances);
            }
            $Location = explode('/', $group['friendly_name']);
            $Name = array_pop($Location);
            if ($instanceID) {
                $value['name'] = IPS_GetName($instanceID);
                $value['instanceID'] = $instanceID;

            } else {
                $value['name'] = $Name;
                $value['instanceID'] = 0;
            }
            $value['parent'] = $this->AddParentElement($valueId, $valuesGroups, $Location, self::$GroupValues);
            $value['id'] = $valueId++;
            $value['ID'] = $group['ID'];
            $value['topic'] = $group['friendly_name'];
            $value['DevicesCount'] = (string) count($group['devices']);
            if (!$instanceID && !$wrongInstanceID) {
                $value['create'] = [
                    'moduleID'      => self::GUID_MODULE_GROUP,
                    'location'      => $Location,
                    'configuration' => [
                        self::MQTT_BASE_TOPIC    => $BaseTopic,
                        self::MQTT_TOPIC         => $group['friendly_name'],
                        'GroupId'                => $group['ID']
                    ]
                ];
            }
            array_push($valuesGroups, $value);
        }
        foreach ($IPSGroupById as $instanceID => $ID) {
            $Topic = '';
            if (isset($IPSGroupByTopic[$instanceID])) { //wenn auch in IPSGroupByTopic vorhanden, hier löschen
                $Topic = $IPSGroupByTopic[$instanceID];
                unset($IPSGroupByTopic[$instanceID]);
            }
            $Location = explode('/', $Topic);
            array_pop($Location);
            $valuesGroups[] = [
                'name'                  => IPS_GetName($instanceID),
                'id'                    => $valueId++,
                'parent'                => $this->AddParentElement($valueId, $valuesGroups, $Location, self::$GroupValues),
                'instanceID'            => $instanceID,
                'ID'                    => $ID,
                'topic'                 => $Topic,
                'DevicesCount'          => ''

            ];
        }
        foreach ($IPSGroupByTopic as $instanceID => $Topic) {
            $Location = explode('/', $Topic);
            array_pop($Location);
            $valuesGroups[] = [
                'name'                  => IPS_GetName($instanceID),
                'id'                    => $valueId++,
                'parent'                => $this->AddParentElement($valueId, $valuesGroups, $Location, self::$GroupValues),
                'instanceID'            => $instanceID,
                'ID'                    => @IPS_GetProperty($instanceID, 'GroupId'),
                'topic'                 => $Topic,
                'DevicesCount'          => ''

            ];
        }

        $Form['actions'][0]['items'][0]['values'] = $valuesDevices;
        $Form['actions'][0]['items'][0]['rowCount'] = (count($valuesDevices) > 15 ? 15 : count($valuesDevices) + 1);
        $Form['actions'][1]['items'][0]['values'] = $valuesGroups;
        $Form['actions'][1]['items'][0]['rowCount'] = (count($valuesGroups) > 15 ? 15 : count($valuesGroups) + 1);
        $this->SendDebug('defect', json_encode($InstancedWithWrongIo), 0);
        $this->ConfigureWrongConnectedInstancesPopup($Form, $InstancedWithWrongIo);
        $this->SendDebug('Form', json_encode($Form), 0);
        return json_encode($Form);
    }

    /**
     * ReceiveData
     *
     * @param  string $JSONString
     *
     * @return string
     *
     * @uses IPSModule::GetStatus()
     * @uses IPSModule::SendDebug()
     * @uses IPSModule::ReadPropertyString()
     * @uses Zigbee2MQTTConfigurator::UpdateTransaction()
     * @uses json_decode()
     * @uses Zigbee2MQTTConfigurator::DecodePayload()
     * @uses empty()
     * @uses isset()
     * @uses strpos()
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
        if ((strpos($ReceiveTopic, $BaseTopic . self::SYMCON_EXTENSION_LIST_RESPONSE) !== 0) &&
        (strpos($ReceiveTopic, $BaseTopic . '/bridge/response/') !== 0)) {
            return '';
        }
        $this->SendDebug('MQTT Topic', $ReceiveTopic, 0);
        $payloadJson = self::DecodePayload($Buffer['Payload']);
        $this->SendLimitedDebug('MQTT Payload', $payloadJson, 0);
        $Payload = json_decode($payloadJson, true);
        if (isset($Payload['transaction'])) {
            $this->UpdateTransaction($Payload);
            return '';
        }
        if (\is_array($Payload)) {
            $this->UpdateTransactionByResponseTopic(substr($ReceiveTopic, \strlen($BaseTopic)), $Payload);
        }
        return '';
    }

    /**
     * Builds the regular Symcon configurator descriptor for a missing Bridge instance.
     *
     * @param string   $BaseTopic
     * @param string[] $Location
     *
     * @return array<string, mixed>
     */
    private function BuildBridgeCreateDescriptor(string $BaseTopic, array $Location = []): array
    {
        return [
            'moduleID'      => self::GUID_MODULE_BRIDGE,
            'location'      => $Location,
            'configuration' => [
                self::MQTT_BASE_TOPIC => $BaseTopic
            ]
        ];
    }

    /**
     * Connects one explicitly selected Zigbee2MQTT instance to the configurator's MQTT splitter.
     */
    private function RepairWrongConnection(int $InstanceID): void
    {
        if (!IPS_InstanceExists($InstanceID)) {
            return;
        }

        $instance = IPS_GetInstance($InstanceID);
        $moduleID = (string) ($instance['ModuleInfo']['ModuleID'] ?? '');
        if (!in_array($moduleID, [self::GUID_MODULE_BRIDGE, self::GUID_MODULE_DEVICE, self::GUID_MODULE_GROUP], true)) {
            return;
        }
        if ((string) @IPS_GetProperty($InstanceID, self::MQTT_BASE_TOPIC) !== $this->ReadPropertyString(self::MQTT_BASE_TOPIC)) {
            return;
        }

        $splitterID = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($splitterID <= 0 || (int) $instance['ConnectionID'] === $splitterID) {
            return;
        }

        @IPS_DisconnectInstance($InstanceID);
        IPS_ConnectInstance($InstanceID, $splitterID);
        $this->ReloadForm();
    }

    /**
     * getDevices
     *
     * @return array
     *
     * @uses Zigbee2MQTTConfigurator::SendData()
     */
    private function getDevices(): array
    {
        $Devices = $this->ReadCachedNetworkDevicesFromBridge();
        if ($Devices !== []) {
            $this->SendDebug('getDevices:Cache', 'Bridge cache: ' . \count($Devices) . ' devices', 0);
            return $Devices;
        }

        $Result = @$this->SendData(self::SYMCON_EXTENSION_LIST_REQUEST . 'getDevices');
        if (\is_array($Result) && \is_array($Result['list'] ?? null)) {
            return $Result['list'];
        }

        return [];
    }

    /**
     * getGroups
     *
     * @return array
     *
     * @uses Zigbee2MQTTConfigurator::SendData()
     */
    private function getGroups(): array
    {
        $Result = @$this->SendData(self::SYMCON_EXTENSION_LIST_REQUEST . 'getGroups');
        if (\is_array($Result) && \is_array($Result['list'] ?? null)) {
            return $Result['list'];
        }
        return [];
    }

    /**
     * RequestOptions
     *
     * @return bool
     *
     * @uses Zigbee2MQTTConfigurator::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    private function RequestOptions(): bool
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> []
        ];
        $Result = $this->SendData($Topic, $Payload);
        if (isset($Result['error'])) {
            trigger_error($Result['error'], E_USER_NOTICE);
        }
        if (isset($Result['status'])) {
            return $Result['status'] == 'ok';
        }
        return false;
    }

    /**
     * Liest bekannte Geraete aus dem Bridge-Cache, falls die SymconExtension-Liste nicht antwortet.
     */
    private function ReadCachedNetworkDevicesFromBridge(): array
    {
        $BridgeID = $this->FindMatchingBridgeInstanceID();
        if ($BridgeID === false || !\function_exists('Z2M_GetCachedNetworkDevices')) {
            return [];
        }

        $Result = @\Z2M_GetCachedNetworkDevices($BridgeID);
        if (!\is_string($Result) || $Result === '') {
            return [];
        }

        $Decoded = json_decode($Result, true);
        if (!\is_array($Decoded)) {
            return [];
        }

        $Devices = [];
        foreach ($Decoded as $Device) {
            if (!\is_array($Device)) {
                continue;
            }

            $Normalized = $this->NormalizeCachedNetworkDevice($Device);
            if ($Normalized['friendly_name'] === '' && $Normalized['ieeeAddr'] === '') {
                continue;
            }

            $Devices[] = $Normalized;
        }

        return $Devices;
    }

    /**
     * Normalisiert Bridge-Cache-Daten auf das Format der SymconExtension-Geraeteliste.
     */
    private function NormalizeCachedNetworkDevice(array $Device): array
    {
        $FriendlyName = (string) ($Device['friendly_name'] ?? $Device['friendlyName'] ?? '');
        $IeeeAddress = (string) ($Device['ieeeAddr'] ?? $Device['ieee_address'] ?? $Device['ieeeAddress'] ?? '');

        return [
            'ieeeAddr'       => $IeeeAddress,
            'friendly_name'  => $FriendlyName,
            'type'           => (string) ($Device['type'] ?? ''),
            'networkAddress' => $Device['networkAddress'] ?? $Device['network_address'] ?? '',
            'vendor'         => (string) ($Device['vendor'] ?? ''),
            'modelID'        => (string) ($Device['modelID'] ?? $Device['model_id'] ?? $Device['model'] ?? ''),
            'description'    => (string) ($Device['description'] ?? ''),
            'powerSource'    => (string) ($Device['powerSource'] ?? $Device['power_source'] ?? '')
        ];
    }

    /**
     * Findet die Bridge-Instanz mit gleichem MQTT-Basistopic.
     */
    private function FindMatchingBridgeInstanceID(): int|false
    {
        $BridgeIDs = $this->GetIPSInstancesByBaseTopic(
            self::GUID_MODULE_BRIDGE,
            $this->ReadPropertyString(self::MQTT_BASE_TOPIC)
        );

        if ($BridgeIDs === []) {
            return false;
        }

        return (int) array_key_first($BridgeIDs);
    }

    /**
     * TopicPathExists
     *
     * @param  array $values
     * @param  string $Topic
     * @return int|false
     */
    private function TopicPathExists(array $values, string $Topic)
    {
        foreach ($values as $dir) {
            if ($dir['topic'] === $Topic) {
                return $dir['id'];
            }
        }
        return false;
    }

    /**
     * AddParentElement
     *
     * @param  int $Id
     * @param  array $Result
     * @param  array $Topics
     * @param  array $EmtpyValuesArray
     * @return int
     *
     * @uses Zigbee2MQTTConfigurator::TopicPathExists()
     * @uses array_merge()
     */
    private function AddParentElement(int &$Id, array &$Result, array $Topics, array $EmtpyValuesArray): int
    {
        $parentId = 0; // Für root-Verzeichnisse setzen wir parent auf 0
        $currentPath = ''; // Der aktuelle Pfad wird hier zusammengebaut

        // Durchlaufe jedes Teil des Verzeichnisses
        foreach ($Topics as $Topic) {
            // Baue den aktuellen Pfad schrittweise auf
            $currentPath .= ($currentPath === '' ? '' : '/') . $Topic;

            // Prüfe, ob das Verzeichnis bereits existiert (nur anhand des vollständigen Pfads)
            $existingId = $this->TopicPathExists($Result, $currentPath);

            if ($existingId === false) {
                // Füge das Verzeichnis dem Ergebnis hinzu
                $Result[] = array_merge($EmtpyValuesArray, [
                    'id'      => $Id,
                    'topic'   => $currentPath, // Fügt den gesamten Pfad hinzu
                    'parent'  => $parentId, // Für root 0, sonst parentId des vorherigen Verzeichnisses
                    'expanded'=> true
                ]);

                // Setze den parentId für das nächste Verzeichnis
                $parentId = $Id;
                $Id++; // Erhöhe die ID für das nächste Verzeichnis
            } else {
                // Falls das Verzeichnis existiert, setze parentId auf die ID des existierenden Verzeichnisses
                $parentId = $existingId;
            }
        }
        return $parentId;
    }

    /**
     * GetIPSInstancesByIEEE
     *
     * @return array
     *
     * @uses IPS_GetInstanceListByModuleID()
     * @uses IPS_GetProperty()
     * @uses array_filter()
     */
    private function GetIPSInstancesByIEEE(): array
    {
        $Devices = [];
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE), [$this, 'FilterInstances']);
        foreach ($InstanceIDList as $InstanceID) {
            $Devices[$InstanceID] = @IPS_GetProperty($InstanceID, 'IEEE');
        }
        return array_filter($Devices);
    }

    /**
     * GetIPSInstancesByGroupId
     *
     * @return array
     *
     * @uses Zigbee2MQTTConfigurator::FilterInstances()
     * @uses IPS_GetInstanceListByModuleID()
     * @uses IPS_GetProperty()
     * @uses array_filter()
     */
    private function GetIPSInstancesByGroupId(): array
    {
        $Devices = [];

        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID(self::GUID_MODULE_GROUP), [$this, 'FilterInstances']);
        foreach ($InstanceIDList as $InstanceID) {
            $Devices[$InstanceID] = @IPS_GetProperty($InstanceID, 'GroupId');
        }
        return array_filter($Devices);
    }

    /**
     * GetIPSInstancesByBaseTopic
     *
     * @param  string $GUID
     * @param  string $BaseTopic
     *
     * @return array
     *
     * @uses IPS_GetInstanceListByModuleID()
     * @uses IPS_GetProperty()
     */
    private function GetIPSInstancesByBaseTopic(string $GUID, string $BaseTopic): array
    {
        $Devices = [];
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID($GUID), [$this, 'FilterInstancesByConnection']);
        foreach ($InstanceIDList as $InstanceID) {
            if (@IPS_GetProperty($InstanceID, 'MQTTBaseTopic') == $BaseTopic) {
                $Devices[$InstanceID] = @IPS_GetProperty($InstanceID, self::MQTT_TOPIC);
            }
        }
        return $Devices;
    }

    /**
     * Returns instances with the requested base topic that are connected to another MQTT splitter.
     *
     * @param string $GUID
     * @param string $BaseTopic
     *
     * @return array<int, string>
     */
    private function GetIPSInstancesWithWrongConnectionByBaseTopic(string $GUID, string $BaseTopic): array
    {
        $instances = [];
        foreach (IPS_GetInstanceListByModuleID($GUID) as $instanceID) {
            if ($this->FilterInstancesByConnection($instanceID)) {
                continue;
            }
            if ((string) @IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $BaseTopic) {
                continue;
            }
            $instances[$instanceID] = (string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC);
        }

        return $instances;
    }

    /**
     * Builds the dedicated repair list for instances connected to another MQTT splitter.
     *
     * @param int[] $InstanceIDs
     *
     * @return array<int, array<string, int|string>>
     */
    private function BuildWrongConnectedInstanceValues(array $InstanceIDs): array
    {
        $values = [];
        foreach ($InstanceIDs as $instanceID) {
            if (!IPS_InstanceExists($instanceID)) {
                continue;
            }

            $instance = IPS_GetInstance($instanceID);
            $topic = (string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC);
            if (($instance['ModuleInfo']['ModuleID'] ?? '') === self::GUID_MODULE_BRIDGE) {
                $topic = 'bridge';
            }
            $values[] = [
                'name'          => IPS_GetName($instanceID),
                'topic'         => $topic,
                'instanceIdStr' => '#' . $instanceID,
                'instanceId'    => $instanceID,
                'action'        => $this->Translate('Repair')
            ];
        }

        return $values;
    }

    /**
     * Shows the dedicated repair popup for instances connected to another MQTT splitter.
     *
     * @param array<string, mixed>                         $Form
     * @param array<int, array<string, int|string>> $Values
     */
    private function ConfigureWrongConnectedInstancesPopup(array &$Form, array $Values): void
    {
        if ($Values === []) {
            return;
        }

        $Form['actions'][3]['visible'] = true;
        $Form['actions'][3]['popup']['items'][1]['rowCount'] = min(count($Values) + 1, 20);
        $Form['actions'][3]['popup']['items'][1]['values'] = $Values;
    }

    /**
     * FilterInstances
     *
     * @param  int $InstanceID
     *
     * @return bool
     *
     * @uses Zigbee2MQTTConfigurator::FilterInstancesByConnection()
     * @uses Zigbee2MQTTConfigurator::FilterInstancesByBaseTopic()
     */
    private function FilterInstances(int $InstanceID): bool
    {
        return
            $this->FilterInstancesByConnection($InstanceID)
         && (
             $this->FilterInstancesByBaseTopic($InstanceID)
         );
    }

    /**
     * FilterInstancesByConnection
     *
     * @param  int $InstanceID
     *
     * @return bool
     *
     * @uses IPS_GetInstance()
     */
    private function FilterInstancesByConnection(int $InstanceID): bool
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == IPS_GetInstance($this->InstanceID)['ConnectionID'];
    }

    /**
     * FilterInstancesByBaseTopic
     *
     * @param  int $InstanceID
     *
     * @return bool
     *
     * @uses IPS_GetProperty()
     */
    private function FilterInstancesByBaseTopic(int $InstanceID): bool
    {
        return IPS_GetProperty($InstanceID, self::MQTT_BASE_TOPIC) == IPS_GetProperty($this->InstanceID, self::MQTT_BASE_TOPIC);
    }
}
