<?php

namespace Database\Seeders;

use App\Models\Recipe;
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

        Recipe::create([
            'title' => 'Tarte aux pommes traditionnelle',
            'description' => 'Une délicieuse tarte aux pommes à l\'ancienne, parfaite pour le dessert.',
            'ingredients' => json_encode([
                '6 pommes Golden',
                '1 pâte feuilletée',
                '50g de beurre',
                '80g de sucre',
                '1 sachet de sucre vanillé',
                'Cannelle (optionnel)'
            ]),
            'instructions' => 'Préchauffer le four à 180°C. Éplucher et couper les pommes en fines tranches. Disposer la pâte dans un moule, piquer le fond. Disposer les pommes en rosace. Saupoudrer de sucre et de cannelle. Parsemer de noisettes de beurre. Enfourner 35 minutes.',
            'is_premium' => false,
            'creator_id' => $users->where('is_expert', true)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=800',
        ]);

        Recipe::create([
            'title' => 'Risotto aux champignons',
            'description' => 'Un risotto crémeux aux champignons de Paris et cèpes séchés.',
            'ingredients' => json_encode([
                '300g de riz arborio',
                '200g de champignons de Paris',
                '20g de cèpes séchés',
                '1 oignon',
                '1L de bouillon de légumes',
                '100ml de vin blanc',
                '50g de parmesan râpé',
                '30g de beurre'
            ]),
            'instructions' => 'Réhydrater les cèpes. Faire revenir l\'oignon dans le beurre. Ajouter le riz et nacrer. Déglacer au vin blanc. Ajouter le bouillon louche par louche en remuant. À mi-cuisson, ajouter les champignons. Finir avec le parmesan et une noix de beurre.',
            'is_premium' => true,
            'creator_id' => $users->where('is_expert', true)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=800',
        ]);

        Recipe::create([
            'title' => 'Poulet rôti aux herbes',
            'description' => 'Un classique indémodable, le poulet rôti parfaitement doré et parfumé.',
            'ingredients' => json_encode([
                '1 poulet fermier (1,5kg)',
                'Thym frais',
                'Romarin',
                '4 gousses d\'ail',
                '50g de beurre',
                'Sel, poivre',
                '1 citron'
            ]),
            'instructions' => 'Préchauffer le four à 200°C. Préparer un beurre aux herbes. Glisser le beurre sous la peau du poulet. Mettre le citron et l\'ail dans la cavité. Saler, poivrer. Enfourner 1h15 en arrosant régulièrement.',
            'is_premium' => false,
            'creator_id' => $users->where('name', 'chef_sophie')->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1598103442097-8b74394b95c6?w=800',
        ]);

        Recipe::create([
            'title' => 'Soupe de légumes d\'hiver',
            'description' => 'Une soupe réconfortante avec des légumes de saison.',
            'ingredients' => json_encode([
                '3 carottes',
                '2 pommes de terre',
                '1 poireau',
                '1 navet',
                '1 oignon',
                'Sel, poivre',
                'Crème fraîche'
            ]),
            'instructions' => 'Éplucher et couper tous les légumes en morceaux. Les mettre dans une grande casserole avec de l\'eau. Porter à ébullition et cuire 30 minutes. Mixer. Assaisonner et servir avec une cuillère de crème fraîche.',
            'is_premium' => false,
            'creator_id' => $users->where('name', 'jean_cook')->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=800',
        ]);

        Recipe::create([
            'title' => 'Crème brûlée à la vanille',
            'description' => 'Le dessert français par excellence, crémeux avec une croûte caramélisée.',
            'ingredients' => json_encode([
                '500ml de crème liquide',
                '6 jaunes d\'œufs',
                '100g de sucre + extra pour caraméliser',
                '2 gousses de vanille'
            ]),
            'instructions' => 'Chauffer la crème avec la vanille fendue. Fouetter les jaunes avec le sucre. Verser la crème chaude sur les jaunes en fouettant. Répartir dans des ramequins. Cuire au bain-marie à 150°C pendant 45 min. Réfrigérer. Saupoudrer de sucre et caraméliser au chalumeau.',
            'is_premium' => true,
            'creator_id' => $users->where('is_expert', true)->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1470124182917-cc6e71b22ecc?w=800',
        ]);

        Recipe::create([
            'title' => 'Salade César maison',
            'description' => 'La célèbre salade avec sa sauce crémeuse et ses croûtons croustillants.',
            'ingredients' => json_encode([
                '1 laitue romaine',
                '100g de parmesan',
                '4 tranches de pain',
                '2 filets de poulet',
                '1 jaune d\'œuf',
                '1 gousse d\'ail',
                'Anchois',
                'Huile d\'olive',
                'Jus de citron'
            ]),
            'instructions' => 'Préparer les croûtons dorés à l\'ail. Griller le poulet. Préparer la sauce: mixer jaune d\'œuf, ail, anchois, citron, puis émulsionner avec l\'huile. Mélanger la salade avec la sauce, ajouter poulet, croûtons et copeaux de parmesan.',
            'is_premium' => false,
            'creator_id' => $users->where('name', 'marie_foodie')->first()->id,
            'image_url' => 'https://images.unsplash.com/photo-1550304943-4f24f54ddde9?w=800',
        ]);
    }
}
