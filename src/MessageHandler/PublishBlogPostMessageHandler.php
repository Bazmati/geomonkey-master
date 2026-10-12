<?php
// src/MessageHandler/PublishBlogPostMessageHandler.php

namespace App\MessageHandler;

use App\Entity\BlogPost;
use App\Message\PublishBlogPostMessage;
use App\Repository\BlogPostRepository;
use App\Service\SocialPublishService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PublishBlogPostMessageHandler
{
    public function __construct(
        private BlogPostRepository $repo,
        private SocialPublishService $socialPublish,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PublishBlogPostMessage $message): void
    {
        $post = $this->em->find(BlogPost::class, $message->blogPostId);
        if ($post === null) {
            $this->logger->warning('BlogPost {id} introuvable : publication sociale annulée.', ['id' => $message->blogPostId]);
            return;
        }

        // Re-vérification : l'article a pu être dépublié entre-temps
        if (!$post->isPublished()) {
            $this->logger->info('BlogPost {id} dépublié avant traitement : annulation.', ['id' => $message->blogPostId]);
            return;
        }

        $this->socialPublish->publishBlogPost($post, $message->publicBaseUrl);
    }
}