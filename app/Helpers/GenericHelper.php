<?php

namespace App\Helpers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GenericHelper
{
    public static function readCsvFileIntoCollection(string $filepath): Collection
    {
        //$csvFileContent = trim(File::get($filepath));

        $file = fopen($filepath, 'rb');


        $header = null;
        $lines = [];

        while(($data = fgetcsv($file)) !== FALSE){

            if(!$header) {
                $header = $data;
                continue;
            }

            $lines[] = $data;
        }

        $header = collect($header)
            // remove \u{FEFF} from the beginning of the first header
            ->map(fn($key) => preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $key));

        return collect($lines)->map(fn($row) => $header->combine($row));
    }

    public static function getTidiedString(string $string): string
    {
        return Str::of($string)->trim()->title()->replaceMatches('/\s+/', ' ')->toString();
    }
}
