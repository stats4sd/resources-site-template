<?php

use App\Support\VideoLink\LegacyYoutubeLinksConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('troves', function (Blueprint $table) {
            $table->renameColumn('youtube_links', 'video_links');
        });

        DB::table('troves')
            ->whereNotNull('video_links')
            ->orderBy('id')
            ->chunkById(100, function ($troves) {
                foreach ($troves as $trove) {
                    $converted = LegacyYoutubeLinksConverter::convertTranslations(
                        json_decode($trove->video_links, true)
                    );

                    DB::table('troves')->where('id', $trove->id)->update([
                        'video_links' => $converted === null ? null : json_encode($converted),
                    ]);
                }
            });
    }
};
