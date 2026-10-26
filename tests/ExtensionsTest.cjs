const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

function createHarness(filename, baseTopic) {
    const Extension = require(path.join(__dirname, '..', 'libs', filename));
    const published = [], errors = [], resolved = [];
    const mqtt = {
        subscribe() { throw new Error('No additional MQTT subscriptions allowed'); },
        async publish(topic, payload) { published.push({topic, payload: JSON.parse(payload)}); }
    };
    const group = {friendly_name: 'Floor/Lights', ID: 3, devices: [], options: {friendly_name: 'Floor/Lights'}, zh: {groupID: 3, members: []}};
    const zigbee = {devicesIterator() { return []; }, groupsIterator() { return []; }, resolveEntity(name) { resolved.push(name); return name === 'Floor/Lights' ? group : undefined; }};
    const settings = {get() { return {mqtt: {base_topic: baseTopic}}; }, getGroups() { return [group]; }};
    const logger = {info() {}, error(message) { errors.push(message); }};
    const events = {on() {}, onMQTTMessage() {}};
    const extension = filename === 'IPSymconExtension.js'
        ? new Extension(zigbee, mqtt, {}, () => {}, events, settings, logger)
        : new Extension(zigbee, mqtt, {}, () => {}, events, null, null, null, settings, logger);
    return {extension, published, errors, resolved};
}

for (const filename of ['IPSymconExtension.js', 'IPSymconExtension2.js']) {
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
        assert.match(fs.readFileSync(path.join(__dirname, '..', 'libs', filename), 'utf8'), /Version: 6\.07\r?\n/);
    });
}

test('legacy extension retains plain-text device requests under bridge/symcon', async () => {
    const h = createHarness('IPSymconExtension.js', 'house/z2m');
    await h.extension.onMQTTMessage({topic: 'house/z2m/bridge/symcon/getDevice', message: 'Floor/Lamp'});
    assert.deepEqual(h.resolved, ['Floor/Lamp']);
    assert.deepEqual(h.errors, []);
});
