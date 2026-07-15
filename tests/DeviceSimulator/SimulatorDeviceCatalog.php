<?php

declare(strict_types=1);

namespace Zigbee2MQTT\Tools\DeviceSimulator;

/**
 * Stellt virtuelle Zigbee2MQTT-Geräte mit repräsentativen Expose-Strukturen bereit.
 */
final class SimulatorDeviceCatalog
{
    /**
     * Liefert alle virtuellen Geräte, indiziert nach ihrem Friendly Name.
     *
     * @return array<string,array<string,mixed>> Geräte, indiziert nach Friendly Name.
     */
    public static function devices(): array
    {
        $devices = [
            self::tunableWhiteDevice(),
            self::allExposesDevice(),
        ];

        $indexed = [];
        foreach ($devices as $device) {
            $indexed[(string) $device['friendly_name']] = $device;
        }

        return $indexed;
    }

    /**
     * Erstellt ein virtuelles Tunable-White-Leuchtmittel mit fünf Farbtemperatur-Presets.
     *
     * @return array<string,mixed>
     */
    private static function tunableWhiteDevice(): array
    {
        return [
            'ieeeAddr'           => '0x0000000000000001',
            'type'               => 'Router',
            'networkAddress'     => 65001,
            'model'              => 'SIM-TW-01',
            'vendor'             => 'Symcon Test',
            'description'        => 'Virtual tunable-white light',
            'friendly_name'      => 'Test/VirtualTunableWhite',
            'manufacturerName'   => 'Symcon Test',
            'powerSource'        => 'Mains (single phase)',
            'modelID'            => 'SIM-TW-01',
            'supports_ota'       => true,
            'options'            => ['filtered_attributes' => []],
            'definition_options' => self::definitionOptions(),
            'filtered_attributes'=> [],
            'endpoints'          => self::endpoints(['genBasic', 'genOnOff', 'genLevelCtrl', 'lightingColorCtrl']),
            'exposes'            => [
                [
                    'type'     => 'light',
                    'features' => [
                        self::binary('state', 'State', 7, 'ON', 'OFF', 'TOGGLE'),
                        self::numeric('brightness', 'Brightness', 7, '', 0, 254),
                        self::colorTemperatureFeature(),
                        [
                            'name'        => 'color_temp_startup',
                            'label'       => 'Color temp startup',
                            'access'      => 7,
                            'type'        => 'numeric',
                            'property'    => 'color_temp_startup',
                            'description' => 'Color temperature after cold power on',
                            'unit'        => 'mired',
                            'value_min'   => 153,
                            'value_max'   => 500,
                            'presets'     => array_merge(self::colorTemperaturePresets(), [
                                ['name' => 'previous', 'value' => 65535, 'description' => 'Restore previous color temperature'],
                            ]),
                        ],
                    ],
                ],
                self::enum('effect', 'Effect', 2, ['blink', 'breathe', 'okay', 'finish_effect', 'stop_effect']),
                self::enum('power_on_behavior', 'Power-on behavior', 7, ['off', 'on', 'toggle', 'previous'], 'config'),
                self::numeric('linkquality', 'Linkquality', 1, 'lqi', 0, 255, 1, 'diagnostic'),
            ],
            'state'             => [
                'state'              => 'ON',
                'brightness'         => 128,
                'color_temp'         => 370,
                'color_temp_startup' => 370,
                'effect'             => 'stop_effect',
                'power_on_behavior'  => 'previous',
                'linkquality'        => 180,
                'last_seen'          => time(),
            ],
        ];
    }

    /**
     * Dieses Gerät kombiniert bewusst alle Expose-Formen und die vom Modul
     * verwendeten Zigbee2MQTT-Gruppen und -Kategorien. Es dient als
     * Kompatibilitätsfixture und bildet kein konkretes physisches Produkt nach.
     *
     * @return array<string,mixed>
     */
    private static function allExposesDevice(): array
    {
        return [
            'ieeeAddr'           => '0x0000000000000002',
            'type'               => 'Router',
            'networkAddress'     => 65002,
            'model'              => 'SIM-ALL-01',
            'vendor'             => 'Symcon Test',
            'description'        => 'Virtual device containing all supported expose categories',
            'friendly_name'      => 'Test/AllExposes',
            'manufacturerName'   => 'Symcon Test',
            'powerSource'        => 'Mains (single phase)',
            'modelID'            => 'SIM-ALL-01',
            'supports_ota'       => true,
            'options'            => ['filtered_attributes' => []],
            'definition_options' => self::definitionOptions(),
            'filtered_attributes'=> [],
            'endpoints'          => self::endpoints([
                'genBasic',
                'genOnOff',
                'genLevelCtrl',
                'genScenes',
                'closuresWindowCovering',
                'hvacThermostat',
                'lightingColorCtrl',
                'msTemperatureMeasurement',
                'msRelativeHumidity',
                'seMetering',
                'haElectricalMeasurement',
            ]),
            'exposes'            => self::allExposes(),
            'state'              => self::allExposeState(),
        ];
    }

