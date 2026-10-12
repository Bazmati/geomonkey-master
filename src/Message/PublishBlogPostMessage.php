<?php
// src/Message/PublishBlogPostMessage.php

namespace App\Message;

/**
 * Publication asynchrone d'un article sur les réseaux sociaux.
 * Transporté par Messenger : l'utilisateur qui met en ligne
 * n'attend jamais les appels d'API.
 */
final class PublishBlogPostMessage
{
    public function __construct(
        public readonly int $blogPostId,
        public readonly string $publicBaseUrl,
    ) {}
}