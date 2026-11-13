<?php

namespace App\Tests\Entity;

use App\Entity\GalleryImage;
use App\Entity\Project;
use App\Entity\ProjectTask;
use App\Entity\ProjectTaskDocument;
use App\Entity\User;
use App\Enum\ProjectTaskState;
use PHPUnit\Framework\TestCase;

class ProjectTaskTest extends TestCase
{
    public function testTaskCreation(): void
    {
        $task = new ProjectTask();
        
        $this->assertNull($task->getId());
        $this->assertNull($task->getProject());
        $this->assertNull($task->getTitle());
        $this->assertNull($task->getDescription());
        $this->assertEquals(ProjectTaskState::Todo, $task->getState());
        $this->assertEquals(0, $task->getPosition());
        $this->assertNull($task->getAssignee());
        $this->assertCount(0, $task->getGalleryImages());
        $this->assertCount(0, $task->getDocuments());
    }

    public function testTaskDefaults(): void
    {
        $task = new ProjectTask();
        
        // Vérifier les valeurs par défaut
        $this->assertEquals(ProjectTaskState::Todo, $task->getState());
        $this->assertEquals(0, $task->getPosition());
    }

    public function testSetAndGetProject(): void
    {
        $task = new ProjectTask();
        $project = $this->createMock(Project::class);
        
        $task->setProject($project);
        $this->assertSame($project, $task->getProject());
        
        $task->setProject(null);
        $this->assertNull($task->getProject());
    }

    public function testSetAndGetTitle(): void
    {
        $task = new ProjectTask();
        
        $task->setTitle('Nouvelle tâche');
        $this->assertEquals('Nouvelle tâche', $task->getTitle());
    }

    public function testSetAndGetDescription(): void
    {
        $task = new ProjectTask();
        
        $description = 'Description de la tâche';
        $task->setDescription($description);
        $this->assertEquals($description, $task->getDescription());
        
        $task->setDescription(null);
        $this->assertNull($task->getDescription());
    }

    public function testSetAndGetState(): void
    {
        $task = new ProjectTask();
        
        $task->setState(ProjectTaskState::Doing);
        $this->assertEquals(ProjectTaskState::Doing, $task->getState());
        
        $task->setState(ProjectTaskState::Done);
        $this->assertEquals(ProjectTaskState::Done, $task->getState());
    }

    public function testSetAndGetPosition(): void
    {
        $task = new ProjectTask();
        
        $task->setPosition(5);
        $this->assertEquals(5, $task->getPosition());
        
        $task->setPosition(10);
        $this->assertEquals(10, $task->getPosition());
        
        $task->setPosition(0);
        $this->assertEquals(0, $task->getPosition());
    }

    public function testSetAndGetAssignee(): void
    {
        $task = new ProjectTask();
        $assignee = $this->createMock(User::class);
        
        $task->setAssignee($assignee);
        $this->assertSame($assignee, $task->getAssignee());
        
        $task->setAssignee(null);
        $this->assertNull($task->getAssignee());
    }

    public function testAddAndGetGalleryImages(): void
    {
        $task = new ProjectTask();
        $image1 = $this->createMock(GalleryImage::class);
        $image2 = $this->createMock(GalleryImage::class);
        
        $task->addGalleryImage($image1);
        $this->assertCount(1, $task->getGalleryImages());
        
        $task->addGalleryImage($image2);
        $this->assertCount(2, $task->getGalleryImages());
    }

    public function testRemoveGalleryImage(): void
    {
        $task = new ProjectTask();
        $image = $this->createMock(GalleryImage::class);
        
        $task->addGalleryImage($image);
        $this->assertCount(1, $task->getGalleryImages());
        
        $task->removeGalleryImage($image);
        $this->assertCount(0, $task->getGalleryImages());
    }

    public function testAddAndGetDocuments(): void
    {
        $task = new ProjectTask();
        $document1 = new ProjectTaskDocument();
        $document2 = new ProjectTaskDocument();
        
        $task->addDocument($document1);
        $this->assertCount(1, $task->getDocuments());
        $this->assertSame($task, $document1->getTask());
        
        $task->addDocument($document2);
        $this->assertCount(2, $task->getDocuments());
        $this->assertSame($task, $document2->getTask());
    }

    public function testRemoveDocument(): void
    {
        $task = new ProjectTask();
        $document = new ProjectTaskDocument();
        
        $task->addDocument($document);
        $this->assertCount(1, $task->getDocuments());
        
        $task->removeDocument($document);
        $this->assertCount(0, $task->getDocuments());
        $this->assertNull($document->getTask());
    }

