<?php

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/..');
}

class OllamaExec {
    private $model;
    private $ollamaPath;
    private $dbFile;

    public function __construct($model = 'gemma3:1b ') {
        $this->model = $model;
        // Use absolute path if 'ollama' is not in the web user's PATH
        // Example: '/usr/local/bin/ollama' or simply 'ollama'
        $this->ollamaPath = 'ollama'; 
        $this->dbFile = BASEPATH . '/src/vector_store.db'; //Nama database
    }

    /**
     * Checking whether the model is available in the system
     */
    public function checkModelExists() {
        $command = "{$this->ollamaPath} list 2>&1";
        $output = shell_exec($command);
        
        if ($output === null) return false;

        // Check if the model name is in the output list.
        return str_contains($output, $this->model);
    }

    /**
     * Detects the model currently loaded in RAM
     */
    public function getActiveModel() {
        // The 'ollama ps' command lists the currently running models.
        $command = "{$this->ollamaPath} ps 2>&1";
        $output = shell_exec($command);
        
        if ($output === null || trim($output) === "") return "Standby";

        // Break down by row
        $lines = explode("\n", trim($output));
        
        // The first line is usually the header (NAME, ID, SIZE, etc.)
        // If there is more than 1 line, it means there is an active model
        if (count($lines) > 1) {
            // Take the second row, take the first column (NAME)
            $cols = preg_split('/\s+/', $lines[1]);
            if (isset($cols[0])) {
                return str_replace(':latest', '', $cols[0]);
            }
        }

        return "Standby";
    }

    public function getEmbedding($text) {
        // Use Ollama API endpoint (more stable for embedding)
        $ch = curl_init('http://localhost:11434/api/embeddings');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'nomic-embed-text',
            'prompt' => $this->cleanForIndex($text),
            'keep_alive' => '24h' // Keep the model in RAM for 24 hours
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        return $data['embedding']; // This is an array containing ~768 numbers
    }

