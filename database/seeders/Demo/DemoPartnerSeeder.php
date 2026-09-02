<?php

namespace Database\Seeders\Demo;

use App\Models\Partner;
use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoDictionary;
use App\Support\Demo\DemoMediaPool;
use App\Support\Demo\DemoNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ~2,500 demo providers: 60% individual/freelancer, 30% UMKM vendor, 10% company.
 * Verification states are DATA; public "level" badges stay computed by
 * ProviderWebController logic (completed_jobs + rating_avg + rating_count) â€”
 * never hardcoded badges.
 */
class DemoPartnerSeeder extends Seeder
{
    public function run(DemoContext $ctx, int $providers, array $providerIds): array
    {
        $existing = Partner::where('is_demo', true)->count();
        $toCreate = max(0, $providers - $existing);

        if ($toCreate === 0) {
            return $this->existingMap($providers);
        }

        $bar = $this->command?->getOutput()->createProgressBar($toCreate);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar?->setMessage('Partners');
        $bar?->start();

        // Composition: 60% individual/freelancer, 30% vendor_company(UMKM), 10% vendor_company
        $typeRoll = [];
        for ($i = 0; $i < $toCreate; $i++) {
            $r = mt_rand(1, 100);
            $typeRoll[] = $r <= 60 ? 'individual' : ($r <= 90 ? 'vendor_company' : 'vendor_company');
        }

        $catSlugs = array_keys(DemoDictionary::SERVICE_WEIGHTS);

        $rows = [];
        $meta = [];
        for ($i = 0; $i < $toCreate; $i++) {
            $userId = $providerIds[$i] ?? null;
            if (! $userId) {
                break;
            }

            $type = $typeRoll[$i];
            $city = $ctx->randomCity();
            $categorySlug = $catSlugs[$i % count($catSlugs)];
            $dict = app(DemoDictionary::class)->category($categorySlug);

            $displayName = $type === 'individual'
                ? DemoNames::personMale()
                : ($type === 'vendor_company' && mt_rand(1, 100) <= 70
                    ? DemoNames::company()
                    : DemoNames::personMale());

            $isCompany = $type === 'vendor_company';
            $slug = Str::slug($displayName).'-'.Str::lower(Str::random(4));
            [$lat, $lng] = $ctx->jitter($city['lat'] ?? -6.2, $city['lng'] ?? 106.8, 8);

            // verification: verified 75%, submitted/under_review/unverified 25%
            $vr = mt_rand(1, 100);
            $verification = $vr <= 75 ? 'verified' : ($vr <= 85 ? 'submitted' : ($vr <= 92 ? 'under_review' : 'unverified'));

            $rows[] = [
                'user_id' => $userId,
                'type' => $isCompany ? 'vendor_company' : ($i % 5 === 0 ? 'freelancer' : 'individual'),
                'display_name' => $displayName,
                'slug' => $slug,
                'about' => $this->about($categorySlug, $isCompany),
                'avatar_path' => DemoMediaPool::avatar($i),
                'verification_state' => $verification,
                'online_status' => $verification === 'verified' ? ['online', 'busy', 'offline'][mt_rand(0, 2)] : 'offline',
                'city' => $city['name'],
                'lat' => $lat,
                'lng' => $lng,
                'is_demo' => true,
                'acceptance_rate' => mt_rand(72, 100),
                'response_minutes' => [15, 20, 25, 30, 45, 60, 90, 120][mt_rand(0, 7)],
                'created_at' => now()->subDays(mt_rand(60, 500)),
                'updated_at' => now(),
            ];

            $meta[] = ['categorySlug' => $categorySlug, 'type' => $type, 'isCompany' => $isCompany, 'city' => $city];
        }

        foreach (array_chunk($rows, 500) as $ci => $chunk) {
            DB::table('partners')->insert($chunk);
            $bar?->advance(count($chunk));
        }
        $bar?->finish();
        $this->command?->getOutput()->writeln('');

        $partners = DB::table('partners')->where('is_demo', true)->orderBy('id')->get(['id', 'display_name', 'slug', 'type']);

        $this->seedOrganizationExtras($ctx, $partners, $meta);
        $this->seedSkills($ctx, $partners, $meta, $catSlugs);
        $this->seedServiceAreas($ctx, $partners, $meta);

        return $this->buildMap($partners, $meta);
    }

    private function about(string $categorySlug, bool $isCompany): string
    {
        $dict = app(DemoDictionary::class)->category($categorySlug);

        return ($isCompany ? 'Bersama tim berpengalaman, ' : 'Saya ').$dict['descriptions'][mt_rand(0, count($dict['descriptions']) - 1)];
    }

