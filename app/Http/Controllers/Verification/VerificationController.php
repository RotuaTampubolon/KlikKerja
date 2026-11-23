<?php

namespace App\Http\Controllers\Verification;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Services\Otp\OtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Halaman verifikasi identitas. Tahap 3 akan menambahkan unggah dokumen di controller ini.
class VerificationController extends Controller
{
    public function show(Request $request, OtpService $otp): View|RedirectResponse
    {
        $user = $request->user();

        // Yang sudah terverifikasi tidak punya urusan di sini.
        if ($user->verification_status === VerificationStatus::Verified) {
            return redirect('/');
        }

        // Tautan "Gunakan nomor lain": lupakan nomor yang sedang menunggu kode.
        if ($request->boolean('ganti')) {
            $request->session()->forget('verifikasi.nomor');
        }

        return view('verifikasi.show', [
            // Dokumen sudah dikirim dan menunggu Admin (diisi Tahap 3): form OTP tidak ditampilkan.
            'sedangDiperiksa' => $user->verification_status === VerificationStatus::Pending,
            // Nomor yang kodenya sedang ditunggu (disimpan di session saat kode dikirim).
            'nomorAktif' => $request->session()->get('verifikasi.nomor'),
            // Nomor yang SUDAH lolos OTP, atau null.
            'nomorTerverifikasi' => $otp->nomorTerverifikasi($user),
        ]);
    }
}