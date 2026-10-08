<?php

namespace App\Tests\Service;

use App\Entity\Event;
use App\Service\ImageManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageManagerTest extends TestCase
{
    private ImageManager $imageManager;
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/geomonkey_test_uploads';
        
        // Clean up and recreate test directory
        if (is_dir($this->publicDir)) {
            $this->removeDirectory($this->publicDir);
        }
        mkdir($this->publicDir, 0755, true);
        
        $this->imageManager = new ImageManager($this->publicDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->publicDir)) {
            $this->removeDirectory($this->publicDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // ==================== SECURITY TESTS ====================

    public function testUploadRejectsNonImageFile(): void
    {
        // Create a PHP file disguised as an image
        $phpContent = '<?php system($_GET["cmd"]); ?>';
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_php');
        file_put_contents($tmpFile, $phpContent);
        
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'malicious.php.jpg',
            'image/jpeg',
            null,
            true
        );

        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');

        $this->expectException(FileException::class);
        $this->imageManager->upload($event, $uploadedFile);
    }

    public function testUploadRejectsLargeFile(): void
    {
        // Create a file larger than 5MB
        $largeContent = str_repeat('A', 6 * 1024 * 1024);
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_large');
        file_put_contents($tmpFile, $largeContent);
        
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'large.jpg',
            'image/jpeg',
            null,
            true
        );

        $event = new Event();
        $event->setTitle('Test Event');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Fichier trop volumineux');
        $this->imageManager->upload($event, $uploadedFile);
    }

    public function testUploadRejectsUnallowedExtension(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_ext');
        file_put_contents($tmpFile, 'test');
        
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'test.php',
            'image/jpeg',
            null,
            true
        );

        $event = new Event();
        $event->setTitle('Test Event');

        $this->expectException(FileException::class);
        // The real MIME type will be detected as text/plain, so it will fail on MIME type check
        $this->expectExceptionMessageMatches('/Type de fichier non autoris|Extension de fichier non autoris/');
        $this->imageManager->upload($event, $uploadedFile);
    }

    public function testUploadRejectsInvalidMimeType(): void
    {
        $textContent = 'This is not an image';
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_txt');
        file_put_contents($tmpFile, $textContent);
        
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'test.jpg',
            'text/plain',
            null,
            true
        );

        $event = new Event();
        $event->setTitle('Test Event');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Type de fichier non autoris');
        $this->imageManager->upload($event, $uploadedFile);
    }

    public function testUploadAcceptsValidImage(): void
    {
        // Use logo.png which exists
        $imagePath = __DIR__ . '/../../public/images/logo.png';
        $this->assertFileExists($imagePath, 'Test image logo.png should exist');
        
        $imageContent = file_get_contents($imagePath);
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_valid_img');
        file_put_contents($tmpFile, $imageContent);
        
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'test.png',
            'image/png',
            null,
            true
        );

        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        
        // Simulate existing entity with ID
        $reflection = new \ReflectionClass($event);
        $property = $reflection->getProperty('id');
        // setAccessible is deprecated in PHP 8.5+ but still works
        $property->setValue($event, 1);

        // This should not throw an exception
        $this->imageManager->upload($event, $uploadedFile);
        
        // Verify image UUID was set
        $this->assertNotNull($event->getImage());
        $this->assertIsString($event->getImage());
    }

    public function testGetAvailableSizes(): void
    {
        $sizes = $this->imageManager->getAvailableSizes();
        
        $this->assertIsArray($sizes);
        $this->assertContains('small', $sizes);
        $this->assertContains('medium', $sizes);
        $this->assertContains('large', $sizes);
    }

    public function testIsValidSize(): void
    {
        $this->assertTrue($this->imageManager->isValidSize('small'));
        $this->assertTrue($this->imageManager->isValidSize('medium'));
        $this->assertTrue($this->imageManager->isValidSize('large'));
        $this->assertFalse($this->imageManager->isValidSize('extra-large'));
    }

    public function testDeleteForEntityWithoutImage(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        
        // Entity has no image
        $this->assertNull($event->getImage());
        
        // This should not throw an exception
        $this->imageManager->deleteForEntity($event);
    }

    public function testDeleteForEntityWithoutId(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setImage('test-uuid-123');
        
        // Entity has no ID
        $this->assertNull($event->getId());
        
        // This should not throw an exception
        $this->imageManager->deleteForEntity($event);
    }

        // ==================== PIPELINE V4 TESTS ====================

    public function testUploadCreatesThreeWebpSizes(): void
    {
        $imagePath = __DIR__ . '/../../public/images/logo.png';
        $this->assertFileExists($imagePath);
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_sizes');
        file_put_contents($tmpFile, file_get_contents($imagePath));

        $uploadedFile = new UploadedFile($tmpFile, 'test.png', 'image/png', null, true);

        $event = new Event();
        $event->setTitle('Test');
        $event->setDescription('Test description');
        $r = new \ReflectionClass($event);
        $r->getProperty('id')->setValue($event, 77);
        // L'entité doit avoir un image non-null (sinon le temp path)
        $event->setImage('pending-test');

        $this->imageManager->upload($event, $uploadedFile);

        $uuid = $event->getImage();
        $this->assertDoesNotMatchRegularExpression('/^pending-/', $uuid, 'upload() doit remplacer le placeholder par l\'UUID réel');

        foreach (['small', 'medium', 'large'] as $size) {
            $path = "{$this->publicDir}/uploads/events/77/{$size}/{$uuid}.webp";
            $this->assertFileExists($path, "La taille {$size} doit exister en .webp");
            $this->assertSame('webp', strtolower(pathinfo($path, PATHINFO_EXTENSION)));
        }
    }

    public function testGetUrlReturnsWebpPath(): void
    {
        $event = new Event();
        $event->setTitle('T');
        $event->setDescription('D');
        $r = new \ReflectionClass($event);
        $r->getProperty('id')->setValue($event, 88);
        $event->setImage('url-test-uuid');

        // pas de fichier : getUrl doit retourner null
        $this->assertNull($this->imageManager->getUrl($event, 'medium'));

        // on crée le fichier : getUrl doit le trouver
        $dir = "{$this->publicDir}/uploads/events/88/medium";
        mkdir($dir, 0755, true);
        file_put_contents("{$dir}/url-test-uuid.webp", 'fake');

        $this->assertSame('/uploads/events/88/medium/url-test-uuid.webp', $this->imageManager->getUrl($event, 'medium'));
    }

    public function testDeleteForEntityDeletesOnlyGivenUuid(): void
    {
        // simule 2 images pour la même entité (id 99)
        $dir = "{$this->publicDir}/uploads/gallery/99/medium";
        mkdir($dir, 0755, true);
        file_put_contents("{$dir}/aaa.webp", 'a');
        file_put_contents("{$dir}/bbb.webp", 'b');

        $image = new \App\Entity\GalleryImage();
        $image->setTitle('T');
        $image->setAlt('A');
        $image->setImage('bbb'); // l'entité pointe sur bbb
        $r = new \ReflectionClass($image);
        $r->getProperty('id')->setValue($image, 99);

        // suppression ciblée sur aaa : bbb doit survivre
        $this->imageManager->deleteForEntity($image, 'aaa');

        $this->assertFileDoesNotExist("{$dir}/aaa.webp");
        $this->assertFileExists("{$dir}/bbb.webp");
    }

    public function testDeleteForEntityIgnoresPendingPlaceholder(): void
    {
        $image = new \App\Entity\GalleryImage();
        $image->setTitle('T');
        $image->setAlt('A');
        $image->setImage('pending-xyz');
        $r = new \ReflectionClass($image);
        $r->getProperty('id')->setValue($image, 100);

        $dir = "{$this->publicDir}/uploads/gallery/100/small";
        mkdir($dir, 0755, true);
        file_put_contents("{$dir}/real.webp", 'x');

        // Ne doit rien supprimer (pas de fichiers pending sur disque)
        $this->imageManager->deleteForEntity($image);

        $this->assertFileExists("{$dir}/real.webp");
    }
}
