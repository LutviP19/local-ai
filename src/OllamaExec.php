<?php

class OllamaExec {
    private $model;
    private $ollamaPath;

    public function __construct($model = 'gemma3:1b') {
        $this->model = $model;
        // Use absolute path if 'ollama' is not in the web user's PATH
        // Example: '/usr/local/bin/ollama' or simply 'ollama'
        $this->ollamaPath = 'ollama'; 
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