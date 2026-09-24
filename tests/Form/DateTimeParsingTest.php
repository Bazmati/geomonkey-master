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
        // Test Symfony's DateTimeType directly
        $form = $this->factory->create(DateTimeType::class, null, [
            'format' => 'd/m/Y H:i',
            'widget' => 'single_text',
            'html5' => false,
            'model_timezone' => 'Europe/Paris',
            'view_timezone' => 'Europe/Paris',
        ]);

        $form->submit('21/11/2030 12:00');

        $data = $form->getData();
        
        echo "Simple DateTimeType result: " . ($data ? $data->format('Y-m-d H:i:s T') : 'null') . "\n";
        
        $this->assertInstanceOf(\DateTime::class, $data);
        $this->assertSame('2030-11-21 12:00:00', $data->format('Y-m-d H:i:s'));
    }
    
    public function testDateTimeParsingWithDifferentFormats(): void
    {
        $formats = [
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
        ];
        
        foreach ($formats as $format) {
            $form = $this->factory->create(DateTimeType::class, null, [
                'format' => $format,
                'widget' => 'single_text',
                'html5' => false,
            ]);

            $testDate = '21/11/2030 12:00';
            if (str_contains($format, 'Y-m-d')) {
                $testDate = '2030-11-21 12:00';
            }
            
            $form->submit($testDate);
            $data = $form->getData();
            
            echo "Format {$format} with '{$testDate}': " . ($data ? $data->format('Y-m-d H:i:s T') : 'null') . "\n";
        }
    }
}
