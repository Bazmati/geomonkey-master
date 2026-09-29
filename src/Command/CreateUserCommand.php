<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\OfficeFunction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un utilisateur avec rôle et fonction de bureau.',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Rôle ROLE_ADMIN')
            ->addOption('president', 'p', InputOption::VALUE_NONE, 'Fonction Président')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe (sinon demandé)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = new User();   // le constructeur initialise déjà createdAt
        $user->setEmail($input->getArgument('email'));

        $user->setFirstName($io->ask('Prénom'));
        $user->setLastName($io->ask('Nom'));

        $plainPassword = $input->getOption('password')
            ?? $io->askHidden('Mot de passe pour ' . $user->getEmail());

        $user->setPassword(
            $this->hasher->hashPassword($user, $plainPassword)
        );

        $user->setRoles($input->getOption('admin') ? ['ROLE_ADMIN'] : ['ROLE_MEMBER']);
        $user->setOfficeFunction($input->getOption('president') ? OfficeFunction::President : OfficeFunction::None);

        $this->em->persist($user);
        $this->em->flush();

        $io->success('Utilisateur ' . $user->getEmail() . ' créé !');

        return Command::SUCCESS;
    }
}