<?php

namespace Database\Seeders\Prep;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

/**
 * Replaces the generic SiteContentSeeder for this IFA-specific site: the generic
 * home/library page keys it seeds belong to home.blade.php and browse-all.blade.php,
 * neither of which is routed here (routes/web.php serves home-ifa/about/students
 * instead) — so those keys are removed rather than left as orphaned rows editable
 * from nowhere. footer_admin_login_label is still live (footer.blade.php) and kept.
 */
class SiteContentIfaSeeder extends Seeder
{
    private const OBSOLETE_KEYS = [
        'home_heading_line1',
        'home_heading_line2',
        'home_intro',
        'library_heading_line1',
        'library_heading_line2',
        'library_hero_description',
    ];

    public function run(): void
    {
        SiteContent::whereIn('key', self::OBSOLETE_KEYS)->delete();

        $locales = array_keys(config('branding.locales', ['en' => 'English']));
        $defaultLocale = $locales[0];

        $defaults = [
            'footer_admin_login_label' => [
                $defaultLocale => 'Staff Login',
            ],
            'nav_library_home_label' => [
                $defaultLocale => 'Library Home',
            ],
            'shared_hero_heading' => [
                $defaultLocale => 'Resource Library: Education for Agroecological Transformations',
            ],
            'home_ifa_heading_line2' => [
                $defaultLocale => 'Agroecology in higher education',
            ],
            'home_ifa_intro' => [
                $defaultLocale => "Welcome to our Let's E.A.T (Educate for Agroecological Transformations) resource library. This is a co-created resource offered by our community of practice consisting of people working to develop transformative agroecology programmes in higher education.\n\nIn this resource library you can find courses, modules and reading to guide and inform theory, methods and hands-on practice-based pedagogies applicable for adaptation across different territories and cultural contexts.\n\nThese resources are made freely available by the [Let's EAT community of practice](/about).",
            ],
            'home_ifa_collections_heading' => [
                $defaultLocale => 'Explore collections',
            ],
            'home_ifa_collections_intro' => [
                $defaultLocale => "The collections below have been carefully chosen to highlight specific topics or themes present in the resources within the Let's EAT hub. They may be a useful starting point to get an idea of what this hub contains.",
            ],
            'home_ifa_resources_heading' => [
                $defaultLocale => 'Explore resources',
            ],
            'home_ifa_topics_heading' => [
                $defaultLocale => 'Explore selected topics',
            ],
            'home_ifa_institutions_heading' => [
                $defaultLocale => 'Explore Institutions',
            ],
            'home_ifa_syllabi_heading' => [
                $defaultLocale => 'Browse by programme curricula or course syllabi',
            ],
            'about_ifa_heading_line2' => [
                $defaultLocale => 'About us',
            ],
            'about_ifa_body' => [
                $defaultLocale => "Let's Educate for Agroecological Transformations is an international community of practice focusing on transformative learning for agroecology in higher education consisting of people who are either already teaching, or who are building agroecology programmes/courses in HE.\n\nLet's EAT was formed in 2024 as an international community of practice, currently stewarded by the Institute for Agroecology at the University of Vermont. Together, our programmes consist of undergraduate, graduate and/or post-graduate courses, and extend to continuing education aimed practitioners and professionals in the field of agroecology. We are committed to co-creating and inspiring transformative agroecological learning that transgresses formal-informal education boundaries to create learning spaces that connect academic knowledge with community and movement building practices across multiple contexts.",
            ],
            'students_ifa_heading_line2' => [
                $defaultLocale => 'Information for students',
            ],
            'students_ifa_intro' => [
                $defaultLocale => 'Below you will find information about the various programs and courses run by our member institutions.',
            ],
        ];

        foreach ($defaults as $key => $value) {
            SiteContent::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
