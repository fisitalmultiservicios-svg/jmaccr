<?php

namespace App\Console\Commands;

use App\Domain\System\Backups;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'jmac:backup {--restore=} {--confirm-staging}';

    protected $description = 'Backup privado cifrado / restore explícito solo staging';

    public function handle(Backups $backups): int
    {
        try {
            if ($this->option('restore')) {
                if (! $this->option('confirm-staging')) {
                    $this->error('Se requiere --confirm-staging; reemplazará datos del staging.');

                    return 1;
                }$backups->restore($this->option('restore'));
                $this->info('Restauración staging terminada.');
            } else {
                $this->info($backups->create());
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
