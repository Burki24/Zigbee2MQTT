/*
 IPSymconExtension
 Version: 6.05
*/

class IPSymconExtension {
    constructor(zigbee, mqtt, state, publishEntityState, eventBus, settings, logger) {
        this.zigbee = zigbee;
        this.mqtt = mqtt;
        this.state = state;
        this.publishEntityState = publishEntityState;
        this.eventBus = eventBus;
        this.settings = settings;
        this.logger = logger;

        this.baseTopic = settings.get().mqtt.base_topic;
        this.symconTopic = 'symcon';

        this.eventBus.on('mqttMessage', this.onMQTTMessage.bind(this), this);
        logger.info('Loaded IP-Symcon Extension');
    }

    async start() {
        this.mqtt.subscribe(`${this.symconTopic}/${this.baseTopic}/#`);
    }

    async onMQTTMessage(data) {
        const topicPrefix = `${this.symconTopic}/${this.baseTopic}`;
        if (data.topic.startsWith(`${this.baseTopic}/SymconExtension/request/getDeviceInfo/`)) {
            try {
                const devicename = data.topic.split('/').slice(4).join('/');
                const message = JSON.parse(data.message);
                const device = this.zigbee.resolveEntity(devicename);
                let devicepayload = {};
                if (typeof device !== "undefined") {
                    devicepayload = this.#createDevicePayload(device, true);
                }
                devicepayload.transaction = message.transaction;
                this.logger.info('Symcon: request/getDevice');
                await this.mqtt.publish(`SymconExtension/response/getDeviceInfo/${devicename}`, JSON.stringify(devicepayload), {
                    retain: false,
                    qos: 0
                }, `${this.baseTopic}`, false, false);
            } catch (error) {
                let errormessage = 'Unknown Error'
                if (error instanceof Error) errormessage = error.message
                this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
            }
            return;
        }
        if (data.topic.startsWith(`${this.baseTopic}/SymconExtension/request/getGroupInfo`)) {
            try {
                const groupname = data.topic.split('/').slice(4).join('/');
                const message = JSON.parse(data.message);
                const groupExposes = this.#createGroupExposes(groupname);
                groupExposes.transaction = message.transaction;
                this.logger.info('Symcon: request/getGroup');
                await this.mqtt.publish(`SymconExtension/response/getGroupInfo/${groupname}`, JSON.stringify(groupExposes), {
                    retain: false,
                    qos: 0
                }, `${this.baseTopic}`, false, false);
            } catch (error) {
                let errormessage = 'Unknown Error'
                if (error instanceof Error) errormessage = error.message
                this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
            }
            return;
        }
        if (data.topic == `${this.baseTopic}/SymconExtension/lists/request/getGroups`) {
            try {
                const message = JSON.parse(data.message);
                const groups = {
                    list: [],
                    transaction: 0,
                };
                groups.list = this.settings.getGroups().map(group => this.#createLegacyGroupPayload(group));
                groups.transaction = message.transaction;
                this.logger.info('Symcon: lists/request/getGroups');
                await this.mqtt.publish('SymconExtension/lists/response/getGroups', JSON.stringify(groups), {
                    retain: false,
                    qos: 0
                }, `${this.baseTopic}`, false, false);
            } catch (error) {
                let errormessage = 'Unknown Error'
                if (error instanceof Error) errormessage = error.message
                this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
            }
            return;
        }
        if (data.topic == `${this.baseTopic}/SymconExtension/lists/request/getDevices`
            || data.topic == `${this.baseTopic}/SymconExtension/lists/request/getDevicesLight`) {
            try {
                const message = JSON.parse(data.message);
                const lightweight = data.topic.endsWith('/getDevicesLight');
                const devices = {
                    list: [],
                    transaction: 0,
                };
                try {
                    for (const device of this.zigbee.devicesIterator()) {
                        devices.list = devices.list.concat(this.#createDevicePayload(device, false, lightweight));
                    }
                } catch (error) {
                    devices.list = this.zigbee.devices(false).map(device => this.#createDevicePayload(device, false, lightweight));
                }
                devices.transaction = message.transaction;
                this.logger.info(lightweight ? 'Symcon: lists/request/getDevicesLight' : 'Symcon: lists/request/getDevices');
                await this.mqtt.publish(
                    lightweight ? 'SymconExtension/lists/response/getDevicesLight' : 'SymconExtension/lists/response/getDevices',
                    JSON.stringify(devices),
                    {
                        retain: false,
                        qos: 0
                    },
                    `${this.baseTopic}`,
                    false,
                    false
                );
            } catch (error) {
                let errormessage = 'Unknown Error'
                if (error instanceof Error) errormessage = error.message
                this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
            }
            return;
        }
        switch (data.topic) {
            case `${topicPrefix}/getDevices`: // deprecated
            case `${this.baseTopic}/bridge/symcon/getDevices`: // deprecated
                let devices = [];
                try {
                    for (const device of this.zigbee.devicesIterator(this.#deviceNotCoordinator)) {
                        devices = devices.concat(this.#createDevicePayload(device, false));
                    }
                } catch (error) {
                    devices = this.zigbee.devices(false).map(device => this.#createDevicePayload(device, false));
                }
                this.logger.info('Symcon: publish devices list');
                await this.#publishToMqtt('devices', devices);
                break;
            case `${topicPrefix}/getDevice`: // deprecated
            case `${this.baseTopic}/bridge/symcon/getDevice`: // deprecated
                if (data.message) {
                    const device = this.zigbee.resolveEntity(data.message);
                    if (typeof device !== "undefined") {
                        const devices = this.#createDevicePayload(device, true);
                        this.logger.info('Symcon: getDevice');
                        await this.#publishToMqtt(`${device.name}/deviceInfo`, devices);
                    }
                }
                break;
            case `${topicPrefix}/getGroups`: // deprecated
            case `${this.baseTopic}/bridge/symcon/getGroups`: // deprecated
                const groups = this.settings.getGroups();
                await this.#publishToMqtt('groups', groups);
                break;
            case `${topicPrefix}/getGroup`: // deprecated
            case `${this.baseTopic}/bridge/symcon/getGroup`: // deprecated
                if (data.message) {
                    const groupExposes = this.#createGroupExposes(data.message);
                    await this.#publishToMqtt(`${data.message}/groupInfo`, groupExposes);
                }
                break;
        }
    }

    async stop() {
        this.eventBus.removeListeners(this);
    }

    #createDevicePayload(device, boolExposes, lightweight = false) {
        const definition = device.definition ?? device._definition ?? {};
        const options = device.options ?? {};
        const payload = {
            ieeeAddr: device.ieeeAddr,
            type: device.zh.type,
            networkAddress: device.zh.networkAddress,
            model: definition.model ?? 'Unknown Model',
            vendor: definition.vendor ?? 'Unknown Vendor',
            description: definition.description ?? 'No description',
            friendly_name: device.name,
            manufacturerName: device.zh.manufacturerName,
            powerSource: device.zh.powerSource,
            modelID: device.zh.modelID,
        };

        if (lightweight) {
            return payload;
        }

        payload.endpoints = this.#createEndpointPayload(device);
        payload.options = options;
        payload.definition_options = definition.options ?? [];
        payload.filtered_attributes = options.filtered_attributes ?? [];
        payload.supports_ota = definition.supports_ota ?? false;

        if (boolExposes) {
            payload.exposes = device.exposes();
        }

        return payload;
    }

    async #publishToMqtt(topicSuffix, payload) {
        await this.mqtt.publish(`${topicSuffix}`, JSON.stringify(payload), {
            retain: false,
            qos: 0
        }, `${this.symconTopic}/${this.baseTopic}`, false, false);
    }

    #createGroupExposes(groupName) {
        const groupSupportedTypes = ['light', 'switch', 'lock', 'cover'];
        const groups = this.settings.getGroups();
        const groupExposes = {
            foundGroup: false,
            options: {},
            members: [],
            scenes: [],
            ID: null,
            friendly_name: groupName
        };

        groupSupportedTypes.forEach(type => groupExposes[type] = {
            type,
            features: []
        });

        groups.forEach(group => {
            if (group.friendly_name === groupName) {
                groupExposes.foundGroup = true;
                groupExposes.options = group.options ?? {};
                groupExposes.members = this.#createLegacyGroupMemberPayload(group);
                groupExposes.scenes = this.#plainArray(group.scenes ?? group.meta?.scenes ?? []);
                groupExposes.ID = group.ID ?? group.id ?? null;
                groupExposes.friendly_name = group.friendly_name ?? groupName;
                this.#processGroupDevices(group, groupExposes);
            }
        });

        return groupExposes;
    }

    #processGroupDevices(group, groupExposes) {
        this.#createLegacyGroupMemberPayload(group).forEach(member => {
            const device = this.zigbee.resolveEntity(member.ieee_address || member.device);
            if (typeof device !== "undefined") {
                this.#addDeviceExposesToGroup(device, groupExposes);
            }
        });
    }

