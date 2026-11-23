<?php

namespace App\Http\Controllers\Verification;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentsRequest;
use App\Services\Verification\VerificationDitolakException;
use App\Services\Verification\VerificationService;
use Illuminate\Http\RedirectResponse;

// POST /verifikasi/dokumen : pengguna mengunggah KTP dan selfie.
class DocumentUploadController extends Controller
{
    public function store(UploadDocumentsRequest $request, VerificationService $layanan): RedirectResponse
    {
        try {
            // $request->file() mengambil objek file yang sudah lolos validasi.
            $layanan->kirimDokumen($request->user(), $request->file('ktp'), $request->file('selfie'));
        } catch (VerificationDitolakException $e) {
            // Syarat belum terpenuhi (OTP belum selesai, sedang diperiksa, dst): tampilkan pesannya.
            return redirect()->route('verifikasi.show')->withErrors(['dokumen' => $e->getMessage()]);
        }

        return redirect()->route('verifikasi.show')
            ->with('status', 'Dokumen berhasil dikirim dan menunggu pemeriksaan Admin.');
    }
}