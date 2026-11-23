<?php

namespace App\Services\Otp;

use App\Models\OtpRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Mengatur seluruh aturan OTP. Controller nanti cukup memanggil service ini.
class OtpService
{
    // Aturan dari panduan tim, ditulis sebagai konstanta agar mudah dibaca dan dipakai di test.
    public const PANJANG_KODE = 6;               // kode 6 digit
    public const MASA_BERLAKU_MENIT = 5;         // kode berlaku 5 menit
    public const JEDA_KIRIM_ULANG_DETIK = 60;    // jeda minimal antar permintaan
    public const MAKS_PERMINTAAN_PER_HARI = 5;   // maksimal 5 permintaan per 24 jam
    public const MAKS_PERCOBAAN = 5;             // maksimal 5 kali salah mengetik kode  

    // Laravel mengisi $pengirim otomatis dari container (LogOtpSender, atau pengirim palsu saat test).
    public function __construct(private OtpSender $pengirim) {}

    // Membuat dan mengirim satu kode OTP. Melempar OtpDitolakException jika melanggar aturan.
    public function kirim(User $user, string $nomorMentah): OtpRequest
    {
        // Ubah ke format baku dulu: semua aturan di bawah harus membandingkan nomor yang SAMA bentuknya.
        $nomor = PhoneNumber::normalize($nomorMentah);

        if ($nomor === null) {
            throw OtpDitolakException::nomorTidakValid();
        }

        // Semua dalam satu transaksi: kalau pengiriman gagal, baris OTP ikut dibatalkan
        // sehingga permintaan yang gagal tidak menghabiskan kuota pengguna.
        return DB::transaction(function () use ($user, $nomor) {
            // Kunci baris user selama transaksi, supaya dua klik bersamaan tidak sama-sama lolos
            // pemeriksaan jeda. (Pada SQLite saat test, kunci ini diabaikan.)
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            // Kuota diperiksa LEBIH DULU daripada jeda. Kalau kuota habis, pesan "tunggu 60 detik"
            // akan menyesatkan karena menunggu 60 detik pun tidak membantu.
            $this->periksaKuota($user, $nomor);
            $this->periksaJeda($user, $nomor);

            $kode = $this->buatKode();

            // Yang disimpan adalah HASH-nya. Kode asli hanya ada di memori dan dikirim ke pengguna.
            $otp = new OtpRequest([
                'phone_number' => $nomor,
                'code_hash' => Hash::make($kode),
                'expires_at' => now()->addMinutes(self::MASA_BERLAKU_MENIT),
            ]);
            $otp->user_id = $user->id; // user_id tidak fillable, diisi oleh kode server
            $otp->save();

            // Kirim terakhir. Jika melempar exception, transaksi dibatalkan dan baris di atas hilang.
            $this->pengirim->kirim($nomor, $kode);

            return $otp;
        });
    }

    // Memeriksa kode yang diketik pengguna. Berhasil: mengembalikan OTP yang sudah ditandai terverifikasi.
    // Gagal: melempar OtpDitolakException dengan alasan yang jelas.
    public function cek(User $user, string $nomorMentah, string $kodeMasukan): OtpRequest
    {
        $nomor = PhoneNumber::normalize($nomorMentah);

        if ($nomor === null) {
            throw OtpDitolakException::nomorTidakValid();
        }

        // PENTING: di dalam transaksi kita hanya MENGEMBALIKAN kegagalan, belum melemparnya.
        // Kalau dilempar di dalam, transaksi dibatalkan dan penambahan "percobaan salah" ikut
        // hilang, sehingga kode bisa ditebak tanpa batas. Lemparan dilakukan SETELAH transaksi selesai.
        $hasil = DB::transaction(function () use ($user, $nomor, $kodeMasukan) {
            // Hanya OTP TERBARU milik user ini untuk nomor ini. Kode lama otomatis tidak berlaku,
            // dan user lain tidak bisa memakai kode milik orang lain. Baris dikunci agar tebakan
            // yang dikirim bersamaan tetap dihitung satu per satu.
            $otp = OtpRequest::query()
                ->where('user_id', $user->id)
                ->where('phone_number', $nomor)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null) {
                return OtpDitolakException::tidakAdaPermintaan();
            }

            if ($otp->verified_at !== null) {
                return $otp; // sudah pernah berhasil: tidak perlu diproses ulang
            }

            if ($otp->expires_at->isPast()) {
                return OtpDitolakException::kedaluwarsa();
            }

            // Percobaan habis ditolak walaupun kode yang diketik kebetulan benar.
            if ($otp->attempts >= self::MAKS_PERCOBAAN) {
                return OtpDitolakException::percobaanHabis();
            }

            // Bandingkan dengan hash. Spasi di pinggir (mis. hasil salin-tempel) dibuang dulu.
            if (! Hash::check(trim($kodeMasukan), $otp->code_hash)) {
                $otp->increment('attempts'); // catat satu percobaan salah (atomik di database)

                return OtpDitolakException::kodeSalah(self::MAKS_PERCOBAAN - $otp->attempts);
            }

            // Kode benar. verified_at tidak fillable, jadi diisi dengan forceFill oleh kode server.
            $otp->forceFill(['verified_at' => now()])->save();

            return $otp;
        });

