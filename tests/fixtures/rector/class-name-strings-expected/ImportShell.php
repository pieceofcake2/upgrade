<?php
namespace App\Console\Command;

use Cake\Console\Command\Task\ExtractTask;

class ImportShell extends AppShell
{
    public $tasks = [ExtractTask::class, 'Template' => ['path' => 'tmp']];
}
