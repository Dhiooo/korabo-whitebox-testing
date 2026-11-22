# Korabo - Platform Kolaborasi Tugas Mahasiswa 🍂☕️

**Korabo** adalah platform manajemen tugas kelompok mahasiswa berbasis web yang menggunakan pendekatan **Linear-Modern Kanban**. Sistem ini dirancang untuk meningkatkan transparansi, kedisiplinan, dan akuntabilitas dalam pengerjaan proyek kolaboratif di lingkungan akademik.

---

## Runtime resmi untuk praktikum

Untuk pengujian lokal, gunakan **Laragon (Apache + MySQL)**. Backend resmi
praktikum adalah folder `php-backend`.

Folder `backend` berisi implementasi Node.js lama dan tidak digunakan pada
setup Laragon-only. Gunakan hanya Apache + MySQL Laragon dan folder
`php-backend`.

### ** Akun Percobaan (Untuk Dosen/Penguji)**
Gunakan akun berikut untuk mencoba fitur kolaborasi tanpa perlu mendaftar:
PERLU DIINGAT: Bahwa website ini yang menjadi admin adalah pengguna yang membuat grup itu PERTAMA KALI

| Role | NIM | Password |
| :--- | :--- | :--- |
| **User 1 (Anggota)** | `20230001` | `dhio` |
| **User 2 (Anggota)** | `20230002` | `student1` |
| **User 3 (Anggota)** | `20230003` | `student2` |

---

## Fitur Utama
*   **Modern Kanban Workspace**: Pengorganisasian tugas per anggota kelompok dengan sistem accordion yang rapi.
*   **Strict Admin Control**: Hanya admin yang memiliki otoritas penuh untuk manajemen tugas dan grup.
*   **Real-time Activity Log**: Pencatatan setiap aksi dalam grup secara otomatis dan real-time.
*   **Punctuality Metrics**: Laporan kinerja otomatis berdasarkan ketepatan waktu penyelesaian tugas terhadap deadline.
*   **PDF Proof System**: Kewajiban unggah bukti pengerjaan berupa file PDF untuk transparansi hasil kerja.
*   **Earthy Autumn Design**: Antarmuka estetik dengan gaya Glassmorphism yang responsif.

## Tech Stack
*   **Frontend**: Vite, Vanilla JavaScript, CSS3 (Modern UI).
*   **Backend**: PHP melalui Apache Laragon.
*   **Database**: MySQL.
*   **Storage**: Online/Local File Storage (for PDF proofs).

## 📋 Daftar Fitur & Status Fungsionalitas
Berikut adalah daftar fitur utama yang telah diimplementasikan dan hasil pengujian fungsionalitasnya:

| Fitur | Deskripsi | Status |
| :--- | :--- | :--- |
| **Autentikasi** | Login, Register, dan Forgot Password menggunakan NIM/email. | ✅ PASS |
| **Workspace Management** | Membuat grup baru (Admin) atau bergabung ke grup via kode unik. | ✅ PASS |
| **Kanban Board** | Workflow tugas: Not Started, In Progress, dan Done. | ✅ PASS |
| **Evidence Upload** | Fitur unggah bukti pengerjaan berupa file PDF pada tugas yang selesai. | ✅ PASS |
| **Activity Logging** | Pencatatan otomatis setiap aksi user (log) untuk transparansi tim. | ✅ PASS |
| **Performance Report** | Laporan statistik kinerja anggota berdasarkan ketepatan waktu (deadline). | ✅ PASS |

## Dokumentasi QA

Role utama yang diuji adalah **Anggota kelompok**. Requirement awal, acceptance
criteria, aturan validasi, dan out-of-scope tersedia di
[`requirements.md`](requirements.md).

## Menjalankan dengan Laragon

1. Salin folder project ke `C:\laragon\www\korabo-task-platform-main`.
2. Jalankan **Apache** dan **MySQL** dari Laragon.
3. Buat database `korabu_db` di HeidiSQL/phpMyAdmin, lalu import `database.sql`.
4. Pastikan konfigurasi lokal berada di `php-backend/config.php`.
5. Buka:

```text
http://localhost/korabo-task-platform-main/frontend/
```

API PHP tersedia di:

```text
http://localhost/korabo-task-platform-main/php-backend/
```
