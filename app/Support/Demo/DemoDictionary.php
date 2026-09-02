<?php

namespace App\Support\Demo;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Per-category Indonesian content dictionary for demo seeding.
 * Every category gets: realistic service types, scopes, spec tokens,
 * price ranges, fulfillment pools, skills, addons, packages and copy.
 * Titles are COMBINATIONS (type + spec + scope + city) — never lorem ipsum.
 */
final class DemoDictionary
{
    /** Deterministic category service distribution (prompt §4, weights pre-normalization). */
    public const SERVICE_WEIGHTS = [
        'technology-programming' => 900,
        'design-creative' => 650,
        'digital-marketing' => 500,
        'business-consulting' => 350,
        'accounting-tax' => 250,
        'legal' => 200,
        'cleaning' => 900,
        'ac-electronics' => 900,
        'plumbing' => 400,
        'electrical' => 450,
        'handyman' => 500,
        'renovation' => 600,
        'construction' => 400,
        'cctv-security' => 350,
        'pest-control' => 250,
        'automotive' => 550,
        'moving-logistics' => 450,
        'event-services' => 400,
        'photography' => 400,
        'education' => 350,
        'personal-services' => 300,
    ];

    /** City weights — heavier on Jabodetabek + major cities (prompt §9). */
    public const CITY_WEIGHTS = [
        'jakarta-selatan' => 100, 'jakarta-pusat' => 70, 'jakarta-barat' => 65,
        'jakarta-timur' => 65, 'jakarta-utara' => 45, 'bekasi' => 80, 'depok' => 70,
        'tangerang' => 65, 'tangerang-selatan' => 55, 'bogor' => 55, 'bandung' => 80,
        'surabaya' => 80, 'semarang' => 55, 'yogyakarta' => 45, 'surakarta' => 35,
        'malang' => 40, 'medan' => 55, 'makassar' => 45, 'palembang' => 40,
        'denpasar' => 40, 'cirebon' => 20, 'magelang' => 12, 'kediri' => 18,
        'serang' => 18, 'padang' => 20, 'balikpapan' => 20, 'samarinda' => 18,
        'pontianak' => 15, 'pekanbaru' => 20, 'batam' => 20,
    ];

    private array $categories = [];

    public function category(string $slug): array
    {
        if (! isset($this->categories[$slug])) {
            $this->categories[$slug] = $this->build($slug);
        }

        return $this->categories[$slug];
    }

    public static function slugFor(string $categoryName): string
    {
        return Str::slug($categoryName);
    }

    /**
     * Normalize SERVICE_WEIGHTS to exactly $total using the largest-remainder
     * method, so the final service count is EXACTLY what was requested.
     */
    public static function normalizedDistribution(int $total): array
    {
        $weights = self::SERVICE_WEIGHTS;
        $sum = array_sum($weights);
        $result = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $slug => $weight) {
            $exact = $weight * $total / $sum;
            $floor = (int) floor($exact);
            $result[$slug] = $floor;
            $remainders[$slug] = $exact - $floor;
            $allocated += $floor;
        }

        arsort($remainders);
        $leftover = $total - $allocated;
        foreach (array_slice(array_keys($remainders), 0, $leftover, true) as $slug) {
            $result[$slug]++;
        }

        ksort($result);

