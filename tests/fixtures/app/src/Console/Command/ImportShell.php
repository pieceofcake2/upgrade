<?php
class ImportShell extends AppShell
{
    public $tasks = ['Extract', 'Download'];

    public function main()
    {
        $this->out('Import');
    }
}
