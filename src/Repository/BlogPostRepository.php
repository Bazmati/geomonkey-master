<?php

namespace App\Repository;

use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogPost>
 */
class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    public function findPublishedPosts(): array
    {
        return $this->findBy(['isPublished' => true], ['createdAt' => 'DESC']);
    }

    public function findRecentPosts(int $limit = 5): array
    {
        return $this->findBy(['isPublished' => true], ['createdAt' => 'DESC'], $limit);
    }

    /**
     * Un seul article publié par son slug — le front ne doit JAMAIS
     * servir un brouillon, même avec le slug deviné.
     */
    public function findOnePublishedBySlug(string $slug): ?BlogPost
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.slug = :slug')
            ->andWhere('b.isPublished = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}