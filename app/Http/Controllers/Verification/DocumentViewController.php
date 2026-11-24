<?php

namespace App\Http\Controllers\Verification;

use App\Http\Controllers\Controller;
use App\Models\Verification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

// GET /verifikasi/dokumen/{verification}/{type}
// Menampilkan foto KTP atau selfie KHUSUS untuk Admin.
//
// Ini SATU-SATUNYA jalan membuka dokumen identitas. File sendiri tidak punya alamat web,
// jadi setiap pembukaan pasti melewati kode ini: dicek siapa pembukanya, dicatat di audit log,
// baru isi file dikirim.
class DocumentViewController extends Controller
{
    // {verification} di route otomatis diubah Laravel menjadi objek Verification (route model binding).
    // Kalau id-nya tidak ada di database, Laravel langsung membalas 404 sebelum method ini berjalan.
    // {type} diterima sebagai teks biasa ('ktp' atau 'selfie').
    // Tipe kembalian StreamedResponse = respons yang isinya dialirkan sedikit demi sedikit dari disk.
    public function show(Verification $verification, string $type): StreamedResponse
    {
        // Lapisan KEDUA. Lapisan pertama adalah middleware auth:admin + role:admin di route.
        // Kalau suatu hari seseorang salah mengubah route sehingga middleware-nya hilang,
        // baris ini masih menahan siapa pun yang bukan Admin.
        // Auth::guard('admin') = "tanya sistem login khusus Admin" (terpisah dari login pengguna biasa).
        $admin = Auth::guard('admin')->user();

        // abort_unless(kondisi, kode): jika kondisi SALAH, hentikan dan balas dengan kode HTTP itu.
        // 403 = Forbidden ("dilarang"). Beda dengan 401/redirect yang berarti "belum login".
        abort_unless($admin !== null, 403);

        // Memilih path file sesuai jenis dokumen yang diminta.
        // match mirip switch, tapi mengembalikan nilai. default menangani jenis yang tidak dikenal.
        // Route sudah membatasi {type} lewat whereIn, jadi default ini hanya jaring pengaman kedua.
        $path = match ($type) {
            'ktp' => $verification->ktp_path,
            'selfie' => $verification->selfie_path,
            default => abort(404),
        };

        // Komentar khusus di bawah memberi tahu EDITOR tipe sebenarnya dari $disk. Isinya tidak
        // memengaruhi cara kerja kode: di saat berjalan, Storage::disk() memang mengembalikan
        // FilesystemAdapter, yang punya method response().
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('private');

        // Barisnya ada di database, tapi filenya mungkin hilang dari harddisk (terhapus manual, dll).
        // Dalam kasus itu balas 404 yang rapi, bukan error 500 yang membocorkan detail teknis.
        abort_unless($disk->exists($path), 404);

        // Mencatat jejak: siapa membuka dokumen apa. PRD bagian 12.1 mewajibkan akses ke dokumen
        // identitas tercatat. Dicatat SETELAH pemeriksaan file ada, supaya log tidak berisi
        // "dibuka" untuk file yang sebenarnya gagal dibuka.
        // Argumen: pelaku, nama tindakan, objek yang dikenai tindakan, keterangan tambahan.
        AuditLogger::log($admin, 'verification.document_viewed', $verification, ['type' => $type]);

        // response() membaca file dari disk privat dan mengalirkannya ke browser.
        // Argumen kedua (null) = pakai nama file apa adanya. Argumen ketiga = header tambahan.
        return $disk->response($path, null, [
            // "private" = hanya boleh disimpan browser pemilik sesi ini, bukan proxy/CDN bersama.
            // "no-store" = JANGAN disimpan sama sekali. Foto KTP tidak boleh tertinggal di cache.
            // "max-age=0" = anggap kedaluwarsa seketika.
            'Cache-Control' => 'private, no-store, max-age=0',

            // Melarang browser "menebak" jenis file. Tanpa ini, file yang menyamar bisa ditafsirkan
            // browser sebagai HTML/skrip dan dijalankan. Dengan nosniff, jenis dari server dipercaya.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}