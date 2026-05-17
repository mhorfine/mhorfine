<?php
// Oculta alertas do PHP
error_reporting(0);
ini_set('display_errors', 0);

// =======================================================================
// 1. PROXY DE IMAGENS (Garante que as capas apareçam sem erro de CORS)
// =======================================================================
if (isset($_GET['img_proxy'])) {
    $img_url = str_replace(' ', '%20', urldecode($_GET['img_proxy']));
    header('Access-Control-Allow-Origin: *');
    header("Cache-Control: public, max-age=86400");
    $ch = curl_init($img_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
        if (stripos($header, 'Content-Type:') === 0) header(trim($header));
        return strlen($header);
    });
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// =======================================================================
// 2. LEITURA PURA DA API JSON (O "Cérebro" do Sistema)
// =======================================================================
$cache_file = __DIR__ . '/api_cache.json';
$cache_time = 3600;
// URL da API mantida exatamente como o original (http://)
$api_url = "http://api.assets-img.com/api-app-updated.json";
$fetch_needed = true;
$response = '';

if (file_exists($cache_file) && (time() - @filemtime($cache_file)) < $cache_time) {
    $response = @file_get_contents($cache_file);
    if (!empty($response)) $fetch_needed = false;
}

if ($fetch_needed) {
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response && json_decode($response)) {
        @file_put_contents($cache_file, $response);
    }
}

