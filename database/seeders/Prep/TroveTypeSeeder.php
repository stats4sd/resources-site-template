<?php

namespace Database\Seeders\Prep;

use App\Models\TroveType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TroveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TroveType::create(["label" => ["en" => "Activity", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "App"]]);
        TroveType::create(["label" => ["en" => "Book"]]);
        TroveType::create(["label" => ["en" => "Booklet"]]);
        TroveType::create(["label" => ["en" => "Case Study", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Checklist", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Curricula / Training Course", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Diagram", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Example"]]);
        TroveType::create(["label" => ["en" => "Guide", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Infographic", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Journal Article", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Leaflet", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "list of Resources"]]);
        TroveType::create(["label" => ["en" => "Manual"]]);
        TroveType::create(["label" => ["en" => "Meeting Recording", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Organisational Diagram", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Picture", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Poster", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Presentation", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Questionnaire"]]);
        TroveType::create(["label" => ["en" => "Reference", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Report"]]);
        TroveType::create(["label" => ["en" => "Script", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Survey", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Template", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Textbook", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Tool"]]);
        TroveType::create(["label" => ["en" => "Video", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Webinar", "es" => "", "fr" => ""]]);
        TroveType::create(["label" => ["en" => "Website"]]);
        TroveType::create(["label" => ["en" => "XLS-form", "es" => "", "fr" => ""]]);

    }
}
