<?php 
require_once 'bootstrap.php';

$action = $_GET['action'] ?? '';
$host = 'localhost'; // Customize if in Docker
$defaultModel = 'default-chat'; // default-chat
$db_file = BASEPATH . '/src/vector_store.db'; // Nama database

// List of custom models allowed to appear in the dropdown
$allowedModels = ['default-chat', 'asisten-riset', 'agen-koding'];


if ($action === 'status') {
    $ollama = new OllamaExec();
    
    // 1. Get the model that is actually active in RAM (Real-time)
    $activeInRam = $ollama->getActiveModel();
    
    // 2. Get the model selected by the user on the frontend via the GET parameter
    // current_ui_model sent by hx-vals
    $uiModel = $_GET['current_ui_model'] ?? $defaultModel;

    // Display Determination Logic
    $isLoaded = ($activeInRam !== "Standby");
    
    // If a model exists in RAM, display it. Otherwise, display the user's choice in the UI.
    $displayName = $isLoaded ? $activeInRam : $uiModel;
    $statusText = $isLoaded ? 'Active' : 'Standby';
    $badgeColor = $isLoaded ? 'emerald' : 'blue';

    // Change text to SVG Icon
    $statusIcon = $isLoaded 
        ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Active in RAM"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>' 
        : '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Standby"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>';

    // Just take the model name
    $displayName = explode(":", $displayName)[0];

    // The output to be put into the #status-container
    echo '
    <div x-show="!isOffline" class="flex items-center gap-2">
        <div class="flex flex-col items-end">
            <div class="flex items-center gap-1.5 px-2 py-0.5 bg-'.$badgeColor.'-500/10 text-'.$badgeColor.'-400 rounded border border-'.$badgeColor.'-500/30">
                <span class="text-[10px] font-bold uppercase tracking-tighter">' . htmlspecialchars($displayName) . '</span>
                <span class="border-l border-'.$badgeColor.'-500/30 pl-1.5 ml-0.5">' . $statusIcon . '</span>
            </div>
        </div>
        
        <span class="px-3 py-1 bg-slate-800/50 text-emerald-400 rounded-full text-xs border border-slate-700 flex items-center gap-2">
            <span class="relative flex h-2 w-2">
                <span class="' . ($isLoaded ? 'animate-ping' : '') . ' absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            Online
        </span>
    </div>';
    exit;
}

if ($action === 'models') {
    // Logic for retrieving a list of models from the Ollama API
    $res = file_get_contents("http://$host:11434/api/tags");
    $models = json_decode($res, true)['models'] ?? [];

    // Filter existing models on the server based on the allowedModels list.
    $filteredModels = filteredModels($models, $allowedModels);

    foreach ($filteredModels as $m) {

        // Take the model name only (remove the :latest tag if any)
        $modelName = explode(':', $m['name'])[0];
        // Check if the model is on the allowed list
        if (in_array($modelName, $allowedModels)) {
            echo "<li class='p-3 border-b border-slate-700 flex justify-between items-center hover:bg-slate-700/50 transition'>
                <span class='font-mono text-slate-200'>{$m['name']}</span>
                <span class='text-xs text-slate-400'>".round($m['size'] / (1024 ** 3), 2)." GB</span>
              </li>";
        }
    }
    exit;
}

// --- Action: Dropdown Models ---
if ($action === 'model_options') {
    $res = @file_get_contents("http://$host:11434/api/tags");
    $data = json_decode($res, true);
    $models = $data['models'] ?? [];

    // Filter existing models on the server based on the allowedModels list.
    $filteredModels = filteredModels($models, $allowedModels);

    // Save the output to a variable so we can check if there is a suitable model.
    $optionsHtml = "";

    if (!empty($filteredModels)) {
        foreach ($filteredModels as $m) {
            // Take the model name only (remove the :latest tag if any)
            $modelName = explode(':', $m['name'])[0];

            // Check if the model is on the allowed list
            if (in_array($modelName, $allowedModels)) {
                // Automatically assign 'selected' attribute if the model is a chat-assistant
                $selected = ($modelName === $defaultModel) ? 'selected' : '';
                
                $optionsHtml .= "<option value='{$m['name']}' {$selected}>{$m['name']}</option>";
            }
        }
    }

    // If after filtering it turns out to be empty or there are no installed models
    if (empty($optionsHtml)) {
        echo "<option disabled>Model khusus belum tersedia</option>";
    } else {
        echo $optionsHtml;
    }
    exit;
}

// --- Action: Ask (AI Integration) ---
if ($action === 'ask') {
    $prompt = $_POST['q'] ?? '';
    $selectedModel = $_POST['model'] ?? $defaultModel; // Take model from parameters, default model
    
    if (empty($prompt)) {
        echo "Error: Prompt tidak boleh kosong.";
        exit;
    }

    // --- SMART SWITCH ---
    $currentActive = getActiveModel($host);

    // If there is an active model AND that model is not the one we currently have selected
    if ($currentActive && $currentActive !== $selectedModel) {
        unloadModel($host, $currentActive);
        // Give a 1 second pause so that the i5 CPU has time to "breathe" after unloading.
        sleep(1); 
    }

    // Using OllamaExec
    $model = new OllamaExec($selectedModel);
    if (!$model->checkModelExists()) {
        echo "Error: Model belum terpasang di sistem.";
        exit;
    }

    // Calling the Wrapper class
    $response = $model->ask($prompt);

    // If the response is an array (error from cURL)
    if (is_array($response)) {
        echo "Terjadi Kesalahan: " . $response['message'];
    } else {
        // Returns the AI ​​answer text
        echo $response;
    }
    exit;
}

