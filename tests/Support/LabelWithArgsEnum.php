<?php

namespace Mhamed\SpatieActivitylogBrowse\Tests\Support;

/** label() needs an argument, so the presenter must skip it and try the next method. */
enum LabelWithArgsEnum: string
{
    case One = 'one';

    public function label(string $locale): string
    {
        return "label-{$locale}";
    }

    public function title(): string
    {
        return 'Title of one';
    }
}
