<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $videoPath = Storage::disk('public')->putFileAs(
            'course_videos',
            new File(database_path('seeders/videos/4253143-uhd_2160_4096_25fps.mp4')),
            '4253143-uhd_2160_4096_25fps.mp4',
        );

        if ($videoPath === false) {
            throw new \RuntimeException('Unable to store the seeded course video.');
        }

        $videoUrl = '/storage/'.$videoPath;

        Course::create([
            'language' => 'fr',
            'title' => 'Les bases de la pâtisserie française',
            'description' => 'Apprenez les techniques fondamentales de la pâtisserie : pâtes, crèmes, meringues et plus encore. Ce cours vous donnera toutes les clés pour réussir vos desserts comme un professionnel.',
            'content' => 'Dans ce cours, vous apprendrez les techniques de base de la pâtisserie française, y compris la préparation des pâtes, des crèmes et des meringues, ainsi que des recettes classiques.',
            'expert_id' => $users->where('name', 'chef_martin')->first()->id,
        ]);

        Course::create([
            'language' => 'fr',
            'title' => 'Maîtriser les sauces classiques',
            'description' => 'Du beurre blanc à la béarnaise, en passant par la hollandaise et la béchamel. Découvrez les secrets des grandes sauces de la cuisine française.',
            'content' => 'Dans ce cours, vous apprendrez à préparer et maîtriser les sauces classiques de la cuisine française, en comprenant les techniques et les ingrédients nécessaires.',
            'expert_id' => $users->where('name', 'chef_martin')->first()->id,
        ]);

        Course::create([
            'language' => 'fr',
            'title' => 'Cuisine asiatique pour débutants',
            'video_path' => $videoUrl,
            'description' => 'Introduction aux techniques et saveurs de la cuisine asiatique. Wok, vapeur, marinades et sauces typiques.',
            'content' => 'Dans ce cours, vous apprendrez les bases de la cuisine asiatique, y compris la préparation des ingrédients, les techniques de cuisson et les recettes typiques.',
            'expert_id' => $users->where('name', 'chef_sophie')->first()->id,
        ]);

        Course::create([
            'language' => 'fr',
            'title' => 'L\'art du pain maison',
            'description' => 'Réalisez votre propre pain : baguette, pain de campagne, focaccia. Comprenez les fermentations et le pétrissage.',
            'content' => 'Dans ce cours, vous apprendrez à préparer et à maîtriser les techniques de fabrication du pain maison, y compris le pétrissage, la fermentation et la cuisson.',
            'expert_id' => $users->where('name', 'chef_sophie')->first()->id,
        ]);

        Course::create([
            'language' => 'fr',
            'title' => 'Techniques de découpe professionnelles',
            'description' => 'Apprenez à utiliser vos couteaux comme un chef : julienne, brunoise, chiffonnade et bien plus.',
            'content' => 'Dans ce cours, vous apprendrez les techniques de découpe professionnelles, y compris la julienne, la brunoise et la chiffonnade.',
            'expert_id' => $users->where('name', 'chef_martin')->first()->id,
        ]);

        $englishCourses = [
            [
                'title' => 'Essential Knife Skills',
                'description' => 'Build confidence with safe knife handling, slicing, dicing and chopping.',
                'content' => "# Essential Knife Skills\n\n## Set up your workspace\nPlace a damp towel under your chopping board to stop it sliding. Keep your board clear and use a sharp chef knife.\n\n## Practise the basics\n1. Grip the handle firmly and keep your guiding fingertips tucked into a claw shape.\n2. Slice a carrot into even rounds, moving slowly and deliberately.\n3. Cut the rounds into strips, then small cubes.\n\n## Practice task\nPrepare an onion, a carrot and a courgette for soup. Aim for consistent sizes rather than speed. Wash and dry your knife after use.",
            ],
            [
                'title' => 'Fresh Pasta from Scratch',
                'video_path' => $videoUrl,
                'description' => 'Learn to mix, knead, roll and cook a simple fresh egg pasta dough.',
                'content' => "# Fresh Pasta from Scratch\n\n## Make the dough\nMix 200 g of plain flour with 2 eggs. Bring the mixture together and knead until smooth. Cover and rest for 30 minutes.\n\n## Roll and shape\n1. Divide the dough into manageable pieces.\n2. Roll each piece thinly, dusting lightly with flour to prevent sticking.\n3. Cut into ribbons and separate them on a floured tray.\n\n## Cook and finish\nCook in boiling salted water until tender, usually 2 to 4 minutes depending on thickness. Taste a strand to check. Toss with your favourite sauce and a little pasta water.",
            ],
            [
                'title' => 'Better Vegetable Cooking',
                'description' => 'Explore roasting, steaming and sauteing to bring out vegetable flavour and texture.',
                'content' => "# Better Vegetable Cooking\n\n## Choose a method\nRoasting develops browned edges, steaming keeps vegetables tender, and sauteing works well for smaller pieces.\n\n## Three exercises\n1. Toss carrot pieces with oil and roast at 200 C until browned and tender.\n2. Steam broccoli until a fork enters the stem easily, then season after cooking.\n3. Saute sliced mushrooms in a wide pan, leaving space for moisture to evaporate.\n\n## Compare the results\nTaste each vegetable before adding more seasoning. Note the colour, texture and moisture. Finish with herbs or a little lemon juice to balance the flavours.",
            ],
            [
                'title' => 'Simple Sauces and Dressings',
                'description' => 'Make a vinaigrette, a tomato sauce and a creamy white sauce with everyday ingredients.',
                'content' => "# Simple Sauces and Dressings\n\n## Vinaigrette\nWhisk 1 tablespoon of vinegar with 3 tablespoons of olive oil and a teaspoon of mustard. Season and taste with a salad leaf.\n\n## Tomato sauce\nSoften a chopped onion in oil. Add chopped tomatoes and simmer until thickened. Stir in fresh basil at the end.\n\n## White sauce\nMelt 25 g of butter, stir in 25 g of flour and cook briefly. Gradually whisk in 300 ml of milk. Simmer gently until smooth and thick.\n\n## Practice task\nMatch each sauce to a different dish and adjust its consistency before serving.",
            ],
            [
                'title' => 'Your First Homemade Bread',
                'description' => 'Understand mixing, kneading and proving with a straightforward yeast loaf.',
                'content' => "# Your First Homemade Bread\n\n## Mix and knead\nCombine 500 g of bread flour, 7 g of instant yeast, 10 g of salt and 320 ml of lukewarm water. Knead until the dough feels smooth and elastic.\n\n## Prove and shape\n1. Cover and leave until noticeably expanded, roughly doubled in size.\n2. Gently press out excess air and shape into a loaf.\n3. Place in a loaf tin and cover for a second rise.\n\n## Bake\nBake at 220 C for about 30 minutes, adjusting for your oven. Cool on a rack before slicing. Record your proving times and dough texture so you can improve the next batch.",
            ],
            [
                'title' => 'Practical Weeknight Meal Planning',
                'description' => 'Plan flexible meals, organise a shopping list and prepare ingredients efficiently.',
                'content' => "# Practical Weeknight Meal Planning\n\n## Start with your schedule\nChoose three main meals that fit the time you have available. Include a quick option for your busiest evening.\n\n## Build a shopping list\nGroup ingredients by produce, pantry and chilled items. Check what you already have and choose dishes that share useful ingredients.\n\n## Prepare efficiently\n1. Wash vegetables and organise ingredients before cooking.\n2. Prepare a versatile dressing or sauce for more than one meal.\n3. Label prepared food with its preparation date and follow the storage guidance for each ingredient.\n\n## Practice task\nWrite a three-day menu and shopping list, then review what you used and what was left over.",
            ],
        ];

        foreach ($englishCourses as $index => $data) {
            Course::create([
                ...$data,
                'language' => 'en',
                'expert_id' => $users->where('name', $index % 2 === 0 ? 'chef_martin' : 'chef_sophie')->first()->id,
            ]);
        }
    }
}
