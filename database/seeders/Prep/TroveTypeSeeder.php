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
        TroveType::create(["label" => ["en" => "App",                        "es" => "App",                         "fr" => "Appli"]]);
        TroveType::create(["label" => ["en" => "Book",                       "es" => "Libro",                       "fr" => "Livre"]]);
        TroveType::create(["label" => ["en" => "Booklet",                    "es" => "Folleto",                     "fr" => "Livret"]]);
        TroveType::create(["label" => ["en" => "Case Study",                 "es" => "Estudio de caso",             "fr" => "Étude de cas"]]);
        TroveType::create(["label" => ["en" => "Checklist",                  "es" => "Checklist",                   "fr" => "Checklist"]]);
        TroveType::create(["label" => ["en" => "Curricula / Training Course","es" => "Plan de estudios",            "fr" => "Programme d'études"]]);
        TroveType::create(["label" => ["en" => "Diagram",                    "es" => "Diagrama",                    "fr" => "Diagramme"]]);
        TroveType::create(["label" => ["en" => "Example",                    "es" => "Ejemplo",                     "fr" => "Exemple"]]);
        TroveType::create(["label" => ["en" => "Guide",                      "es" => "Guía",                        "fr" => "Guide"]]);
        TroveType::create(["label" => ["en" => "Infographic",                "es" => "Infografía",                  "fr" => "Infographie"]]);
        TroveType::create(["label" => ["en" => "Journal Article",            "es" => "Artículo científico",         "fr" => "Article scientifique"]]);
        TroveType::create(["label" => ["en" => "Leaflet",                    "es" => "Folleto",                     "fr" => "Dépliant"]]);
        TroveType::create(["label" => ["en" => "List of Resources",          "es" => "Lista de recursos",           "fr" => "Liste de ressources"]]);
        TroveType::create(["label" => ["en" => "Manual",                     "es" => "Manual",                      "fr" => "Manuel"]]);
        TroveType::create(["label" => ["en" => "Meeting Recording",          "es" => "Grabación de una reunión",    "fr" => "Enregistrement d'une réunion"]]);
        TroveType::create(["label" => ["en" => "Organisational Diagram",     "es" => "Organigrama",                 "fr" => "Organigramme"]]);
        TroveType::create(["label" => ["en" => "Picture",                    "es" => "Imagen",                      "fr" => "Illustration"]]);
        TroveType::create(["label" => ["en" => "Poster",                     "es" => "Poster",                      "fr" => "Poster"]]);
        TroveType::create(["label" => ["en" => "Presentation",               "es" => "Presentación",                "fr" => "Présentation"]]);
        TroveType::create(["label" => ["en" => "Questionnaire",              "es" => "Cuestionario",                "fr" => "Questionnaire"]]);
        TroveType::create(["label" => ["en" => "Reference",                  "es" => "Referencia",                  "fr" => "Référence"]]);
        TroveType::create(["label" => ["en" => "Report",                     "es" => "Informe",                     "fr" => "Rapport"]]);
        TroveType::create(["label" => ["en" => "Script",                     "es" => "Script",                      "fr" => "Script"]]);
        TroveType::create(["label" => ["en" => "Survey",                     "es" => "Encuesta",                    "fr" => "Enquête"]]);
        TroveType::create(["label" => ["en" => "Syllabus",                   "es" => "Programa de estudios",        "fr" => "Syllabus"]]);
        TroveType::create(["label" => ["en" => "Template",                   "es" => "Plantilla",                   "fr" => "Modèle"]]);
        TroveType::create(["label" => ["en" => "Textbook",                   "es" => "Libro de texto",              "fr" => "Manuel scolaire"]]);
        TroveType::create(["label" => ["en" => "Tool",                       "es" => "Herramienta",                 "fr" => "Outil"]]);
        TroveType::create(["label" => ["en" => "Video",                      "es" => "Vídeo",                       "fr" => "Vidéo"]]);
        TroveType::create(["label" => ["en" => "Webinar",                    "es" => "Webinario",                   "fr" => "Webinaire"]]);
        TroveType::create(["label" => ["en" => "Website",                    "es" => "Sitio web",                   "fr" => "Site web"]]);
        TroveType::create(["label" => ["en" => "XLS-form",                   "es" => "XLS-form",                    "fr" => "XLS-form"]]);
    }
}
