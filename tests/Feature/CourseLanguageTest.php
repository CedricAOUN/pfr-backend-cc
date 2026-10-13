<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Database\Seeders\CourseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_is_required_validated_and_saved_on_creation_and_form_edit(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $chef = User::factory()->create();
        $chef->assignRole('chef');
        Sanctum::actingAs($chef);
        $this->postJson('/api/v1/courses/create', ['title' => 'Knife skills'])->assertUnprocessable()->assertJsonValidationErrors('language');
        $response = $this->postJson('/api/v1/courses/create', ['title' => 'Knife skills', 'content' => 'Chop', 'language' => 'en'])
            ->assertSuccessful()->assertJsonPath('data.language', 'en');
        $id = $response->json('data.id');
        foreach (['de', '', null] as $language) {
            $this->postJson('/api/v1/courses/create', ['title' => 'Knife skills', 'language' => $language])->assertUnprocessable()->assertJsonValidationErrors('language');
            $this->putJson('/api/v1/courses/edit/'.$id, ['language' => $language])->assertUnprocessable()->assertJsonValidationErrors('language');
        }
        $this->post('/api/v1/courses/edit/'.$id, ['_method' => 'PUT', 'language' => 'fr'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.language', 'fr');
        $this->assertDatabaseHas('courses', ['id' => $id, 'language' => 'fr']);
    }

    public function test_language_filters_both_public_course_lists_and_creator_queries(): void
    {
        $chef = User::factory()->create();
        $other = User::factory()->create();
        $english = Course::create(['title' => 'Bread', 'language' => 'en', 'content' => 'Private lesson', 'expert_id' => $chef->id]);
        Course::create(['title' => 'Pain', 'language' => 'fr', 'expert_id' => $chef->id]);
        Course::create(['title' => 'Bread making', 'language' => 'en', 'expert_id' => $other->id]);
        $this->getJson('/api/v1/courses?language=en&creator_id='.$chef->id)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $english->id)->assertJsonPath('data.0.language', 'en')->assertJsonMissingPath('data.0.content');
        $this->getJson('/api/v1/courses/list?language=fr')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Pain');
        $this->getJson('/api/v1/courses/list?language=en&search=Pain')->assertOk()->assertJsonCount(0, 'data');
        foreach (['/api/v1/courses', '/api/v1/courses/list'] as $url) {
            $this->getJson($url.'?language=de')->assertUnprocessable()->assertJsonValidationErrors('language');
        }
    }

    public function test_course_seeder_contains_french_and_six_english_lessons(): void
    {
        User::factory()->create(['name' => 'chef_martin']);
        User::factory()->create(['name' => 'chef_sophie']);
        $this->seed(CourseSeeder::class);
        $this->assertSame(5, Course::where('language', 'fr')->count());
        $this->assertSame(6, Course::where('language', 'en')->count());
        $this->assertSame(0, Course::whereNull('content')->count());
    }
}
