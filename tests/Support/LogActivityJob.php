<?php

namespace Mhamed\SpatieActivitylogBrowse\Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Runs some queries, logs an activity; optionally dispatches a child job (sync driver runs it inline) or throws. */
class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(
        public string $description,
        public bool $dispatchChild = false,
        public bool $fail = false,
        public int $queries = 0,
        public int $childQueries = 0,
    ) {
    }

    public function handle(): void
    {
        for ($i = 0; $i < $this->queries; $i++) {
            DB::select('select 1');
        }

        activity()->log($this->description);

        if ($this->dispatchChild) {
            dispatch(new self($this->description . ':child', queries: $this->childQueries));
            activity()->log($this->description . ':after-child');
        }

        if ($this->fail) {
            throw new \RuntimeException("job {$this->description} failed");
        }
    }
}
