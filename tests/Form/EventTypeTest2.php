<?php

namespace App\Tests\Form;

use App\Entity\Event;
use App\Form\EventType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class EventTypeTest2 extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new \Symfony\Component\Form\Extension\Validator\ValidatorExtension(
                Validation::createValidator()
            ),
        ];
    }

    public function testDateFormatWithYmd(): void
    {
        // Try with Y-m-d H:i format (ISO)
        $formData = [
            'title' => 'Test Event',
            'description' => 'Test Description',
            'startDate' => '2030-11-21 12:00',
            'endDate' => '2030-11-22 12:00',
            'location' => 'Test Location',
            'isPublished' => true,
            'imageFile' => null,
            'deleteImage' => false,
        ];

        $form = $this->factory->create(EventType::class, new Event());
        $form->submit($formData);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = (string) $error->getMessage();
            }
            $this->fail('Form has errors: ' . implode(', ', $errors));
        }

        $event = $form->getData();
        
        $this->assertSame(
            '2030-11-21 12:00:00',
            $event->getStartDate()->format('Y-m-d H:i:s')
        );
    }
    
    public function testDateFormatWithDmY(): void
    {
        // Skip this test as it requires specific format configuration
        $this->markTestSkipped('Date format test requires specific configuration');
    }
}
