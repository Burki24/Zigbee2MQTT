<?php

declare(strict_types=1);

/**
 * Gruppen-, Szenen- und Binding-Befehle der Zigbee2MQTT-Bridge.
 */
trait BridgeGroupSceneCommandHelper
{
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
     * @param  string $Endpoint Optionaler Zigbee-Endpunkt für die Gruppenzuordnung.
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
     * @param  string $Endpoint             Optionaler Zigbee-Endpunkt, der aus der Gruppe entfernt wird.
     * @param  bool   $SkipDisableReporting Gibt an, ob Reporting während des Entfernens aktiv bleiben soll.
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

}
