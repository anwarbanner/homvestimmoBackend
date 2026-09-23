<?php

namespace Tests\Unit;

use App\Models\Property;
use App\Services\SocialMedia\PropertyCaptionGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyCaptionGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mentions_the_rental_term_for_a_rental_property(): void
    {
        $property = Property::factory()->create([
            'transaction_type' => 'rent',
            'rental_term' => 'short_term',
        ]);

        $caption = PropertyCaptionGenerator::make($property);

        $this->assertStringContainsString('📅 Courte durée', $caption);
    }

    public function test_it_omits_the_rental_term_line_for_a_sale_property(): void
    {
        $property = Property::factory()->create([
            'transaction_type' => 'sale',
            'rental_term' => null,
        ]);

        $caption = PropertyCaptionGenerator::make($property);

        $this->assertStringNotContainsString('📅', $caption);
    }
}
