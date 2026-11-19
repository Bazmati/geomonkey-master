<?php

namespace App\Command;

use App\Exception\MailboxUnavailableException;
use App\Service\MailboxSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Synchronise les en-têtes de la boîte mail IMAP vers le cache local
 * (table mailbox_messages) lu par la page Messages du backoffice.
 *
 * Usage :
 *   php bin/console app:mailbox:sync           # incrémental (défaut, cron)
 *   php bin/console app:mailbox:sync --full   # toute la boîte (première fois)
 *
 * Cron recommandé sur le CT applicatif (toutes les 5 minutes) :
 *   *\\5 * * * * cd /var/www/geomonkey && php bin/console app:mailbox:sync --env=prod
 */
#[AsCommand(
    name: 'app:mailbox:sync',
    description: 'Synchronise les en-têtes de la boîte mail IMAP dans le cache local.',
)]
final class MailboxSyncCommand extends Command
{
    public function __construct(private readonly MailboxSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('full', null, InputOption::VALUE_NONE, 'Parcourir toute la boîte (première synchronisation ou rattrapage)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $full = (bool) $input->getOption('full');

        $io->title($full ? 'Synchronisation complète de la boîte' : 'Synchronisation incrémentale de la boîte');

        try {
            $result = $this->synchronizer->synchronize(
                full: $full,
                onChunk: function (int $page, int $inChunk, int $created, int $updated) use ($io): void {
                    $io->writeln(sprintf(
                        '  chunk #%d : %d message(s) — %d créé(s), %d mis à jour',
                        $page, $inChunk, $created, $updated
                    ));
                },
            );
        } catch (MailboxUnavailableException $e) {
            $io->error('Boîte mail indisponible : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Synchro terminée : %d nouveau(x) message(s), %d mis à jour.',
            $result['created'],
            $result['updated']
        ));

        return Command::SUCCESS;
    }
}
