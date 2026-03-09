<?php 
// api-debug.php

require_once 'bootstrap.php';


$db_file = BASEPATH . '/src/vector_store.db';
$backup_dir = BASEPATH . '/backups';

// Create a backup folder if it doesn't exist yet
if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);

// Load database
try {
    // Setup FTSs
    $db = new PDO('sqlite:'.$db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode = WAL;");

    // 1. Master Table (Main Data)
    $db->exec("CREATE TABLE IF NOT EXISTS kearifan_lokal (
        id INTEGER PRIMARY KEY, 
        content TEXT, 
        tags TEXT,
        vector BLOB,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. FTS5 Virtual Table (For Quick Search)
    // We've included 'tags' so it can also be searched via FTS
    $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS kearifan_lokal_fts USING fts5(
        content, 
        tags, 
        content='kearifan_lokal', 
        content_rowid='id'
    )");
} catch (Exception $e) {
    $error = $e->getMessage();
}

// --- LOGIC: BACKUP ---
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    try {
        $filename = $backup_dir . '/backup_' . date('Ymd_His') . '.db';
        copy($db_file, $filename);
        $msg = "✅ Backup berhasil dibuat: $filename";
        echo "<div x-init='setTimeout(() => window.location.reload(), 1500)' class='p-4 mb-6 bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 rounded-xl text-sm italic'>$msg</div>";
    } catch (Exception $e) {
        echo "<div class='p-4 mb-6 bg-red-500/10 border border-red-500/50 text-red-400 rounded-xl text-sm'>
                ❌ Gagal Backup: " . $e->getMessage() . "
              </div>";
    }
    exit;
}

// --- LOGIC: DELETE ITEM ---
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    // Delete from master table
    try {
        $db->beginTransaction();
        $stmt1 = $db->prepare("DELETE FROM kearifan_lokal WHERE id = ?");
        $stmt1->execute([$id]);
        // Delete from FTS table (Use content for synchronization if ROWID is different)
        $db->commit();
        // The easiest way is to suggest Re-index after mass delete
        $msg = "🗑️ Data ID $id telah dihapus. Jangan lupa Re-index agar sinkron!";
        echo "<td colspan='3' class='px-3'><div x-init='setTimeout(() => window.location.reload(), 3000)' class='p-4 mb-6 bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 rounded-xl text-sm italic'>
            $msg
            </div></td>";
    } catch (Exception $e) {
        $db->rollBack();
        echo "<td colspan='3' class='px-3'>Error: " . $e->getMessage() ."</td>";
    }
}

// --- LOGIC: RE-SYNC INDEX (FTS5 Reconstruction) ---
if (isset($_GET['action']) && $_GET['action'] === 'resync') {
    try {
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

        echo "<div x-init='setTimeout(() => window.location.reload(), 1500)' class='p-4 mb-6 bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 rounded-xl text-sm italic'>
                ✅ Index FTS5 Berhasil Di-sinkronisasi! Memuat ulang data...
              </div>";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo "<div x-data=\"{ show: true }\" 
                 x-init=\"setTimeout(() => show = false, 8000)\" 
                 x-show=\"show\" 
                 x-transition:leave=\"transition ease-in duration-1000\"
                 x-transition:leave-start=\"opacity-100\"
                 x-transition:leave-end=\"opacity-0\"
                class='p-4 mt-4 mb-4 bg-red-500/10 border border-red-500/50 text-red-400 rounded-xl text-sm'>
                ❌ Gagal Sinkronisasi: " . $e->getMessage() . "
              </div>";
    }
    exit;
}

// Save data to master table, then trigger synchronization to FTS5 table.
if (isset($_GET['action']) && $_GET['action'] === 'add_and_sync') {
    $ollama = new OllamaExec();
    $tags = $_POST['tags'] ? strtolower(trim($_POST['tags'])) : ''; // Keep tags in lowercase for consistency
    $content = $ollama->cleanForIndex($_POST['content']) ?? '';

    // automatic sentence correction before entering FTS Index
    $content = refineContent($content);
    
    try {
        $db->beginTransaction();

        // 1. Save to DB
        $stmt = $db->prepare("INSERT INTO kearifan_lokal (content, tags) VALUES (?, ?)");
        $stmt->execute([$content, $tags]);
        $newId = $db->lastInsertId();

        // 2. Update FTS5 Incrementally (New rows only)
        // Make sure the tags column is already in your FTS5 schema!
        $stmtFts = $db->prepare("INSERT INTO kearifan_lokal_fts (rowid, content, tags) VALUES (?, ?, ?)");
        $stmtFts->execute([$newId, $content, $tags]);

        $db->commit();

        echo "<tr id='row-$newId' class='border-b border-slate-800 animate-pulse'>";
        echo "<td colspan='3' class='px-3'><div x-init='setTimeout(() => window.location.reload(), 3000)' class='p-4 mb-6       bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 rounded-xl text-sm italic'>
            ✅ Index berhasil ditambahkan! Merefresh data...
            </div></td>";
        echo "</tr>";
    } catch (Exception $e) {
        $db->rollBack();
        echo "<tr id='row-x' class='border-b border-slate-800 animate-pulse'>";
        echo "<td colspan='3' class='px-3'>
              <div x-data=\"{ show: true }\" 
                 x-init=\"setTimeout(() => show = false, 8000)\" 
                 x-show=\"show\" 
                 x-transition:leave=\"transition ease-in duration-1000\"
                 x-transition:leave-start=\"opacity-100\"
                 x-transition:leave-end=\"opacity-0\"
                class='p-4 mt-4 mb-4 bg-red-500/10 border border-red-500/50 text-red-400 rounded-xl text-sm'>
                ❌ Gagal menambah data: " . $e->getMessage() . "
              </div></td>";
        echo "</tr>";
    }

    exit;
}

