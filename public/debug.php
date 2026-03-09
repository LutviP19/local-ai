<?php
include_once "api-debug.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <script src="./assets/js/htmx.min.js"></script>
    <script defer src="./assets/js/alpine.min.js"></script>
    <script src="./assets/js/tailwindcss.js"></script>
    <link rel="stylesheet" href="./assets/css/app.css">
    <title>FTS5 Debugger & Admin</title>
</head>
<body x-data class="bg-slate-950 text-slate-300 p-8">

    <div class="max-w-5xl mx-auto">
        <div class="relative flex flex-col mb-8">
            <h1 class="text-2xl font-bold text-emerald-400">FTS5 Debugger</h1>

            <div class="flex items-center mt-3 gap-4 bg-slate-900/50 p-2 px-4 rounded-full border border-slate-700/50">
                <div class="flex gap-1.5">
                    <div title="Chat Model" 
                         class="w-3.5 h-3.5 rounded-full bg-emerald-500 animate-pulse flex items-center justify-center text-[7px] font-bold text-emerald-950 leading-none">
                         M
                    </div>
                    
                    <div title="Embedding Model" 
                         class="w-3.5 h-3.5 rounded-full bg-blue-500 animate-pulse flex items-center justify-center text-[7px] font-bold text-blue-950 leading-none">
                         E
                    </div>
                </div>
                
                <div hx-get="api.php?action=get_memory_stats" 
                     hx-trigger="load" 
                     class="border-l border-slate-700 pl-4">
                    <div class="animate-pulse bg-slate-700 h-3 w-20 rounded"></div>
                </div>
            </div>
            
            <div class="absolute top-0 right-0 flex gap-4" id="backup-status">
                <button hx-get="api-debug.php?action=backup" 
                        hx-target="#msg-container"
                        class="flex items-center gap-2 px-3 py-1.5 bg-slate-800 text-slate-300 border border-slate-700 rounded-lg hover:bg-slate-700 hover:text-emerald-400 transition-all group">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 group-hover:text-emerald-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    <span class="text-[10px] font-bold uppercase tracking-wider">Buat Backup</span>
                </button>
                <button @click="$dispatch('open-modal', { 
                            title: 'Re-Sync Index', 
                            message: 'Ini akan membangun ulang seluruh index pencarian FTS5. Lanjutkan?', 
                            type: 'warning',
                            confirmText: 'Ya, Re-Sync Index',
                            action: () => { 
                                htmx.ajax('GET', 'api-debug.php?action=resync', {
                                    target: '#msg-container', 
                                    swap: 'innerHTML' // MENGGUNAKAN innerHTML agar pesan muncul di dalam container
                                })
                            }
                        })"
                        class="flex items-center gap-2 px-3 py-1.5 bg-slate-800 text-slate-300 border border-slate-700 rounded-lg hover:bg-slate-700 hover:text-blue-400 transition-all group">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 group-hover:text-blue-400 group-hover:rotate-180 transition-all duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>

                    <span class="text-[10px] font-bold uppercase tracking-wider">Re-Sync Index</span>
                </button>

                <button @click="$dispatch('open-add-modal')" 
                        class="flex items-center gap-2 px-3 py-1.5 bg-slate-800 text-slate-300 border border-slate-700 rounded-lg hover:bg-slate-700 hover:text-emerald-400 transition-all group">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 group-hover:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-[10px] font-bold uppercase tracking-wider">Tambah Memori</span>
                </button>
            </div>

            <div id="msg-container" class="mt-4"></div>
        </div>

        <?php if (isset($msg)): ?>
            <div class="p-4 mb-6 bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 rounded-xl text-sm italic">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 mb-8">
            <form method="GET" class="flex gap-2">
                <div class="relative flex-1">
                    <input type="text" name="q" id="search-input" autocomplete="off" 
                           placeholder="Cari potongan memori..." 
                           value="<?php echo $_GET['q'] ?? ''; ?>" 
                           class="w-full bg-slate-950 border border-slate-700 rounded-lg pl-4 pr-10 py-2 focus:outline-none focus:border-emerald-500">
                    
                    <?php if (!empty($_GET['q'])): ?>
                    <button type="button" 
                            onclick="window.location.href='debug.php'" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-red-400 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>

                <button type="submit" class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-500 transition-colors">
                    Cari
                </button>
            </form>
        </div>

        <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-800/50 text-xs uppercase tracking-widest text-slate-500">
                        <th class="p-4 border-b border-slate-800">ID</th>
                        <th class="p-4 border-b border-slate-800">Isi Memori (Content)</th>
                        <th class="p-4 border-b border-slate-800 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="table-body" class="text-sm">
                    <?php
                    $q = $_GET['q'] ?? false;
                    
                    // If there is a search, use MATCH (FTS5) for more accurate results
                    if ($q) {
                        // Sanitize input but still allow spaces
                        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $q);
                        $words = explode(' ', trim($clean));
                        
                        // Combine with AND to make the results more relevant (all words must be present)
                        // Example: "batik solo" becomes "batik* AND solo*"
                        $ftsQuery = implode(' AND ', array_map(fn($w) => $w . '*', $words));
                    
                        $sql = "SELECT m.id, m.content, m.tags 
                                FROM kearifan_lokal m
                                JOIN kearifan_lokal_fts f ON m.id = f.rowid
                                WHERE kearifan_lokal_fts MATCH ? 
                                ORDER BY bm25(kearifan_lokal_fts) ASC -- BM25 makin KECIL makin RELEVAN
                                LIMIT 10";
                    
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$ftsQuery]);
                    } else {
                        // If there is no search, fetch directly from Master (Faster)
                        $sql = "SELECT id, content, tags FROM kearifan_lokal ORDER BY id DESC LIMIT 10";
                        $stmt = $db->query($sql);
                    }
                    
                    $stmt->execute();
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr id="row-<?php echo $row['id']; ?>" class="border-b border-slate-800/50 hover:bg-slate-800/30 transition">
                            <td class="p-4 text-slate-500 font-mono"><?php echo $row['id']; ?></td>
                            <td class="p-4 leading-relaxed">
                                <div class="text-sm text-slate-400 line-clamp-3">
                                    <?php echo highlight($row['content'], $_GET['q'] ?? ''); ?>
                                </div>
                                <?php if (!empty($row['tags'])): ?>
                                <div class="flex gap-2 mt-2">
                                    <?php 
                                    $tagList = explode(',', $row['tags']);
                                    foreach ($tagList as $tag): 
                                    ?>
                                        <span class="text-[9px] bg-slate-800 text-emerald-400 px-2 py-0.5 rounded border border-slate-700">
                                            #<?php echo trim($tag); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="$dispatch('open-edit-modal', { 
                                                id: '<?php echo $row['id']; ?>', 
                                                content: <?php echo htmlspecialchars(json_encode($row['content']), ENT_QUOTES, 'UTF-8'); ?>,
                                                tags: `<?php echo addslashes($row['tags'] ?? ''); ?>` 
                                            })"
                                            class="hover:bg-blue-500/10 p-2 rounded-lg transition-colors group">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500/50 group-hover:text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button @click="$dispatch('open-modal', { 
                                            title: 'Hapus Memori', 
                                            message: 'Hapus data ini secara permanen dari Database & FTS?', 
                                            type: 'warning',
                                            confirmText: 'Ya, Hapus',
                                            action: () => { 
                                                htmx.ajax('GET', 'api-debug.php?delete_id=<?php echo $row['id']; ?>', {
                                                    target: '#row-<?php echo $row['id']; ?>', 
                                                    swap: 'delete'
                                                })
                                            }
                                        })"
                                        class="hover:bg-red-500/10 p-2 rounded-lg transition-colors group">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-500/50 group-hover:text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ModalBox -->
    <div x-data="{ 
            open: false, 
            title: '', 
            message: '', 
            confirmAction: null,
            confirmText: 'Ya, Lanjutkan',
            type: 'info',
            triggerAction() {
                if (this.confirmAction) {
                    // Trigger HTMX secara manual jika dibutuhkan atau eksekusi fungsi
                    this.confirmAction();
                    this.open = false;
                }
            }
         }" 
         x-cloak 
         x-show="open" 
         @open-modal.window="
            open = true; 
            title = $event.detail.title; 
            message = $event.detail.message; 
            confirmAction = $event.detail.action;
            confirmText = $event.detail.confirmText || 'Ya';
            type = $event.detail.type || 'info';
         "
         class="relative z-[999]">
        
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm"></div>

        <div x-show="open" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="fixed inset-0 flex items-center justify-center p-4">
            
            <div @click.away="open = false" class="bg-slate-900 border border-slate-700 w-full max-w-sm p-6 rounded-2xl shadow-2xl">
                <h2 class="text-lg font-bold mb-2" 
                    :class="type === 'warning' ? 'text-amber-400' : 'text-blue-400'" 
                    x-text="title"></h2>
                
                <p class="text-sm text-slate-400 mb-6" x-text="message"></p>

                <div class="flex justify-end gap-3">
                    <button @click="open = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-300">Batal</button>
                    <button @click="triggerAction()" 
                            class="px-5 py-2 rounded-xl text-xs font-bold transition-all"
                            :class="type === 'warning' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/50' : 'bg-blue-500/20 text-blue-400 border border-blue-500/50'"
                            x-text="confirmText"></button>
                </div>
            </div>
        </div>
    </div>
    <!-- End ModalBox -->

    <!-- Modal Form -->
    <div x-data="{ 
            open: false, 
            id: '', 
            content: '',
            tags: '',
            loading: false,
            isEdit: false // Flag untuk membedakan mode
         }" 
         x-cloak 
         x-show="open"
         @open-edit-modal.window="open = true; isEdit = true; id = $event.detail.id; content = $event.detail.content; tags = $event.detail.tags || '';"
         @open-add-modal.window="open = true; isEdit = false; id = ''; content = ''; tags = '';"
         class="relative z-[999]">

        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm"></div>

        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-700 w-full max-w-4xl p-6 rounded-2xl shadow-2xl">
                <h2 class="text-lg font-bold mb-4" :class="isEdit ? 'text-blue-400' : 'text-emerald-400'" 
                    x-text="isEdit ? 'Edit Memori Index' : 'Tambah Memori Baru'"></h2>

                <form @submit.prevent="
                        const action = isEdit ? 'edit_memory' : 'add_and_sync';
                        htmx.ajax('POST', 'api-debug.php?action=' + action, {
                            values: { id: id, content: content, tags: tags },
                            target: isEdit ? '#row-' + id : '#table-body', // Jika tambah, targetkan ke body tabel
                            swap: isEdit ? 'outerHTML' : 'afterbegin' // Jika tambah, taruh di paling atas
                        });
                        open = false; 
                    ">
                    
                    <input type="hidden" name="id" :value="id">
                    
                    <div class="mb-6">
                        <label class="block text-[10px] uppercase tracking-widest text-slate-500 mb-2">Konten Memori</label>
                        <button type="button" 
                                @click="
                                    let btn = $el;
                                    let originalText = btn.innerText;
                                    btn.innerText = 'Memproses...';
                                    fetch('api-debug.php?action=ai_refine', {
                                        method: 'POST',
                                        body: new URLSearchParams({'content': content})
                                    })
                                    .then(res => res.text())
                                    .then(data => {
                                        content = data;
                                        btn.innerText = originalText;
                                    })
                                "
                                class="mb-2 text-[10px] bg-purple-600/20 text-purple-400 border border-purple-500/50 px-2 py-1 rounded hover:bg-purple-600/40 transition">
                            ✨ AI Refine (Gemma)
                        </button>
                        <textarea 
                            name="content" 
                            x-model="content" 
                            required
                            x-init="$nextTick(() => { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px' })"
                            @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-emerald-500 text-slate-300 transition-all
                                   overflow-y-auto scrollbar-thin scrollbar-thumb-slate-700"
                            style="min-height: 150px; max-height: 70vh; resize: none;"
                            placeholder="Masukkan konten kearifan lokal..."></textarea>
                    </div>

                    <div class="mb-6">
                        <label class="block text-[10px] uppercase tracking-widest text-slate-500 mb-2">Tags (Pisahkan dengan koma)</label>
                        <input type="text" x-model="tags" name="tags" autocomplete="off" placeholder="misal: sejarah, budaya, penting"
                               class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-purple-500 text-slate-300">
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" 
                                @click="open = false" 
                                class="px-6 py-2 bg-slate-800/50 text-slate-400 border border-slate-700 rounded-xl text-xs font-bold hover:bg-slate-700 hover:text-slate-200 transition-all">
                            Batal
                        </button>
                        <button type="submit" 
                                :class="isEdit ? 'bg-blue-600/20 text-blue-400 border-blue-500/50' : 'bg-emerald-600/20 text-emerald-400 border-emerald-500/50'"
                                class="px-6 py-2 border rounded-xl text-xs font-bold hover:opacity-80 transition-all">
                            <span x-text="isEdit ? 'Simpan Perubahan' : 'Tanam Memori'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- End Modal Form -->

</body>
</html>