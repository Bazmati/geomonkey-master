<?php

namespace App\Service;

use App\Entity\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * Génère un fichier calendrier (.ics) pour un événement.
 *
 * Format iCalendar (RFC 5545) : les lignes sont séparées par CRLF,
 * les caractères spéciaux (antislash, point-virgule, virgule, retours
 * à la ligne) sont échappés et les dates sont converties en UTC.
 */
class EventIcsGenerator
{
    /**
     * Échappe une valeur texte pour respecter la RFC 5545.
     */
    private function escape(?string $value): string
    {
        $value = (string) $value;

        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n'],
            $value
        );
    }

    /**
     * Formate une date en UTC au format iCalendar (Ymd\THis\Z).
     */
    private function formatUtc(?\DateTimeInterface $date): string
    {
        return null === $date
            ? ''
            : $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * Génère le contenu brut du fichier .ics pour un événement.
     *
     * @param string $url URL absolue de la page de l'événement
     */
    public function generate(Event $event, string $url): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GeoMonkey//Events//FR',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:event-' . $event->getId() . '@geomonkey',
            'DTSTAMP:' . (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z'),
            'DTSTART:' . $this->formatUtc($event->getStartDate()),
            'DTEND:' . $this->formatUtc($event->getEndDate()),
            'SUMMARY:' . $this->escape($event->getTitle()),
            'LOCATION:' . $this->escape($event->getLocation()),
            'DESCRIPTION:' . $this->escape($event->getDescription()),
            'URL:' . $url,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines);
    }

    /**
     * Nom de fichier proposé au téléchargement.
     */
    public function filename(Event $event): string
    {
        return 'evenement-' . $event->getId() . '.ics';
    }

    /**
     * Construit la réponse HTTP de téléchargement du fichier .ics.
     *
     * @param string $url URL absolue de la page de l'événement
     */
    public function response(Event $event, string $url): Response
    {
        $response = new Response($this->generate($event, $url));
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->filename($event) . '"');

        return $response;
    }
}
