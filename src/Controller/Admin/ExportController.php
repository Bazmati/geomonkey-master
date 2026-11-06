<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\Membership;
use App\Repository\EventParticipantRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\CsvExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/export', name: 'app_admin_export_')]
class ExportController extends AbstractController
{
    public function __construct(private CsvExporter $csv) {}

    #[Route('/users', name: 'users', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function users(Request $request, UserRepository $users, CsvExporter $csv): StreamedResponse
    {
        // ?members=1 -> uniquement les adhérents actifs (liste de diffusion, assemblée générale)
        $membersOnly = $request->query->getBoolean('members');

        $all = $users->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC']);
        $records = $membersOnly
            ? array_filter($all, fn ($u) => $u->hasActiveMembership())
            : $all;

        return $csv->response(
            ($membersOnly ? 'membres_actifs_' : 'utilisateurs_') . (new \DateTimeImmutable())->format('Y-m-d') . '.csv',
            ['ID', 'Nom', 'Prénom', 'Email', 'Fonction', 'Rôle', 'Adhésion valide jusqu\'au', 'Inscrit le'],
            $records,
            fn ($u) => [
                $u->getId(),
                $u->getLastName(),
                $u->getFirstName(),
                $u->getEmail(),
                $u->getOfficeFunction()->value,
                \in_array('ROLE_ADMIN', $u->getRoles(), true) ? 'admin' : 'membre',
                $u->getMembershipValidUntil()?->format('d/m/Y') ?? '',
                $u->getCreatedAt()?->format('d/m/Y') ?? '',
            ]
        );
    }

    #[Route('/memberships', name: 'memberships', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function memberships(MembershipRepository $memberships, CsvExporter $csv): StreamedResponse
    {
        /** @var list<Membership> $records */
        $records = $memberships->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->orderBy('m.startedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $csv->response(
            'adhesions_' . (new \DateTimeImmutable())->format('Y-m-d') . '.csv',
            ['ID', 'Nom', 'Prénom', 'Email', 'Début', 'Expiration', 'Montant (€)', 'Statut paiement', 'Active'],
            $records,
            fn (Membership $m) => [
                $m->getId(),
                $m->getUser()?->getLastName(),
                $m->getUser()?->getFirstName(),
                $m->getUser()?->getEmail(),
                $m->getStartedAt()->format('d/m/Y'),
                $m->getExpiresAt()->format('d/m/Y'),
                \number_format($m->getAmountInEuros(), 2, ',', ''),
                $m->getPaymentStatus() ?? '',
                $m->isActive() ? 'oui' : 'non',
            ]
        );
    }

    #[Route('/event/{id}/participants', name: 'event_participants', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function eventParticipants(Event $event, EventParticipantRepository $participants, CsvExporter $csv): StreamedResponse
    {
        $records = $participants->findParticipantsByEvent($event);

        return $csv->response(
            'participants_' . $event->getId() . '_' . (new \DateTimeImmutable())->format('Y-m-d') . '.csv',
            ['Nom', 'Prénom', 'Email', 'Rôle', 'Inscrit le', 'Présent', 'Notes'],
            $records,
            fn ($p) => [
                $p->getUser()->getLastName(),
                $p->getUser()->getFirstName(),
                $p->getUser()->getEmail(),
                $p->getRole()->value,
                $p->getRegisteredAt()->format('d/m/Y H:i'),
                $p->hasAttended() ? 'oui' : 'non',
                $p->getNotes() ?? '',
            ]
        );
    }
}