<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicInfoController extends Controller
{
    public function __invoke(string $page): View
    {
        $pages = [
            'status' => ['title' => 'Status SuperSoft', 'label' => 'Status layanan', 'summary' => 'Informasi kesiapan layanan SuperSoft Enterprise.', 'items' => ['Aplikasi lokal siap digunakan', 'Database dan modul inti terhubung', 'Pemantauan VPS akan aktif setelah deployment']],
            'security' => ['title' => 'Keamanan SuperSoft', 'label' => 'Security noticeboard', 'summary' => 'Keamanan data dan akses adalah bagian utama pengembangan SuperSoft Enterprise.', 'items' => ['Akses berbasis peran dan perusahaan', 'Audit aktivitas penting', 'API key disimpan di konfigurasi server, bukan source code']],
            'terms' => ['title' => 'Ketentuan Penggunaan', 'label' => 'SuperSoft Enterprise', 'summary' => 'SuperSoft Enterprise menyediakan rangkaian modul bisnis yang dapat diaktifkan sesuai lisensi perusahaan.', 'items' => ['Pengguna wajib menjaga kerahasiaan akun', 'Data transaksi harus ditinjau oleh pihak berwenang', 'Rekomendasi AI bersifat pendukung keputusan']],
            'privacy' => ['title' => 'Privasi', 'label' => 'Perlindungan data', 'summary' => 'SuperSoft memproses data sesuai kebutuhan operasional organisasi.', 'items' => ['Data dibatasi berdasarkan hak akses', 'Informasi sensitif tidak ditampilkan tanpa izin', 'Integrasi eksternal dikendalikan melalui konfigurasi dan guardrail']],
            'help' => ['title' => 'Pusat Bantuan', 'label' => 'Bantuan SuperSoft', 'summary' => 'Butuh bantuan penggunaan, akses, atau pelaporan masalah?', 'items' => ['Hubungi administrator SuperSoft perusahaan Anda', 'Sertakan modul, waktu kejadian, dan tangkapan layar bila ada', 'Pertanyaan pengembangan dapat dikirim melalui kontak resmi']],
            'access' => ['title' => 'Akses & Kemitraan', 'label' => 'Bergabung dengan SuperSoft', 'summary' => 'Akses baru, kerja sama, dan peluang investasi ditangani langsung oleh pengembang.', 'items' => ['Permintaan akses melalui administrator perusahaan', 'Demo dan kerja sama pengembangan tersedia sesuai kebutuhan', 'Investor dapat menghubungi pengembang melalui kontak resmi']],
        ];

        abort_unless(isset($pages[$page]), 404);

        return view('public.info', $pages[$page]);
    }
}