$data = json_decode($response, true);
if (!is_array($data)) $data = ["background_screen" => "", "items" => []];
$bg_screen = $data['Background_screen'] ?? $data['background_screen'] ?? '';
$json_encoded = json_encode($data);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mhorfine Play | Conexão Direta (HTTP)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .neon-red-glow:hover { box-shadow: 0 0 15px rgba(220, 38, 38, 0.6); border-color: rgba(220, 38, 38, 1); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #050505; }
        ::-webkit-scrollbar-thumb { background: #dc2626; border-radius: 3px; }
        .active-tab { border-bottom: 3px solid #dc2626; background: rgba(220, 38, 38, 0.1); color: white !important; }
        .glass-panel { background: rgba(10, 10, 10, 0.5); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .console-banner { background: linear-gradient(90deg, rgba(5,5,5,0.95) 0%, rgba(5,5,5,0.4) 50%, rgba(5,5,5,0.95) 100%); }
    </style>
</head>
<body id="body-bg" class="bg-[#050505] text-gray-100 min-h-screen font-sans transition-all duration-700" 
      style="background-image: linear-gradient(rgba(5, 5, 5, 0.85), rgba(5, 5, 5, 0.98)), url('<?= htmlspecialchars($bg_screen) ?>'); background-size: cover; background-attachment: fixed;">

    <header class="w-full glass-panel sticky top-0 z-40 border-b border-white/5 shadow-2xl">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col sm:flex-row gap-4 justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-red-600 animate-pulse shadow-[0_0_15px_#dc2626]"></div>
                <h1 class="text-2xl font-black tracking-widest text-white">MHORFINE <span class="text-red-600">PLAY</span></h1>
            </div>
            <div class="relative w-full sm:w-80">
                <input type="text" id="search-input" placeholder="Buscar título..." 
                       class="w-full bg-[#0a0a0a] border border-white/10 rounded-lg pl-4 pr-4 py-2 text-sm text-white focus:outline-none focus:border-red-600 shadow-inner">
            </div>
        </div>
        <nav id="emulator-tabs" class="max-w-7xl mx-auto px-4 flex gap-2 overflow-x-auto border-t border-white/5"></nav>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div id="active-emulator-content" class="space-y-12"></div>
    </main>

    <div id="info-modal" class="hidden fixed inset-0 z-50 bg-[#050505]/90 backdrop-blur-md flex items-center justify-center p-4">
        <div class="glass-panel w-full max-w-3xl rounded-2xl overflow-hidden shadow-2xl flex flex-col md:flex-row border border-white/10 neon-red-glow">
            <div class="relative w-full md:w-1/2 aspect-[3/4] bg-black shrink-0">
                <img id="info-game-cover" class="w-full h-full object-cover" src="">
                <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent opacity-80"></div>
            </div>
            <div class="p-8 flex flex-col justify-between flex-1 gap-6">
                <div class="space-y-5">
                    <div class="flex justify-between items-start">
                        <span id="info-game-system" class="text-[10px] bg-red-600/20 text-red-500 border border-red-600/30 font-bold px-3 py-1 rounded uppercase tracking-widest"></span>
                        <button onclick="closeInfoModal()" class="text-neutral-500 hover:text-white font-black text-lg">✕</button>
                    </div>
                    <h2 id="info-game-title" class="text-2xl font-black text-white uppercase tracking-tight drop-shadow-md"></h2>
                </div>
                <div id="info-modal-actions" class="w-full"></div>
            </div>
        </div>
    </div>

    <div id="game-modal" class="hidden fixed inset-0 z-[60] bg-black flex flex-col">
        <div class="flex justify-between items-center p-4 bg-[#0a0a0a] border-b border-red-600/30 shrink-0 shadow-lg z-10">
            <div class="flex items-center gap-3">
                <div id="status-led" class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse shadow-[0_0_10px_#22c55e]"></div>
                <h2 id="modal-game-title" class="text-white font-bold uppercase tracking-widest text-xs sm:text-sm">Iniciando Motor...</h2>
            </div>
            <button onclick="closeGame()" class="bg-red-600/20 text-red-500 hover:bg-red-600 hover:text-white border border-red-600/40 px-5 py-2 rounded text-xs font-black transition-all">SAIR DO JOGO</button>
        </div>
        
        <div id="emulator-container" class="flex-1 w-full bg-black relative flex items-center justify-center"></div>
    </div>

    <script>
        const apiData = <?= $json_encoded ?>;
        let currentEmulatorIndex = 0;
        let searchQuery = '';
        const baseUrl = window.location.origin + window.location.pathname;

        const coreMap = { 'sony_playstation': 'psx', 'nintendo_ds': 'nds', 'super_nintendo': 'snes' };

        function getSafeImageUrl(url) {
            if (!url) return '';
            // Mesmo em HTTP, passamos as imagens pelo proxy para evitar que o Canvas do navegador bloqueie a imagem (CORS)
            if (url.startsWith('http://')) return baseUrl + "?img_proxy=" + encodeURIComponent(url);
            return url;
        }

        function init() {
            if (!apiData || !apiData.items || apiData.items.length === 0) return;
            document.getElementById('search-input').addEventListener('input', (e) => {
                searchQuery = e.target.value.toLowerCase().trim();
                renderActiveEmulator();
            });
            renderTabs();
            selectEmulator(0);
        }

        function renderTabs() {
            const nav = document.getElementById('emulator-tabs');
            nav.innerHTML = '';
            apiData.items.forEach((emu, index) => {
                const tab = document.createElement('button');
                tab.className = `flex items-center gap-3 px-6 py-4 text-xs font-bold uppercase tracking-widest transition-all whitespace-nowrap text-neutral-500 hover:text-white ${index === currentEmulatorIndex ? 'active-tab' : ''}`;
                tab.innerHTML = `<span>${emu.code}</span>`;
                tab.onclick = () => selectEmulator(index);
                nav.appendChild(tab);
            });
        }

        function selectEmulator(index) {
            currentEmulatorIndex = index;
            renderTabs();
            renderActiveEmulator();
            const emu = apiData.items[index];
            if (emu.background) document.getElementById('body-bg').style.backgroundImage = `linear-gradient(rgba(5, 5, 5, 0.85), rgba(5, 5, 5, 0.98)), url('${getSafeImageUrl(emu.background)}')`;
        }

        function renderActiveEmulator() {
            const container = document.getElementById('active-emulator-content');
            const emulator = apiData.items[currentEmulatorIndex];
            container.innerHTML = '';

            let games = emulator.items || emulator.itens || [];
            games.sort((a, b) => new Date(a.added) - new Date(b.added));
            const filteredGames = games.filter(game => game.title.toLowerCase().includes(searchQuery));

            const safeConsoleImg = getSafeImageUrl(emulator.console);

            const banner = document.createElement('div');
            banner.className = "relative w-full h-44 rounded-2xl overflow-hidden glass-panel mb-12";
            banner.innerHTML = `
                <div class="absolute inset-0 bg-cover bg-center opacity-40" style="background-image: url('${getSafeImageUrl(emulator.background)}');"></div>
                <div class="absolute inset-0 console-banner flex items-center px-8 sm:px-12">
                    <div class="flex items-center gap-6 z-10">
                        ${safeConsoleImg ? `<img src="${safeConsoleImg}" class="h-20 sm:h-28 drop-shadow-[0_0_15px_rgba(255,255,255,0.2)]" />` : ''}
                        <div>
                            <h2 class="text-3xl sm:text-4xl font-black text-white uppercase tracking-tight drop-shadow-lg">${emulator.code}</h2>
                            <p class="text-red-500 font-bold text-xs tracking-widest mt-1 bg-red-600/10 inline-block px-2 py-0.5 rounded border border-red-600/20">${filteredGames.length} JOGOS</p>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(banner);

            const categories = {};
            filteredGames.forEach(g => {
                const cat = g.subcategory || "Geral";
                if (!categories[cat]) categories[cat] = [];
                categories[cat].push(g);
            });

            Object.keys(categories).forEach(catKey => {
                const section = document.createElement('div');
                section.className = "space-y-6";
                section.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="h-5 w-1.5 bg-red-600 rounded-r shadow-[0_0_8px_#dc2626]"></div>
                        <h3 class="text-sm font-black uppercase text-neutral-300 tracking-widest">Categoria ${catKey}</h3>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6" id="cat-grid-${catKey}"></div>
                `;
                container.appendChild(section);
                const grid = document.getElementById(`cat-grid-${catKey}`);

                categories[catKey].forEach(game => {
                    const card = document.createElement('div');
                    const safeCover = getSafeImageUrl(game.cover);
                    card.className = "glass-panel rounded-xl overflow-hidden flex flex-col transition-all cursor-pointer neon-red-glow group relative";
                    card.onclick = () => showInfoModal(escape(JSON.stringify({...game, safeCover})));
                    card.innerHTML = `
                        <div class="relative aspect-[3/4] overflow-hidden bg-[#0a0a0a]">
                            <img src="${safeCover}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy" onerror="this.src='https://via.placeholder.com/300x400/000000/dc2626?text=Capa+Indispon%C3%ADvel'" />
                        </div>
                        <div class="p-3"><h4 class="text-[11px] font-bold text-neutral-300 uppercase line-clamp-2">${game.title}</h4></div>
                    `;
                    grid.appendChild(card);
                });
            });
        }

        function showInfoModal(escapedJson) {
            const game = JSON.parse(unescape(escapedJson));
            document.getElementById('info-game-cover').src = game.safeCover;
            document.getElementById('info-game-title').innerText = game.title;
            document.getElementById('info-game-system').innerText = game.type.replace('_', ' ');
            
            document.getElementById('info-modal-actions').innerHTML = `
                <button onclick="launchEmulator('${game.rom}', '${game.title}', '${game.type}')" 
                        class="w-full bg-red-600 hover:bg-red-500 text-white font-black py-3 rounded-lg text-xs uppercase tracking-widest transition-all shadow-[0_0_15px_rgba(220,38,38,0.5)]">
                    JOGAR AGORA
                </button>
            `;
            document.getElementById('info-modal').classList.remove('hidden');
        }

        function closeInfoModal() { document.getElementById('info-modal').classList.add('hidden'); }

        // ==============================================================
        // INICIA O EMULADOR (LENDO A ROM DIRETO DO JSON/API)
        // ==============================================================
        function launchEmulator(romUrl, title, apiType) {
            closeInfoModal();
            document.getElementById('game-modal').classList.remove('hidden');
            document.getElementById('modal-game-title').innerText = `RODANDO: ${title}`;
            
            // Garantimos que não haja HTTPS forçado no link da ROM
            // E trocamos os espaços por %20 para a Engine aceitar perfeitamente
            const directRomUrl = romUrl.replace(/ /g, '%20').replace('https://', 'http://');

            const core = coreMap[apiType] || 'psx'; 
            const emuContainer = document.getElementById('emulator-container');
            emuContainer.innerHTML = '<div id="game" style="width:100%; height:100%;"></div>'; 

            window.EJS_player = '#game'; 
            window.EJS_core = core;
            
            // A MÁGICA: O Emulador se conecta 100% livre na porta 8080 do seu Servidor de Jogos!
            window.EJS_gameUrl = directRomUrl; 
            
            // A biblioteca do emulador em si pode continuar carregando do HTTPS da CDN sem problemas
            window.EJS_pathtodata = 'https://cdn.emulatorjs.org/stable/data/'; 
            window.EJS_color = '#dc2626'; 
            window.EJS_startOnLoaded = true;

            const script = document.createElement('script');
            script.src = 'https://cdn.emulatorjs.org/stable/data/loader.js';
            document.body.appendChild(script);
        }

        function closeGame() {
            document.getElementById('game-modal').classList.add('hidden');
            if (window.EJS_emulator) {
                try { window.EJS_emulator.destroy(); } catch(e) {}
            }
            document.getElementById('emulator-container').innerHTML = '';
            document.querySelectorAll('script[src*="emulatorjs"]').forEach(s => s.remove());
            window.EJS_gameUrl = null;
        }

        window.onload = init;
    </script>
</body>
</html>
