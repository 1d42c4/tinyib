<?php

declare(strict_types=1);

namespace TinyIB;

final class BoardLock
{
    /** @var resource */
    private $handle;

    public function __construct(string $filename)
    {
        $handle = fopen($filename, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Unable to acquire the board write lock.');
        }
        $this->handle = $handle;
    }

    public function __destruct()
    {
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
    }
}
