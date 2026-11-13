<?php

namespace App\Tests\Repository;

use App\Entity\ProjectTask;
use App\Repository\ProjectTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ProjectTaskRepositoryTest extends TestCase
{
    private ProjectTaskRepository $repository;
    private ManagerRegistry $registry;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        
        $this->registry
            ->method('getManagerForClass')
            ->willReturn($this->em);
        
        $this->registry
            ->method('getManager')
            ->willReturn($this->em);
        
        $this->repository = new ProjectTaskRepository($this->registry);
    }

    public function testGetNextPositionWithNoTasks(): void
    {
        $projectId = 1;
        
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
        
        // MAX(...) retourne null quand il n'y a pas de résultats
        $query
            ->method('getSingleScalarResult')
            ->willReturn(null);
        
        $result = $this->repository->getNextPosition($projectId);
        
        // null + 1 = 1 (car cast en int)
        $this->assertEquals(1, $result);
    }

    public function testGetNextPositionWithExistingTasks(): void
    {
        $projectId = 1;
        
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
        
        // MAX(...) retourne 5 (il y a 5 tâches avec positions 0-5)
        $query
            ->method('getSingleScalarResult')
            ->willReturn(5);
        
        $result = $this->repository->getNextPosition($projectId);
        
        $this->assertEquals(6, $result);
    }

    public function testGetNextPositionWithMaxAtZero(): void
    {
        $projectId = 1;
        
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
        
        // MAX(...) retourne 0 (toutes les tâches sont à position 0)
        $query
            ->method('getSingleScalarResult')
            ->willReturn(0);
        
        $result = $this->repository->getNextPosition($projectId);
        
        $this->assertEquals(1, $result);
    }

    public function testGetNextPositionWithNegativeMax(): void
    {
        $projectId = 1;
        
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
        
        // MAX(...) retourne -5 (cas bizarre mais possible)
        $query
            ->method('getSingleScalarResult')
            ->willReturn(-5);
        
        $result = $this->repository->getNextPosition($projectId);
        
        // -5 + 1 = -4
        $this->assertEquals(-4, $result);
    }

    public function testGetNextPositionSelectsMaxPosition(): void
    {
        $projectId = 123;
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Vérifier que select est appelé avec MAX(t.position)
        $queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('MAX(t.position)');
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->with('projectId', $projectId)
            ->willReturnSelf();
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getSingleScalarResult')
            ->willReturn(10);
        
        $this->repository->getNextPosition($projectId);
    }

    public function testGetNextPositionFiltersByProject(): void
    {
        $projectId = 456;
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->em
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('select')
            ->willReturnSelf();
        
        // Vérifier que andWhere filtre par project
        $queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('t.project = :projectId');
        
        $queryBuilder
            ->method('setParameter')
            ->with('projectId', $projectId)
            ->willReturnSelf();
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getSingleScalarResult')
            ->willReturn(0);
        
        $this->repository->getNextPosition($projectId);
    }

    public function testGetNextPositionSetsParameter(): void
    {
        $projectId = 789;
        
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
        
        // Vérifier que setParameter est appelé avec le bon paramètre
        $queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('projectId', $projectId);
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getSingleScalarResult')
            ->willReturn(0);
        
        $this->repository->getNextPosition($projectId);
    }

    public function testGetNextPositionReturnsInt(): void
    {
        $projectId = 1;
        
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
            ->willReturn(100);
        
        $result = $this->repository->getNextPosition($projectId);
        
        $this->assertIsInt($result);
        $this->assertEquals(101, $result);
    }

    public function testGetNextPositionWithStringMax(): void
    {
        $projectId = 1;
        
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
        
        // MAX(...) retourne une chaîne
        $query
            ->method('getSingleScalarResult')
            ->willReturn('5');
        
        $result = $this->repository->getNextPosition($projectId);
        
        // Le cast en int convertit '5' en 5, puis +1 = 6
        $this->assertEquals(6, $result);
    }
}
