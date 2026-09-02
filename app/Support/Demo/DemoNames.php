<?php

namespace App\Support\Demo;

/**
 * Deterministic Indonesian personal + business name pools.
 * No real/misleading brand names — pure fictional combinations.
 */
final class DemoNames
{
    public const FIRST = [
        'Ahmad', 'Budi', 'Cahyo', 'Dimas', 'Eko', 'Fajar', 'Gunawan', 'Hendra', 'Irfan', 'Joko',
        'Krisna', 'Lukman', 'Made', 'Nanda', 'Oscar', 'Putra', 'Qori', 'Rizki', 'Santoso', 'Taufik',
        'Umar', 'Vino', 'Wahyu', 'Yoga', 'Zaki', 'Agus', 'Bayu', 'Candra', 'Deni', 'Erlangga',
        'Farhan', 'Galih', 'Hariyanto', 'Ilham', 'Jamal', 'Kurniawan', 'Lutfi', 'Miftah', 'Nur', 'Oki',
        'Pandu', 'Rendra', 'Setiawan', 'Tri', 'Usman', 'Wibowo', 'Yudha', 'Zainal', 'Andi', 'Bagas',
    ];

    public const LAST = [
        'Santoso', 'Wijaya', 'Kusuma', 'Pratama', 'Saputra', 'Hidayat', 'Nugroho', 'Maulana', 'Firmansyah', 'Ramadhan',
        'Setiawan', 'Wibowo', 'Utomo', 'Halim', 'Susanto', 'Gunawan', 'Handoko', 'Prasetyo', 'Yusuf', 'Sinaga',
        'Simatupang', 'Hutagalung', 'Sihombing', 'Sitompul', 'Manurung', 'Sitorus', 'Nainggolan', 'Siregar', 'Tanjung', 'Lubis',
        'Putra', 'Mahendra', 'Alamsyah', 'Basri', 'Cahyono', 'Darmawan', 'Ekaputra', 'Fauzi', 'Gultom', 'Harahap',
        'Iskandar', 'Junaedi', 'Kurniadi', 'Lesmana', 'Mulyana', 'Nurhalim', 'Oktaviano', 'Purnama', 'Rahmat', 'Salim',
    ];

    public const FEMALE_FIRST = [
        'Anisa', 'Bunga', 'Citra', 'Dewi', 'Endah', 'Fitri', 'Gita', 'Hana', 'Indah', 'Juwita',
        'Kartika', 'Lestari', 'Maya', 'Nadia', 'Okta', 'Putri', 'Rina', 'Sari', 'Tiara', 'Umi',
        'Vina', 'Wulan', 'Yuni', 'Zahra', 'Ayu', 'Dian', 'Eka', 'Farah', 'Gloria', 'Hesti',
    ];

    public const COMPANY_PREFIX = ['CV', 'PT', 'CV', 'PT'];

    public const COMPANY_WORDS = [
        'Maju', 'Surya', 'Andalan', 'Cipta', 'Nusa', 'Sinar', 'Mandiri', 'Sejahtera', 'Karya', 'Bumi',
        'Harapan', 'Aneka', 'Mitra', 'Tirta', 'Agung', 'Satria', 'Anugerah', 'Berkah', 'Cahaya', 'Delta',
        'Garuda', 'Hasta', 'Indra', 'Kencana', 'Langgeng', 'Mahakam', 'Nirmala', 'Oktavia', 'Pelangi', 'Rukun',
    ];

    public const COMPANY_SUFFIX = [
        'Teknik', 'Elektrik', 'Bersih Pro', 'AC Service', 'Digital Studio', 'Kreatif', 'Konstruksi',
        'Facility Service', 'Logistik', 'Teknologi', 'Services', 'Maintain', 'Solusi', 'Engineering', 'Home Care',
    ];

    public const STUDIO_PREFIX = [
        'BersihPro', 'Surya', 'Andalan', 'Nusa', 'Rapi', 'Cepat', 'Prima', 'Sentra', 'Ardi', 'Baja',
    ];

    public static function person(): string
    {
        if (mt_rand(1, 100) <= 35) {
            $first = self::FEMALE_FIRST[mt_rand(0, count(self::FEMALE_FIRST) - 1)];
        } else {
            $first = self::FIRST[mt_rand(0, count(self::FIRST) - 1)];
        }

        return $first.' '.self::LAST[mt_rand(0, count(self::LAST) - 1)];
    }

    public static function personMale(): string
    {
        return self::FIRST[mt_rand(0, count(self::FIRST) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)];
    }

    public static function personFemale(): string
    {
        return self::FEMALE_FIRST[mt_rand(0, count(self::FEMALE_FIRST) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)];
    }

    public static function company(): string
    {
        $pattern = mt_rand(1, 100);

        if ($pattern <= 55) {
            return self::COMPANY_PREFIX[mt_rand(0, count(self::COMPANY_PREFIX) - 1)]
                .' '.self::COMPANY_WORDS[mt_rand(0, count(self::COMPANY_WORDS) - 1)]
                .' '.self::COMPANY_SUFFIX[mt_rand(0, count(self::COMPANY_SUFFIX) - 1)];
        }

        if ($pattern <= 85) {
            return self::STUDIO_PREFIX[mt_rand(0, count(self::STUDIO_PREFIX) - 1)]
                .' '.self::COMPANY_SUFFIX[mt_rand(0, count(self::COMPANY_SUFFIX) - 1)];
        }

        return self::COMPANY_WORDS[mt_rand(0, count(self::COMPANY_WORDS) - 1)]
            .' '.self::COMPANY_WORDS[mt_rand(0, count(self::COMPANY_WORDS) - 1)]
            .' '.self::COMPANY_SUFFIX[mt_rand(0, count(self::COMPANY_SUFFIX) - 1)];
    }

    /** Non-routable dummy Indonesian mobile (081-0000-xxxx style). */
    public static function dummyPhone(int $sequence): string
    {
        return '0812-0000-'.str_pad((string) ($sequence % 10000), 4, '0', STR_PAD_LEFT);
    }

    public static function dummyNpwp(int $sequence): string
    {
        $n = str_pad((string) ($sequence % 100000000000), 12, '7', STR_PAD_LEFT);

        return substr($n, 0, 2).'.'.substr($n, 2, 3).'.'.substr($n, 5, 3).'.1-0'.($sequence % 10).'.000';
    }

    public static function dummyNib(int $sequence): string
    {
        return '8120'.str_pad((string) ($sequence % 100000000000), 12, '5', STR_PAD_LEFT);
    }
}
