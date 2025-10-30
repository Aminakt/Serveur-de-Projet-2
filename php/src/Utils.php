<?php

declare(strict_types= 1);

namespace Php\Src;


final class Utils {
    public static function dbReturn(bool $error, mixed $data): array {
    return ['error' => $error, 'data' => $data];
    }
    public static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    public static function now(): \DateTimeImmutable{
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}