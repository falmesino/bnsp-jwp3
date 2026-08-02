CREATE DATABASE bnsp_jwp3;
USE bnsp_jwp3;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'dokter', 'apoteker', 'user') NOT NULL DEFAULT 'user',
    name VARCHAR(64) NOT NULL,
    email VARCHAR(64) UNIQUE,
    phone VARCHAR(32) UNIQUE,
    address TEXT,
    gender ENUM('M', 'F'),

    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    gender ENUM('M', 'F'),
    birthdate DATE,
    phone VARCHAR(32),
    email VARCHAR(64),
    address TEXT,

    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    user_id INT NOT NULL,

    subjective TEXT,
    objective TEXT,
    assessment TEXT,
    plan TEXT,
    visit_date DATE,

    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 0,

    CONSTRAINT fk_medical_record_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id),

    CONSTRAINT fk_medical_record_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
);

CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medical_record_id INT NOT NULL,
    medication_id INT NOT NULL,

    qty INT NOT NULL,
    dosage VARCHAR(100),

    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 0,

    CONSTRAINT fk_prescription_record
        FOREIGN KEY (medical_record_id)
        REFERENCES medical_records(id),

    CONSTRAINT fk_prescription_medication
        FOREIGN KEY (medication_id)
        REFERENCES medications(id)
);

CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medical_record_id INT NOT NULL,

    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    bill_status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',

    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 0,

    CONSTRAINT fk_bill_record
        FOREIGN KEY (medical_record_id)
        REFERENCES medical_records(id)
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,

    amount DECIMAL(10,2) NOT NULL,
    method VARCHAR(100),
    date DATETIME,

    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 0,

    CONSTRAINT fk_payment_bill
        FOREIGN KEY (bill_id)
        REFERENCES bills(id)
);