<?php

namespace Database\Seeders\Prep;

use App\Models\TroveType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TroveTypeSeeder extends Seeder
{
    public function run(): void
    {
        TroveType::create(["label" => ["en" => "Activity",                   "es" => "Actividad",                   "fr" => "Activité"]]);
        TroveType::create(["label" => ["en" => "Book",                       "es" => "Libro",                       "fr" => "Livre"]]);
        TroveType::create(["label" => ["en" => "Case Study",                 "es" => "Estudio de caso",             "fr" => "Étude de cas"]]);
        TroveType::create(["label" => ["en" => "Curricula / Training Course","es" => "Plan de estudios",            "fr" => "Programme d'études"]]);
        TroveType::create(["label" => ["en" => "Example",                    "es" => "Ejemplo",                     "fr" => "Exemple"]]);
        TroveType::create(["label" => ["en" => "Guide",                      "es" => "Guía",                        "fr" => "Guide"]]);
        TroveType::create(["label" => ["en" => "Journal Article",            "es" => "Artículo científico",         "fr" => "Article scientifique"]]);
        TroveType::create(["label" => ["en" => "Presentation",               "es" => "Presentación",                "fr" => "Présentation"]]);
        TroveType::create(["label" => ["en" => "Tool",                       "es" => "Herramienta",                 "fr" => "Outil"]]);
        TroveType::create(["label" => ["en" => "Video",                      "es" => "Vídeo",                       "fr" => "Vidéo"]]);
        TroveType::create(["label" => ["en" => "Webinar",                    "es" => "Webinario",                   "fr" => "Webinaire"]]);
        TroveType::create(["label" => ["en" => "Website",                    "es" => "Sitio web",                   "fr" => "Site web"]]);
        TroveType::create(["label" => ["en" => "Syllabus",                   "es" => "Programa de estudios",        "fr" => "Syllabus"]]);
    }
}
