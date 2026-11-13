<?php

namespace App\Tests\Entity;

use App\Entity\ProjectTask;
use App\Entity\ProjectTaskDocument;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ProjectTaskDocumentTest extends TestCase
{
    public function testDocumentCreation(): void
    {
        $document = new ProjectTaskDocument();
        
        $this->assertNull($document->getId());
        $this->assertNull($document->getTask());
        $this->assertNull($document->getFileName());
        $this->assertNull($document->getOriginalName());
        $this->assertNull($document->getMimeType());
        $this->assertNull($document->getSize());
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getUploadedAt());
        $this->assertNull($document->getUploadedBy());
    }

    public function testDocumentDefaults(): void
    {
        $document = new ProjectTaskDocument();
        
        // uploadedAt est initialisé dans le constructeur
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getUploadedAt());
    }

    public function testSetAndGetTask(): void
    {
        $document = new ProjectTaskDocument();
        $task = new ProjectTask();
        
        $document->setTask($task);
        $this->assertSame($task, $document->getTask());
        
        $document->setTask(null);
        $this->assertNull($document->getTask());
    }

    public function testSetAndGetFileName(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setFileName('unique-file-name-12345.jpg');
        $this->assertEquals('unique-file-name-12345.jpg', $document->getFileName());
    }

    public function testSetAndGetOriginalName(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setOriginalName('Mon Document Original.pdf');
        $this->assertEquals('Mon Document Original.pdf', $document->getOriginalName());
    }

    public function testSetAndGetMimeType(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setMimeType('application/pdf');
        $this->assertEquals('application/pdf', $document->getMimeType());
    }

    public function testSetAndGetSize(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setSize(1024);
        $this->assertEquals(1024, $document->getSize());
        
        $document->setSize(2048);
        $this->assertEquals(2048, $document->getSize());
    }

    public function testSetAndGetUploadedAt(): void
    {
        $document = new ProjectTaskDocument();
        $date = new \DateTimeImmutable('2024-01-15 10:30:00');
        
        $document->setUploadedAt($date);
        $this->assertEquals($date, $document->getUploadedAt());
    }

    public function testSetAndGetUploadedBy(): void
    {
        $document = new ProjectTaskDocument();
        $user = $this->createMock(User::class);
        
        $document->setUploadedBy($user);
        $this->assertSame($user, $document->getUploadedBy());
        
        $document->setUploadedBy(null);
        $this->assertNull($document->getUploadedBy());
    }

    public function testGetWebPath(): void
    {
        $document = new ProjectTaskDocument();
        $task = new ProjectTask();
        
        // Configurer un ID pour la tâche
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 123);
        
        $document->setTask($task);
        $document->setFileName('document.pdf');
        
        $expectedPath = 'uploads/task_documents/123/document.pdf';
        $this->assertEquals($expectedPath, $document->getWebPath());
    }

    public function testGetWebPathWithoutTask(): void
    {
        $document = new ProjectTaskDocument();
        $document->setFileName('document.pdf');
        
        // Sans task, getId() retourne null, qui est casté en chaîne vide
        // donc le chemin sera "//document.pdf"
        $this->assertEquals('uploads/task_documents//document.pdf', $document->getWebPath());
    }

    public function testGetWebPathWithoutFileName(): void
    {
        $document = new ProjectTaskDocument();
        $task = new ProjectTask();
        
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 123);
        
        $document->setTask($task);
        
        // Sans fileName, le chemin se termine par null
        $this->assertStringContainsString('uploads/task_documents/123/', $document->getWebPath());
    }

    public function testGetIconForImages(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setMimeType('image/jpeg');
        $this->assertEquals('bi-file-image', $document->getIcon());
        
        $document->setMimeType('image/png');
        $this->assertEquals('bi-file-image', $document->getIcon());
        
        $document->setMimeType('image/svg+xml');
        $this->assertEquals('bi-file-image', $document->getIcon());
    }

    public function testGetIconForPdf(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setMimeType('application/pdf');
        $this->assertEquals('bi-file-pdf', $document->getIcon());
    }

    public function testGetIconForVideos(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setMimeType('video/mp4');
        $this->assertEquals('bi-file-play', $document->getIcon());
        
        $document->setMimeType('video/webm');
        $this->assertEquals('bi-file-play', $document->getIcon());
    }

    public function testGetIconForAudio(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setMimeType('audio/mp3');
        $this->assertEquals('bi-file-music', $document->getIcon());
        
        $document->setMimeType('audio/wav');
        $this->assertEquals('bi-file-music', $document->getIcon());
    }

    public function testGetIconForOtherFiles(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setMimeType('text/plain');
        $this->assertEquals('bi-file-earmark', $document->getIcon());
        
        $document->setMimeType('application/json');
        $this->assertEquals('bi-file-earmark', $document->getIcon());
        
        $document->setMimeType('application/zip');
        $this->assertEquals('bi-file-earmark', $document->getIcon());
    }

    public function testGetIconWithNullMimeType(): void
    {
        $document = new ProjectTaskDocument();
        // mimeType est de type string et non nullable, donc on ne peut pas le setter à null
        // On va juste tester avec une chaîne vide
        $document->setMimeType('');
        
        // str_starts_with avec une chaîne vide retourne false, donc ça retourne la valeur par défaut
        $this->assertEquals('bi-file-earmark', $document->getIcon());
    }

    public function testGetFormattedSizeInBytes(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setSize(500);
        $this->assertEquals('500 o', $document->getFormattedSize());
        
        $document->setSize(1);
        $this->assertEquals('1 o', $document->getFormattedSize());
    }

    public function testGetFormattedSizeInKilobytes(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setSize(1024);
        $this->assertEquals('1 Ko', $document->getFormattedSize());
        
        $document->setSize(1536);
        $this->assertEquals('1.5 Ko', $document->getFormattedSize());
    }

    public function testGetFormattedSizeInMegabytes(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setSize(1024 * 1024);
        $this->assertEquals('1 Mo', $document->getFormattedSize());
        
        $document->setSize(1024 * 1024 * 2.5);
        $this->assertEquals('2.5 Mo', $document->getFormattedSize());
    }

    public function testGetFormattedSizeInGigabytes(): void
    {
        $document = new ProjectTaskDocument();
        
        $document->setSize(1024 * 1024 * 1024);
        $this->assertEquals('1 Go', $document->getFormattedSize());
    }

    public function testGetFormattedSizeWithZero(): void
    {
        $document = new ProjectTaskDocument();
        $document->setSize(0);
        
        $this->assertEquals('0 o', $document->getFormattedSize());
    }

    public function testDocumentWithAllFields(): void
    {
        $document = new ProjectTaskDocument();
        $task = new ProjectTask();
        $user = $this->createMock(User::class);
        
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, 123);
        
        $date = new \DateTimeImmutable('2024-01-15 10:30:00');
        
        $document
            ->setTask($task)
            ->setFileName('unique-name.pdf')
            ->setOriginalName('Document Original.pdf')
            ->setMimeType('application/pdf')
            ->setSize(1024 * 1024 * 2) // 2 Mo
            ->setUploadedAt($date)
            ->setUploadedBy($user);
        
        $this->assertSame($task, $document->getTask());
        $this->assertEquals('unique-name.pdf', $document->getFileName());
        $this->assertEquals('Document Original.pdf', $document->getOriginalName());
        $this->assertEquals('application/pdf', $document->getMimeType());
        $this->assertEquals(1024 * 1024 * 2, $document->getSize());
        $this->assertEquals($date, $document->getUploadedAt());
        $this->assertSame($user, $document->getUploadedBy());
        $this->assertEquals('uploads/task_documents/123/unique-name.pdf', $document->getWebPath());
        $this->assertEquals('bi-file-pdf', $document->getIcon());
        $this->assertEquals('2 Mo', $document->getFormattedSize());
    }

    public function testFluency(): void
    {
        $document = new ProjectTaskDocument();
        $task = new ProjectTask();
        $user = $this->createMock(User::class);
        $date = new \DateTimeImmutable();
        
        // Vérifier que les setters retournent $this
        $result = $document->setTask($task);
        $this->assertSame($document, $result);
        
        $result = $document->setFileName('file.jpg');
        $this->assertSame($document, $result);
        
        $result = $document->setOriginalName('Original.jpg');
        $this->assertSame($document, $result);
        
        $result = $document->setMimeType('image/jpeg');
        $this->assertSame($document, $result);
        
        $result = $document->setSize(1024);
        $this->assertSame($document, $result);
        
        $result = $document->setUploadedAt($date);
        $this->assertSame($document, $result);
        
        $result = $document->setUploadedBy($user);
        $this->assertSame($document, $result);
    }

    public function testUploadedAtIsInitialized(): void
    {
        $document = new ProjectTaskDocument();
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getUploadedAt());
        
        // Vérifier que la date est proche de maintenant
        $now = new \DateTimeImmutable();
        $diff = $now->diff($document->getUploadedAt());
        
        // La différence devrait être de quelques secondes maximum
        $this->assertLessThanOrEqual(5, $diff->s);
    }

    public function testSetUploadedAtChangesDate(): void
    {
        $document = new ProjectTaskDocument();
        $originalDate = $document->getUploadedAt();
        
        $newDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $document->setUploadedAt($newDate);
        
        $this->assertEquals($newDate, $document->getUploadedAt());
        $this->assertNotEquals($originalDate, $document->getUploadedAt());
    }
}
