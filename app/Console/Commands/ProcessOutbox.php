<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Outbox;
use Illuminate\Console\Command;

class ProcessOutbox extends Command
{
    protected $signature = 'jmac:outbox {--limit=20}';

    protected $description = 'Procesa un lote finito de correos pendientes por SMTP';

    public function handle(Outbox $outbox): int
    {
        $this->line(json_encode($outbox->process((int) $this->option('limit'))));

        return self::SUCCESS;
    }
}
