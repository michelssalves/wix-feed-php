<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(array $config): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $db = $config['database'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );

        try {
            self::$connection = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $timezoneName = trim((string) ($config['timezone'] ?? 'UTC'));
            $timezone = new DateTimeZone($timezoneName);
            $offsetSeconds = $timezone->getOffset(new DateTimeImmutable('now', $timezone));
            $offsetPrefix = $offsetSeconds >= 0 ? '+' : '-';
            $offsetSeconds = abs($offsetSeconds);
            $offsetHours = str_pad((string) intdiv($offsetSeconds, 3600), 2, '0', STR_PAD_LEFT);
            $offsetMinutes = str_pad((string) intdiv($offsetSeconds % 3600, 60), 2, '0', STR_PAD_LEFT);
            $mysqlOffset = sprintf('%s%s:%s', $offsetPrefix, $offsetHours, $offsetMinutes);

            self::$connection->exec("SET time_zone = '{$mysqlOffset}'");
        } catch (PDOException $exception) {
            throw new RuntimeException('Falha ao conectar no MySQL: ' . $exception->getMessage(), 0, $exception);
        }

        return self::$connection;
    }
}