        return $result;
    }

    /** Weighted random pick from [key => weight] map with seeded mt_rand. */
    public static function weightedPick(array $weights): string
    {
        $sum = array_sum($weights);
        $roll = mt_rand(1, (int) $sum);
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Build a realistic service title from category parts.
     * Combination space = types × specs × scopes × cities.
     */
    public function serviceTitle(string $slug, int $index): string
    {
        $c = $this->category($slug);
        $types = $c['types'];
        $specs = $c['specs'];
        $scopes = $c['scopes'];
        $cities = $c['cities'];

        // Coprime strides keep generated titles varied without repetition streaks.
        $t = $types[$index % count($types)];
        $spec = $specs !== [] ? $specs[(intdiv($index, count($types)) * 3 + 1) % count($specs)] : '';
        $scope = $scopes !== [] ? $scopes[(intdiv($index, count($types) * max(1, count($specs))) * 5 + 2) % count($scopes)] : '';
        $city = $cities[$index % count($cities)];

        $patterns = $c['title_patterns'];

        return str_replace(
            ['{type}', '{spec}', '{scope}', '{city}'],
            [$t, $spec, $scope, $city],
            $patterns[$index % count($patterns)],
        );
    }

    public function serviceDescription(string $slug): string
    {
        $c = $this->category($slug);

        return $c['descriptions'][mt_rand(0, count($c['descriptions']) - 1)];
    }

    /** [fulfillment_type, price_model, unit_label, duration, weight] */
    public function pickFulfillment(string $slug): array
    {
        $pool = $this->category($slug)['fulfillments'];
        $weights = array_column($pool, 4);
        $idx = 0;
        $roll = mt_rand(1, array_sum($weights));
        foreach ($pool as $i => $row) {
            $roll -= $row[4];
            if ($roll <= 0) {
                $idx = $i;
                break;
            }
        }

        return $pool[$idx];
    }

    public function priceFor(string $slug, string $priceModel): int
    {
        [$lo, $hi] = $this->category($slug)['price_range'];

        $lo = match ($priceModel) {
            'hourly' => max(35000, (int) round($lo / 3 / 5000) * 5000),
            'per_unit' => max(50000, (int) round($lo / 2 / 5000) * 5000),
            'quotation', 'milestone' => max(500000, (int) round($lo / 10000) * 10000),
            default => max(50000, (int) round($lo / 5000) * 5000),
        };

        $hi = match ($priceModel) {
            'hourly' => (int) round($hi / 8 / 5000) * 5000,
            'per_unit' => (int) round($hi / 4 / 5000) * 5000,
            'quotation', 'milestone' => max($lo, (int) round((($lo + $hi) / 2) / 100000) * 100000),
            default => max($lo, (int) round(($lo + ($hi - $lo) * 0.55) / 5000) * 5000),
        };

        return max(50000, mt_rand($lo, $hi));
    }

    public function packagesFor(string $slug): array
    {
        return $this->category($slug)['packages'];
    }

    public function addonFor(string $slug): array
    {
        $addons = $this->category($slug)['addons'];

        return $addons[mt_rand(0, count($addons) - 1)];
    }

    public function skillsFor(string $slug): array
    {
        return $this->category($slug)['skills'];
    }

    public function projectPool(string $slug): array
    {
        return $this->category($slug)['projects'];
    }

    public function rfqPool(string $slug): array
    {
        return $this->category($slug)['rfqs'];
    }

    // ------------------------------------------------------------------
    //  Category dictionaries
    // ------------------------------------------------------------------

    private function build(string $slug): array
    {
        $cities = array_map(
            fn ($city) => ucwords(str_replace('-', ' ', $city)),
            array_keys(self::CITY_WEIGHTS),
        );

        $onsite = [['instant_booking', 'fixed', null, 120, 25], ['appointment', 'fixed', null, 120, 45], ['per_unit', 'per_unit', 'unit', 90, 20], ['hourly', 'hourly', 'jam', 240, 10]];
        $fieldService = [['instant_booking', 'fixed', null, 90, 30], ['appointment', 'fixed', null, 120, 40], ['per_unit', 'per_unit', 'unit', 60, 25], ['survey_required', 'starting_from', null, 90, 5]];
        $projectBased = [['project', 'starting_from', null, null, 55], ['milestone_project', 'milestone', null, null, 35], ['fixed_package', 'fixed', null, 240, 10]];
        $digital = [['project', 'starting_from', null, null, 45], ['milestone_project', 'milestone', null, null, 25], ['fixed_package', 'fixed', null, 480, 20], ['appointment', 'fixed', null, 60, 10]];

        $base = [
            'cities' => $cities,
            'scopes' => [],
            'specs' => [],
            'title_patterns' => ['{type} {spec} {city}', '{type} {scope} {city}', '{type} Area {city}', '{type} {spec} — {city}'],
            'packages' => [
                ['name' => 'Basic', 'mult' => 1.0, 'desc' => 'Paket dasar sesuai lingkup minimal.'],
                ['name' => 'Standard', 'mult' => 1.8, 'desc' => 'Paket paling populer dengan lingkup lengkap.'],
                ['name' => 'Premium', 'mult' => 3.2, 'desc' => 'Lingkup penuh + prioritas jadwal dan garansi diperpanjang.'],
            ],
        ];

        return match ($slug) {
            'technology-programming' => $base + [
                'fulfillments' => $digital,
                'price_range' => [500000, 30000000],
                'delivery' => 'remote',
                'types' => [
                    'Pembuatan Website Company Profile', 'Pembuatan Toko Online Laravel', 'Pengembangan Aplikasi Android',
                    'Pengembangan Aplikasi iOS', 'Integrasi REST API', 'Pembuatan Sistem ERP', 'Maintenance Website',
                    'Bug Fix Laravel', 'Optimasi Database MySQL', 'Integrasi Payment Gateway', 'Setup VPS dan Deployment',
                    'Pembuatan Dashboard Analytics', 'Pembuatan Sistem Inventory', 'Pembuatan POS Kasir',
                    'Pembuatan CRM Penjualan', 'Integrasi WhatsApp API', 'Pembuatan Aplikasi Absensi Karyawan',
                    'Migrasi Server dan Hosting', 'Audit Keamanan Aplikasi', 'Pembuatan Landing Page',
                ],
                'specs' => ['Laravel', 'Node.js', 'Flutter', 'React', 'WordPress', 'PHP Native', 'Vue.js', 'Next.js'],
                'scopes' => ['UMKM', 'Startup', 'Korporat', 'Sekolah', 'Klinik', 'Restoran', 'Toko Retail'],
                'descriptions' => [
                    'Pengembangan sesuai kebutuhan bisnis Anda dengan tahapan diskusi, desain, development, hingga serah terima source code.',
                    'Tim developer berpengalaman mengerjakan proyek dengan milestone jelas, testing, dan dokumentasi teknis.',
                    'Jasa development profesional dengan garansi bug fix, deployment, dan pendampingan pasca rilis.',
                    'Pengerjaan remote dengan komunikasi via chat/ meeting mingguan, progress terukur per milestone.',
                ],
                'inclusions' => 'Source code lengkap, hosting setup (bila diminta), panduan penggunaan, 2x revisi mayor, garansi bug fix 30 hari.',
                'exclusions' => 'Biaya hosting/domain, lisensi pihak ketiga berbayar, konten (foto/teks) dari klien, integrasi API premium berbayar.',
                'skills' => ['Laravel', 'PHP', 'MySQL', 'Flutter', 'React', 'REST API', 'DevOps', 'WordPress', 'Tailwind CSS', 'Docker'],
                'addons' => [
                    ['Setup domain + SSL', 250000, 500000], ['Desain UI/UX tambahan', 750000, 2500000],
                    ['Integrasi Google Analytics', 200000, 400000], ['SEO on-page dasar', 500000, 1000000],
                    ['Pendampingan 1 bulan', 1000000, 2500000], ['Backup otomatis harian', 150000, 300000],
                ],
                'packages' => [
                    ['name' => 'Landing Page', 'mult' => 1.0, 'desc' => '1 halaman promosi responsif + form kontak.'],
                    ['name' => 'Company Profile', 'mult' => 2.2, 'desc' => '5-7 halaman, CMS sederhana, SEO dasar.'],
                    ['name' => 'Custom Business Website', 'mult' => 4.5, 'desc' => 'Fitur sesuai kebutuhan, integrasi API, dashboard admin.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Pembuatan aplikasi inventory untuk distributor', 'Aplikasi web + mobile untuk stok gudang multi-lokasi, laporan harian, integrasi barcode.'],
                    ['Sistem POS untuk jaringan retail', 'POS multi-outlet dengan sinkronisasi stok real-time dan laporan konsolidasi.'],
                    ['Portal HRIS perusahaan manufaktur', 'Absensi, cuti, payroll slip, dan evaluasi karyawan untuk 500+ pegawai.'],
                    ['Website e-learning untuk bimbel', 'Kelas online, video course, kuis, sertifikat otomatis.'],
                    ['Integrasi ERP dengan marketplace', 'Sinkronisasi stok dan order dari 3 marketplace ke ERP internal.'],
                    ['Pembuatan aplikasi delivery internal', 'Tracking kurir, rute, bukti kirim, dashboard operasional.'],
                    ['Redesign dan migrasi website korporat', 'Migrasi dari WordPress lawas ke Laravel dengan SEO redirect map.'],
                    ['Aplikasi booking klinik', 'Jadwal dokter, antrian online, notifikasi WhatsApp.'],
                ],
                'rfqs' => [
                    ['Butuh pembuatan aplikasi inventory', 'Gudang 3 lokasi, butuh aplikasi stok dengan laporan harian dan scan barcode.'],
                    ['Pembuatan website company profile + katalog', 'Perusahaan konstruksi, katalog produk 200+ item, 2 bahasa.'],
                    ['Integrasi payment gateway + WhatsApp API', 'Toko online existing butuh pembayaran otomatis dan notifikasi WA.'],
                    ['Maintenance aplikasi internal 6 bulan', 'Aplikasi Laravel existing, butuh SLA respon 4 jam.'],
                    ['Pembuatan dashboard monitoring IoT', 'Visualisasi data sensor 50 titik, alert Telegram.'],
                    ['Migrasi server on-premise ke cloud', '10 server, downtime minimal, migration weekend.'],
                ],
            ],
            'design-creative' => $base + [
                'fulfillments' => $digital,
                'price_range' => [250000, 8000000],
                'delivery' => 'remote',
                'types' => [
                    'Desain Logo dan Identitas Brand', 'Desain Kemasan Produk', 'Desain Feed Instagram (30 Hari)',
                    'Desain Company Profile', 'Desain UI/UX Aplikasi Mobile', 'Desain Banner dan Spanduk',
                    'Desain Brosur dan Leaflet', 'Illustrasi Karakter Brand', 'Desain Undangan Digital',
                    'Motion Graphic Animasi', 'Desain Stand Pameran', 'Desain Menu Kafe dan Restoran',
                    'Video Editing Company Profile', 'Desain Poster Event', 'Desain Kalender Promosi',
                    'Desain Label Produk UMKM', 'Desain Laporan Tahunan', 'Desain Sticker dan Merchandise',
                ],
                'specs' => ['Starter', 'Profesional', 'Premium', 'Revisi 2x', 'Revisi 5x', 'Full Package'],
                'scopes' => ['UMKM', 'Kafe', 'Restoran', 'Klinik', 'Fashion', 'Kosmetik', 'Kuliner', 'Event'],
                'descriptions' => [
                    'Desain original sesuai brief Anda, termasuk 2 tahap revisi dan file sumber (AI/PSD/Figma).',
                    'Hasil desain siap cetak maupun digital, konsisten dengan identitas brand yang sudah ada.',
                    'Bekerja dengan moodboard dan referensi terlebih dahulu agar hasil sesuai ekspektasi.',
                    'File akhir diserahkan lengkap: JPG, PNG transparan, PDF, dan file sumber editable.',
                ],
                'inclusions' => '2-5 konsep awal, revisi sesuai paket, file sumber editable, lisensi penggunaan penuh untuk klien.',
                'exclusions' => 'Pembelian font/aset premium berbayar, foto produk dari klien, cetak fisik.',
                'skills' => ['Adobe Illustrator', 'Photoshop', 'Figma', 'After Effects', 'Branding', 'UI/UX', 'Video Editing', 'Canva Pro'],
                'addons' => [
                    ['Revisi tambahan', 100000, 250000], ['Rush order 2 hari', 300000, 750000],
                    ['File sumber tambahan', 150000, 400000], ['Variasi warna logo', 100000, 300000],
                    ['Mockup 3D produk', 250000, 600000],
                ],
                'packages' => [
                    ['name' => 'Basic Branding', 'mult' => 1.0, 'desc' => 'Logo utama + 1 revisi, file PNG/JPG.'],
                    ['name' => 'Brand Identity', 'mult' => 2.5, 'desc' => 'Logo + guideline warna/typo + stationery.'],
                    ['name' => 'Full Brand Kit', 'mult' => 5.0, 'desc' => 'Identity lengkap + template sosmed + file sumber semua.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Rebranding brand kosmetik lokal', 'Logo baru, kemasan 12 SKU, guideline brand lengkap.'],
                    ['Desain UI/UX aplikasi fintech', '30+ screen, design system, prototipe interaktif.'],
                    ['Video company profile 2 menit', 'Script, storyboard, animasi 2D, voice over.'],
                    ['Katalog produk furniture 40 halaman', 'Fotografi produk bisa include, layout katalog cetak.'],
                    ['Desain booth pameran 6x6 m', 'Konsep 3D, produksi, dan instalasi.'],
                    ['Social media kit untuk kafe', 'Template feed + story 3 bulan, tone visual konsisten.'],
                ],
                'rfqs' => [
                    ['Butuh desainer UI/UX untuk aplikasi internal', 'Aplikasi HR internal, 15 screen, design system ringan.'],
                    ['Desain kemasan produk snack 5 varian', 'Siap cetak, mengikuti guideline brand existing.'],
                    ['Video animasi promosi 60 detik', 'Untuk iklan digital, 2 versi rasio 16:9 dan 9:16.'],
                    ['Desain annual report perusahaan', '60 halaman, data chart, cetak dan digital.'],
                    ['Butuh ilustrator untuk buku anak', '12 halaman, gaya watercolor digital.'],
                ],
            ],
            'digital-marketing' => $base + [
                'fulfillments' => [['project', 'starting_from', null, null, 35], ['fixed_package', 'fixed', null, null, 35], ['hourly', 'hourly', 'jam', 120, 10], ['appointment', 'fixed', null, 60, 20]],
                'price_range' => [500000, 15000000],
                'delivery' => 'remote',
                'types' => [
                    'Kelola Instagram Bisnis (Bulanan)', 'Google Ads Setup dan Optimasi', 'Meta Ads untuk E-commerce',
                    'SEO On-Page Website', 'SEO Audit dan Riset Keyword', 'Copywriting Landing Page',
                    'Content Writing Blog (8 Artikel)', 'Kelola TikTok Shop', 'Email Marketing Campaign',
                    'Strategi Digital Marketing UMKM', 'Kelola Google Bisnisku', 'Facebook Ads untuk Local Business',
                    'Optimasi Marketplace (Tokopedia/Shopee)', 'Live Selling Package', 'Influencer Seeding Campaign',
                    'Remarketing Campaign Setup', 'Landing Page Optimization', 'Social Media Audit',
                ],
                'specs' => ['Bulanan', '3 Bulan', 'Starter', 'Growth', 'Agresif', 'Harian'],
                'scopes' => ['UMKM', 'F&B', 'Fashion', 'Klinik', 'Properti', 'Jasa', 'Toko Online'],
                'descriptions' => [
                    'Pengelolaan penuh dengan laporan performa mingguan dan monthly recap meeting.',
                    'Fokus pada metrik yang penting: traffic berkualitas, conversion rate, dan ROAS.',
                    'Tim berpengalaman mengelola budget iklan mulai dari 3 juta per bulan.',
                    'Content plan disusun bersama, approval via WhatsApp/Telegram sebelum posting.',
                ],
                'inclusions' => 'Content calendar, desain konten, caption + hashtag riset, laporan performa mingguan, konsultasi bulanan.',
                'exclusions' => 'Budget iklan (ad spend), endorsement influencer, tools berbayar premium.',
                'skills' => ['Meta Ads', 'Google Ads', 'SEO', 'Copywriting', 'Content Strategy', 'TikTok Ads', 'Analytics', 'Email Marketing'],
                'addons' => [
                    ['Audit kompetitor', 500000, 1000000], ['Shooting konten on-site', 1000000, 3000000],
                    ['Ads creatives tambahan', 500000, 1500000], ['Laporan bulanan mendalam', 300000, 600000],
                ],
                'packages' => [
                    ['name' => 'Starter Growth', 'mult' => 1.0, 'desc' => '12 konten/bulan + kelola 1 platform.'],
                    ['name' => 'Growth Pro', 'mult' => 2.0, 'desc' => '20 konten + 2 platform + iklan dasar.'],
                    ['name' => 'Full Funnel', 'mult' => 3.6, 'desc' => 'Konten + iklan penuh + email + laporan ROAS.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Setup digital marketing dari nol untuk klinik', 'Strategi 6 bulan, Meta + Google Ads, konten edukasi.'],
                    ['Optimasi toko online kecil ROAS 3x', 'Audit funnel, remarketing, landing page A/B test.'],
                    ['Content 90 hari untuk brand F&B', 'Kalender konten, shooting 1x bulan, posting harian.'],
                    ['SEO recovery setelah penalti', 'Audit teknis, disavow, rebuild content structure.'],
                    ['Campaign launching produk skincare', 'Teaser, KOL seeding, flash sale ads.'],
                    ['Kelola marketplace 3 toko', 'Optimasi listing, promosi, flash deal harian.'],
                ],
                'rfqs' => [
                    ['Butuh jasa kelola Instagram 3 bulan', 'Brand fashion lokal, target 30 konten/bulan + iklan.'],
                    ['Google Ads untuk jasa cleaning', 'Budget iklan 5 juta/bulan, area Jabodetabek.'],
                    ['SEO untuk website jasa travel', 'Target keyword lokal, audit + on-page + backlink.'],
                    ['Copywriter untuk email campaign', '12 email/bulan, tone of voice santai.'],
                    ['TikTok content creator on-site', '2x seminggu ke lokasi outlet, 8 video/bulan.'],
                ],
            ],
            'business-consulting' => $base + [
                'fulfillments' => [['appointment', 'fixed', null, 120, 45], ['project', 'starting_from', null, null, 35], ['fixed_package', 'fixed', null, null, 20]],
                'price_range' => [750000, 25000000],
                'delivery' => 'hybrid',
                'types' => [
                    'Konsultasi Strategi Bisnis', 'Pendampingan Pembukuan UMKM', 'Konsultasi Pendirian PT/CV',
                    'Business Plan untuk Investor', 'Analisis Kelayakan Usaha', 'Konsultasi Digitalisasi Bisnis',
                    'SOP dan Standardisasi Operasional', 'Feasibility Study Produk Baru', 'Konsultasi Ekspansi Cabang',
                    'Pendampingan Pitch Deck', 'Konsultasi Supply Chain', 'Training Manajemen Tim',
                    'Market Research dan Analisis Kompetitor', 'Konsultasi Pricing Strategy', 'Valuasi Bisnis',
                ],
                'specs' => ['Sesi 2 Jam', 'Paket 4 Sesi', 'Bulanan', 'Project-Based', 'Retainer'],
                'scopes' => ['UMKM', 'Startup', 'Korporat', 'Franchise', 'Restoran', 'Manufaktur'],
                'descriptions' => [
                    'Konsultasi berbasis data: kami mulai dari analisis kondisi bisnis Anda sebelum memberi rekomendasi.',
                    'Output jelas dan actionable, bukan teori — disesuaikan dengan skala dan budget bisnis Anda.',
                    'Sesi bisa online atau on-site (area kota tertentu), dilengkapi dokumen ringkasan.',
                ],
                'inclusions' => 'Pra-analisis bisnis, sesi konsultasi, ringkasan rekomendasi tertulis, follow-up via chat 2 minggu.',
                'exclusions' => 'Implementasi teknis, legalitas resmi (notaris), perangkat lunak berbayar.',
                'skills' => ['Business Strategy', 'Financial Modeling', 'Market Research', 'SOP Writing', 'Pitch Deck', 'Operations'],
                'addons' => [
                    ['Sesi tambahan', 500000, 1500000], ['Dokumen deliverable lengkap', 1000000, 3000000],
                    ['Pendampingan 1 bulan', 2000000, 5000000],
                ],
                'packages' => [
                    ['name' => 'Discovery', 'mult' => 1.0, 'desc' => '1 sesi analisis + ringkasan rekomendasi.'],
                    ['name' => 'Blueprint', 'mult' => 2.5, 'desc' => '4 sesi + roadmap 6 bulan + KPI.'],
                    ['name' => 'Retainer', 'mult' => 5.0, 'desc' => 'Pendampingan bulanan dengan review kinerja.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Business plan untuk pengajuan modal bank', 'Proyeksi 3 tahun, analisis pasar, struktur modal.'],
                    ['SOP operasional jaringan laundry', 'Standarisasi 12 cabang, manual kerja, KPI harian.'],
                    ['Feasibility study ekspansi kafe', 'Analisis lokasi 5 titik, proyeksi cash flow.'],
                    ['Restrukturisasi tim sales', 'Desain organisasi, insentif, target per wilayah.'],
                    ['Valuasi bisnis untuk exit', 'Metode DCF dan multiple, laporan 40 halaman.'],
                ],
                'rfqs' => [
                    ['Butuh konsultan untuk business plan startup', 'Sektor agrikultur, butuh proyeksi 5 tahun untuk investor.'],
                    ['SOP untuk perusahaan logistik baru', 'Standardisasi operasional gudang dan armada.'],
                    ['Konsultasi pendirian PT + struktur saham', '2 pendiri, rencana employee stock option.'],
                    ['Market research produk minuman', 'Survei 300 responden di 3 kota.'],
                ],
            ],
            'accounting-tax' => $base + [
                'fulfillments' => [['fixed_package', 'fixed', null, null, 40], ['appointment', 'fixed', null, 120, 35], ['project', 'starting_from', null, null, 25]],
                'price_range' => [500000, 12000000],
                'delivery' => 'hybrid',
                'types' => [
                    'Pembukuan Bulanan UMKM', 'Lapor PPh 21 Karyawan', 'Lapor PPN Masukan/Keluaran',
                    'Penyusunan Laporan Keuangan', 'Rekonsiliasi Bank Bulanan', 'Konsultasi Pajak UMKM PPh Final',
                    'Pendampingan Audit Pajak', 'Setup Pembukuan Digital', 'Payroll dan BPJS Bulanan',
                    'Lapor SPT Tahunan Badan', 'Pendaftaran NPWP dan NIB', 'Konsultasi Struktur Pajak Perusahaan',
                    'Penataan Pembukuan Manual ke Digital', 'Sertifikat Standar (SLO) Pajak',
                ],
                'specs' => ['Bulanan', 'Tahunan', 'Kuartalan', 'One-Time', 'Retainer'],
                'scopes' => ['UMKM', 'CV', 'PT', 'Koperasi', 'Freelancer', 'Toko Online'],
                'descriptions' => [
                    'Ditangani konsultan pajak bersertifikat, data Anda dijaga kerahasiaannya.',
                    'Mengikuti peraturan perpajakan terbaru, termasuk kewajiban e-Faktur dan lapor daring.',
                    'Output berupa laporan yang rapi dan siap dipakai untuk bank maupun audit.',
                ],
                'inclusions' => 'Konsultasi awal, pengerjaan laporan, pengajuan via DJP (bila diwakilkan), arsip digital.',
                'exclusions' => 'Denda keterlambatan, sanksi pajak masa lalu, biaya notaris.',
                'skills' => ['Pajak PPh', 'PPN', 'e-Faktur', 'Coretax', 'Pembukuan', 'Accurate', 'Myob'],
                'addons' => [
                    ['Rush 3 hari kerja', 300000, 750000], ['Representasi ke KPP', 1000000, 2500000],
                    ['Setup aplikasi pembukuan', 750000, 2000000],
                ],
                'packages' => [
                    ['name' => 'Bulanan Dasar', 'mult' => 1.0, 'desc' => 'Pembukuan + lapor pajak bulanan.'],
                    ['name' => 'Bulanan Plus', 'mult' => 1.9, 'desc' => '+ payroll, BPJS, rekonsiliasi bank.'],
                    ['name' => 'Full Service', 'mult' => 3.5, 'desc' => 'Pembukuan penuh + SPT tahunan + konsultasi.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Penataan pembukuan 2 tahun tertunda', 'Rekap transaksi, jurnal penyesuaian, laporan lengkap.'],
                    ['Implementasi software akuntansi', 'Chart of account, migrasi data, training staf.'],
                    ['Pemeriksaan kepatuhan pajak internal', 'Review 3 tahun terakhir, rekomendasi perbaikan.'],
                    ['Struktur pajak untuk holding company', 'Optimasi dividen, transfer pricing dasar.'],
                ],
                'rfqs' => [
                    ['Butuh jasa lapor pajak bulanan UMKM', 'Toko retail omzet 150 juta/bulan, PPh final.'],
                    ['Pembukuan online shop multi-channel', 'Transaksi Shopee, TikTok, Tokopedia — rekonsiliasi bulanan.'],
                    ['Laporan keuangan untuk pengajuan kredit', 'Setahun transaksi, butuh laporan sesuai standar bank.'],
                    ['Payroll 40 karyawan', 'Slip gaji, PPh 21, BPJS, termin tepat waktu.'],
                ],
            ],
            'legal' => $base + [
                'fulfillments' => [['appointment', 'fixed', null, 120, 50], ['project', 'starting_from', null, null, 30], ['fixed_package', 'fixed', null, null, 20]],
                'price_range' => [500000, 25000000],
                'delivery' => 'hybrid',
                'types' => [
                    'Konsultasi Hukum Bisnis', 'Penyusunan Perjanjian Kerja Sama', 'Pendirian PT (Paket Lengkap)',
                    'Pendirian CV dan Firma', 'Pendaftaran Merek', 'Pendaftaran Hak Cipta',
                    'Review Kontrak Bisnis', 'Pembuatan Surat Peringatan (SP)', 'Konsultasi Ketenagakerjaan',
                    'Perjanjian Sewa dan Jual Beli', 'Pendirian Yayasan dan Perkumpulan', 'Legal Due Diligence',
                    'Perjanjian Kerja Rahasia (NDA)', 'Balasanklaim dan Mediasi', 'Izin Usaha dan NIB',
                ],
                'specs' => ['Konsultasi 1 Jam', 'Dokumen Lengkap', 'Berkas Siap AJB', 'Express 5 Hari', 'Reguler 14 Hari'],
                'scopes' => ['Perorangan', 'UMKM', 'PT', 'Yayasan', 'Koperasi', 'Startup'],
                'descriptions' => [
                    'Ditangani advokat/konsultan hukum terdaftar, dengan surat kuasa resmi bila diperlukan.',
                    'Proses transparan: Anda mendapat checklist dokumen dan estimasi waktu sejak awal.',
                    'Semua dokumen disusun sesuai peraturan terbaru dan kebutuhan spesifik Anda.',
                ],
                'inclusions' => 'Konsultasi awal, penyusunan dokumen, pendampingan pengurusan, 1x revisi dokumen.',
                'exclusions' => 'Biaya negara (PNBP), notaris/PPAT, biaya penerbitan resmi, perkara di pengadilan.',
                'skills' => ['Hukum Bisnis', 'HKI', 'Ketenagakerjaan', 'Perizinan', 'Kontrak', 'Due Diligence'],
                'addons' => [
                    ['Pendampingan ke instansi', 750000, 2000000], ['Revisi dokumen tambahan', 250000, 750000],
                    ['Legal opinion tertulis', 1500000, 4000000],
                ],
                'packages' => [
                    ['name' => 'Konsultasi', 'mult' => 1.0, 'desc' => 'Sesi konsultasi 1 jam + ringkasan.'],
                    ['name' => 'Dokumen', 'mult' => 2.5, 'desc' => 'Penyusunan dokumen + 1 revisi.'],
                    ['name' => 'Full Process', 'mult' => 5.0, 'desc' => 'Dokumen + pengurusan + pendampingan.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Legal audit perusahaan manufaktur', 'Review 50+ dokumen, rekomendasi perbaikan legalitas.'],
                    ['Pendaftaran 10 merek sekaligus', 'Risetro kelas, pengajuan DJKI, monitoring.'],
                    ['Penyusunan kontrak kerja standar 50 karyawan', 'PKWT/PKWTT, peraturan perusahaan.'],
                    ['Restructuring badan usaha keluarga', 'PT + yayasan, skema kepemilikan.'],
                ],
                'rfqs' => [
                    ['Butuh pendampingan pendirian PT PMA', 'Investor asing, sektor perdagangan, KBLI lengkap.'],
                    ['Review perjanjian kerja sama distribusi', 'Kontrak 5 tahun, eksklusif wilayah Jawa.'],
                    ['Pendaftaran merek untuk 3 kelas', 'Brand F&B, kelas 29/30/43.'],
                    ['Sengketa sewa ruko', 'Perlu somasi dan mediasi dengan pemilik.'],
                ],
            ],
            'cleaning' => $base + [
                'fulfillments' => [['instant_booking', 'fixed', null, 240, 30], ['hourly', 'hourly', 'jam', 240, 30], ['per_unit', 'per_unit', 'unit', 120, 15], ['appointment', 'fixed', null, 480, 25]],
                'price_range' => [50000, 2500000],
                'delivery' => 'onsite',
                'types' => [
                    'General Cleaning Rumah', 'Deep Cleaning Apartemen', 'Cleaning Kantor', 'Cuci Sofa',
                    'Cuci Karpet', 'Cleaning Kamar Mandi', 'Cleaning Pasca Renovasi', 'Cleaning Kost',
                    'Cuci Kasur dan Spring Bed', 'Cuci Gorden', 'Cleaning Ruko', 'Cuci Kaca Gedung',
                    'Cleaning Pabrik dan Gudang', 'Cuci Kursi Kantor', 'Cleaning Toko Retail',
                    'Deep Cleaning Dapur', 'Cleaning Purna Konstruksi', 'Cuci AC Combo Cleaning',
                ],
                'specs' => ['2 Jam', '4 Jam', '8 Jam', '1 Unit', '2 Unit', '3 Orang', '5 Orang', 'Standar', 'Deep Clean'],
                'scopes' => ['Rumah', 'Apartemen', 'Kantor', 'Kost', 'Ruko', 'Studio', 'Villa', 'Gudang'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {scope} {city}', '{type} Area {city}', '{type} {spec} — {scope} {city}'],
                'descriptions' => [
                    'Tim cleaning bersertifikat dengan peralatan lengkap dan chemical ramah lingkungan.',
                    'Pengerjaan sistematis: area kotor dulu, lalu detail, diakhiri quality check bersama Anda.',
                    'Petugas terlatih, berunjuk, dan sudah divaksinasi. Area kerja dilindungi agar aman.',
                ],
                'inclusions' => 'Peralatan + chemical, 2-4 petugas sesuai paket, tangga dan alat tinggi, quality check akhir.',
                'exclusions' => 'Cuci AC (opsi addon), pembersihan biologi/jamur berat, service elektronik, kaca luar gedung tinggi (gondola).',
                'skills' => ['Housekeeping', 'Deep Cleaning', 'Carpet Care', 'Glass Cleaning', 'Post-Construction Cleaning'],
                'addons' => [
                    ['Cuci AC 1 unit', 75000, 150000], ['Setrika pakaian 2 jam', 75000, 150000],
                    ['Cuci kaca dalam', 100000, 250000], ['Pet handler (hewan di rumah)', 50000, 100000],
                    ['Jam tambahan', 40000, 90000],
                ],
                'packages' => [
                    ['name' => '2 Jam', 'mult' => 1.0, 'desc' => '2 petugas, area utama (kamar mandi, dapur, ruang tamu).'],
                    ['name' => '4 Jam', 'mult' => 1.8, 'desc' => '2 petugas, seluruh rumah termasuk kamar tidur.'],
                    ['name' => '8 Jam + Deep Cleaning', 'mult' => 3.2, 'desc' => '3 petugas, detail sudut, kamar mandi scrub, dalam lemari.'],
                ],
                'emergency' => true,
                'projects' => [
                    ['Kontrak cleaning kantor 3 bulan', 'Gedung 4 lantai, 6 petugas harian, supervisi mingguan.'],
                    ['Cleaning 50 unit apartemen selesai renovasi', 'Pasca konstruksi, jadwal bertahap 3 minggu.'],
                    ['Cleaning rutin gudang logistik', 'Area 2000 m2, tim 4 orang, 2x seminggu.'],
                    ['Deep cleaning hotel 60 kamar', 'Kamar bertahap, koordinasi dengan housekeeping.'],
                ],
                'rfqs' => [
                    ['Butuh cleaning kantor 200 m2', 'Jakarta Selatan, selesai renovasi minggu depan, butuh tim 4 orang.'],
                    ['Cleaning rutin rumah 2x sebulan', 'Rumah 2 lantai di Bekasi, petugas tetap.'],
                    ['Cuci karpet kantor 40 m2', 'Karpet expanse area lobby dan ruang meeting.'],
                    ['Deep cleaning kost 20 kamar', 'Kosong sementara liburan, butuh secepatnya.'],
                    ['Cleaning pasca renovasi ruko 3 lantai', 'Sisa material, debu cat, jendela.'],
                ],
            ],
            'ac-electronics' => $base + [
                'fulfillments' => [['per_unit', 'per_unit', 'unit', 60, 45], ['instant_booking', 'fixed', null, 90, 20], ['appointment', 'fixed', null, 120, 25], ['survey_required', 'starting_from', null, 60, 10]],
                'price_range' => [75000, 5000000],
                'delivery' => 'onsite',
                'types' => [
                    'Cuci AC 0.5-1 PK', 'Cuci AC 1.5-2 PK', 'Service AC Tidak Dingin', 'Isi Freon AC',
                    'Perbaikan AC Bocor', 'Bongkar Pasang AC', 'Instalasi AC Baru', 'Service AC Inverter',
                    'Maintenance AC Kantor', 'Perbaikan Kulkas', 'Service Mesin Cuci', 'Perbaikan TV LED',
                    'Service Microwave', 'Perbaikan Water Heater', 'Service Dispenser', 'Reparasi Rice Cooker',
                    'Service AC Cassette', 'Perbaikan Freezer', 'Service Air Conditioner VRV/VRF',
                ],
                'specs' => ['0.5-1 PK', '1.5-2 PK', '2.5 PK', 'Inverter', 'Split Wall', 'Cassette', 'Standing Floor', '1 Unit', '2 Unit', '3 Unit'],
                'scopes' => ['Rumah', 'Kantor', 'Toko', 'Apartemen', 'Gudang', 'Kafe', 'Restoran'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {spec} — {scope} {city}', '{type} Area {city}', '{type} {scope} {city}'],
                'descriptions' => [
                    'Teknisi berpengalaman dengan garansi pengerjaan. Cek keluhan, tawarkan solusi, baru kerja.',
                    'Sparepart asli/rekanan, harga jelas sebelum pengerjaan, tanpa biaya tersembunyi.',
                    'Bisa panggilan hari yang sama untuk area dalam cakupan. Cek freon dan kelistrikan standar.',
                ],
                'inclusions' => 'Pengecekan, pengerjaan sesuai diagnosis, pengujian setelah service, garansi pengerjaan 7-30 hari.',
                'exclusions' => 'Harga sparepart (ditawarkan terpisah), penyakit berat pada unit tua, garansi kompresor rusak akibat umur.',
                'skills' => ['Teknisi AC', 'Refrigerasi', 'Kelistrikan', 'Inverter', 'Soldering', 'Freon R32/R410'],
                'addons' => [
                    ['Freon tambahan R32', 100000, 200000], ['Freon tambahan R410', 150000, 300000],
                    ['Sparepart minor', 50000, 500000], ['Garansi diperpanjang 30 hari', 50000, 150000],
                ],
                'packages' => [
                    ['name' => 'Cuci Standar', 'mult' => 1.0, 'desc' => 'Cuci AC indoor + outdoor, cek tekanan freon.'],
                    ['name' => 'Cuci + Cek Elektrik', 'mult' => 1.6, 'desc' => 'Cuci penuh + amper + kelistrikan + drainage.'],
                    ['name' => 'Service Total', 'mult' => 2.8, 'desc' => 'Cuci + perbaikan ringan + freon + garansi 30 hari.'],
                ],
                'emergency' => true,
                'projects' => [
                    ['Maintenance AC kantor 40 unit tahunan', 'Cuci berkala 4x setahun + laporan kondisi tiap unit.'],
                    ['Pemasangan 20 unit AC gudang', 'Instalasi pipa panjang, dinding beton, weekend work.'],
                    ['Servis VRV/VRF gedung 8 lantai', 'Diagnosis sistem, cleaning chiller, koordinasi building management.'],
                    ['Perbaikan panel pendingin pabrik', 'Kompresor besar, weekend shutdown.'],
                ],
                'rfqs' => [
                    ['Butuh service 20 unit AC kantor', 'Jakarta Pusat, kantor 5 lantai, cuci + cek freon.'],
                    ['Bongkar pasang 6 unit AC pindah kantor', 'Dari Kebayoran ke Cibubur, dinding gypsum.'],
                    ['Maintenance AC ruang server', '24/7 critical, redundant unit, respon 2 jam.'],
                    ['Perbaikan kulkas showcase kafe', 'Tidak dingin, kompresor bunyi.'],
                    ['Isi freon AC 4 unit rumah', 'Semua tidak dingin, rumah 2 lantai.'],
                ],
            ],
            'plumbing' => $base + [
                'fulfillments' => [['instant_booking', 'fixed', null, 90, 40], ['appointment', 'fixed', null, 120, 35], ['survey_required', 'starting_from', null, 90, 25]],
                'price_range' => [100000, 8000000],
                'delivery' => 'onsite',
                'types' => [
                    'Perbaikan Keran Bocor', 'Perbaikan Pipa Mampet', 'Perbaikan Kloset Mampet', 'Instalasi Pipa Air Baru',
                    'Perbaikan Water Heater', 'Ganti Kloset Duduk', 'Perbaikan Sambungan Pipa Bocor',
                    'Pompa Air dan Pressure Tank', 'Instalasi Sambungan Wastafel', 'Perbaikan Shower dan Kran',
                    'Drainase dan Saluran Air', 'Tukang Panggilan Instalasi Dapur', 'Perbaikan Pompa Kolam',
                    'Instalasi Tandon Air', 'Perbaikan Lemari Air (WC)',
                ],
                'specs' => ['Panggilan', 'Standar', 'Pipa PVC', 'Pipa PPR', 'Drainase', 'Air Bersih', 'Air Kotor'],
                'scopes' => ['Rumah', 'Apartemen', 'Kantor', 'Ruko', 'Kost', 'Kafe'],
                'descriptions' => [
                    'Tukang pipa berpengalaman, datang dengan alat lengkap. Diagnosis dulu, harga disepakati sebelum kerja.',
                    'Penanganan bersih: area kerja dilapisi, sisa material dibawa, tidak meninggalkan kotoran.',
                    'Garansi pengerjaan 7-30 hari tergantung jenis perbaikan.',
                ],
                'inclusions' => 'Pengecekan, alat kerja, material dasar (semen perekat, seal), tes air setelah kerja, garansi.',
                'exclusions' => 'Material besar (pipa baru, kloset, pompa) — dihitung terpisah dengan persetujuan Anda.',
                'skills' => ['Instalasi Air', 'Drainase', 'Pompa', 'Water Heater', 'Bore Pile Piping'],
                'addons' => [
                    ['Material pipa standar', 100000, 500000], ['Ganti stop kran', 75000, 200000],
                    ['Kunjungan luar jam', 50000, 150000],
                ],
                'packages' => [
                    ['name' => 'Perbaikan Ringan', 'mult' => 1.0, 'desc' => '1 titik masalah (keran/ sambungan/ flush).'],
                    ['name' => 'Perbaikan Menengah', 'mult' => 2.2, 'desc' => 'Bongkar pasang 1-2 titik + material dasar.'],
                    ['name' => 'Instalasi Area', 'mult' => 4.5, 'desc' => '1 area lengkap (kamar mandi/ dapur).'],
                ],
                'emergency' => true,
                'projects' => [
                    ['Instalasi pipa air rumah 2 lantai', 'Dari tandon ke 3 kamar mandi + dapur, PPR.'],
                    ['Perbaikan drainase kompleks 20 rumah', 'Bersama pengelola, pipa besi ke PVC.'],
                    ['Sistem air bersih kafe 2 lantai', 'Pompa + filter + tandon 2000L.'],
                    ['Renovasi instalasi kamar mandi hotel', '24 kamar bertahap, malam hari.'],
                ],
                'rfqs' => [
                    ['Renovasi kamar mandi 3x4', 'Butuh tukang pipa + keramik, total renovasi.'],
                    ['Perbaikan pompa air gedung 4 lantai', 'Pompa 3 phase, pressure tank rusak.'],
                    ['Instalasi air bersih kafe baru', 'Dari PDAM + sumur bor, dual sistem.'],
                    ['Kloset mampet berulang', 'Sudah 2x dibongkar, curiga pipa utama.'],
                ],
            ],
            'electrical' => $base + [
                'fulfillments' => [['instant_booking', 'fixed', null, 90, 35], ['appointment', 'fixed', null, 120, 35], ['survey_required', 'starting_from', null, 90, 30]],
                'price_range' => [100000, 10000000],
                'delivery' => 'onsite',
                'types' => [
                    'Instalasi Titik Lampu', 'Perbaikan MCB dan Panel Listrik', 'Instalasi Titik Stop Kontak',
                    'Perbaikan Korsleting', 'Instalasi Lampu Downlight', 'Pasang CCTV + Kelistrikan',
                    'Instalasi Genset', 'Perbaikan Trafo Rumah', 'Kelistrikan Papan Nama dan Neon Box',
                    'Instalasi Grounding', 'Upgrade Daya Listrik', 'Perbaikan Saklar dan Wiring',
                    'Instalasi Smart Home Dasar', 'Kelistrikan Sound System', 'Instalasi Listrik Gedung',
                ],
                'specs' => ['1 Titik', '5 Titik', '10 Titik', '2200 VA', '3500 VA', '4400 VA', 'Panel 3 Phase', 'Rumah', 'Kantor', 'Toko'],
                'scopes' => ['Rumah', 'Kantor', 'Toko', 'Ruko', 'Gudang', 'Kafe', 'Apartemen'],
                'descriptions' => [
                    'Listrik itu soal keselamatan — semua pengerjaan mengikuti standar PUIL dan diukur ulang setelahnya.',
                    'Elektriseter bersertifikat, mengerjakan wiring rapi dalam conduit, bukan tambal sulam.',
                    'Sertakan checklist pengecekan: arus, grounding, dan beban tiap jalur.',
                ],
                'inclusions' => 'Pengecekan awal, material dasar (kabel dalam batas paket, conduit), pengukuran akhir, garansi 30 hari.',
                'exclusions' => 'Panel besar, trafo, genset, kabel utama — dihitung terpisah setelah survei.',
                'skills' => ['Instalasi Listrik', 'Panel', 'Genset', 'Smart Home', 'PUIL', 'Grounding'],
                'addons' => [
                    ['Kabel tambahan', 50000, 400000], ['Fitur smart switch', 200000, 800000],
                    ['Surge arrester', 300000, 800000],
                ],
                'packages' => [
                    ['name' => '1 Titik', 'mult' => 1.0, 'desc' => '1 titik lampu/ stop kontak + wiring lokal.'],
                    ['name' => '5 Titik', 'mult' => 3.8, 'desc' => '5 titik + cek panel + pengukuran.'],
                    ['name' => 'Instalasi Ruangan', 'mult' => 8.0, 'desc' => '1 ruangan lengkap + material standar.'],
                ],
                'emergency' => true,
                'projects' => [
                    ['Instalasi listrik ruko 3 lantai', 'Dari MCB utama ke 3 lantai, panel distribusi, grounding.'],
                    ['Upgrade daya 3500 ke 13000 VA', 'Koordinasi PLN, panel baru, jalur kabel.'],
                    ['Instalasi listrik gudang 1000 m2', 'Lampu LED industri, jalur 3 phase, mesin.'],
                    ['Smart home 20 titik', 'Tuya, kontrol via aplikasi, scene malam.'],
                ],
                'rfqs' => [
                    ['Instalasi listrik rumah baru 2 lantai', 'Bekasi, 2200 VA, 30 titik lampu, 20 stop kontak.'],
                    ['Perbaikan panel kantor', 'MCB sering turun, panel tua, butuh overhaul.'],
                    ['Instalasi genset 10 kVA', 'Dengan ATS, untuk toko.'],
                    ['Grounding rumah tidak ada', 'Butuh 1 batang, kabel ke panel.'],
                ],
            ],
            'handyman' => $base + [
                'fulfillments' => [['instant_booking', 'fixed', null, 120, 40], ['appointment', 'fixed', null, 120, 35], ['hourly', 'hourly', 'jam', 120, 25]],
                'price_range' => [100000, 5000000],
                'delivery' => 'onsite',
                'types' => [
                    'Pasang Rak Dinding', 'Perbaikan Pintu dan Jendela', 'Pasang Gorden dan Blind',
                    'Pasang Bracket TV', 'Perbaikan Engsel dan Kunci Pintu', 'Pasang Wallpaper',
                    'Perbaikan Lemari dan Meja', 'Pasang Kasa Nyamuk', 'Perbaikan Lantai Keramik Lepas',
                    'Perbaikan Plafon Gypsum', 'Pasang Kusen Aluminium', 'Perbaikan Tangga dan Handrail',
                    'Pasang Rak Sepatu dan Lemari', 'Perbaikan Keran dan Wastafel', 'Servis Umum Rumah (Banyak Item)',
                ],
                'specs' => ['1 Item', '3 Item', 'Paket Besar', 'Kecil', 'Menengah', 'Rumah', 'Kantor'],
                'scopes' => ['Rumah', 'Apartemen', 'Kantor', 'Toko', 'Kost'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {scope} {city}', '{type} Area {city}'],
                'descriptions' => [
                    'Tukang serba bisa untuk pekerjaan rumah: datang dengan toolbox lengkap dan bahan dasar.',
                    'Kerja rapi, sampai dibersihkan. Kalau butuh material tambahan, kami tawarkan dulu.',
                    'Ideal untuk daftar "to-do" rumah Anda — kerjakan banyak item dalam satu kunjungan.',
                ],
                'inclusions' => 'Toolbox lengkap, bahan dasar (sekrup, angkur, lem), pembuangan sisa kecil, garansi pemasangan 7 hari.',
                'exclusions' => 'Material besar, perbaikan struktur, instalasi gas, kerja tinggi tanpa alat.',
                'skills' => ['Carpentry', 'Drywall', 'Mounting', 'Hardware', 'Minor Repair'],
                'addons' => [
                    ['Item tambahan', 50000, 200000], ['Material standar', 75000, 300000],
                    ['Kunjungan cepat 2 jam', 75000, 150000],
                ],
                'packages' => [
                    ['name' => '1 Jam Kunjungan', 'mult' => 1.0, 'desc' => 'Perbaikan 1-2 item kecil.'],
                    ['name' => 'Half Day', 'mult' => 2.8, 'desc' => '4 jam, hingga 6 item.'],
                    ['name' => 'Full Day', 'mult' => 5.0, 'desc' => '8 jam, daftar pekerjaan lengkap rumah.'],
                ],
                'emergency' => true,
                'projects' => [
                    ['Perbaikan 20 unit kamar kost', 'Engsel, gorden, kunci, keramik lepas.'],
                    ['Fitting out toko 50 m2', 'Rak, display, plafon, listrik.'],
                    ['Perbaikan kantor 3 lantai', 'Pintu, partisi, gypsum, lantai.'],
                    ['Pasang 100 titik rak gudang', 'Rak besi, angkan beton, level.'],
                ],
                'rfqs' => [
                    ['Butuh tukang untuk 15 titik perbaikan', 'Rumah 2 lantai di Bogor, selesai renovasi.'],
                    ['Pasang bracket TV 5 unit kantor', 'Dinding gypsum, kabel rapi.'],
                    ['Perbaikan pintu kamar mandi 8 unit', 'Kost di Depok, engsel dan kunci.'],
                    ['Pasang wallpaper kafe 40 m2', 'Wallpaper custom print.'],
                ],
            ],
            'renovation' => $base + [
                'fulfillments' => [['survey_required', 'starting_from', null, null, 70], ['project', 'milestone', null, null, 30]],
                'price_range' => [500000, 250000000],
                'delivery' => 'onsite',
                'types' => [
                    'Renovasi Kamar Mandi', 'Renovasi Dapur', 'Renovasi Rumah Bertahap', 'Renovasi Kantor',
                    'Cat Ulang Total Rumah', 'Plafon Gypsum dan false ceiling', 'Lantai Granit/Keramik',
                    'Renovasi Ruko', 'Pasang Kanopi', 'Pagar Besi dan Beton', 'Taman Minimalis',
                    'Renovasi Kamar Tidur', 'Waterproofing Atap dan Balkon', 'Renovasi Kafe dan Restoran',
                    'Renovasi Toko Retail', 'Duplex dan Mezzanine',
                ],
                'specs' => ['Kecil', 'Menengah', 'Total', '2x3', '3x4', '4x5', '3 Lantai', '1 Lantai', 'Standar', 'Premium'],
                'scopes' => ['Rumah', 'Kantor', 'Ruko', 'Kafe', 'Apartemen', 'Toko', 'Villa'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {scope} {city}', '{type} {spec} — {scope} {city}', '{type} Area {city}'],
                'descriptions' => [
                    'Mulai dari survei + RAB transparan. Tidak ada biaya siluman, semua material disepakati.',
                    'Tim tetap dengan mandor, jadwal harian dilaporkan, progress bisa dipantau via foto.',
                    'Garansi pengerjaan, material sesuai spesifikasi, revisi kecil disertakan.',
                ],
                'inclusions' => 'Survei + RAB, tenaga kerja, material sesuai RAB, pengelolaan sampah, garansi 30-90 hari.',
                'exclusions' => 'IMB/PBG, desain arsitek (opsi addon), furnitur dekoratif, perubahan RAB di tengah jalan.',
                'skills' => ['Renovasi', 'Gypsum', 'Granit', 'Cat', 'Waterproofing', 'Kanopi', 'Pagar'],
                'addons' => [
                    ['Desain 3D', 750000, 3000000], ['Waterproofing tambahan', 500000, 3000000],
                    ['Supervisi arsitek', 1000000, 5000000], ['Pembersihan pasca kerja', 500000, 1500000],
                ],
                'packages' => [
                    ['name' => 'Ringan', 'mult' => 1.0, 'desc' => 'Finishing ulang: cat, plafon, perbaikan kecil.'],
                    ['name' => 'Menengah', 'mult' => 2.5, 'desc' => 'Ganti keramik, instalasi, partisi.'],
                    ['name' => 'Total Renovation', 'mult' => 5.5, 'desc' => 'Bongkar pasang, instalasi baru, finishing penuh.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Renovasi rumah 2 lantai bertahap', 'Lantai 1 dulu, RAB 400 juta, timeline 4 bulan.'],
                    ['Renovasi kantor 200 m2', 'Open space, 3 meeting room, pantry, work from home corner.'],
                    ['Renovasi kafe bertema industrial', 'Bar, seating 40, plafon terbuka.'],
                    ['Perbaikan atap bocor + cat rumah', 'Waterproofing, ganti genteng, cat total.'],
                ],
                'rfqs' => [
                    ['Renovasi kamar mandi 3x4', 'Butuh total: keramik, closet, shower, plafon.'],
                    ['Renovasi kantor 150 m2', 'Partisi gypsum, lantai vinyl, listrik, AC.'],
                    ['Cat rumah 2 lantai', 'Luas 350 m2, cat premium, 2 warna.'],
                    ['Pasang kanopi alderon 4x6', 'Termasuk rangka besi.'],
                    ['Renovasi dapur + island', 'Kitchen set, granit, wastafel.'],
                ],
            ],
            'construction' => $base + [
                'fulfillments' => [['project', 'milestone', null, null, 60], ['survey_required', 'quotation', null, null, 40]],
                'price_range' => [5000000, 1000000000],
                'delivery' => 'onsite',
                'types' => [
                    'Pembangunan Rumah Tinggal', 'Pekerjaan Bored Pile', 'Renovasi Rumah', 'Pembuatan Kanopi',
                    'Pengecoran', 'Waterproofing', 'Pemasangan Keramik', 'Pembuatan Dak', 'Pengecatan',
                    'Pekerjaan Pondasi', 'Renovasi Kantor', 'Pembangunan Gudang', 'Struktur Baja (Rangka)',
                    'Pembangunan Ruko', 'Taman dan Landscaping', 'Paving dan Drainase Area',
                ],
                'specs' => ['1 Lantai', '2 Lantai', '3 Lantai', '500 m2', '1000 m2', 'Struktur', 'Finishing', 'Total'],
                'scopes' => ['Rumah', 'Ruko', 'Gudang', 'Kantor', 'Villa', 'Pabrik'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {spec} — {scope} {city}', '{type} {scope} {city}', '{type} Area {city}'],
                'descriptions' => [
                    'Dikerjakan kontraktor dengan legalitas lengkap (NIB, NPWP). RAB detail, termin sesuai progress.',
                    'Struktur sesuai SNI, material brand sesuai RAB, laporan mingguan dengan foto progress.',
                    'Konsultasi awal gratis, mulai dari gambar kerja hingga serah terima kunci.',
                ],
                'inclusions' => 'Gambar kerja, RAB, tenaga kerja, material sesuai RAB, safety (APD), buangan material, garansi struktur 1 tahun.',
                'exclusions' => 'IMB/PBG dan retribusi pemerintah, perubahan desain di tengah, biaya K3 khusus.',
                'skills' => ['Konstruksi', 'Struktur', 'Bored Pile', 'Cor', 'Waterproofing', 'Project Management'],
                'addons' => [
                    ['Gambar kerja arsitek', 3000000, 15000000], ['Supervisi K3', 2000000, 10000000],
                    ['Borepile tambahan', 5000000, 30000000],
                ],
                'packages' => [
                    ['name' => 'Struktur', 'mult' => 1.0, 'desc' => 'Pondasi + kolom + ring balk + dinding bata.'],
                    ['name' => 'Struktur + Finishing', 'mult' => 2.2, 'desc' => 'Hingga siap cat + keramik + kusen.'],
                    ['name' => 'Turnkey', 'mult' => 3.5, 'desc' => 'Siap huni termasuk sanitair dan listrik.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Pembangunan gudang 1200 m2', 'Baja WF, dak beton, 2 loading dock, timeline 6 bulan.'],
                    ['Bored pile untuk ruko 3 lantai', '12 titik, depth 12m, dengan laporan CPT.'],
                    ['Pembangunan rumah 2 lantai 180 m2', 'Struktur beton, modern minimalis, turnkey.'],
                    ['Pembangunan villa 6 kamar', 'Bali, gaya tropis, finishing natural.'],
                ],
                'rfqs' => [
                    ['Pembangunan gudang 800 m2', 'Bekasi, struktur baja, butuh RAB detail.'],
                    ['Pekerjaan pondasi rumah 2 lantai', 'Tanah lunak, butuh bored pile 8-10 m.'],
                    ['Pengecoran dak 150 m2', 'Beton K250, bekisting lengkap.'],
                    ['Pembangunan kandang ternak 500 m2', 'Struktur baja ringan + beton.'],
                ],
            ],
            'cctv-security' => $base + [
                'fulfillments' => [['per_unit', 'per_unit', 'unit', 120, 40], ['appointment', 'fixed', null, 240, 30], ['survey_required', 'starting_from', null, 120, 30]],
                'price_range' => [150000, 25000000],
                'delivery' => 'onsite',
                'types' => [
                    'Pemasangan CCTV 4 Kamera', 'Pemasangan CCTV 8 Kamera', 'Maintenance CCTV Bulanan',
                    'Perbaikan CCTV Mati', 'Upgrade CCTV ke 4K', 'Pemasangan Access Control',
                    'Door Lock Digital', 'Alarm Anti Maling', 'Video Door Phone', 'NVR/DVR Setup',
                    'Remote Monitoring HP', 'Pemasangan CCTV Gudang', 'CCTV AHD ke IP Migration',
                    'Sistem Keamanan Kantor', 'CCTV Cloud Storage',
                ],
                'specs' => ['2 Kamera', '4 Kamera', '8 Kamera', '16 Kamera', 'Indoor', 'Outdoor', 'IP Camera', 'Analog HD', '32 Kamera'],
                'scopes' => ['Rumah', 'Toko', 'Kantor', 'Gudang', 'Ruko', 'Pabrik'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {spec} — {scope} {city}', '{type} {scope} {city}'],
                'descriptions' => [
                    'Kamera sesuai kebutuhan (indoor/ outdoor/ night vision), kabel rapi, akses HP Android/iOS.',
                    'Garansi alat dan pemasangan, training penggunaan rekaman + remote view.',
                    'Rekomendasi posisi kamera setelah survei — bukan template asal pasang.',
                ],
                'inclusions' => 'Pemasangan, kabel + conduit rapi, konfigurasi NVR, aplikasi HP, training singkat, garansi 30 hari.',
                'exclusions' => 'Harga kamera (bisa include addon), HDD besar, cloud subscription, UPS.',
                'skills' => ['CCTV', 'IP Camera', 'NVR', 'Access Control', 'Network', 'Alarm'],
                'addons' => [
                    ['Kamera tambahan', 400000, 1500000], ['HDD 2TB', 800000, 1200000],
                    ['UPS untuk NVR', 500000, 1200000], ['Cloud storage 1 bulan', 100000, 300000],
                ],
                'packages' => [
                    ['name' => '4 Kamera', 'mult' => 1.0, 'desc' => '2 indoor + 2 outdoor, NVR 4 channel.'],
                    ['name' => '8 Kamera', 'mult' => 1.9, 'desc' => 'Kombinasi indoor/outdoor, NVR 8 channel.'],
                    ['name' => 'Full Coverage', 'mult' => 3.5, 'desc' => '16 kamera + access control + alarm.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Instalasi CCTV 32 kamera gudang', 'NVR 36 channel, 8 TB storage, remote management.'],
                    ['Keamanan toko retail 3 cabang', 'CCTV + EAS + access control terintegrasi.'],
                    ['Upgrade CCTV analog ke IP 12 kamera', 'Migrasi bertahap, malam hari.'],
                    ['CCTV perumahan cluster 20 titik', 'Gerbang + jalan utama, monitoring pos satpam.'],
                ],
                'rfqs' => [
                    ['Maintenance CCTV 32 kamera', 'Kantor 5 lantai, cek berkala 3 bulan.'],
                    ['Pemasangan CCTV 8 kamera rumah', 'Bekasi, 4 indoor 4 outdoor, night vision.'],
                    ['Access control 4 pintu kantor', 'Kartu + PIN, log kehadiran.'],
                    ['CCTV toko 12 kamera', 'Ruko 2 lantai, remote dari HP owner.'],
                ],
            ],
            'pest-control' => $base + [
                'fulfillments' => [['appointment', 'fixed', null, 120, 45], ['per_unit', 'per_unit', 'unit', 90, 25], ['fixed_package', 'fixed', null, 120, 30]],
                'price_range' => [150000, 4000000],
                'delivery' => 'onsite',
                'types' => [
                    'Fogging Nyamuk', 'Basmi Kecoa dan Kecil-Busuk', 'Anti Rayap (Pre/Post Construction)',
                    'Basmi Tikus', 'Pest Control Kantor Bulanan', 'Disinfeksi dan Sanitasi',
                    'Basmi Kutu Kasur', 'Fumigasi Gudang', 'Pest Control Restoran',
                    'Basmi Lalat dan Serangga', 'Anti Rayap Rumah', 'Pest Control Pabrik',
                    'Kontrol Hama Taman', 'Sanitasi Septic Tank',
                ],
                'specs' => ['Rumah', 'Apartemen', 'Kantor', 'Gudang', 'Restoran', '1 Kali', 'Bulanan', 'Kontrak 3 Bulan'],
                'scopes' => ['Rumah', 'Kantor', 'Gudang', 'Restoran', 'Pabrik', 'Toko'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {scope} {city}', '{type} Area {city}'],
                'descriptions' => [
                    'Menggunakan bahan kimia aman untuk manusia dan hewan peliharaan, terdaftar Kementerian Pertanian.',
                    'Teknisi bersertifikat, laporan kerja lengkap dengan area yang diperlakukan.',
                    'Paket langganan hemat untuk kebutuhan rutin bulanan/ kuartalan.',
                ],
                'inclusions' => 'Survei, perlakuan sesuai hama, laporan kerja, garansi efektivitas (retreatment bila perlu).',
                'exclusions' => 'Perbaikan struktural penyebab hama, fogging area luar gedung > 1000 m2.',
                'skills' => ['HAMA', 'Fumigasi', 'Disinfeksi', 'Anti Rayap', 'HSE'],
                'addons' => [
                    ['Retreatment garansi', 0, 150000], ['Disinfeksi tambahan', 250000, 750000],
                    ['Gel bait kecoa', 150000, 400000],
                ],
                'packages' => [
                    ['name' => '1 Kali', 'mult' => 1.0, 'desc' => 'Perlakuan lengkap 1x untuk seluruh area.'],
                    ['name' => 'Bulanan', 'mult' => 2.5, 'desc' => '4 kunjungan, rotasi bahan agar hama tidak resisten.'],
                    ['name' => 'Kontrak 3 Bulan+', 'mult' => 6.0, 'desc' => '12 kunjungan + laporan + koordinasi HSE.'],
                ],
                'emergency' => true,
                'projects' => [
                    ['Kontrak pest control 6 bulan restoran', 'HACCP compliance, laporan per kunjungan.'],
                    ['Anti rayap gudang 2000 m2', 'Drilling + injection perimeter.'],
                    ['Fogging 3 cluster perumahan', 'Koordinasi RT, larvasida + fogging.'],
                    ['Disinfeksi kantor 4 lantai', 'Electrostatic spray, weekend.'],
                ],
                'rfqs' => [
                    ['Butuh pest control kantor bulanan', 'Jakarta Barat, 3 lantai, kecoa dan tikus.'],
                    ['Anti rayap rumah baru', '200 m2, pre-construction treatment.'],
                    ['Basmi kutu kasur hotel 30 kamar', 'Bertahap, lantai demi lantai.'],
                    ['Disinfeksi klinik gigi', 'Aman untuk alat medis, malam hari.'],
                ],
            ],
            'automotive' => $base + [
                'fulfillments' => [['instant_booking', 'fixed', null, 60, 40], ['appointment', 'fixed', null, 120, 35], ['per_unit', 'per_unit', 'unit', 60, 25]],
                'price_range' => [75000, 5000000],
                'delivery' => 'onsite',
                'types' => [
                    'Ganti Oli Panggilan', 'Service Mobil Berkala', 'Jump Start Aki', 'Ganti Aki',
                    'Tambal Ban Panggilan', 'Detailing Mobil', 'Cuci Mobil Premium', 'Service Rem',
                    'Tune Up', 'Inspeksi Mobil Bekas', 'Ganti Kampas Rem', 'Spooring dan Balancing',
                    'Ganti Busi', 'Flushing Radiator', 'Servis AC Mobil', 'Ganti Timing Belt',
                    'Polish dan Wax', 'Coating Keramik Mobil', 'Cuci Motor Jemput', 'Servis Motor Ringan',
                ],
                'specs' => ['Avanza/Xenia', 'Brio', 'Innova', 'Fortuner', 'Toyota', 'Honda', 'Daihatsu', 'Mitsubishi', 'SUV', 'MPV', 'City Car', 'Motor Matic', 'Motor Bebek'],
                'scopes' => ['Rumah', 'Kantor', 'Jalan Raya', 'Ruko', 'Apartemen'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {scope} {city}', '{type} Area {city}'],
                'descriptions' => [
                    'Mekanik berpengalaman datang ke lokasi dengan peralatan dan oli sesuai spesifikasi mobil.',
                    'Cek keluhan dulu, tunjukkan komponen, baru kerja. Invoice transparan.',
                    'Garansi pengerjaan, oli dan part original/ rekanan sesuai pilihan Anda.',
                ],
                'inclusions' => 'Kunjungan ke lokasi, pengecekan, pengerjaan, pembuangan oli bekas, cek 10 titik standar.',
                'exclusions' => 'Part besar (transmisi, mesin), bahan bakar, pengangkutan mobil ke bengkel.',
                'skills' => ['Oli', 'Aki', 'Ban', 'Engine', 'Detailing', 'Cuci Motor'],
                'addons' => [
                    ['Ganti filter oli', 50000, 150000], ['Ganti filter udara', 50000, 200000],
                    ['Tambal ban tubeless', 50000, 100000], ['Cek 20 titik', 75000, 150000],
                ],
                'packages' => [
                    ['name' => 'Oli + Cek', 'mult' => 1.0, 'desc' => 'Ganti oli + cek 10 titik.'],
                    ['name' => 'Berkala 10K', 'mult' => 2.0, 'desc' => 'Oli, filter, tune up ringan, cek lengkap.'],
                    ['name' => 'Berkala 40K', 'mult' => 4.0, 'desc' => 'Oli, filter, busi, spoilring, AC.'],
                ],
                'emergency' => true,
                'projects' => [
                    ['Servis armada 15 mobil perusahaan', 'Kontrak bulanan, cek berkala + darurat.'],
                    ['Detailing 5 mobil eksekutif', 'Coating keramik + interior deep clean.'],
                    ['Cuci mobil kantor 40 unit/bulan', 'Jemput antar tiap Jumat.'],
                    ['Inspeksi 20 mobil fleet bekas', 'Laporan kondisi per unit.'],
                ],
                'rfqs' => [
                    ['Butuh ganti oli 3 mobil kantor', 'Daihatsu Sigra + Honda Brio, di kantor.'],
                    ['Detailing mobil interior', 'Jok kotor bau, butuh deep cleaning.'],
                    ['Inspeksi mobil bekas sebelum beli', 'Fortuner 2019, di showroom.'],
                    ['Tambal ban di rumah malam hari', 'Ban kempes, puncak, butuh segera.'],
                ],
            ],
            'moving-logistics' => $base + [
                'fulfillments' => [['survey_required', 'starting_from', null, null, 55], ['per_unit', 'per_unit', 'unit', 240, 25], ['fixed_package', 'fixed', null, 480, 20]],
                'price_range' => [300000, 15000000],
                'delivery' => 'onsite',
                'types' => [
                    'Jasa Pindahan Rumah', 'Pindahan Apartemen', 'Pindahan Kantor', 'Sewa Truk + Tukang',
                    'Angkut Barang Besar', 'Pindahan Gudang', 'Ekspedisi Barang Antarkota', 'Angkut Barang Kost',
                    'Pindahan Pabrik', 'Bongkar Muat Container', 'Angkut Material Bangunan', 'Penyimpanan Barang (Storage)',
                ],
                'specs' => ['Pick Up', 'CDD', 'Engkel', 'Truk Besar', '1 Trip', '2 Trip', '3 Tukang', '5 Tukang', 'Antarkota'],
                'scopes' => ['Rumah', 'Apartemen', 'Kantor', 'Gudang', 'Kost', 'Pabrik'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {spec} — {scope} {city}', '{type} {scope} {city}'],
                'descriptions' => [
                    'Barang dikemas dengan bubble wrap dan karung, asuransi opsional, driver berpengalaman.',
                    'Survei dulu (foto via chat) untuk harga final — tidak ada tambahan sembarangan di lokasi.',
                    'Tim rapi, barang difoto sebelum muat, tanda tangan serah terima.',
                ],
                'inclusions' => 'Kemasan dasar, muat dan bongkar, perlindungan lantai, serah terima berfoto.',
                'exclusions' => 'Kemasan premium (crate), asuransi penuh, parkir dan tol, barang berbahaya.',
                'skills' => ['Pindahan', 'Packaging', 'Logistik', 'Angkut', 'Forklift'],
                'addons' => [
                    ['Tukang tambahan', 100000, 250000], ['Bubble wrap premium', 150000, 500000],
                    ['Asuransi barang', 100000, 500000], ['Kemasan khusus kaca', 200000, 600000],
                ],
                'packages' => [
                    ['name' => '1 Trip Kecil', 'mult' => 1.0, 'desc' => 'Pick up + 2 tukang, barang 1 kamar.'],
                    ['name' => 'Rumah Standar', 'mult' => 2.5, 'desc' => 'CDD + 4 tukang, rumah 2 kamar.'],
                    ['name' => 'Rumah Besar', 'mult' => 5.0, 'desc' => 'Truk besar + 6 tukang, full packaging.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Relokasi kantor 150 karyawan', '3 hari, 400 karton, aset IT ditangani terpisah.'],
                    ['Pindahan gudang 800 m2', 'Bekasi ke Cikarang, forklift + truk besar.'],
                    ['Pindahan rumah antarkota', 'Jakarta ke Bandung, 2 trip, barang rapuh.'],
                    ['Angkut mesin pabrik 5 ton', 'Crane + truk flatbed, weekend.'],
                ],
                'rfqs' => [
                    ['Pindahan rumah 2 lantai', 'Bogor ke Depok, ± 60 karton, ada piano.'],
                    ['Pindahan kantor 100 m2', 'Jakarta Selatan ke Tangerang, weekend.'],
                    ['Angkut barang kost 12 kamar', 'Kosongkan seluruh kost, ke 3 alamat.'],
                    ['Ekspedisi antarkota 5 karton', 'Jakarta ke Semarang, barang pecah belah.'],
                ],
            ],
            'event-services' => $base + [
                'fulfillments' => [['survey_required', 'starting_from', null, null, 40], ['fixed_package', 'fixed', null, 480, 30], ['per_unit', 'per_unit', 'unit', 480, 30]],
                'price_range' => [500000, 50000000],
                'delivery' => 'onsite',
                'types' => [
                    'Sewa Tenda dan Perlengkapan Event', 'Dekorasi Panggung Wedding', 'Sound System Event',
                    'Lighting Panggung', 'Event Organizer Pernikahan', 'EO Event Corporate',
                    'MC dan Host Event', 'Sewa Meja Kursi Pesta', 'Katering Prasmanan Event',
                    'Photobooth Event', 'Sewa LED Screen', 'Dekorasi Ulang Tahun',
                    'Live Music Wedding', 'Rilis Bunga dan Balon', 'Genset Event',
                ],
                'specs' => ['Kecil (50 Orang)', 'Menengah (150 Orang)', 'Besar (300+ Orang)', '1 Hari', '2 Hari', 'Tenda 4x6', 'Tenda 6x12', 'Panggung 4x6'],
                'scopes' => ['Rumah', 'Kantor', 'Ballroom', 'Taman', 'Gedung', 'Tepi Pantai', 'Outdoor'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {spec} — {scope} {city}', '{type} {scope} {city}'],
                'descriptions' => [
                    'Paket lengkap dari konsep hingga teardown. Koordinator on-site selama acara berlangsung.',
                    'Perangkat terawat, crew berpengalaman, backup perangkat untuk keandalan acara.',
                    'Timeline detail dibuat 2 minggu sebelum acara, briefing crew sebelum acara.',
                ],
                'inclusions' => 'Konsultasi konsep, perangkat sesuai paket, crew, setup + teardown, koordinator acara.',
                'exclusions' => 'Sewa venue, catering (kecuali paket), konsumsi crew, transportasi luar kota, pajak.',
                'skills' => ['EO', 'Sound System', 'Lighting', 'Dekorasi', 'MC', 'Katering', 'LED'],
                'addons' => [
                    ['Crew tambahan', 300000, 1000000], ['Genset silent 10 kVA', 1000000, 3000000],
                    ['Photobooth', 1500000, 4000000], ['Balon dekorasi', 500000, 2000000],
                ],
                'packages' => [
                    ['name' => 'Intimate', 'mult' => 1.0, 'desc' => 'Hingga 50 tamu: tenda + sound + dekorasi dasar.'],
                    ['name' => 'Standar', 'mult' => 2.5, 'desc' => '150 tamu: + lighting + MC + catering dasar.'],
                    ['name' => 'Premium', 'mult' => 5.5, 'desc' => '300+ tamu: full production + LED + EO penuh.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Wedding 400 tamu outdoor', 'Bekasi, tenda 20x30, stage, lighting, catering 400 pax.'],
                    ['Gathering karyawan 300 orang', 'Offsite 1 hari, games, sound, photobooth.'],
                    ['Grand opening toko', 'Panggung kecil, MC, pengisi acara, konsumsi.'],
                    ['Seminar perusahaan 200 pax', 'Sound, LED, catering, dokumentasi.'],
                ],
                'rfqs' => [
                    ['Butuh tenda + kursi acara keluarga', '200 tamu, rumah Bogor, 1 hari.'],
                    ['EO pernikahan 300 tamu', 'Jogja, konsep Jawa modern, wedding organizer penuh.'],
                    ['Sound system + lighting event komunitas', 'Panggung 6x4, 2 hari.'],
                    ['Katering prasmanan 150 orang', 'Menu nasi kotak, kantor Jakarta Pusat.'],
                    ['Dekorasi ulang tahun anak', 'Tema safari, balon, backdrop.'],
                ],
            ],
            'photography' => $base + [
                'fulfillments' => [['appointment', 'fixed', null, 240, 45], ['per_unit', 'per_unit', 'unit', 120, 25], ['project', 'starting_from', null, null, 30]],
                'price_range' => [250000, 30000000],
                'delivery' => 'onsite',
                'types' => [
                    'Foto Prewedding', 'Foto Wedding Akad', 'Foto Wedding Akad + Resepsi', 'Foto Keluarga',
                    'Foto Produk E-commerce', 'Foto Makanan dan Minuman', 'Foto Corporate Headshot',
                    'Videografi Event', 'Foto Event Seminar', 'Foto Property dan Interior',
                    'Foto Katalog Fashion', 'Foto Bayi dan Maternity', 'Foto Sekolah/ Wisuda',
                    'Drone Aerial Photo', 'Foto Automotive', 'Foto Hotel dan Resort',
                ],
                'specs' => ['1 Jam', '3 Jam', '8 Jam', '1 Hari', 'Basic Edit', 'Full Edit', '20 Foto', '50 Foto', 'Semua File', 'Album Cetak'],
                'scopes' => ['Studio', 'Outdoor', 'Indoor', 'Rumah', 'Kantor', 'Hotel'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {spec} — {scope} {city}', '{type} {scope} {city}'],
                'descriptions' => [
                    'Hasil edit profesional, file dikirim via cloud maksimal 7 hari kerja, preview 24 jam setelah sesi.',
                    'Kameraman backup, retouching wajah termasuk, lokasi/ jam golden hour disarankan.',
                    'Untuk produk: lighting setup sesuai marketplace, retouching detail, konsistensi warna.',
                ],
                'inclusions' => 'Sesi foto sesuai durasi, edit sesuai paket, file digital via cloud, hak pakai untuk klien.',
                'exclusions' => 'Cetak fisik (opsi addon), sewa studio/ kamera, videografi terpisah,travel luar kota.',
                'skills' => ['Wedding Photography', 'Product Photography', 'Lightroom', 'Photoshop', 'Drone', 'Videografi'],
                'addons' => [
                    ['Jam tambahan', 250000, 750000], ['Album cetak premium', 1000000, 4000000],
                    ['Video highlight 2 menit', 1500000, 5000000], ['Retouching premium', 100000, 300000],
                    ['Drone shot', 500000, 1500000],
                ],
                'packages' => [
                    ['name' => 'Basic Session', 'mult' => 1.0, 'desc' => '1 jam, 15 foto edited.'],
                    ['name' => 'Half Day', 'mult' => 2.2, 'desc' => '4 jam, 40 foto edited + 1 lokasi.'],
                    ['name' => 'Full Day', 'mult' => 4.0, 'desc' => '8 jam, semua file edited + album digital.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Foto katalog produk 100 SKU', 'Studio, white background, 3 angle per produk.'],
                    ['Wedding 1 hari full', 'Akad + resepsi, 2 photographer + 1 video.'],
                    ['Foto property 3 unit apartemen', 'Wide angle + twilight shot.'],
                    ['Corporate profile video 3 menit', 'Interview + b-roll + animation text.'],
                ],
                'rfqs' => [
                    ['Butuh fotografer akad dan resepsi', 'Bandung, 1 hari, 2 photographer.'],
                    ['Foto produk skincare 20 SKU', 'Butuh white background + lifestyle 5 foto.'],
                    ['Foto makanan menu baru kafe', '30 menu, natural light + properti.'],
                    ['Foto tim kantor 50 orang', 'Headshot studio mini di kantor.'],
                ],
            ],
            'education' => $base + [
                'fulfillments' => [['appointment', 'fixed', null, 60, 50], ['fixed_package', 'fixed', null, null, 30], ['per_unit', 'per_unit', 'sesi', 60, 20]],
                'price_range' => [50000, 5000000],
                'delivery' => 'hybrid',
                'types' => [
                    'Les Matematika SD', 'Les Matematika SMP', 'Les Matematika SMA', 'Les Fisika SMA',
                    'Les Kimia SMA', 'Les Bahasa Inggris', 'Les Bahasa Jepang', 'Bimbel UTBK',
                    'Bimbel CPNS', 'Les Piano untuk Anak', 'Les Gitar', 'Les Vokal',
                    'Les Menggambar/Melukis', 'Bimbel Calistung', 'Les Coding untuk Anak',
                    'Les Robotika', 'Kursus Microsoft Excel', 'Training Public Speaking',
                ],
                'specs' => ['1 Sesi', '4 Sesi', '8 Sesi', 'Bulanan', 'Online', 'Datang ke Rumah', 'Privat', 'Semi-Privat (2 Orang)', 'Grup 5 Orang'],
                'scopes' => ['SD', 'SMP', 'SMA', 'Anak', 'Dewasa', 'Pemula', 'Menengah'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {spec} — {scope} {city}', '{type} {scope} {city}', '{type} Area {city}'],
                'descriptions' => [
                    'Materi disusun sesuai kurikulum dan kecepatan belajar siswa, laporan progres per bulan.',
                    'Tutor berpengalaman, metode interaktif, tugas terukur, coba 1 sesi dulu.',
                    'Bisa online via Zoom/Google Meet atau tatap muka di rumah Anda.',
                ],
                'inclusions' => 'Assesment awal, materi + latihan, laporan progres, komunikasi dengan orang tua (untuk anak).',
                'exclusions' => 'Buku luar (bisa disarankan), biaya ujian resmi, transport tutor luar kota.',
                'skills' => ['Matematika', 'Fisika', 'English', 'Japanese', 'Piano', 'Coding', 'Calistung', 'UTBK'],
                'addons' => [
                    ['Sesi tambahan', 75000, 250000], ['Laporan detail orang tua', 50000, 150000],
                    ['Materi cetak', 50000, 200000],
                ],
                'packages' => [
                    ['name' => 'Coba 1 Sesi', 'mult' => 1.0, 'desc' => 'Assesment + 1 sesi 60-90 menit.'],
                    ['name' => 'Paket 4 Sesi', 'mult' => 3.5, 'desc' => '4 sesi mingguan + materi.'],
                    ['name' => 'Paket 12 Sesi', 'mult' => 9.5, 'desc' => '3 bulan + laporan progres + bonus konsultasi.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Les privat 10 siswa SMA', 'Fisika + matematika, 2x seminggu, ragunan.'],
                    ['Pelatihan Excel untuk kantor 30 orang', '2 batch, dari dasar hingga pivot table.'],
                    ['Kelas coding anak 20 siswa', 'Scratch + Python dasar, 3 bulan.'],
                    ['Bimbel UTBK intensif 40 siswa', 'Tryout berkala, 8 minggu.'],
                ],
                'rfqs' => [
                    ['Butuh les matematika SMA kelas 12', '2x seminggu, fokus UTBK.'],
                    ['Les bahasa Inggris anak SD', 'Datang ke rumah, Sabtu pagi.'],
                    ['Pelatihan public speaking 10 karyawan', '2 hari workshop di kantor.'],
                    ['Les piano untuk anak 8 tahun', 'Pemula, keyboard di rumah.'],
                ],
            ],
            'personal-services' => $base + [
                'fulfillments' => [['appointment', 'fixed', null, 60, 45], ['instant_booking', 'fixed', null, 60, 30], ['per_unit', 'per_unit', 'sesi', 60, 25]],
                'price_range' => [50000, 1500000],
                'delivery' => 'onsite',
                'types' => [
                    'Pijat Panggilan Relaksasi', 'Pijat Panggilan Tradisional', 'Pijat Bayi dan Anak',
                    'Pijat Ibu Hamil', 'Potong Rambut Panggilan', 'Makeup Party Panggilan',
                    'Makeup Prewedding', 'Makeup Wisuda', 'Manicure Pedicure Panggilan',
                    'Hair Styling Panggilan', 'Pet Grooming Panggilan', 'Terapi Pijat Sport',
                    'Asistensi Belanja Jasa', 'Kursir Panggilan',
                ],
                'specs' => ['60 Menit', '90 Menit', '120 Menit', '1 Orang', '2 Orang', 'Paket 4x', 'Terapis Wanita', 'Terapis Pria'],
                'scopes' => ['Rumah', 'Apartemen', 'Kantor', 'Hotel', 'Studio'],
                'title_patterns' => ['{type} {spec} {city}', '{type} {spec} — {scope} {city}', '{type} {scope} {city}'],
                'descriptions' => [
                    'Terapis bersertifikat, bawa peralatan sendiri (matras, minyak), higienis dan profesional.',
                    'Bisa untuk pria/ wanita, terapis sesuai permintaan, bukan layanan liar.',
                    'Booking minimal 2 jam sebelumnya, area Jabodetabek.',
                ],
                'inclusions' => 'Terapis, peralatan dasar, minyak/ lotion, pembuangan sampah kecil, higienitas terjaga.',
                'exclusions' => 'Terapi medis, obat-obatan, makeup premium (diminta terpisah), hair wash (butuh air).',
                'skills' => ['Pijat', 'Makeup', 'Grooming', 'Hair Styling', 'Therapy'],
                'addons' => [
                    ['Terapis tambahan', 100000, 200000], ['Scrubs', 50000, 150000],
                    ['Pijat refleksi tambahan', 50000, 150000],
                ],
                'packages' => [
                    ['name' => '1 Sesi', 'mult' => 1.0, 'desc' => '60 menit untuk 1 orang.'],
                    ['name' => 'Couple', 'mult' => 1.8, 'desc' => '60 menit untuk 2 orang, 2 terapis.'],
                    ['name' => 'Paket 4x', 'mult' => 3.5, 'desc' => '4 sesi 60 menit, bisa untuk keluarga.'],
                ],
                'emergency' => false,
                'projects' => [
                    ['Pijat karyawan hari kesehatan', '50 orang, 2 terapis, 1 hari.'],
                    ['Makeup bridal party 8 orang', 'Di rumah, pagi hari, hairdo + makeup.'],
                    ['Pet grooming 20 anjing apartemen', 'Bertahap, grooming van.'],
                ],
                'rfqs' => [
                    ['Butuh pijat panggilan 2 orang', 'Malam hari, apartemen Jakarta Utara.'],
                    ['Makeup wisuda 3 orang', 'Kampus UI Depok, pagi.'],
                    ['Pet grooming anjing medium', 'Bekasi, shih tzu, full grooming.'],
                    ['Pijat ibu hamil trimester 3', 'Terapis wanita berpengalaman.'],
                ],
            ],
            default => throw new \InvalidArgumentException("Unknown demo category [{$slug}]"),
        };
    }
}
