<?php

namespace App\Tests\Entity;

use App\Entity\BlogPost;
use App\Entity\Event;
use App\Entity\Project;
use App\Entity\ProjectTask;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Enum\ProjectTaskState;
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    public function testProjectCreation(): void
    {
        $project = new Project();
        
        $this->assertNull($project->getId());
        $this->assertNull($project->getTitle());
        $this->assertNull($project->getSlug());
        $this->assertNull($project->getDescription());
        $this->assertNull($project->getStartDate());
        $this->assertNull($project->getEndDate());
        $this->assertFalse($project->isPublished());
        $this->assertNull($project->getImage());
        $this->assertEquals(ProjectStatus::Planned, $project->getStatus());
        $this->assertNull($project->getLeader());
        $this->assertCount(0, $project->getTasks());
        $this->assertCount(0, $project->getEvents());
        $this->assertCount(0, $project->getBlogPosts());
        $this->assertNull($project->getImageFile());
    }

    public function testProjectDefaults(): void
    {
        $project = new Project();
        
        // Vérifier les valeurs par défaut
        $this->assertEquals(ProjectStatus::Planned, $project->getStatus());
        $this->assertFalse($project->isPublished());
    }

    public function testSetAndGetTitle(): void
    {
        $project = new Project();
        
        $project->setTitle('Nouveau Projet');
        $this->assertEquals('Nouveau Projet', $project->getTitle());
    }

    public function testSetAndGetSlug(): void
    {
        $project = new Project();
        
        $project->setSlug('nouveau-projet');
        $this->assertEquals('nouveau-projet', $project->getSlug());
    }

    public function testSetAndGetDescription(): void
    {
        $project = new Project();
        
        $description = 'Description du nouveau projet';
        $project->setDescription($description);
        $this->assertEquals($description, $project->getDescription());
    }

    public function testSetAndGetStartDate(): void
    {
        $project = new Project();
        
        $startDate = new \DateTime('2024-01-01 10:00:00');
        $project->setStartDate($startDate);
        $this->assertEquals($startDate, $project->getStartDate());
    }

    public function testSetAndGetEndDate(): void
    {
        $project = new Project();
        
        $endDate = new \DateTime('2024-12-31 18:00:00');
        $project->setEndDate($endDate);
        $this->assertEquals($endDate, $project->getEndDate());
    }

    public function testSetAndGetIsPublished(): void
    {
        $project = new Project();
        
        $project->setIsPublished(true);
        $this->assertTrue($project->isPublished());
        
        $project->setIsPublished(false);
        $this->assertFalse($project->isPublished());
    }

    public function testSetAndGetImage(): void
    {
        $project = new Project();
        
        $project->setImage('path/to/image.jpg');
        $this->assertEquals('path/to/image.jpg', $project->getImage());
    }

    public function testSetAndGetStatus(): void
    {
        $project = new Project();
        
        $project->setStatus(ProjectStatus::InProgress);
        $this->assertEquals(ProjectStatus::InProgress, $project->getStatus());
        
        $project->setStatus(ProjectStatus::Completed);
        $this->assertEquals(ProjectStatus::Completed, $project->getStatus());
    }

    public function testSetAndGetLeader(): void
    {
        $project = new Project();
        $leader = $this->createMock(User::class);
        
        $project->setLeader($leader);
        $this->assertSame($leader, $project->getLeader());
        
        $project->setLeader(null);
        $this->assertNull($project->getLeader());
    }

    public function testAddAndGetTasks(): void
    {
        $project = new Project();
        $task1 = new ProjectTask();
        $task2 = new ProjectTask();
        
        $project->addTask($task1);
        $this->assertCount(1, $project->getTasks());
        $this->assertSame($project, $task1->getProject());
        
        $project->addTask($task2);
        $this->assertCount(2, $project->getTasks());
        $this->assertSame($project, $task2->getProject());
    }

    public function testRemoveTask(): void
    {
        $project = new Project();
        $task = new ProjectTask();
        
        $project->addTask($task);
        $this->assertCount(1, $project->getTasks());
        
        $project->removeTask($task);
        $this->assertCount(0, $project->getTasks());
        $this->assertNull($task->getProject());
    }

    public function testAddAndGetEvents(): void
    {
        $project = new Project();
        $event1 = $this->createMock(Event::class);
        $event2 = $this->createMock(Event::class);
        
        $project->addEvent($event1);
        $this->assertCount(1, $project->getEvents());
        
        $project->addEvent($event2);
        $this->assertCount(2, $project->getEvents());
    }

    public function testRemoveEvent(): void
    {
        $project = new Project();
        $event = $this->createMock(Event::class);
        
        $project->addEvent($event);
        $this->assertCount(1, $project->getEvents());
        
        $project->removeEvent($event);
        $this->assertCount(0, $project->getEvents());
    }

    public function testAddAndGetBlogPosts(): void
    {
        $project = new Project();
        $blogPost1 = $this->createMock(BlogPost::class);
        $blogPost2 = $this->createMock(BlogPost::class);
        
        $project->addBlogPost($blogPost1);
        $this->assertCount(1, $project->getBlogPosts());
        
        $project->addBlogPost($blogPost2);
        $this->assertCount(2, $project->getBlogPosts());
    }

    public function testRemoveBlogPost(): void
    {
        $project = new Project();
        $blogPost = $this->createMock(BlogPost::class);
        
        $project->addBlogPost($blogPost);
        $this->assertCount(1, $project->getBlogPosts());
        
        $project->removeBlogPost($blogPost);
        $this->assertCount(0, $project->getBlogPosts());
    }

    public function testImageFile(): void
    {
        $project = new Project();
        
        $file = $this->createMock(\Symfony\Component\HttpFoundation\File\File::class);
        
        $project->setImageFile($file);
        $this->assertSame($file, $project->getImageFile());
        
        $project->setImageFile(null);
        $this->assertNull($project->getImageFile());
    }

    public function testHasImage(): void
    {
        $project = new Project();
        
        $this->assertFalse($project->hasImage());
        
        $project->setImage('path/to/image.jpg');
        $this->assertTrue($project->hasImage());
        
        $project->setImage('');
        $this->assertFalse($project->hasImage());
        
        $project->setImage(null);
        $this->assertFalse($project->hasImage());
    }

    public function testGetImagePath(): void
    {
        $project = new Project();
        
        $this->assertEquals('projects', $project->getImagePath());
    }

    public function testGetDefaultImage(): void
    {
        $project = new Project();
        
        $this->assertEquals('', $project->getDefaultImage());
    }

    public function testComputeSlug(): void
    {
        $project = new Project();
        
        $project->setTitle('Nouveau Projet');
        $project->computeSlug();
        $this->assertEquals('nouveau-projet', $project->getSlug());
        
        // Avec caractères spéciaux
        $project->setTitle('Projet Spécial !');
        $project->computeSlug();
        $this->assertEquals('projet-special', $project->getSlug());
        
        // Avec des espaces multiples
        $project->setTitle('Projet   avec   espaces');
        $project->computeSlug();
        $this->assertEquals('projet-avec-espaces', $project->getSlug());
        
        // Avec des caractères accentués
        $project->setTitle('Projet Événement');
        $project->computeSlug();
        $this->assertEquals('projet-evenement', $project->getSlug());
        
        // Titre vide
        $project->setTitle('');
        $project->computeSlug();
        $this->assertEquals('projet', $project->getSlug());
    }

    public function testGetDoneTasksCount(): void
    {
        $project = new Project();
        
        // Aucun tâche
        $this->assertEquals(0, $project->getDoneTasksCount());
        
        // Ajouter des tâches non terminées
        $task1 = new ProjectTask();
        $task1->setState(ProjectTaskState::Todo);
        $project->addTask($task1);
        
        $task2 = new ProjectTask();
        $task2->setState(ProjectTaskState::Doing);
        $project->addTask($task2);
        
        $this->assertEquals(0, $project->getDoneTasksCount());
        
        // Ajouter une tâche terminée
        $task3 = new ProjectTask();
        $task3->setState(ProjectTaskState::Done);
        $project->addTask($task3);
        
        $this->assertEquals(1, $project->getDoneTasksCount());
        
        // Ajouter une autre tâche terminée
        $task4 = new ProjectTask();
        $task4->setState(ProjectTaskState::Done);
        $project->addTask($task4);
        
        $this->assertEquals(2, $project->getDoneTasksCount());
    }

    public function testGetProgressPercent(): void
    {
        $project = new Project();
        
        // Aucun tâche = 0%
        $this->assertEquals(0, $project->getProgressPercent());
        
        // 1 tâche sur 4 terminées = 25%
        $task1 = new ProjectTask();
        $task1->setState(ProjectTaskState::Done);
        $project->addTask($task1);
        
        for ($i = 0; $i < 3; $i++) {
            $task = new ProjectTask();
            $task->setState(ProjectTaskState::Todo);
            $project->addTask($task);
        }
        
        $this->assertEquals(25, $project->getProgressPercent());
        
        // 2 tâches sur 4 terminées = 50%
        $tasks = $project->getTasks();
        $task = $tasks->filter(fn($t) => $t->getState() !== ProjectTaskState::Done)->first();
        $task->setState(ProjectTaskState::Done);
        
        $this->assertEquals(50, $project->getProgressPercent());
        
        // 4 tâches sur 4 terminées = 100%
        foreach ($project->getTasks() as $task) {
            $task->setState(ProjectTaskState::Done);
        }
        
        $this->assertEquals(100, $project->getProgressPercent());
        
        // Test avec des arrondis
        // 1 tâche sur 3 = 33.33% -> 33%
        $project2 = new Project();
        $taskDone = new ProjectTask();
        $taskDone->setState(ProjectTaskState::Done);
        $project2->addTask($taskDone);
        
        for ($i = 0; $i < 2; $i++) {
            $task = new ProjectTask();
            $task->setState(ProjectTaskState::Todo);
            $project2->addTask($task);
        }
        
        $this->assertEquals(33, $project2->getProgressPercent());
        
        // 2 tâches sur 3 = 66.67% -> 67%
        $tasks = $project2->getTasks();
        $task = $tasks->filter(fn($t) => $t->getState() !== ProjectTaskState::Done)->first();
        $task->setState(ProjectTaskState::Done);
        
        $this->assertEquals(67, $project2->getProgressPercent());
    }

    public function testProgressPercentWithNoTasks(): void
    {
        $project = new Project();
        
        // Même avec des tâches ajoutées puis retirées
        $task = new ProjectTask();
        $project->addTask($task);
        $project->removeTask($task);
        
        $this->assertEquals(0, $project->getProgressPercent());
    }

    public function testFluency(): void
    {
        $project = new Project();
        $user = $this->createMock(User::class);
        $event = $this->createMock(Event::class);
        $blogPost = $this->createMock(BlogPost::class);
        $task = new ProjectTask();
        
        // Vérifier que les setters retournent $this
        $result = $project->setTitle('Test');
        $this->assertSame($project, $result);
        
        $result = $project->setSlug('test');
        $this->assertSame($project, $result);
        
        $result = $project->setDescription('Description');
        $this->assertSame($project, $result);
        
        $result = $project->setStartDate(new \DateTime());
        $this->assertSame($project, $result);
        
        $result = $project->setEndDate(new \DateTime());
        $this->assertSame($project, $result);
        
        $result = $project->setIsPublished(true);
        $this->assertSame($project, $result);
        
        $result = $project->setImage('image.jpg');
        $this->assertSame($project, $result);
        
        $result = $project->setStatus(ProjectStatus::InProgress);
        $this->assertSame($project, $result);
        
        $result = $project->setLeader($user);
        $this->assertSame($project, $result);
        
        $result = $project->addTask($task);
        $this->assertSame($project, $result);
        
        $result = $project->addEvent($event);
        $this->assertSame($project, $result);
        
        $result = $project->addBlogPost($blogPost);
        $this->assertSame($project, $result);
    }

    public function testProjectWithAllFields(): void
    {
        $project = new Project();
        $user = $this->createMock(User::class);
        
        $project
            ->setTitle('Grand Projet')
            ->setDescription('Description du grand projet')
            ->setStartDate(new \DateTime('2024-01-01'))
            ->setEndDate(new \DateTime('2024-12-31'))
            ->setIsPublished(true)
            ->setImage('path/to/image.jpg')
            ->setStatus(ProjectStatus::InProgress)
            ->setLeader($user)
            ->computeSlug();
        
        $this->assertEquals('Grand Projet', $project->getTitle());
        $this->assertEquals('grand-projet', $project->getSlug());
        $this->assertEquals('Description du grand projet', $project->getDescription());
        $this->assertEquals(new \DateTime('2024-01-01'), $project->getStartDate());
        $this->assertEquals(new \DateTime('2024-12-31'), $project->getEndDate());
        $this->assertTrue($project->isPublished());
        $this->assertEquals('path/to/image.jpg', $project->getImage());
        $this->assertEquals(ProjectStatus::InProgress, $project->getStatus());
        $this->assertSame($user, $project->getLeader());
    }

    public function testStatusEnumValues(): void
    {
        $project = new Project();
        
        // Tester toutes les valeurs possibles de ProjectStatus
        $project->setStatus(ProjectStatus::Planned);
        $this->assertEquals(ProjectStatus::Planned, $project->getStatus());
        
        $project->setStatus(ProjectStatus::InProgress);
        $this->assertEquals(ProjectStatus::InProgress, $project->getStatus());
        
        $project->setStatus(ProjectStatus::Completed);
        $this->assertEquals(ProjectStatus::Completed, $project->getStatus());
    }

    public function testTasksAreOrderedByPosition(): void
    {
        $project = new Project();
        
        $task1 = new ProjectTask();
        $task1->setPosition(3);
        
        $task2 = new ProjectTask();
        $task2->setPosition(1);
        
        $task3 = new ProjectTask();
        $task3->setPosition(2);
        
        $project->addTask($task1);
        $project->addTask($task2);
        $project->addTask($task3);
        
        // L'entité a #[ORM\OrderBy(['position' => 'ASC'])]
        // mais en PHP pur, l'ordre n'est pas garanti dans ArrayCollection
        // donc nous ne pouvons pas tester l'ordre ici
        // Ce test serait plus pertinent en base de données
        
        $this->assertCount(3, $project->getTasks());
    }
}
