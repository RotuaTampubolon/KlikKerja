<?php

namespace App\Http\Controllers\Verification;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckOtpRequest;
use App\Http\Requests\SendOtpRequest;
use App\Services\Otp\OtpDitolakException;
use App\Services\Otp\OtpService;
use Illuminate\Http\RedirectResponse;

// Controller "tipis": hanya menerima request, memanggil OtpService, lalu memilih respons.
// Semua aturan (jeda, kuota, percobaan, hash) ada di OtpService.
class OtpController extends Controller
{
    // Laravel menyuntikkan OtpService otomatis.
    public function __construct(private OtpService $otp)
    {
    }

    // POST /verifikasi/otp/kirim
    public function kirim(SendOtpRequest $request): RedirectResponse
    {
        try {
            $permintaan = $this->otp->kirim($request->user(), $request->validated('nomor'));
        } catch (OtpDitolakException $e) {
            // Penolakan karena aturan (terlalu cepat, kuota habis): tampilkan pesannya, bukan halaman error.
            return redirect()->route('verifikasi.show')
                ->withInput()
                ->withErrors(['otp' => $e->getMessage()]);
        }

        // Simpan nomor baku di session agar halaman menampilkan kolom kode, juga setelah di-refresh.
        $request->session()->put('verifikasi.nomor', $permintaan->phone_number);

        return redirect()->route('verifikasi.show')
            ->with('status', 'Kode verifikasi telah dikirim ke WhatsApp Anda.');
    }

    // POST /verifikasi/otp/cek
    public function cek(CheckOtpRequest $request): RedirectResponse
    {
        try {
            $this->otp->cek($request->user(), $request->validated('nomor'), $request->validated('kode'));
        } catch (OtpDitolakException $e) {
            // Kode salah, kedaluwarsa, atau percobaan habis. Nomor aktif di session tetap dipertahankan
            // agar pengguna bisa mengetik ulang atau menekan "Kirim ulang kode".
            return redirect()->route('verifikasi.show')->withErrors(['otp' => $e->getMessage()]);
        }

        // Berhasil: nomor tidak lagi "menunggu kode".
        $request->session()->forget('verifikasi.nomor');

        return redirect()->route('verifikasi.show')
            ->with('status', 'Nomor WhatsApp berhasil diverifikasi.');
    }
}