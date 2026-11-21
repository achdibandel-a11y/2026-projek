<?php

// =========================================================
// ONLINE USER
// =========================================================

$ONLINE_FILE = __DIR__ . '/online_users.json';
$ONLINE_TIMEOUT = 60;

if (isset($_GET['online_ping'])) {

    header('Content-Type: application/json; charset=utf-8');

    $now = time();

    $user_id = $_COOKIE['ytbg_user_id'] ?? '';

    if (!$user_id) {

        $user_id = bin2hex(random_bytes(16));

        setcookie(
            'ytbg_user_id',
            $user_id,
            [
                'expires'  => time() + (86400 * 365),
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax'
            ]
        );

    }

    $users = [];

    if (file_exists($ONLINE_FILE)) {

        $data = @file_get_contents($ONLINE_FILE);

        if ($data) {

            $decoded = json_decode($data, true);

            if (is_array($decoded)) {
                $users = $decoded;
            }

        }

    }

    foreach ($users as $id => $last_seen) {

        if (($now - (int)$last_seen) > $ONLINE_TIMEOUT) {
            unset($users[$id]);
        }

    }

    $users[$user_id] = $now;

    file_put_contents(
        $ONLINE_FILE,
        json_encode($users),
        LOCK_EX
    );

    echo json_encode([
        'success' => true,
        'online'  => count($users)
    ]);

    exit;
}
function getYouTubeVideos($query) {
    $query = urlencode($query);
    $html = @file_get_contents("https://www.youtube.com/results?search_query=$query");
    preg_match_all('/"videoId":"(.*?)"/', $html, $matches);
    preg_match_all('/"title":{"runs":\[{"text":"(.*?)"}/', $html, $titles);
    $ids = array_unique($matches[1] ?? []);
    $titles = array_unique($titles[1] ?? []);
    $results = [];
    foreach ($ids as $i => $id) {
        if (strlen($id) === 11 && isset($titles[$i])) {
            $results[] = ['id' => $id, 'title' => $titles[$i]];
        }
        if (count($results) >= 10) break;
    }
    return $results;
}
function getYouTubeHomeVideos() {
    return getYouTubeVideos("lagu trending");
}
$q = $_GET['q'] ?? '';
$home = true;
$videos = $q ? getYouTubeVideos($q) : getYouTubeHomeVideos();
?>
<!DOCTYPE html>
<html>
<script>
// POLYFILL queueMicrotask (Paling Pertama)
window.queueMicrotask = window.queueMicrotask || function(callback) {
    window.setTimeout(callback, 0);
};
</script>
<head>
    <meta charset="UTF-8">

    <title>YTBG</title>

    <link rel="icon"
          type="image/png"
          sizes="32x32"
          href="https://bandeltv.xyz/karaoke/beta.png">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <script src="https://www.youtube.com/iframe_api"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #0f0f0f;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            display: flex;
            flex-direction: column;
        }

        /* =========================================================
           HEADER
        ========================================================= */
        header {
            flex: 0 0 auto;
            background: #181818;
            border-bottom: 1px solid #292929;
            padding: 10px 18px;
            text-align: center;
        }

        header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        /* =========================================================
           AREA UTAMA
           PLAYER KIRI + SEARCH KANAN
        ========================================================= */
        .container {
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
            padding: 10px;
            overflow: hidden;
        }

        .player-search-area {
            width: 100%;
            height: 100%;
            min-height: 0;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            gap: 10px;
        }

        /* =========================================================
           PLAYER SIDE
        ========================================================= */
        .player-side {
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: #111;
            border-radius: 8px;
            overflow: hidden;
        }

        #playerContainer {
            width: 100%;
            flex: 1 1 auto;
            min-height: 0;
            background: #000;
            border-radius: 8px 8px 0 0;
            position: relative;
            overflow: hidden;
        }

        #player {
            width: 100%;
            height: 100%;
            background: #000;
        }

        #player iframe {
            width: 100% !important;
            height: 100% !important;
            border: 0;
        }

        /* =========================================================
           TOMBOL BACK KETIKA FULLSCREEN (DIPERBAIKI)
        ========================================================= */
        #fsBackBtn {
            display: none;
            position: fixed;
            top: 24px;
            left: 24px;
            z-index: 2147483647;
            background: rgba(15, 15, 15, 0.85);
            color: #f1c40f;
            border: 2px solid #f1c40f;
            border-radius: 30px;
            padding: 10px 22px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.8), 0 0 15px rgba(241, 196, 15, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        #fsBackBtn:hover {
            background: #f1c40f;
            color: #000;
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(241, 196, 15, 0.9);
        }

        /* =========================================================
           TOOLBAR PLAYER
        ========================================================= */
        .player-toolbar {
            flex: 0 0 auto;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #181818;
            border-top: 1px solid #292929;
        }

        .player-toolbar button {
            width: 40px;
            height: 34px;
            padding: 0;
            border: 0;
            border-radius: 17px;
            background: #292929;
            color: #fff;
            font-size: 17px;
            cursor: pointer;
        }

        .player-toolbar button:hover {
            background: #3b3b3b;
        }

        .player-toolbar button.main-play {
            background: #f1c40f;
            color: #000;
        }

        /* =========================================================
           AUDIO VISUALIZER
        ========================================================= */
        .equalizer {
            flex: 0 0 48px;
            height: 48px;
            width: 100%;
            position: relative;
            overflow: hidden;
            background: #111;
            border-top: 1px solid #222;
        }

        #equalizerCanvas {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* =========================================================
           SEARCH SIDE
        ========================================================= */
        .search-side {
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: #151515;
            border: 1px solid #292929;
            border-radius: 8px;
            overflow: hidden;
        }

        .search-box {
            flex: 0 0 auto;
            padding: 10px;
            background: #181818;
            border-bottom: 1px solid #292929;
        }

        #searchForm {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        #searchInput {
            flex: 1 1 auto;
            min-width: 0;
            height: 40px;
            padding: 0 14px;
            background: #101010;
            color: #fff;
            border: 1px solid #555;
            border-radius: 20px;
            outline: none;
            font-size: 14px;
        }

        #searchInput:focus {
            border-color: #f1c40f;
        }

        #searchForm button {
            flex: 0 0 auto;
            height: 40px;
            padding: 0 13px;
            border: 0;
            border-radius: 20px;
            background: #f1c40f;
            color: #000;
            font-weight: 700;
            cursor: pointer;
        }

        .home-button {
            margin-top: 7px;
            width: 100%;
            height: 34px;
            border: 0;
            border-radius: 17px;
            background: #292929;
            color: #fff;
            cursor: pointer;
        }

        .home-button:hover {
            background: #3a3a3a;
        }

        .search-title {
            margin: 0 0 7px;
            font-size: 13px;
            color: #aaa;
        }

        /* =========================================================
           HASIL PENCARIAN
        ========================================================= */
        #videoList {
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 8px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px;
            background: #111;
            scrollbar-width: thin;
        }

        #videoList::-webkit-scrollbar {
            width: 7px;
        }

        #videoList::-webkit-scrollbar-track {
            background: #181818;
        }

        #videoList::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 10px;
        }

        #videoList > div {
            flex: 0 0 auto;
            width: 100%;
            min-height: 82px;
            display: flex;
            flex-direction: row;
            align-items: center;
            padding: 6px;
            background: #202020;
            color: #fff;
            border: 1px solid #292929;
            border-radius: 7px;
            cursor: pointer;
            overflow: hidden;
            transition: .15s;
        }

        #videoList > div:hover {
            background: #303030;
            transform: translateY(-1px);
        }

        #videoList > div.active {
            border: 2px solid #f1c40f;
            background: #303030;
            box-shadow: 0 0 10px rgba(241, 196, 15, .22);
        }

        #videoList > div img {
            flex: 0 0 105px;
            width: 105px;
            height: 60px;
            object-fit: cover;
            margin: 0 9px 0 0;
            border-radius: 5px;
        }

        #videoList > div > div {
            flex: 1 1 auto;
            width: auto;
            padding: 0;
            font-size: 13px;
            line-height: 1.35;
            text-align: left;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
        }

        /* =========================================================
           MOBILE & PORTRAIT LAYOUT: PLAYER FIXED AT TOP, ONLY PLAYLIST SCROLLS
        ========================================================= */
        @media (max-width: 800px), (orientation: portrait) {
            html, body {
                height: 100vh !important;
                overflow: hidden !important;
                position: fixed !important;
                width: 100% !important;
            }

            body {
                display: flex !important;
                flex-direction: column !important;
                height: 100vh !important;
                overflow: hidden !important;
            }

            header {
                flex: 0 0 auto !important;
                padding-top: 10px !important;
            }

            .container {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                width: 100% !important;
                height: 100% !important;
                padding: 4px !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
            }

            .player-search-area {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                width: 100% !important;
                height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
                overflow: hidden !important;
            }

            .player-side {
                flex: 0 0 auto !important;
                width: 100% !important;
                background: #111;
                border-radius: 8px;
                overflow: hidden;
            }

            #playerContainer {
                width: 100% !important;
                aspect-ratio: 16 / 9 !important;
                height: auto !important;
                max-height: 38vh !important;
                position: relative !important;
                background: #000;
            }

            #player iframe {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
            }

            .player-toolbar {
                height: 42px !important;
                flex: 0 0 42px !important;
            }

            .equalizer {
                height: 28px !important;
                flex: 0 0 28px !important;
            }

            .search-side {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                border-radius: 8px;
                overflow: hidden !important;
            }

            .search-box {
                flex: 0 0 auto !important;
                padding: 6px 8px !important;
            }

            .qris-box {
                display: none !important;
            }

            #videoList {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                width: 100% !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
                touch-action: pan-y !important;
                padding: 6px !important;
            }

            header h1 {
                font-size: 18px;
            }
        }

        .youtube-btn {
            display: inline-flex;
            align-items: center;
            vertical-align: middle;
            margin-right: 8px;
            position: relative;
            overflow: hidden;
            border-radius: 6px;
        }

        .youtube-btn svg {
            width: 42px;
            height: 30px;
            display: block;
            position: relative;
            z-index: 1;
        }

        h1 {
            text-align: left;
            white-space: nowrap;
        }

        /* =========================================================
           LANDSCAPE DESKTOP / STB / TV
        ========================================================= */
        @media (orientation: landscape) and (min-width: 801px) {
            html, body {
                width: 100%;
                height: 100%;
                margin: 0;
                overflow: hidden;
            }

            body {
                display: flex;
                flex-direction: column;
            }

            header {
                flex: 0 0 auto;
            }

            .container {
                flex: 1 1 auto;
                min-height: 0;
                width: 100%;
                height: auto;
                padding: 6px;
                overflow: hidden;
            }

            .player-search-area {
                width: 100%;
                height: 100%;
                min-height: 0;
                display: grid;
                grid-template-columns: minmax(0, 1fr) 280px;
                gap: 6px;
            }

            .player-side {
                width: 100%;
                min-width: 0;
                min-height: 0;
                display: flex;
                flex-direction: column;
                background: #111;
                border-radius: 8px;
                overflow: hidden;
            }

            #playerContainer {
                width: 100%;
                flex: 0 0 auto;
                aspect-ratio: 16 / 9;
                height: auto;
                min-height: 490px;
                position: relative;
                background: #000;
                overflow: hidden;
                border-radius: 8px 8px 0 0;
            }

            #player iframe {
                position: absolute;
                top: 0;
                left: 0;
                width: 100% !important;
                height: 100% !important;
                border: 0;
                display: block;
            }

            .player-toolbar {
                flex: 0 0 48px;
                width: 100%;
                height: 48px;
                min-height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                background: #181818;
                border-top: 1px solid #292929;
                z-index: 10;
            }

            .equalizer {
                flex: 0 0 48px;
                width: 100%;
                height: 48px;
                min-height: 48px;
                position: relative;
                background: #111;
                border-top: 1px solid #222;
                overflow: hidden;
                z-index: 10;
            }

            #equalizerCanvas {
                display: block;
                width: 100%;
                height: 100%;
            }

            .search-side {
                width: 280px;
                height: 100%;
                min-width: 0;
                min-height: 0;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            #videoList {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                overflow-x: hidden;
            }
        }

        /* =========================================================
           ONLINE USER
        ========================================================= */
        .online-users {
            width: 100%;
            height: 30px;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #aaa;
            font-size: 12px;
            background: #101010;
            border: 1px solid #292929;
            border-radius: 15px;
        }

        .online-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #2ecc71;
            box-shadow: 0 0 5px #2ecc71, 0 0 10px rgba(46, 204, 113, .5);
            animation: onlinePulse 1.5s infinite;
        }

        #onlineCount {
            color: #2ecc71;
            font-weight: 700;
        }

        @keyframes onlinePulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: .45; transform: scale(.8); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* =========================================================
           QRIS LOGO AREA
        ========================================================= */
        .qris-box {
            width: 100%;
            margin-top: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #111;
            border: 1px solid #292929;
            border-radius: 10px;
            padding: 6px;
            transition: border-color .2s;
        }

        .qris-box:hover {
            border-color: #f1c40f;
        }

        .qris-box img {
            max-width: 100%;
            height: auto;
            max-height: 140px;
            object-fit: contain;
            border-radius: 6px;
            display: block;
        }
    </style>
