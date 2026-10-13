<?php

namespace Database\Seeders;

use App\Models\Recipe;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        $recipe1 = Recipe::create([
            'title' => 'Tarte aux pommes traditionnelle',
            'language' => 'fr',
            'category' => 'dessert',
            'duration' => 55,
            'description' => 'Une délicieuse tarte aux pommes à l\'ancienne, parfaite pour le dessert.',
            'instructions' => 'Préchauffer le four à 180°C. Éplucher et couper les pommes en fines tranches. Disposer la pâte dans un moule, piquer le fond. Disposer les pommes en rosace. Saupoudrer de sucre et de cannelle. Parsemer de noisettes de beurre. Enfourner 35 minutes.',
            'is_premium' => false,
            'creator_id' => $users->where('id', 1)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=800',
        ]);

        foreach (
            [
                ['name' => 'Pâte brisée', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Pommes', 'quantity' => 5, 'unit' => 'pcs'],
                ['name' => 'Sucre', 'quantity' => 50, 'unit' => 'g'],
                ['name' => 'Cannelle', 'quantity' => 1, 'unit' => 'tbsp'],
                ['name' => 'Beurre', 'quantity' => 30, 'unit' => 'g']
            ] as $ing
        ) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe1->id]));
        }

        $recipe2 = Recipe::create([
            'title' => 'Risotto aux champignons',
            'language' => 'fr',
            'category' => 'main',
            'duration' => 45,
            'description' => 'Un risotto crémeux aux champignons de Paris et cèpes séchés.',
            'instructions' => 'Réhydrater les cèpes. Faire revenir l\'oignon dans le beurre. Ajouter le riz et nacrer. Déglacer au vin blanc. Ajouter le bouillon louche par louche en remuant. À mi-cuisson, ajouter les champignons. Finir avec le parmesan et une noix de beurre.',
            'is_premium' => true,
            'creator_id' => $users->where('id', 2)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=800',
        ]);

        foreach (
            [
                ['name' => 'Riz arborio', 'quantity' => 300, 'unit' => 'g'],
                ['name' => 'Champignons de Paris', 'quantity' => 200, 'unit' => 'g'],
                ['name' => 'Cèpes séchés', 'quantity' => 20, 'unit' => 'g'],
                ['name' => 'Oignon', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Bouillon de légumes', 'quantity' => 1, 'unit' => 'L'],
                ['name' => 'Vin blanc', 'quantity' => 100, 'unit' => 'ml'],
                ['name' => 'Parmesan râpé', 'quantity' => 50, 'unit' => 'g'],
                ['name' => 'Beurre', 'quantity' => 30, 'unit' => 'g']
            ] as $ing
        ) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe2->id]));
        }

        $recipe3 = Recipe::create([
            'title' => 'Poulet rôti aux herbes',
            'language' => 'fr',
            'category' => 'main',
            'duration' => 90,
            'description' => 'Un classique indémodable, le poulet rôti parfaitement doré et parfumé.',
            'instructions' => 'Préchauffer le four à 200°C. Préparer un beurre aux herbes. Glisser le beurre sous la peau du poulet. Mettre le citron et l\'ail dans la cavité. Saler, poivrer. Enfourner 1h15 en arrosant régulièrement.',
            'is_premium' => false,
            'creator_id' => $users->where('id', 1)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1598103442097-8b74394b95c6?w=800',
        ]);

        foreach (
            [
                ['name' => 'Poulet entier', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Beurre', 'quantity' => 50, 'unit' => 'g'],
                ['name' => 'Thym', 'quantity' => 2, 'unit' => 'g'],
                ['name' => 'Romarin', 'quantity' => 2, 'unit' => 'g'],
                ['name' => 'Citron', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Ail', 'quantity' => 4, 'unit' => 'pcs'],
                ['name' => 'Sel', 'quantity' => 1, 'unit' => 'tbsp'],
                ['name' => 'Poivre', 'quantity' => 0.5, 'unit' => 'tbsp']
            ] as $ing
        ) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe3->id]));
        }

        $recipe4 = Recipe::create([
            'title' => 'Soupe de légumes d\'hiver',
            'language' => 'fr',
            'category' => 'starter',
            'duration' => 45,
            'description' => 'Une soupe réconfortante avec des légumes de saison.',
            'instructions' => 'Éplucher et couper tous les légumes en morceaux. Les mettre dans une grande casserole avec de l\'eau. Porter à ébullition et cuire 30 minutes. Mixer. Assaisonner et servir avec une cuillère de crème fraîche.',
            'is_premium' => false,
            'creator_id' => $users->where('name', 'jean_cook')->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=800',
        ]);

        foreach (
            [
                ['name' => 'Carottes', 'quantity' => 3, 'unit' => 'pcs'],
                ['name' => 'Pomme de terre', 'quantity' => 2, 'unit' => 'pcs'],
                ['name' => 'Poireau', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Oignon', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Bouillon de légumes', 'quantity' => 1, 'unit' => 'L'],
                ['name' => 'Sel', 'quantity' => 1, 'unit' => 'tbsp'],
                ['name' => 'Poivre', 'quantity' => 0.5, 'unit' => 'tbsp']
            ] as $ing
        ) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe4->id]));
        }

        $recipe5 = Recipe::create([
            'title' => 'Crème brûlée à la vanille',
            'language' => 'fr',
            'category' => 'dessert',
            'duration' => 180,
            'description' => 'Le dessert français par excellence, crémeux avec une croûte caramélisée.',
            'instructions' => 'Chauffer la crème avec la vanille fendue. Fouetter les jaunes avec le sucre. Verser la crème chaude sur les jaunes en fouettant. Répartir dans des ramequins. Cuire au bain-marie à 150°C pendant 45 min. Réfrigérer. Saupoudrer de sucre et caraméliser au chalumeau.',
            'is_premium' => true,
            'creator_id' => $users->where('id', 3)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1470124182917-cc6e71b22ecc?w=800',
        ]);

        foreach (
            [
                ['name' => 'Crème liquide', 'quantity' => 500, 'unit' => 'ml'],
                ['name' => 'Jaunes d\'œufs', 'quantity' => 6, 'unit' => 'pcs'],
                ['name' => 'Sucre', 'quantity' => 100, 'unit' => 'g'],
                ['name' => 'Gousses de vanille', 'quantity' => 2, 'unit' => 'pcs']
            ] as $ing
        ) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe5->id]));
        }

        $recipe6 = Recipe::create([
            'title' => 'Salade César maison',
            'language' => 'fr',
            'category' => 'main',
            'duration' => 30,
            'description' => 'La célèbre salade avec sa sauce crémeuse et ses croûtons croustillants.',
            'instructions' => 'Préparer les croûtons dorés à l\'ail. Griller le poulet. Préparer la sauce: mixer jaune d\'œuf, ail, anchois, citron, puis émulsionner avec l\'huile. Mélanger la salade avec la sauce, ajouter poulet, croûtons et copeaux de parmesan.',
            'is_premium' => true,
            'creator_id' => $users->where('name', 'marie_foodie')->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1550304943-4f24f54ddde9?w=800',
        ]);

        foreach (
            [
                ['name' => 'Laitue romaine', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Poulet grillé', 'quantity' => 200, 'unit' => 'g'],
                ['name' => 'Croûtons à l\'ail', 'quantity' => 50, 'unit' => 'g'],
                ['name' => 'Parmesan en copeaux', 'quantity' => 30, 'unit' => 'g'],
                ['name' => 'Jaune d\'œuf', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Ail', 'quantity' => 1, 'unit' => 'pcs'],
                ['name' => 'Anchois', 'quantity' => 2, 'unit' => 'pcs'],
                ['name' => 'Citron', 'quantity' => 0.5, 'unit' => 'pcs'],
                ['name' => 'Huile d\'olive', 'quantity' => 100, 'unit' => 'ml']
            ] as $ing
        ) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe6->id]));
        }

        $englishRecipes = [
            [
                'title' => 'Fluffy Breakfast Pancakes',
                'category' => 'breakfast',
                'duration' => 25,
                'description' => 'Light, golden pancakes served with maple syrup and fresh berries.',
                'instructions' => "1. Mix the flour, baking powder and sugar in a bowl.\n2. Whisk the milk, egg and melted butter together.\n3. Stir the wet ingredients into the dry ingredients until just combined.\n4. Heat a non-stick pan over medium heat and cook small portions of batter until bubbles appear. Flip and cook until golden.\n5. Serve with maple syrup and berries.",
                'image_url' => 'https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=800',
                'ingredients' => [
                    ['name' => 'Plain flour', 'quantity' => 200, 'unit' => 'g'],
                    ['name' => 'Baking powder', 'quantity' => 2, 'unit' => 'tsp'],
                    ['name' => 'Sugar', 'quantity' => 20, 'unit' => 'g'],
                    ['name' => 'Milk', 'quantity' => 250, 'unit' => 'ml'],
                    ['name' => 'Egg', 'quantity' => 1, 'unit' => 'pcs'],
                    ['name' => 'Butter', 'quantity' => 30, 'unit' => 'g'],
                    ['name' => 'Maple syrup', 'quantity' => 2, 'unit' => 'tbsp'],
                    ['name' => 'Fresh berries', 'quantity' => 100, 'unit' => 'g'],
                ],
            ],
            [
                'title' => 'Tomato and Basil Soup',
                'category' => 'starter',
                'duration' => 35,
                'description' => 'A smooth tomato soup with fresh basil, perfect with crusty bread.',
                'instructions' => "1. Chop the onion and garlic.\n2. Heat the olive oil in a saucepan and soften the onion for 5 minutes. Add the garlic and cook for another minute.\n3. Add the tomatoes and vegetable stock, then simmer for 20 minutes.\n4. Add the basil and blend until smooth.\n5. Season with salt and pepper and serve warm.",
                'image_url' => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=800',
                'ingredients' => [
                    ['name' => 'Chopped tomatoes', 'quantity' => 800, 'unit' => 'g'],
                    ['name' => 'Onion', 'quantity' => 1, 'unit' => 'pcs'],
                    ['name' => 'Garlic cloves', 'quantity' => 2, 'unit' => 'pcs'],
                    ['name' => 'Olive oil', 'quantity' => 1, 'unit' => 'tbsp'],
                    ['name' => 'Vegetable stock', 'quantity' => 500, 'unit' => 'ml'],
                    ['name' => 'Fresh basil', 'quantity' => 10, 'unit' => 'g'],
                    ['name' => 'Salt', 'quantity' => 0.5, 'unit' => 'tsp'],
                    ['name' => 'Black pepper', 'quantity' => 0.25, 'unit' => 'tsp'],
                ],
            ],
            [
                'title' => 'Lemon and Spinach Pasta',
                'category' => 'main',
                'duration' => 25,
                'description' => 'A quick pasta dish with wilted spinach, lemon and Parmesan.',
                'instructions' => "1. Cook the pasta in boiling salted water according to the packet instructions. Reserve a cup of cooking water before draining.\n2. Warm the olive oil in a large pan and gently cook the chopped garlic for 1 minute.\n3. Add the spinach and stir until wilted.\n4. Add the pasta, lemon zest, lemon juice and Parmesan. Toss with a little reserved cooking water to make a light sauce.\n5. Season with black pepper and serve immediately.",
                'image_url' => 'https://images.unsplash.com/photo-1473093226795-af9932fe5856?w=800',
                'ingredients' => [
                    ['name' => 'Pasta', 'quantity' => 300, 'unit' => 'g'],
                    ['name' => 'Fresh spinach', 'quantity' => 200, 'unit' => 'g'],
                    ['name' => 'Lemon', 'quantity' => 1, 'unit' => 'pcs'],
                    ['name' => 'Garlic cloves', 'quantity' => 2, 'unit' => 'pcs'],
                    ['name' => 'Olive oil', 'quantity' => 2, 'unit' => 'tbsp'],
                    ['name' => 'Parmesan', 'quantity' => 50, 'unit' => 'g'],
                    ['name' => 'Salt', 'quantity' => 1, 'unit' => 'tsp'],
                    ['name' => 'Black pepper', 'quantity' => 0.25, 'unit' => 'tsp'],
                ],
            ],
            [
                'title' => 'Crispy Rosemary Potatoes',
                'category' => 'side',
                'duration' => 45,
                'description' => 'Golden roasted potatoes with rosemary and garlic.',
                'instructions' => "1. Preheat the oven to 220 C.\n2. Wash the potatoes and cut them into small, even cubes.\n3. Toss with olive oil, chopped rosemary, crushed garlic, salt and pepper.\n4. Spread in a single layer on a baking tray.\n5. Roast for 35 minutes, turning halfway through, until crisp and tender.",
                'image_url' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=800',
                'ingredients' => [
                    ['name' => 'Potatoes', 'quantity' => 800, 'unit' => 'g'],
                    ['name' => 'Olive oil', 'quantity' => 3, 'unit' => 'tbsp'],
                    ['name' => 'Fresh rosemary', 'quantity' => 5, 'unit' => 'g'],
                    ['name' => 'Garlic cloves', 'quantity' => 3, 'unit' => 'pcs'],
                    ['name' => 'Salt', 'quantity' => 0.5, 'unit' => 'tsp'],
                    ['name' => 'Black pepper', 'quantity' => 0.25, 'unit' => 'tsp'],
                ],
            ],
            [
                'title' => 'Chocolate Chip Banana Bread',
                'category' => 'dessert',
                'duration' => 80,
                'description' => 'A moist banana loaf dotted with chocolate chips, ideal for sharing.',
                'instructions' => "1. Preheat the oven to 180 C and grease a loaf tin.\n2. Mash the bananas and mix with melted butter, sugar and eggs.\n3. Fold in the flour and baking powder, then stir in the chocolate chips.\n4. Pour into the tin and bake for 50 minutes, or until a skewer inserted into the centre comes out without wet batter.\n5. Cool in the tin for 15 minutes before transferring to a rack and slicing.",
                'image_url' => 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?w=800',
                'ingredients' => [
                    ['name' => 'Ripe bananas', 'quantity' => 3, 'unit' => 'pcs'],
                    ['name' => 'Plain flour', 'quantity' => 200, 'unit' => 'g'],
                    ['name' => 'Butter', 'quantity' => 80, 'unit' => 'g'],
                    ['name' => 'Brown sugar', 'quantity' => 100, 'unit' => 'g'],
                    ['name' => 'Eggs', 'quantity' => 2, 'unit' => 'pcs'],
                    ['name' => 'Baking powder', 'quantity' => 2, 'unit' => 'tsp'],
                    ['name' => 'Chocolate chips', 'quantity' => 80, 'unit' => 'g'],
                ],
            ],
            [
                'title' => 'Berry and Yogurt Smoothie',
                'category' => 'drink',
                'duration' => 5,
                'description' => 'A refreshing berry smoothie with banana and creamy yogurt.',
                'instructions' => "1. Peel and slice the banana.\n2. Place the banana, frozen berries, yogurt and milk in a blender.\n3. Blend until smooth, adding a splash more milk if needed.\n4. Pour into glasses and serve immediately.",
                'image_url' => 'https://images.unsplash.com/photo-1505252585461-04db1eb84625?w=800',
                'ingredients' => [
                    ['name' => 'Frozen mixed berries', 'quantity' => 200, 'unit' => 'g'],
                    ['name' => 'Banana', 'quantity' => 1, 'unit' => 'pcs'],
                    ['name' => 'Plain yogurt', 'quantity' => 150, 'unit' => 'g'],
                    ['name' => 'Milk', 'quantity' => 200, 'unit' => 'ml'],
                ],
            ],
        ];

        foreach ($englishRecipes as $data) {
            $ingredients = $data['ingredients'];
            unset($data['ingredients']);
            $recipe = Recipe::create([
                ...$data,
                'language' => 'en',
                'is_premium' => false,
                'creator_id' => $users->where('name', 'chef_martin')->first()->id,
            ]);
            $recipe->ingredients()->createMany($ingredients);
        }
    }
}
