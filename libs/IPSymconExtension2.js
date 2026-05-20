/*
 IPSymconExtension
 Version: 5.42
*/

class MyLogger {
    constructor(logger) {
        // Das von Zigbee2MQTT übergebene logger-Objekt (ggf. leer)
        this.logger = logger || {};
    }

    info(...args) {
        if (typeof this.logger.info === 'function') {
            // Logger von Z2M nutzen
            this.logger.info(...args);
        } else {
            // Fallback: auf console.log
            console.log('[MyExtension][info]', ...args);
        }
    }

    error(...args) {
        if (typeof this.logger.error === 'function') {
            this.logger.error(...args);
        } else {
            console.error('[MyExtension][error]', ...args);
        }
    }

    debug(...args) {
        if (typeof this.logger.debug === 'function') {
            this.logger.debug(...args);
        } else {
            console.log('[MyExtension][debug]', ...args);
        }
    }
}

class IPSymconExtension {
    constructor(zigbee, mqtt, state, publishEntityState, eventBus, enableDisableExtension, restartCallback, addExtension, settings, baseLogger) {
        this.zigbee = zigbee;
        this.mqtt = mqtt;
        this.state = state;
        this.publishEntityState = publishEntityState;
        this.eventBus = eventBus;
        this.settings = settings;
        this.logger = new MyLogger(baseLogger);
        this.baseTopic = this.settings.get().mqtt.base_topic;
        this.symconExtensionTopic = 'SymconExtension';
        this.eventBus.onMQTTMessage(this, this.onMQTTMessage.bind(this));
        this.logger.info('Loaded IP-Symcon Extension');
    }

    async start() {
        this.mqtt.subscribe(`${this.baseTopic}/${this.symconExtensionTopic}/#`);

    }

    async onMQTTMessage(data) {
        if (!data.topic.startsWith(`${this.baseTopic}/${this.symconExtensionTopic}`)) {
            return;
        }
        let message = {};
        const transaction = JSON.parse(data.message).transaction;
        const topic = (data.topic.slice(this.baseTopic.length + 1)).replace('request', 'response');
        try {
            if (data.topic.startsWith(`${this.baseTopic}/${this.symconExtensionTopic}/request/getDeviceInfo/`)) {
                this.logger.info('Symcon: request/getDeviceInfo');
                const devicename = data.topic.split('/').slice(4).join('/');
                const device = this.zigbee.resolveEntity(devicename);
                if (typeof device !== "undefined") {
                    message = this.#createDevicePayload(device, true);
                }
            }
            if (data.topic.startsWith(`${this.baseTopic}/${this.symconExtensionTopic}/request/getGroupInfo`)) {
                this.logger.info('Symcon: request/getGroupInfo');
                const groupname = data.topic.split('/').slice(4).join('/');
                message = this.#createGroupExposes(groupname);
            }
            if (data.topic == `${this.baseTopic}/${this.symconExtensionTopic}/lists/request/getGroups`) {
                this.logger.info('Symcon: lists/request/getGroups');
                message.list = [];
                for (const group of this.zigbee.groupsIterator()) {
                    const listEntry = {
                        devices: [],
                        members: this.#createGroupMemberPayload(group),
                        scenes: this.#createGroupScenePayload(group),
                        options: group.options ?? {},
                        friendly_name: group.options?.friendly_name,
                        ID: group.zh?.groupID ?? group.ID ?? group.id ?? null
                    }
                    for (const member of listEntry.members) {
                        listEntry.devices.push(member.ieee_address || member.device);
                    }
                    if (typeof listEntry.friendly_name !== "undefined") {
                        message.list.push(listEntry);
                    }
                }
            }
            if (data.topic == `${this.baseTopic}/${this.symconExtensionTopic}/lists/request/getDevices`) {
                this.logger.info('Symcon: lists/request/getDevices');
                message.list = [];
                for (const device of this.zigbee.devicesIterator()) {
                    message.list = message.list.concat(this.#createDevicePayload(device, false));
                }
            }
            message.transaction = transaction;
            await this.mqtt.publish(topic, JSON.stringify(message));
            return;
        } catch (error) {
            message.transaction = transaction;
            await this.mqtt.publish(topic, JSON.stringify(message));
            let errormessage = 'Unknown Error'
            if (error instanceof Error) errormessage = error.message
            this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
        }

    }

    async stop() {
        this.eventBus.removeListeners(this);
    }

    #createDevicePayload(device, boolExposes) {
        const definition = device.definition ?? device._definition ?? {};
        const options = device.options ?? {};
        let exposes;
        if (boolExposes) {
            exposes = device.exposes();
        }
        return {
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
            endpoints: this.#createEndpointPayload(device),
            exposes: exposes,
            options: options,
            definition_options: definition.options ?? [],
            filtered_attributes: options.filtered_attributes ?? [],
        };
    }

