# KlikKerja

Sistem informasi penyalur tenaga kerja informal. Proyek kelompok mata kuliah Pemrograman dan Pengujian Web, Institut Teknologi Del.

## Tim dan Modul
| Modul | Pemilik | Reviewer PR |
|---|---|---|
| 1 Akun, profil, halaman publik, notifikasi | Rotua | Olivia |
| 2 Verifikasi, laporan, admin | Ingrate | Rotua |
| 3 Lowongan | Doy | Ingrate |
| 4 Lamaran dan buka kontak | Olivia | Doy |

Papan tugas: https://trello.com/invite/b/6af961d2680fcaea4c10424c/ATTIe8d675c0cda668b8b77cc43a6cf20eecDA5AD76C/klikkerja

## Setup
1. git clone <url-repo> && cd klikkerja
2. composer install
3. cp .env.example .env
4. php artisan key:generate
5. Buat database kosong bernama klikkerja (MySQL 8)
6. Isi DB_USERNAME dan DB_PASSWORD di .env
7. php artisan migrate --seed
8. php artisan serve

## Aturan Git
- Branch main diproteksi. Tidak ada push langsung.
- Satu fitur satu branch: modul1/google-login, modul3/form-lowongan, dst.
- Semua perubahan lewat Pull Request dan wajib di-approve satu orang lain (lihat tabel reviewer).
- Pesan commit: feat(modul3): tambah filter kategori | fix(modul4): ... | test(modul2): ... | docs: ...
- Perubahan migrasi hanya lewat PR dan diumumkan di grup.

## Aturan Trello
- Satu kartu satu pemilik, wajib ada due date.
- Pindah list sesuai status, tulis satu kalimat setiap pindah.
- Macet lebih dari 2 hari: pindahkan ke Terhambat dan tulis alasannya.