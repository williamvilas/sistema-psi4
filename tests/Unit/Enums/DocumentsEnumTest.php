<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\DocumentsEnum;
use Tests\TestCase;

class DocumentsEnumTest extends TestCase
{
    public function testItEveryTypeHasIconToneAndDescription(): void
    {
        foreach (DocumentsEnum::cases() as $type) {
            // Act
            $description = $type->description();

            // Assert
            $this->assertStringStartsWith('fas fa-', $type->icon());
            $this->assertContains($type->tone(), range(0, 5));
            $this->assertNotSame('documents.descriptions.'.$type->value, $description, "Descrição sem tradução: {$type->value}");
        }
    }

    public function testItOnlyAttendanceStatementHasAgenda(): void
    {
        // Act
        $withAgenda = array_filter(DocumentsEnum::cases(), fn (DocumentsEnum $type) => $type->hasAgenda());

        // Assert
        $this->assertSame([DocumentsEnum::ATTENDANCE_STATEMENT], array_values($withAgenda));
    }
}