    #createGroupExposes(groupname) {
        const groupSupportedTypes = ['light', 'switch', 'lock', 'cover'];
        const groupExposes = {
            foundGroup: false,
            options: {},
            members: [],
            scenes: [],
            ID: null,
            friendly_name: groupname
        };
        groupSupportedTypes.forEach(type => groupExposes[type] = {
            type,
            features: []
        });

        const group = this.zigbee.resolveEntity(groupname);
        if (typeof group !== "undefined") {
            groupExposes.foundGroup = true;
            groupExposes.options = group.options ?? {};
            groupExposes.members = this.#createGroupMemberPayload(group);
            groupExposes.scenes = this.#createGroupScenePayload(group);
            groupExposes.ID = group.zh?.groupID ?? group.ID ?? group.id ?? null;
            groupExposes.friendly_name = group.options?.friendly_name ?? groupname;
            this.#processGroupDevices(group, groupExposes);
        }
        return groupExposes;
    }

    #processGroupDevices(group, groupExposes) {
        this.#createGroupMemberPayload(group).forEach(member => {
            this.logger.info(`Symcon processGroupDevices: ${JSON.stringify(member.ieee_address || member.device)}`);
            const device = this.zigbee.resolveEntity(member.ieee_address || member.device);
            if (typeof device !== "undefined") {
                this.#addDeviceExposesToGroup(device, groupExposes);
            }
        });
    }

    #createGroupMemberPayload(group) {
        const members = group.zh?.members ?? group.members ?? [];
        if (!Array.isArray(members)) {
            return [];
        }

        return members.map(member => {
            const ieeeAddress = member.deviceIeeeAddress ?? member.device?.ieeeAddr ?? member.ieeeAddr ?? member.ieee_address ?? '';
            const device = this.zigbee.resolveEntity(ieeeAddress || member.device);
            return {
                device: device?.name ?? String(ieeeAddress || member.device ?? ''),
                ieee_address: String(ieeeAddress || ''),
                endpoint: this.#groupMemberEndpoint(member)
            };
        });
    }

    #groupMemberEndpoint(member) {
        return String(member.endpoint?.ID ?? member.endpoint?.id ?? member.ID ?? member.id ?? member.endpointID ?? member.endpoint_id ?? '');
    }

    #createGroupScenePayload(group) {
        return this.#plainArray(group.zh?.meta?.scenes ?? group.zh?.scenes ?? group.scenes ?? group.meta?.scenes ?? []);
    }

    #addDeviceExposesToGroup(device, groupExposes) {
        let exposes = [];
        this.logger.info(`Symcon addDeviceExposesToGroup: ${JSON.stringify(device)}`);
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
            if (!groupExposeType.features.some(f => f.property === feature.property)) {
                groupExposeType.features.push(feature);
            }
        });
    }

    #createEndpointPayload(device) {
        const endpoints = device.endpoints ?? device.zh?.endpoints ?? {};
        const entries = Array.isArray(endpoints)
            ? endpoints.map(endpoint => [this.#endpointId(endpoint), endpoint])
            : Object.entries(endpoints);

        const result = {};
        for (const [key, endpoint] of entries) {
            const id = this.#endpointId(endpoint, key);
            result[id] = {
                id,
                name: endpoint.name ?? endpoint.endpoint_name ?? '',
                bindings: this.#plainArray(endpoint.bindings),
                configured_reportings: this.#plainArray(endpoint.configured_reportings),
                clusters: this.#endpointClusters(endpoint),
            };
        }

        return result;
    }

    #endpointId(endpoint, fallback = '') {
        return String(endpoint.ID ?? endpoint.id ?? endpoint.endpointID ?? endpoint.endpoint_id ?? fallback);
    }

    #endpointClusters(endpoint) {
        const clusters = endpoint.clusters ?? {};
        return {
            input: this.#clusterNames(clusters.input ?? endpoint.inputClusters ?? []),
            output: this.#clusterNames(clusters.output ?? endpoint.outputClusters ?? []),
            scenes: this.#plainArray(clusters.scenes ?? endpoint.scenes ?? []),
        };
    }

    #clusterNames(clusters) {
        if (!Array.isArray(clusters)) {
            return [];
        }

        return clusters.map(cluster => {
            if (typeof cluster === 'string') {
                return cluster;
            }
            return String(cluster.name ?? cluster.ID ?? cluster.id ?? cluster.clusterID ?? cluster);
        });
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
}

module.exports = IPSymconExtension;
