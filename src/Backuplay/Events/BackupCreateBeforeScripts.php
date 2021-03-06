<?php

namespace Astrotomic\Backuplay\Events;

use Astrotomic\Backuplay\Artisan\CreateBackup;

class BackupCreateBeforeScripts extends Event
{
    /**
     * @var \Astrotomic\Backuplay\Artisan\CreateBackup
     */
    public $command;
    /**
     * @var string
     */
    public $key;
    /**
     * @var array
     */
    public $scripts;

    public function __construct(CreateBackup $command, $key, array $scripts)
    {
        $this->command = $command;
        $this->key = $key;
        $this->scripts = $scripts;
    }
}
