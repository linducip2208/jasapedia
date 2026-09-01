<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocationSeeder extends Seeder
{
    // province => [city => [lat, lng]]
    private array $data = [
        'DKI Jakarta' => ['Jakarta Pusat' => [-6.1862, 106.8342], 'Jakarta Selatan' => [-6.2495, 106.7992], 'Jakarta Barat' => [-6.1683, 106.7591], 'Jakarta Timur' => [-6.2253, 106.9003], 'Jakarta Utara' => [-6.1523, 106.8710]],
        'Jawa Barat' => ['Bandung' => [-6.9175, 107.6191], 'Bekasi' => [-6.2383, 106.9756], 'Bogor' => [-6.5950, 106.8166], 'Depok' => [-6.4025, 106.7942], 'Cirebon' => [-6.7320, 108.5523]],
        'Jawa Tengah' => ['Semarang' => [-7.0051, 110.4387], 'Surakarta' => [-7.5755, 110.8243], 'Magelang' => [-7.4797, 110.2177]],
        'DI Yogyakarta' => ['Yogyakarta' => [-7.7956, 110.3695]],
        'Jawa Timur' => ['Surabaya' => [-7.2575, 112.7521], 'Malang' => [-7.9666, 112.6326], 'Kediri' => [-7.8482, 112.0188]],
        'Banten' => ['Tangerang' => [-6.1783, 106.6319], 'Tangerang Selatan' => [-6.2886, 106.7179], 'Serang' => [-6.1203, 106.1506]],
        'Bali' => ['Denpasar' => [-8.6705, 115.2126]],
        'Sumatera Utara' => ['Medan' => [3.5952, 98.6722]],
        'Sumatera Selatan' => ['Palembang' => [-2.9761, 104.7754]],
        'Sumatera Barat' => ['Padang' => [-0.9471, 100.4172]],
        'Sulawesi Selatan' => ['Makassar' => [-5.1477, 119.4327]],
        'Kalimantan Timur' => ['Balikpapan' => [-1.2379, 116.8529], 'Samarinda' => [-0.5019, 117.1536]],
        'Kalimantan Barat' => ['Pontianak' => [-0.0263, 109.3425]],
        'Riau' => ['Pekanbaru' => [0.5071, 101.4478]],
        'Kepulauan Riau' => ['Batam' => [1.1301, 104.0532]],
    ];

    public function run(): void
    {
        $country = Location::firstOrCreate(
            ['type' => 'country', 'parent_id' => null, 'name' => 'Indonesia'],
            ['slug' => 'indonesia'],
        );

        $rows = [];
        foreach ($this->data as $province => $cities) {
            $p = Location::firstOrCreate(
                ['type' => 'province', 'parent_id' => $country->id, 'name' => $province],
                ['slug' => Str::slug($province)],
            );

            foreach ($cities as $city => [$lat, $lng]) {
                $rows[] = [
                    'parent_id' => $p->id,
                    'type' => 'city',
                    'name' => $city,
                    'slug' => Str::slug($city),
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            }
        }

        foreach ($rows as $row) {
            Location::firstOrCreate(
                ['type' => $row['type'], 'parent_id' => $row['parent_id'], 'name' => $row['name']],
                ['slug' => $row['slug'], 'lat' => $row['lat'], 'lng' => $row['lng']],
            );
        }

        DB::statement("UPDATE locations SET lat = lat, lng = lng WHERE type = 'city' AND lat IS NOT NULL");
    }
}
