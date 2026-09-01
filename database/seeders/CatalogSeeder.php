<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ServiceTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds all 21 blueprint categories (locked doc 13) with MVP-locked templates (§134).
 * Config drives fulfillment policies — never category-name logic.
 */
class CatalogSeeder extends Seeder
{
    private array $categories = [
        'Technology & Programming' => ['review_dimensions' => ['quality', 'communication', 'deadline', 'requirement_match', 'support']],
        'Design & Creative' => ['review_dimensions' => ['quality', 'communication', 'deadline', 'value']],
        'Digital Marketing' => ['review_dimensions' => ['quality', 'communication', 'deadline', 'result']],
        'Business & Consulting' => ['review_dimensions' => ['quality', 'communication', 'value']],
        'Accounting & Tax' => ['review_dimensions' => ['accuracy', 'communication', 'deadline', 'value']],
        'Legal' => ['review_dimensions' => ['quality', 'communication', 'value']],
        'Cleaning' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'professionalism', 'value']],
        'AC & Electronics' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'professionalism', 'value']],
        'Plumbing' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'professionalism', 'value']],
        'Electrical' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'professionalism', 'value']],
        'Handyman' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'professionalism', 'value']],
        'Renovation' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'professionalism', 'value']],
        'Construction' => ['review_dimensions' => ['quality', 'deadline', 'communication', 'safety', 'value']],
        'CCTV & Security' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'value']],
        'Pest Control' => ['review_dimensions' => ['quality', 'punctuality', 'effectiveness', 'value']],
        'Automotive' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'value']],
        'Moving & Logistics' => ['review_dimensions' => ['care', 'punctuality', 'communication', 'value']],
        'Event Services' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'value']],
        'Photography' => ['review_dimensions' => ['quality', 'communication', 'deadline', 'value']],
        'Education' => ['review_dimensions' => ['quality', 'communication', 'progress', 'value']],
        'Personal Services' => ['review_dimensions' => ['quality', 'punctuality', 'communication', 'value']],
    ];

    // MVP-locked templates §134
    private array $templates = [
        'Cleaning' => [
            ['House Cleaning', 'hourly', 'onsite', 240],
            ['Office Cleaning', 'hourly', 'onsite', 480],
        ],
        'AC & Electronics' => [
            ['AC Cleaning', 'per_unit', 'onsite', 60],
            ['AC Repair', 'survey_required', 'onsite', 90],
            ['AC Installation', 'request_quotation', 'onsite', null],
        ],
        'Handyman' => [
            ['General Handyman Visit', 'survey_required', 'onsite', 120],
            ['Small Installation', 'appointment', 'onsite', 90],
        ],
        'Technology & Programming' => [
            ['Website Development', 'project', 'remote', null],
            ['Mobile App Development', 'milestone_project', 'remote', null],
            ['Bug Fix / Small Task', 'fixed_package', 'remote', 120],
            ['IT Consulting Session', 'appointment', 'remote', 60],
        ],
    ];

    public function run(): void
    {
        foreach ($this->categories as $name => $config) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort' => count($this->categories),
                    'config' => [
                        'review_dimensions' => $config['review_dimensions'],
                        'warranty' => ['default_days' => in_array($name, ['AC & Electronics', 'Handyman', 'Plumbing', 'Electrical']) ? 7 : 0],
                        'sla_defaults' => ['accept_minutes' => 120, 'response_minutes' => 60],
                        'cancellation' => [
                            'free_until_hours' => 24,
                            'cancellation_fee_percent' => 25,
                        ],
                    ],
                ],
            );

            foreach ($this->templates[$name] ?? [] as [$tName, $fulfillment, $delivery, $duration]) {
                ServiceTemplate::firstOrCreate(
                    ['slug' => Str::slug($name.'-'.$tName)],
                    [
                        'category_id' => $category->id,
                        'name' => $tName,
                        'fulfillment_type' => $fulfillment,
                        'delivery_mode' => $delivery,
                        'default_duration_minutes' => $duration,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
