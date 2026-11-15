[Deutsch](README.md) | [English](README.en.md)

# Zigbee2MQTT Discovery

Discovery finds Zigbee2MQTT installations through MQTT brokers and offers their [Configurators](../Configurator/README.en.md). It belongs to the [Zigbee2MQTT library](../README.en.md).

## Requirements and installation

- Symcon 9.0 or newer.
- A running [Zigbee2MQTT](https://www.zigbee2mqtt.io) installation.
- An MQTT broker: either a Symcon MQTT Server or an external broker such as Mosquitto.

Create Discovery after installing the library from the Module Store. Discovery itself needs no initial configuration. For an already configured Symcon MQTT Client or Server, it searches through that existing connection. You can also enter an external broker manually.

## Discovering installations

The form immediately displays the most recently cached results. If the cache is older than 60 seconds, a background search examines the MQTT Clients and Servers configured in Symcon and updates the form. **Refresh discovery** starts a search manually.

![Discovery overview](imgs/config1.png)
![Discovery configuration](imgs/config2.png)

Select a result and choose **Create**. Symcon creates the matching Configurator and, when required for a manually entered external broker, an MQTT Client and Client Socket. Creation requires your use of the normal configurator controls. **Configure** opens the existing Configurator for the next setup steps.

## Manually specifying an external broker

Enter the broker URL and, if needed, username and password. Empty credentials are appropriate only if the broker permits anonymous access.

| URL scheme | Connection |
| --- | --- |
| `mqtt://` | Unencrypted MQTT. |
| `mqtts://` | MQTT over TLS. Certificate and hostname verification are enabled by default. |

For TLS, the server certificate must be trusted by the Symcon system and valid for the specified host. For explicitly trusted local brokers with self-signed certificates, **Verify TLS certificate** and **Verify TLS hostname** can be disabled deliberately. This retains encryption but no longer fully verifies the broker's identity. Do this only on a trusted local network with a broker you control.

Discovery never automatically falls back from a failed TLS connection to unencrypted MQTT. These verification options affect only Discovery's direct search of external brokers. Existing Zigbee2MQTT instances continue communicating through their configured Symcon MQTT splitter.

## Reusing an existing configurator

When moving an installation to another MQTT connection, **Reuse existing configurator (optional)** lets you explicitly select an existing configurator with the same base topic, including a disconnected configurator. Its configuration is offered for the chosen target. Selecting a candidate alone does not change a connection; you must deliberately apply the proposed configuration. Where several source or target connections exist, choose the intended combination explicitly.

Configurators already connected to the matching broker remain associated with that connection. Identical base topics on different MQTT connections are not sufficient grounds to merge installations automatically.

## Variables, functions and actions

Discovery creates no status variables and has no public PHP commands or external user actions. Search, manual broker entry and configurator selection are performed through the configuration form.

## Further information

- [Library installation and migration](../README.en.md)
- [Configurator](../Configurator/README.en.md)
- [Changelog](../CHANGELOG.en.md)
- [Beta release notes](../docs/BETA_RELEASE_NOTES.md#english)
- [License: CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)

The module is free for non-commercial use. Optional support: [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW) or the [author's wishlist](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share).