    /**
     * Sending prompts directly via the Ollama CLI
     */
    public function ask($prompt) {
        // 1. Validate model existence before heavy execution
        if (!$this->checkModelExists()) {
            return [
                "error" => true, 
                "message" => "Model '{$this->model}' tidak ditemukan. Silakan jalankan 'ollama pull {$this->model}' di terminal."
            ];
        }

        // Default prompt
        $finalPrompt = $prompt;

        // Only use FTS5 feature for AI Chat Agent || Assistant
        if (str_contains($this->model, 'chat') || str_contains($this->model, 'asisten')) {
            $db = new PDO('sqlite:'.$this->dbFile);

            // --- STEP 1: Full-Text Search (Enhanced) ---
            $cleanQuery = preg_replace('/[^A-Za-z0-9 ]/', '', $prompt);
            $contextText = "";
            $results = [];

            if (!empty(trim($cleanQuery))) {
                try {
                    // 1. FTS5 Query Preparation
                    $words = explode(' ', trim($cleanQuery));
                    // Adding * to each word to support partial search (prefix matching)
                    // Use AND to make your search more specific, or leave a space for OR.
                    $ftsQuery = implode(' AND ', array_map(fn ($w) => $w . '*', $words));

                    // A. FTS5 STRATEGY
                    $stmt = $db->prepare("
                                            SELECT m.id, m.content, m.tags 
                                            FROM kearifan_lokal m
                                            JOIN kearifan_lokal_fts f ON m.id = f.rowid
                                            WHERE kearifan_lokal_fts MATCH ? 
                                            ORDER BY bm25(kearifan_lokal_fts) ASC -- ASC karena BM25 negatif (makin kecil makin relevan)
                                            LIMIT 3
                                        ");
                    $stmt->execute([$ftsQuery]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Save the ID you have obtained so that it is not duplicated in the fallback.
                    $excludeIds = array_column($results, 'id');

                    // B. STRATEGI FUZZY FALLBACK
                    if (count($results) < 3) {
                        $needed = 3 - count($results);
                        $placeholders = count($excludeIds) > 0 ? "AND id NOT IN (" . implode(',', array_fill(0, count($excludeIds), '?')) . ")" : "";

                        $sqlLike = "SELECT id, content, tags FROM kearifan_lokal 
                                            WHERE (content LIKE ? OR tags LIKE ?) 
                                            $placeholders 
                                            LIMIT $needed";

                        $stmtLike = $db->prepare($sqlLike);

                        // Combine the LIKE and excluded ID parameters
                        $params = ["%$cleanQuery%", "%$cleanQuery%"];
                        if (!empty($excludeIds)) {
                            $params = array_merge($params, $excludeIds);
                        }

                        $stmtLike->execute($params);
                        $results = array_merge($results, $stmtLike->fetchAll(PDO::FETCH_ASSOC));
                    }

                    // 2. Result Format for AI Prompt
                    $contextArr = [];
                    foreach ($results as $row) {
                        $tagLabel = !empty($row['tags']) ? "[Tags: " . $row['tags'] . "]" : "[No Tags]";
                        $contextArr[] = "- $tagLabel " . $row['content'];
                    }
                    $contextText = implode("\n", $contextArr);

                } catch (PDOException $e) {
                    $results = [];
                }
            }

            // --- STEP 2: Augment Prompt ---
            // Build Prompt dynamically
            if (!empty($contextText)) {
                $finalPrompt = "Gunakan data referensi berikut untuk menjawab pertanyaan.\n" . $contextText . "\nPertanyaan: " . $prompt;
            }
        }

        // 2. Escape prompt for shell security
        $userPrompt = escapeshellarg($finalPrompt);

        // 3. Build command (Use $user Prompt, not $final Prompt!)
        // Add quotes around the model and prompt for extra security
        $command = "{$this->ollamaPath} run {$this->model} {$userPrompt} 2>&1";

        // 4. Execution
        $output = shell_exec($command);

        if ($output === null) {
            return ["error" => true, "message" => "Gagal mengeksekusi Ollama binary."];
        }

        return $this->processResponse($output);
    }

    public function cleanForIndex($text) {
        // 1. Remove URLs (http, https, ftp, and www)
        // Remove links to focus the model on informative content
        $urlPattern = '/\b(?:https?|ftp):\/\/\S+|www\.\S+/i';
        $text = preg_replace($urlPattern, '', $text);

        // 2. Decode HTML entities (Example: &nbsp; to spaces)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 3. Remove HTML tags
        $text = strip_tags($text);
        
        // 4. Fix spaces after punctuation for accurate LLM tokenization
        // Change "Text.This" to "Text.This"
        $text = preg_replace('/([.!?])(?=[^\s])/', '$1 ', $text);

        // 5. NORMALIZE SPACE (Only horizontal spaces, not newlines)
        // [ \t\f] is space and tab, not \r or \n
        $text = preg_replace('/[ \t\f]+/', ' ', $text);
        
        // 6. Limit excessive newlines (Maximum 2 consecutive newlines to avoid empty spaces)
        $text = preg_replace("/(\r\n|\n|\r){3,}/", "\n\n", $text);
        
        // 7. Remove the remaining non-printable characters using Unicode properties
        $text = preg_replace('/[^\PC\s]/u', '', $text);
        
        return trim($text);
    }

    /**
     * Cleans output of ANSI escape codes and CLI progress residue
     */
    private function processResponse($output) {
        // 1. Remove ANSI Escape Sequences (Color & Cursor Movement)
        $cleanOutput = preg_replace('/\x1b[[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-ORZcf-nqry=><]/', '', $output);
        
        // 2. Remove Braille Characters (Noise Spinner Ollama: U+2800 to U+28FF)
        // This character usually appears as "⠙ ⠹ ⠸"
        $cleanOutput = preg_replace('/[\x{2800}-\x{28FF}]/u', '', $cleanOutput);
        
        // 3. Remove excess whitespace at the beginning due to noise removal
        $cleanOutput = ltrim($cleanOutput);

        // 4. If there is still "faint text" or other control characters (such as \r or \b)
        $cleanOutput = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanOutput);
        
        // Removed progress strings like "loading model..." that sometimes leaked to stdout.
        $cleanOutput = preg_replace('/pulling.*?\d+%/i', '', $cleanOutput);
        
        return trim($cleanOutput) ?: "AI tidak memberikan respon.";
    }
}