    #createLegacyGroupPayload(group) {
        return {
            ...group,
            members: this.#createLegacyGroupMemberPayload(group),
            scenes: this.#plainArray(group.scenes ?? group.meta?.scenes ?? []),
            options: group.options ?? {},
        };
    }

    #createLegacyGroupMemberPayload(group) {
        const devices = group.devices ?? [];
        if (!Array.isArray(devices)) {
            return [];
        }

        return devices.map(deviceAddress => {
            const parts = String(deviceAddress).split('/');
            return {
                device: parts[0] ?? '',
                ieee_address: parts[0] ?? '',
                endpoint: parts[1] ?? ''
            };
        });
    }

    #addDeviceExposesToGroup(device, groupExposes) {
        let exposes = [];

        // Überprüfen, ob 'definition' vorhanden ist und Exposes hinzufügen
        if (device.definition && device.definition.exposes) {
            exposes = exposes.concat(device.definition.exposes);
        }

        // Überprüfen, ob '_definition' vorhanden ist und Exposes hinzufügen
        if (device._definition && device._definition.exposes) {
            exposes = exposes.concat(device._definition.exposes);
        }

        // Verarbeite alle gesammelten Exposes
        exposes.forEach(expose => {
            const type = expose.type;
            if (groupExposes[type]) {
                this.#processExposeFeatures(expose, groupExposes[type]);
            }
        });
    }
    #processExposeFeatures(expose, groupExposeType) {
        expose.features.forEach(feature => {
            const existingIndex = groupExposeType.features.findIndex(f => f.property === feature.property);
            if (existingIndex === -1) {
                groupExposeType.features.push(feature);
                return;
            }

            groupExposeType.features[existingIndex] = this.#mergeGroupExposeFeature(
                groupExposeType.features[existingIndex],
                feature
            );
        });
    }

    #mergeGroupExposeFeature(existing, incoming) {
        const merged = {...existing};
        if (Number.isFinite(existing.access) && Number.isFinite(incoming.access)) {
            merged.access = existing.access & incoming.access;
        }

        if (existing.type !== 'numeric' || incoming.type !== 'numeric') {
            return merged;
        }

        const minimumValues = [existing.value_min, incoming.value_min].filter(Number.isFinite);
        const maximumValues = [existing.value_max, incoming.value_max].filter(Number.isFinite);
        if (minimumValues.length > 0) {
            merged.value_min = Math.max(...minimumValues);
        }
        if (maximumValues.length > 0) {
            merged.value_max = Math.min(...maximumValues);
        }

        if (Number.isFinite(merged.value_min)
            && Number.isFinite(merged.value_max)
            && merged.value_max <= merged.value_min
        ) {
            // Ohne gemeinsamen Wertebereich darf die Gruppe keinen Slider-Befehl anbieten.
            merged.access = Number.isFinite(merged.access) ? merged.access & ~2 : 1;
            delete merged.presets;
        }

        return merged;
    }

    #createEndpointPayload(device) {
        const endpoints = device.endpoints ?? device.zh?.endpoints ?? {};
        const entries = this.#endpointEntries(endpoints);

        const result = {};
        for (const [key, endpoint] of entries) {
            const id = this.#endpointId(endpoint, key);
            const zhEndpoint = this.#matchingEndpoint(device.zh?.endpoints, id);
            result[id] = {
                id,
                name: endpoint.name ?? endpoint.endpoint_name ?? zhEndpoint?.name ?? zhEndpoint?.endpoint_name ?? '',
                bindings: this.#endpointBindings(endpoint, zhEndpoint),
                configured_reportings: this.#endpointConfiguredReportings(endpoint, zhEndpoint),
                clusters: this.#endpointClusters(endpoint, zhEndpoint),
            };
        }

        return result;
    }

    #endpointEntries(endpoints) {
        if (!endpoints) {
            return [];
        }
        if (Array.isArray(endpoints)) {
            return endpoints.map(endpoint => [this.#endpointId(endpoint), endpoint]);
        }
        if (typeof endpoints.entries === 'function') {
            return Array.from(endpoints.entries());
        }
        if (typeof endpoints[Symbol.iterator] === 'function') {
            return Array.from(endpoints).map((endpoint, index) => {
                if (Array.isArray(endpoint) && endpoint.length >= 2) {
                    return endpoint;
                }
                return [this.#endpointId(endpoint, String(index)), endpoint];
            });
        }
        return Object.entries(endpoints);
    }

    #endpointId(endpoint, fallback = '') {
        return String(endpoint.ID ?? endpoint.id ?? endpoint.endpointID ?? endpoint.endpoint_id ?? fallback);
    }

    #matchingEndpoint(endpoints, id) {
        for (const [key, endpoint] of this.#endpointEntries(endpoints)) {
            if (this.#endpointId(endpoint, key) === id) {
                return endpoint;
            }
        }

        return undefined;
    }

    #endpointClusters(...endpoints) {
        const endpoint = endpoints.find(candidate => candidate?.clusters || candidate?.inputClusters || candidate?.outputClusters)
            ?? endpoints.find(candidate => candidate)
            ?? {};
        const clusters = endpoint.clusters ?? {};
        return {
            input: this.#clusterNames(clusters.input ?? endpoint.inputClusters ?? []),
            output: this.#clusterNames(clusters.output ?? endpoint.outputClusters ?? []),
            scenes: this.#plainCollection(clusters.scenes ?? endpoint.scenes ?? []),
        };
    }

    #endpointBindings(...endpoints) {
        const candidates = [];
        for (const endpoint of endpoints) {
            if (!endpoint) {
                continue;
            }
            candidates.push(
                endpoint.bindings,
                endpoint.binds,
                endpoint.bind,
                endpoint.zh?.bindings,
                endpoint.zh?.binds,
                endpoint.zh?.bind,
            );
        }

        return this.#firstPlainCollection(candidates);
    }

    #endpointConfiguredReportings(...endpoints) {
        const candidates = [];
        for (const endpoint of endpoints) {
            if (!endpoint) {
                continue;
            }
            candidates.push(
                endpoint.configured_reportings,
                endpoint.configuredReportings,
                endpoint.configuredReporting,
                endpoint.reportings,
                endpoint.reports,
                endpoint.zh?.configured_reportings,
                endpoint.zh?.configuredReportings,
                endpoint.zh?.configuredReporting,
                endpoint.zh?.reportings,
                endpoint.zh?.reports,
            );
        }

        return this.#firstPlainCollection(candidates);
    }

    #clusterNames(clusters) {
        return this.#plainCollection(clusters).map(cluster => {
            if (typeof cluster === 'string') {
                return cluster;
            }
            return String(cluster.name ?? cluster.ID ?? cluster.id ?? cluster.clusterID ?? cluster);
        });
    }

    #firstPlainCollection(candidates) {
        for (const candidate of candidates) {
            const values = this.#plainCollection(candidate);
            if (values.length > 0) {
                return values;
            }
        }

        return [];
    }

    #plainCollection(value) {
        if (Array.isArray(value)) {
            return value.map(entry => this.#plainValue(entry, new WeakSet(), 0));
        }
        if (!value) {
            return [];
        }
        if (typeof value === 'string') {
            return [value];
        }
        if (typeof value.values === 'function') {
            return Array.from(value.values()).map(entry => this.#plainValue(entry, new WeakSet(), 0));
        }
        if (typeof value[Symbol.iterator] === 'function') {
            return Array.from(value).map(entry => this.#plainValue(entry, new WeakSet(), 0));
        }
        if (typeof value === 'object') {
            return Object.values(value).map(entry => this.#plainValue(entry, new WeakSet(), 0));
        }
        return [];
    }

    #plainArray(value) {
        if (!Array.isArray(value)) {
            return [];
        }

        return value.map(entry => this.#plainValue(entry, new WeakSet(), 0));
    }

    #plainValue(value, seen, depth) {
        if (value === null || typeof value !== 'object') {
            return value;
        }
        if (seen.has(value)) {
            return undefined;
        }
        if (depth > 3) {
            return String(value);
        }

        seen.add(value);
        if (Array.isArray(value)) {
            return value.map(entry => this.#plainValue(entry, seen, depth + 1));
        }

        const result = {};
        for (const [key, entry] of Object.entries(value)) {
            if (typeof entry === 'function') {
                continue;
            }
            const normalized = this.#plainValue(entry, seen, depth + 1);
            if (typeof normalized !== 'undefined') {
                result[key] = normalized;
            }
        }

        return result;
    }

    #deviceNotCoordinator(device) {
        return device.type !== 'Coordinator';
    }
}

module.exports = IPSymconExtension;
