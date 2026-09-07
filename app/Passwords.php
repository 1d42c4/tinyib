<?php

declare(strict_types=1);

namespace TinyIB;

final class Passwords
{
    #[\NoDiscard]
    public static function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    #[\NoDiscard]
    public static function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
