<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

// Pintu tunggal untuk mencatat jejak aktivitas. Dipakai semua modul:
//   AuditLogger::log($admin, 'verification.approved', $verification, ['catatan' => '...']);
class AuditLogger
{
    // $actor   : User atau Admin yang bertindak (null = tindakan oleh sistem).
    // $action  : ditulis konsisten "objek.aksi", mis. verification.submitted.
    // $subject : data yang dikenai tindakan (mis. Verification), boleh null.
    // $meta    : keterangan tambahan berupa array (disimpan sebagai JSON).
    public static function log(?Model $actor, string $action, ?Model $subject = null, array $meta = []): AuditLog
    {
        $log = new AuditLog([
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
            'ip_address' => request()->ip(), // null jika dijalankan dari terminal/tinker
        ]);

        // Pelaku dan objek dipasang lewat relasi polimorfik (kolom *_type dan *_id terisi otomatis).
        if ($actor !== null) {
            $log->actor()->associate($actor);
        }

        if ($subject !== null) {
            $log->subject()->associate($subject);
        }

        $log->save();

        return $log;
    }
}