    /**
     * Liefert sämtliche Expose-Gruppen des umfassenden Kompatibilitätsgeräts.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function allExposes(): array
    {
        return [
            [
                'type'     => 'light',
                'features' => [
                    self::binary('state_l1', 'Light state', 7, 'ON', 'OFF', 'TOGGLE'),
                    self::numeric('brightness', 'Brightness', 7, '', 0, 254),
                    self::colorTemperatureFeature(),
                    [
                        'name'       => 'color',
                        'label'      => 'Color',
                        'access'     => 7,
                        'type'       => 'composite',
                        'property'   => 'color',
                        'color_mode' => ['xy', 'hs'],
                        'features'   => [
                            self::numeric('x', 'X', 7, '', 0, 1, 0.001),
                            self::numeric('y', 'Y', 7, '', 0, 1, 0.001),
                            self::numeric('hue', 'Hue', 7, '°', 0, 360, 1),
                            self::numeric('saturation', 'Saturation', 7, '%', 0, 100, 1),
                        ],
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'features' => [
                    self::binary('state_l2', 'Switch state', 7, 'ON', 'OFF', 'TOGGLE'),
                ],
            ],
            [
                'type'     => 'cover',
                'features' => [
                    self::numeric('position', 'Position', 7, '%', 0, 100),
                    self::enum('cover_state', 'Cover state', 1, ['OPEN', 'CLOSE', 'STOP']),
                    self::binary('moving', 'Moving', 1, true, false),
                ],
            ],
            [
                'type'     => 'lock',
                'features' => [
                    self::enum('lock_state', 'Lock state', 7, ['LOCK', 'UNLOCK', 'NOT_FULLY_LOCKED']),
                ],
            ],
            [
                'type'     => 'climate',
                'features' => [
                    self::numeric('local_temperature', 'Local temperature', 1, '°C', -20, 60, 0.1),
                    self::numeric('occupied_heating_setpoint', 'Occupied heating setpoint', 7, '°C', 5, 30, 0.5),
                    self::enum('system_mode', 'System mode', 7, ['off', 'auto', 'heat']),
                    self::enum('running_state', 'Running state', 1, ['idle', 'heat']),
                ],
            ],
            [
                'type'     => 'fan',
                'features' => [
                    self::binary('fan_state', 'Fan state', 7, 'ON', 'OFF', 'TOGGLE'),
                    self::enum('fan_mode', 'Fan mode', 7, ['off', 'low', 'medium', 'high', 'auto']),
                ],
            ],
            [
                'type'     => 'text',
                'features' => [
                    self::text('display_text', 'Display text', 7),
                ],
            ],

            // Standalone binary exposes: readable, writable and diagnostic.
            self::binary('contact', 'Contact', 1, true, false),
            self::binary('occupancy', 'Occupancy', 1, true, false),
            self::binary('tamper', 'Tamper', 1, true, false, null, 'diagnostic'),
            self::binary('battery_low', 'Battery low', 1, true, false, null, 'diagnostic'),
            self::binary('child_lock', 'Child lock', 7, 'LOCK', 'UNLOCK'),

            // Numeric exposes cover integer, float, signed and metering values.
            self::numeric('temperature', 'Temperature', 1, '°C', -40, 125, 0.1),
            self::numeric('humidity', 'Humidity', 1, '%', 0, 100, 0.1),
            self::numeric('pressure', 'Pressure', 1, 'hPa', 300, 1100, 0.1),
            self::numeric('battery', 'Battery', 1, '%', 0, 100, 1, 'diagnostic'),
            self::numeric('voltage', 'Voltage', 1, 'V', 0, 400, 0.1),
            self::numeric('current', 'Current', 1, 'A', 0, 32, 0.01),
            self::numeric('power', 'Power', 1, 'W', 0, 7500, 0.1),
            self::numeric('energy', 'Energy', 1, 'kWh', 0, 100000, 0.001),
            self::numeric('illuminance_lux', 'Illuminance lux', 1, 'lx', 0, 100000),
            self::numeric('linkquality', 'Linkquality', 1, 'lqi', 0, 255, 1, 'diagnostic'),
            [
                'name'        => 'calibration',
                'label'       => 'Calibration',
                'type'        => 'numeric',
                'property'    => 'calibration',
                'access'      => 7,
                'unit'        => '%',
                'value_min'   => -10,
                'value_max'   => 10,
                'value_step'  => 0.1,
                'category'    => 'config',
                'description' => 'Signed numeric configuration value',
                'presets'     => [
                    ['name' => 'minus_ten', 'value' => -10, 'description' => 'Minimum'],
                    ['name' => 'zero', 'value' => 0, 'description' => 'Neutral'],
                    ['name' => 'plus_ten', 'value' => 10, 'description' => 'Maximum'],
                ],
            ],

            // Enum, text, composite, list and a write-only single-value command.
            self::enum('operation_mode', 'Operation mode', 7, ['manual', 'schedule', 'eco', 'boost'], 'config'),
            self::text('serial_number', 'Serial number', 1, 'diagnostic'),
            self::text('user_note', 'User note', 7, 'config'),
            [
                'name'        => 'coordinates',
                'label'       => 'Coordinates',
                'type'        => 'composite',
                'property'    => 'coordinates',
                'access'      => 7,
                'description' => 'Generic composite expose',
                'features'    => [
                    self::numeric('latitude', 'Latitude', 7, '°', -90, 90, 0.0001),
                    self::numeric('longitude', 'Longitude', 7, '°', -180, 180, 0.0001),
                    self::text('label', 'Coordinate label', 7),
                ],
            ],
            [
                'name'        => 'weekly_schedule',
                'label'       => 'Weekly schedule',
                'type'        => 'list',
                'property'    => 'weekly_schedule',
                'access'      => 7,
                'description' => 'List expose with composite items',
                'item_type'   => [
                    'type'     => 'composite',
                    'features' => [
                        self::enum('day', 'Day', 7, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                        self::text('time', 'Time', 7),
                        self::numeric('setpoint', 'Setpoint', 7, '°C', 5, 30, 0.5),
                    ],
                ],
            ],
            self::enum('identify', 'Identify', 2, ['identify'], 'config'),
            self::enum('action', 'Action', 1, ['single', 'double', 'hold', 'release']),
        ];
    }

    /**
     * Liefert den Anfangszustand zu sämtlichen Exposes des Kompatibilitätsgeräts.
     *
     * @return array<string,mixed>
     */
    private static function allExposeState(): array
    {
        return [
            'state_l1'                  => 'ON',
            'brightness'                => 180,
            'color_temp'                => 250,
            'color'                     => ['x' => 0.3127, 'y' => 0.3290, 'hue' => 40, 'saturation' => 75],
            'state_l2'                  => 'OFF',
            'position'                  => 55,
            'cover_state'               => 'STOP',
            'moving'                    => false,
            'lock_state'                => 'LOCK',
            'local_temperature'         => 21.4,
            'occupied_heating_setpoint' => 22.0,
            'system_mode'               => 'heat',
            'running_state'             => 'idle',
            'fan_state'                 => 'ON',
            'fan_mode'                  => 'medium',
            'display_text'              => 'Simulator ready',
            'contact'                   => true,
            'occupancy'                 => false,
            'tamper'                    => false,
            'battery_low'               => false,
            'child_lock'                => 'UNLOCK',
            'temperature'               => 20.8,
            'humidity'                  => 48.5,
            'pressure'                  => 1013.2,
            'battery'                   => 87,
            'voltage'                   => 230.1,
            'current'                   => 0.42,
            'power'                     => 88.4,
            'energy'                    => 12.345,
            'illuminance_lux'           => 725,
            'linkquality'               => 200,
            'calibration'               => 0.0,
            'operation_mode'            => 'manual',
            'serial_number'             => 'SIM-2026-0002',
            'user_note'                 => 'Editable simulator text',
            'coordinates'               => ['latitude' => 52.52, 'longitude' => 13.405, 'label' => 'Test position'],
            'weekly_schedule'           => [
                'type'  => 'list',
                'items' => [
                    ['day' => 'monday', 'time' => '06:00', 'setpoint' => 21.0],
                    ['day' => 'monday', 'time' => '22:00', 'setpoint' => 17.0],
                ],
            ],
            'action'                    => 'single',
            'last_seen'                 => time(),
        ];
    }

