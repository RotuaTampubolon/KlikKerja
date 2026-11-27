<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectVerificationRequest;
use App\Models\Verification;
use App\Services\Verification\AdminVerificationService;
use App\Services\Verification\NomorWhatsAppSudahDipakaiException;
use App\Services\Verification\VerificationSudahDiputuskanException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Halaman Admin untuk memeriksa pengajuan verifikasi identitas.
// Controller "tipis": aturan keputusan ada di AdminVerificationService.
class VerificationReviewController extends Controller
{
    // Pilihan filter pada halaman antrean: kode di URL => label tab.
    private const FILTER = [
        'pending' => 'Menunggu',
        'verified' => 'Disetujui',
        'rejected' => 'Ditolak',
        'semua' => 'Semua',
    ];

    // GET /admin/verifikasi : daftar antrean.
    public function index(Request $request): View
    {
        // Nilai filter dari URL (?status=...). Nilai asing dikembalikan ke 'pending' supaya
        // pengguna iseng tidak mendapat halaman aneh.
        $status = $request->query('status', 'pending');
        $filter = array_key_exists($status, self::FILTER) ? $status : 'pending';

        // with('user') memuat data pemilik SEKALIGUS dalam satu query tambahan. Tanpa ini, setiap
        // baris tabel memicu satu query sendiri (masalah "N+1"): 15 baris = 16 query.
        $query = Verification::query()->with('user');

        if ($filter !== 'semua') {
            $query->where('status', $filter);
        }

        // Antrean Menunggu: yang PALING LAMA menunggu ada di atas (adil, siapa duluan dilayani duluan).
        // Tab lain: yang terbaru di atas.
        $filter === 'pending'
            ? $query->orderBy('submitted_at')->orderBy('id')
            : $query->orderByDesc('submitted_at')->orderByDesc('id');

        // Jumlah per status untuk label tab. DB::table() memberi data mentah (status tetap teks).
        $perStatus = DB::table('verifications')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $jumlah = [
            'pending' => (int) ($perStatus['pending'] ?? 0),
            'verified' => (int) ($perStatus['verified'] ?? 0),
            'rejected' => (int) ($perStatus['rejected'] ?? 0),
            'semua' => (int) $perStatus->sum(),
        ];

        return view('admin.verifikasi.index', [
            // 15 baris per halaman. withQueryString() menjaga ?status=... saat pindah halaman.
            'pengajuan' => $query->paginate(15)->withQueryString(),
            'filter' => $filter,
            'tab' => self::FILTER,
            'jumlah' => $jumlah,
        ]);
    }

    // GET /admin/verifikasi/{verification} : detail satu pengajuan.
    public function show(Verification $verification): View
    {
        // Memuat pemilik dan Admin pemeriksa (relasi dari Tahap 1) agar tampilan tidak memicu query tambahan.
        $verification->load(['user', 'reviewer']);

        return view('admin.verifikasi.show', [
            'verification' => $verification,
            // Tombol keputusan hanya untuk yang masih menunggu.
            'menunggu' => $verification->status === VerificationStatus::Pending,
        ]);
    }

    // POST /admin/verifikasi/{verification}/setujui
    public function setujui(Request $request, Verification $verification, AdminVerificationService $layanan): RedirectResponse
    {
        try {
            // $request->user('admin') = Admin yang sedang login (guard 'admin').
            $layanan->setujui($request->user('admin'), $verification);
        } catch (VerificationSudahDiputuskanException|NomorWhatsAppSudahDipakaiException $e) {
            // Penolakan yang wajar (sudah diputuskan, nomor dipakai akun lain): tampilkan pesannya.
            return redirect()->route('admin.verifikasi.show', $verification)
                ->withErrors(['keputusan' => $e->getMessage()]);
        }

        // Setelah memutuskan, kembali ke antrean supaya Admin bisa langsung memeriksa pengajuan berikutnya.
        return redirect()->route('admin.verifikasi.index')
            ->with('status', 'Pengajuan disetujui. Akun pengguna kini terverifikasi.');
    }

    // POST /admin/verifikasi/{verification}/tolak
    public function tolak(RejectVerificationRequest $request, Verification $verification, AdminVerificationService $layanan): RedirectResponse
    {
        try {
            $layanan->tolak($request->user('admin'), $verification, $request->validated('alasan'));
        } catch (VerificationSudahDiputuskanException $e) {
            return redirect()->route('admin.verifikasi.show', $verification)
                ->withErrors(['keputusan' => $e->getMessage()]);
        }

        return redirect()->route('admin.verifikasi.index')
            ->with('status', 'Pengajuan ditolak. Pengguna diberi tahu dan dapat mengirim ulang dokumen.');
    }
}