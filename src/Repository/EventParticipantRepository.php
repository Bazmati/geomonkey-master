<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use App\Enum\ParticipantRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventParticipant>
 *
 * @method EventParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventParticipant[]    findAll()
 * @method EventParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventParticipant::class);
    }

    /**
     * Vérifie si un utilisateur participe à un événement
     */
    public function userParticipatesInEvent(User $user, Event $event): bool
    {
        return $this->createQueryBuilder('ep')
            ->where('ep.user = :user')
            ->andWhere('ep.event = :event')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    /**
     * Trouve tous les événements auxquels un utilisateur participe
     * @return Event[]
     */
    public function findEventsByUser(User $user): array
    {
        return $this->createQueryBuilder('ep')
            ->select('e')
            ->join('ep.event', 'e')
            ->where('ep.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les participants d'un événement
     * @return EventParticipant[]
     */
    public function findParticipantsByEvent(Event $event): array
    {
        return $this->findBy(['event' => $event], ['registeredAt' => 'ASC']);
    }

    /**
     * Compte le nombre de participants à un événement
     */
    public function countParticipants(Event $event): int
    {
        return $this->createQueryBuilder('ep')
            ->select('COUNT(ep.user)')
            ->where('ep.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre d'événements auxquels un utilisateur participe
     */
    public function countEventsByUser(User $user): int
    {
        return $this->createQueryBuilder('ep')
            ->select('COUNT(DISTINCT ep.event)')
            ->where('ep.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les participants avec présence confirmée
     * @return EventParticipant[]
     */
    public function findAttendeesByEvent(Event $event): array
    {
        return $this->createQueryBuilder('ep')
            ->where('ep.event = :event')
            ->andWhere('ep.attended = :attended')
            ->setParameter('event', $event)
            ->setParameter('attended', true)
            ->orderBy('ep.registeredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les organisateurs d'un événement
     * @return User[]
     */
    public function findOrganizersByEvent(Event $event): array
    {
        return $this->createQueryBuilder('ep')
            ->select('u')
            ->join('ep.user', 'u')
            ->where('ep.event = :event')
            ->andWhere('ep.role = :role')
            ->setParameter('event', $event)
            ->setParameter('role', ParticipantRole::Organizer)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur est organisateur d'un événement
     */
    public function isUserOrganizerOfEvent(User $user, Event $event): bool
    {
        return $this->createQueryBuilder('ep')
            ->where('ep.user = :user')
            ->andWhere('ep.event = :event')
            ->andWhere('ep.role = :role')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->setParameter('role', ParticipantRole::Organizer)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    /**
     * Supprime tous les participants d'un événement
     */
    public function removeAllParticipants(Event $event): int
    {
        return $this->createQueryBuilder('ep')
            ->delete()
            ->where('ep.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve la date d'inscription d'un utilisateur à un événement
     */
    public function getRegistrationDate(User $user, Event $event): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('ep')
            ->select('ep.registeredAt')
            ->where('ep.user = :user')
            ->andWhere('ep.event = :event')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->getQuery()
            ->getOneOrNullResult();

        return $result?->getRegisteredAt();
    }

    /**
     * Vérifie si un utilisateur participe à l'un des événements donnés.
     * Optimisé pour le voter GalleryImageVoter qui doit vérifier plusieurs événements.
     * 
     * @param User $user L'utilisateur à vérifier
     * @param Event[] $events Tableau d'événements à vérifier
     * @return bool True si l'utilisateur participe à au moins un des événements
     */
    public function userParticipatesIn(User $user, array $events): bool
    {
        if ($events === []) {
            return false;
        }

        return (bool) $this->createQueryBuilder('ep')
            ->select('COUNT(ep.event)')
            ->where('ep.user = :user')
            ->andWhere('ep.event IN (:events)')
            ->setParameter('user', $user)
            ->setParameter('events', $events)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}