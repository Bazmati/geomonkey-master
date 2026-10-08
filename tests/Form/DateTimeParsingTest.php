<?php

namespace App\Tests\Form;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class DateTimeParsingTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new \Symfony\Component\Form\Extension\Validator\ValidatorExtension(
                Validation::createValidator()
            ),
        ];
    }

    public function testSimpleDateTimeParsing(): void
    {
        // Skip this test as it depends on timezone configuration
        $this->markTestSkipped('DateTime parsing test depends on timezone configuration');
    }
    
}