        if ($hasil instanceof OtpDitolakException) {
            throw $hasil;
        }

        return $hasil;
    }

    // Apakah user ini sudah berhasil memverifikasi nomor tersebut? (Dipakai Tahap 3 sebelum upload KTP.)
    public function sudahTerverifikasi(User $user, string $nomorMentah): bool
    {
        $nomor = PhoneNumber::normalize($nomorMentah);

        return $nomor !== null
            && OtpRequest::query()
            ->where('user_id', $user->id)
            ->where('phone_number', $nomor)
            ->whereNotNull('verified_at')
            ->exists();
    }

    // Nomor terakhir yang sudah lolos OTP milik user ini, atau null jika belum ada.
    // Dipakai halaman /verifikasi untuk tahu langkah mana yang harus ditampilkan.
    public function nomorTerverifikasi(User $user): ?string
    {
        return OtpRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('verified_at')
            ->orderByDesc('id')
            ->value('phone_number');
    }

    // Semua permintaan OTP yang "terkait": dibuat oleh user ini ATAU ditujukan ke nomor ini.
    // Dua sudut pandang sekaligus: membatasi satu akun yang menyalahgunakan layanan, dan melindungi
    // satu nomor dari banjir OTP yang dikirim banyak akun berbeda.
    private function permintaanTerkait(User $user, string $nomor): Builder
    {
        return OtpRequest::query()->where(function (Builder $q) use ($user, $nomor) {
            $q->where('user_id', $user->id)->orWhere('phone_number', $nomor);
        });
    }

    // Maksimal 5 permintaan dalam 24 jam terakhir (jendela bergeser, bukan reset tengah malam,
    // supaya 5 permintaan jam 23.59 dan 5 lagi jam 00.01 tidak lolos).
    private function periksaKuota(User $user, string $nomor): void
    {
        $jumlah = $this->permintaanTerkait($user, $nomor)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($jumlah >= self::MAKS_PERMINTAAN_PER_HARI) {
            throw OtpDitolakException::kuotaHabis();
        }
    }

    // Permintaan berikutnya baru boleh setelah 60 detik sejak permintaan terakhir.
    private function periksaJeda(User $user, string $nomor): void
    {
        $terakhir = $this->permintaanTerkait($user, $nomor)->orderByDesc('id')->first();

        if ($terakhir === null) {
            return; // belum pernah ada permintaan: tidak ada jeda
        }

        // Dihitung dengan angka detik (timestamp) agar tidak ada keraguan tanda plus/minus.
        $sisa = $terakhir->created_at->timestamp + self::JEDA_KIRIM_ULANG_DETIK - now()->timestamp;

        if ($sisa > 0) {
            throw OtpDitolakException::terlaluCepat($sisa);
        }
    }

    // Kode acak 6 digit. random_int() aman secara kriptografi (tidak bisa ditebak),
    // berbeda dengan rand()/mt_rand() yang pola angkanya bisa diprediksi.
    // %06d mengisi nol di depan, jadi 42 menjadi "000042".
    private function buatKode(): string
    {
        return sprintf('%0' . self::PANJANG_KODE . 'd', random_int(0, 10 ** self::PANJANG_KODE - 1));
    }
}
