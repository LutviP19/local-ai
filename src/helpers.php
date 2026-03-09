<?php 

//=================== Api
// Function to check what model is currently active in RAM
function getActiveModel($host) {
    $res = @file_get_contents("http://$host:11434/api/ps");
    $data = json_decode($res, true);
    // Mengambil nama model pertama yang aktif (jika ada)
    return $data['models'][0]['name'] ?? null;
}

// Function to sort models based on the list of allowedModels
function filteredModels($models, $allowedModels) {
    // 1. Filter existing models on the server based on the allowedModels list.
    $filteredModels = [];
    foreach ($models as $m) {
        $modelName = explode(':', $m['name'])[0];
        if (in_array($modelName, $allowedModels)) {
            $filteredModels[] = $m;
        }
    }

    // 2. Sort by index position in $allowedModels array
    usort($filteredModels, function($a, $b) use ($allowedModels) {
        $posA = array_search(explode(':', $a['name'])[0], $allowedModels);
        $posB = array_search(explode(':', $b['name'])[0], $allowedModels);
        return $posA - $posB;
    });

    return $filteredModels;
}

// Function to unload certain models from RAM
function unloadModel($host, $modelName) {
    $ch = curl_init("http://$host:11434/api/generate");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "model" => $modelName,
        "keep_alive" => 0,
        "stream" => false
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_exec($ch);
    curl_close($ch);
}

// automatic sentence correction before entering FTS Index
function refineContent($text) {
    $jsonFile = 'dictionary.json';
    $dictionary = [];

    // Check if the file exists, if it does load its contents
    if (file_exists($jsonFile)) {
        $jsonContent = file_get_contents($jsonFile);
        $dictionary = json_decode($jsonContent, true) ?? [];
    }

   // If the file fails to load or is empty, use the minimal default to avoid errors.
    if (empty($dictionary)) {
        $dictionary = [
            ' nggak ' => ' tidak ',
            ' gak '    => ' tidak ',
            ' krn '    => ' karena ',
            ' yg '     => ' yang ',
            ' bgt '    => ' sangat ',
            ' dngn '   => ' dengan ',
            ' sbg '    => ' sebagai ',
            ' kpd '    => ' kepada ',
            ' tdk '    => ' tidak ',
            ' sdh '    => ' sudah ',
            ' blm '    => ' belum '
        ];
    }

    // Use Regex Boundary (\b) to only replace complete words
    // This prevents the word 'banget' from changing to 'basangatet' because of the 'bgt'.
    foreach ($dictionary as $slang => $formal) {
        // \b adalah word boundary, /i adalah case-insensitive
        $pattern = '/\b' . preg_quote($slang, '/') . '\b/i';
        $text = preg_replace($pattern, $formal, $text);
    }

    return !empty($text) ? ucfirst($text) : $text;
}

function createFtsDb($db_file) {
    try {
        // Setup FTSs
        $db = new PDO('sqlite:'.$db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("PRAGMA journal_mode = WAL;");

        // 1. Tabel Master (Data Utama)
        $db->exec("CREATE TABLE IF NOT EXISTS kearifan_lokal (
            id INTEGER PRIMARY KEY, 
            content TEXT, 
            tags TEXT,
            vector BLOB,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 2. Tabel Virtual FTS5 (Untuk Pencarian Cepat)
        // Kita sertakan 'tags' agar bisa dicari juga via FTS
        $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS kearifan_lokal_fts USING fts5(
            content, 
            tags, 
            content='kearifan_lokal', 
            content_rowid='id'
        )");
    } catch (Exception $e) {
        $error = $e->getMessage();

        // Buat folder 'logs' jika belum ada
        if (!file_exists('logs')) {
            mkdir('logs', 0755, true);
        }
        if (!file_exists('logs/'.str_replace(" ", "_", $modelName))) {
            mkdir('logs/errors', 0755, true);
        }
        $logPath = BASEPATH . '/logs/errors';

        $timestamp = date('Y-m-d H:i:s');
        $fileName  = $logPath.'/error-db-' . date('Y-m-d') . '.log';

        $content = "==========================================START\n";
        $content .= "WAKTU  : $timestamp\n";
        $content .= "------------------------------------------\n";
        $content .= "ERROR  :\n$error\n";
        $content .= "==========================================END\n\n";

        // Simpan dengan mode APPEND (menambah ke baris bawah, tidak menimpa)
        if (file_put_contents($fileName, $content, FILE_APPEND)) {
            echo json_encode(['status' => 'success', 'file' => $fileName]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menulis file']);
        }
    }
}
//=================== End Api


//=================== Api-Debug
function refineSentence($rawText, $ollama) {
    $prompt = "Tugas Anda adalah memperbaiki ejaan, tanda baca, dan tata bahasa teks berikut agar menjadi kalimat formal yang bersih untuk database. Jangan tambahkan komentar, berikan hasilnya saja.\n\nTeks: $rawText";
    
    // Call Ollama (using exec)
    $refined = $ollama->ask('gemma3:1b', $prompt);
    return trim($refined);
}

function highlight($text, $query) {
    if (empty($query)) return htmlspecialchars($text);
    
    // Clean up the query and break it down into words for word by word highlighting.
    $cleanQuery = preg_replace('/[^A-Za-z0-9 ]/', '', $query);
    $words = explode(' ', trim($cleanQuery));
    
    $highlighted = htmlspecialchars($text);
    foreach ($words as $word) {
        if (strlen($word) < 2) continue; // Avoid highlighting single letters
        $pattern = "/" . preg_quote($word, '/') . "/i";
        $highlighted = preg_replace($pattern, '<mark class="bg-yellow-500/30 text-yellow-200 px-0.5 rounded">$0</mark>', $highlighted);
    }
    return $highlighted;
}
//=================== Api-Debug Index