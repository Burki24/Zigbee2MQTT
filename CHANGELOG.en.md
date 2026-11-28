# Unreleased

[Deutsch](CHANGELOG.md) | [English](CHANGELOG.en.md)

This changelog covers version **6.1** for Symcon 9.1. The published **5.43** beta (`ce8aba0de27c8e9a6ffbaa91759009fb34d11806`) is the baseline for changes shared across the 6.x series.

> [!CAUTION]
> **Upgrading from 5.x creates a new string Identify variable.** The new variable expects `identify` instead of `true`. Review existing scripts, links and automations and point them to the new variable ID where necessary. The old Boolean variable retains its ID, value, existing visibility and user customizations; only its obsolete module default action is disabled. Custom actions are retained. Old module commands are not forwarded automatically.

Variables hidden or custom actions removed by earlier testing versions are not restored automatically: the previous settings were not recorded and must be checked against a backup where necessary.

## 6.1 compared with dev_V6

- `Symcon-9.1` requires Symcon 9.1. The `dev_V6` branch remains available for Symcon 9.0. Upgrade Symcon first, then switch the existing module library to the new branch.
- Row selection fills in the device and endpoint for group members and the fields for group scenes, group options, device options, bindings, reports, and network security. Changes have separate actions.
- The binding and reporting wizards send their requests only after final confirmation. Advanced reporting JSON parameters are included only when the advanced options are visible.
- The network map uses native fullscreen and lists connections in both directions for the selected device. It can open the corresponding device instance and query the routing table of a supported router on demand. Selecting a device reads cached data without starting a scan.
- The device form and network map show the last observed path to the coordinator with a timestamp after the extension captures a path. Z-Stack requires extension 6.10 or later, and Ember requires 6.11 or later. The library bundles 6.11 for Zigbee2MQTT 2.x and 6.09 for 1.x.
- Starting and scheduling OTA updates use a shared confirmation dialog with the device, action, and both firmware versions. The request is sent after confirmation.
- The bridge stores Zigbee2MQTT backups outside the WebServer directory. Applying the bridge configuration moves existing module backups and preserves files with matching names.

## New

- Requires Symcon 9.1 or newer; all six modules use `IPSModuleStrict`. Create a complete backup before upgrading from 5.x or an earlier testing version. Downgrading requires restoring that backup.
- Native Symcon presentations for switches, enumerations, measurements, brightness, color, shutters, duration and date/time replace automatically generated module default profiles. Custom profiles and presentations continue to take precedence. Old `Z2M.*` profiles are not deleted globally; review scripts that depend on profile names.
- Custom, Symcon-theme-aware tiles for heating, switches with optional measurements and multiple channels, sensors, security devices, window handles and actions. Archive charts are offered only for logged variables. Lights and shutters use native Symcon presentations; separate RGB/tunable-white HTML tiles are not included in the current distribution.
- Heating presets, fallback temperature ranges and Kelvin limits can be configured per Device instance. Derived Kelvin and color values support suitable native light presentations and presets.
- A local variable catalog supports individual and multiple selection, explicit creation, disabling, re-enabling manually deleted variables and showing presentation changes. A manual rebuild considers current exposes and known payloads.
- Instance-local variable maintenance detects stale variables. Archived or referenced variables are protected; each deletion is checked again and confirmed individually. The Bridge provides an overview separated by MQTT connection and base topic but does not delete variables centrally.
- Device options have typed editors for Boolean, enum, number, text, JSON and attribute lists. Known values and device-specific option definitions are displayed together.
- Binding and reporting provide endpoint, cluster, attribute and target selection, display the existing configuration and allow explicit refresh of endpoint information from the matching Bridge cache.
- Device maintenance supports a new interview, reconfiguration and confirmed normal, forced or blocklisting removal from Zigbee2MQTT. This does not automatically delete the Symcon instance.
- Group management covers members including endpoints, group options and Zigbee scenes. Selecting a row in either member or device list fills in the device and endpoint. Group selections remain restricted to the matching MQTT connection.
- A new Network Map module provides asynchronous scans, topology, link quality, routing, search, filters, adjustable views, Symcon's native expanded view and export. A full network analysis requires confirmation and may temporarily load the Zigbee network.
- Central OTA management for explicitly supported devices: checks, updates, downgrades, custom firmware URLs, scheduling, unscheduling, cancellation, progress, remaining time and result overviews. The UI prevents multiple updates from being started simultaneously.
- Extended Bridge diagnostics include health checks, coordinator checks, events, warnings, errors and unsupported or incompletely interviewed devices.
- Extended pairing controls offer target selection and a time limit, together with blocklist and passlist management. Changes to the restrictive passlist require security confirmation.
- Zigbee2MQTT backups are saved as ZIP files on the Symcon server. Large responses are bounded and processed incrementally; a direct Base64 export through the public PHP interface is not offered.
- An install-code catalog provides masked display, resending and confirmed deletion, alongside Touchlink scanning, identification and confirmed factory reset. Stored install codes are not encrypted and may be included in Symcon backups.
- Confirmed Bridge expert actions support selected administrative commands, with clear feedback and deliberately chosen JSON parameters.
- Additional flow-plan actions support toggling, transitions, Kelvin color temperature and scene recall; transition actions are restricted to suitable Device and Group instances.
- Complete English library and module guides, synchronized beta/migration notes and a consolidated changelog. Earlier chronological development notes remain available as explicitly labeled history.
- Development tools provide isolated MQTT device simulation and missing-translation searches, together with substantially expanded regression checks and internal helper diagnostics.

