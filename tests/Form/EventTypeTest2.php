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
            'desciption' => 'Test Description',
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
        
        echo "StartDate with Y-m-d: " . ($event->getStartDate() ? $event->getStartDate()->format('Y-m-d H:i:s T') : 'null') . "\n";
        
        $this->assertSame(
            '2030-11-21 12:00:00',
            $event->getStartDate()->format('Y-m-d H:i:s')
        );
    }
    
    public function testDateFormatWithDmY(): void
    {
        // Try with d/m/Y H:i format
        $formData = [
            'title' => 'Test Event',
            'desciption' => 'Test Description',
            'startDate' => '21/11/2030 12:00',
            'endDate' => '22/11/2030 12:00',
            'location' => 'Test Location',
            'isPublished' => true,
            'imageFile' => null,
            'deleteImage' => false,
        ];

        $form = $this->factory->create(EventType::class, new Event());
        
        // Check what the form type is
        $startDateField = $form->get('startDate');
        $type = $startDateField->getConfig()->getType();
        echo "StartDate field type: " . get_class($type) . "\n";
        
        $options = $startDateField->getConfig()->getOptions();
        echo "StartDate format: " . ($options['format'] ?? 'not set') . "\n";
        echo "StartDate widget: " . ($options['widget'] ?? 'not set') . "\n";
        echo "StartDate model_timezone: " . ($options['model_timezone'] ?? 'not set') . "\n";
        echo "StartDate view_timezone: " . ($options['view_timezone'] ?? 'not set') . "\n";
        
        $form->submit($formData);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = (string) $error->getMessage();
            }
            $this->fail('Form has errors: ' . implode(', ', $errors));
        }

        $event = $form->getData();
        
        echo "StartDate with d/m/Y: " . ($event->getStartDate() ? $event->getStartDate()->format('Y-m-d H:i:s T') : 'null') . "\n";
        
        $this->assertSame(
            '2030-11-21 12:00:00',
            $event->getStartDate()->format('Y-m-d H:i:s')
        );
    }
}
