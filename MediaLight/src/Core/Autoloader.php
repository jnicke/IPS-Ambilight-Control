<?php

declare(strict_types=1);

namespace MediaLight\Core;

use RuntimeException;

final class Autoloader
{
    private const NAMESPACE_PREFIX = 'MediaLight\\';

    public static function register(string $sourceDirectory): void
    {
        $baseDirectory = rtrim(
            $sourceDirectory,
            DIRECTORY_SEPARATOR
        );

        spl_autoload_register(
            static function (string $className) use ($baseDirectory): void {
                if (!str_starts_with($className, self::NAMESPACE_PREFIX)) {
                    return;
                }

                $relativeClass = substr(
                    $className,
                    strlen(self::NAMESPACE_PREFIX)
                );

                if ($relativeClass === false || $relativeClass === '') {
                    return;
                }

                $relativePath = str_replace(
                    '\\',
                    DIRECTORY_SEPARATOR,
                    $relativeClass
                );

                $file = $baseDirectory
                    . DIRECTORY_SEPARATOR
                    . $relativePath
                    . '.php';

                if (!is_file($file)) {
                    throw new RuntimeException(
                        sprintf(
                            'MediaLight-Klassendatei nicht gefunden: %s',
                            $file
                        )
                    );
                }

                require_once $file;
            }
        );
    }
}