// --- LOGIC: EDIT MEMORY ---
if (isset($_GET['action']) && $_GET['action'] === 'edit_memory') {
    $ollama = new OllamaExec();
    $id = (int)$_POST['id'] ?? null;
    $tags = $_POST['tags'] ? strtolower(trim($_POST['tags'])) : ''; // Keep tags in lowercase for consistency
    $content = $ollama->cleanForIndex($_POST['content']) ?? '';

    if(is_null($id) || $content === '') {
        echo "<td colspan='3' class='px-3'>Error: Parameter yang dikirimkan tidak valid.</td>";
        exit;
    }

    // automatic sentence correction before entering FTS Index
    $content = refineContent($content);

    try {
        $db->beginTransaction();

        // 1. Get OLD data before updating (Mandatory to delete clean FTS5 index)
        $stmtOld = $db->prepare("SELECT content, tags FROM kearifan_lokal WHERE id = ?");
        $stmtOld->execute([$id]);
        $old = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if ($old) {
            // 2. Remove the OLD index from the FTS5 table
            // FTS5 needs precise old data to remove the word pointer from the index
            $stmtDel = $db->prepare("INSERT INTO kearifan_lokal_fts(kearifan_lokal_fts, rowid, content, tags) 
                                     VALUES('delete', ?, ?, ?)");
            $stmtDel->execute([$id, $old['content'], $old['tags']]);
        }

        // 3. Update data in the MASTER table
        $stmtUpd = $db->prepare("UPDATE kearifan_lokal SET content = ?, tags = ? WHERE id = ?");
        $stmtUpd->execute([$content, $tags, $id]);

        // 4. Insert NEW index into FTS5 table
        $stmtIns = $db->prepare("INSERT INTO kearifan_lokal_fts(rowid, content, tags) VALUES(?, ?, ?)");
        $stmtIns->execute([$id, $content, $tags]);

        $db->commit();
        
        // Send a success notification and force a page refresh to display the latest table data
        // (Or use hx-trigger to update specific rows for more advanced features)
        echo "<td colspan='3' class='px-3'><div x-init='setTimeout(() => window.location.reload(), 3000)' class='p-4 mb-6 bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 rounded-xl text-sm italic'>
                ✅ Index dibersihkan & diperbarui! Merefresh data...
              </div></td>";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo "<td colspan='3' class='px-3'>
              <div x-data=\"{ show: true }\" 
                 x-init=\"setTimeout(() => show = false, 8000)\" 
                 x-show=\"show\" 
                 x-transition:leave=\"transition ease-in duration-1000\"
                 x-transition:leave-start=\"opacity-100\"
                 x-transition:leave-end=\"opacity-0\"
                class='p-4 mt-4 mb-4 bg-red-500/10 border border-red-500/50 text-red-400 rounded-xl text-sm'>
                ❌ Gagal perbaharui data: " . $e->getMessage() . "
              </div></td>";
    }
    exit;
}

// --- LOGIC: DELETE ITEM (Direct from FTS) ---
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    
    // 1. Delete from Master table
    $db->prepare("DELETE FROM kearifan_lokal WHERE id = ?")->execute([$id]);
    
    // 2. Delete from the FTS table (Important for synchronous lookups)
    // In FTS5, the rowid is usually the same as the id in the master table if inserted simultaneously.
    $stmtFts = $db->prepare("DELETE FROM kearifan_lokal_fts WHERE rowid = ?");
    $stmtFts->execute([$id]);
    
    $msg = "🗑️ Memori berhasil dihapus dari Database & Index FTS!";
}

// --- LOGIC: AI REFINE (GEMMA 3:1B) ---
if (isset($_GET['action']) && $_GET['action'] === 'ai_refine') {
    $text = $_POST['content'] ?? '';
    
    // Use the shell exec function to call Ollama synchronously
    // Make sure Ollama is running in the background
    $prompt = "Tolong perbaiki ejaan dan tata bahasa kalimat ini agar rapi dan baku tanpa mengubah maknanya. Jangan berikan kalimat pembuka, langsung hasil akhirnya saja: " . escapeshellarg($text);
    
    $command = "ollama run asisten-chat:latest " . $prompt;
    $output = shell_exec($command);
    
    echo trim($output ?: $text); // Return the result or original text if it fails
    exit;
}