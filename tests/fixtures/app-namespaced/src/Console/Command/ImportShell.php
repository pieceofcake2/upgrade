<?php

namespace App\Console\Command;

use Cake\Console\Command\Task\ExtractTask;
use App\Console\Command\Task\DownloadTask;

class ImportShell extends AppShell
{
    public $tasks = [ExtractTask::class, DownloadTask::class];

    public function main()
    {
        $this->out('Import');
    }
}
