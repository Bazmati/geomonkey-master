<?php

namespace App\Tests\Enum;

use App\Enum\ProjectStatus;
use PHPUnit\Framework\TestCase;

class ProjectStatusTest extends TestCase
{
    public function testPlannedStatus(): void
    {
        $this->assertEquals('planifie', ProjectStatus::Planned->value);
        $this->assertInstanceOf(ProjectStatus::class, ProjectStatus::Planned);
    }

    public function testInProgressStatus(): void
    {
        $this->assertEquals('en_cours', ProjectStatus::InProgress->value);
        $this->assertInstanceOf(ProjectStatus::class, ProjectStatus::InProgress);
    }

    public function testCompletedStatus(): void
    {
        $this->assertEquals('termine', ProjectStatus::Completed->value);
        $this->assertInstanceOf(ProjectStatus::class, ProjectStatus::Completed);
    }

    public function testAllStatusesAreStrings(): void
    {
        $statuses = [ProjectStatus::Planned, ProjectStatus::InProgress, ProjectStatus::Completed];
        
        foreach ($statuses as $status) {
            $this->assertIsString($status->value);
        }
    }

    public function testStatusesAreUnique(): void
    {
        $values = [
            ProjectStatus::Planned->value,
            ProjectStatus::InProgress->value,
            ProjectStatus::Completed->value,
        ];
        
        $uniqueValues = array_unique($values);
        
        $this->assertCount(count($values), $uniqueValues);
    }

    public function testStatusFromValue(): void
    {
        $this->assertEquals(ProjectStatus::Planned, ProjectStatus::from('planifie'));
        $this->assertEquals(ProjectStatus::InProgress, ProjectStatus::from('en_cours'));
        $this->assertEquals(ProjectStatus::Completed, ProjectStatus::from('termine'));
    }

    public function testTryFromValidValue(): void
    {
        $result = ProjectStatus::tryFrom('planifie');
        $this->assertInstanceOf(ProjectStatus::class, $result);
        $this->assertEquals(ProjectStatus::Planned, $result);
    }

    public function testTryFromInvalidValue(): void
    {
        $result = ProjectStatus::tryFrom('invalid');
        $this->assertNull($result);
    }

    public function testStatusCases(): void
    {
        $cases = ProjectStatus::cases();
        
        $this->assertCount(3, $cases);
        $this->assertContains(ProjectStatus::Planned, $cases);
        $this->assertContains(ProjectStatus::InProgress, $cases);
        $this->assertContains(ProjectStatus::Completed, $cases);
    }

    public function testStatusCanBeUsedInSwitch(): void
    {
        $status = ProjectStatus::InProgress;
        
        $result = match ($status) {
            ProjectStatus::Planned => 'Planifié',
            ProjectStatus::InProgress => 'En cours',
            ProjectStatus::Completed => 'Terminé',
        };
        
        $this->assertEquals('En cours', $result);
    }

    public function testAllValuesAreValid(): void
    {
        foreach (ProjectStatus::cases() as $status) {
            $this->assertNotNull($status->value);
            $this->assertIsString($status->value);
            $this->assertNotEmpty($status->value);
        }
    }
}
