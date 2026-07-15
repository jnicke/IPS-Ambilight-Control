<?php

declare(strict_types=1);

final class MediaLightAutoloader
{
    /**
     * @var array<string, string>
     */
    private const CLASS_MAP = [
        'MediaLightConfig'     => 'Config.php',
        'MediaLightDevice'     => 'Device.php',
        'MediaLightHttpClient' => 'HttpClient.php',
        'MediaLightJsonRpc'    => 'JsonRpc.php',
        'MediaLightLogger'     => 'Logger.php'
    ];

    public static function register(string $baseDirectory): void
    {
        spl_autoload_register(
            static function (string $className) use ($baseDirectory): void {
                if (!array_key_exists($className, self::CLASS_MAP)) {
                    return;
                }

                $file = rtrim($baseDirectory, DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . self::CLASS_MAP[$className];

                if (!is_file($file)) {
                    throw new RuntimeException(
                        sprintf(
                            'MediaLight class file not found: %s',
                            $file
                        )
                    );
                }

                require_once $file;
            }
        );
    }
}