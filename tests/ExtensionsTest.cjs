const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

for (const filename of ['IPSymconExtension.js', 'IPSymconExtension2.js']) {
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
        assert.match(fs.readFileSync(path.join(__dirname, '..', 'libs', filename), 'utf8'), /Version: 6\.06\r?\n/);
    });
}
