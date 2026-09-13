<?php

$hostname = 'localhost';
$username = 'root';
$password = '';
$dbname   = 'db_marketplace';

// Membuat koneksi ke database
$conn = mysqli_connect($hostname, $username, $password, $dbname);

// Memeriksa status koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// Menyetel character set ke utf8mb4
mysqli_set_charset($conn, "utf8mb4");
