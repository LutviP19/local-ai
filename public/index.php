<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LocalAI-Assistant Pro</title>
    <script src="./assets/js/htmx.min.js"></script>
    <script defer src="./assets/js/alpine.min.js"></script>
    <script src="./assets/js/tailwindcss.js"></script>

    <script src="./assets/js/marked.min.js"></script>
    <link rel="stylesheet" href="./assets/css/github-dark.min.css">
    <script src="./assets/js/highlight.min.js"></script>
    <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body class="bg-slate-900 text-slate-100 font-sans antialiased">

    <div class="max-w-4xl mx-auto p-6" 
         x-data="{ 
            prompt: '', 
            result: '', 
            thought: '', // untuk menyimpan alur pikir 
            isExpanded: false, // mengontrol buka-tutup panel pemikiran
            selectedModel: 'default-chat', 
            isSwitching: false,
            lastModel: 'default-chat',
            progress: 0,
            isLoading: false, // Proses memuat jawaban
            toast: { show: false, message: '', type: 'success' }, // Toast
            showToTop: false,
            showTemplates: false,
            init() {
                // Deteksi scroll layar
                window.addEventListener('scroll', () => {
                    this.showToTop = window.pageYOffset > 400;
                });
            },
            renderMarkdown(rawText) {
                if (!rawText) return '<span class=\'text-slate-500 italic\'>Menunggu input...</span>';
                
                // Konfigurasi agar Markdown merender baris baru dengan benar
                marked.setOptions({
                    breaks: true, // Mengubah \n menjadi <br>
                    gfm: true,    // Mengaktifkan GitHub Flavored Markdown (untuk list & tabel)
                    headerIds: false,
                    mangle: false
                });

                this.$nextTick(() => {
                    document.querySelectorAll('pre code').forEach((block) => {
                        if (!block.dataset.highlighted) {
                            hljs.highlightElement(block);
                            block.dataset.highlighted = 'yes';
                        }
                    });
                });

                return marked.parse(rawText);
            },
            setTemplate(type) {
                const templates = {
                    greeting: 'Halo, apa kabar?',
                    explain: 'Tolong jelaskan cara kerja query SQL berikut dan beri saran optimasi: \n\n[PASTE QUERY ANDA DISINI]',
                    fix: 'Query SQL ini error atau lambat, tolong perbaiki dan jelaskan masalahnya: \n\n[PASTE QUERY ANDA DISINI]',
                    schema: 'Buatkan skema tabel MySQL untuk keperluan [NAMA FITUR] dengan relasi yang tepat.',

                    // Tambahan Baru
                    security: 'Audit query/skema berikut dari sisi keamanan (SQL Injection, Privilese) dan berikan rekomendasi pengamanannya: \n\n[PASTE DISINI]',
                    index: 'Analisis query berikut dan sarankan pembuatan INDEX atau COMPOSITE INDEX yang paling efektif untuk mempercepat eksekusi: \n\n[PASTE QUERY]',
                    dummy: 'Buatkan SQL script untuk memasukkan 50 data dummy yang realistis ke dalam tabel berikut: \n\n[PASTE STRUKTUR TABEL]',
                    migrate: 'Konversikan logika bisnis berikut menjadi STORED PROCEDURE atau TRIGGER di MySQL/MariaDB: \n\n[JELASKAN LOGIKA]',

                    // Tambahan Template PHP
                    php_pdo: 'Buatkan class PHP Singleton untuk koneksi database menggunakan PDO (MySQL) yang aman dengan error handling try-catch.',
                    php_crud: 'Buatkan fungsi PHP untuk melakukan operasi CRUD (Create, Read, Update, Delete) pada tabel berikut menggunakan Prepared Statements: \n\n[PASTE STRUKTUR TABEL]',
                    php_api: 'Buatkan script PHP (API Endpoint) yang menerima JSON POST, melakukan validasi input, dan menyimpan data ke database.',
                    php_ollama: 'Berikan contoh cara memanggil class OllamaDBA dari file PHP lain dan cara menangani respon string-nya.'
                };
                
                this.prompt = templates[type];
                
                // Gunakan $nextTick agar DOM sempat update sebelum menghitung scrollHeight
                this.$nextTick(() => {
                    const ta = document.querySelector('textarea');
                    if(ta) {
                        ta.style.height = 'auto'; 
                        ta.style.height = ta.scrollHeight + 'px'; // Melebar otomatis sesuai template
                        ta.focus(); // Langsung siap ngetik/paste
                        
                        // Scroll ke posisi kursor agar user tidak bingung jika template panjang
                        ta.setSelectionRange(ta.value.length, ta.value.length);
                    }
                });
            },
            startProgress() {
                this.progress = 0;
                let interval = setInterval(() => {
                    if (this.progress < 85) {
                        this.progress += 10; // Cepat di awal (Loading file)
                    } else if (this.progress < 100) {
                        this.progress += 2.5; // Melambat di akhir (Inisialisasi RAM)
                    }
                    
                    if (!this.isSwitching && this.result !== 'Sedang menganalisa...') {
                        clearInterval(interval);
                        this.progress = 100;
                    }
                }, 500); // Total simulasi sekitar 8-10 detik
            },
            playSound() {
                const context = new (window.AudioContext || window.webkitAudioContext)();
                
                const playTone = (freq, start, duration) => {
                    const osc = context.createOscillator();
                    const gain = context.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, context.currentTime + start);
                    gain.gain.setValueAtTime(0.3, context.currentTime + start);
                    gain.gain.exponentialRampToValueAtTime(0.01, context.currentTime + start + duration);
                    osc.connect(gain);
                    gain.connect(context.destination);
                    osc.start(context.currentTime + start);
                    osc.stop(context.currentTime + start + duration);
                };

                // Nada pertama (rendah) lalu nada kedua (tinggi) - Efek Ding!
                playTone(660, 0, 0.1); 
                playTone(880, 0.1, 0.2);
            },
            isSpeaking: false,
            speechInstance: null,
            speak(text) {
                // Jika sedang bicara, maka berhenti (Toggle)
                if (this.isSpeaking) {
                    window.speechSynthesis.cancel();
                    this.isSpeaking = false;
                    return;
                }

                // Bersihkan teks dari format Markdown (seperti ** dan ```)
                const cleanText = text.replace(/[*#`]|```[\s\S]*?```/g, '');
                
                this.speechInstance = new SpeechSynthesisUtterance(cleanText);
                
                // Set Bahasa (id-ID untuk Bahasa Indonesia)
                this.speechInstance.lang = 'id-ID';
                this.speechInstance.rate = 1.0; // Kecepatan bicara

                this.speechInstance.onend = () => { this.isSpeaking = false; };
                this.speechInstance.onerror = () => { this.isSpeaking = false; };

                this.isSpeaking = true;
                window.speechSynthesis.speak(this.speechInstance);
            },
            showToast(msg, type = 'success') {
                this.toast.show = true;
                this.toast.message = msg;
                this.toast.type = type;
                setTimeout(() => { this.toast.show = false }, 3000);
            },
            saveToLog() {
                if (!this.result || this.result === 'Sedang menganalisa...') return;

                const payload = new FormData();
                payload.append('model', this.selectedModel);
                payload.append('prompt', this.prompt);
                payload.append('result', this.result);

                fetch('api.php?action=save_log', {
                    method: 'POST',
                    body: payload
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.showToast('Log berhasil disimpan di server: ' + data.file);
                    } else {
                        this.showToast('Gagal simpan log', 'error');
                    }
                })
                .catch(err => console.error('Error:', err));
            }
         }"
         x-init="$watch('selectedModel', value => {
            if (value !== lastModel) {
                isSwitching = true;
                lastModel = value;
                startProgress();
                // Notifikasi hilang otomatis setelah progres selesai atau klik run
                setTimeout(() => { isSwitching = false }, 8000);
            }
         })">

         <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 md:mb-10 border-b border-slate-800 pb-6 gap-6">
            <div class="flex flex-col w-full md:w-auto">
                <h1 class="text-xl md:text-2xl font-bold bg-gradient-to-r from-blue-400 to-emerald-400 bg-clip-text text-transparent">
                    LocalAI-Assistant Pro
                </h1>
                <p class="text-slate-400 text-xs md:text-sm">i5 Gen 3 Node | Local AI Engine</p>
            </div>

            <div class="flex items-center justify-between w-full md:w-auto md:gap-6 border-t md:border-t-0 border-slate-800 pt-4 md:pt-0">
                
                <div x-data="{ isOffline: false }" class="flex items-center gap-2">

                    <template x-if="isOffline">
                        <div class="flex flex-col items-start md:items-end border-r border-slate-700 pr-2 md:pr-4">
                            <div class="flex items-center gap-2">
                                <span class="text-red-500 text-[10px] font-bold uppercase">Server Offline</span>
                                <button @click="isOffline = false; htmx.trigger('#phpStatusBox', 'load')" 
                                        class="text-[9px] bg-slate-700 px-1 rounded hover:bg-slate-600 text-slate-300">
                                    Retry
                                </button>
                            </div>
                        </div>
                    </template>

                    <div id="phpStatusBox"
                         hx-get="api.php?action=status" 
                         hx-trigger="load, every 15s" 
                         :hx-vals="JSON.stringify({ current_ui_model: selectedModel })"
                         hx-swap="innerHTML"
                         @htmx:before-request="
                            if (isOffline) {
                                $event.preventDefault();
                                return;
                            }                            
                            
                            // 2. BERSIHKAN KONTEN sebelum fetch dimulai
                            if(isLoading) {
                                $el.innerHTML = '<div class=\'text-slate-600 text-xs animate-pulse\'>Thinking...</div>';
                            } else {
                                $el.innerHTML = '<div class=\'text-slate-600 text-xs animate-pulse\'>Reconnecting...</div>';
                            }
                         "
                         @htmx:send-error="isOffline = true"
                         @htmx:response-error="isOffline = true"
                         @htmx:after-request="if($event.detail.successful) isOffline = false"
                         class="flex flex-col items-start md:items-end border-r border-slate-700 pr-2 md:pr-4">

                         <div x-show="!isOffline" class="text-slate-600 text-xs animate-pulse">Connecting...</div>
                    </div>
                </div>

                <div class="relative ml-auto md:ml-0" x-data="{ open: false }">
                    <button @click="open = !open" 
                            @click.outside="open = false"
                            class="bg-slate-900/50 hover:bg-slate-800 p-2 md:p-2.5 rounded-xl border border-slate-800 transition-all duration-300 group shadow-inner hover:border-blue-500/50 hover:shadow-[0_0_15px_rgba(59,130,246,0.2)]"
                            title="AI Model Hub">
                        
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" 
                                 class="h-5 w-5 text-slate-400 transition-all duration-300 group-hover:text-blue-500 group-hover:rotate-90" 
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 md:hidden group-hover:text-blue-400">Models</span>
                        </div>
                    </button>

                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         class="absolute right-0 mt-3 w-64 md:w-72 bg-slate-800 border border-slate-700 rounded-xl shadow-2xl z-[100] overflow-hidden shadow-blue-900/20"
                         style="display: none;">
                        
                        <div class="bg-slate-700/50 p-3 font-bold border-b border-slate-700 text-[9px] md:text-[10px] flex justify-between items-center text-slate-300 uppercase tracking-[0.2em]">
                            Installed Models
                            <button hx-get="api.php?action=models" hx-target="#model-list" class="text-blue-400 hover:rotate-180 transition-transform duration-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                        
                        <ul id="model-list" hx-get="api.php?action=models" hx-trigger="load" class="text-sm max-h-[300px] md:max-h-[350px] overflow-y-auto custom-scrollbar">
                            <li class="p-4 text-slate-500 text-center animate-pulse">Scanning local models...</li>
                        </ul>
                        
                        <div class="p-2 bg-slate-900/50 border-t border-slate-700 text-center">
                            <p class="text-[9px] text-slate-500 uppercase tracking-tighter text-center">Ollama Engine v0.5.x</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-6xl mx-auto px-0 md:px-4">

            <div class="w-full space-y-4 md:space-y-6">
                <div class="bg-slate-800 p-4 md:p-6 rounded-none md:rounded-xl border-y md:border border-slate-700 shadow-xl">
                    <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        AI Assistant
                    </h2>

                    <div class="mt-2 mb-2 border-t border-slate-800">
                        <button @click="showTemplates = !showTemplates" 
                                class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 hover:text-blue-400 transition-colors group">
                            <svg xmlns="http://www.w3.org/2000/svg" 
                                 class="h-3 w-3 transition-transform duration-300" 
                                 :class="showTemplates ? 'rotate-180' : ''" 
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
                            </svg>
                            <span x-text="showTemplates ? 'Hide Quick Templates' : 'Show Quick Templates'"></span>
                        </button>

                        <div x-show="showTemplates" 
                             x-collapse
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 transform -translate-y-2"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3">
                            <button @click="setTemplate('greeting')" 
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-blue-400 border border-blue-500/20 rounded-lg hover:bg-blue-500/10 hover:border-blue-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Greeting
                            </button> 
                            <button @click="setTemplate('explain')" 
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-blue-400 border border-blue-500/20 rounded-lg hover:bg-blue-500/10 hover:border-blue-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Explain Query
                            </button>

                            <button @click="setTemplate('fix')" 
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-emerald-400 border border-emerald-500/20 rounded-lg hover:bg-emerald-500/10 hover:border-emerald-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                                Fix My SQL
                            </button>

                            <button @click="setTemplate('schema')" 
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-purple-400 border border-purple-500/20 rounded-lg hover:bg-purple-500/10 hover:border-purple-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                </svg>
                                Design Schema
                            </button>

                            <button @click="setTemplate('security')" 
                                    class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-red-400 border border-red-500/20 rounded-lg hover:bg-red-500/10 hover:border-red-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Security Audit
                            </button>

                            <button @click="setTemplate('index')" 
                                    class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-yellow-400 border border-yellow-500/20 rounded-lg hover:bg-yellow-500/10 hover:border-yellow-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                Optimize Index
                            </button>

                            <button @click="setTemplate('dummy')" 
                                    class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-orange-400 border border-orange-500/20 rounded-lg hover:bg-orange-500/10 hover:border-orange-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Generate Data
                            </button>

                            <button @click="setTemplate('migrate')" 
                                    class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-cyan-400 border border-cyan-500/20 rounded-lg hover:bg-cyan-500/10 hover:border-cyan-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.675.337a4 4 0 01-2.547.314l-4.59-1.211M10 3L7 21m8-3v5m0-5h5a2 2 0 002-2V8a2 2 0 00-2-2h-5" />
                                </svg>
                                Stored Proc
                            </button>

                            <button @click="setTemplate('php_pdo')" 
                                    class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-indigo-400 border border-indigo-500/20 rounded-lg hover:bg-indigo-500/10 hover:border-indigo-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                PHP PDO Connection
                            </button>

                            <button @click="setTemplate('php_crud')" 
                                    class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-violet-400 border border-violet-500/20 rounded-lg hover:bg-violet-500/10 hover:border-violet-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Generate CRUD
                            </button>

                            <button @click="setTemplate('php_api')" 
                                    class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-purple-400 border border-purple-500/20 rounded-lg hover:bg-purple-500/10 hover:border-purple-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                API Endpoint
                            </button>

                            <button @click="setTemplate('php_ollama')" 
                                    class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider bg-slate-800/50 text-fuchsia-400 border border-fuchsia-500/20 rounded-lg hover:bg-fuchsia-500/10 hover:border-fuchsia-500/50 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Ollama Integration
                            </button>
                        </div>
                    </div>
                    
                    <div class="relative group mt-1">

                        <div class="relative w-full">
                            <textarea 
                                x-model="prompt"
                                @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                class="w-full bg-slate-900 border border-slate-700 rounded-lg p-4 pr-10 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none text-blue-300 transition-all overflow-hidden resize-none"
                                style="min-height: 3.5rem; height: auto;"
                                rows="2"
                                placeholder="Contoh: Siapa pembuat super komputer pertama..."></textarea>
                            
                            <div class="absolute bottom-2 right-3 text-[10px] text-slate-600 font-mono" x-show="prompt.length > 0">
                                <span x-text="prompt.length"></span> chars
                            </div>
                        </div>
                        
                        <button 
                            x-show="prompt.length > 0 && !isLoading && !isSwitching"
                            @click="
                                prompt = ''; 
                                let ta = $el.parentElement.querySelector('textarea');
                                ta.style.height = 'auto'; // Reset tinggi textarea
                                ta.focus();
                            " 
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-90"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute top-3 right-3 text-slate-500 hover:text-red-400 p-1 rounded-md hover:bg-red-500/10 transition-all z-10"
                            title="Bersihkan teks">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mb-4" x-data>
                        <label class="text-xs text-slate-500 uppercase font-bold mb-2 block text-slate-400">Pilih Model AI:</label>
                        <select 
                            x-model="selectedModel" 
                            name="model"
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2 text-sm text-blue-300 focus:ring-2 focus:ring-blue-400 outline-none cursor-pointer"
                            hx-get="api.php?action=model_options" 
                            hx-trigger="load"
                            hx-target="this"
                            hx-swap="innerHTML">
                            <option>Loading models...</option>
                        </select>
                        <template x-if="isSwitching">
                            <div x-transition class="mb-6 mt-4 p-4 bg-slate-800 border border-blue-500/30 rounded-xl shadow-lg">
                                <div class="flex justify-between items-center mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex h-3 w-3">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                                        </div>
                                        <span class="text-sm font-medium text-blue-100">
                                            Memuat: <span class="text-blue-400 font-mono" x-text="selectedModel"></span>
                                        </span>
                                    </div>
                                    <span class="text-xs font-mono text-blue-400" x-text="Math.round(progress) + '%'"></span>
                                </div>
                                
                                <div class="w-full bg-slate-900 rounded-full h-2 overflow-hidden border border-slate-700">
                                    <div class="bg-gradient-to-r from-blue-600 to-cyan-400 h-full transition-all duration-500 ease-out shadow-[0_0_10px_rgba(59,130,246,0.5)]"
                                         :style="`width: ${progress}%`" 
                                         style="min-width: 2%">
                                    </div>
                                </div>
                                
                                <p class="mt-2 text-[10px] text-slate-500 italic text-center">
                                    Mohon tunggu, Sistem sedang mengganti Model AI...
                                </p>
                            </div>
                        </template>
                    </div>

                    <button 
                        @click="isLoading = true; // Set loading jadi true
                                isSwitching = false; // Matikan notifikasi switch jika masih ada
                                progress = 0; // Reset progress
                                result = 'Sedang menganalisa...'; 
                                thought = ''; // untuk menyimpan alur pikir 
                                isExpanded = false; // mengontrol buka-tutup panel pemikiran 
                                 // Kita ambil nilai langsung dari element select jika x-model belum sinkron
                                 let currentModel = document.getElementsByName('model')[0].value;

                                 // Menggunakan FormData untuk POST
                                let formData = new FormData();
                                formData.append('model', currentModel);
                                formData.append('q', prompt);

                                fetch('api.php?action=ask', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(res => res.text())
                                .then(data => {
                                    // 1. Coba deteksi format Tag XML <think>...</think>
                                    let thinkMatch = data.match(/<think>([\s\S]*?)<\/think>/);
                                    let cleanResult = data;
                                    let extractedThought = '';
                                                                    
                                    if (thinkMatch) {
                                        extractedThought = thinkMatch[1].trim();
                                        cleanResult = data.replace(/<think>[\s\S]*?<\/think>/, '').trim();
                                    } 
                                    // 2. Jika tidak ada tag XML, coba deteksi format teks Thinking... ...done thinking.
                                    else {
                                        const textThinkMatch = data.match(/Thinking\.\.\.([\s\S]*?)\.\.\.done thinking\./i);
                                        if (textThinkMatch) {
                                            extractedThought = textThinkMatch[1].trim();
                                            cleanResult = data.replace(/Thinking\.\.\.[\s\S]*?\.\.\.done thinking\./i, '').trim();
                                        }
                                    }

                                    // Masukkan ke state Alpine.js
                                    thought = extractedThought;
                                    result = cleanResult;

                                    //result = data;
                                    progress = 100;
                                    playSound(); 
                                })
                                .catch(err => {
                                    result = 'Terjadi kesalahan koneksi...';
                                    showToast('Gagal memanggil AI', 'error');
                                })
                                .finally(() => { isLoading = false })"
                        :disabled="isLoading || isSwitching || !prompt" 
                        :class="(isLoading || isSwitching) ? 'opacity-50 cursor-not-allowed bg-slate-600' : 'bg-blue-600 hover:bg-blue-500'"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded-lg font-medium transition flex items-center gap-2 shadow-lg shadow-blue-900/20 w-full justify-center">

                        <template x-if="isLoading">
                            <svg xmlns="http://www.w3.org/2000/svg" 
                                 :class="isLoading ? 'animate-pulse scale-110' : ''" 
                                 class="h-5 w-5 transition-all duration-300" 
                                 viewBox="0 0 24 24" 
                                 fill="none"
                                 stroke-width="2"
                                 stroke-linecap="round" 
                                 stroke-linejoin="round">
                                
                                <defs>
                                    <linearGradient id="aiGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#60a5fa;stop-opacity:1" /> <stop offset="100%" style="stop-color:#34d399;stop-opacity:1" /> </linearGradient>
                                </defs>

                                <path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" stroke="url(#aiGradient)" />
                            </svg>
                        </template>
                        
                        <svg xmlns="http://www.w3.org/2000/svg" 
                             :class="isLoading ? 'hidden' : 'block'" 
                             class="h-5 w-5 transition-all duration-300" 
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                        <span x-text="isSwitching ? 'Menyiapkan Agen AI...' : (isLoading ? 'Sedang Menganalisa...' : 'Tanya AI')"></span>
                    </button>

                    <!-- <button 
                        @click="
                            let fakeData = '<think>\n1. Menganalisis permintaan koding dari user.\n2. Merancang solusi menggunakan PHP Native.\n</think>\nIni adalah jawaban simulasi! Pemisahan teks berhasil.';
                            
                            // Perbaikan Regex agar tidak error SyntaxError
                            let match = fakeData.match(/<think>([\s\S]*?)<\/think>/);
                            
                            if (match) {
                                thought = match[1].trim();
                                result = fakeData.replace(/<think>[\s\S]*?<\/think>/, '').trim();
                            }
                            
                            isExpanded = true;
                            showToast('Simulasi Berhasil!');
                            playSound();
                        "
                        class="mt-2 text-[10px] text-slate-500 hover:text-blue-400 underline decoration-dotted uppercase tracking-widest transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                        Test UI Response
                    </button> -->
                </div>

                <div class="bg-black/40 rounded-xl p-6 border border-slate-800 min-h-[100px] relative" 
                     x-show="result" 
                     x-transition>
                    
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Analysis Result:</h3>

                        <div class="flex gap-2">
                            <button x-show="result.length > 0 && result !== 'Sedang menganalisa...' && !isLoading"
                                @click="saveToLog()"
                                x-transition
                                class="text-xs bg-slate-700 hover:bg-blue-900/50 hover:text-blue-300 px-2 py-1 rounded text-slate-300 transition flex items-center gap-1 border border-transparent hover:border-blue-500/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                </svg>
                                Save Log
                            </button>

                            <button x-show="result.length > 0 && result !== 'Sedang menganalisa...'" 
                                @click="navigator.clipboard.writeText(result); showToast('Copied to Clipboard!')" 
                                class="text-xs bg-slate-700 hover:bg-slate-600 px-2 py-1 rounded text-slate-300 transition">
                                Copy
                            </button>

                            <button x-show="result.length > 0 && result !== 'Sedang menganalisa...'" 
                                    @click="speak(result)" 
                                    :title="isSpeaking ? 'Stop Reading' : 'Read Aloud'"
                                    class="flex items-center justify-center p-2 rounded-lg border transition-all duration-300 relative group"
                                    :class="isSpeaking ? 'bg-red-500/20 border-red-500/50 text-red-400 shadow-[0_0_10px_rgba(239,68,68,0.2)]' : 'bg-slate-700/50 border-slate-600 text-slate-400 hover:border-emerald-500/50 hover:text-emerald-400'">
                                
                                <template x-if="!isSpeaking">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                    </svg>
                                </template>
                                
                                <template x-if="isSpeaking">
                                    <div class="flex items-center gap-0.5">
                                        <span class="w-1 h-3 bg-red-400 animate-[bounce_1s_infinite_0.1s]"></span>
                                        <span class="w-1 h-4 bg-red-400 animate-[bounce_1s_infinite_0.2s]"></span>
                                        <span class="w-1 h-3 bg-red-400 animate-[bounce_1s_infinite_0.3s]"></span>
                                    </div>
                                </template>

                                <span class="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 bg-slate-900 text-[9px] rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap border border-slate-700 uppercase tracking-tighter"
                                      x-text="isSpeaking ? 'Stop' : 'Read'">
                                </span>
                            </button>
                        </div>
                    </div>

                    <template x-if="result === 'Sedang menganalisa...'">
                        <div class="flex items-center gap-3 text-blue-400 animate-pulse">
                            <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>AI merespon perintah Anda...</span>
                        </div>
                    </template>
                    
                    <div class="mt-4 prose prose-invert prose-emerald max-w-none border-t border-slate-700/50 pt-4"
                         x-html="renderMarkdown(result)"
                         x-cloak>
                    </div>

                    <div x-show="thought" class="mt-4 border border-amber-900/30 rounded-lg overflow-hidden transition-all duration-300" x-transition>
                        <div class="flex justify-between items-center bg-amber-950/20 px-4 py-2 border-b border-amber-900/20">
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <h3 class="text-[10px] font-bold uppercase tracking-widest text-amber-500/80">Think Flow - AI</h3>
                            </div>
                            
                            <button 
                                @click="isExpanded = !isExpanded"
                                class="text-[10px] font-bold text-amber-500/60 hover:text-amber-400 flex items-center gap-1 transition-colors uppercase">
                                <span x-text="isExpanded ? 'Hide Details' : 'Show Details'"></span>
                                <svg class="w-3 h-3 transition-transform duration-300" :class="isExpanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        <pre 
                            x-show="isExpanded"
                            x-collapse
                            class="p-4 text-[11px] font-mono text-amber-200/60 bg-slate-900/50 overflow-x-auto whitespace-pre-wrap leading-relaxed max-h-[400px] overflow-y-auto"
                            x-text="thought"></pre>
                    </div>

                </div>
            </div>
        </div>


        <!-- Toast -->        
        <div class="fixed top-5 right-5 z-[9999]">
            <div x-show="toast.show" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 :class="toast.type === 'success' ? 'bg-emerald-600 shadow-emerald-900/20' : 'bg-rose-600 shadow-rose-900/20'"
                 class="px-5 py-3 rounded-xl shadow-2xl text-white flex items-center gap-3 min-w-[250px] border border-white/10"
                 style="display: none;"> 
                 
                 <template x-if="toast.type === 'success'">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>

                <template x-if="toast.type === 'error'">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>

                <span class="text-sm font-semibold" x-text="toast.message"></span>
            </div>
        </div>
        <!-- End Toast -->

        <button 
            x-show="showToTop"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-10"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-10"
            @click="window.scrollTo({top: 0, behavior: 'smooth'})"
            class="fixed bottom-20 right-6 p-3 rounded-xl bg-slate-900/80 border border-blue-500/30 text-blue-400 shadow-[0_0_15px_rgba(59,130,246,0.2)] hover:shadow-[0_0_20px_rgba(59,130,246,0.4)] hover:bg-blue-600 hover:text-white transition-all duration-300 z-40 group"
            title="Kembali ke atas">
            
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 group-hover:-translate-y-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11l7-7 7 7M5 19l7-7 7 7" />
            </svg>
        </button>
    </div>

</body>
</html>