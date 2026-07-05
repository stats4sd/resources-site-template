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
                'es' => 'Temas',
                'fr' => 'Sujets',
            ],
            'description' => [
                'en' => 'Topics of interest',
                'es' => 'Temas de interés',
                'fr' => 'Sujets d\'intérêt',
            ],
            'freetext' => false,
        ]);

        TagType::create([
            'slug' => 'authors',
            'label' => [
                'en' => 'Authors',
                'es' => 'Autores',
                'fr' => 'Auteurs',
            ],
            'description' => [
                'en' => 'Authors of content',
                'es' => 'Autores del contenido',
                'fr' => 'Auteurs de contenu',
            ],
            'freetext' => true,
        ]);

        TagType::create([
            'slug' => 'locations',
            'label' => [
                'en' => 'Locations',
                'es' => 'Ubicaciones',
                'fr' => 'Lieux',
            ],
            'description' => [
                'en' => 'Geographic locations',
                'es' => 'Ubicaciones geográficas',
                'fr' => 'Lieux géographiques',
            ],
            'freetext' => false,
        ]);

    }
}
