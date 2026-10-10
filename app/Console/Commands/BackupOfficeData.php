<?php

namespace App\Console\Commands;

use App\Actions\CreateOfficeBackup;
use Illuminate\Console\Command;

class BackupOfficeData extends Command
{
    protected $signature = 'office:backup';

    protected $description = 'Encrypt an office data and document backup on private local storage';

    public function handle(CreateOfficeBackup $backup): int
    {
        $this->info('Encrypted backup saved: '.$backup->handle());
        $this->comment('Recovery requires the original application encryption key, kept separately from the backup.');

        return self::SUCCESS;
    }
}
