<?php

namespace App\Tests\Repository;

use App\Entity\Project;
use App\Entity\ProjectTask;
use App\Enum\ProjectStatus;
use App\Enum\ProjectTaskState;
use App\Repository\ProjectTaskRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration de ProjectTaskRepository sur la vraie BDD de test
 * (rollback automatique par DAMADoctrineTestBundle).
 */
class ProjectTaskRepositoryTest extends KernelTestCase
{
    private ProjectTaskRepository $repository;
    private \Doctrine\ORM\EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = static::getContainer()->get(ProjectTaskRepository::class);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    private function createProject(string $slug): Project
    {
        $project = new Project();
        $project->setTitle('Projet ' . $slug);
        $project->setSlug($slug);
        $project->setDescription('Description ' . $slug);
        $project->setStatus(ProjectStatus::InProgress);
        $project->setIsPublished(true);
        $this->em->persist($project);

        return $project;
    }

    private function createTask(Project $project, string $title, int $position): ProjectTask
    {
        $task = new ProjectTask();
        $task->setProject($project);
        $task->setTitle($title);
        $task->setState(ProjectTaskState::Todo);
        $task->setPosition($position);
        $this->em->persist($task);

        return $task;
    }

    public function testGetNextPositionWithNoTasks(): void
    {
        $project = $this->createProject('projet-vide');
        $this->em->flush();

        $this->assertSame(1, $this->repository->getNextPosition($project->getId()));
    }

    public function testGetNextPositionWithExistingTasks(): void
    {
        $project = $this->createProject('projet-plein');
        $this->createTask($project, 'Tâche 1', 1);
        $this->createTask($project, 'Tâche 2', 2);
        $this->createTask($project, 'Tâche 3', 5);
        $this->em->flush();

        $this->assertSame(6, $this->repository->getNextPosition($project->getId()), 'MAX(position) + 1, pas COUNT + 1');
    }

    public function testGetNextPositionFiltersByProject(): void
    {
        $projectA = $this->createProject('projet-a');
        $projectB = $this->createProject('projet-b');
        $this->createTask($projectB, 'Tâche B1', 10);
        $this->createTask($projectB, 'Tâche B2', 20);
        $this->em->flush();

        $this->assertSame(1, $this->repository->getNextPosition($projectA->getId()), 'Les tâches d\'un autre projet ne comptent pas');
        $this->assertSame(21, $this->repository->getNextPosition($projectB->getId()));
    }

    public function testGetNextPositionReturnsInt(): void
    {
        $project = $this->createProject('projet-int');
        $this->createTask($project, 'Tâche', 100);
        $this->em->flush();

        $result = $this->repository->getNextPosition($project->getId());

        $this->assertIsInt($result);
        $this->assertSame(101, $result);
    }
}