    /**
     * Erstellt ein binäres Zigbee2MQTT-Expose-Feature.
     *
     * @return array<string,mixed>
     */
    private static function binary(
        string $property,
        string $label,
        int $access,
        mixed $valueOn,
        mixed $valueOff,
        mixed $valueToggle = null,
        ?string $category = null
    ): array {
        $feature = [
            'name'      => $property,
            'label'     => $label,
            'type'      => 'binary',
            'property'  => $property,
            'access'    => $access,
            'value_on'  => $valueOn,
            'value_off' => $valueOff,
        ];
        if ($valueToggle !== null) {
            $feature['value_toggle'] = $valueToggle;
        }
        if ($category !== null) {
            $feature['category'] = $category;
        }
        return $feature;
    }

    /**
     * Erstellt ein numerisches Zigbee2MQTT-Expose-Feature mit Wertebereich.
     *
     * @return array<string,mixed>
     */
    private static function numeric(
        string $property,
        string $label,
        int $access,
        string $unit,
        int|float $minimum,
        int|float $maximum,
        int|float $step = 1,
        ?string $category = null
    ): array {
        $feature = [
            'name'       => $property,
            'label'      => $label,
            'type'       => 'numeric',
            'property'   => $property,
            'access'     => $access,
            'value_min'  => $minimum,
            'value_max'  => $maximum,
            'value_step' => $step,
        ];
        if ($unit !== '') {
            $feature['unit'] = $unit;
        }
        if ($category !== null) {
            $feature['category'] = $category;
        }
        return $feature;
    }