// --- Action: Save the Log to the Server Folder ---
if ($action === 'save_log') {
    $model  = $_POST['model'] ?? 'unknown';
    $prompt = $_POST['prompt'] ?? '';
    $result = $_POST['result'] ?? '';
    $duration = $_POST['duration'] ?? '';

    // Take the model name only (remove the :latest tag if any)
    $modelName = explode(':', $model)[0];

    // Create a 'logs' folder if it doesn't exist yet.
    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }
    if (!file_exists('logs/'.str_replace(" ", "_", $modelName))) {
        mkdir('logs/'.$modelName, 0755, true);
    }
    $logPath = 'logs/'.$modelName;

    $timestamp = date('Y-m-d H:i:s');
    $fileName  = $logPath.'/chat-history-' . date('Y-m-d') . '.log';

    $content = "==========================================START\n";
    $content .= "WAKTU  : $timestamp\n";
    $content .= "DURASI : $duration\n";
    $content .= "MODEL  : $model\n";
    $content .= "PROMPT : $prompt\n";    
    $content .= "------------------------------------------\n";
    $content .= "HASIL  :\n$result\n";
    $content .= "==========================================END\n\n";

    // Save with APPEND mode (appends to bottom row, does not overwrite)
    if (file_put_contents($fileName, $content, FILE_APPEND)) {
        echo json_encode(['status' => 'success', 'file' => $fileName]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menulis file']);
    }
    exit;
}

// --- Action: Inject FTS (Embeded Model) ---
if ($action === 'inject_text') {
    $content = $_POST['content'] ?? '';

    if (strlen($content) < 10) {
        echo "<span class='text-red-400'>Teks terlalu pendek!</span>";
        exit;
    }

    $ollama = new OllamaExec();
    $content = $ollama->cleanForIndex($content);

    // automatic sentence correction before entering the FTS Index
    $content = refineContent($content);

    // Setup FTS
    createFtsDb($db_file);
    $db = new PDO('sqlite:'.$db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode = WAL;");

    $chunks = preg_split('/\n\s*\n/', $content);
    $count = 0;
    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if (empty($chunk)) continue;

        // Get embedding (for future/vector search)
        $vector = $ollama->getEmbedding($chunk); 
        
        if ($vector) {
            // 1. Save to master table
            $stmt = $db->prepare("INSERT INTO kearifan_lokal (content, vector) VALUES (?, ?)");
            $stmt->execute([$chunk, json_encode($vector)]);
            
            // 2. Save to quick lookup table (FTS5) - THIS IS IMPORTANT
            $stmtFts = $db->prepare("INSERT INTO kearifan_lokal_fts (content) VALUES (?)");
            $stmtFts->execute([$chunk]);
            
            $count++;
        }
    }

    echo "
        <div x-data=\"{ show: true }\" 
             x-init=\"setTimeout(() => show = false, 8000)\" 
             x-show=\"show\" 
             x-transition:leave=\"transition ease-in duration-1000\"
             x-transition:leave-start=\"opacity-100\"
             x-transition:leave-end=\"opacity-0\"
             class='text-emerald-400 font-bold italic p-2 bg-emerald-500/5 rounded border border-emerald-500/20'>
            ✨ Memory Updated: $count chunks learned.
        </div>";
    exit;
}

if ($action === 'reindex_memory') {
    try {
        $db = new PDO('sqlite:'.$db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->beginTransaction();

        // 1. Clean & Rebuild FTS5 table with complete column schema (Content + Tags)
        $db->exec("DROP TABLE IF EXISTS kearifan_lokal_fts");
        $db->exec("CREATE VIRTUAL TABLE kearifan_lokal_fts USING fts5(
            content, 
            tags, 
            content='kearifan_lokal', 
            content_rowid='id'
        )");

        // 2. Directly move data from Master to FTS at the Database level
        // This doesn't use up any PHP RAM at all!
        $db->exec("INSERT INTO kearifan_lokal_fts(rowid, content, tags) 
                   SELECT id, content, tags FROM kearifan_lokal");

        $db->commit();

        // Calculate total rows for feedback
        $count = $db->query("SELECT count(*) FROM kearifan_lokal_fts")->fetchColumn();
        
        echo "<div x-data='{ show: true }' x-init='setTimeout(() => show = false, 5000)' x-show='show' class='text-amber-400 font-bold italic'>
              🔄 Re-index Berhasil: $count data disinkronkan.
              </div>";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo "<span class='text-red-400'>Gagal Re-index: " . $e->getMessage() . "</span>";
    }
    exit;
}

if ($action === 'get_memory_stats') {
    createFtsDb($db_file);
    $db = new PDO('sqlite:'.$db_file);
    
    
    // Count total chunks
    $count = $db->query("SELECT COUNT(*) FROM kearifan_lokal")->fetchColumn();
    
    // Calculate database file size in KB/MB
    $sizeBytes = filesize($db_file);
    $sizeFormatted = $sizeBytes >= 1048576 
        ? number_format($sizeBytes / 1048576, 2) . ' MB' 
        : number_format($sizeBytes / 1024, 2) . ' KB';

    echo "<div class='flex flex-col text-[10px] text-slate-500 font-mono'>
            <span>TOTAL CHUNKS: <span class='text-blue-400'>$count</span></span>
            <span>DB SIZE: <span class='text-amber-400'>$sizeFormatted</span></span>
          </div>";
    exit;
}