## Fixes

- Late MQTT responses carrying a transaction ID can no longer complete a new request through a reused ID after a timeout. ID allocation remains monotonic across configuration changes and restarts.

- The new identify migration was also verified on a real Symcon 9.1 test system with visible and hidden legacy variables, custom profiles, presentations, working custom actions and repeated execution.
- Color and brightness commands are independent: color commands no longer implicitly include the current brightness. Black and missing or unusable brightness values are handled correctly; HSV/RGB conversions have been hardened.
- Native numeric parameters are registered with the correct Integer/Float type and satisfy stricter validation in Symcon 9.1. Units, millivolt-to-volt conversion and subsequently received temperature/dew-point values are handled consistently.
- Read-only exposes receive no action. Writable text values use compatible value input; status text receives no presentation that requires an action. Presets retain fractional values and special enum values, avoid duplicates and can be restored explicitly.
- Switch values are not marked successful prematurely; repeated incoming values update the write timestamp again. Partial MQTT payloads are merged, nested objects are retained and lists are replaced as a whole. GET requests include only readable, unfiltered properties.
- Large exposes, device, discovery and transaction data use bounded or compressed buffers. Forms use caches, Discovery can refresh stale data asynchronously, and catalog changes are batched rather than saved repeatedly.
- Configurators and group lists distinguish MQTT connections even when their base topics are identical. Already assigned instances remain visible when the physical device is missing; creating or moving configurators and changing properties require the relevant user selection.
- Device-image downloads verify TLS, size and PNG format. Direct Discovery connections using MQTT over TLS verify the certificate and hostname unless explicitly disabled; there is no automatic fallback to an unencrypted connection. Credentials, tokens and install codes are masked in the designated diagnostic outputs.
- Exposes migrations retain recovery data, user customizations and controlled error states. Temporarily unavailable instance interfaces during module updates no longer cause the known form/timer warnings. OTA status messages no longer cause a recursive MessageSink storm.
- Additional German device, energy, protection, weather and mode translations have been added. Missing enum/preset translations are detected again; diagnostic reports include the necessary information without exposing the secrets intended to be protected.
- The extension installation path has been corrected; read failures abort installation. Additional MQTT subscriptions by the extension have been removed. Installation and updates occur only following a user action.