    private function seedOrganizationExtras(DemoContext $ctx, $partners, array $meta): void
    {
        $orgRows = [];
        $memberRows = [];
        $payoutRows = [];
        $i = 0;

        foreach ($partners as $partner) {
            $info = $meta[$i] ?? null;
            $i++;
            if (! $info || ! $info['isCompany']) {
                continue;
            }

            $owner = DB::table('users')->where('id', DB::table('partners')->where('id', $partner->id)->value('user_id'))->first(['name', 'phone']);
            $orgRows[] = [
                'partner_id' => $partner->id,
                'name' => $partner->display_name,
                'legal_name' => $partner->display_name,
                'npwp' => DemoNames::dummyNpwp($partner->id),
                'nib' => DemoNames::dummyNib($partner->id),
                'address' => 'Jl. Demo Raya No. '.mt_rand(1, 200).', '.$info['city']['name'],
                'pic_name' => $owner->name ?? $partner->display_name,
                'pic_phone' => $owner->phone ?? DemoNames::dummyPhone($partner->id),
                'worker_count' => mt_rand(3, 40),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $memberRows[] = [
                'organization_id' => $partner->id, // partner_id â€” resolved to org id after insert
                'user_id' => DB::table('partners')->where('id', $partner->id)->value('user_id'),
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($orgRows, 500) as $chunk) {
            DB::table('partner_organizations')->insert($chunk);
        }

        // Members reference partner_organizations.id â€” resolve via unique partner_id
        $orgIds = DB::table('partner_organizations')->pluck('id', 'partner_id');
        $fixedMembers = [];
        foreach ($memberRows as $member) {
            $orgId = $orgIds[$member['organization_id']] ?? null;
            if ($orgId) {
                $member['organization_id'] = $orgId;
                $fixedMembers[] = $member;
            }
        }
        foreach (array_chunk($fixedMembers, 500) as $chunk) {
            DB::table('partner_members')->insertOrIgnore($chunk);
        }

        // Payout destinations for verified companies (needed for realistic withdrawals)
        $verifiedCompanies = DB::table('partners')
            ->where('is_demo', true)->where('type', 'vendor_company')->where('verification_state', 'verified')
            ->select('id', 'display_name')->get();

        foreach ($verifiedCompanies as $pc) {
            $payoutRows[] = [
                'partner_id' => $pc->id,
                'type' => 'bank',
                'bank_code' => ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'][mt_rand(0, 4)],
                'account_number' => (string) (8000000000 + $pc->id * 37),
                'account_name' => Str::limit($pc->display_name, 120),
                'is_default' => true,
                'verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($payoutRows, 500) as $chunk) {
            DB::table('payout_destinations')->insert($chunk);
        }
    }

    private function seedSkills(DemoContext $ctx, $partners, array $meta, array $catSlugs): void
    {
        $rows = [];
        $seen = [];
        $i = 0;
        foreach ($partners as $partner) {
            $info = $meta[$i] ?? null;
            $i++;
            if (! $info) {
                continue;
            }

            $dict = app(DemoDictionary::class)->category($info['categorySlug']);
            $skills = $dict['skills'];
            $count = min(count($skills), mt_rand(3, 5));
            $picked = (array) array_rand($skills, $count);

            foreach ($picked as $idx) {
                $key = $partner->id.'|'.$skills[$idx];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $rows[] = [
                    'partner_id' => $partner->id,
                    'category_id' => $ctx->categoryIds[$info['categorySlug']] ?? null,
                    'name' => $skills[$idx],
                    'level' => mt_rand(3, 5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('partner_skills')->insertOrIgnore($chunk);
        }
    }

    private function seedServiceAreas(DemoContext $ctx, $partners, array $meta): void
    {
        $rows = [];
        $i = 0;
        foreach ($partners as $partner) {
            $info = $meta[$i] ?? null;
            $i++;
            if (! $info) {
                continue;
            }

            $city = $info['city'];
            $rows[] = [
                'partner_id' => $partner->id,
                'coverage_type' => 'city',
                'location_id' => $city['id'],
                'radius_km' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // ~40% also cover one neighboring city via radius
            if (mt_rand(1, 100) <= 40) {
                $neighbor = $ctx->randomCity();
                $rows[] = [
                    'partner_id' => $partner->id,
                    'coverage_type' => 'radius',
                    'location_id' => $neighbor['id'],
                    'radius_km' => [10, 15, 20, 25][mt_rand(0, 3)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('partner_service_areas')->insertOrIgnore($chunk);
        }
    }

    private function buildMap($partners, array $meta): array
    {
        $map = [];
        foreach ($partners->values() as $idx => $partner) {
            $info = $meta[$idx] ?? null;
            if (! $info) {
                continue;
            }

            $map[] = [
                'id' => $partner->id,
                'slug' => $partner->slug,
                'display_name' => $partner->display_name,
                'type' => $partner->type,
                'categorySlug' => $info['categorySlug'],
                'city' => $info['city'],
            ];
        }

        return $map;
    }

    private function existingMap(int $providers): array
    {
        $partners = DB::table('partners')->where('is_demo', true)->orderBy('id')->get(['id', 'display_name', 'slug', 'type']);

        $map = [];
        foreach ($partners as $partner) {
            $meta = json_decode((string) (DB::table('partners')->where('id', $partner->id)->value('meta') ?? '[]'), true) ?: [];
            $map[] = [
                'id' => $partner->id,
                'slug' => $partner->slug,
                'display_name' => $partner->display_name,
                'type' => $partner->type,
                'categorySlug' => $meta['category_slug'] ?? array_key_first(DemoDictionary::SERVICE_WEIGHTS),
                'city' => ['id' => 0, 'name' => $partner->city, 'slug' => 'jakarta-selatan', 'lat' => null, 'lng' => null],
            ];
        }

        return $map;
    }
}
