<?php

class OllamaExec {
    private $model;
    private $ollamaPath;

    public function __construct($model = 'gemma3') {
        $this->model = $model;
        // Use absolute path if 'ollama' is not in the web user's PATH
        // Example: '/usr/local/bin/ollama' or simply 'ollama'
        $this->ollamaPath = 'ollama'; 
    }

    /**
     * Checks whether the model is available in the system
     */
    public function checkModelExists() {
        $command = "{$this->ollamaPath} list 2>&1";
        $output = shell_exec($command);
        
        if ($output === null) return false;

        // Check whether the model name is present in the output list
        return str_contains($output, $this->model);
    }

    /**
     * Send prompts directly via the Ollama CLI
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

        // 2. Escape prompt for shell security (This is correct)
        $userPrompt = escapeshellarg($finalPrompt);

        // 3. Build command (Use $userPrompt, not $finalPrompt!)
        // Add quotes around the model and prompt for extra security
        $command = "{$this->ollamaPath} run {$this->model} {$userPrompt} 2>&1";

        // 4.Execution
        $output = shell_exec($command);

        if ($output === null) {
            return ["error" => true, "message" => "Gagal mengeksekusi Ollama binary."];
        }

        return $this->processResponse($output);
    }

    /**
     * Cleans up the output of ANSI escape codes and residual CLI progress
     */
    private function processResponse($output) {
        // 1. Remove ANSI Escape Sequences (Color & Cursor Movement)
        $cleanOutput = preg_replace('/\x1b[[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-ORZcf-nqry=><]/', '', $output);
        
        // 2. Delete Braille Characters (Noise Spinner Ollama: U+2800 to U+28FF)
        // These characters usually appear as "⠙ ⠹ ⠸"
        $cleanOutput = preg_replace('/[\x{2800}-\x{28FF}]/u', '', $cleanOutput);
        
        // 3. Remove excess whitespace at the beginning due to noise removal
        $cleanOutput = ltrim($cleanOutput);

        // 4. If there is still "faint text" or other control characters remaining (such as \r or \b)
        $cleanOutput = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanOutput);
        
        // Removed progress strings like "loading model..." that sometimes leaked to stdout
        $cleanOutput = preg_replace('/pulling.*?\d+%/i', '', $cleanOutput);
        
        return trim($cleanOutput) ?: "AI tidak memberikan respon.";
    }
}