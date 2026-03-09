<?php

class Stubs {

    // Konstanta Warna Terminal
    const CLR_SUCCESS = "\033[0;32m"; // Hijau
    const CLR_ERROR   = "\033[0;31m"; // Merah
    const CLR_INFO    = "\033[0;34m"; // Biru
    const CLR_BOLD    = "\033[1m";    // Tebal
    const CLR_RESET   = "\033[0m";    // Reset Warna

    /**
     * Fungsi dinamis untuk men-generate file dari stub
     */
    public static function generate(string $newName, string $stubPath, string $targetDir)
    {
        // 1. Validasi file sumber
        if (!file_exists($stubPath)) {
            return self::CLR_ERROR . "❌ Error: File stub tidak ditemukan di $stubPath" . self::CLR_RESET . "\n";
        }

        // 2. Buat folder jika belum ada
        $targetDirParts = explode('/', $targetDir);
        $formattedDirParts = array_map('ucfirst', $targetDirParts);
        $targetDir = implode('/', $formattedDirParts);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // 3. Baca isi file stub
        $content = file_get_contents($stubPath);

        // --- A. LOGIKA NAMESPACE ---
        if (!str_contains($targetDir, 'stubs')) {
            // 1. Bersihkan path: hilangkan "./" di depan dan "/" di akhir
            $cleanPath = ltrim($targetDir, './'); 
            $cleanPath = rtrim($cleanPath, '/');
            
            // 2. Normalisasi pemisah folder agar konsisten (Windows/Linux)
            $normalizedPath = str_replace('\\', '/', $cleanPath);
            
            // 3. Pecah folder menjadi bagian-bagian
            $folderParts = explode('/', $normalizedPath);
            
            // 4. AUTO-CORRECTION: Paksa setiap bagian menjadi PascalCase
            // Contoh: "app/core/models" -> ["App", "Core", "Models"]
            $formattedParts = array_map(function($part) {
                // Daftar folder yang biasanya singkatan (opsional)
                // $specialCases = ['api' => 'API', 'url' => 'URL', 'id' => 'ID'];
                $lowerPart = strtolower($part);
                
                // if (isset($specialCases[$lowerPart])) {
                //     return $specialCases[$lowerPart];
                // }
                
                return ucfirst($lowerPart);
            }, array_filter($folderParts));
            
            $newNamespace = implode('\\', $formattedParts);
            
            // 5. Update konten file stub dengan regex
            $content = preg_replace('/namespace\s+[^;]+;/', "namespace {$newNamespace};", $content);
        } else {
            // Default Namespace untuk folder stubs
            $newNamespace = str_contains($newName, 'Model') ? 'App\\Models' : 'App\\Controllers';
        }

        // --- B. LOGIKA NAMA CLASS DINAMIS ---
        $content = preg_replace('/class\s+\w+/', "class {$newName}", $content);

        // --- C. SIMPAN FILE ---
        $destination = rtrim($targetDir, '/') . "/{$newName}.php";

        if (file_exists($destination)) {
            return self::CLR_ERROR . "⚠️  Error: File {$newName}.php sudah ada di {$targetDir}!" . self::CLR_RESET . "\n";
        }

        if (file_put_contents($destination, $content)) {
            $output = self::CLR_SUCCESS . self::CLR_BOLD . "✅ Sukses: " . self::CLR_RESET;
            $output .= self::CLR_SUCCESS . "'{$newName}' berhasil dibuat." . self::CLR_RESET . "\n";
            $output .= self::CLR_INFO . "📌 Namespace: " . self::CLR_RESET . "{$newNamespace}\n";
            $output .= self::CLR_INFO . "📍 Lokasi: " . self::CLR_RESET . "{$destination}\n";
            return $output;
        }

        return self::CLR_ERROR . "❌ Error: Gagal menulis file ke disk." . self::CLR_RESET . "\n";
    }

