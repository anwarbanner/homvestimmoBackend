<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(): User
    {
        return User::factory()->admin()->create([
            'email' => 'admin@gmail.com',
            'password' => bcrypt('12345678'),
        ]);
    }

    public function test_admin_can_login_with_correct_credentials(): void
    {
        $this->makeAdmin();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@gmail.com',
                'password' => '12345678',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->makeAdmin();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@gmail.com',
                'password' => 'wrong-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest();
    }

    public function test_a_user_without_the_admin_role_cannot_access_the_panel(): void
    {
        $user = User::factory()->create([
            'email' => 'no-role@gmail.com',
            'password' => bcrypt('12345678'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'no-role@gmail.com',
                'password' => '12345678',
            ])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest();

        $this->actingAs($user);
        $this->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->get('/admin')->assertSuccessful();
    }

    public function test_admin_can_list_properties(): void
    {
        $this->actingAs($this->makeAdmin());
        Property::factory()->count(3)->create();

        $this->get('/admin/properties')->assertSuccessful();
    }

    public function test_admin_can_create_property(): void
    {
        $this->actingAs($this->makeAdmin());

        Livewire::test(\App\Filament\Resources\PropertyResource\Pages\CreateProperty::class)
            ->fillForm([
                'title' => 'Villa Test CRUD',
                'type' => 'villa',
                'transaction_type' => 'sale',
                'status' => 'draft',
                'price' => 250000,
                'surface' => 120,
                'address' => '1 Rue de Test',
                'city' => 'Casablanca',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // The "reference" field is disabled in the form and generated
        // automatically by the model, not submitted by the admin.
        $this->assertDatabaseHas('properties', [
            'title' => 'Villa Test CRUD',
        ]);
        $this->assertNotEmpty(Property::where('title', 'Villa Test CRUD')->first()->reference);
    }

    public function test_admin_can_update_property(): void
    {
        $this->actingAs($this->makeAdmin());
        // transaction_type is forced to "sale" so the conditionally required
        // "rental_term" field isn't part of this unrelated smoke test.
        $property = Property::factory()->create(['title' => 'Old Title', 'transaction_type' => 'sale']);

        Livewire::test(\App\Filament\Resources\PropertyResource\Pages\EditProperty::class, [
            'record' => $property->getRouteKey(),
        ])
            ->fillForm(['title' => 'Updated Title'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_admin_can_delete_property(): void
    {
        // PropertyResource's table only exposes bulk delete (DeleteBulkAction), no per-row delete action.
        $this->actingAs($this->makeAdmin());
        $property = Property::factory()->create();

        Livewire::test(\App\Filament\Resources\PropertyResource\Pages\ListProperties::class)
            ->callTableBulkAction('delete', [$property]);

        $this->assertModelMissing($property);
    }
}