</head>

<body>

<header>
    <h1>
    <span class="youtube-btn">
        <svg viewBox="0 0 68 48" aria-hidden="true">
            <path d="M66.52 7.11a8.6 8.6 0 0 0-6.06-6.09C55.1 0 34 0 34 0S12.9 0 7.54 1.02a8.6 8.6 0 0 0-6.06 6.09C.46 12.48.46 24 .46 24s0 11.52 1.02 16.89a8.6 8.6 0 0 0 6.06 6.09C12.9 48 34 48 34 48s21.1 0 26.46-1.02a8.6 8.6 0 0 0 6.06-6.09C67.54 35.52 67.54 24 67.54 24s0-11.52-1.02-16.89Z" fill="#FF0000"/>
            <path d="M27 34.5 45 24 27 13.5v21Z" fill="#fff"/>
        </svg>
    </span>
    <strong><em>BandelGroup</em></strong>
</h1>
</header>

<?php if ($videos): ?>

<div class="container">

    <div class="player-search-area">

        <!-- =====================================================
             PLAYER KIRI
        ====================================================== -->
        <div class="player-side">

            <div id="playerContainer">
                <div id="player"></div>
                <!-- TOMBOL BACK KETIKA FULLSCREEN -->
                <button type="button" id="fsBackBtn" onclick="toggleFullscreen()">
                    ⬅ Kembali
                </button>
            </div>

            <!-- TOOLBAR -->
            <div class="player-toolbar">

                <button type="button"
                        title="Previous"
                        onclick="prevVideo()">
                    ⏮
                </button>

                <button type="button"
                        id="playPauseButton"
                        class="main-play"
                        title="Play / Pause"
                        onclick="togglePlayPause()">
                    ▶
                </button>

                <button type="button"
                        title="Next"
                        onclick="nextVideo()">
                    ⏭
                </button>

                <button type="button"
                        title="Mute / Unmute"
                        onclick="toggleMute()">
                    🔊
                </button>

                <button type="button"
                        id="fsToggleBtn"
                        title="Fullscreen / Exit Fullscreen"
                        onclick="toggleFullscreen()">
                    ⛶
                </button>

            </div>

            <!-- EQUALIZER -->
            <div class="equalizer" id="equalizer">
                <canvas id="equalizerCanvas"></canvas>
            </div>

        </div>


        <!-- =====================================================
             SEARCH KANAN
        ====================================================== -->
        <div class="search-side">

            <div class="search-box">

                <div class="search-title">
                    🔍 Pencarian YouTube
                </div>

                <form id="searchForm">

                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Cari video..."
                        value="<?= htmlspecialchars($q) ?>"
                        autocomplete="off"
                    >

                    <button type="submit">
                        Cari
                    </button>

                </form>

                

                <!-- =====================================================
                     ONLINE USER
                ====================================================== -->
                <div class="online-users">

                    <span class="online-dot"></span>

                    <span id="onlineCount">
                        0
                    </span>

                    <span>
                        User Online
                    </span>

                </div>

                <!-- =====================================================
                     QRIS LOGO
                ====================================================== -->
                <div class="qris-box">
                    <img src="http://bandeltv.xyz/logo/qris.jpeg" alt="QRIS BandelTV">
                </div>

            </div>


            <!-- HASIL -->
            <div id="videoList">

                <?php foreach ($videos as $index => $v): ?>

                    <div
                        onclick="playFromList(<?= $index ?>)"
                        id="vid-<?= $index ?>"
                        tabindex="0"
                    >

                        <img
                            src="https://img.youtube.com/vi/<?= $v['id'] ?>/mqdefault.jpg"
                            alt="<?= htmlspecialchars($v['title']) ?>"
                        >

                        <div>
                            <?= htmlspecialchars($v['title']) ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

