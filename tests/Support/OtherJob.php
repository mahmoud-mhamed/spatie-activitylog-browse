<?php

namespace Mhamed\SpatieActivitylogBrowse\Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** A second job class, to tell jobs apart in execution_context.job_name. */
class OtherJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        activity()->log('other job');
    }
}
