<?php

require_once 'src/OllamaExec.php';

// Menggunakan OllamaExec
$model = new OllamaExec('default-chat');

// Contoh Case: Chat sederhana
$prompt = "Katakan Hallo.";

// echo "<p>$prompt</p>" . PHP_EOL;
// echo "<h3>Output:</h3>" . PHP_EOL;
// echo "<pre>" . $model->ask($prompt) . "</pre>" . PHP_EOL;

echo "Prompt: $prompt" . PHP_EOL;
echo "Output:" . PHP_EOL;
echo $model->ask($prompt) . PHP_EOL;