</div>

<script>

    var playlist = <?= json_encode(array_column($videos, 'id')) ?>;
    var currentIndex = 0;
    var player = null;
    var isMuted = false;

// =========================================================
// VISUAL EQUALIZER
// =========================================================

var equalizerCanvas = document.getElementById('equalizerCanvas');
var equalizerCtx = equalizerCanvas.getContext('2d');
var BAR_COUNT = 48;
var equalizerBars = [];

function resizeEqualizer() {
    var rect = equalizerCanvas.getBoundingClientRect();
    var dpr = window.devicePixelRatio || 1;
    equalizerCanvas.width = Math.max(1, Math.floor(rect.width * dpr));
    equalizerCanvas.height = Math.max(1, Math.floor(rect.height * dpr));
    equalizerCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
}

window.addEventListener('resize', resizeEqualizer);

function initEqualizer() {
    equalizerBars = [];
    for (var i = 0; i < BAR_COUNT; i++) {
        equalizerBars.push({
            value: 0.04,
            target: 0.04,
            phase: (i / BAR_COUNT) * Math.PI * 2
        });
    }
}

function drawEqualizer(time) {
    var width = equalizerCanvas.clientWidth;
    var height = equalizerCanvas.clientHeight;
    equalizerCtx.clearRect(0, 0, width, height);

    var playing = false;
    if (player) {
        try {
            playing = player.getPlayerState() === YT.PlayerState.PLAYING;
        } catch (e) {}
    }

    var gap = 3;
    var barWidth = Math.max(2, (width - ((BAR_COUNT - 1) * gap)) / BAR_COUNT);

    for (var index = 0; index < BAR_COUNT; index++) {
        var bar = equalizerBars[index];
        if (playing) {
            var bass = (Math.sin(time * 0.0045 + bar.phase) + 1) / 2;
            var mid = (Math.sin(time * 0.008 + bar.phase * 1.7) + 1) / 2;
            var high = (Math.sin(time * 0.013 + bar.phase * 2.4) + 1) / 2;
            var center = 1 - Math.abs(index - ((BAR_COUNT - 1) / 2)) / ((BAR_COUNT - 1) / 2);

            bar.target = 0.08 + (bass * 0.42) + (mid * 0.25) + (high * 0.12) + (center * 0.10);
        } else {
            bar.target = 0.035;
        }

        var smoothing = playing ? 0.12 : 0.06;
        bar.value += (bar.target - bar.value) * smoothing;

        var barHeight = Math.max(2, bar.value * (height - 6));
        var x = index * (barWidth + gap);
        var y = height - barHeight;

        var gradient = equalizerCtx.createLinearGradient(0, y, 0, height);
        gradient.addColorStop(0, '#ffffff');
        gradient.addColorStop(0.35, '#f1c40f');
        gradient.addColorStop(1, '#d89f00');

        equalizerCtx.fillStyle = gradient;
        equalizerCtx.beginPath();
        if (typeof equalizerCtx.roundRect === 'function') {
            equalizerCtx.roundRect(x, y, barWidth, barHeight, 2);
        } else {
            equalizerCtx.rect(x, y, barWidth, barHeight);
        }
        equalizerCtx.fill();
    }

    requestAnimationFrame(drawEqualizer);
}

