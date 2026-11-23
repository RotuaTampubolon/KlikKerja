<?php

namespace App\Services;

use App\Enums\AdminTaskStatus;
use App\Enums\AdminTaskType;
use App\Models\AdminTask;

// Pintu tunggal untuk memasukkan pekerjaan ke antrean Admin. Dipakai modul verifikasi, laporan, dan tiket:
//   AdminTaskQueue::push(AdminTaskType::Report, 'report', $laporan->id);
class AdminTaskQueue
{
    // $type boleh berupa enum atau teksnya ('verification'/'report'/'ticket'). Teks yang salah ketik
    // langsung ditolak (ValueError), tidak diam-diam masuk ke antrean.
    public static function push(AdminTaskType|string $type, string $refType, int $refId): AdminTask
    {
        $type = $type instanceof AdminTaskType ? $type : AdminTaskType::from($type);

        // Jangan menumpuk: kalau pekerjaan yang sama masih belum selesai, pakai yang sudah ada.
        $sudahAda = AdminTask::query()
            ->where('type', $type->value)
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->where('status', '!=', AdminTaskStatus::Done->value)
            ->first();

        if ($sudahAda !== null) {
            return $sudahAda;
        }

        $task = new AdminTask(['type' => $type, 'ref_type' => $refType, 'ref_id' => $refId]);
        $task->save(); // status otomatis 'open'

        return $task;
    }
}