# Specification

Aplikasi Sistem Informasi Rumah Sakit memiliki beberapa aktor
Berikut ini adalah fungsionalitas atau fitur yang berada pada aplikasi (sesuaikan dengan kebutuhan aktor):

Aktor : Seseorang yang melakukan control dan memonitor  data rumah sakit
- Add/Update/Delete/View Data Pasien
- Add/Update/Delete/View Data Dokter
- Add/Update/Delete/View Data Obat

Transaksi :
-   Mengelola Rekam Medis Pasien
-   Mengelola Data Pembayaran Pasien
-   Mengelola Resep Obat Pasien
-   Mengubah Status Pembayaran (Lunas / Belum Lunas)
-   Mengelola Data Pembayaran

Laporan :
-   Laporan Data Pasien
-   Laporan Stok Obat
-   Laporan Pembayaran

---

# Roles
- Admin, Apoteker, Dokter, User

## Admin
Bisa CRUD terhadap data Pasien, Dokter dan Obat.

## Dokter
Bisa CRUD terhadap data Pasien dan Obat.

## Apoteker
Bisa CRUD terhadap data Obat.

---

# Database Schema

Table name: users
id            INT, PRIMARY, AUTO INCREMENT
username      VARCHAR(32) UNIQUE
password      VARCHAR(255)
role          ENUM('admin', 'dokter', 'apoteker', 'user'), Default 'user'
name          VARCHAR(64)
email         VARCHAR(64) UNIQUE
phone         VARCHAR(32) UNIQUE
address       TEXT
gender        ENUM('M', 'F')
createdAt     TIMESTAMP
updatedAt     TIMESTAMP
isDeleted     TINYINT(1), Default 0
status        TINYINT(1), Default 0

Table name: patients
id            INT, PRIMARY, AUTO INCREMENT
name          VARCHAR(255)
gender        ENUM('M', 'F')
birthdate     DATE
phone         VARCHAR(32)
email         VARCHAR(64)
address       TEXT
createdAt     TIMESTAMP
updatedAt     TIMESTAMP
isDeleted     TINYINT(1), Default 0
status        TINYINT(1), Default 0

Table name: medical_records
id            INT, PRIMARY, AUTO INCREMENT
patient_id    INT, FOREIGN KEY to patients
user_id       INT, FOREIGN KEY to users (with 'dokter' role)
subjective    TEXT
objective     TEXT
assessment    TEXT
plan          TEXT
visit_date    DATE
createdAt     TIMESTAMP
updatedAt     TIMESTAMP
isDeleted     TINYINT(1), Default 0
status        TINYINT(1), Default 0

Table name: medications
id            INT, PRIMARY, AUTO INCREMENT
name          VARCHAR(64)
stock         INT
price         DECIMAL(10,2)
createdAt     TIMESTAMP
updatedAt     TIMESTAMP
isDeleted     TINYINT(1), Default 0
status        TINYINT(1), Default 0

Table name: prescriptions
id                  INT, PRIMARY, AUTO INCREMENT
medical_record_id   INT, FOREIGN KEY medical_records
medication_id       INT, FOREIGN KEY medications
qty                 INT
dosage              VARCHAR(100)
createdAt           TIMESTAMP
updatedAt           TIMESTAMP
isDeleted           TINYINT(1), Default 0
status              TINYINT(1), Default 0

Table name: bills
id                INT, PRIMARY, AUTO INCREMENT
medical_record_id INT, FOREIGN KEY medical_records
total             DECIMAL(10, 2)
bill_status       ENUM('pending', 'paid'), Default 'pending'
createdAt         TIMESTAMP
updatedAt         TIMESTAMP
isDeleted         TINYINT(1), Default 0
status            TINYINT(1), Default 0

Table name: payments
id            INT, PRIMARY, AUTO INCREMENT
bill_id       INT, FOREIGN KEY bills
amount        DECIMAL(10, 2)
method        VARCHAR(100)
date          DATETIME
createdAt     TIMESTAMP
updatedAt     TIMESTAMP
isDeleted     TINYINT(1), Default 0
status        TINYINT(1), Default 0