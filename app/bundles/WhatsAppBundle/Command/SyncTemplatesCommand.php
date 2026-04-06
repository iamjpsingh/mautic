<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Command;

use Mautic\WhatsAppBundle\Service\TemplateSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author iamjpsingh
 */
#[AsCommand(
    name: 'mautic:whatsapp:sync-templates',
    description: 'Synchronize WhatsApp message templates from Meta Business Account.',
)]
class SyncTemplatesCommand extends Command
{
    public function __construct(
        private TemplateSyncService $templateSyncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('WhatsApp Template Sync');

        try {
            $results = $this->templateSyncService->syncTemplates();

            $io->success(sprintf(
                '%d templates synced, %d approved, %d pending, %d rejected',
                $results['synced'],
                $results['approved'],
                $results['pending'],
                $results['rejected']
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Template sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
