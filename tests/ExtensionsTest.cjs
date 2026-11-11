const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

function createHarness(filename, baseTopic, devices = []) {
    const Extension = require(path.join(__dirname, '..', 'libs', filename));
    const published = [], errors = [], resolved = [];
    const mqtt = {
        subscribe() { throw new Error('No additional MQTT subscriptions allowed'); },
        async publish(topic, payload) { published.push({topic, payload: JSON.parse(payload)}); }
    };
    const group = {friendly_name: 'Floor/Lights', ID: 3, devices: [], options: {friendly_name: 'Floor/Lights'}, zh: {groupID: 3, members: []}};
    group.devices = devices.map(device => `${device.ieeeAddr}/1`);
    group.zh.members = devices.map(device => ({deviceIeeeAddress: device.ieeeAddr, ID: 1}));
    const zigbee = {
        devicesIterator() { return devices; },
        groupsIterator() { return []; },
        resolveEntity(name) {
            resolved.push(name);
            return name === 'Floor/Lights' ? group : devices.find(device => device.name === name || device.ieeeAddr === name);
        }
    };
    const settings = {get() { return {mqtt: {base_topic: baseTopic}}; }, getGroups() { return [group]; }};
    const logger = {info() {}, error(message) { errors.push(message); }};
    const events = {on() {}, onMQTTMessage() {}};
    const extension = filename === 'IPSymconExtension.js'
        ? new Extension(zigbee, mqtt, {}, () => {}, events, settings, logger)
        : new Extension(zigbee, mqtt, {}, () => {}, events, null, null, null, settings, logger);
    return {extension, published, errors, resolved};
}

