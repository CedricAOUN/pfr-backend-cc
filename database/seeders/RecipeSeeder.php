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
            'description' => 'Une délicieuse tarte aux pommes à l\'ancienne, parfaite pour le dessert.',
            'instructions' => 'Préchauffer le four à 180°C. Éplucher et couper les pommes en fines tranches. Disposer la pâte dans un moule, piquer le fond. Disposer les pommes en rosace. Saupoudrer de sucre et de cannelle. Parsemer de noisettes de beurre. Enfourner 35 minutes.',
            'is_premium' => false,
            'creator_id' => $users->where('is_expert', true)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=800',
        ]);

        foreach ([
            ['name' => 'Pâte brisée', 'quantity' => 1, 'unit' => 'pâte'],
            ['name' => 'Pommes', 'quantity' => 5, 'unit' => 'pièces'],
            ['name' => 'Sucre', 'quantity' => 50, 'unit' => 'g'],
            ['name' => 'Cannelle', 'quantity' => 1, 'unit' => 'c. à café'],
            ['name' => 'Beurre', 'quantity' => 30, 'unit' => 'g']
        ] as $ing) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe1->id]));
        }

        $recipe2 = Recipe::create([
            'title' => 'Risotto aux champignons',
            'description' => 'Un risotto crémeux aux champignons de Paris et cèpes séchés.',
            'instructions' => 'Réhydrater les cèpes. Faire revenir l\'oignon dans le beurre. Ajouter le riz et nacrer. Déglacer au vin blanc. Ajouter le bouillon louche par louche en remuant. À mi-cuisson, ajouter les champignons. Finir avec le parmesan et une noix de beurre.',
            'is_premium' => true,
            'creator_id' => $users->where('is_expert', true)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=800',
        ]);

        foreach ([
            ['name' => 'Riz arborio', 'quantity' => 300, 'unit' => 'g'],
            ['name' => 'Champignons de Paris', 'quantity' => 200, 'unit' => 'g'],
            ['name' => 'Cèpes séchés', 'quantity' => 20, 'unit' => 'g'],
            ['name' => 'Oignon', 'quantity' => 1, 'unit' => 'pièce'],
            ['name' => 'Bouillon de légumes', 'quantity' => 1, 'unit' => 'L'],
            ['name' => 'Vin blanc', 'quantity' => 100, 'unit' => 'ml'],
            ['name' => 'Parmesan râpé', 'quantity' => 50, 'unit' => 'g'],
            ['name' => 'Beurre', 'quantity' => 30, 'unit' => 'g']
        ] as $ing) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe2->id]));
        }

        $recipe3 = Recipe::create([
            'title' => 'Poulet rôti aux herbes',
            'description' => 'Un classique indémodable, le poulet rôti parfaitement doré et parfumé.',
            'instructions' => 'Préchauffer le four à 200°C. Préparer un beurre aux herbes. Glisser le beurre sous la peau du poulet. Mettre le citron et l\'ail dans la cavité. Saler, poivrer. Enfourner 1h15 en arrosant régulièrement.',
            'is_premium' => false,
            'creator_id' => $users->where('name', 'chef_sophie')->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1598103442097-8b74394b95c6?w=800',
        ]);

        foreach ([
            ['name' => 'Poulet entier', 'quantity' => 1, 'unit' => 'pièce'],
            ['name' => 'Beurre', 'quantity' => 50, 'unit' => 'g'],
            ['name' => 'Thym', 'quantity' => 2, 'unit' => 'brins'],
            ['name' => 'Romarin', 'quantity' => 2, 'unit' => 'brins'],
            ['name' => 'Citron', 'quantity' => 1, 'unit' => 'pièce'],
            ['name' => 'Ail', 'quantity' => 4, 'unit' => 'gousses'],
            ['name' => 'Sel', 'quantity' => 1, 'unit' => 'c. à café'],
            ['name' => 'Poivre', 'quantity' => 0.5, 'unit' => 'c. à café']
        ] as $ing) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe3->id]));
        }

        $recipe4 = Recipe::create([
            'title' => 'Soupe de légumes d\'hiver',
            'description' => 'Une soupe réconfortante avec des légumes de saison.',
            'instructions' => 'Éplucher et couper tous les légumes en morceaux. Les mettre dans une grande casserole avec de l\'eau. Porter à ébullition et cuire 30 minutes. Mixer. Assaisonner et servir avec une cuillère de crème fraîche.',
            'is_premium' => false,
            'creator_id' => $users->where('name', 'jean_cook')->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=800',
        ]);

        foreach ([
            ['name' => 'Carottes', 'quantity' => 3, 'unit' => 'pièces'],
            ['name' => 'Pomme de terre', 'quantity' => 2, 'unit' => 'pièces'],
            ['name' => 'Poireau', 'quantity' => 1, 'unit' => 'pièce'],
            ['name' => 'Oignon', 'quantity' => 1, 'unit' => 'pièce'],
            ['name' => 'Bouillon de légumes', 'quantity' => 1, 'unit' => 'L'],
            ['name' => 'Sel', 'quantity' => 1, 'unit' => 'c. à café'],
            ['name' => 'Poivre', 'quantity' => 0.5, 'unit' => 'c. à café']
        ] as $ing) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe4->id]));
        }

        $recipe5 = Recipe::create([
            'title' => 'Crème brûlée à la vanille',
            'description' => 'Le dessert français par excellence, crémeux avec une croûte caramélisée.',
            'instructions' => 'Chauffer la crème avec la vanille fendue. Fouetter les jaunes avec le sucre. Verser la crème chaude sur les jaunes en fouettant. Répartir dans des ramequins. Cuire au bain-marie à 150°C pendant 45 min. Réfrigérer. Saupoudrer de sucre et caraméliser au chalumeau.',
            'is_premium' => true,
            'creator_id' => $users->where('is_expert', true)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1470124182917-cc6e71b22ecc?w=800',
        ]);

        foreach ([
            ['name' => 'Crème liquide', 'quantity' => 500, 'unit' => 'ml'],
            ['name' => 'Jaunes d\'œufs', 'quantity' => 6, 'unit' => 'pièces'],
            ['name' => 'Sucre', 'quantity' => 100, 'unit' => 'g'],
            ['name' => 'Gousses de vanille', 'quantity' => 2, 'unit' => 'pièces']
        ] as $ing) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe5->id]));
        }

        $recipe6 = Recipe::create([
            'title' => 'Salade César maison',
            'description' => 'La célèbre salade avec sa sauce crémeuse et ses croûtons croustillants.',
            'instructions' => 'Préparer les croûtons dorés à l\'ail. Griller le poulet. Préparer la sauce: mixer jaune d\'œuf, ail, anchois, citron, puis émulsionner avec l\'huile. Mélanger la salade avec la sauce, ajouter poulet, croûtons et copeaux de parmesan.',
            'is_premium' => false,
            'creator_id' => $users->where('name', 'marie_foodie')->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1550304943-4f24f54ddde9?w=800',
        ]);

        foreach ([
            ['name' => 'Laitue romaine', 'quantity' => 1, 'unit' => 'pièce'],
            ['name' => 'Poulet grillé', 'quantity' => 200, 'unit' => 'g'],
            ['name' => 'Croûtons à l\'ail', 'quantity' => 50, 'unit' => 'g'],
            ['name' => 'Parmesan en copeaux', 'quantity' => 30, 'unit' => 'g'],
            ['name' => 'Jaune d\'œuf', 'quantity' => 1, 'unit' => 'pièce'],
            ['name' => 'Ail', 'quantity' => 1, 'unit' => 'gousse'],
            ['name' => 'Anchois', 'quantity' => 2, 'unit' => 'filets'],
            ['name' => 'Citron', 'quantity' => 0.5, 'unit' => 'pièce'],
            ['name' => 'Huile d\'olive', 'quantity' => 100, 'unit' => 'ml']
        ] as $ing) {
            Ingredient::create(array_merge($ing, ['recipe_id' => $recipe6->id]));
        }
    }
}
