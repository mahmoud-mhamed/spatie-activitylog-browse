<?php

namespace Mhamed\SpatieActivitylogBrowse\Tests\Support;

/** Int-backed enum without label methods: labels come from lang `enums.PriorityEnum.{value}`. */
enum PriorityEnum: int
{
    case Low = 1;
    case High = 2;
}