for (const filename of ['IPSymconExtension.js', 'IPSymconExtension2.js']) {
    for (const type of ['light', 'switch', 'lock', 'cover']) {
        test(`${filename}: group ${type} exposes use the device API and current options`, async () => {
            const device = {ieeeAddr: '0x00124b0000000001', name: 'Floor/Device', zh: {}, options: {tilt: true}};
            const expected = {type: 'binary', property: 'state', name: 'state', access: 7, value_on: 'ON', value_off: 'OFF'};
            let calls = 0;
            device.definition = {
                exposes(zh, options) {
                    assert.equal(zh, device.zh);
                    assert.equal(options, device.options);
                    return [{type, features: options.tilt ? [expected] : []}];
                }
            };
            device._definition = {exposes: [{type, features: [{...expected, property: 'stale_state'}]}]};
            device.exposes = function () {
                calls++;
                return this.definition.exposes(this.zh, this.options);
            };
            const h = createHarness(filename, 'house/z2m', [device]);
            await h.extension.onMQTTMessage({topic: 'house/z2m/SymconExtension/request/getGroupInfo/Floor/Lights', message: '{"transaction":41}'});
            assert.equal(calls, 1);
            assert.deepEqual(h.published[0].payload[type].features, [expected]);
            assert.equal(h.published[0].payload.transaction, 41);
            assert.equal(h.published[0].payload.foundGroup, true);
            device.options.tilt = false;
            await h.extension.onMQTTMessage({topic: 'house/z2m/SymconExtension/request/getGroupInfo/Floor/Lights', message: '{"transaction":42}'});
            assert.equal(calls, 2);
            assert.deepEqual(h.published[1].payload[type].features, []);
            assert.equal(h.published[1].payload.transaction, 42);
            assert.deepEqual(h.errors, []);
        });
    }

    test(`${filename}: group exposes retain static legacy definitions without the device API`, async () => {
        const state = {type: 'binary', property: 'state', access: 7};
        const position = {type: 'numeric', property: 'position', access: 7, value_min: 0, value_max: 100};
        const device = {
            ieeeAddr: '0x00124b0000000001', name: 'Floor/Device',
            definition: {exposes: [{type: 'switch', features: [state]}]},
            _definition: {exposes: [{type: 'cover', features: [position]}]}
        };
        const h = createHarness(filename, 'house/z2m', [device]);
        await h.extension.onMQTTMessage({topic: 'house/z2m/SymconExtension/request/getGroupInfo/Floor/Lights', message: '{"transaction":42}'});
        assert.deepEqual(h.published[0].payload.switch.features, [state]);
        assert.deepEqual(h.published[0].payload.cover.features, [position]);
        assert.deepEqual(h.errors, []);
    });

    test(`${filename}: dynamic legacy definitions receive the device and options`, async () => {
        const expected = {type: 'numeric', property: 'tilt', access: 7, value_min: 0, value_max: 100};
        const device = {ieeeAddr: '0x00124b0000000001', name: 'Floor/Device', zh: {manufacturerName: 'test'}, options: {tilt: true}};
        device._definition = {
            exposes(zh, options) {
                assert.equal(zh, device.zh);
                assert.equal(options, device.options);
                return [{type: 'cover', features: [expected]}];
            }
        };
        const h = createHarness(filename, 'house/z2m', [device]);
        await h.extension.onMQTTMessage({topic: 'house/z2m/SymconExtension/request/getGroupInfo/Floor/Lights', message: '{"transaction":43}'});
        assert.deepEqual(h.published[0].payload.cover.features, [expected]);
        assert.deepEqual(h.errors, []);
    });

    test(`${filename}: dynamic group features retain common ranges and write access`, async () => {
        const feature = (access, min, max) => ({type: 'numeric', property: 'color_temp', access, value_min: min, value_max: max});
        const first = feature(7, 150, 500);
        const second = feature(5, 200, 450);
        const devices = [first, second].map((entry, index) => ({
            ieeeAddr: `0x00124b000000000${index + 1}`, name: `Floor/Device${index + 1}`,
            exposes() { return [{type: 'light', features: [entry]}]; }
        }));
        const h = createHarness(filename, 'house/z2m', devices);
        await h.extension.onMQTTMessage({topic: 'house/z2m/SymconExtension/request/getGroupInfo/Floor/Lights', message: '{"transaction":44}'});
        assert.deepEqual(h.published[0].payload.light.features, [feature(5, 200, 450)]);
        assert.deepEqual(first, feature(7, 150, 500));
        assert.deepEqual(second, feature(5, 200, 450));
        assert.deepEqual(h.errors, []);
    });

    test(`${filename}: device details and lists report modern and legacy OTA capabilities`, async () => {
        const cases = [
            [{ota: true}, true],
            [{ota: {}}, true],
            [{ota: {url: 'https://example.test/firmware.json'}}, true],
            [{ota: false}, false],
            [{ota: true, supports_ota: false}, true],
            [{ota: false, supports_ota: true}, false],
            [{ota: null, supports_ota: true}, false],
            [{supports_ota: true}, true],
            [{supports_ota: false}, false],
            [{}, false]
        ];
        for (const legacyDefinition of [false, true]) {
            const devices = cases.map(([definition], index) => ({
                ieeeAddr: `0x00124b00000000${index.toString(16).padStart(2, '0')}`, name: `Floor/Device${index}`,
                zh: {type: 'Router', endpoints: []}, exposes() { return []; },
                [legacyDefinition ? '_definition' : 'definition']: definition
            }));
            const h = createHarness(filename, 'house/z2m', devices);
            for (const device of devices) {
                await h.extension.onMQTTMessage({topic: `house/z2m/SymconExtension/request/getDeviceInfo/${device.name}`, message: '{"transaction":45}'});
            }
            await h.extension.onMQTTMessage({topic: 'house/z2m/SymconExtension/lists/request/getDevices', message: '{"transaction":46}'});
            const expected = cases.map(([, supported]) => supported);
            assert.deepEqual(h.published.slice(0, devices.length).map(result => result.payload.supports_ota), expected);
            assert.deepEqual(h.published[devices.length].payload.list.map(device => device.supports_ota), expected);
            assert.equal(h.published[devices.length].payload.transaction, 46);
            assert.deepEqual(h.errors, []);
        }
    });

    test(`${filename}: malformed and non-object JSON are ignored and subsequent requests work`, async () => {
        const h = createHarness(filename, 'house/z2m');
        const topic = 'house/z2m/SymconExtension/lists/request/getDevicesLight';
        for (const suffix of ['lists/request/getDevicesLight', 'lists/request/getGroups', 'request/getDeviceInfo/Floor/Lamp', 'request/getGroupInfo/Floor/Lights']) {
            for (const message of ['{broken', 'null', '[]', '42', 'false', '"text"', null]) {
                await h.extension.onMQTTMessage({topic: `house/z2m/SymconExtension/${suffix}`, message});
            }
        }
        assert.equal(h.published.length, 0);
        assert.equal(h.errors.length, 28);
        await h.extension.onMQTTMessage({topic, message: '{"transaction":42}'});
        assert.deepEqual(h.published, [{topic: 'SymconExtension/lists/response/getDevicesLight', payload: {list: [], transaction: 42}}]);
    });

    for (const base of ['zigbee2mqtt', 'house/floor/zigbee2mqtt']) {
        test(`${filename}: device and group names retain their path with base ${base}`, async () => {
            const h = createHarness(filename, base);
            await h.extension.onMQTTMessage({topic: `${base}/SymconExtension/request/getDeviceInfo/Floor/Lamp`, message: '{"transaction":7}'});
            assert.deepEqual(h.resolved, ['Floor/Lamp']);
            assert.equal(h.published[0].topic, 'SymconExtension/response/getDeviceInfo/Floor/Lamp');
            assert.equal(h.published[0].payload.transaction, 7);
            await h.extension.onMQTTMessage({topic: `${base}/SymconExtension/request/getGroupInfo/Floor/Lights`, message: '{"transaction":8}'});
            assert.equal(h.published[1].topic, 'SymconExtension/response/getGroupInfo/Floor/Lights');
            assert.equal(h.published[1].payload.friendly_name, 'Floor/Lights');
            assert.equal(h.published[1].payload.foundGroup, true);
            assert.equal(h.published[1].payload.ID, 3);
            assert.equal(h.published[1].payload.transaction, 8);
            assert.equal(h.errors.length, 0);
        });
    }

    test(`${filename}: response and unrelated topics are ignored`, async () => {
        const h = createHarness(filename, 'house/z2m');
        for (const topic of ['house/z2m-other/SymconExtension/lists/request/getDevicesLight', 'house/z2m/SymconExtension/lists/response/getDevicesLight', 'house/z2m/SymconExtension/request/getGroupInfoOther/Lights']) {
            await h.extension.onMQTTMessage({topic, message: '{broken'});
        }
        assert.deepEqual(h.published, []);
        assert.deepEqual(h.errors, []);
    });
    test(`${filename}: requests work without adding MQTT subscriptions`, async () => {
        const Extension = require(path.join(__dirname, '..', 'libs', filename));
        let handler;
        const published = [];
        const mqtt = {
            subscribe() { throw new Error('Extension must use the existing Z2M base-topic subscription'); },
            async publish(topic, payload) { published.push({topic, payload: JSON.parse(payload)}); }
        };
        const zigbee = {devicesIterator() { return []; }};
        const settings = {get() { return {mqtt: {base_topic: 'test-z2m'}}; }};
        const logger = {info() {}, error(message) { throw new Error(message); }};
        const eventBus = {
            on(event, callback) { handler = callback; },
            onMQTTMessage(owner, callback) { handler = callback; }
        };
        const extension = filename === 'IPSymconExtension.js'
            ? new Extension(zigbee, mqtt, {}, () => {}, eventBus, settings, logger)
            : new Extension(zigbee, mqtt, {}, () => {}, eventBus, null, null, null, settings, logger);
        await extension.start();
        await handler({topic: 'test-z2m/SymconExtension/lists/request/getDevicesLight', message: '{"transaction":42}'});
        assert.deepEqual(published, [{
            topic: 'SymconExtension/lists/response/getDevicesLight',
            payload: {list: [], transaction: 42}
        }]);
        assert.match(fs.readFileSync(path.join(__dirname, '..', 'libs', filename), 'utf8'), /Version: 6\.08\r?\n/);
    });
}

test('legacy extension retains plain-text device requests under bridge/symcon', async () => {
    const h = createHarness('IPSymconExtension.js', 'house/z2m');
    await h.extension.onMQTTMessage({topic: 'house/z2m/bridge/symcon/getDevice', message: 'Floor/Lamp'});
    assert.deepEqual(h.resolved, ['Floor/Lamp']);
    assert.deepEqual(h.errors, []);
});
