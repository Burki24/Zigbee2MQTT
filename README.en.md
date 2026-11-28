[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FBurki24%2FZigbee2MQTT%2Frefs%2Fheads%2FSymcon-9.1%2Flibrary.json&query=%24.version&label=Module%20Version&color=blue)](https://community.symcon.de/t/modul-zigbee2mqtt-v6-0-testing/144007)
[![Symcon Version](https://img.shields.io/badge/Symcon-9.1%2B-green)](https://www.symcon.de/en/service/documentation/)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Burki24/Zigbee2MQTT/actions/workflows/style.yml/badge.svg?branch=Symcon-9.1)](https://github.com/Burki24/Zigbee2MQTT/actions/workflows/style.yml?query=branch%3ASymcon-9.1)
[![Run Tests](https://github.com/Burki24/Zigbee2MQTT/actions/workflows/tests.yml/badge.svg?branch=Symcon-9.1)](https://github.com/Burki24/Zigbee2MQTT/actions/workflows/tests.yml?query=branch%3ASymcon-9.1)

# Zigbee2MQTT

[Deutsch](README.md) | [English](README.en.md)

Connect [Zigbee2MQTT](https://www.zigbee2mqtt.io) to Symcon. The library discovers Zigbee2MQTT installations, creates device/group instances and exposes their capabilities as Symcon variables and actions.

## Contents

- [Requirements](#requirements)
- [Included modules](#included-modules)
- [New installation](#new-installation)
- [Upgrading from 5.x to 6.0](#upgrading-from-5x-to-60)
- [Upgrading from dev_V6 to 6.1](#upgrading-from-dev_v6-to-61)
- [Earlier 4.x installations](#earlier-4x-installations)
- [Extension installation](#extension-installation)
- [Tiles, presentations and profiles](#tiles-presentations-and-profiles)
- [Variables and maintenance](#variables-and-maintenance)
- [Actions](#actions)
- [Local data and cross-instance checks](#local-data-and-cross-instance-checks)
- [Changelog](#changelog)
- [Donations](#donations)
- [Third-party components](#third-party-components)
- [License](#license)

## Requirements

- Symcon **9.1 or newer**.
- A working Zigbee2MQTT installation and a Zigbee coordinator compatible with it.
- An MQTT broker, either Symcon's MQTT Server or an external broker such as Mosquitto.
- The matching Symcon extension installed in Zigbee2MQTT. The library bundles **6.11 for Zigbee2MQTT 2.x** and **6.09 for Zigbee2MQTT 1.x**. Confirm installation or update manually in the bridge. Features that use newer extension responses require the current extension. The general minimum remains 6.05.

For Symcon 9.0, use the [dev_V6 branch](https://github.com/Burki24/Zigbee2MQTT/tree/dev_V6).

## Included modules

| Module | Purpose |
| --- | --- |
| [Discovery](Discovery/README.en.md) | Find installations and prepare configurators on the correct MQTT connection. |
| [Configurator](Configurator/README.en.md) | List devices/groups and create their Symcon instances, including topic categories. |
| [Bridge](Bridge/README.en.md) | Installation status, extension management, pairing, diagnostics, OTA and network maintenance. |
| [Device](Device/README.en.md) | Device state, controls, native presentations, supported custom tiles, options, maintenance and binding/reporting. |
| [Group](Group/README.en.md) | Group state/controls, members and endpoints, group options and scenes. |
| [Network Map](NetworkMap/README.en.md) | Topology, links, routing, network analysis and exports. |

## New installation

1. Install and configure Zigbee2MQTT according to its [getting-started guide](https://www.zigbee2mqtt.io/guide/getting-started/). Verify that Zigbee2MQTT and the broker are reachable.
2. Configure an MQTT Server or MQTT Client in Symcon. An additional generic MQTT Configurator is not required for this library.
3. Install the library through the Symcon Module Store and create its Discovery instance when prompted.
4. Use [Discovery](Discovery/README.en.md) to create the configurator belonging to the intended broker and MQTT base topic.
5. Open the configurator, create/open the matching bridge and install the extension after reviewing the request.
6. Refresh the configurator and create the desired device/group instances. Follow the module-specific setup instructions.

![Module Store](imgs/store.png)
![Module installation](imgs/install.png)

Keep separate brokers/connections and base topics separate. Two installations using the same base topic on different brokers are not interchangeable. Moving an existing configurator requires explicit selection; the library does not silently reconnect unrelated instances.

## Upgrading from 5.x to 6.0

> [!CAUTION]
> **IDENTIFY IS CREATED AS A NEW VARIABLE.** Existing scripts, links and automations may need the new variable ID and the string command `identify` instead of Boolean `true`: `RequestAction($newIdentifyVariableID, 'identify')`. Old module commands are not automatically forwarded.

> [!IMPORTANT]
> Create a complete Symcon backup before upgrading from 5.x or earlier testing builds. There is no automatic downgrade migration. Restore that backup if you need to return to 5.x.

The public beta used for the 6.0 changelog comparison is **5.43**; older installation instructions also refer to 5.42. Version 6.0 uses `IPSModuleStrict` and native Symcon presentations and requires Symcon 9.1 on this branch. Existing variables are not deleted automatically, but callers relying on old profile names or identify behavior need review.

### Identify migration

The new identify variable is a string enumeration accepting `identify`. The retained Boolean variable keeps its ID, value, references, visibility, name, icon, description, position, profiles, presentations and custom action. Its ident changes to an internal legacy ident to free `identify` for the new variable. Only the obsolete **module default action** is disabled. Review custom scripts for continued compatibility; retaining a custom action does not rewrite its implementation.

The migration sends no identify command and can resume after an interruption. Repeating it does not hide the retained variable or clear user customizations. Review links and events that still target its old ID. The new variable represents the last command, not a permanent device state.

Earlier testing versions may already have hidden a legacy variable or removed its custom action. Those old settings were not recorded and cannot be reconstructed automatically. Compare with a backup and restore user settings manually if necessary.

### Profile compatibility

New and re-registered variables use native Symcon presentations where available, or no module profile. The library no longer creates or maintains dynamic `Z2M.*` profiles and does not pin existing variables to old module profile assignments. Custom presentations/profiles retain priority and are not overwritten.

Old profile definitions are not globally deleted. Review scripts, external visualizations and tools that inspect `Z2M.*`, `~Color`, `~Intensity.100` or similar profile names. Prefer variable ID/ident, type, value and presentation metadata for new integrations. Do not run a global profile-deletion script as an upgrade step.

Most values retain Zigbee2MQTT's scale; presentations can display brightness as a percentage and timestamps as dates. Derived Kelvin values and native integer colors are stored as converted values, as is voltage when converted from millivolts. Legacy brightness migration may align an existing value with the expose's scale. See the device documentation before writing custom conversions.

### Upgrade procedure

1. Check the Symcon version and create the backup.
2. Confirm that the correct bridge, MQTT connection and Zigbee2MQTT installation are reachable.
3. Review the [beta notes](docs/BETA_RELEASE_NOTES.md#english), identify callers and profile-dependent scripts.
4. Perform the library update in the Module Store. Watch Symcon's message window during the update. Transient unavailable-instance warnings may occur when another module accesses an instance while it reloads; persistent errors require investigation and reopening/applying the affected configuration.
5. Open the bridge, review the extension status and confirm the extension update manually. Remove obsolete duplicate extensions manually.
6. Check representative devices/groups, their actions, temperatures, colors, Kelvin ranges and profile-dependent scripts. If an automatically chosen custom tile is unsuitable, disable that tile in the instance configuration.
7. Review new variables and variable-catalog choices. Deliberately deleted known variables stay suppressed until explicitly re-enabled or created.

Failed legacy expose migration preserves its source files and restores the previous MQTT processing state. Correct the reported problem before retrying; do not replace invalid recovery data with empty JSON.

**Classic WebFront:** Newly created native presentations can temporarily appear as raw values and keyboard fields. Reload the browser page to load the correct formatting and controls. This was reproduced independently of the module on Symcon 9.1 and reported by a user on 9.0. Tile visualization is unaffected.

### Optional features require deliberate use

- Variable maintenance never deletes variables automatically. Review a candidate within its owning instance and confirm each deletion.
- Refresh endpoint data before editing binding/reporting; wake battery devices if necessary.
- Passlist changes are restrictive and can cause devices not included in the list to leave the network.
- Backups are ZIP files on the Symcon server, not automatic browser downloads.
- Install codes are masked in the form but are not encrypted in the bridge attribute or Symcon backups.
- Run only one active OTA update at a time. Battery devices may need waking for checks/planning; a scheduled update is not necessarily an immediately executing update.

## Upgrading from dev_V6 to 6.1

1. Create a complete backup of the Symcon 9.0 installation.
2. Upgrade Symcon to 9.1 and verify that the existing instances load.
3. Change the branch of the existing module library from `dev_V6` to `Symcon-9.1` and update the library.
4. Check the extension version in the bridge. If necessary, manually confirm the update to 6.11 for Zigbee2MQTT 2.x or 6.09 for 1.x. Then check the bridge, existing devices, and groups.

See the [6.1 release notes](docs/BETA_RELEASE_NOTES.md) for the changes. To return to Symcon 9.0, restore the backup taken before the upgrade.

## Earlier 4.x installations

The old 4.5-to-5.x migration is a separate historical step. Follow the [German legacy instructions](README.md#32-update-von-modul-version-45-auf-5x), including the extension prerequisite **4.6**, before applying the 5.x-to-6.0 procedure above. Do not interpret the old extension prerequisite as sufficient for 6.0.

Version 5 changed prefixed variable idents, such as `Z2M_Brightness`, while retaining compatible variable IDs. Type-incompatible old variables were retained and additional correctly typed variables created; adjust ID-based links/events where needed. Historical examples include `action_transition_time`, `action_transaction` and coordinate variables. Backups are also required for these older migrations.

## Extension installation

The Symcon extension supplies the information and list requests used by the configurator, bridge, devices and groups. Install only the variant matching Zigbee2MQTT:

| Zigbee2MQTT | Bundled source | Zigbee2MQTT directory |
| --- | --- | --- |
| Up to 1.42 | [IPSymconExtension.js](libs/IPSymconExtension.js) | `data/extension` |
| From 2.0 | [IPSymconExtension2.js](libs/IPSymconExtension2.js) | `data/external_extensions` |

The filename uses the historical `IPSymcon` spelling; this is a literal filename, not a different product.

**Recommended:** Use the matching [bridge's installation action](Bridge/README.en.md). Review version/status and confirm the update manually. The bridge reads the installed and bundled versions; an unconfirmed installation is reported rather than assumed successful.

**Manual frontend installation:** Open Zigbee2MQTT's developer console/external extensions, create an extension such as `symcon.js`, paste the matching bundled source, save and restart Zigbee2MQTT if required. Remove duplicate older Symcon extensions rather than running both.

![Create extension](imgs/z2m_extension_anlegen.png)
![Extension source](imgs/z2m_extension_code.png)
![Restart](imgs/z2m_extension_restart.png)

**Manual filesystem installation:** Experienced users may place the matching file in the directory above on the Zigbee2MQTT host. Exact service paths and permissions depend on that installation; do not confuse the Zigbee2MQTT host with the Symcon server.

## Tiles, presentations and profiles

- An **instance tile** is the instance's tile visualization, including supported HTML SDK tiles.
- A **variable presentation** is Symcon's native representation of a single variable, set as its module default through the registration API.
- A **profile** is a legacy Symcon variable profile. Existing profiles may remain after upgrades; native presentations replace them as the module's current defaults.

Custom profiles and custom presentations belong to the user and have priority over module defaults. Changing a module's standard presentation does not remove these user overrides.

Supported custom device tiles cover heating, switches with optional measurements/multiple outputs, sensors, security/contact/presence devices, window handles and buttons/remotes/actions. They inherit basic colors and font sizing from the selected Symcon theme. Semantic alarm/status colors remain meaningful. Instance configuration shows only relevant tile choices.

RGB/HS/XY lights, tunable-white lights and covers use native Symcon presentations and standard tiles. Separate custom RGB/tunable-white HTML tiles are not part of the current library. See the [device guide](Device/README.en.md).

## Variables and maintenance

The local device/group variable catalog tracks known exposes and received payload properties. You can create, disable or re-enable variables explicitly. Deleting a known variable suppresses unsolicited recreation; recreating it requires a deliberate catalog action. Refresh the catalog to rebuild its known entries from current data, without deleting existing Symcon variables.

Options use typed editors where definitions are available. JSON/attribute-list options remain explicit; apply values only after checking their effect in Zigbee2MQTT.

The bridge's stale-variable overview is scoped to the same MQTT connection and base topic. It opens the owning device/group for review; the bridge does not centrally delete variables. Instance maintenance handles only directly owned variables. References and archive logging protect candidates, and each deletion is checked again and confirmed separately. The archive status and last update timestamp help the user decide.

## Actions

The [bundled Actions](actions/README.en.md) are reusable Symcon action templates, not additional modules. Use them in events, flow scripts or automations for suitable device/group instances. Supported operations include state toggling, transition times, Kelvin color temperature and scene recall. Device capabilities and valid ranges still apply.

Public PHP APIs, parameter semantics, return values and examples are documented in each module guide. Use `RequestAction($variableID, $value)` when addressing a variable so custom actions remain effective; distinguish this from instance-specific `Z2M_*` calls.

## Local data and cross-instance checks

Persistent generated files are confined to `user/IPSZigbee2MQTT` below the Symcon directory:

- `backups`: ZIP files created by an explicit bridge backup action.
- `networkmaps`: user-requested map exports.
- `icons`: shared model-based PNG cache with TLS, size, format and dimension checks.

Large backup responses may temporarily use the system temp directory under `IPSZigbee2MQTT`; temporary files are removed after finalization. Legacy expose JSON may be read as upgrade recovery data.

The bridge reads information from other instances of this library for diagnostics, OTA and maintenance. Network/maintenance selections are scoped as documented; these read-only checks do not rewrite unrelated instance properties. Stale-variable checks read references and archive status system-wide to protect user data. Actual deletion remains explicit and local to the owning instance.

Discovery's direct external-broker search supports `mqtt://` and `mqtts://`. TLS verifies the certificate and hostname by default. Disabling verification for a self-signed broker is an explicit choice that weakens broker identity checking; there is no silent plaintext fallback. Existing instances use their configured Symcon MQTT data flow, not Discovery's separate connection.

## Changelog

See the [English changelog](CHANGELOG.en.md) or [German changelog](CHANGELOG.md) for the consolidated changes since public beta **5.43**. Older chronological notes and the 5.x history are preserved in [historical notes](docs/CHANGELOG_HISTORY.md), which describe intermediate states and do not override current instructions.

## Donations

This library is free for non-commercial use. Optional support for the author: [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW) or [Amazon wishlist](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share).

## Third-party components

- `libs/phpMQTT.php`: phpMQTT by Blue Rhinos Consulting / Andrew Milsted, MIT license; license text included in the file.
- `NetworkMap/assets/cytoscape.min.js`: [Cytoscape.js](https://js.cytoscape.org/), MIT license; see [license text](NetworkMap/assets/CYTOSCAPE-LICENSE.txt).

Both are bundled locally. The network map does not load external web resources.

## License

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/). Third-party components retain their own licenses above.
