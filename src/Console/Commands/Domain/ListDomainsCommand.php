<?php

namespace App\Console\Commands\Domain;

use App\Support\Domain\DomainManager;
use Illuminate\Console\Command;

class ListDomainsCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:list';

    /** @var string */
    protected $description = 'List registered domain modules (kind core|product, parent subproduct + removal status)';

    public function handle(DomainManager $domains): int
    {
        $rows = [];

        foreach ($domains->all() as $name => $meta) {
            $rows[] = [
                $name,
                $domains->kind($name),
                $meta['parent'] ?? '—',
                $domains->removalStatus($name),
                $domains->directoryExists($name) ? 'yes' : 'missing',
                $domains->isPackaged($name) ? 'package' : '—',
                $domains->slug($name),
            ];
        }

        if ($rows === []) {
            $this->warn('No domains registered in config/domains.php.');

            return self::SUCCESS;
        }

        $this->table(['Domain', 'Kind', 'Parent', 'Removal', 'Source', 'Package', 'Slug'], $rows);
        $this->line('Kind: <info>core</info> = foundation (never removable); <info>product</info> = SaaS surface.');
        $this->line('Parent: <info>product domain this one extends</info> (subproduct) — <info>—</info> = none.');
        $this->line('Removal: <info>never</info> = platform core; <info>locked</info> = unlock via config/domains.php; <info>removable</info> = domain:remove allowed.');
        $this->line('Package: <info>package</info> = Composer path package under packages/; <info>—</info> = in app/Domain/.');

        return self::SUCCESS;
    }
}
