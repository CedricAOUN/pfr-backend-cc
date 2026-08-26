<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LucianoTonet\GroqLaravel\Facades\Groq;
use Tests\TestCase;

class RecipeSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_an_existing_suggestion_without_contacting_groq(): void
    {
        $recipe = $this->recipe();
        $suggestion = $recipe->suggestion()->create([
            'suggestion' => 'An existing concise summary.',
        ]);

        Groq::shouldReceive('chat')->never();

        $this->getJson("/api/v1/recipes/{$recipe->id}/ai")
            ->assertOk()
            ->assertJsonPath('data.suggestion', $suggestion->suggestion)
            ->assertJsonPath('data.recipe_id', $recipe->id);

        $this->assertSame(1, Suggestion::count());
    }

    public function test_it_generates_and_stores_a_suggestion_from_ingredients_and_steps(): void
    {
        $recipe = $this->recipe();
        Ingredient::create([
            'name' => 'Carrot',
            'quantity' => 2,
            'unit' => 'pcs',
            'recipe_id' => $recipe->id,
        ]);

        Groq::shouldReceive('chat->completions->create')
            ->once()
            ->withArgs(function (array $payload): bool {
                $prompt = $payload['messages'][1]['content'];

                return str_contains($prompt, '2 pcs Carrot')
                    && str_contains($prompt, 'Chop and roast the carrots.')
                    && ! str_contains($prompt, 'Internal description');
            })
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => 'Roast the chopped carrots until tender.'],
                ]],
            ]);

        $this->getJson("/api/v1/recipes/{$recipe->id}/ai")
            ->assertOk()
            ->assertJsonPath('data.suggestion', 'Roast the chopped carrots until tender.')
            ->assertJsonPath('data.recipe_id', $recipe->id);

        $this->assertDatabaseHas('suggestions', [
            'recipe_id' => $recipe->id,
            'suggestion' => 'Roast the chopped carrots until tender.',
        ]);
    }

    public function test_it_does_not_store_an_empty_groq_response(): void
    {
        $recipe = $this->recipe();

        Groq::shouldReceive('chat->completions->create')
            ->once()
            ->andReturn(['choices' => []]);

        $this->getJson("/api/v1/recipes/{$recipe->id}/ai")
            ->assertStatus(502)
            ->assertJsonPath('message', 'Unable to generate the recipe suggestion right now.');

        $this->assertDatabaseEmpty('suggestions');
    }

    private function recipe(): Recipe
    {
        $owner = User::factory()->create();

        return Recipe::create([
            'title' => 'Carrots',
            'description' => 'Internal description',
            'instructions' => 'Chop and roast the carrots.',
            'is_premium' => false,
            'creator_id' => $owner->id,
        ]);
    }
}
