<?php

namespace App\Service;

use App\Entity\ProjectTask;
use App\Entity\ProjectTaskDocument;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gère le stockage des documents rattachés aux tâches de projet.
 *
 * Les fichiers sont stockés hors de public/, dans var/task_documents/{taskId}/,
 * et ne sont accessibles que via la route de téléchargement authentifiée.
 * Les fichiers historiques présents dans public/uploads/task_documents/
 * restent lisibles (retour arrière) tant qu'ils n'ont pas été migrés.
 */
class ProjectTaskDocumentManager
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Répertoire de stockage privé d'une tâche.
     */
    private function storageDir(int|string $taskId): string
    {
        return $this->projectDir . '/var/task_documents/' . $taskId;
    }

    /**
     * Répertoire public historique d'une tâche (avant migration).
     */
    private function legacyDir(int|string $taskId): string
    {
        return $this->projectDir . '/public/uploads/task_documents/' . $taskId;
    }

    /**
     * Stocke les fichiers uploadés d'une tâche dans var/task_documents/{taskId}/
     * et crée les entités associées (non persistées : à persister par l'appelant).
     *
     * @param UploadedFile[] $files
     * @param User|null      $uploader Utilisateur connecté ayant envoyé les fichiers
     *
     * @return ProjectTaskDocument[] Documents créés
     */
    public function upload(ProjectTask $task, array $files, ?User $uploader): array
    {
        $documents = [];

        if ([] === $files) {
            return $documents;
        }

        $uploadDir = $this->storageDir($task->getId());
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($files as $file) {
            // IMPORTANT : lire toutes les infos AVANT move(),
            // car le fichier temporaire disparaît après le déplacement.
            $originalName = $file->getClientOriginalName() ?? 'document';
            $mimeType = $file->getMimeType() ?? 'application/octet-stream';
            $size = (int) $file->getSize();

            $safeName = strtolower(
                $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME))
                . '-' . uniqid()
                . '.' . ($file->guessExtension() ?? 'bin')
            );

            $file->move($uploadDir, $safeName);

            $document = new ProjectTaskDocument();
            $document->setTask($task)
                ->setFileName($safeName)
                ->setOriginalName($originalName)
                ->setMimeType($mimeType)
                ->setSize($size)
                ->setUploadedBy($uploader);

            $task->addDocument($document);
            $documents[] = $document;
        }

        return $documents;
    }

    /**
     * Chemin absolu du fichier d'un document : priorité au répertoire
     * privé var/, avec repli sur l'emplacement public historique.
     */
    public function absolutePath(ProjectTaskDocument $document): ?string
    {
        $taskId = $document->getTask()?->getId();
        $fileName = $document->getFileName();

        if ($taskId === null || $fileName === null) {
            return null;
        }

        $privatePath = $this->storageDir($taskId) . '/' . $fileName;
        if (is_file($privatePath)) {
            return $privatePath;
        }

        $legacyPath = $this->legacyDir($taskId) . '/' . $fileName;
        if (is_file($legacyPath)) {
            return $legacyPath;
        }

        return null;
    }

    /**
     * Supprime le fichier d'un document sur le disque (l'entité reste
     * à supprimer par l'appelant).
     */
    public function remove(ProjectTaskDocument $document): void
    {
        $path = $this->absolutePath($document);
        if ($path !== null) {
            unlink($path);
        }
    }

    /**
     * Supprime tous les fichiers d'une tâche sur le disque.
     */
    public function removeAll(ProjectTask $task): void
    {
        foreach ($task->getDocuments() as $document) {
            $this->remove($document);
        }
    }

    /**
     * Réponse de téléchargement (streaming) d'un document.
     *
     * Le fichier est servi avec le nom d'origine, sans jamais exposer
     * son chemin de stockage.
     */
    public function downloadResponse(ProjectTaskDocument $document): BinaryFileResponse
    {
        $path = $this->absolutePath($document);
        if ($path === null) {
            throw $this->createFileNotFoundException($document);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getOriginalName() ?? $document->getFileName() ?? 'document'
        );

        return $response;
    }

    /**
     * Fabrique une NotFoundHttpException (sans dépendre du contrôleur).
     */
    private function createFileNotFoundException(ProjectTaskDocument $document): \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
    {
        return new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
            'Le fichier du document #' . $document->getId() . ' est introuvable sur le disque.'
        );
    }
}
