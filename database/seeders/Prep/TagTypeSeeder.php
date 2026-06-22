<?php

namespace Database\Seeders\Prep;

use App\Models\TagType;
use Illuminate\Database\Seeder;

class TagTypeSeeder extends Seeder
{

    public function run(): void
    {
        TagType::create([
            'slug' => 'topics',
            'label' => [
                'en' => 'Topics',
                'fr' => 'Sujets',
            ],
            'description' => [
                'en' => 'Topics of interest',
                'fr' => 'Sujets d\'intérêt',
            ],
            'freetext' => false,
        ]);

        TagType::create([
            'slug' => 'keywords',
            'label' => [
                'en' => 'Keywords',
                'fr' => 'Mots-clés',
            ],
            'description' => [
                'en' => 'Keywords for search',
                'fr' => 'Mots-clés pour la recherche',
            ],
            'freetext' => true,
        ]);

        TagType::create([
            'slug' => 'audiences',
            'label' => [
                'en' => 'Audiences',
                'fr' => 'Audiences',
            ],
            'description' => [
                'en' => 'Target audiences',
                'fr' => 'Public cible',
            ],
            'freetext' => false,
        ]);

        TagType::create([
            'slug' => 'themes',
            'label' => [
                'en' => 'Themes',
                'fr' => 'Thèmes',
            ],
            'description' => [
                'en' => 'Themes of interest',
                'fr' => 'Thèmes d\'intérêt',
            ],
            'freetext' => false,
        ]);

        TagType::create([
            'slug' => 'authors',
            'label' => [
                'en' => 'Authors',
                'fr' => 'Auteurs',
            ],
            'description' => [
                'en' => 'Authors of content',
                'fr' => 'Auteurs de contenu',
            ],
            'freetext' => true,
        ]);

        TagType::create([
            'slug' => 'locations',
            'label' => [
                'en' => 'Locations',
                'fr' => 'Lieux',
            ],
            'description' => [
                'en' => 'Geographic locations',
                'fr' => 'Lieux géographiques',
            ],
            'freetext' => false,
        ]);

    }
}
