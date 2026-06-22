<?php

namespace Database\Seeders\Prep;

use App\Models\Trove;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Parallax\FilamentComments\Models\FilamentComment;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaTableSeeder extends Seeder
{
    public function run(): void
    {
        // Handle Media table
        $uuids = collect([]);

        $mediaItems = DB::connection('mysql_old_troves')
            ->table('media')
            ->get();

        foreach ($mediaItems as $item) {

            $itemArray = [];
            foreach ($item as $key => $value) {

                // preserve arrays
                if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                    $itemArray[$key] = json_decode($value, true);
                    continue;
                }

                $itemArray[$key] = $value;
            }

            //rename collections to match new structure
            if ($itemArray['model_type'] === Trove::class) {

                $itemArray['collection_name'] = match ($itemArray['collection_name']) {
                    'coverImage' => 'cover_image_en',
                    'troveFiles' => 'content_en',
                    default => $itemArray['collection_name'],
                };
            }

            $itemArray['disk'] = config('media-library.disk_name');
            $itemArray['conversions_disk'] = config('media-library.disk_name');
            $itemArray['generated_conversions'] = [];

            // if the uuid is identical, we can overwrite the old one
            if ($uuids->contains($itemArray['uuid'])) {
                Media::firstWhere('uuid', $itemArray['uuid'])->updateQuietly($itemArray);
            } else {
                Media::create($itemArray);
            }

            $uuids[] = $itemArray['uuid'];

        };


        // handle old trove comments
        $comments = DB::connection('mysql_old_troves')
            ->table('trove_comments')
            ->get()
            ->each(function ($comment) {
                FilamentComment::create([
                    'subject_type' => Trove::class,
                    'subject_id' => $comment->trove_id,
                    'user_id' => $comment->user_id,
                    'comment' => $comment->comment,
                ]);
            });

    }
}
