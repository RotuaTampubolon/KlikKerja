<?php

namespace Tests\Feature;

use App\Enums\AdminTaskStatus;
use App\Enums\AdminTaskType;
use App\Models\Admin;
use App\Models\AdminTask;
use App\Models\User;
use App\Models\Verification;
use App\Services\AdminTaskQueue;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ValueError;

// Test dua service bersama (AuditLogger dan AdminTaskQueue) yang dipakai semua anggota tim.
class SharedServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_mencatat_pelaku_objek_dan_meta(): void
    {
        $admin = Admin::create(['name' => 'Admin Uji', 'email' => 'admin@example.test', 'password' => 'rahasia123']);
        $user = User::factory()->seeker()->create();
        $v = new Verification(['phone_number' => '6281234567890', 'ktp_path' => 'ktp/a.jpg', 'selfie_path' => 'selfie/a.jpg']);
        $v->user_id = $user->id;
        $v->save();

        $log = AuditLogger::log($admin, 'verification.approved', $v, ['catatan' => 'KTP jelas']);

        $log = $log->fresh();
        $this->assertSame('verification.approved', $log->action);
        $this->assertTrue($log->actor->is($admin));
        $this->assertTrue($log->subject->is($v));
        $this->assertSame(['catatan' => 'KTP jelas'], $log->meta);
    }

    public function test_audit_logger_boleh_tanpa_pelaku_dan_objek(): void
    {
        $log = AuditLogger::log(null, 'system.cleanup')->fresh();

        $this->assertNull($log->actor);
        $this->assertNull($log->subject);
        $this->assertNull($log->meta);
    }

    public function test_antrean_menerima_enum_dan_tugas_baru_berstatus_open(): void
    {
        $task = AdminTaskQueue::push(AdminTaskType::Verification, 'verification', 12)->fresh();

        $this->assertSame(AdminTaskType::Verification, $task->type);
        $this->assertSame('verification', $task->ref_type);
        $this->assertSame(12, $task->ref_id);
        $this->assertSame(AdminTaskStatus::Open, $task->status);
    }

    public function test_antrean_menerima_jenis_berupa_teks(): void
    {
        $task = AdminTaskQueue::push('report', 'report', 5)->fresh();

        $this->assertSame(AdminTaskType::Report, $task->type);
    }

    public function test_jenis_yang_salah_ketik_ditolak(): void
    {
        $this->expectException(ValueError::class);

        AdminTaskQueue::push('verifikasi', 'verification', 1); // seharusnya 'verification'
    }

    public function test_pekerjaan_yang_sama_tidak_menumpuk_kecuali_yang_lama_sudah_selesai(): void
    {
        $pertama = AdminTaskQueue::push(AdminTaskType::Ticket, 'ticket', 3);
        $kedua = AdminTaskQueue::push(AdminTaskType::Ticket, 'ticket', 3);

        $this->assertSame($pertama->id, $kedua->id);                              // dipakai ulang
        $this->assertSame(1, AdminTask::count());

        // Setelah selesai ditangani, pekerjaan baru untuk rujukan yang sama boleh masuk lagi.
        $pertama->status = AdminTaskStatus::Done;
        $pertama->save();
        $ketiga = AdminTaskQueue::push(AdminTaskType::Ticket, 'ticket', 3);

        $this->assertNotSame($pertama->id, $ketiga->id);
        $this->assertSame(2, AdminTask::count());
    }
}