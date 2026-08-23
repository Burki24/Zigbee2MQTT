<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Schuetzt laufende Modulaufrufe waehrend Kernelstart und Library-Reload.
 */
final class ModuleUpdateGuard
{
    /**
     * Fuehrt eine Operation nur bei bereitem Kernel aus und verwirft sie, wenn
     * Symcon die PHP-Instanzschnittstelle waehrend eines Updates gerade ersetzt.
     */
    public static function Execute(\Closure $operation, mixed $fallback): mixed
    {
        if (\function_exists('IPS_GetKernelRunlevel') && IPS_GetKernelRunlevel() !== KR_READY) {
            return $fallback;
        }

        \set_error_handler(
            static function (int $severity, string $message): bool
            {
                if (!self::IsUnavailableInterfaceMessage($message)) {
                    return false;
                }

                throw new \ErrorException($message, 0, $severity);
            }
        );

        try {
            return $operation();
        } catch (\Throwable $throwable) {
            if (self::IsUnavailableInterfaceMessage($throwable->getMessage())) {
                return $fallback;
            }
            throw $throwable;
        } finally {
            \restore_error_handler();
        }
    }

    private static function IsUnavailableInterfaceMessage(string $message): bool
    {
        return \str_contains($message, 'InstanceInterface is not available')
            || \str_contains($message, 'InstanceInterface is not a PHP module');
    }
}
