<?php

declare(strict_types=1);

namespace TinyIB;

final class Request
{
    public static function integer(mixed $value): int
    {
        if ($value === '') {
            return 0;
        }
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (!is_string($value) || !ctype_digit($value) || strlen($value) > 18) {
            throw new BoardMessage('A non-negative integer is required.');
        }
        return (int) $value;
    }

    public function queryInt(string $key): int
    {
        return self::integer($this->query[$key] ?? 0);
    }

    public function formInt(string $key): int
    {
        return self::integer($this->form[$key] ?? 0);
    }

    /** @return list<int> */
    public static function ids(string $value): array
    {
        return array_map(self::integer(...), explode(',', $value));
    }

    /** @param array<string, string|list<string>> $query
     * @param array<string, string|list<string>> $form
     * @param array<string, array<string, int|string>> $files
     * @param array<string, string> $server */
    public function __construct(
        public array $query,
        public array $form,
        public readonly array $files,
        public readonly array $server,
    ) {}

    #[\NoDiscard]
    public static function capture(): self
    {
        $normalize = static function (array $input): array {
            foreach ($input as $key => $value) {
                if (is_array($value)) {
                    if ($key !== 'delete' || !array_is_list($value)) {
                        throw new BoardMessage('Invalid request field.');
                    }
                    foreach ($value as $item) {
                        if (!is_string($item) || !ctype_digit($item)) {
                            throw new BoardMessage('Invalid post identifier.');
                        }
                    }
                } elseif (!is_string($value)) {
                    throw new BoardMessage('Invalid request field.');
                }
            }
            return $input;
        };
        $server = array_filter($_SERVER, is_string(...));
        $server['REMOTE_ADDR'] ??= '127.0.0.1';
        $server['PHP_SELF'] ??= '/imgboard.php';
        $server['REQUEST_METHOD'] ??= 'GET';
        return new self($normalize($_GET), $normalize($_POST), $_FILES, $server);
    }
}
