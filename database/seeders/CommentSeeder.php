<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $users = User::all();
    $recipes = Recipe::all();

    $comments = [
      'Délicieux ! Ma famille a adoré.',
      'Recette testée et approuvée, je la refais ce week-end !',
      'Super recette, facile à suivre.',
      'J\'ai ajouté un peu plus d\'épices, c\'était parfait.',
      'Merci pour ce partage, un vrai régal.',
      'Les photos donnent trop envie !',
      'Je n\'avais pas tous les ingrédients mais j\'ai improvisé, très bon quand même.',
      'Recette adoptée dans mon répertoire !',
      'Simple et efficace, exactement ce que je cherchais.',
      'Mes enfants ont tout mangé, c\'est rare !',
      'Une tuerie cette recette !',
      'Je recommande vivement.',
    ];

    foreach ($recipes as $recipe) {
      // Add 2-4 comments per recipe
      $numComments = rand(2, 4);
      $shuffledComments = collect($comments)->shuffle()->take($numComments);
      $shuffledUsers = $users->shuffle();

      foreach ($shuffledComments as $index => $commentText) {
        Comment::create([
          'content' => $commentText,
          'creator_id' => $shuffledUsers[$index % $users->count()]->id,
          'recipe_id' => $recipe->id,
        ]);
      }
    }
  }
}
