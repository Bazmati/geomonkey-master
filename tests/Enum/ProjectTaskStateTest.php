<?php

namespace App\Tests\Enum;

use App\Enum\ProjectTaskState;
use PHPUnit\Framework\TestCase;

class ProjectTaskStateTest extends TestCase
{
    public function testTodoState(): void
    {
        $this->assertEquals('a_faire', ProjectTaskState::Todo->value);
        $this->assertInstanceOf(ProjectTaskState::class, ProjectTaskState::Todo);
    }

    public function testDoingState(): void
    {
        $this->assertEquals('en_cours', ProjectTaskState::Doing->value);
        $this->assertInstanceOf(ProjectTaskState::class, ProjectTaskState::Doing);
    }

    public function testDoneState(): void
    {
        $this->assertEquals('fait', ProjectTaskState::Done->value);
        $this->assertInstanceOf(ProjectTaskState::class, ProjectTaskState::Done);
    }

    public function testAllStatesAreStrings(): void
    {
        $states = [ProjectTaskState::Todo, ProjectTaskState::Doing, ProjectTaskState::Done];
        
        foreach ($states as $state) {
            $this->assertIsString($state->value);
        }
    }

    public function testStatesAreUnique(): void
    {
        $values = [
            ProjectTaskState::Todo->value,
            ProjectTaskState::Doing->value,
            ProjectTaskState::Done->value,
        ];
        
        $uniqueValues = array_unique($values);
        
        $this->assertCount(count($values), $uniqueValues);
    }

    public function testStateFromValue(): void
    {
        $this->assertEquals(ProjectTaskState::Todo, ProjectTaskState::from('a_faire'));
        $this->assertEquals(ProjectTaskState::Doing, ProjectTaskState::from('en_cours'));
        $this->assertEquals(ProjectTaskState::Done, ProjectTaskState::from('fait'));
    }

    public function testTryFromValidValue(): void
    {
        $result = ProjectTaskState::tryFrom('a_faire');
        $this->assertInstanceOf(ProjectTaskState::class, $result);
        $this->assertEquals(ProjectTaskState::Todo, $result);
    }

    public function testTryFromInvalidValue(): void
    {
        $result = ProjectTaskState::tryFrom('invalid');
        $this->assertNull($result);
    }

    public function testStateCases(): void
    {
        $cases = ProjectTaskState::cases();
        
        $this->assertCount(3, $cases);
        $this->assertContains(ProjectTaskState::Todo, $cases);
        $this->assertContains(ProjectTaskState::Doing, $cases);
        $this->assertContains(ProjectTaskState::Done, $cases);
    }

    public function testStateCanBeUsedInSwitch(): void
    {
        $state = ProjectTaskState::Done;
        
        $result = match ($state) {
            ProjectTaskState::Todo => 'À faire',
            ProjectTaskState::Doing => 'En cours',
            ProjectTaskState::Done => 'Fait',
        };
        
        $this->assertEquals('Fait', $result);
    }

    public function testAllValuesAreValid(): void
    {
        foreach (ProjectTaskState::cases() as $state) {
            $this->assertNotNull($state->value);
            $this->assertIsString($state->value);
            $this->assertNotEmpty($state->value);
        }
    }

    public function testStateFlow(): void
    {
        // Test du flux normal d'un état
        $todo = ProjectTaskState::Todo;
        $doing = ProjectTaskState::Doing;
        $done = ProjectTaskState::Done;
        
        $this->assertNotEquals($todo, $doing);
        $this->assertNotEquals($doing, $done);
        $this->assertNotEquals($done, $todo);
    }

    public function testStateComparison(): void
    {
        $this->assertTrue(ProjectTaskState::Todo === ProjectTaskState::Todo);
        $this->assertFalse(ProjectTaskState::Todo === ProjectTaskState::Doing);
        
        $this->assertSame(ProjectTaskState::Todo, ProjectTaskState::Todo);
        $this->assertNotSame(ProjectTaskState::Todo, ProjectTaskState::Doing);
    }
}