    public function testTaskWithAllFields(): void
    {
        $task = new ProjectTask();
        $project = $this->createMock(Project::class);
        $assignee = $this->createMock(User::class);
        $image = $this->createMock(GalleryImage::class);
        $document = new ProjectTaskDocument();
        
        $task
            ->setProject($project)
            ->setTitle('Tâche complète')
            ->setDescription('Description complète')
            ->setState(ProjectTaskState::Done)
            ->setPosition(1)
            ->setAssignee($assignee)
            ->addGalleryImage($image)
            ->addDocument($document);
        
        $this->assertSame($project, $task->getProject());
        $this->assertEquals('Tâche complète', $task->getTitle());
        $this->assertEquals('Description complète', $task->getDescription());
        $this->assertEquals(ProjectTaskState::Done, $task->getState());
        $this->assertEquals(1, $task->getPosition());
        $this->assertSame($assignee, $task->getAssignee());
        $this->assertCount(1, $task->getGalleryImages());
        $this->assertCount(1, $task->getDocuments());
    }

    public function testStateEnumValues(): void
    {
        $task = new ProjectTask();
        
        // Tester toutes les valeurs possibles de ProjectTaskState
        $task->setState(ProjectTaskState::Todo);
        $this->assertEquals(ProjectTaskState::Todo, $task->getState());
        
        $task->setState(ProjectTaskState::Doing);
        $this->assertEquals(ProjectTaskState::Doing, $task->getState());
        
        $task->setState(ProjectTaskState::Done);
        $this->assertEquals(ProjectTaskState::Done, $task->getState());
    }

    public function testFluency(): void
    {
        $task = new ProjectTask();
        $project = $this->createMock(Project::class);
        $assignee = $this->createMock(User::class);
        $image = $this->createMock(GalleryImage::class);
        $document = new ProjectTaskDocument();
        
        // Vérifier que les setters retournent $this
        $result = $task->setProject($project);
        $this->assertSame($task, $result);
        
        $result = $task->setTitle('Test');
        $this->assertSame($task, $result);
        
        $result = $task->setDescription('Description');
        $this->assertSame($task, $result);
        
        $result = $task->setState(ProjectTaskState::Doing);
        $this->assertSame($task, $result);
        
        $result = $task->setPosition(5);
        $this->assertSame($task, $result);
        
        $result = $task->setAssignee($assignee);
        $this->assertSame($task, $result);
        
        $result = $task->addGalleryImage($image);
        $this->assertSame($task, $result);
        
        $result = $task->addDocument($document);
        $this->assertSame($task, $result);
    }

    public function testAddGalleryImageDoesNotAddDuplicate(): void
    {
        $task = new ProjectTask();
        $image = $this->createMock(GalleryImage::class);
        
        $task->addGalleryImage($image);
        $task->addGalleryImage($image);
        $task->addGalleryImage($image);
        
        $this->assertCount(1, $task->getGalleryImages());
    }

    public function testAddDocumentDoesNotAddDuplicate(): void
    {
        $task = new ProjectTask();
        $document = new ProjectTaskDocument();
        
        $task->addDocument($document);
        $task->addDocument($document);
        $task->addDocument($document);
        
        $this->assertCount(1, $task->getDocuments());
    }

    public function testPositionDefaultValue(): void
    {
        $task = new ProjectTask();
        
        $this->assertEquals(0, $task->getPosition());
        
        $task->setPosition(-5);
        $this->assertEquals(-5, $task->getPosition());
        
        $task->setPosition(1000);
        $this->assertEquals(1000, $task->getPosition());
    }

    public function testStateDefaultValue(): void
    {
        $task = new ProjectTask();
        
        $this->assertEquals(ProjectTaskState::Todo, $task->getState());
    }

    public function testTaskStateFlow(): void
    {
        $task = new ProjectTask();
        
        // Flux normal d'une tâche
        $this->assertEquals(ProjectTaskState::Todo, $task->getState());
        
        $task->setState(ProjectTaskState::Doing);
        $this->assertEquals(ProjectTaskState::Doing, $task->getState());
        
        $task->setState(ProjectTaskState::Done);
        $this->assertEquals(ProjectTaskState::Done, $task->getState());
    }

    public function testTaskCanGoBackToPreviousState(): void
    {
        $task = new ProjectTask();
        
        $task->setState(ProjectTaskState::Done);
        $this->assertEquals(ProjectTaskState::Done, $task->getState());
        
        // Peut revenir en arrière
        $task->setState(ProjectTaskState::Doing);
        $this->assertEquals(ProjectTaskState::Doing, $task->getState());
        
        $task->setState(ProjectTaskState::Todo);
        $this->assertEquals(ProjectTaskState::Todo, $task->getState());
    }
}
