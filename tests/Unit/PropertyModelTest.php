<?php

namespace Tests\Unit;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PropertyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_is_auto_generated_when_left_empty(): void
    {
        $property = Property::factory()->make(['reference' => null]);
        $property->save();

        $this->assertNotEmpty($property->reference);
    }

    public function test_auto_generated_references_are_unique_and_increment(): void
    {
        $first = Property::factory()->make(['reference' => null]);
        $first->save();

        $second = Property::factory()->make(['reference' => null]);
        $second->save();

        $this->assertNotSame($first->reference, $second->reference);
        $this->assertSame((int) $first->reference + 1, (int) $second->reference);
    }

    public function test_explicit_reference_is_not_overridden(): void
    {
        $property = Property::factory()->create(['reference' => 'MANUAL-42']);

        $this->assertSame('MANUAL-42', $property->reference);
    }

    public function test_published_at_is_set_automatically_on_first_publish(): void
    {
        // Queue::fake() prevents the real PropertyObserver -> social media
        // dispatch chain from running (QUEUE_CONNECTION is forced to "sync"
        // in tests) — this test only cares about the model's own behavior.
        Queue::fake();

        $property = Property::factory()->create(['status' => 'draft', 'published_at' => null]);

        $property->update(['status' => 'published']);

        $this->assertNotNull($property->fresh()->published_at);
    }

    public function test_published_at_is_not_overwritten_on_subsequent_saves(): void
    {
        Queue::fake();

        $property = Property::factory()->create(['status' => 'published']);
        $originalPublishedAt = $property->fresh()->published_at;

        $property->update(['title' => 'Updated title']);

        $this->assertEquals($originalPublishedAt, $property->fresh()->published_at);
    }
}
