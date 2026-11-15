[Deutsch](README.md) | [English](README.en.md)

# Zigbee2MQTT Actions

These JSON files are Symcon action templates, not PHP functions to execute from the filesystem. They are available when a suitable Zigbee2MQTT device or group instance is selected as the action target.

## Use in Symcon

1. Open an event, flow script, automation or action dialog.
2. Select the device/group instance as target.
3. Choose an offered Zigbee2MQTT action.
4. Set brightness, step size, temperature, scene ID or transition duration as applicable.
5. Save or deliberately execute the action.

Actions appear only when the required variable idents exist. A missing action can indicate an unsupported feature or a variable disabled/deleted in the instance.

## Available actions

| Action | File | Required idents / behavior |
| --- | --- | --- |
| Toggle state | `toggleState.json` | `state`; excludes covers with `position`, `position_left` or `position_right`; sends `TOGGLE`. |
| Switch light with transition | `setStateWithTransition.json` | `state`, `brightness`; sends ON/OFF and transition duration. |
| Brightness with transition | `setWithTransition.json` | `brightness`; converts percent to Zigbee2MQTT 0–254. |
| Increase/decrease brightness | `dimStep.json` | `brightness`, `state`; one positive/negative step. |
| Start/stop relative dimming | `dimRelative.json` | `brightness`, `state`; positive brightens, negative dims, zero stops. |
| Color temperature with transition | `setColorTempWithTransition.json` | `color_temp`, `color_temp_kelvin`; Kelvin converted to Mired. |
| Color temperature step | `dimColorTempStep.json` | `color_temp`; positive warms, negative cools. |
| Start/stop relative color temperature | `dimColorTempRelative.json` | `color_temp`; zero stops ongoing movement. |
| Color with transition | `setColorWithTransition.json` | `color`; requires a native color expose for execution. |
| On with timed off | `setOnTime.json` | `state`, `countdown`; switches on now and off after the duration. |
| Off with timed on | `setOffTime.json` | `state`, `countdown`; switches off now and on after the duration. |
| Recall scene | `recallScene.json` | A device/group instance with an existing supported Zigbee scene. |

## Parameters

- **Transition time:** seconds; zero applies immediately. `Z2M_SetColorExt` takes integer seconds. Other Set payloads may support fractional seconds depending on the device. Unsupported transitions may be ignored by Zigbee2MQTT/the device.
- **Brightness:** the UI uses percent and converts to native 0–254. Relative commands use Zigbee2MQTT step/movement semantics.
- **Color temperature:** Kelvin action uses `color_temp_kelvin` and converts with `mired = 1000000 / kelvin`. Relative actions use native color-temperature step/movement commands.
- **Countdown:** seconds; requires a `countdown` expose. Device-specific limits still apply even if a generic editor offers a larger range.
- **Scene ID:** the existing Zigbee2MQTT scene ID for the target. Create/store a scene before recalling it.

## Flow-script example

When a motion detector detects movement, run **Switch light with transition** on the lamp/group with `State = ON` and `TransitionTime = 1.5`. After the desired wait, use the same action with `OFF`. Timed on/off actions are an alternative only for devices exposing countdown.

## PHP example

```php
<?php

$instanceID = 12345;

Z2M_SendSetCommand($instanceID, ['state' => 'TOGGLE']);

Z2M_SendSetCommand($instanceID, [
    'brightness' => (int) round(50 * 254 / 100),
    'transition' => 2.0
]);

Z2M_SendSetCommand($instanceID, [
    'color_temp' => (int) round(1000000 / 4000),
    'transition' => 2.0
]);

Z2M_SetColorExt($instanceID, 0xFFAA00, 2);
Z2M_SendSetCommand($instanceID, ['scene_recall' => 1]);
```

## Limits and related documentation

Actions send Zigbee2MQTT Set payloads; they do not replace custom variable actions. Wake battery devices when needed. Scene, transition and countdown support depend on the target. For scripts, use the documented module functions or `RequestAction` on a variable when its actual/custom action is intended; do not recursively call a variable from its own custom action script.

Color changes in XY/HS/HSV preserve separate brightness. Black with separate brightness is a successful no-op, not a switch-off command. Pure tunable-white devices do not gain RGB control from their derived white-color variable.

[Device functions](../Device/README.en.md#php-functions) · [Group functions](../Group/README.en.md#php-functions) · [Library](../README.en.md) · [Changelog](../CHANGELOG.en.md)
