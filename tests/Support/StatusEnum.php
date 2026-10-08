<?php

namespace Mhamed\SpatieActivitylogBrowse\Tests\Support;

/** String-backed enum with a label method (the presenter tries getLabel/label/translate/...). */
enum StatusEnum: string
{
    case Pending = 'pending';
    case Paid = 'paid';

    public function translate(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for payment',
            self::Paid => 'Fully paid',
        };
    }
}
