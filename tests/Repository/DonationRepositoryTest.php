<?php

namespace App\Tests\Repository;

use App\Entity\Donation;
use App\Repository\DonationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class DonationRepositoryTest extends TestCase
{
    private DonationRepository $repository;
    private ManagerRegistry $registry;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        
        // Configurer le mock du registry
        $this->registry
            ->method('getManagerForClass')
            ->willReturn($this->em);
        
        $classMetadata = $this->createMock(ClassMetadata::class);
        
        $this->registry
            ->method('getManager')
            ->willReturn($this->em);
        
        $this->registry
            ->method('getRepository')
            ->willReturn($this->repository ?? null);
        
        $this->repository = new DonationRepository($this->registry);
    }

    public function testGetPaidTotalForYear(): void
    {
        // Créer des données de test
        $year = 2024;
        
        // Configurer le QueryBuilder mock
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('select')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        // Cas 1: Total avec des dons
        $query
            ->method('getSingleScalarResult')
            ->willReturn(15000); // 150,00€ en centimes
        
        $result = $this->repository->getPaidTotalForYear($year);
        
        $this->assertEquals(15000, $result);
        
        // Cas 2: Aucun don
        $query
            ->method('getSingleScalarResult')
            ->willReturn(null);
        
        $result = $this->repository->getPaidTotalForYear($year);
        
        $this->assertEquals(0, $result);
    }

    public function testGetPaidTotalForYearWithNoResults(): void
    {
        $year = 2025;
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('select')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getSingleScalarResult')
            ->willReturn(null);
        
        $result = $this->repository->getPaidTotalForYear($year);
        
        $this->assertEquals(0, $result);
    }

    public function testFindPaidForYear(): void
    {
        $year = 2024;
        
        // Créer des dons mocks
        $donation1 = $this->createMock(Donation::class);
        $donation2 = $this->createMock(Donation::class);
        $expectedDonations = [$donation1, $donation2];
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('orderBy')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getResult')
            ->willReturn($expectedDonations);
        
        $result = $this->repository->findPaidForYear($year);
        
        $this->assertCount(2, $result);
        $this->assertSame($donation1, $result[0]);
        $this->assertSame($donation2, $result[1]);
    }

    public function testFindPaidForYearWithNoResults(): void
    {
        $year = 2025;
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('orderBy')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getResult')
            ->willReturn([]);
        
        $result = $this->repository->findPaidForYear($year);
        
        $this->assertEmpty($result);
        $this->assertIsArray($result);
    }

    public function testFindPaidForYearOrdersByDateDescending(): void
    {
        $year = 2024;
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        // Vérifier que orderBy est appelé avec DESC
        $queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('d.createdAt', 'DESC');
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getResult')
            ->willReturn([]);
        
        $this->repository->findPaidForYear($year);
    }

    public function testFindPaidForYearFiltersByStatus(): void
    {
        $year = 2024;
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        // Vérifier que le filtre sur paymentStatus est appliqué
        $queryBuilder
            ->expects($this->atLeastOnce())
            ->method('setParameter')
            ->with('status', Donation::STATUS_PAID);
        
        $queryBuilder
            ->method('orderBy')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getResult')
            ->willReturn([]);
        
        $this->repository->findPaidForYear($year);
    }

    public function testGetPaidTotalForYearFiltersByStatus(): void
    {
        $year = 2024;
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('select')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        // Vérifier que le filtre sur paymentStatus est appliqué
        $queryBuilder
            ->expects($this->atLeastOnce())
            ->method('setParameter')
            ->with('status', Donation::STATUS_PAID);
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getSingleScalarResult')
            ->willReturn(0);
        
        $this->repository->getPaidTotalForYear($year);
    }

    public function testGetPaidTotalForYearFiltersByDateRange(): void
    {
        $year = 2024;
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('select')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        // Vérifier que les paramètres de date sont corrects
        $queryBuilder
            ->expects($this->exactly(3)) // start, end, status
            ->method('setParameter');
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getSingleScalarResult')
            ->willReturn(0);
        
        $this->repository->getPaidTotalForYear($year);
    }
}
