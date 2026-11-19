<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SanctionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SanctionReasonRequest;
use App\Http\Requests\SuspendUserRequest;
use App\Models\User;
use App\Models\UserSanction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AdminUserSanctionController extends Controller
{
    public function warn(SanctionReasonRequest $request, User $user): RedirectResponse
    {
        $this->record($user, SanctionType::Warning, $request->validated('reason'));

        return $this->done($user, 'Peringatan dicatat.');
    }

    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        if ($user->currentRestriction() !== null) {
            return $this->refuse($user, 'Akun sudah dibatasi. Pulihkan dulu, atau blokir untuk meningkatkan sanksi.');
        }

        $this->record(
            $user,
            SanctionType::Suspension,
            $request->validated('reason'),
            now()->addDays((int) $request->validated('days')),
        );

        return $this->done($user, 'Akun ditangguhkan.');
    }

    public function ban(SanctionReasonRequest $request, User $user): RedirectResponse
    {
        // Suspend boleh ditingkatkan menjadi blokir, tapi blokir ganda tidak ada gunanya.
        if ($user->currentRestriction()?->type === SanctionType::Ban) {
            return $this->refuse($user, 'Akun sudah diblokir.');
        }

        $this->record($user, SanctionType::Ban, $request->validated('reason'));

        return $this->done($user, 'Akun diblokir.');
    }

    public function restore(SanctionReasonRequest $request, User $user): RedirectResponse
    {
        if ($user->currentRestriction() === null) {
            return $this->refuse($user, 'Akun tidak sedang dibatasi.');
        }

        // Pemulihan = baris BARU. Riwayat sanksi lama tidak diubah atau dihapus.
        $this->record($user, SanctionType::Restoration, $request->validated('reason'));

        return $this->done($user, 'Akun dipulihkan.');
    }

    private function record(User $user, SanctionType $type, string $reason, ?Carbon $expiresAt = null): void
    {
        $sanction = new UserSanction([
            'type' => $type,
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ]);

        // admin_id sengaja tidak fillable (tidak boleh dari input), jadi diisi dari
        // Admin yang sedang login. user_id terisi otomatis oleh relasi saat save().
        $sanction->admin_id = Auth::guard('admin')->id();
        $user->sanctions()->save($sanction);
    }

    private function done(User $user, string $message): RedirectResponse
    {
        return redirect()->route('admin.users.show', $user)->with('status', $message);
    }

    private function refuse(User $user, string $message): RedirectResponse
    {
        return redirect()->route('admin.users.show', $user)->withErrors(['sanction' => $message]);
    }
}