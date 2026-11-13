<?php

namespace App\Tests\Service;

use App\Entity\Event;
use App\Service\EventIcsGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du générateur de fichiers .ics (aucune dépendance Symfony).
 */
class EventIcsGeneratorTest extends TestCase
{
    private function createEvent(): Event
    {
        $event = new Event();
        $event->setTitle('Réunion trimestrielle');
        $event->setDescription('Point sur les projets, avec plein ; de détails');
        $event->setLocation('Maison des associations, Lyon');
        $event->setStartDate(new \DateTime('2026-12-25 10:00:00', new \DateTimeZone('Europe/Paris')));
        $event->setEndDate(new \DateTime('2026-12-25 12:00:00', new \DateTimeZone('Europe/Paris')));

        return $event;
    }

    public function testGenerateProducesValidVcalendar(): void
    {
        $generator = new EventIcsGenerator();
        $content = $generator->generate($this->createEvent(), 'http://localhost/evenements/1');

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $content);
        $this->assertStringEndsWith("END:VCALENDAR", $content);
        $this->assertStringContainsString('VERSION:2.0', $content);
        $this->assertStringContainsString('PRODID:-//GeoMonkey//Events//FR', $content);
        $this->assertStringContainsString('CALSCALE:GREGORIAN', $content);
        $this->assertStringContainsString('BEGIN:VEVENT', $content);
        $this->assertStringContainsString('END:VEVENT', $content);
        $this->assertStringContainsString('URL:http://localhost/evenements/1', $content);
    }

    public function testGenerateEscapesSpecialCharacters(): void
    {
        $event = $this->createEvent();
        $event->setTitle('Virgule, point-virgule; antislash\\');
        $event->setDescription("Ligne 1\nLigne 2");

        $content = (new EventIcsGenerator())->generate($event, 'http://localhost/x');

        $this->assertStringContainsString('SUMMARY:Virgule\\, point-virgule\\; antislash\\\\', $content);
        $this->assertStringContainsString("DESCRIPTION:Ligne 1\\nLigne 2", $content);
    }

    public function testGenerateFormatsDatesInUtc(): void
    {
        $content = (new EventIcsGenerator())->generate($this->createEvent(), 'http://localhost/x');

        // 10:00 à Paris (UTC+1 en hiver) = 09:00 UTC
        $this->assertStringContainsString('DTSTART:20261225T090000Z', $content);
        $this->assertStringContainsString('DTEND:20261225T110000Z', $content);
    }

    public function testGenerateHandlesNullDates(): void
    {
        $event = new Event();
        $event->setTitle('Sans dates');

        $content = (new EventIcsGenerator())->generate($event, 'http://localhost/x');

        $this->assertStringContainsString('DTSTART:', $content);
        $this->assertStringContainsString('DTEND:', $content);
        $this->assertStringContainsString('LOCATION:', $content);
    }

    public function testGenerateContainsDtstamp(): void
    {
        $content = (new EventIcsGenerator())->generate($this->createEvent(), 'http://localhost/x');

        $this->assertMatchesRegularExpression('/DTSTAMP:\d{8}T\d{6}Z/', $content);
    }

    public function testFilename(): void
    {
        $event = $this->createEvent();

        // L'entité n'a pas encore d'id en mémoire : le nom reste cohérent
        $this->assertStringEndsWith('.ics', (new EventIcsGenerator())->filename($event));
    }

    public function testResponseHeaders(): void
    {
        $response = (new EventIcsGenerator())
            ->response($this->createEvent(), 'http://localhost/evenements/1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.ics', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
    }
}
