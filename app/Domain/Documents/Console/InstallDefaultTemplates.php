<?php

namespace App\Domain\Documents\Console;

use App\Domain\Documents\Services\DefaultTemplateInstaller;
use App\Domain\Documents\Models\DocumentTemplate;
use Illuminate\Console\Command;

class InstallDefaultTemplates extends Command
{
    protected $signature = 'documents:install-defaults {--type= : Only this document type} {--reset : Overwrite existing default templates}';

    protected $description = 'Install (or reset) the starter document templates for every paperwork type';

    public function handle(): int
    {
        $type = $this->option('type');
        if ($type && ! isset(DocumentTemplate::TYPES[$type])) {
            $this->error("Unknown document type [{$type}].");

            return self::FAILURE;
        }
        $count = DefaultTemplateInstaller::install($type ?: null, (bool) $this->option('reset'));
        $this->info("{$count} template(s) ".($this->option('reset') ? 'installed or reset.' : 'installed.'));

        return self::SUCCESS;
    }
}
