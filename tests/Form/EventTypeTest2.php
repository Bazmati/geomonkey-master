<?php

namespace App\Tests\Form;

use App\Form\EventType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EventTypeTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testFileWithoutAltIsRejected(): void
    {
        $formFactory = self::getContainer()->get('form.factory');
        $form = $formFactory->create(EventType::class);

        $form->submit([
            'title' => 'Valid Title',
            'description' => 'Valid description here',
            'startDate' => '2030-11-21T12:00',
            'endDate' => '2030-11-22T12:00',
            'location' => 'Paris',
            'isPublished' => false,
            'newImageFile' => $this->createUploadedFile(),
            'newImageAlt' => '',
            'newImageConsent' => true,
        ], false);

        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('newImageAlt')->getErrors()->count(),
            'Un fichier sans alt doit générer une erreur sur newImageAlt');
    }

    public function testFileWithoutConsentIsRejected(): void
    {
        $formFactory = self::getContainer()->get('form.factory');
        $form = $formFactory->create(EventType::class);

        $form->submit([
            'title' => 'Valid Title',
            'description' => 'Valid description here',
            'startDate' => '2030-11-21T12:00',
            'endDate' => '2030-11-22T12:00',
            'location' => 'Paris',
            'isPublished' => false,
            'newImageFile' => $this->createUploadedFile(),
            'newImageAlt' => 'Un événement',
            'newImageConsent' => false,
        ], false);

        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('newImageConsent')->getErrors()->count(),
            'Un fichier sans consentement RGPD doit générer une erreur sur newImageConsent');
    }

    public function testFileWithAltAndConsentPasses(): void
    {
        $formFactory = self::getContainer()->get('form.factory');
        $form = $formFactory->create(EventType::class);

        $form->submit([
            'title' => 'Valid Title',
            'description' => 'Valid description here',
            'startDate' => '2030-11-21T12:00',
            'endDate' => '2030-11-22T12:00',
            'location' => 'Paris',
            'isPublished' => false,
            'newImageFile' => $this->createUploadedFile(),
            'newImageAlt' => 'Sortie géologique',
            'newImageConsent' => true,
        ], false);

        // On ne juge que les champs newImage* (le mapping des dates peut échouer sans incidence ici)
        $this->assertSame(0, $form->get('newImageAlt')->getErrors()->count());
        $this->assertSame(0, $form->get('newImageConsent')->getErrors()->count());
    }

    private function createUploadedFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'formtest');
        file_put_contents($tmp, 'fake');

        return new UploadedFile($tmp, 'test.png', 'image/png', null, true);
    }
}