<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Console\Commands;

use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use Illuminate\Console\Command;

final class SyncSurveysCommand extends Command
{
    protected $signature = 'simple-surveys:sync';

    protected $description = 'Sync all code-registered surveys and questions to the database';

    public function handle(SurveySynchronizer $synchronizer): int
    {
        $records = $synchronizer->syncAll();

        $this->components->info(sprintf(
            'Synced %d %s to the database.',
            $records->count(),
            $records->count() === 1 ? 'survey' : 'surveys',
        ));

        return self::SUCCESS;
    }
}
