<?php
// debug.php

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/..');
}


header('Content-Type: text/html; charset=utf-8');
$db_file = BASEPATH . '/src/vector_store.db';

echo "<body style='background:#0f172a; color:#cbd5e1; font-family:sans-serif; padding:20px;'>";
echo "<h1 style='color:#10b981;'>Memory Debugger</h1>";

if (!file_exists($db_file)) {
    die("<p style='color:#ef4444;'>Database belum dibuat! Silakan Inject Knowledge dulu.</p>");
}

try {
    $db = new PDO("sqlite:$db_file");
    
    // 1. Cek Tabel FTS5
    echo "<h3>1. Isi Tabel Memori (FTS5):</h3>";
    $stmt = $db->query("SELECT rowid, content FROM kearifan_lokal_fts");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "<p style='color:#f59e0b;'>Tabel kosong. Belum ada data yang di-inject.</p>";
    } else {
        echo "<table border='1' style='border-collapse:collapse; width:100%; border-color:#334155;'>";
        echo "<tr style='background:#1e293b;'><th>ID</th><th>Content</th></tr>";
        foreach ($rows as $row) {
            echo "<tr><td style='padding:8px;'>{$row['rowid']}</td><td style='padding:8px;'>{$row['content']}</td></tr>";
        }
        echo "</table>";
    }

    // 2. Simulasi Search
    echo "<h3>2. Test Pencarian:</h3>";
    $test_keyword = $_GET['q'] ?? '';
    echo "<form method='GET'><input type='text' name='q' value='$test_keyword' autocomplete='off' placeholder='Cari kata kunci...'> <button type='submit'>Test Search</button></form>";

    if ($test_keyword) {
        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $test_keyword);
        $searchParam = trim($clean) . '*';
        $stmt = $db->prepare("SELECT content FROM kearifan_lokal_fts WHERE content MATCH ?");
        $stmt->execute([$searchParam]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "<h4>Hasil Pencarian untuk '$test_keyword':</h4>";
        if ($results) {
            foreach ($results as $res) echo "<div style='background:#064e3b; padding:10px; margin-bottom:5px; border-radius:5px;'>✅ $res</div>";
        } else {
            echo "<div style='background:#450a0a; padding:10px; border-radius:5px;'>❌ Tidak ditemukan di database.</div>";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</body>";