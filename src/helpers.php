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

//=================== End Api
