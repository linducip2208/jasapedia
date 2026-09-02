<?php

namespace Database\Seeders\Demo;

use App\Models\CustomerAddress;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo users: customers + provider owner accounts + fixed documented accounts.
 * Emails use a non-routable domain; one bcrypt hash reused for ALL rows.
 */
class DemoUserSeeder extends Seeder
{
    public function run(DemoContext $ctx, int $customers, int $providers): array
    {
        $roleIdCustomer = Role::where('name', 'Customer')->value('id');
        $roleIdPartner = Role::where('name', 'Partner')->value('id');

        $fixed = $this->fixedAccounts($ctx);

        $needed = $customers + $providers;
        $existing = User::where('is_demo', true)->where('email', 'not like', 'demo-%@%')->count();
        $toCreate = max(0, $needed - $existing);

        if ($toCreate === 0) {
            return $this->resolveAccounts($ctx, $fixed, $roleIdCustomer, $roleIdPartner);
        }

        $bar = $this->command?->getOutput()->createProgressBar($toCreate);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar?->setMessage('Users');
        $bar?->start();

        $base = (int) (User::max('id') ?? 0) + (int) (DB::table('users')->count() ?? 0);

        $userRows = [];
        for ($i = 1; $i <= $toCreate; $i++) {
            $n = $base + $i;
            $isProvider = $i > $customers;
            $name = DemoNames::person();
            $userRows[] = [
                'name' => $name,
                'email' => ($isProvider ? 'provider' : 'customer').str_pad((string) $n, 6, '0', STR_PAD_LEFT).'@'.$ctx->emailDomain,
                'password' => $ctx->passwordHash,
                'phone' => DemoNames::dummyPhone($n),
                'status' => 'active',
                'locale' => 'id-ID',
                'email_verified_at' => now(),
                'is_demo' => true,
                'created_at' => now()->subDays(mt_rand(30, 400)),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($userRows, 500) as $chunk) {
            DB::table('users')->insert($chunk);
            $bar?->advance(count($chunk));
        }

        $bar?->finish();
        $this->command?->getOutput()->writeln('');

        // Profiles in bulk
        $profiles = [];
        $ids = DB::table('users')->where('is_demo', true)->orderByDesc('id')->limit($toCreate)->pluck('id');

        $cities = array_map(fn ($c) => $c['name'], $ctx->cities);
        foreach ($ids as $id) {
            $profiles[] = [
                'user_id' => $id,
                'city' => $cities[mt_rand(0, count($cities) - 1)],
                'bio' => mt_rand(1, 100) <= 40 ? 'Pengguna Jasapedia aktif.' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($profiles, 500) as $chunk) {
            DB::table('user_profiles')->insertOrIgnore($chunk);
        }

        // Role pivots in bulk
        $pivots = [];
        $i = 0;
        foreach ($ids as $id) {
            $isProvider = $i >= $customers;
            $roleId = $isProvider ? $roleIdPartner : $roleIdCustomer;
            if ($roleId) {
                $pivots[] = ['user_id' => $id, 'role_id' => $roleId, 'organization_id' => null, 'created_at' => now(), 'updated_at' => now()];
            }
            $i++;
        }
        foreach (array_chunk($pivots, 500) as $chunk) {
            DB::table('user_role')->insertOrIgnore($chunk);
        }

        $this->seedAddresses($ctx, $customers);

        return $this->resolveAccounts($ctx, $fixed, $roleIdCustomer, $roleIdPartner);
    }

    /** Documented demo logins (prompt §18) — same password as bulk. */
    private function fixedAccounts(DemoContext $ctx): array
    {
        $specs = [
            'customer' => ['Budi Santoso (Demo)', 'Customer'],
            'provider' => ['Surya Teknik (Demo)', 'Partner'],
            'company' => ['Surya Facility Service (Demo)', 'Partner'],
            'corporate' => ['Andi Wijaya (Demo Corporate)', 'Customer'],
        ];

        $out = [];
        foreach ($specs as $key => [$name, $role]) {
            $user = User::firstOrCreate(
                ['email' => "{$key}@jasapedia.test"],
                [
                    'name' => $name,
                    'password' => $ctx->passwordHash,
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'phone' => DemoNames::dummyPhone(crc32($key)),
                    'is_demo' => true,
                ],
            );

            $roleId = Role::where('name', $role)->value('id');
            if ($roleId && ! $user->hasRole($role)) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }

            if (! UserProfile::where('user_id', $user->id)->exists()) {
                UserProfile::create(['user_id' => $user->id, 'city' => 'Jakarta Selatan']);
            }

            $out[$key] = $user->id;
        }

        return $out;
    }

    private function seedAddresses(DemoContext $ctx, int $customers): void
    {
        $existing = CustomerAddress::count();
        if ($existing >= min(1500, $customers)) {
            return;
        }

        $rows = [];
        $customerIds = DB::table('users')->where('is_demo', true)->where('email', 'like', 'customer%')->orderBy('id')->limit(1500)->pluck('id');

        foreach ($customerIds as $idx => $userId) {
            $city = $ctx->randomCity();
            [$lat, $lng] = $ctx->jitter($city['lat'] ?? -6.2, $city['lng'] ?? 106.8, 5);
            $rows[] = [
                'user_id' => $userId,
                'label' => ['Rumah', 'Kantor'][mt_rand(0, 1)],
                'recipient_name' => DemoNames::person(),
                'phone' => DemoNames::dummyPhone($idx + 7),
                'address_line' => 'Jl. Demo No. '.mt_rand(1, 300).', '.$city['name'],
                'lat' => $lat,
                'lng' => $lng,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('customer_addresses')->insert($chunk);
        }
    }

    private function resolveAccounts(DemoContext $ctx, array $fixed, $roleIdCustomer, $roleIdPartner): array
    {
        // Fresh ids for bulk-created users, split by email prefix
        $providerIds = DB::table('users')->where('is_demo', true)->where('email', 'like', 'provider%')->orderBy('id')->pluck('id')->all();
        $customerIds = DB::table('users')->where('is_demo', true)->where('email', 'like', 'customer%')->orderBy('id')->pluck('id')->all();

        return [
            'fixed' => $fixed,
            'providerIds' => $providerIds,
            'customerIds' => $customerIds,
        ];
    }
}
