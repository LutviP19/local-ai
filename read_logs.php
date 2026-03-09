<?php 

date_default_timezone_set('Asia/Jakarta');

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}


$start = microtime(true);

/**
 *Reads large logs line by line in String Format to save RAM
 *@param string $filePath Path to the log file
 *@return Generator
 */
function streamLogReader($filePath) {
    $handle = fopen($filePath, "r");
    if (!$handle) return;

    $currentBlock = [];
    $isParsingHasil = false;

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);

        if (strpos($line, "==========================================START") !== false) {
            $currentBlock = []; // Reset blok baru
            $isParsingHasil = false;
        } elseif (strpos($line, "WAKTU  :") !== false) {
            $currentBlock['waktu'] = trim(str_replace("WAKTU  :", "", $line));
        } elseif (strpos($line, "DURASI :") !== false) {
            $currentBlock['durasi'] = trim(str_replace("DURASI :", "", $line));
        } elseif (strpos($line, "MODEL  :") !== false) {
            $currentBlock['model'] = trim(str_replace("MODEL  :", "", $line));
        } elseif (strpos($line, "PROMPT :") !== false) {
            $currentBlock['prompt'] = trim(str_replace("PROMPT :", "", $line));
        } elseif (strpos($line, "HASIL  :") !== false) {
            $isParsingHasil = true;
            $currentBlock['hasil'] = "";
        } elseif (strpos($line, "==========================================END") !== false) {
            yield $currentBlock; // Kirim satu blok log ke pemanggil
            $currentBlock = [];
            $isParsingHasil = false;
        } elseif ($isParsingHasil && !empty($line)) {
            $currentBlock['hasil'] .= $line . " ";
        }
    }

    fclose($handle);
}

/**
 *Read large AI logs with RAM saving (Stream Mode)
 *@param string $filePath Path to the log file
 *@yield array Returns a generator containing one log block
 */
function streamAiLog($filePath) {
    if (!file_exists($filePath)) return;

    $handle = fopen($filePath, "r");
    $currentBlock = [];
    $isCapturingHasil = false;
    $hasilContent = "";

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);

        // 1. Deteksi Awal Blok
        if (str_contains($line, "==========================================START")) {
            $currentBlock = [];
            $hasilContent = "";
            $isCapturingHasil = false;
            continue;
        }

        // 2. Ekstraksi Metadata (Waktu, Durasi, Model, Prompt)
        if (str_starts_with($line, "WAKTU")) {
            $currentBlock['waktu'] = trim(explode(":", $line, 2)[1]);
        } elseif (str_starts_with($line, "DURASI")) {
            $currentBlock['durasi'] = trim(explode(":", $line, 2)[1]);
        } elseif (str_starts_with($line, "MODEL")) {
            $currentBlock['model'] = trim(explode(":", $line, 2)[1]);
        } elseif (str_starts_with($line, "PROMPT")) {
            $currentBlock['prompt'] = trim(explode(":", $line, 2)[1]);
        } 
        
        // 3. Deteksi Mulai Bagian HASIL
        elseif (str_starts_with($line, "HASIL")) {
            $isCapturingHasil = true;
            $hasilContent = trim(explode(":", $line, 2)[1] ?? "");
        } 
        
        // 4. Deteksi Akhir Blok
        elseif (str_contains($line, "==========================================END")) {
            $currentBlock['hasil'] = trim($hasilContent);
            // yield (Generator): PHP tidak akan membuat array raksasa di RAM. 
            // Ia hanya memproses satu blok log, lalu menghapusnya untuk blok berikutnya.
            yield $currentBlock; // Kirim satu data ke perulangan luar
        } 
        
        // 5. Kumpulkan isi HASIL (Multi-line)
        elseif ($isCapturingHasil && !str_starts_with($line, "---")) {
            $hasilContent .= "\n" . $line;
        }
    }

    fclose($handle);
}



$logFile = BASEPATH . '/logs/default-chat/chat-history-2026-03-09.log';

// //---HOW TO USE (Line -String) ---
// $jsonArr = [];
// foreach (streamLogReader($logFile) as $logEntry) {
//     // //Process one by one, no need to store in a large array
//     // echo "Prompt: " . $logEntry['prompt'] . "\n";

//     // If you need JSON:  
//     $jsonArr[] = $logEntry;
// }

// echo json_encode($jsonArr) . "\n";


// // ---HOW TO USE (Array) ---
// // Output as JSON
// // header('Content-Type: application/json');
// echo "[\n"; //Start the JSON Array format
// $first = true;

// foreach (streamAiLog($logFile) as $data) {
//     if (!$first) echo ",\n";
//     echo json_encode($data, JSON_PRETTY_PRINT);
//     $first = false;
    
//     // //Optional: Flush output so the browser doesn't hang if the data is thousands
//     // flush();
// }

// echo "\n]";



//1. Open the target file to write to (Write mode)
$outputJson = '/logs/history_converted.json';
$fileHandle = fopen(BASEPATH . $outputJson, 'w');

if ($fileHandle) {
    //2. Write a JSON array opener
    fwrite($fileHandle, "[\n");
    
    $first = true;
    $count = 0;

    //3. Iterate log using generator (Save RAM)
    foreach (streamAiLog($logFile) as $data) {
        //Add a comma if it is not the first data
        if (!$first) {
            fwrite($fileHandle, ",\n");
        }
        
        //4. Write a block of data to the file
        fwrite($fileHandle, json_encode($data, JSON_PRETTY_PRINT));
        
        $first = false;
        $count++;
    }

    //5. Write the JSON array cover
    fwrite($fileHandle, "\n]");
    fclose($fileHandle);

    echo "✅ Berhasil! $count data log telah dikonversi ke $outputJson\n";
} else {
    echo "❌ Gagal membuka file untuk ditulisi.\n";
}

//Function execution timestamp
echo "\n================================\n";
$execution_time = $diff = microtime(true) - $start;

//If more than 1 second
if($diff >= 1) {
    if ($diff < 60) {
        $duration = round($diff, 0) . "s";
    } else {
        $minutes = floor($diff / 60);
        $seconds = round($diff % 60);
        $duration = "{$minutes}m {$seconds}s";
    }
    echo "\n================================\n";
    echo "Waktu eksekusi: " . $duration;
    echo "\n";
} else {
    echo "Waktu eksekusi: " . number_format($execution_time, 5) . " detik\n";
}
