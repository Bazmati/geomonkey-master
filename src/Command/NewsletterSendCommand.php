<?php

namespace App\Command;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\MailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Envoi d'une campagne newsletter à tous les abonnés actifs.
 *
 * Usage :
 *   php bin/console app:newsletter:send \
 *     --subject="Les news de novembre" \
 *     --file=campagne.html \
 *     [--dry-run]
 *
 * Le fichier --file contient le corps HTML de l'email (sans <html>).
 * Le pied de page avec le lien de désinscription est ajouté automatiquement
 * pour chaque destinataire (obligation RGPD).
 */
#[AsCommand(
    name: 'app:newsletter:send',
    description: 'Envoie une campagne newsletter à tous les abonnés actifs.',
)]
final class NewsletterSendCommand extends Command
{
    public function __construct(
        private NewsletterSubscriberRepository $subscriberRepository,
        private MailService $mailService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Sujet de l\'email')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin du fichier HTML contenant le corps de la campagne')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre de destinataires (test)', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans rien envoyer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $subject = (string) $input->getOption('subject');
        $file = (string) $input->getOption('file');
        $limit = max(0, (int) $input->getOption('limit'));
        $dryRun = (bool) $input->getOption('dry-run');

        if ('' === $subject) {
            $io->error('Option --subject requise.');

            return Command::FAILURE;
        }

        if ('' === $file || !is_file($file) || !is_readable($file)) {
            $io->error(sprintf('Fichier introuvable ou illisible : "%s".', $file));

            return Command::FAILURE;
        }

        $body = (string) file_get_contents($file);
        if ('' === trim($body)) {
            $io->error('Le fichier de campagne est vide.');

            return Command::FAILURE;
        }

        $subscribers = $this->subscriberRepository->findBy(['isActive' => true]);
        if ($limit > 0) {
            $subscribers = array_slice($subscribers, 0, $limit);
        }

        if ([] === $subscribers) {
            $io->warning('Aucun abonné actif : rien à envoyer.');

            return Command::SUCCESS;
        }

        $io->title(sprintf(
            '%s de la campagne « %s » à %d abonné(s)',
            $dryRun ? 'Simulation' : 'Envoi',
            $subject,
            count($subscribers)
        ));

        $sent = 0;
        $failed = [];
        $io->progressStart(count($subscribers));

        foreach ($subscribers as $subscriber) {
            /** @var NewsletterSubscriber $subscriber */
            if (!$dryRun) {
                try {
                    $this->mailService->sendNewsletterCampaign($subscriber, $subject, $body);
                } catch (\Throwable $e) {
                    $failed[] = sprintf('%s (%s)', $subscriber->getEmail(), $e->getMessage());
                    $io->progressAdvance();

                    continue;
                }
            }
            ++$sent;
            $io->progressAdvance();
            usleep(100000); // ~10 emails/seconde max : ménager le SMTP
        }

        $io->progressFinish();

        if ([] !== $failed) {
            $io->warning(sprintf('%d échec(s) :', count($failed)));
            $io->listing($failed);
        }

        $io->success(sprintf('%d email(s) %s.', $sent, $dryRun ? 'simulé(s)' : 'envoyé(s)'));

        return Command::SUCCESS;
    }
}