- Since extension 6.09, missing device definitions and errors while resolving an individual member's group exposes no longer abort the group query. Group metadata and available features of the remaining members are still returned; affected devices are logged and tried again on the next query.

- Both Symcon extensions have been updated to version 6.08. Group queries use the exposes resolved by Zigbee2MQTT for the current device and its options. Dynamic features are no longer missing from groups; older definitions remain supported.
- OTA detection in both extensions considers the current definition field `ota`, including explicitly disabled support. The older `supports_ota` field is used only when `ota` is absent. Extension updates continue to be initiated manually through the Bridge.

- Discovery again offers moving incorrectly connected or disconnected configurators. The existing configurator and target connection are selected explicitly; identical base topics on different MQTT connections are still not merged automatically.

- The switch symbol remains identical in the on and off states. The unsuitable symbol name for the on state has been corrected.

- Identify migration leaves the visibility, names, icons, descriptions, positions, presentations, profiles and custom actions of existing Boolean variables unchanged. The new enum variable is created in addition to the old variable. Repeatedly applying the configuration also preserves subsequent user changes to the old variable. Migration sends no device command and can be resumed after an interruption.

- For lights, only actual state variables from `light` exposes receive the on/off role, including devices with multiple endpoints. Additional options such as Do not disturb, delayed switching and pulse operation remain actionable but are no longer offered as light state variables.

- One-shot commands such as Identify receive a button instead of an on/off presentation. They therefore no longer compete with the light's actual switch state in native tiles.

- The Bridge online indicator also considers the current MQTT connection and updates in open forms when the connection is lost or restored. Reported version information is retained.
- RAW network maps are stored in the Bridge as JSON text. The previous type error when receiving network-analysis results is resolved; Graphviz and PlantUML outputs remain text.

- Device and Group instances again process availability messages from older Zigbee2MQTT versions as plain text (`online`/`offline`). Invalid-payload checks remain active for other messages.
- Manually added MQTT brokers remain visible in Discovery even when other servers use the same base topic. Duplicates are identified using broker identity and base topic.

- Delayed MQTT responses with a different transaction ID no longer complete newer requests. Responses without an ID are accepted only for an unambiguously matching pending request.
- Device and Group instances discard scalar and malformed JSON payloads without a TypeError. Empty availability messages remain supported.
- Network Map accepts only responses with the transaction ID matching the active scan; old and unsolicited responses have no effect.
- Discovery matches existing configurators using both base topic and MQTT connection. Identical base topics on different servers are no longer confused.

- Both Symcon extensions have been updated to version 6.07. Invalid JSON requests are discarded safely; device and group requests work with multi-level MQTT base topics. Extension updates continue to be initiated manually.

- Interrupted migrations restore the previous MQTT processing state. Old exposes files remain available as a recovery source; invalid legacy data cause an explicit migration failure.
- MQTT requests wait within their timeout when the transaction lock is temporarily occupied. Completed responses are retrieved atomically; expired transactions are cleared during the next successful access.

- Group management uses the matching Bridge's existing cache for devices and endpoints. This removes repeated full-device queries and their timeouts when opening and using the form.

- A temporarily occupied MQTT transaction lock no longer causes a fatal failure of a request or form action. Existing transaction data remain protected.
- When extension installation cannot be confirmed, the Bridge error message includes guidance for checking the connection and version information.

## Known limitations

- Classic WebFront may display new native presentations correctly only after the browser page is reloaded. On Symcon 9.1, the behavior was reproduced independently of the module; a user report exists for Symcon 9.0. Tile visualization is unaffected.
- There is no automatic downgrade path to 5.x. In particular, review scripts that depend on profile names and references to the previous identify variable after upgrading.

Earlier development states and older 5.x entries: [Historical change notes](docs/CHANGELOG_HISTORY.md) (German). The technical comparison against the last beta is recorded in the [documentation and migration verification](docs/VERIFICATION.md).
