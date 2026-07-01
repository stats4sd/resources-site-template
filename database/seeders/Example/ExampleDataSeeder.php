<?php

namespace Database\Seeders\Example;

use App\Models\Collection;
use App\Models\TagType;
use App\Models\Trove;
use App\Models\TroveType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds example tags, troves, and collections for local development.
 *
 * Run with: php artisan db:seed --class="Database\Seeders\Example\ExampleDataSeeder"
 *
 * Requires: TagTypeSeeder and TroveTypeSeeder to have run first (via db:seed).
 * Also requires at least one user — run TestSeeder or create a user via the admin panel first.
 */
class ExampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (!$user) {
            $this->command->error('No users found. Run the main seeder or create a user first.');
            return;
        }

        // Sample tags
        $topicType    = TagType::where('slug', 'topics')->first();
        $authorType   = TagType::where('slug', 'authors')->first();
        $locationType = TagType::where('slug', 'locations')->first();

        $topicTags = $topicType ? $topicType->tags()->createMany([
            ['name' => ['en' => 'Data Analysis',          'es' => 'Análisis de datos']],
            ['name' => ['en' => 'Sampling',               'es' => 'Muestreo']],
            ['name' => ['en' => 'Survey Design']],
            ['name' => ['en' => 'Agroecology',            'es' => 'Agroecología', 'fr' => 'Agroécologie']],
            ['name' => ['en' => 'Monitoring and Evaluation']],
        ]) : collect();

        $authorTags = collect();
        if ($authorType) {
            foreach ([
                ['name' => ['en' => 'John Smith']],
                ['name' => ['en' => 'Maria García']],
                ['name' => ['en' => 'Sophie Leclerc']],
            ] as $tagData) {
                $authorTags->push($authorType->tags()->create($tagData));
            }
        }

        if ($locationType) {
            $locationType->tags()->createMany([
                ['name' => ['en' => 'Africa']],
                ['name' => ['en' => 'Kenya']],
                ['name' => ['en' => 'Bolivia']],
                ['name' => ['en' => 'Global']],
            ]);
        }

        $troveType     = TroveType::first();
        $firstTopicTag = $topicTags->first();
        [$john, $maria, $sophie] = [$authorTags->get(0), $authorTags->get(1), $authorTags->get(2)];

        $troveData = [
            [
                'title'          => ['en' => 'Introduction to Data Analysis', 'es' => 'Introducción al análisis de datos'],
                'description'    => ['en' => 'A beginner-friendly guide to data analysis techniques used in agricultural research contexts.'],
                'external_links' => ['en' => [['link_url' => 'https://www.stats4sd.org', 'link_title' => 'Stats4SD Website']]],
                'youtube_links'  => ['en' => [['youtube_id' => 'q76bMs-NwRk']]],
                'authors'        => array_filter([$john, $maria]),
            ],
            [
                'title'          => ['en' => 'Field Research Methods', 'es' => 'Métodos de investigación de campo'],
                'description'    => ['en' => 'Practical approaches to conducting field research, including data collection and quality assurance.'],
                'external_links' => ['en' => [['link_url' => 'https://www.stats4sd.org', 'link_title' => 'Stats4SD Website']]],
                'youtube_links'  => ['en' => [['youtube_id' => 'xNN7iTA57jM']]],
                'authors'        => array_filter([$john]),
            ],
            [
                'title'          => ['en' => 'Survey Design Handbook'],
                'description'    => ['en' => 'How to design effective surveys for agricultural research, covering sampling and questionnaire structure.'],
                'external_links' => ['en' => [['link_url' => 'https://www.stats4sd.org', 'link_title' => 'Stats4SD Website']]],
                'authors'        => array_filter([$sophie]),
            ],
            [
                'title'          => ['en' => 'Data Visualisation Guide'],
                'description'    => ['en' => 'Best practices for visualising research data clearly and accessibly for different audiences.'],
                'youtube_links'  => ['en' => [
                    ['youtube_id' => 'q76bMs-NwRk'],
                    ['youtube_id' => 'xNN7iTA57jM'],
                ]],
                'authors'        => array_filter([$maria, $sophie]),
            ],
            [
                'title'          => ['en' => 'Agroecology Training Materials', 'fr' => 'Matériel de formation en agroécologie'],
                'description'    => ['en' => 'Training resources for agroecology practitioners working in smallholder farming contexts.', 'fr' => 'Ressources de formation pour les praticiens de l\'agroécologie.'],
                'external_links' => ['en' => [['link_url' => 'https://www.stats4sd.org', 'link_title' => 'Stats4SD Website']]],
                'youtube_links'  => ['en' => [['youtube_id' => 'xNN7iTA57jM']]],
                'authors'        => array_filter([$sophie]),
            ],
        ];

        $troves = [];
        Trove::withoutSyncingToSearch(function () use (&$troves, $troveData, $troveType, $firstTopicTag, $user) {
            foreach ($troveData as $data) {
                $trove = new Trove();
                $trove->title          = $data['title'];
                $trove->description    = $data['description'];
                $trove->external_links = $data['external_links'] ?? null;
                $trove->youtube_links  = $data['youtube_links'] ?? null;
                $trove->source         = false;
                $trove->creation_date  = '2024-01-01';
                $trove->uploader_id    = $user->id;
                $trove->is_published   = true;
                $trove->is_current     = true;
                $trove->save();

                if ($troveType) {
                    $trove->troveTypes()->sync([$troveType->id]);
                }

                $tagIds = array_filter([
                    $firstTopicTag?->id,
                    ...collect($data['authors'] ?? [])->pluck('id')->toArray(),
                ]);
                if ($tagIds) {
                    $trove->tags()->sync($tagIds);
                }

                $troves[] = $trove;
            }
        });

        Collection::withoutSyncingToSearch(function () use ($troves, $user) {
            $collection1 = Collection::create([
                'title'       => ['en' => 'Getting Started with Research', 'es' => 'Comenzar con la investigación'],
                'description' => ['en' => 'A curated collection for new researchers.'],
                'uploader_id' => $user->id,
                'public'      => true,
            ]);
            $collection1->troves()->sync([$troves[0]->id, $troves[1]->id, $troves[2]->id]);

            $collection2 = Collection::create([
                'title'       => ['en' => 'Agroecology Resources'],
                'description' => ['en' => 'Resources focused on agroecological research methods.'],
                'uploader_id' => $user->id,
                'public'      => true,
            ]);
            $collection2->troves()->sync([$troves[2]->id, $troves[3]->id, $troves[4]->id]);
        });

        $this->command->info('Example data seeded: ' . count($troves) . ' troves, 2 collections.');
    }
}
