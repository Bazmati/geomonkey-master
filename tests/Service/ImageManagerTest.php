<?php

namespace App\Tests\Service;

use App\Entity\Event;
use App\Service\ImageManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageManagerTest extends TestCase
{
    private ImageManager $imageManager;
    private string $publicDir;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/geomonkey_test_uploads';
        $this->tempDir = $this->publicDir . '/uploads';
        
        // Clean up and recreate test directory
        if (is_dir($this->publicDir)) {
            $this->removeDirectory($this->publicDir);
        }
        mkdir($this->publicDir, 0755, true);
        mkdir($this->tempDir, 0755, true);
        
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

    private function createTestImage(string $name = 'test.jpg'): UploadedFile
    {
        $imageContent = file_get_contents(__DIR__ . '/../../public/images/test-image.jpg') ?: 
                       base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgo//wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAr/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwC77//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwC77//xAArEAEAAAAAAAAAAAAAAAAAAAAA/9o=');
        
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_img');
        file_put_contents($tmpFile, $imageContent);
        
        return new UploadedFile(
            $tmpFile,
            $name,
            'image/jpeg',
            null,
            true
        );
    }

    public function testUploadForNewEntityWithoutId(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setStartDate(new \DateTime('2030-11-21 12:00:00'));
        $event->setEndDate(new \DateTime('2030-11-22 12:00:00'));
        $event->setLocation('Test Location');
        $event->setIsPublished(true);
        
        // Verify entity has no ID
        $this->assertNull($event->getId());
        
        // Store original dates for comparison
        $originalStartDate = clone $event->getStartDate();
        $originalEndDate = clone $event->getEndDate();
        
        // Create a test image file
        $imageFile = $this->createTestImage();
        
        // Upload image for new entity (no ID yet)
        $this->imageManager->upload($event, $imageFile);
        
        // Verify dates have NOT changed
        $this->assertSame(
            $originalStartDate->format('Y-m-d H:i:s'),
            $event->getStartDate()->format('Y-m-d H:i:s'),
            'Start date should not change after upload for new entity'
        );
        
        $this->assertSame(
            $originalEndDate->format('Y-m-d H:i:s'),
            $event->getEndDate()->format('Y-m-d H:i:s'),
            'End date should not change after upload for new entity'
        );
        
        // Verify image UUID was set
        $this->assertNotNull($event->getImage());
        $this->assertIsString($event->getImage());
        
        // Verify files were created in temp directory
        $basePath = $this->publicDir . '/uploads/events/temp';
        $this->assertTrue(is_dir($basePath), 'Temp directory should be created');
        
        foreach (['small', 'medium', 'large'] as $size) {
            $filePath = $basePath . '/' . $size . '/' . $event->getImage() . '.webp';
            $this->assertTrue(file_exists($filePath), "File should exist in temp/{$size}/ directory");
        }
    }

    public function testFinalizeUploadMovesFilesToCorrectLocation(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setImage('test-uuid-123');
        
        // Create temp directory structure manually
        $tempPath = $this->publicDir . '/uploads/events/temp';
        foreach (['small', 'medium', 'large'] as $size) {
            $dir = $tempPath . '/' . $size;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/test-uuid-123.webp', 'test content');
        }
        
        // Simulate entity getting an ID after persist
        $reflection = new \ReflectionClass($event);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($event, 42);
        
        // Verify entity now has an ID
        $this->assertSame(42, $event->getId());
        
        // Finalize upload (move from temp to actual ID directory)
        $this->imageManager->finalizeUpload($event);
        
        // Verify files were moved
        foreach (['small', 'medium', 'large'] as $size) {
            $oldPath = $tempPath . '/' . $size . '/test-uuid-123.webp';
            $newPath = $this->publicDir . '/uploads/events/42/' . $size . '/test-uuid-123.webp';
            
            $this->assertFalse(file_exists($oldPath), "File should be removed from temp/{$size}/");
            $this->assertTrue(file_exists($newPath), "File should exist in 42/{$size}/");
        }
        
        // Verify temp directory was cleaned up
        $this->assertFalse(is_dir($tempPath), 'Temp directory should be removed');
    }

    public function testUploadForExistingEntityWithId(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setStartDate(new \DateTime('2030-11-21 12:00:00'));
        $event->setEndDate(new \DateTime('2030-11-22 12:00:00'));
        $event->setLocation('Test Location');
        $event->setIsPublished(true);
        $event->setImage('old-uuid-456');
        
        // Simulate existing entity with ID
        $reflection = new \ReflectionClass($event);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($event, 1);
        
        // Store original dates
        $originalStartDate = clone $event->getStartDate();
        $originalEndDate = clone $event->getEndDate();
        
        // Create a test image file
        $imageFile = $this->createTestImage();
        
        // Upload image for existing entity
        $this->imageManager->upload($event, $imageFile);
        
        // Verify dates have NOT changed
        $this->assertSame(
            $originalStartDate->format('Y-m-d H:i:s'),
            $event->getStartDate()->format('Y-m-d H:i:s'),
            'Start date should not change after upload for existing entity'
        );
        
        $this->assertSame(
            $originalEndDate->format('Y-m-d H:i:s'),
            $event->getEndDate()->format('Y-m-d H:i:s'),
            'End date should not change after upload for existing entity'
        );
        
        // Verify old image was marked for deletion (files deleted)
        $this->assertNotSame('old-uuid-456', $event->getImage(), 'Image UUID should be changed');
        
        // Verify new files were created
        $basePath = $this->publicDir . '/uploads/events/1';
        foreach (['small', 'medium', 'large'] as $size) {
            $filePath = $basePath . '/' . $size . '/' . $event->getImage() . '.webp';
            $this->assertTrue(file_exists($filePath), "New file should exist in 1/{$size}/ directory");
        }
    }
}
