<?php

namespace App\Enums;

// Status sebuah pekerjaan di antrean Admin.
enum AdminTaskStatus: string
{
    case Open = 'open';              // baru masuk, belum disentuh
    case InProgress = 'in_progress'; // sedang ditangani Admin
    case Done = 'done';              // selesai ditangani
}