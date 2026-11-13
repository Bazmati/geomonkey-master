<?php

namespace App\Tests\Service;

use App\Entity\ProjectTask;
use App\Entity\ProjectTaskDocument;
use App\Service\ProjectTaskDocumentManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Tests unitaires du gestionnaire de documents de tâches,
 * avec un répertoire temporaire jouant le rôle du projet.
 */
class ProjectTaskDocumentManagerTest extends TestCase
{
    private string $projectDir;
    private ProjectTaskDocumentManager $manager;
    /** @var string[] Répertoires à nettoyer */
    private array $cleanupDirs = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/gm-doc-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0775, true);
        $this->cleanupDirs[] = $this->projectDir;

        $this->manager = new ProjectTaskDocumentManager(
            $this->projectDir,
            new AsciiSlugger()
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->cleanupDirs = [];
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }

    /**
     * Crée une tâche avec un identifiant (sans base de données).
     */
    private function createTask(int $id): ProjectTask
    {
        $task = new ProjectTask();
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setValue($task, $id);

        return $task;
    }

    /**
     * Crée un vrai fichier temporaire puis un UploadedFile en mode test.
     */
    private function createUploadedFile(string $originalName, string $content, string $mime): UploadedFile
    {
        $path = $this->projectDir . '/source-' . uniqid() . '.tmp';
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, $mime, null, true);
    }

    public function testUploadStoresFileInPrivateVarDirectory(): void
    {
        $task = $this->createTask(123);
        $file = $this->createUploadedFile('Rapport Annuel.pdf', '%PDF-fake', 'application/pdf');

        $documents = $this->manager->upload($task, [$file], null);

        $this->assertCount(1, $documents);
        $document = $documents[0];

        // Le fichier est bien stocké dans var/, PAS dans public/
        $storageDir = $this->projectDir . '/var/task_documents/123';
        $this->assertDirectoryExists($storageDir);
        $stored = $storageDir . '/' . $document->getFileName();
        $this->assertFileExists($stored);
        $this->assertSame('%PDF-fake', file_get_contents($stored));
        $this->assertFileDoesNotExist(
            $this->projectDir . '/public/uploads/task_documents/123/' . $document->getFileName()
        );

        // Entité correctement renseignée
        // NB : getMimeType() est deviné par finfo depuis le contenu réel,
        // on vérifie donc juste sa présence, pas la valeur exacte.
        $this->assertSame($task, $document->getTask());
        $this->assertSame('Rapport Annuel.pdf', $document->getOriginalName());
        $this->assertIsString($document->getMimeType());
        $this->assertNotSame('', $document->getMimeType());
        $this->assertSame(9, $document->getSize());
        $this->assertSame($document, $task->getDocuments()->first());
        $this->assertMatchesRegularExpression('/^rapport-annuel-[a-f0-9]+\.[a-z0-9]+$/', $document->getFileName());
    }

    public function testUploadWithEmptyArrayReturnsNoDocuments(): void
    {
        $task = $this->createTask(1);

        $this->assertSame([], $this->manager->upload($task, [], null));
        $this->assertFileDoesNotExist($this->projectDir . '/var/task_documents/1');
    }

    public function testRemoveDeletesFileFromDisk(): void
    {
        $task = $this->createTask(123);
        $file = $this->createUploadedFile('Doc.txt', 'hello', 'text/plain');

        [$document] = $this->manager->upload($task, [$file], null);
        $stored = $this->projectDir . '/var/task_documents/123/' . $document->getFileName();
        $this->assertFileExists($stored);

        $this->manager->remove($document);
        $this->assertFileDoesNotExist($stored);
    }

    public function testAbsolutePathFallsBackToLegacyPublicPath(): void
    {
        // Document historique encore dans public/uploads/
        $task = $this->createTask(123);
        $legacyDir = $this->projectDir . '/public/uploads/task_documents/123';
        mkdir($legacyDir, 0775, true);
        file_put_contents($legacyDir . '/ancien.pdf', 'legacy');

        $document = new ProjectTaskDocument();
        $document->setTask($task)->setFileName('ancien.pdf');

        $this->assertSame($legacyDir . '/ancien.pdf', $this->manager->absolutePath($document));
    }

    public function testAbsolutePathPrefersPrivatePathWhenBothExist(): void
    {
        $task = $this->createTask(123);
        $privateDir = $this->projectDir . '/var/task_documents/123';
        mkdir($privateDir, 0775, true);
        file_put_contents($privateDir . '/doc.pdf', 'new');

        $document = new ProjectTaskDocument();
        $document->setTask($task)->setFileName('doc.pdf');

        $this->assertSame($privateDir . '/doc.pdf', $this->manager->absolutePath($document));
    }

    public function testAbsolutePathReturnsNullWhenFileMissing(): void
    {
        $task = $this->createTask(999);
        $document = new ProjectTaskDocument();
        $document->setTask($task)->setFileName('introuvable.pdf');

        $this->assertNull($this->manager->absolutePath($document));
    }

    public function testDownloadResponseStreamsOriginalName(): void
    {
        $task = $this->createTask(123);
        $file = $this->createUploadedFile('Budget 2027.pdf', '%PDF-1.4', 'application/pdf');

        [$document] = $this->manager->upload($task, [$file], null);

        $response = $this->manager->downloadResponse($document);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('%PDF-1.4', $response->getFile()->getContent());
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('Budget 2027.pdf', $disposition);
    }

    public function testDownloadResponseThrowsNotFoundWhenFileMissing(): void
    {
        $task = $this->createTask(1);
        $document = new ProjectTaskDocument();
        $document->setTask($task)->setFileName('gone.pdf');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->manager->downloadResponse($document);
    }

    public function testRemoveAllDeletesEveryDocumentOfTask(): void
    {
        $task = $this->createTask(123);
        $files = [
            $this->createUploadedFile('A.txt', 'aaa', 'text/plain'),
            $this->createUploadedFile('B.txt', 'bbb', 'text/plain'),
        ];

        $documents = $this->manager->upload($task, $files, null);
        $this->assertCount(2, $documents);

        $this->manager->removeAll($task);

        foreach ($documents as $document) {
            $this->assertFileDoesNotExist(
                $this->projectDir . '/var/task_documents/123/' . $document->getFileName()
            );
        }
    }
}
