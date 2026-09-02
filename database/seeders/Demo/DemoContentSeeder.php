<?php

namespace Database\Seeders\Demo;

use App\Support\Demo\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CMS content so homepage/blog/SEO pages are alive: published blog posts,
 * homepage CMS blocks (hero/stats), and category×city SEO metadata rows.
 */
class DemoContentSeeder extends Seeder
{
    public function run(DemoContext $ctx): void
    {
        $this->blogPosts($ctx);
        $this->cmsBlocks();
        $this->seoMetadata($ctx);
    }

    private function blogPosts(DemoContext $ctx): void
    {
        $posts = [
            ['cara-memilih-tukang-ac-terpercaya', 'Cara Memilih Tukang AC Terpercaya di Kota Besar', 'Tips memilih teknisi AC: cek sertifikasi, garansi pengerjaan, dan transparansi harga sebelum booking.', 'Cek sertifikasi teknisi, pastikan ada garansi pengerjaan, bandingkan minimal 3 penawaran, dan baca review asli dari pelanggan sebelumnya. Jasapedia memverifikasi setiap penyedia sebelum badge Verified diberikan.'],
            ['panduan-renovasi-rumah-pertama', 'Panduan Renovasi Rumah untuk Pemula: RAB dan Timeline', 'Sebelum renovasi, pahami dulu cara menyusun RAB realistis dan timeline yang masuk akal.', 'Renovasi tanpa RAB = bencana finansial. Susun daftar kebutuhan, minta penawaran ke minimal 2 kontraktor, sisihkan dana cadangan 15-20%, dan pastikan termin pembayaran terkait progress fisik pekerjaan.'],
            ['harga-service-ac-2026', 'Berapa Harga Cuci dan Service AC di 2026?', 'Rincian kisaran harga cuci AC, isi freon, dan service berat sesuai tipe unit.', 'Cuci AC standar 0.5-2 PK berkisar Rp75-150 ribu per unit. Isi freon R32 sekitar Rp100-300 ribu tergantung tekanan. Perbaikan bocor atau ganti sparepart memerlukan survei teknisi. Gunakan fitur Posting Kebutuhan untuk dapat beberapa penawaran sekaligus.'],
            ['deep-cleaning-vs-general-cleaning', 'Deep Cleaning vs General Cleaning: Mana yang Anda Butuhkan?', 'Perbedaan lingkup, durasi, dan harga kedua jenis layanan cleaning.', 'General cleaning cocok untuk perawatan rutin: menyapu, mengepel, merapikan. Deep cleaning menyasar area yang jarang tersentuh: dalam lemari, sudut kamar mandi, balik perabot. Deep cleaning butuh waktu 2-3x lebih lama.'],
            ['freelance-vs-agency-untuk-website', 'Freelancer atau Agency: Mana yang Cocok untuk Website Bisnis Anda?', 'Perbandingan biaya, kecepatan, dan jaminan hasil antara freelancer dan agency.', 'Freelancer lebih hemat dan fleksibel untuk proyek kecil-menengah. Agency memberi tim lengkap untuk proyek besar dengan deadline ketat. Di Jasapedia keduanya terverifikasi, jadi pilih sesuai skala dan budget.'],
            ['cara-aman-hiring-tukang-online', '7 Cara Aman Menggunakan Jasa Tukang Lewat Platform Online', 'Checklist keamanan sebelum menyewa pekerja lepas lewat marketplace jasa.', '1. Cek status Verified. 2. Baca review dengan foto. 3. Gunakan pembayaran dalam platform. 4. Jangan transaksi di luar platform. 5. Dokumentasikan kesepakatan di chat. 6. Minta foto sebelum-sesudah. 7. Laporkan penyalahgunaan.'],
            ['benefit-kontrak-bulanan-cleaning-kantor', 'Kenapa Kantor Lebih Hemat dengan Kontrak Cleaning Bulanan?', 'Analisis biaya cleaning harian vs kontrak bulanan untuk kantor kecil-menengah.', 'Kontrak bulanan memberi harga per kunjungan lebih murah, tim tetap yang paham layout kantor, dan prioritas jadwal. Kantor 200 m2 umumnya hemat 20-35% dibanding pemesanan lepas.'],
            ['persiapan-foto-prewedding', 'Checklist Persiapan Foto Prewedding: dari Konsep sampai D-Day', 'Panduan singkat memilih konsep, lokasi, dan penawaran fotografer prewedding.', 'Tentukan konsep dan budget dulu, cari referensi lokasi, diskusikan wardrobe, dan konfirmasi backup plan saat hujan. Photographer profesional biasanya menawarkan sesi konsultasi gratis — manfaatkan.'],
            ['tanda-installasi-listrik-rumah-perlu-di-upgrade', '5 Tanda Instalasi Listrik Rumah Anda Perlu Di-upgrade', 'MCB sering turun? Lampu berkedip? Mungkin saatnya cek instalasi listrik.', 'MCB sering turun, stop kontak panas, lampu berkedip, kabel tikus, dan MCB berkarat adalah tanda bahaya. Upgrade daya dan penggantian panel harus dikerjakan elektriseter bersertifikat mengikuti PUIL.'],
            ['gebyok-memilih-jasa-pindahan', 'Memilih Jasa Pindahan: Survey, Packing, dan Asuransi', 'Yang perlu ditanyakan ke penyedia jasa pindahan sebelum deal.', 'Minta survei (foto via chat cukup), tanyakan material packing, siapa yang bertanggung jawab barang pecah, dan apakah ada asuransi. Jangan asal pilih harga termurah tanpa kejelasan tanggung jawab.'],
        ];

        $rows = [];
        $now = now();
        foreach ($posts as $i => [$slug, $title, $excerpt, $content]) {
            $rows[] = [
                'slug' => $slug,
                'title' => $title,
                'excerpt' => $excerpt,
                'content' => '<p>'.str_replace("\n", '</p><p>', $content).'</p>',
                'status' => 'published',
                'published_at' => $now->copy()->subDays($i * 9 + 3),
                'seo' => json_encode(['meta_title' => $title.' | Jasapedia', 'meta_description' => $excerpt]),
                'is_demo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($rows as $row) {
            DB::table('blog_posts')->updateOrInsert(['slug' => $row['slug']], $row);
        }
    }

    private function cmsBlocks(): void
    {
        $blocks = [
            ['home.hero.badge', 'banner', ['text' => 'Lebih dari 10.000+ jasa terverifikasi di seluruh Indonesia'], 1],
            ['home.stats', 'promo_strip', [
                'items' => [
                    ['label' => 'Jasa Aktif', 'value' => '10.000+'],
                    ['label' => 'Penyedia Terverifikasi', 'value' => '1.900+'],
                    ['label' => 'Ulasan Asli', 'value' => '7.000+'],
                    ['label' => 'Kota Terjangkau', 'value' => '30+'],
                ],
            ], 2],
            ['home.trust', 'richtext', ['title' => 'Kenapa Jasapedia?', 'body' => 'Penyedia diverifikasi, pembayaran aman tertahan sampai pekerjaan selesai, dan semua ulasan berasal dari transaksi nyata.'], 3],
        ];

        foreach ($blocks as [$key, $type, $data, $sort]) {
            DB::table('cms_blocks')->updateOrInsert(['key' => $key], [
                'key' => $key,
                'type' => $type,
                'data' => json_encode($data),
                'sort' => $sort,
                'is_active' => true,
                'is_demo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seoMetadata(DemoContext $ctx): void
    {
        $rows = [];
        $topCities = array_slice($ctx->cities, 0, 12);

        foreach ($ctx->categories as $category) {
            foreach (array_slice($topCities, 0, 6) as $city) {
                $catName = $category['name'];
                $cityName = $city['name'];
                $rows[] = [
                    'page_type' => 'category_city',
                    'category_id' => $category['id'],
                    'city' => $cityName,
                    'canonical_url' => '/explore?category='.$category['slug'],
                    'meta_title' => "Jasa {$catName} di {$cityName} — Murah & Terpercaya | Jasapedia",
                    'meta_description' => "Cari penyedia {$catName} terverifikasi di {$cityName}. Bandingkan harga, baca ulasan asli, booking aman lewat Jasapedia.",
                    'og_image' => null,
                    'noindex' => false,
                    'h1' => "Jasa {$catName} di {$cityName}",
                    'intro_copy' => "Temukan penyedia {$catName} terbaik di {$cityName}. Semua penyedia terverifikasi dengan ulasan dari pelanggan nyata.",
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('seo_metadata')->updateOrInsert(
                    ['page_type' => $row['page_type'], 'category_id' => $row['category_id'], 'city' => $row['city']],
                    $row,
                );
            }
        }
    }
}
