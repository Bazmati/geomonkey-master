<?php

namespace App\EventListener;

use App\Interface\ImageableInterface;
use App\Service\ImageManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * Doctrine event listener for automatic image cleanup.
 * Handles image deletion when entities are updated or removed.
 */
class ImageUploadListener
{
    public function __construct(private ImageManager $imageManager) {}

    /**
     * Called before an entity is removed.
     * Deletes all associated images.
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof ImageableInterface) {
            $this->imageManager->deleteForEntity($entity);
        }
    }

    /**
     * Called before an entity is updated.
     * If the image field has changed, deletes the old images.
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if (!$entity instanceof ImageableInterface) {
            return;
        }

        // Check if image field is being changed
        if ($args->hasChangedField('image')) {
            $oldValue = $args->getOldValue('image');
            $newValue = $args->getNewValue('image');
            
            // If image is being removed (set to null)
            if ($oldValue !== null && $newValue === null) {
                $this->imageManager->deleteForEntity($entity);
            }
            // If image is being changed to a new value
            elseif ($oldValue !== null && $newValue !== null && $oldValue !== $newValue) {
                // The old images will be deleted when the new image is uploaded
                // via ImageManager::upload() which is called in the controller
            }
        }
    }
}
