<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $experts = User::where('is_expert', true)->get();

        Course::create([
            'title' => 'Les bases de la pâtisserie française',
            'description' => 'Apprenez les techniques fondamentales de la pâtisserie : pâtes, crèmes, meringues et plus encore. Ce cours vous donnera toutes les clés pour réussir vos desserts comme un professionnel.',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'expert_id' => $experts->first()->id,
        ]);

        Course::create([
            'title' => 'Maîtriser les sauces classiques',
            'description' => 'Du beurre blanc à la béarnaise, en passant par la hollandaise et la béchamel. Découvrez les secrets des grandes sauces de la cuisine française.',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'expert_id' => $experts->first()->id,
        ]);

        Course::create([
            'title' => 'Cuisine asiatique pour débutants',
            'description' => 'Introduction aux techniques et saveurs de la cuisine asiatique. Wok, vapeur, marinades et sauces typiques.',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'expert_id' => $experts->where('name', 'chef_sophie')->first()->id,
        ]);

        Course::create([
            'title' => 'L\'art du pain maison',
            'description' => 'Réalisez votre propre pain : baguette, pain de campagne, focaccia. Comprenez les fermentations et le pétrissage.',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'expert_id' => $experts->where('name', 'chef_sophie')->first()->id,
        ]);

        Course::create([
            'title' => 'Techniques de découpe professionnelles',
            'description' => 'Apprenez à utiliser vos couteaux comme un chef : julienne, brunoise, chiffonnade et bien plus.',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'expert_id' => $experts->first()->id,
        ]);
    }
}
