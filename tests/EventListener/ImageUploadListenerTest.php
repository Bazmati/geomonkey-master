<?php

namespace App\Tests\EventListener;

use App\Entity\GalleryImage;
use App\EventListener\ImageUploadListener;
use App\Service\ImageManager;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;

class ImageUploadListenerTest extends TestCase
{
    private ImageManager $imageManager;
    private ImageUploadListener $listener;
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/geomonkey_listener_test';
        if (is_dir($this->publicDir)) {
            $this->rrmdir($this->publicDir);
        }
        mkdir($this->publicDir, 0755, true);

        $this->imageManager = new ImageManager($this->publicDir);
        $this->listener = new ImageUploadListener($this->imageManager);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->publicDir)) {
            $this->rrmdir($this->publicDir);
        }
    }

    private function rrmdir(string $dir): void
    {
        foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
            is_dir("$dir/$f") ? $this->rrmdir("$dir/$f") : unlink("$dir/$f");
        }
        rmdir($dir);
    }

    /** Entité avec ID forcé (pas de DB en test unitaire) */
    private function makeImage(int $id, string $uuid): GalleryImage
    {
        $img = new GalleryImage();
        $img->setTitle('Test');
        $img->setAlt('Test alt');
        $img->setImage($uuid);
        $r = new \ReflectionClass($img);
        $r->getProperty('id')->setValue($img, $id);
        return $img;
    }

    /** Crée les 3 fichiers webp pour un UUID donné */
    private function createFakeFiles(int $id, string $uuid): void
    {
        foreach (['small', 'medium', 'large'] as $size) {
            $dir = "{$this->publicDir}/uploads/gallery/{$id}/{$size}";
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents("{$dir}/{$uuid}.webp", 'fake');
        }
    }

    /**
     * Mock minimal de PreUpdateEventArgs sans EntityManager.
     */
    private function makeArgs(object $entity, bool $changed, ?string $old, ?string $new): PreUpdateEventArgs
    {
        $args = (new \ReflectionClass(PreUpdateEventArgs::class))
            ->newInstanceWithoutConstructor();

        $stub = $this->createConfiguredMock(PreUpdateEventArgs::class, [
            'getObject' => $entity,
            'hasChangedField' => $changed,
            'getOldValue' => $old,
            'getNewValue' => $new,
        ]);
        // On ne peut pas re-mocker : on renvoie plutôt directement le stub configuré
        return $stub;
    }

    /**
     * 🎯 RÉGRESSION CRITIQUE : le remplacement pending- → uuid réel
     * ne doit PAS supprimer les fichiers du NOUVEL uuid.
     * (C'était le bug : les fichiers fraîchement écrits étaient effacés
     * par le preUpdate déclenché par le flush final.)
     */
    public function testPreUpdateWithPlaceholderDoesNotDeleteNewFiles(): void
    {
        $image = $this->makeImage(42, 'new-uuid-000');
        $this->createFakeFiles(42, 'new-uuid-000');

        $args = $this->makeArgs($image, true, 'pending-abc123', 'new-uuid-000');
        $this->listener->preUpdate($args);

        // Les fichiers du NOUVEL uuid doivent exister
        $this->assertFileExists("{$this->publicDir}/uploads/gallery/42/small/new-uuid-000.webp");
        $this->assertFileExists("{$this->publicDir}/uploads/gallery/42/large/new-uuid-000.webp");
    }

    /**
     * Le remplacement d'un vrai ancien uuid doit supprimer les ANCIENS
     * fichiers et conserver les nouveaux.
     */
    public function testPreUpdateReplacesOldUuid(): void
    {
        $image = $this->makeImage(42, 'new-uuid-111');
        $this->createFakeFiles(42, 'old-uuid-111');
        $this->createFakeFiles(42, 'new-uuid-111');

        $args = $this->makeArgs($image, true, 'old-uuid-111', 'new-uuid-111');
        $this->listener->preUpdate($args);

        $this->assertFileDoesNotExist("{$this->publicDir}/uploads/gallery/42/small/old-uuid-111.webp");
        $this->assertFileExists("{$this->publicDir}/uploads/gallery/42/small/new-uuid-111.webp");
    }

    public function testPreUpdateIgnoresNonImageableEntity(): void
    {
        $std = new \stdClass();
        $args = $this->makeArgs($std, true, 'a', 'b');
        $this->listener->preUpdate($args); // ne doit pas planter
        $this->addToAssertionCount(1);
    }

    public function testPreUpdateWithUnchangedValueDoesNothing(): void
    {
        $image = $this->makeImage(42, 'same-uuid');
        $this->createFakeFiles(42, 'same-uuid');

        $args = $this->makeArgs($image, true, 'same-uuid', 'same-uuid');
        $this->listener->preUpdate($args);

        $this->assertFileExists("{$this->publicDir}/uploads/gallery/42/medium/same-uuid.webp");
    }
}