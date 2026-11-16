<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id tidak fillable: diisi otomatis lewat relasi $user->employerProfile()->create(...).
// region_id fillable karena berasal dari dropdown form; keabsahannya (apakah wilayah itu
// benar-benar ada) akan dicek di Form Request, bukan di sini.
#[Fillable(['account_type', 'display_name', 'region_id', 'additional_info'])]
class EmployerProfile extends Model
{
    protected function casts(): array
    {
        return [
            // String 'individual'/'business' di database -> objek enum AccountType.
            'account_type' => AccountType::class,
        ];
    }

    // Profil ini milik satu user (sisi yang memegang foreign key).
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}