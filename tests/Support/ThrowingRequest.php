<?php

namespace Mhamed\SpatieActivitylogBrowse\Tests\Support;

use Illuminate\Http\Request;

/** A request whose accessors blow up, to make collectors throw. */
class ThrowingRequest extends Request
{
    /** @var array<int, string> */
    public static array $throwOn = [];

    public function fullUrl(): string
    {
        $this->maybeThrow('fullUrl');

        return parent::fullUrl();
    }

    public function ip(): ?string
    {
        $this->maybeThrow('ip');

        return parent::ip();
    }

    public function isJson(): bool
    {
        $this->maybeThrow('isJson');

        return parent::isJson();
    }

    private function maybeThrow(string $method): void
    {
        if (in_array($method, self::$throwOn, true)) {
            throw new \RuntimeException("{$method} exploded");
        }
    }
}
