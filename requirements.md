# Initial Requirements — Korabo v0.1

## Role yang diuji

**Anggota kelompok mahasiswa.** Akun Admin hanya digunakan untuk menyiapkan
precondition data uji dan bukan role utama yang dievaluasi.

## Scope fitur

1. Login.
2. Register.
3. Forgot Password.
4. Join Group menggunakan kode enam karakter.
5. Melihat workspace dan task yang ditugaskan.
6. Menjalankan workflow task: Not Started -> In Progress -> Done.
7. Mengunggah PDF proof untuk menyelesaikan task.
8. Melihat activity log.
9. Melihat performance report.

## Requirement dan acceptance criteria

| ID | Requirement | Acceptance criteria |
|---|---|---|
| REQ-AUTH-01 | Anggota dapat login menggunakan NIM dan password yang benar. | Token dibuat dan dashboard tampil. |
| REQ-AUTH-02 | Pengguna dapat mendaftar dengan NIM dan email unik. | Akun tersimpan; data duplikat ditolak. |
| REQ-AUTH-03 | Pengguna dapat mengganti password menggunakan token reset. | Token berlaku 15 menit, hanya sekali pakai, dan password baru dapat dipakai untuk login. |
| REQ-GRP-01 | Anggota dapat bergabung menggunakan group code valid. | Kode tepat enam karakter alfanumerik diterima; kode lain ditolak. |
| REQ-TASK-01 | Anggota dapat melihat task pada grup yang diikuti. | Task grup tampil di workspace. |
| REQ-TASK-02 | Task berpindah melalui status yang valid. | Hanya status 0, 1, dan 2 yang diterima. |
| REQ-TASK-03 | Task Done wajib memiliki PDF proof. | Tanpa PDF ditolak; PDF valid diterima. |
| REQ-LOG-01 | Perubahan task tercatat pada activity log. | Log menyimpan actor, pesan, dan waktu. |
| REQ-REPORT-01 | Report menampilkan jumlah task selesai dan punctuality. | Perhitungan ditampilkan untuk setiap anggota. |

## Aturan validasi untuk BVA/EP

- Nama: 2–100 karakter.
- NIM: 5–20 digit.
- Email: format email valid.
- Password: 4–72 karakter.
- Nama grup: 3–100 karakter.
- Group code: tepat 6 karakter alfanumerik.
- Judul task: 3–150 karakter.
- Status task: hanya `0`, `1`, atau `2`.

## Out of scope

Pembuatan/penghapusan grup, assign/reassign task, dan penghapusan activity log
adalah fitur Admin. Fitur tersebut tidak menjadi fokus role yang diuji.

## Environment resmi untuk praktikum

- Backend: PHP melalui Apache Laragon.
- Frontend: file statis yang disajikan Apache Laragon.
- Database: MySQL, database `korabu_db`.
- API: folder `php-backend`.