    /**
     * Khusus untuk men-generate file View (HTML/PHP)
     * Tanpa logika Namespace dan Class
     */
    public static function generateView(string $newName, string $stubPath, string $targetDir)
    {
        // 1. Validasi file sumber
        if (!file_exists($stubPath)) {
            return self::CLR_ERROR . "❌ Error: File stub View tidak ditemukan di $stubPath" . self::CLR_RESET . "\n";
        }

        // 2. Buat folder jika belum ada
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // 3. Baca isi file stub
        $content = file_get_contents($stubPath);

        // --- A. LOGIKA PLACEHOLDER DINAMIS (Opsional) ---
        // Mengganti {{title}} atau {{name}} di dalam HTML jika ada
        $content = str_replace(['{{title}}', '{{name}}'], $newName, $content);

        // --- B. SIMPAN FILE ---
        // Pastikan nama file lowercase untuk standar View
        $fileName = strtolower($newName);
        $destination = rtrim($targetDir, '/') . "/{$fileName}.php";

        if (file_exists($destination)) {
            return self::CLR_ERROR . "⚠️  Error: View {$destination} sudah ada!" . self::CLR_RESET . "\n";
        }

        if (file_put_contents($destination, $content)) {
            $output = self::CLR_SUCCESS . self::CLR_BOLD . "🎨 Sukses: " . self::CLR_RESET;
            $output .= self::CLR_SUCCESS . "View '{$fileName}' berhasil dibuat." . self::CLR_RESET . "\n";
            $output .= self::CLR_INFO . "📍 Lokasi: " . self::CLR_RESET . "{$destination}\n";
            return $output;
        }

        return self::CLR_ERROR . "❌ Error: Gagal menulis file view." . self::CLR_RESET . "\n";
    }

     /**
     * Fungsi untuk men-generate model baru dari stub
     * @param string $newClassName Nama class baru (misal: 'DashboardModel')
     * @param string $stubPath Path file sumber
     * @param string $targetDir Direktori tujuan penyimpanan
     */
    public static function generateModelFromStub(string $newClassName, string $stubPath, string $targetDir)
    {
        // 1. Validasi file sumber
        if (!file_exists($stubPath)) {
            return "Error: File stub tidak ditemukan di $stubPath";
        }

        // 2. Baca isi file stub
        $content = file_get_contents($stubPath);

        // -- A. Perbaiki Namespace Berdasarkan Folder --    
        // Cek apakah targetDir TIDAK mengandung kata "stubs"
        if (!str_contains($targetDir, 'stubs')) {

            // Mengonversi App/Models menjadi App\Models (Standard PSR-4)
            // ucfirst setiap bagian folder agar namespace rapi (Contoh: app/models -> App\Models)
            $folderParts = explode('/', $targetDir);
            $formattedParts = array_map('ucfirst', $folderParts);
            $newNamespace = implode('\\', $formattedParts);

            // Mengonversi App/Models menjadi App\Models
            $newNamespace = str_replace('/', '\\', $targetDir);

            // Jika folder input diawali 'App', kita asumsikan itu namespace utama
            $content = preg_replace('/namespace\s+[^;]+;/', "namespace {$newNamespace};", $content);
        } else {
            $newNamespace = str_replace('/', '\\', 'App/Models');
            // echo "ℹ️  Menjaga namespace asli (Folder stub terdeteksi).\n";
        }

        // -- B. Ganti Nama Class --
        $content = str_replace('class MyModel', "class {$newClassName}", $content);

        // 6. Simpan File
        $destination = "{$targetDir}/{$newClassName}.php";

        if (file_exists($destination)) {
            die("⚠️ Error: File {$newClassName}.php sudah ada di folder tersebut!\n");
        }

        if (file_put_contents($destination, $content)) {
            echo "✅ Sukses: Model '{$newClassName}' berhasil dibuat.\n";
            // if (!str_contains($targetDir, 'stubs')) {
                echo "📌 Namespace: {$newNamespace}\n";
            // }
            echo "📍 Lokasi: {$destination}\n";
        } else {
            echo "❌ Error: Gagal menulis file.\n";
        }
    }

    /**
     * Mengonversi input ke PascalCase + Suffix
     * Contoh: "user_setting" + "Controller" -> "UserSettingController"
     */
    public static function formatClassName(string $input, string $suffix): string 
    {
        // 1. Bersihkan suffix jika user sudah mengetiknya (case-insensitive)
        // Menggunakan regex agar "usercontroller" tetap jadi "User" sebelum ditambah suffix resmi
        $cleanName = preg_replace("/$suffix$/i", '', trim($input));
        
        // 2. Ubah snake_case/kebab-case menjadi PascalCase
        // "product_detail" -> "Product Detail" -> "ProductDetail"
        $pascal = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $cleanName)));
        
        return $pascal . $suffix;
    }

    /**
     * Mengonversi input ke snake_case untuk View
     * Contoh: "UserDetail" -> "user_detail"
     */
    public static function formatViewName(string $input): string 
    {
        // Pisahkan huruf kapital dengan underscore (untuk handle Pascal ke Snake)
        $input = preg_replace('/([a-z])([A-Z])/', '$1_$2', trim($input));
        
        // Kecilkan semua dan ubah spasi/dash menjadi underscore
        $snake = strtolower(str_replace([' ', '-'], '_', $input));
        
        // Bersihkan double underscore jika ada
        return preg_replace('/__+/', '_', $snake);
    }
}