initEqualizer();
resizeEqualizer();
requestAnimationFrame(drawEqualizer);

function resetEqualizer() {
    for (var i = 0; i < equalizerBars.length; i++) {
        equalizerBars[i].value = 0.04;
        equalizerBars[i].target = 0.04;
    }
}

// =========================================================
// FULLSCREEN HANDLER & TOGGLE (DIPERBAIKI)
// =========================================================

function toggleFullscreen() {
    var fsElement = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;

    if (!fsElement) {
        var container = document.getElementById('playerContainer');
        if (container.requestFullscreen) {
            container.requestFullscreen();
        } else if (container.webkitRequestFullscreen) {
            container.webkitRequestFullscreen();
        } else if (container.mozRequestFullScreen) {
            container.mozRequestFullScreen();
        } else if (container.msRequestFullscreen) {
            container.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

// SINKRONISASI TAMPILAN TOMBOL BACK SAAT LAYAR PENUH
function handleFullscreenChange() {
    var fsElement = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
    var backBtn = document.getElementById('fsBackBtn');
    var toggleBtn = document.getElementById('fsToggleBtn');

    if (fsElement) {
        if (backBtn) backBtn.style.display = 'inline-flex';
        if (toggleBtn) toggleBtn.innerHTML = '🗗';
    } else {
        if (backBtn) backBtn.style.display = 'none';
        if (toggleBtn) toggleBtn.innerHTML = '⛶';
    }
}

document.addEventListener('fullscreenchange', handleFullscreenChange);
document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
document.addEventListener('mozfullscreenchange', handleFullscreenChange);
document.addEventListener('MSFullscreenChange', handleFullscreenChange);

// =========================================================
// YOUTUBE READY (DIPERBAIKI DENGAN IZIN FULLSCREEN)
// =========================================================

function onYouTubeIframeAPIReady() {
    player = new YT.Player('player', {
        videoId: playlist[currentIndex],
        playerVars: {
            autoplay: 1,
            controls: 1,
            rel: 0,
            fs: 1
        },
        events: {
            'onReady': function(e) {
                // Beri izin fullscreen pada iframe secara eksplisit
                var iframe = document.querySelector('#player iframe');
                if (iframe) {
                    iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen');
                    iframe.setAttribute('allowfullscreen', 'true');
                }
                e.target.playVideo();
                updatePlayButton();
            },
            'onStateChange': onPlayerStateChange
        }
    });

    highlightActive(currentIndex);
}

function onPlayerStateChange(e) {
    updatePlayButton();
    if (e.data === YT.PlayerState.PLAYING) {
        resetEqualizer();
    }
    if (e.data === YT.PlayerState.ENDED) {
        nextVideo();
    }
}

function togglePlayPause() {
    if (!player) return;
    var state = player.getPlayerState();
    if (state === YT.PlayerState.PLAYING) {
        player.pauseVideo();
    } else {
        player.playVideo();
    }
}

function updatePlayButton() {
    var button = document.getElementById('playPauseButton');
    if (!player || !button) return;
    var state = player.getPlayerState();
    if (state === YT.PlayerState.PLAYING) {
        button.innerHTML = '⏸';
    } else {
        button.innerHTML = '▶';
    }
}

function toggleMute() {
    if (!player) return;
    if (isMuted) {
        player.unMute();
        isMuted = false;
    } else {
        player.mute();
        isMuted = true;
    }
    var buttons = document.querySelectorAll('.player-toolbar button');
    if (buttons[3]) {
        buttons[3].innerHTML = isMuted ? '🔇' : '🔊';
    }
}

function nextVideo() {
    if (!player || !playlist.length) return;
    if (currentIndex < playlist.length - 1) {
        currentIndex++;
        resetEqualizer();
        player.loadVideoById(playlist[currentIndex]);
        highlightActive(currentIndex);
    }
}

function prevVideo() {
    if (!player || !playlist.length) return;
    if (currentIndex > 0) {
        currentIndex--;
        resetEqualizer();
        player.loadVideoById(playlist[currentIndex]);
        highlightActive(currentIndex);
    }
}

function playFromList(index) {
    if (!player || !playlist[index]) return;
    currentIndex = index;
    player.loadVideoById(playlist[currentIndex]);
    highlightActive(currentIndex);
}

function highlightActive(index) {
    var items = document.querySelectorAll('#videoList > div');
    items.forEach(function(el) {
        el.classList.remove('active');
    });
    var activeItem = document.getElementById('vid-' + index);
    if (activeItem) {
        activeItem.classList.add('active');
    }
}

// SEARCH
document.getElementById('searchForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var searchInput = document.getElementById('searchInput');
    var query = searchInput.value.trim();
    if (!query) return;

    try {
        var response = await fetch('ytsearch.php?q=' + encodeURIComponent(query));
        if (!response.ok) throw new Error('HTTP ' + response.status);

        var data = await response.json();
        if (!data.success || !data.results || data.results.length === 0) {
            document.getElementById('videoList').innerHTML = '<div style="color:#ff6b6b;padding:20px;text-align:center;">❌ Tidak ada hasil ditemukan.</div>';
            return;
        }

        playlist = data.results.map(function(v) { return v.id; });
        currentIndex = 0;

        var videoList = document.getElementById('videoList');
        videoList.innerHTML = '';

        data.results.forEach(function(video, index) {
            var item = document.createElement('div');
            item.id = 'vid-' + index;
            item.tabIndex = 0;
            item.onclick = function() {
                playSearchVideo(video.id, index);
            };
            item.innerHTML = '<img src="https://img.youtube.com/vi/' + video.id + '/mqdefault.jpg" alt="' + escapeHTML(video.title) + '"><div>' + escapeHTML(video.title) + '</div>';
            videoList.appendChild(item);
        });
    } catch (error) {
        console.log('[SEARCH ERROR]', error);
    }
});

function playSearchVideo(videoID, index) {
    if (!player) return;
    currentIndex = index;
    resetEqualizer();
    player.loadVideoById(videoID);
    highlightActive(index);
}

function escapeHTML(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ONLINE HEARTBEAT
function updateOnlineUser() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?online_ping=1&_=' + new Date().getTime(), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data && data.success && typeof data.online !== 'undefined') {
                    var onlineCount = document.getElementById('onlineCount');
                    if (onlineCount) onlineCount.textContent = data.online;
                }
            } catch(e) {}
        }
    };
    xhr.send();
}

updateOnlineUser();
setInterval(updateOnlineUser, 20000);

</script>

<?php elseif ($q): ?>

    <p style="color:red; text-align:center; padding:20px;">
        Tidak bisa ambil video. Coba kata kunci lain.
    </p>

<?php endif; ?>

</body>
</html>