    /**
     * Erstellt ein Zigbee2MQTT-Enum-Feature.
     *
     * @param array<int,string> $values Zulässige Enum-Werte.
     *
     * @return array<string,mixed>
     */
    private static function enum(
        string $property,
        string $label,
        int $access,
        array $values,
        ?string $category = null
    ): array {
        $feature = [
            'name'     => $property,
            'label'    => $label,
            'type'     => 'enum',
            'property' => $property,
            'access'   => $access,
            'values'   => $values,
        ];
        if ($category !== null) {
            $feature['category'] = $category;
        }
        return $feature;
    }

    /**
     * Erstellt ein textuelles Zigbee2MQTT-Expose-Feature.
     *
     * @return array<string,mixed>
     */
    private static function text(string $property, string $label, int $access, ?string $category = null): array
    {
        $feature = [
            'name'     => $property,
            'label'    => $label,
            'type'     => 'text',
            'property' => $property,
            'access'   => $access,
        ];
        if ($category !== null) {
            $feature['category'] = $category;
        }
        return $feature;
    }

    /**
     * Erstellt das für Tunable-White-Tests verwendete Farbtemperatur-Feature.
     *
     * @return array<string,mixed>
     */
    private static function colorTemperatureFeature(): array
    {
        return [
            'name'        => 'color_temp',
            'label'       => 'Color temperature',
            'type'        => 'numeric',
            'property'    => 'color_temp',
            'description' => 'Color temperature in mired',
            'access'      => 7,
            'unit'        => 'mired',
            'value_min'   => 153,
            'value_max'   => 500,
            'presets'     => self::colorTemperaturePresets(),
        ];
    }

    /**
     * Liefert fünf eindeutige Farbtemperatur-Presets für den Simulator.
     *
     * @return array<int,array{name:string,value:int,description:string}>
     */
    private static function colorTemperaturePresets(): array
    {
        return [
            ['name' => 'coolest', 'value' => 153, 'description' => 'Coolest temperature'],
            ['name' => 'cool', 'value' => 240, 'description' => 'Cool temperature'],
            ['name' => 'neutral', 'value' => 326, 'description' => 'Neutral temperature'],
            ['name' => 'warm', 'value' => 413, 'description' => 'Warm temperature'],
            ['name' => 'warmest', 'value' => 500, 'description' => 'Warmest temperature'],
        ];
    }

    /**
     * Liefert simulierte Zigbee2MQTT-Definitionsoptionen.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function definitionOptions(): array
    {
        return [
            self::numeric('transition', 'Transition', 7, 's', 0, 60, 0.1, 'config'),
            self::enum('simulated_precision', 'Simulated precision', 7, ['low', 'normal', 'high'], 'config'),
        ];
    }

    /**
     * Erstellt die Endpunktinformationen eines simulierten Geräts.
     *
     * @param array<int,string> $inputClusters Eingangscluster des Endpunkts.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function endpoints(array $inputClusters): array
    {
        return [
            '1' => [
                'id'                    => '1',
                'name'                  => 'default',
                'bindings'              => [],
                'configured_reportings' => [
                    [
                        'cluster'                 => 'genOnOff',
                        'attribute'               => 'onOff',
                        'minimum_report_interval' => 0,
                        'maximum_report_interval' => 300,
                        'reportable_change'       => 1,
                    ],
                ],
                'clusters'              => [
                    'input'  => $inputClusters,
                    'output' => ['genOta'],
                    'scenes' => [],
                ],
            ],
        ];
    }
}
