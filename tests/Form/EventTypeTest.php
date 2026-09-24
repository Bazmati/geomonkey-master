<?php

namespace App\Tests\Form;

use App\Entity\Event;
use App\Form\EventType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class EventTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new \Symfony\Component\Form\Extension\Validator\ValidatorExtension(
                Validation::createValidator()
            ),
        ];
    }

    public function testSubmitWithValidDatesAndImage(): void
    {
        $formData = [
            'title' => 'Test Event',
            'desciption' => 'Test Description',
            'startDate' => '2030-11-21T12:00',
            'endDate' => '2030-11-22T12:00',
            'location' => 'Test Location',
            'isPublished' => true,
            'imageFile' => null,
            'deleteImage' => false,
        ];

        $event = new Event();
        
        // Set dates manually to test if they change
        $startDate = new \DateTime('2030-11-21 12:00:00');
        $endDate = new \DateTime('2030-11-22 12:00:00');
        $event->setStartDate($startDate);
        $event->setEndDate($endDate);
        
        $form = $this->factory->create(EventType::class, $event);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        
        $submittedEvent = $form->getData();
        
        // Verify dates have NOT changed
        $this->assertSame(
            $startDate->format('Y-m-d H:i:s'),
            $submittedEvent->getStartDate()->format('Y-m-d H:i:s'),
            'Start date should not change: expected ' . $startDate->format('Y-m-d H:i:s') . 
            ', got ' . ($submittedEvent->getStartDate() ? $submittedEvent->getStartDate()->format('Y-m-d H:i:s') : 'null')
        );
        
        $this->assertSame(
            $endDate->format('Y-m-d H:i:s'),
            $submittedEvent->getEndDate()->format('Y-m-d H:i:s'),
            'End date should not change: expected ' . $endDate->format('Y-m-d H:i:s') . 
            ', got ' . ($submittedEvent->getEndDate() ? $submittedEvent->getEndDate()->format('Y-m-d H:i:s') : 'null')
        );
    }

    public function testSubmitWithDateOnly(): void
    {
        $formData = [
            'title' => 'Test Event',
            'desciption' => 'Test Description',
            'startDate' => '2030-11-21T12:00',
            'endDate' => '2030-11-22T12:00',
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

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        $event = $form->getData();
        
        // Verify dates are correctly parsed
        $this->assertInstanceOf(\DateTime::class, $event->getStartDate(), 'StartDate should be DateTime');
        $this->assertInstanceOf(\DateTime::class, $event->getEndDate(), 'EndDate should be DateTime');
        
        echo "StartDate raw: " . ($event->getStartDate() ? $event->getStartDate()->format('Y-m-d H:i:s T') : 'null') . "\n";
        
        $this->assertSame(
            '2030-11-21 12:00:00',
            $event->getStartDate()->format('Y-m-d H:i:s'),
            'Start date should be 2030-11-21 12:00:00, got ' . $event->getStartDate()->format('Y-m-d H:i:s')
        );
        
        $this->assertSame(
            '2030-11-22 12:00:00',
            $event->getEndDate()->format('Y-m-d H:i:s'),
            'End date should be 2030-11-22 12:00:00, got ' . $event->getEndDate()->format('Y-m-d H:i:s')
        );
    }
}
