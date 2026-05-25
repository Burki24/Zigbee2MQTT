/*
 IPSymconExtension
 Version: 6.02
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
            // Fallback: auf die Zigbee2MQTT-Konsole
            console.log('[IPSymconExtension][info]', ...args);
        }
    }

    error(...args) {
        if (typeof this.logger.error === 'function') {
            this.logger.error(...args);
        } else {
            console.error('[IPSymconExtension][error]', ...args);
        }
    }

    debug(...args) {
        if (typeof this.logger.debug === 'function') {
            this.logger.debug(...args);
        } else {
            console.log('[IPSymconExtension][debug]', ...args);
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
            const fallbackDevice = typeof member.device === 'string' ? member.device : member.device?.name ?? '';
            const device = this.zigbee.resolveEntity(ieeeAddress || fallbackDevice);
            return {
                device: device?.name ?? String(ieeeAddress || fallbackDevice),
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
}

module.exports = IPSymconExtension;
