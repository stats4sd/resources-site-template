<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\OldCollection;
use App\Models\Trove;
use Illuminate\Console\Command;

class ConvertOldToNewCollections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:convert-old-to-new-collections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'processes all collections from the existing /old database into the new format';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // delete all existing collections
        Collection::query()->delete();

        $oldCollections = OldCollection::all();

        $this->info('Converting ' . $oldCollections->count() . ' collections...');

        $oldCollections->map(function(OldCollection $oldCollection) {
            $newCollection = new Collection();


            $newCollection->id = $oldCollection->id;

            // set locale to english
            $newCollection->setLocale('en');

            $newCollection->title = $oldCollection->title;
            $newCollection->description = $oldCollection->description;
            $newCollection->uploader_id = $oldCollection->uploader_id;
            $newCollection->public = $oldCollection->public ?? false;

            $newCollection->save();


            $oldTroves = $oldCollection->oldTroves()->get();

            $this->info('Collection ' . $oldCollection->id . ' has ' . $oldTroves->count() . ' troves.');

            // find the new trove
            foreach($oldTroves as $oldTrove) {
                $newTrove = Trove::find($oldTrove->id);

                if(!$newTrove) {
                    $newTrove = Trove::firstWhere('slug', $oldTrove->slug);
                }

                if(!$newTrove) {
                    $newTrove = Trove::whereJsonContains('previous_slugs', $oldTrove->slug)->first();
                }

                if(!$newTrove) {
                    dd('cannot find new trove for the old trove ' . $oldTrove->id);
                }

                $newCollection->troves()->attach($newTrove->id);
            }

        });

    }
}
