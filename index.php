<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 text-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Billing & Cloud Hub (AWS, Groq, OpenRouter, GCP)</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        aws: { orange: '#FF9900', navy: '#232F3E', dark: '#131A22' },
                        gcp: { blue: '#4285F4', red: '#EA4335', yellow: '#FBBC05', green: '#34A853' }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            color-scheme: light;
            --aws-navy: #232f3e;
            --aws-orange: #ff9900;
            --aws-border: #d5dbdb;
            --aws-muted: #545b64;
        }
        html, body { background: #f2f3f3; color: #16191f; }
        body { min-height: 100vh; }
        header { background: #fff !important; border-color: var(--aws-border) !important; }
        header > div {
            height: auto !important;
            min-height: 4rem;
            padding-top: .65rem;
            padding-bottom: .65rem;
            gap: .75rem;
            flex-wrap: wrap;
        }
        header h1 { color: var(--aws-navy); }
        .bg-slate-950, .bg-slate-950\/80 { background-color: #fff !important; }
        .bg-slate-900, .bg-slate-900\/50, .bg-slate-900\/60, .bg-slate-900\/70 { background-color: #f8f9fa !important; }
        .bg-slate-800, .bg-slate-800\/50 { background-color: #f1f3f3 !important; }
        .text-slate-100, .text-slate-200, .text-white { color: #16191f !important; }
        .text-slate-300 { color: #414d5c !important; }
        .text-slate-400 { color: #545b64 !important; }
        .text-slate-500 { color: #687078 !important; }
        .border-slate-800, .border-slate-700 { border-color: var(--aws-border) !important; }
        .divide-slate-800 > :not([hidden]) ~ :not([hidden]) { border-color: var(--aws-border) !important; }
        .text-amber-400, .text-amber-300 { color: #9c3d10 !important; }
        .text-indigo-400, .text-sky-400 { color: #0972d3 !important; }
        .text-emerald-400, .text-emerald-300 { color: #067d62 !important; }
        .text-rose-400, .text-rose-300 { color: #b31319 !important; }
        .nav-tab {
            color: #414d5c !important;
            border: 0 !important;
            border-bottom: 2px solid transparent !important;
            border-radius: 0 !important;
            white-space: nowrap;
        }
        .nav-tab:hover { background: #f8f9fa !important; color: var(--aws-navy) !important; }
        .nav-tab.active {
            color: var(--aws-navy) !important;
            border-bottom-color: var(--aws-orange) !important;
            background: transparent !important;
        }
        #tab-calculator > div:first-child {
            background: #fff !important;
            border: 1px solid var(--aws-border);
            box-shadow: 0 1px 3px rgb(0 0 0 / 8%);
        }
        #tab-budget-guard > div:first-child {
            background: #fff8f5 !important;
            border-color: #f3c1b9 !important;
            box-shadow: none !important;
        }
        #tab-docker-secrets .rounded-xl, #tab-docker-secrets .rounded-2xl {
            border-radius: .5rem !important;
        }
        section .rounded-2xl { border-radius: .65rem !important; }
        input, select, textarea {
            color: #16191f !important;
            background-color: #fff !important;
            border-color: #879596 !important;
        }
        input::placeholder, textarea::placeholder { color: #687078 !important; }
        input[type="range"] { accent-color: var(--aws-orange); }
        .shadow-xl, .shadow-2xl { box-shadow: 0 1px 3px rgb(0 0 0 / 10%) !important; }
        #tab-calculator table thead { background: #f8f9fa; color: #414d5c; }
        #budget-cli-code { color: #067d62 !important; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f2f3f3; }
        ::-webkit-scrollbar-thumb { background: #aab7b8; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #879596; }
        @media (max-width: 640px) {
            header > div { align-items: flex-start; }
            header nav { width: 100%; max-width: 100%; overflow-x: auto; }
            main { padding: 1rem !important; }
        }
    </style>
</head>
<body class="h-full flex flex-col font-sans selection:bg-amber-500 selection:text-black">

    <!-- Top Navigation Bar -->
    <header class="bg-slate-950/80 backdrop-blur border-b border-slate-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500 flex items-center justify-center font-bold text-slate-950 text-lg">
                    <i data-lucide="layers" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="font-bold text-base sm:text-lg flex items-center gap-2">
                        AI Billing & Cloud Hub
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Multi-Cloud
                        </span>
                    </h1>
                    <p class="text-xs text-slate-400 hidden sm:block">AWS Bedrock ($200) &bull; GCP ($300) &bull; Groq &bull; OpenRouter (Qwen)</p>
                </div>
            </div>

            <!-- Tab Buttons -->
            <nav class="flex items-center gap-1 sm:gap-2">
                <button onclick="switchTab('calculator')" id="tab-btn-calculator" class="nav-tab active flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition bg-slate-800 text-amber-400 border border-slate-700">
                    <i data-lucide="calculator" class="w-4 h-4"></i>
                    <span class="hidden md:inline">Kalkulator Token</span>
                </button>
                <button onclick="switchTab('chat')" id="tab-btn-chat" class="nav-tab flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-slate-800/50">
                    <i data-lucide="message-square" class="w-4 h-4"></i>
                    <span class="hidden md:inline">Qwen Playground</span>
                </button>
                <button onclick="switchTab('budget-guard')" id="tab-btn-budget-guard" class="nav-tab flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-slate-800/50">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span class="hidden md:inline">Budget Guard (AWS/GCP)</span>
                </button>
                <button onclick="switchTab('docker-secrets')" id="tab-btn-docker-secrets" class="nav-tab flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-slate-800/50">
                    <i data-lucide="container" class="w-4 h-4"></i>
                    <span class="hidden md:inline">Docker & Secrets</span>
                </button>
                <a href="billing/" class="nav-tab flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition text-slate-400 hover:text-slate-200 hover:bg-slate-800/50">
                    <i data-lucide="wallet" class="w-4 h-4"></i>
                    <span class="hidden md:inline">AWS Billing</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">

        <!-- ==================== TAB 1: KALKULATOR BIAYA ==================== -->
        <section id="tab-calculator" class="space-y-6">
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl relative overflow-hidden">
                <div class="relative z-10 max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-xs font-medium mb-3">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                        Simulasi Token & Runway Saldo
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-2">Estimasi Token & Biaya Multi-Provider</h2>
                    <p class="text-slate-300 text-sm leading-relaxed">
                        Bandingkan konsumsi kredit untuk model <strong>Qwen (2.5 32B Coder, 235B Thinking, QwQ)</strong> di OpenRouter / Groq, <strong>AWS Bedrock ($200)</strong>, serta <strong>Google Cloud ($300)</strong>.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Controls Column (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-slate-950 p-6 rounded-2xl border border-slate-800 space-y-5">
                        <h3 class="text-base font-semibold flex items-center gap-2 text-slate-200">
                            <i data-lucide="sliders" class="w-4 h-4 text-amber-400"></i>
                            Parameter Penggunaan
                        </h3>

                        <!-- Model Selection -->
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Pilih Model & Provider</label>
                            <select id="calc-model" onchange="calculateCost()" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                <optgroup label="Qwen Series (OpenRouter / Groq)">
                                    <option value="qwen25_coder_32b">Qwen 2.5 Coder 32B ($0.66 in / $1.00 out)</option>
                                    <option value="qwen3_235b_thinking">Qwen3 235B A22B Thinking ($0.45 in / $3.50 out)</option>
                                    <option value="qwen_plus">Qwen-Plus 131K ($0.26 in / $0.78 out)</option>
                                    <option value="qwen3_vl_30b">Qwen3 VL 30B ($0.29 in / $1.00 out)</option>
                                    <option value="qwen_25_72b">Qwen 2.5 72B Instruct ($0.35 in / $0.40 out)</option>
                                </optgroup>
                                <optgroup label="Amazon Bedrock ($200 Credit)">
                                    <option value="bedrock_qwen3_next">Qwen3-Next 80B A3B (tarif sesuai region/service tier)</option>
                                    <option value="bedrock_claude35">Claude 3.5 Sonnet Bedrock ($3.00 in / $15.00 out)</option>
                                    <option value="bedrock_llama33_70b">Llama 3.3 70B Bedrock ($0.72 in / $0.72 out)</option>
                                </optgroup>
                                <optgroup label="Google Cloud / Gemini ($300 Credit)">
                                    <option value="gcp_gemini25_flash">Gemini 2.5 Flash ($0.075 in / $0.30 out)</option>
                                    <option value="gcp_gemini25_pro">Gemini 2.5 Pro ($1.25 in / $5.00 out)</option>
                                </optgroup>
                                <option value="custom">Model Kustom...</option>
                            </select>
                        </div>

                        <!-- Custom Pricing inputs -->
                        <div id="custom-pricing-box" class="hidden grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                            <p id="custom-pricing-note" class="hidden col-span-2 text-[11px] text-amber-300">
                                Isi tarif input/output resmi per 1 juta token dari
                                <a href="https://aws.amazon.com/bedrock/pricing/" target="_blank" rel="noopener noreferrer" class="underline">AWS Bedrock Pricing</a>
                                untuk region dan service tier akun Anda.
                            </p>
                            <div>
                                <label class="text-xs text-slate-400">Input ($/1M token)</label>
                                <input type="number" min="0" step="0.001" id="custom-in-price" value="0.50" oninput="calculateCost()" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-sm mt-1">
                            </div>
                            <div>
                                <label class="text-xs text-slate-400">Output ($/1M token)</label>
                                <input type="number" min="0" step="0.001" id="custom-out-price" value="1.50" oninput="calculateCost()" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-sm mt-1">
                            </div>
                        </div>

                        <!-- Starting Credit Balance -->
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-400">Saldo Kredit (USD)</span>
                                <span id="val-credit" class="font-bold text-amber-400">$200</span>
                            </div>
                            <input type="range" id="input-credit" min="10" max="1000" step="10" value="200" oninput="updateSliders(); calculateCost();" class="w-full accent-amber-500 h-1.5 bg-slate-800 rounded-lg cursor-pointer">
                            <div class="flex gap-2 mt-1.5">
                                <button type="button" onclick="setPresetCredit(200)" class="text-[10px] px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300">Preset AWS ($200)</button>
                                <button type="button" onclick="setPresetCredit(300)" class="text-[10px] px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300">Preset GCP ($300)</button>
                                <button type="button" onclick="setPresetCredit(20)" class="text-[10px] px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300">Preset OpenRouter ($20)</button>
                            </div>
                        </div>

                        <!-- Queries per day -->
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-400">Jumlah Query per Hari</span>
                                <span id="val-queries" class="font-bold text-indigo-400">50 query</span>
                            </div>
                            <input type="range" id="input-queries" min="1" max="1000" step="5" value="50" oninput="updateSliders(); calculateCost();" class="w-full accent-indigo-500 h-1.5 bg-slate-800 rounded-lg cursor-pointer">
                        </div>

                        <!-- Average Input Tokens -->
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-400">Rata-rata Input Token / Query</span>
                                <span id="val-input-tokens" class="font-bold text-sky-400">500 tokens</span>
                            </div>
                            <input type="range" id="input-tokens" min="50" max="10000" step="50" value="500" oninput="updateSliders(); calculateCost();" class="w-full accent-sky-500 h-1.5 bg-slate-800 rounded-lg cursor-pointer">
                        </div>

                        <!-- Average Output Tokens -->
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-400">Rata-rata Output Token / Query</span>
                                <span id="val-output-tokens" class="font-bold text-emerald-400">800 tokens</span>
                            </div>
                            <input type="range" id="output-tokens" min="50" max="8000" step="50" value="800" oninput="updateSliders(); calculateCost();" class="w-full accent-emerald-500 h-1.5 bg-slate-800 rounded-lg cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Results & Chart Column (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                            <p class="text-xs text-slate-400">Biaya / Hari</p>
                            <p id="res-cost-day" class="text-xl font-bold text-amber-400 mt-1">$0.00</p>
                            <p id="res-tokens-day" class="text-[10px] text-slate-500 mt-0.5">0 token/hari</p>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                            <p class="text-xs text-slate-400">Biaya / Bulan</p>
                            <p id="res-cost-month" class="text-xl font-bold text-indigo-400 mt-1">$0.00</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">30 hari kalender</p>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                            <p class="text-xs text-slate-400">Runway Saldo</p>
                            <p id="res-runway-days" class="text-xl font-bold text-emerald-400 mt-1">0 Hari</p>
                            <p id="res-runway-months" class="text-[10px] text-slate-500 mt-0.5">0 Bulan</p>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                            <p class="text-xs text-slate-400">Kapasitas Query</p>
                            <p id="res-total-queries" class="text-xl font-bold text-sky-400 mt-1">0</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">hingga saldo habis</p>
                        </div>
                    </div>

                    <!-- Projection Chart -->
                    <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-semibold flex items-center gap-2">
                                <i data-lucide="trending-down" class="w-4 h-4 text-rose-400"></i>
                                Proyeksi Penurunan Saldo Kredit
                            </h4>
                            <span id="badge-burn-rate" class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">
                                Hemat
                            </span>
                        </div>
                        <div class="h-64">
                            <canvas id="creditChart"></canvas>
                        </div>
                    </div>

                    <!-- Comparison Table -->
                    <div class="bg-slate-950 rounded-2xl border border-slate-800 overflow-hidden">
                        <div class="px-5 py-3 border-b border-slate-800 flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-slate-200">Perbandingan Model Lain</h4>
                            <span class="text-xs text-slate-500" id="comparison-load-label">50 query/hari</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-slate-900/60 text-slate-400 uppercase border-b border-slate-800">
                                    <tr>
                                        <th class="px-4 py-2.5">Model</th>
                                        <th class="px-4 py-2.5">Input / Output ($/1M)</th>
                                        <th class="px-4 py-2.5">Biaya / Bln</th>
                                        <th class="px-4 py-2.5">Daya Tahan Saldo</th>
                                    </tr>
                                </thead>
                                <tbody id="comparison-table-body" class="divide-y divide-slate-800/60 text-slate-300">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== TAB 2: QWEN CHAT PLAYGROUND ==================== -->
        <section id="tab-chat" class="hidden space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Sidebar Config (4 cols) -->
                <div class="lg:col-span-4 space-y-5">
                    <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-4">
                        <h3 class="text-sm font-semibold flex items-center gap-2 text-slate-200">
                            <i data-lucide="settings-2" class="w-4 h-4 text-amber-400"></i>
                            Konfigurasi API
                        </h3>

                        <!-- Provider Switcher -->
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Pilih Provider</label>
                            <select id="chat-provider" onchange="onProviderChanged()" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500/50">
                                <option value="groq" selected>Groq (qwen3.8-27b, Ultra Fast LPUs)</option>
                                <option value="openrouter">OpenRouter (Full Qwen Catalog: 2.5 32B/72B/QwQ)</option>
                                <option value="gemini">Google Cloud / Gemini (GCP API)</option>
                            </select>
                        </div>

                        <!-- API Key Input -->
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-xs font-medium text-slate-400" id="api-key-label">API Key</label>
                                <a id="api-key-link" href="https://console.groq.com/keys" target="_blank" class="text-[11px] text-amber-400 hover:underline flex items-center gap-0.5">
                                    Ambil Key <i data-lucide="external-link" class="w-2.5 h-2.5"></i>
                                </a>
                            </div>
                            <div class="relative">
                                <input type="password" id="provider-api-key" placeholder="Masukkan API Key..." onchange="saveCurrentApiKey()" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-amber-500/50 pr-8">
                                <button type="button" onclick="toggleKeyVisibility()" class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-200">
                                    <i data-lucide="eye" id="eye-icon" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                            <span class="text-[10px] text-slate-500 mt-1 block">Tersimpan di browser atau disuntikkan via <code>.env</code> / Docker secret.</span>
                        </div>

                        <!-- OpenRouter live balance button (if openrouter selected) -->
                        <div id="openrouter-balance-box" class="hidden">
                            <button onclick="checkOpenRouterBalance()" class="w-full py-1.5 px-3 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 rounded-xl text-xs flex items-center justify-between transition">
                                <span class="flex items-center gap-1.5"><i data-lucide="wallet" class="w-3.5 h-3.5"></i> Cek Saldo OpenRouter:</span>
                                <span id="openrouter-balance-text" class="font-bold">Klik untuk Cek</span>
                            </button>
                        </div>

                        <!-- Model Selector -->
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Model Target</label>
                            <select id="chat-model" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500/50">
                                <!-- Populated dynamically based on provider -->
                            </select>
                        </div>

                        <!-- System Prompt -->
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">System Prompt</label>
                            <textarea id="chat-system" rows="3" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-amber-500/50 resize-none">You are an expert AI assistant specializing in AWS cloud architecture, Qwen model optimizations, and billing management.</textarea>
                        </div>

                        <button onclick="clearChat()" class="w-full py-2 px-3 rounded-xl border border-slate-800 hover:bg-slate-900 text-xs text-slate-400 hover:text-slate-200 flex items-center justify-center gap-1.5 transition">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            Bersihkan Riwayat Chat
                        </button>
                    </div>

                    <!-- Usage Summary Card -->
                    <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-xs space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Total Token Sesi:</span>
                            <span id="session-tokens" class="font-bold text-white">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Estimasi Biaya Sesi:</span>
                            <span id="session-cost" class="font-bold text-amber-400">$0.000000</span>
                        </div>
                    </div>
                </div>

                <!-- Chat Messages Area (8 cols) -->
                <div class="lg:col-span-8 flex flex-col h-[650px] bg-slate-950 rounded-2xl border border-slate-800 overflow-hidden shadow-2xl">
                    <div class="px-5 py-3.5 bg-slate-900/60 border-b border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
                            <span class="text-xs font-semibold text-slate-200" id="chat-header-model">qwen/qwen3.8-27b (Groq LPUs)</span>
                        </div>
                        <span class="text-[11px] text-slate-400" id="chat-header-badge">Internet API Call</span>
                    </div>

                    <div id="messages-container" class="flex-1 p-5 overflow-y-auto space-y-4">
                        <div class="flex gap-3 text-slate-300">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600/30 text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/30">
                                <i data-lucide="bot" class="w-4 h-4"></i>
                            </div>
                            <div class="space-y-1.5 max-w-[85%]">
                                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3.5 text-xs sm:text-sm leading-relaxed text-slate-200">
                                    Halo! Playground ini terhubung ke model <strong>Qwen</strong> melalui Groq atau OpenRouter, serta Google Cloud (Gemini). Kredensial tidak pernah masuk ke Docker image dan aman di environment. Silakan ajukan pertanyaan!
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-900/70 border-t border-slate-800">
                        <form onsubmit="sendMessage(event)" class="flex gap-2">
                            <textarea id="chat-input" rows="1" placeholder="Tanyakan seputar cloud atau kode (Ctrl+Enter untuk kirim)..." onkeydown="handleKeyDown(event)" class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500/50 resize-none"></textarea>
                            <button type="submit" id="btn-send" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-semibold text-sm flex items-center gap-2 transition disabled:opacity-50">
                                <span>Kirim</span>
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== TAB 3: BUDGET GUARD (AWS & GCP) ==================== -->
        <section id="tab-budget-guard" class="hidden space-y-6">
            <div class="bg-gradient-to-r from-rose-950/40 via-slate-950 to-slate-950 p-6 rounded-2xl border border-rose-900/30">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl shrink-0">
                        <i data-lucide="shield-alert" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Generator Pengaman Billing (AWS & Google Cloud)</h3>
                        <p class="text-slate-300 text-xs sm:text-sm mt-1 leading-relaxed">
                            Mencegah tagihan bengkak dengan memasang alarm otomatis pada level cloud provider. Baik saldo \$200 AWS maupun \$300 Google Cloud Free Trial akan terjaga aman.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Configurator -->
                <div class="lg:col-span-5 bg-slate-950 p-6 rounded-2xl border border-slate-800 space-y-4">
                    <div class="flex gap-2 p-1 bg-slate-900 rounded-xl border border-slate-800">
                        <button type="button" onclick="setGuardCloud('aws')" id="guard-btn-aws" class="flex-1 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 text-slate-950 transition">AWS Budgets</button>
                        <button type="button" onclick="setGuardCloud('gcp')" id="guard-btn-gcp" class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition">Google Cloud (GCP)</button>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nama Budget</label>
                        <input type="text" id="bg-name" value="student-guard" oninput="updateBudgetScript()" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs">
                    </div>

                    <div id="gcp-project-box" class="hidden">
                        <label class="block text-xs font-medium text-slate-400 mb-1">GCP Project ID</label>
                        <input type="text" id="bg-gcp-project" value="sample-firebase-ai-app-cf270" oninput="updateBudgetScript()" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Batas Maksimal Pengeluaran (USD / Bulan)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs text-slate-500">$</span>
                            <input type="number" id="bg-limit" value="5" min="1" max="500" oninput="updateBudgetScript()" class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-7 pr-3 py-2 text-xs font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Ambang Peringatan (Persentase)</label>
                        <div class="relative">
                            <input type="number" id="bg-threshold" value="80" min="10" max="100" oninput="updateBudgetScript()" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono pr-7">
                            <span class="absolute right-3 top-2.5 text-xs text-slate-500">%</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Email Penerima Notifikasi</label>
                        <input type="email" id="bg-email" placeholder="mahasiswa@univ.ac.id" oninput="updateBudgetScript()" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs">
                    </div>
                </div>

                <!-- Generated Command -->
                <div class="lg:col-span-7 space-y-4">
                    <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="terminal" class="w-4 h-4 text-emerald-400"></i>
                                <span class="text-xs font-semibold text-slate-300" id="guard-cli-title">AWS CLI Budget Guard</span>
                            </div>
                            <button onclick="copyBudgetCli()" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-amber-400 text-xs rounded-lg flex items-center gap-1.5 transition">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                <span id="btn-copy-label">Salin Perintah</span>
                            </button>
                        </div>
                        <pre id="budget-cli-code" class="bg-slate-900 p-4 rounded-xl text-xs font-mono text-emerald-300 overflow-x-auto whitespace-pre leading-relaxed border border-slate-800"></pre>
                    </div>

                    <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800 text-xs text-slate-300 space-y-2">
                        <h5 class="font-semibold text-white flex items-center gap-1.5">
                            <i data-lucide="info" class="w-4 h-4 text-sky-400"></i>
                            Tindakan Otomatis
                        </h5>
                        <p class="text-slate-400 leading-relaxed">
                            Ketika budget terpicu (misal sudah mencapai 80%), cloud provider akan mengirim peringatan instan ke email Anda sehingga Anda dapat mematikan endpoint atau model sebelum batas kredit habis.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== TAB 4: DOCKER & SECRETS ARCHITECTURE ==================== -->
        <section id="tab-docker-secrets" class="hidden space-y-6">
            <div class="bg-slate-950 p-6 rounded-2xl border border-slate-800 space-y-6">
                <div>
                    <span class="text-xs font-semibold text-indigo-400 tracking-wider uppercase">Arsitektur Container & Keamanan</span>
                    <h3 class="text-xl font-bold text-white mt-1">Docker Runtime & Secret Injection Mechanism</h3>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        Docker hanya bertindak sebagai runtime lokal/server; koneksi ke Groq, AWS Bedrock, GCP, dan OpenRouter <strong>tetap melalui internet (outbound HTTPS port 443)</strong>.
                    </p>
                </div>

                <!-- Principle Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-slate-900/70 border border-slate-800 rounded-xl space-y-2">
                        <div class="flex items-center gap-2 text-rose-400 font-semibold text-xs">
                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                            JANGAN: Hardcode di Image
                        </div>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            Jangan pernah menyertakan file <code>.env</code> atau menulis <code>ENV GROQ_API_KEY=...</code> di dalam <code>Dockerfile</code>. Image dapat di-push atau terekspos ke registry publik.
                        </p>
                    </div>
                    <div class="p-4 bg-slate-900/70 border border-slate-800 rounded-xl space-y-2">
                        <div class="flex items-center gap-2 text-emerald-400 font-semibold text-xs">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            GUNAKAN: .dockerignore
                        </div>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            File <code>.dockerignore</code> telah dikonfigurasi untuk mengecualikan <code>.env</code>, <code>.git</code>, dan token lokal dari build context Docker.
                        </p>
                    </div>
                    <div class="p-4 bg-slate-900/70 border border-slate-800 rounded-xl space-y-2">
                        <div class="flex items-center gap-2 text-sky-400 font-semibold text-xs">
                            <i data-lucide="key" class="w-4 h-4"></i>
                            RUNTIME: Secret Mount / Env
                        </div>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            Suntikkan secret saat kontainer dijalankan melalui <code>docker-compose.yml</code> (membaca <code>.env</code> host) atau Docker Secrets (<code>/run/secrets/</code>).
                        </p>
                    </div>
                </div>

                <!-- Docker commands snippet -->
                <div class="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-300 font-medium">
                        <span>Menjalankan Aplikasi dengan Docker Compose (Port 8081)</span>
                    </div>
                    <pre class="text-xs font-mono text-amber-300 overflow-x-auto whitespace-pre">
# 1. Pastikan file .env ada di host (tidak masuk ke image)
copy .env.example .env

# 2. Build dan jalankan container
docker compose up -d --build

# 3. Buka dashboard di browser
http://localhost:8081
                    </pre>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 py-4 text-center text-xs text-slate-500 bg-slate-950">
        AI Billing & Cloud Hub &bull; Docker Container Ready &bull; AWS Bedrock &bull; Groq &bull; OpenRouter &bull; GCP
    </footer>

    <script>
        lucide.createIcons();

        const MODELS = {
            qwen25_coder_32b: { name: 'Qwen 2.5 Coder 32B', input: 0.66, output: 1.00, desc: 'OpenRouter/Groq' },
            qwen3_235b_thinking: { name: 'Qwen3 235B A22B Thinking', input: 0.45, output: 3.50, desc: 'Deep Reasoning' },
            qwen_plus: { name: 'Qwen-Plus 131K', input: 0.26, output: 0.78, desc: 'Balanced fast & budget' },
            qwen3_vl_30b: { name: 'Qwen3 VL 30B', input: 0.29, output: 1.00, desc: 'Vision & Multimodal' },
            qwen_25_72b: { name: 'Qwen 2.5 72B Instruct', input: 0.35, output: 0.40, desc: 'OpenRouter Flagship' },
            bedrock_qwen3_next: { name: 'Qwen3-Next 80B A3B (AWS Bedrock)', input: null, output: null, desc: 'Isi tarif sesuai region/service tier AWS' },
            bedrock_claude35: { name: 'Claude 3.5 Sonnet (AWS Bedrock)', input: 3.00, output: 15.00, desc: '$200 AWS Credit' },
            bedrock_llama33_70b: { name: 'Llama 3.3 70B (AWS Bedrock)', input: 0.72, output: 0.72, desc: '$200 AWS Credit' },
            gcp_gemini25_flash: { name: 'Gemini 2.5 Flash (GCP)', input: 0.075, output: 0.30, desc: '$300 GCP Credit' },
            gcp_gemini25_pro: { name: 'Gemini 2.5 Pro (GCP)', input: 1.25, output: 5.00, desc: '$300 GCP Credit' }
        };

        const PROVIDER_MODELS = {
            groq: [
                { id: 'qwen/qwen3.8-27b', name: 'qwen/qwen3.8-27b (Fast LPUs)' },
                { id: 'openai/gpt-oss-120b', name: 'openai/gpt-oss-120b' },
                { id: 'openai/gpt-oss-20b', name: 'openai/gpt-oss-20b' },
                { id: 'allam-2-7b', name: 'allam-2-7b' }
            ],
            openrouter: [
                { id: 'qwen/qwen-2.5-coder-32b-instruct', name: 'qwen/qwen-2.5-coder-32b-instruct' },
                { id: 'qwen/qwen-2.5-72b-instruct', name: 'qwen/qwen-2.5-72b-instruct' },
                { id: 'qwen/qwen3-coder', name: 'qwen/qwen3-coder (Coding)' },
                { id: 'qwen/qwen3-235b-a22b-thinking-2507', name: 'qwen/qwen3-235b-a22b-thinking-2507 (Reasoning)' }
            ],
            gemini: [
                { id: 'gemini-2.5-flash', name: 'gemini-2.5-flash (Ultra Fast & Cheap)' },
                { id: 'gemini-2.5-pro', name: 'gemini-2.5-pro (Deep Coding & Logic)' }
            ]
        };

        function switchTab(tabId) {
            document.querySelectorAll('section[id^="tab-"]').forEach(el => el.classList.add('hidden'));
            document.getElementById(`tab-${tabId}`).classList.remove('hidden');

            document.querySelectorAll('.nav-tab').forEach(btn => {
                btn.classList.remove('bg-slate-800', 'text-amber-400', 'border', 'border-slate-700');
                btn.classList.add('text-slate-400');
                btn.classList.remove('active');
            });
            const activeBtn = document.getElementById(`tab-btn-${tabId}`);
            activeBtn.classList.add('bg-slate-800', 'text-amber-400', 'border', 'border-slate-700');
            activeBtn.classList.remove('text-slate-400');
            activeBtn.classList.add('active');

            if (tabId === 'calculator' && chartInstance) chartInstance.resize();
            lucide.createIcons();
        }

        // ================= CALCULATOR =================
        let chartInstance = null;
        let previousCalcModelKey = null;

        function setPresetCredit(val) {
            document.getElementById('input-credit').value = val;
            updateSliders();
            calculateCost();
        }

        function updateSliders() {
            document.getElementById('val-credit').textContent = '$' + document.getElementById('input-credit').value;
            document.getElementById('val-queries').textContent = document.getElementById('input-queries').value + ' query';
            document.getElementById('val-input-tokens').textContent = Number(document.getElementById('input-tokens').value).toLocaleString() + ' tokens';
            document.getElementById('val-output-tokens').textContent = Number(document.getElementById('output-tokens').value).toLocaleString() + ' tokens';
            document.getElementById('comparison-load-label').textContent = document.getElementById('input-queries').value + ' query/hari';
        }

        function calculateCost() {
            const modelKey = document.getElementById('calc-model').value;
            const customBox = document.getElementById('custom-pricing-box');
            const pricingNote = document.getElementById('custom-pricing-note');
            const requiresManualPricing = modelKey === 'custom' || modelKey === 'bedrock_qwen3_next';

            if (modelKey === 'bedrock_qwen3_next' && previousCalcModelKey !== modelKey) {
                document.getElementById('custom-in-price').value = '';
                document.getElementById('custom-out-price').value = '';
            }
            previousCalcModelKey = modelKey;

            let inputRate = 0, outputRate = 0;
            let pricingAvailable = true;
            if (requiresManualPricing) {
                customBox.classList.remove('hidden');
                customBox.classList.add('grid');
                pricingNote.classList.toggle('hidden', modelKey !== 'bedrock_qwen3_next');
                const inputValue = document.getElementById('custom-in-price').value;
                const outputValue = document.getElementById('custom-out-price').value;
                inputRate = parseFloat(inputValue);
                outputRate = parseFloat(outputValue);
                pricingAvailable = inputValue !== '' && outputValue !== ''
                    && Number.isFinite(inputRate) && inputRate >= 0
                    && Number.isFinite(outputRate) && outputRate >= 0;
            } else {
                customBox.classList.add('hidden');
                customBox.classList.remove('grid');
                pricingNote.classList.add('hidden');
                inputRate = MODELS[modelKey].input;
                outputRate = MODELS[modelKey].output;
            }

            const initialCredit = parseFloat(document.getElementById('input-credit').value);
            const queriesPerDay = parseInt(document.getElementById('input-queries').value);
            const inTokens = parseInt(document.getElementById('input-tokens').value);
            const outTokens = parseInt(document.getElementById('output-tokens').value);

            const tokensPerDay = queriesPerDay * (inTokens + outTokens);
            if (!pricingAvailable) {
                document.getElementById('res-cost-day').textContent = 'Isi tarif';
                document.getElementById('res-tokens-day').textContent = Number(tokensPerDay).toLocaleString() + ' tokens/hari';
                document.getElementById('res-cost-month').textContent = 'Isi tarif';
                document.getElementById('res-runway-days').textContent = '—';
                document.getElementById('res-runway-months').textContent = '—';
                document.getElementById('res-total-queries').textContent = '—';
                const badge = document.getElementById('badge-burn-rate');
                badge.className = 'text-xs px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-medium';
                badge.textContent = 'Tarif diperlukan';
                renderChart(initialCredit, null);
                renderComparisonTable(queriesPerDay, inTokens, outTokens, initialCredit);
                return;
            }

            const costPerQuery = ((inTokens / 1000000) * inputRate) + ((outTokens / 1000000) * outputRate);
            const costPerDay = costPerQuery * queriesPerDay;
            const costPerMonth = costPerDay * 30;

            const runwayDays = costPerDay > 0 ? (initialCredit / costPerDay) : 9999;
            const runwayMonths = (runwayDays / 30).toFixed(1);
            const totalQueries = costPerQuery > 0 ? Math.floor(initialCredit / costPerQuery) : 0;

            document.getElementById('res-cost-day').textContent = '$' + costPerDay.toFixed(4);
            document.getElementById('res-tokens-day').textContent = Number(tokensPerDay).toLocaleString() + ' tokens/hari';
            document.getElementById('res-cost-month').textContent = '$' + costPerMonth.toFixed(2);
            document.getElementById('res-runway-days').textContent = runwayDays > 3650 ? '>10 Tahun' : Math.floor(runwayDays) + ' Hari';
            document.getElementById('res-runway-months').textContent = runwayDays > 3650 ? '>120 Bulan' : runwayMonths + ' Bulan';
            document.getElementById('res-total-queries').textContent = Number(totalQueries).toLocaleString() + 'x';

            const badge = document.getElementById('badge-burn-rate');
            if (runwayDays > 180) {
                badge.className = 'text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium';
                badge.textContent = 'Sangat Hemat (>6 Bulan)';
            } else if (runwayDays > 60) {
                badge.className = 'text-xs px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-medium';
                badge.textContent = 'Sedang (~2-6 Bulan)';
            } else {
                badge.className = 'text-xs px-2.5 py-0.5 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 font-medium';
                badge.textContent = 'Tinggi (<2 Bulan)';
            }

            renderChart(initialCredit, costPerDay);
            renderComparisonTable(queriesPerDay, inTokens, outTokens, initialCredit);
        }

        function renderChart(initialCredit, costPerDay) {
            const ctx = document.getElementById('creditChart').getContext('2d');
            const labels = [];
            const dataPoints = [];
            for (let day = 0; day <= 90; day += 5) {
                labels.push('Hari ' + day);
                dataPoints.push(costPerDay === null ? null : Math.max(0, initialCredit - (costPerDay * day)).toFixed(2));
            }
            if (chartInstance) chartInstance.destroy();
            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sisa Saldo ($)',
                        data: dataPoints,
                        borderColor: '#FF9900',
                        backgroundColor: 'rgba(255, 153, 0, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#FF9900',
                        pointBorderColor: '#fff',
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: '#eaeded' }, ticks: { color: '#545b64', font: { size: 10 } } },
                        y: { beginAtZero: true, max: Math.ceil(initialCredit * 1.1), grid: { color: '#eaeded' }, ticks: { color: '#545b64', callback: v => '$' + v } }
                    }
                }
            });
        }

        function renderComparisonTable(queriesPerDay, inTokens, outTokens, credit) {
            const tbody = document.getElementById('comparison-table-body');
            tbody.innerHTML = '';
            const selectedModelKey = document.getElementById('calc-model').value;
            const selectedQwenRates = selectedModelKey === 'bedrock_qwen3_next'
                ? {
                    input: document.getElementById('custom-in-price').value,
                    output: document.getElementById('custom-out-price').value
                }
                : null;
            for (const [key, m] of Object.entries(MODELS)) {
                const rates = key === 'bedrock_qwen3_next' && selectedQwenRates
                    ? { input: parseFloat(selectedQwenRates.input), output: parseFloat(selectedQwenRates.output) }
                    : m;
                const hasRates = Number.isFinite(rates.input) && rates.input >= 0
                    && Number.isFinite(rates.output) && rates.output >= 0;
                const costPerQ = hasRates
                    ? ((inTokens / 1000000) * rates.input) + ((outTokens / 1000000) * rates.output)
                    : null;
                const costMonth = hasRates ? costPerQ * queriesPerDay * 30 : null;
                const days = costPerQ > 0 ? (credit / (costPerQ * queriesPerDay)) : 9999;
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-900/50 transition';
                tr.innerHTML = `
                    <td class="px-4 py-3 font-medium text-slate-200">
                        ${m.name}
                        <span class="block text-[10px] text-slate-500">${m.desc}</span>
                    </td>
                    <td class="px-4 py-3 font-mono text-slate-400">${hasRates ? `$${rates.input.toFixed(3)} / $${rates.output.toFixed(3)}` : 'Isi tarif'}</td>
                    <td class="px-4 py-3 font-semibold ${hasRates ? (costMonth > 50 ? 'text-rose-400' : 'text-amber-400') : 'text-slate-500'}">${hasRates ? `$${costMonth.toFixed(2)}` : '—'}</td>
                    <td class="px-4 py-3 font-semibold ${days > 180 ? 'text-emerald-400' : (days > 30 ? 'text-amber-400' : 'text-rose-400')}">
                        ${hasRates ? (days > 3650 ? '>10 Tahun' : Math.floor(days) + ' Hari') : '—'}
                    </td>
                `;
                tbody.appendChild(tr);
            }
        }

        // ================= PROVIDER & CHAT PLAYGROUND =================
        function onProviderChanged() {
            const provider = document.getElementById('chat-provider').value;
            const modelSelect = document.getElementById('chat-model');
            const keyLabel = document.getElementById('api-key-label');
            const keyLink = document.getElementById('api-key-link');
            const balanceBox = document.getElementById('openrouter-balance-box');

            // Set Models
            modelSelect.innerHTML = '';
            PROVIDER_MODELS[provider].forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name;
                modelSelect.appendChild(opt);
            });

            // Set Links & Labels
            if (provider === 'groq') {
                keyLabel.textContent = 'Groq API Key';
                keyLink.href = 'https://console.groq.com/keys';
                balanceBox.classList.add('hidden');
                document.getElementById('chat-header-model').textContent = 'Qwen via Groq LPUs';
            } else if (provider === 'openrouter') {
                keyLabel.textContent = 'OpenRouter API Key';
                keyLink.href = 'https://openrouter.ai/keys';
                balanceBox.classList.remove('hidden');
                document.getElementById('chat-header-model').textContent = 'Qwen via OpenRouter';
            } else if (provider === 'gemini') {
                keyLabel.textContent = 'Google Gemini/GCP API Key';
                keyLink.href = 'https://aistudio.google.com/app/apikey';
                balanceBox.classList.add('hidden');
                document.getElementById('chat-header-model').textContent = 'Gemini via Google AI / GCP';
            }

            // Load saved key for this provider
            const saved = localStorage.getItem('API_KEY_' + provider) || '';
            document.getElementById('provider-api-key').value = saved;
        }

        function saveCurrentApiKey() {
            const provider = document.getElementById('chat-provider').value;
            const val = document.getElementById('provider-api-key').value.trim();
            localStorage.setItem('API_KEY_' + provider, val);
        }

        function toggleKeyVisibility() {
            const input = document.getElementById('provider-api-key');
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        async function checkOpenRouterBalance() {
            const btnText = document.getElementById('openrouter-balance-text');
            btnText.textContent = 'Memeriksa...';
            const key = document.getElementById('provider-api-key').value.trim();
            try {
                const res = await fetch('api.php?action=openrouter_balance', {
                    headers: { 'X-Provider-Api-Key': key }
                });
                const data = await res.json();
                if (data.status === 'ok') {
                    const usage = data.data.usage || 0;
                    const limit = data.data.limit || 0;
                    btnText.textContent = `Used: $${Number(usage).toFixed(2)} / Limit: $${Number(limit).toFixed(2)}`;
                } else {
                    btnText.textContent = data.error || 'Gagal';
                }
            } catch (e) {
                btnText.textContent = 'Error koneksi';
            }
        }

        let chatHistory = [];
        let sessionTokens = 0, sessionCost = 0;

        function clearChat() {
            chatHistory = [];
            sessionTokens = 0; sessionCost = 0;
            document.getElementById('session-tokens').textContent = '0';
            document.getElementById('session-cost').textContent = '$0.000000';
            document.getElementById('messages-container').innerHTML = `
                <div class="flex gap-3 text-slate-300">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600/30 text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/30">
                        <i data-lucide="bot" class="w-4 h-4"></i>
                    </div>
                    <div class="space-y-1.5 max-w-[85%]">
                        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3.5 text-xs sm:text-sm leading-relaxed text-slate-200">
                            Riwayat telah dibersihkan.
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        }

        function handleKeyDown(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                sendMessage(e);
            }
        }

        async function sendMessage(e) {
            if (e) e.preventDefault();
            const input = document.getElementById('chat-input');
            const userText = input.value.trim();
            if (!userText) return;

            const provider = document.getElementById('chat-provider').value;
            const apiKey = document.getElementById('provider-api-key').value.trim();
            const model = document.getElementById('chat-model').value;
            const systemPrompt = document.getElementById('chat-system').value.trim();

            appendMessage('user', userText);
            input.value = '';

            const messages = [];
            if (systemPrompt) messages.push({ role: 'system', content: systemPrompt });
            chatHistory.push({ role: 'user', content: userText });
            messages.push(...chatHistory);

            const btnSend = document.getElementById('btn-send');
            btnSend.disabled = true;
            const loadingId = appendLoading();

            try {
                const response = await fetch('api.php?action=chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Provider': provider,
                        'X-Provider-Api-Key': apiKey
                    },
                    body: JSON.stringify({
                        provider: provider,
                        model: model,
                        messages: messages,
                        temperature: 0.6,
                        max_completion_tokens: 2048
                    })
                });

                removeLoading(loadingId);
                
                // Check if response is valid
                if (!response.ok) {
                    const errorText = await response.text();
                    let msg = errorText;
                    try {
                        const parsed = JSON.parse(errorText);
                        msg = parsed.error || errorText;
                    } catch (_) {}
                    appendMessage('error', `HTTP ${response.status}: ${msg}`);
                    return;
                }

                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (jsonErr) {
                    const snippet = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300);
                    appendMessage('error', `Respons API bukan JSON. Cuplikan: ${snippet || '(kosong)'}`);
                    console.error('Raw response:', text);
                    return;
                }

                if (data.error) {
                    appendMessage('error', data.error || 'Terjadi kesalahan saat memanggil API.');
                } else {
                    const content = data.content || '';
                    chatHistory.push({ role: 'assistant', content: content });
                    const usage = data.usage || { prompt_tokens: 0, completion_tokens: 0, total_tokens: 0 };
                    const dur = data.duration_seconds || 0;

                    const qCost = ((usage.prompt_tokens / 1000000) * 0.66) + ((usage.completion_tokens / 1000000) * 1.00);
                    sessionTokens += usage.total_tokens;
                    sessionCost += qCost;

                    document.getElementById('session-tokens').textContent = sessionTokens.toLocaleString();
                    document.getElementById('session-cost').textContent = '$' + sessionCost.toFixed(6);

                    appendMessage('assistant', content, {
                        model: data.model,
                        tokens: usage,
                        duration: dur,
                        cost: qCost
                    });
                }
            } catch (err) {
                removeLoading(loadingId);
                appendMessage('error', 'Koneksi gagal: ' + err.message);
            } finally {
                btnSend.disabled = false;
            }
        }

        function appendMessage(role, text, meta = null) {
            const container = document.getElementById('messages-container');
            const div = document.createElement('div');
            if (role === 'user') {
                div.className = 'flex justify-end';
                div.innerHTML = `<div class="max-w-[85%] bg-amber-500/15 border border-amber-500/30 rounded-2xl p-3.5 text-xs sm:text-sm text-amber-100 whitespace-pre-wrap leading-relaxed">${escapeHtml(text)}</div>`;
            } else if (role === 'assistant') {
                div.className = 'flex gap-3 text-slate-300';
                let metaBadge = '';
                if (meta) {
                    metaBadge = `
                        <div class="flex flex-wrap items-center gap-2 pt-1 text-[10px] text-slate-400">
                            <span class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700">⚡ ${meta.duration}s</span>
                            <span class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700">In: ${meta.tokens.prompt_tokens} | Out: ${meta.tokens.completion_tokens}</span>
                            <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-mono border border-emerald-500/20">$${meta.cost.toFixed(6)}</span>
                        </div>
                    `;
                }
                div.innerHTML = `
                    <div class="w-8 h-8 rounded-lg bg-indigo-600/30 text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/30"><i data-lucide="bot" class="w-4 h-4"></i></div>
                    <div class="space-y-1.5 max-w-[85%]">
                        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3.5 text-xs sm:text-sm leading-relaxed text-slate-200 whitespace-pre-wrap">${escapeHtml(text)}</div>
                        ${metaBadge}
                    </div>
                `;
            } else if (role === 'error') {
                div.className = 'p-3 bg-rose-500/10 border border-rose-500/20 rounded-xl text-xs text-rose-300 flex items-start gap-2';
                div.innerHTML = `<i data-lucide="alert-triangle" class="w-4 h-4 text-rose-400 shrink-0 mt-0.5"></i><span>${escapeHtml(text)}</span>`;
            }
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
            lucide.createIcons();
        }

        function appendLoading() {
            const container = document.getElementById('messages-container');
            const id = 'loading-' + Date.now();
            const div = document.createElement('div');
            div.id = id;
            div.className = 'flex gap-3 text-slate-400';
            div.innerHTML = `
                <div class="w-8 h-8 rounded-lg bg-indigo-600/20 text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/20 animate-pulse"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i></div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl px-4 py-3 text-xs flex items-center gap-2 text-slate-400"><span>Sedang memproses respon dari AI API...</span></div>
            `;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
            lucide.createIcons();
            return id;
        }

        function removeLoading(id) {
            const el = document.getElementById(id);
            if (el) el.remove();
        }

        function escapeHtml(string) {
            return String(string).replace(/[&<>"']/g, s => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[s]));
        }

        // ================= BUDGET GUARD =================
        let currentGuardCloud = 'aws';

        function setGuardCloud(cloud) {
            currentGuardCloud = cloud;
            const btnAws = document.getElementById('guard-btn-aws');
            const btnGcp = document.getElementById('guard-btn-gcp');
            const gcpBox = document.getElementById('gcp-project-box');

            if (cloud === 'aws') {
                btnAws.className = 'flex-1 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 text-slate-950 transition';
                btnGcp.className = 'flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition';
                gcpBox.classList.add('hidden');
                document.getElementById('guard-cli-title').textContent = 'AWS CLI Budget Guard';
            } else {
                btnGcp.className = 'flex-1 py-1.5 text-xs font-semibold rounded-lg bg-sky-500 text-white transition';
                btnAws.className = 'flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition';
                gcpBox.classList.remove('hidden');
                document.getElementById('guard-cli-title').textContent = 'Google Cloud (gcloud) Budget Guard';
            }
            updateBudgetScript();
        }

        function updateBudgetScript() {
            const name = document.getElementById('bg-name').value || 'student-guard';
            const limit = document.getElementById('bg-limit').value || '5';
            const threshold = document.getElementById('bg-threshold').value || '80';
            const email = document.getElementById('bg-email').value || 'your@email.com';
            const gcpProject = document.getElementById('bg-gcp-project').value || 'my-project-id';

            let cmd = '';
            if (currentGuardCloud === 'aws') {
                cmd = `aws budgets create-budget \\
  --account-id $(aws sts get-caller-identity --query Account --output text) \\
  --budget '{
    "BudgetName": "${name}",
    "BudgetLimit": {"Amount": "${limit}", "Unit": "USD"},
    "TimeUnit": "MONTHLY",
    "BudgetType": "COST"
  }' \\
  --notifications-with-subscribers '[{
    "Notification": {
      "NotificationType": "ACTUAL",
      "ComparisonOperator": "GREATER_THAN",
      "Threshold": ${threshold}
    },
    "Subscribers": [{
      "SubscriptionType": "EMAIL",
      "Address": "${email}"
    }]
  }]'`;
            } else {
                // Google Cloud Billing Budget Command
                cmd = `gcloud billing budgets create \\
  --billing-account=$(gcloud beta billing accounts list --format="value(name)" | head -n1) \\
  --display-name="${name}" \\
  --budget-amount=${limit}USD \\
  --threshold-rule=percent=0.${threshold} \\
  --filter-projects="projects/${gcpProject}" \\
  --notifications-rule-monitoring-notification-channels="projects/${gcpProject}/notificationChannels/YOUR_CHANNEL_ID"`;
            }

            document.getElementById('budget-cli-code').textContent = cmd;
        }

        function copyBudgetCli() {
            const text = document.getElementById('budget-cli-code').textContent;
            navigator.clipboard.writeText(text).then(() => {
                const label = document.getElementById('btn-copy-label');
                label.textContent = 'Tersalin!';
                setTimeout(() => label.textContent = 'Salin Perintah', 2000);
            });
        }

        window.addEventListener('DOMContentLoaded', () => {
            onProviderChanged();
            updateSliders();
            calculateCost();
            updateBudgetScript();
        });
    </script>
</body>
</html>
