<?php
namespace App\Console\Command;

class ImportShell extends AppShell
{
    public $tasks = ['Extract', 'Template' => ['path' => 'tmp']];
}
