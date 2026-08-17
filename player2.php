<?php
/*
=========================================================
 SONA TV - UNIVERSAL FAST PLAYER
 --------------------------------------------------------
 Supports:
 - MPEG-TS Live
 - HLS / M3U8
 - DASH / MPD
 - MP4
 - WebM
 - OGG
 - Native browser formats

 Engines:
 - Native HTML5 Video
 - mpegts.js
 - hls.js
 - dash.js

 Modes:
 - DIRECT
 - PROXY
 - Automatic fallback

 Optimized for:
 - InfinityFree
 - IPTV Live
 - Movies
 - Series
 - Mobile browsers
=========================================================
*/

ini_set('display_errors', '0');
error_reporting(0);

@ini_set('output_buffering', '0');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');

while (ob_get_level() > 0) {
    @ob_end_flush();
}

@ob_implicit_flush(true);

set_time_limit(0);
ignore_user_abort(true);


/* =====================================================
   PARAMETERS
===================================================== */

$source = trim($_GET['url'] ?? '');
$title  = trim($_GET['title'] ?? 'SONA TV');

$nextUrl   = trim($_GET['next_url'] ?? '');
$nextTitle = trim($_GET['next_title'] ?? '');

$proxyMode = (
    isset($_GET['proxy']) &&
    $_GET['proxy'] === '1'
);


/* =====================================================
   BASIC VALIDATION
===================================================== */

if ($source === '') {

    http_response_code(400);

    exit('Missing URL');
}


if (!preg_match('~^https?://~i', $source)) {

    http_response_code(400);

    exit('Invalid URL');
}


/* =====================================================
   SECURITY
===================================================== */

function valid_remote_url($url)
{
    if (!preg_match('~^https?://~i', $url)) {
        return false;
    }

    $parts = @parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return false;
    }

    return true;
}


/* =====================================================
   PROXY
===================================================== */

function proxy_stream($target)
{
    if (!valid_remote_url($target)) {

        http_response_code(400);

        exit('Invalid proxy URL');
    }


    /*
    -----------------------------------------------------
    IMPORTANT
    -----------------------------------------------------
    Disable compression/buffering because live MPEG-TS
    must be sent to browser immediately.
    */

    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    @ob_implicit_flush(true);


    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header(
        'Access-Control-Allow-Headers: ' .
        'Range, Origin, Accept, Content-Type, User-Agent'
    );

    header(
        'Access-Control-Expose-Headers: ' .
        'Content-Length, Content-Range, Accept-Ranges, Content-Type'
    );

    header('Accept-Ranges: bytes');

    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    header('X-Accel-Buffering: no');


    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        ===
        'OPTIONS'
    ) {

        http_response_code(204);

        exit;
    }


    /*
    -----------------------------------------------------
    REQUEST HEADERS
    -----------------------------------------------------
    */

    $headers = [

        'User-Agent: VLC/3.0.21',

        'Accept: */*',

        'Connection: keep-alive'

    ];


    /*
    -----------------------------------------------------
    RANGE
    -----------------------------------------------------
    */

    if (
        !empty($_SERVER['HTTP_RANGE'])
    ) {

        $headers[] =
            'Range: ' .
            $_SERVER['HTTP_RANGE'];
    }


    /*
    -----------------------------------------------------
    CURL
    -----------------------------------------------------
    */

    $ch = curl_init($target);


    curl_setopt_array(
        $ch,
        [

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_MAXREDIRS =>
                10,

            CURLOPT_RETURNTRANSFER =>
                false,

            CURLOPT_HEADER =>
                false,

            CURLOPT_HTTPHEADER =>
                $headers,

            CURLOPT_USERAGENT =>
                'VLC/3.0.21',

            CURLOPT_SSL_VERIFYPEER =>
                false,

            CURLOPT_SSL_VERIFYHOST =>
                false,

            CURLOPT_CONNECTTIMEOUT =>
                10,

            CURLOPT_TIMEOUT =>
                0,

            CURLOPT_BUFFERSIZE =>
                16384,

            CURLOPT_TCP_NODELAY =>
                true,

            CURLOPT_HTTP_VERSION =>
                CURL_HTTP_VERSION_1_1,

            CURLOPT_ENCODING =>
                '',


            /*
            -------------------------------------------------
            HEADER CALLBACK
            -------------------------------------------------
            */

            CURLOPT_HEADERFUNCTION =>
                function (
                    $ch,
                    $header
                ) {

                    $trim =
                        trim($header);


                    /*
                    Content-Type
                    */

                    if (
                        preg_match(
                            '~^Content-Type:\s*(.+)$~i',
                            $trim,
                            $m
                        )
                    ) {

                        $type =
                            trim($m[1]);

                        /*
                        Prevent dangerous/invalid header
                        */

                        if (
                            preg_match(
                                '~^[a-zA-Z0-9.+/_;-]+$~',
                                $type
                            )
                        ) {

                            header(
                                'Content-Type: ' .
                                $type
                            );
                        }
                    }


                    /*
                    Content-Length
                    */

                    if (
                        preg_match(
                            '~^Content-Length:\s*(.+)$~i',
                            $trim,
                            $m
                        )
                    ) {

                        header(
                            'Content-Length: ' .
                            trim($m[1])
                        );
                    }


                    /*
                    Content-Range
                    */

                    if (
                        preg_match(
                            '~^Content-Range:\s*(.+)$~i',
                            $trim,
                            $m
                        )
                    ) {

                        header(
                            'Content-Range: ' .
                            trim($m[1])
                        );
                    }


                    /*
                    Accept-Ranges
                    */

                    if (
                        preg_match(
                            '~^Accept-Ranges:\s*(.+)$~i',
                            $trim,
                            $m
                        )
                    ) {

                        header(
                            'Accept-Ranges: ' .
                            trim($m[1])
                        );
                    }


                    /*
                    HTTP status
                    */

                    if (
                        preg_match(
                            '~^HTTP/\d+(?:\.\d+)?\s+(\d+)~i',
                            $trim,
                            $m
                        )
                    ) {

                        $code =
                            (int)$m[1];

                        if (
                            $code >= 400
                        ) {

                            http_response_code(
                                $code
                            );
                        }
                    }


                    return strlen(
                        $header
                    );
                },


            /*
            -------------------------------------------------
            STREAM DATA
            -------------------------------------------------
            */

            CURLOPT_WRITEFUNCTION =>
                function (
                    $ch,
                    $data
                ) {

                    echo $data;

                    /*
                    Immediately send data.
                    */

                    if (
                        function_exists(
                            'ob_flush'
                        )
                    ) {

                        @ob_flush();
                    }

                    flush();

                    return strlen(
                        $data
                    );
                }

        ]
    );


    /*
    -----------------------------------------------------
    EXECUTE
    -----------------------------------------------------
    */

    $ok =
        curl_exec($ch);


    if (
        $ok === false
    ) {

        $error =
            curl_error($ch);

        curl_close($ch);

        if (
            !headers_sent()
        ) {

            http_response_code(502);

            header(
                'Content-Type: text/plain; charset=utf-8'
            );
        }

        echo
            'Proxy connection error: ' .
            $error;

        exit;
    }


    curl_close($ch);

    exit;
}


/* =====================================================
   PROXY REQUEST
===================================================== */

if ($proxyMode) {

    proxy_stream($source);

    exit;
}


/* =====================================================
   BUILD BASE URL
===================================================== */

$self =
    $_SERVER['PHP_SELF'] ??
    '/player.php';

$host =
    $_SERVER['HTTP_HOST'] ??
    '';

$scheme =
    (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    )
        ? 'https'
        : 'http';


$base =
    $scheme .
    '://' .
    $host .
    $self;


/* =====================================================
   PROXY URL
===================================================== */

$proxyURL =
    $base .
    '?proxy=1&url=' .
    rawurlencode(
        $source
    );


/* =====================================================
   DETECT SOURCE
===================================================== */

$lower =
    strtolower(
        $source
    );


$path =
    strtolower(
        parse_url(
            $source,
            PHP_URL_PATH
        ) ?? ''
    );


$isM3U8 =
    strpos(
        $lower,
        '.m3u8'
    ) !== false;


$isMPD =
    strpos(
        $lower,
        '.mpd'
    ) !== false;


$isTS =
    (
        strpos(
            $lower,
            '.ts'
        ) !== false
        ||
        strpos(
            $lower,
            '/live/'
        ) !== false
    );


$isLive =
    (
        strpos(
            $lower,
            '/live/'
        ) !== false
        ||
        $isM3U8
        ||
        $isTS
    );


$isMovie =
    strpos(
        $lower,
        '/movie/'
    ) !== false;


$isSeries =
    strpos(
        $lower,
        '/series/'
    ) !== false;


/* =====================================================
   SOURCE TYPE
===================================================== */

if ($isM3U8) {

    $sourceType = 'hls';

}
elseif ($isMPD) {

    $sourceType = 'dash';

}
elseif ($isTS) {

    $sourceType = 'mpegts';

}
else {

    $sourceType = 'native';
}


/* =====================================================
   MX PLAYER
===================================================== */

$mxURL =
    'intent://' .
    preg_replace(
        '~^https?://~i',
        '',
        $source
    ) .
    '#Intent;' .
    'scheme=http;' .
    'type=video/*;' .
    'package=com.mxtech.videoplayer.ad;' .
    'end';


/* =====================================================
   VLC ANDROID
===================================================== */

$vlcURL =
    'vlc://' .
    preg_replace(
        '~^https?://~i',
        '',
        $source
    );


/* =====================================================
   JSON
===================================================== */

$sourceJSON =
    json_encode(
        $source,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

$proxyJSON =
    json_encode(
        $proxyURL,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

$mxJSON =
    json_encode(
        $mxURL,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

$vlcJSON =
    json_encode(
        $vlcURL,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

$nextUrlJSON =
    json_encode(
        $nextUrl,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

$nextTitleJSON =
    json_encode(
        $nextTitle,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

$sourceTypeJSON =
    json_encode(
        $sourceType
    );

?>
<!DOCTYPE html>

<html
lang="ar"
dir="rtl"
>

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"
>

<title>
<?= htmlspecialchars(
    $title,
    ENT_QUOTES,
    'UTF-8'
) ?>
</title>


<!-- ==================================================
     PLAYER ENGINES
================================================== -->

<script
src="https://cdn.jsdelivr.net/npm/mpegts.js@1.8.0/dist/mpegts.min.js"
defer>
</script>

<script
src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"
defer>
</script>

<script
src="https://cdn.jsdelivr.net/npm/dashjs@4.7.4/dist/modern/umd/dash.all.min.js"
defer>
</script>


<style>

/* =====================================================
   RESET
===================================================== */

*{

    box-sizing:border-box;

    -webkit-tap-highlight-color:
        transparent;

}


html,
body{

    margin:0;

    padding:0;

    background:#050505;

    color:#fff;

    font-family:
        Arial,
        system-ui,
        sans-serif;

}


body{

    min-height:100vh;

    padding-bottom:20px;

}


/* =====================================================
   TOP
===================================================== */

.top{

    position:sticky;

    top:0;

    z-index:1000;

    height:58px;

    display:flex;

    align-items:center;

    gap:10px;

    padding:
        8px 12px;

    background:
        rgba(
            15,
            15,
            15,
            .96
        );

    border-bottom:
        1px solid #242424;

}


.back{

    width:40px;

    height:40px;

    border:0;

    border-radius:10px;

    background:#1b1b1b;

    color:#fff;

    font-size:20px;

}


.title{

    flex:1;

    min-width:0;

    font-size:14px;

    font-weight:bold;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;

}


/* =====================================================
   PLAYER
===================================================== */

.player{

    width:100%;

    background:#000;

    position:relative;

    overflow:hidden;

}


video{

    display:block;

    width:100%;

    height:auto;

    min-height:220px;

    max-height:82vh;

    background:#000;

    object-fit:contain;

}


/* =====================================================
   LOADING
===================================================== */

.loading{

    position:absolute;

    inset:0;

    z-index:20;

    display:flex;

    align-items:center;

    justify-content:center;

    text-align:center;

    padding:20px;

    background:
        rgba(
            0,
            0,
            0,
            .75
        );

    font-size:14px;

}


.loading.hide{

    display:none;

}


/* =====================================================
   LIVE DOT
===================================================== */

.live-dot{

    display:inline-block;

    width:8px;

    height:8px;

    background:#e50914;

    border-radius:50%;

    margin-left:5px;

    animation:
        pulse 1s infinite;

}


@keyframes pulse{

    0%{
        opacity:1;
    }

    50%{
        opacity:.25;
    }

    100%{
        opacity:1;
    }

}


/* =====================================================
   STATUS
===================================================== */

.status{

    margin:
        10px 12px;

    padding:
        10px;

    background:#111;

    border:
        1px solid #252525;

    border-radius:10px;

    color:#aaa;

    font-size:11px;

    text-align:center;

}


/* =====================================================
   BUTTONS
===================================================== */

.buttons{

    display:grid;

    grid-template-columns:
        repeat(
            2,
            1fr
        );

    gap:8px;

    padding:
        10px 12px;

}


.btn{

    min-height:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    border:
        1px solid #292929;

    border-radius:10px;

    background:#151515;

    color:#fff;

    text-decoration:none;

    font-size:11px;

    cursor:pointer;

}


.btn:active{

    transform:scale(.98);

}


.primary{

    background:#e50914;

    border-color:#e50914;

}


/* =====================================================
   DIAGNOSTIC
===================================================== */

.diagnostic{

    margin:
        0 12px;

    border:
        1px solid #252525;

    border-radius:10px;

    overflow:hidden;

    background:#0c0c0c;

}


.diag-title{

    padding:12px;

    background:#151515;

    font-size:12px;

    cursor:pointer;

}


.diag{

    display:none;

    direction:ltr;

    text-align:left;

    padding:12px;

    color:#aaa;

    font-family:
        monospace;

    font-size:10px;

    line-height:1.6;

    max-height:400px;

    overflow:auto;

    white-space:pre-wrap;

    word-break:break-word;

}


.diag.show{

    display:block;

}


/* =====================================================
   NEXT
===================================================== */

.next-overlay{

    position:absolute;

    inset:0;

    z-index:50;

    display:none;

    align-items:center;

    justify-content:center;

    flex-direction:column;

    background:
        rgba(
            0,
            0,
            0,
            .9
        );

    text-align:center;

    padding:20px;

}


.next-overlay.show{

    display:flex;

}


.next-title-text{

    color:#aaa;

    margin-top:8px;

    font-size:12px;

}


.next-btn-action{

    margin-top:15px;

    padding:
        10px 20px;

    background:#e50914;

    border-radius:8px;

    color:#fff;

    text-decoration:none;

    font-size:12px;

}


/* =====================================================
   MOBILE
===================================================== */

@media(
    max-width:550px
){

    video{

        min-height:
            210px;

    }

    .buttons{

        gap:7px;

        padding:
            9px;

    }

    .btn{

        font-size:10px;

    }

}

</style>

</head>


<body>


<!-- ==================================================
     TOP
================================================== -->

<div class="top">

    <button
        class="back"
        onclick="history.back()"
    >
        ←
    </button>

    <div class="title">

        <?= htmlspecialchars(
            $title,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

</div>


<!-- ==================================================
     PLAYER
================================================== -->

<div class="player">

    <video
        id="video"
        controls
        playsinline
        webkit-playsinline
        preload="auto"
        crossorigin="anonymous"
    ></video>


    <div
        id="loading"
        class="loading"
    >

        ⏳ جاري تجهيز المشغل...

    </div>


    <div
        id="nextOverlay"
        class="next-overlay"
    >

        <div>

            ▶️ تشغيل الحلقة التالية

        </div>


        <div
            id="nextTitleText"
            class="next-title-text"
        ></div>


        <a
            id="nextBtnLink"
            href="#"
            class="next-btn-action"
        >

            تشغيل الآن

        </a>

    </div>

</div>


<!-- ==================================================
     STATUS
================================================== -->

<div
    id="status"
    class="status"
>

    ⏳ جاري تجهيز المشغل...

</div>


<!-- ==================================================
     BUTTONS
================================================== -->

<div class="buttons">

    <button
        class="btn primary"
        onclick="restartPlayer()"
    >

        🔄 إعادة الاتصال

    </button>


    <button
        class="btn"
        onclick="fullscreenPlayer()"
    >

        ⛶ ملء الشاشة

    </button>


    <button
        class="btn"
        onclick="pipPlayer()"
    >

        📺 Picture in Picture

    </button>


    <button
        class="btn"
        onclick="toggleMode()"
    >

        🔁 Direct / Proxy

    </button>


    <a
        id="mx"
        class="btn primary"
        href="#"
    >

        ▶️ MX Player

    </a>


    <a
        id="vlc"
        class="btn"
        href="#"
    >

        ▶️ VLC

    </a>


    <button
        class="btn"
        onclick="copySource()"
    >

        📋 نسخ الرابط

    </button>


    <button
        class="btn"
        onclick="copyProxy()"
    >

        🔗 نسخ Proxy

    </button>

</div>


<!-- ==================================================
     DIAGNOSTIC
================================================== -->

<div class="diagnostic">

    <div
        class="diag-title"
        onclick="toggleDiagnostic()"
    >

        🔍 التشخيص ومعلومات المصدر

    </div>


    <div
        id="diag"
        class="diag"
    ></div>

</div>


<script>

/* =====================================================
   GLOBALS
===================================================== */

const SOURCE =
    <?= $sourceJSON ?>;


const PROXY =
    <?= $proxyJSON ?>;


const SOURCE_TYPE =
    <?= $sourceTypeJSON ?>;


const MX =
    <?= $mxJSON ?>;


const VLC =
    <?= $vlcJSON ?>;


const NEXT_URL =
    <?= $nextUrlJSON ?>;


const NEXT_TITLE =
    <?= $nextTitleJSON ?>;


const IS_LIVE =
    <?= $isLive ? 'true' : 'false' ?>;


const video =
    document.getElementById(
        'video'
    );


const loading =
    document.getElementById(
        'loading'
    );


const statusBox =
    document.getElementById(
        'status'
    );


const diag =
    document.getElementById(
        'diag'
    );


const nextOverlay =
    document.getElementById(
        'nextOverlay'
    );


const nextTitleText =
    document.getElementById(
        'nextTitleText'
    );


const nextBtnLink =
    document.getElementById(
        'nextBtnLink'
    );


let mpegPlayer =
    null;


let hlsPlayer =
    null;


let dashPlayer =
    null;


let currentURL =
    '';


let currentMode =
    'direct';


let started =
    false;


let reconnecting =
    false;


let firstPlay =
    false;


let lastErrorTime =
    0;


let reconnectCount =
    0;


let countdownTimer =
    null;


/* =====================================================
   LOG
===================================================== */

function log(text)
{

    const now =
        new Date()
            .toLocaleTimeString(
                'ar-EG'
            );


    diag.textContent +=
        now +
        ' | ' +
        text +
        '\n';


    diag.scrollTop =
        diag.scrollHeight;
}


/* =====================================================
   STATUS
===================================================== */

function setStatus(text)
{

    statusBox.innerHTML =
        text;
}


/* =====================================================
   LOADING
===================================================== */

function showLoading(text)
{

    loading.textContent =
        text;

    loading.classList.remove(
        'hide'
    );
}


function hideLoading()
{

    loading.classList.add(
        'hide'
    );
}


/* =====================================================
   WAIT FOR LIBRARIES
===================================================== */

function librariesReady()
{

    return (
        window.mpegts ||
        window.Hls ||
        window.dashjs
    );
}


function waitForLibraries()
{

    let attempts =
        0;


    const timer =
        setInterval(
            function(){

                attempts++;


                if (
                    librariesReady()
                ){

                    clearInterval(
                        timer
                    );

                    startPlayback();

                    return;
                }


                if (
                    attempts >= 50
                ){

                    clearInterval(
                        timer
                    );

                    log(
                        'PLAYER LIBRARIES TIMEOUT'
                    );


                    startNative(
                        currentURL
                    );

                }

            },
            200
        );
}


/* =====================================================
   CLEAN ENGINE
===================================================== */

function cleanPlayer()
{

    try{

        if (
            mpegPlayer
        ){

            mpegPlayer.pause();

            mpegPlayer.unload();

            mpegPlayer.detachMediaElement();

            mpegPlayer.destroy();

            mpegPlayer =
                null;
        }

    }catch(e){

        log(
            'MPEG cleanup error'
        );
    }


    try{

        if (
            hlsPlayer
        ){

            hlsPlayer.destroy();

            hlsPlayer =
                null;
        }

    }catch(e){

        log(
            'HLS cleanup error'
        );
    }


    try{

        if (
            dashPlayer
        ){

            dashPlayer.reset();

            dashPlayer =
                null;
        }

    }catch(e){

        log(
            'DASH cleanup error'
        );
    }


    try{

        video.pause();

    }catch(e){}


    video.removeAttribute(
        'src'
    );


    video.load();
}


/* =====================================================
   URL
===================================================== */

function getURL()
{

    if (
        currentMode ===
        'proxy'
    ){

        return PROXY;
    }


    return SOURCE;
}


/* =====================================================
   SOURCE TYPE
===================================================== */

function getType(url)
{

    const u =
        url.toLowerCase();


    if (
        u.includes(
            '.m3u8'
        )
    ){

        return 'hls';
    }


    if (
        u.includes(
            '.mpd'
        )
    ){

        return 'dash';
    }


    if (
        u.includes(
            '.ts'
        )
        ||
        u.includes(
            '/live/'
        )
    ){

        return 'mpegts';
    }


    return 'native';
}


/* =====================================================
   NATIVE
===================================================== */

function startNative(url)
{

    log(
        'NATIVE START: ' +
        url
    );


    video.src =
        url;


    video.load();


    const p =
        video.play();


    if (
        p &&
        p.catch
    ){

        p.catch(
            function(){

                log(
                    'NATIVE AUTOPLAY BLOCKED'
                );

                hideLoading();

                setStatus(
                    '▶️ اضغط زر التشغيل'
                );

            }
        );
    }
}


/* =====================================================
   HLS
===================================================== */

function startHLS(url)
{

    log(
        'HLS START: ' +
        url
    );


    /*
    Native HLS
    */

    if (
        video.canPlayType(
            'application/vnd.apple.mpegurl'
        )
    ){

        video.src =
            url;


        video.load();


        setStatus(
            IS_LIVE
                ? '📺 HLS LIVE'
                : '▶️ HLS'
        );


        const p =
            video.play();


        if (
            p &&
            p.catch
        ){

            p.catch(
                function(){}
            );
        }


        return;
    }


    /*
    HLS.js
    */

    if (
        window.Hls &&
        Hls.isSupported()
    ){

        log(
            'HLS.JS SUPPORTED'
        );


        hlsPlayer =
            new Hls({

                enableWorker:
                    true,

                lowLatencyMode:
                    true,

                backBufferLength:
                    5,

                maxBufferLength:
                    IS_LIVE
                        ? 8
                        : 20,

                maxMaxBufferLength:
                    IS_LIVE
                        ? 12
                        : 30,

                liveSyncDurationCount:
                    2,

                liveMaxLatencyDurationCount:
                    4,

                maxBufferHole:
                    0.5,

                highBufferWatchdogPeriod:
                    1,

                manifestLoadingMaxRetry:
                    2,

                levelLoadingMaxRetry:
                    3,

                fragLoadingMaxRetry:
                    3

            });


        hlsPlayer.on(
            Hls.Events.MANIFEST_PARSED,
            function(){

                log(
                    'HLS MANIFEST READY'
                );


                hideLoading();


                setStatus(
                    IS_LIVE
                        ? '📺 HLS LIVE • جاهز'
                        : '▶️ الفيديو جاهز'
                );


                video.play()
                    .catch(
                        function(){}
                    );
            }
        );


        hlsPlayer.on(
            Hls.Events.ERROR,
            function(
                event,
                data
            ){

                log(
                    'HLS ERROR: ' +
                    JSON.stringify(
                        data
                    )
                );


                if (
                    data.fatal
                ){

                    /*
                    Try recovery before switching mode.
                    */

                    if (
                        data.type ===
                        Hls.ErrorTypes.NETWORK_ERROR
                    ){

                        try{

                            hlsPlayer.startLoad();

                            return;

                        }catch(e){}
                    }


                    if (
                        data.type ===
                        Hls.ErrorTypes.MEDIA_ERROR
                    ){

                        try{

                            hlsPlayer.recoverMediaError();

                            return;

                        }catch(e){}
                    }


                    switchToProxy(
                        'HLS FATAL'
                    );
                }

            }
        );


        hlsPlayer.loadSource(
            url
        );


        hlsPlayer.attachMedia(
            video
        );


        return;
    }


    /*
    Final fallback.
    */

    startNative(
        url
    );
}


/* =====================================================
   MPEGTS
===================================================== */

function startMPEGTS(url)
{

    log(
        'MPEGTS START: ' +
        url
    );


    if (
        !window.mpegts
    ){

        log(
            'MPEGTS LIBRARY NOT READY'
        );


        startNative(
            url
        );

        return;
    }


    if (
        !mpegts.isSupported()
    ){

        log(
            'MPEGTS NOT SUPPORTED'
        );


        startNative(
            url
        );

        return;
    }


    mpegPlayer =
        mpegts.createPlayer(

            {

                type:
                    'mpegts',

                url:
                    url,

                isLive:
                    IS_LIVE,

                hasAudio:
                    true,

                hasVideo:
                    true

            },

            {

                enableWorker:
                    true,

                lazyLoad:
                    false,

                lazyLoadMaxDuration:
                    0,

                autoCleanupSourceBuffer:
                    true,

                autoCleanupMaxBackwardDuration:
                    10,

                autoCleanupMinBackwardDuration:
                    5,

                liveBufferLatencyChasing:
                    false,

                liveBufferLatencyMaxLatency:
                    6,

                liveBufferLatencyMinRemain:
                    1,

                liveBufferLatencyChasingOnPaused:
                    false,

                fixAudioTimestampGap:
                    true,

                accurateSeek:
                    false,

                seekType:
                    'range',

                seekParamStart:
                    'starttime',

                seekParamEnd:
                    'endtime'

            }
        );


    /*
    MEDIA INFO
    */

    mpegPlayer.on(
        mpegts.Events.MEDIA_INFO,
        function(info){

            log(
                'MEDIA INFO: ' +
                JSON.stringify(
                    info
                )
            );


            hideLoading();


            if (
                IS_LIVE
            ){

                setStatus(
                    '📺 <span class="live-dot"></span> LIVE • ' +
                    (
                        info.width ||
                        ''
                    ) +
                    '×' +
                    (
                        info.height ||
                        ''
                    )
                );

            }
            else{

                setStatus(
                    '▶️ الفيديو جاهز'
                );
            }

        }
    );


    /*
    LOADING COMPLETE
    */

    mpegPlayer.on(
        mpegts.Events.LOADING_COMPLETE,
        function(){

            log(
                'MPEGTS LOADING COMPLETE'
            );

        }
    );


    /*
    ERROR
    */

    mpegPlayer.on(
        mpegts.Events.ERROR,
        function(
            type,
            detail,
            info
        ){

            log(
                'MPEGTS ERROR: ' +
                type +
                ' / ' +
                detail +
                ' / ' +
                JSON.stringify(
                    info || {}
                )
            );


            const now =
                Date.now();


            /*
            Don't instantly restart.
            */

            if (
                now -
                lastErrorTime
                <
                3000
            ){

                return;
            }


            lastErrorTime =
                now;


            /*
            Direct failed:
            switch to proxy.
            */

            if (
                currentMode ===
                'direct'
            ){

                switchToProxy(
                    'MPEGTS NETWORK ERROR'
                );

            }

        }
    );


    /*
    ATTACH
    */

    mpegPlayer.attachMediaElement(
        video
    );


    /*
    LOAD
    */

    mpegPlayer.load();


    /*
    PLAY
    */

    const p =
        mpegPlayer.play();


    if (
        p &&
        p.catch
    ){

        p.catch(
            function(){

                log(
                    'MPEGTS AUTOPLAY BLOCKED'
                );


                hideLoading();


                setStatus(
                    '▶️ اضغط زر التشغيل'
                );

            }
        );
    }
}


/* =====================================================
   DASH
===================================================== */

function startDASH(url)
{

    log(
        'DASH START: ' +
        url
    );


    if (
        !window.dashjs
    ){

        log(
            'DASH.JS NOT AVAILABLE'
        );


        startNative(
            url
        );

        return;
    }


    try{

        dashPlayer =
            dashjs.MediaPlayer()
                .create();


        dashPlayer.updateSettings({

            streaming: {

                lowLatencyEnabled:
                    IS_LIVE,

                liveDelay:
                    IS_LIVE
                        ? 3
                        : 10,

                buffer: {

                    fastSwitchEnabled:
                        true,

                    stableBufferTime:
                        IS_LIVE
                            ? 3
                            : 12,

                    bufferTimeAtTopQuality:
                        IS_LIVE
                            ? 5
                            : 20,

                    bufferTimeAtTopQualityLongForm:
                        IS_LIVE
                            ? 5
                            : 30

                }

            }

        });


        dashPlayer.on(
            dashjs.MediaPlayer.events.STREAM_INITIALIZED,
            function(){

                log(
                    'DASH STREAM READY'
                );


                hideLoading();


                setStatus(
                    IS_LIVE
                        ? '📺 DASH LIVE • جاهز'
                        : '▶️ DASH جاهز'
                );


                video.play()
                    .catch(
                        function(){}
                    );

            }
        );


        dashPlayer.on(
            dashjs.MediaPlayer.events.ERROR,
            function(e){

                log(
                    'DASH ERROR: ' +
                    JSON.stringify(
                        e
                    )
                );

            }
        );


        dashPlayer.initialize(
            video,
            url,
            true
        );


    }catch(e){

        log(
            'DASH EXCEPTION: ' +
            e.message
        );


        startNative(
            url
        );
    }
}


/* =====================================================
   START ENGINE
===================================================== */

function startPlayback()
{

    cleanPlayer();


    started =
        false;


    reconnecting =
        false;


    currentURL =
        getURL();


    const type =
        getType(
            SOURCE
        );


    log(
        '================================'
    );


    log(
        'SONA UNIVERSAL PLAYER'
    );


    log(
        'SOURCE TYPE: ' +
        type
    );


    log(
        'MODE: ' +
        currentMode.toUpperCase()
    );


    log(
        'URL: ' +
        currentURL
    );


    log(
        '================================'
    );


    showLoading(
        IS_LIVE
            ? '📺 جاري الاتصال بالبث...'
            : '⏳ جاري تجهيز الفيديو...'
    );


    if (
        type ===
        'mpegts'
    ){

        startMPEGTS(
            currentURL
        );

    }

    else if (
        type ===
        'hls'
    ){

        startHLS(
            currentURL
        );

    }

    else if (
        type ===
        'dash'
    ){

        startDASH(
            currentURL
        );

    }

    else{

        startNative(
            currentURL
        );

    }

}


/* =====================================================
   DIRECT → PROXY
===================================================== */

function switchToProxy(reason)
{

    if (
        currentMode ===
        'proxy'
    ){

        return;
    }


    if (
        reconnecting
    ){

        return;
    }


    reconnecting =
        true;


    log(
        'DIRECT FAILED → PROXY'
    );


    log(
        'REASON: ' +
        reason
    );


    currentMode =
        'proxy';


    setStatus(
        '🔗 جاري التحويل إلى Proxy...'
    );


    setTimeout(
        function(){

            reconnecting =
                false;


            startPlayback();

        },
        250
    );
}


/* =====================================================
   TOGGLE DIRECT / PROXY
===================================================== */

function toggleMode()
{

    if (
        currentMode ===
        'direct'
    ){

        currentMode =
            'proxy';

    }
    else{

        currentMode =
            'direct';

    }


    log(
        'MANUAL MODE: ' +
        currentMode.toUpperCase()
    );


    startPlayback();
}


/* =====================================================
   RESTART
===================================================== */

function restartPlayer()
{

    reconnectCount++;


    log(
        'MANUAL RECONNECT #' +
        reconnectCount
    );


    startPlayback();
}


/* =====================================================
   VIDEO PLAYING
===================================================== */

video.addEventListener(
    'playing',
    function(){

        started =
            true;


        hideLoading();


        if (
            IS_LIVE
        ){

            setStatus(
                '📺 <span class="live-dot"></span> LIVE • يعمل الآن'
            );

        }
        else{

            setStatus(
                '▶️ يعمل الآن'
            );
        }


        if (
            !firstPlay
        ){

            firstPlay =
                true;


            log(
                'VIDEO PLAYING'
            );
        }

    }
);


/* =====================================================
   WAITING
===================================================== */

video.addEventListener(
    'waiting',
    function(){

        if (
            IS_LIVE &&
            started
        ){

            setStatus(
                '⏳ LIVE • جاري تحميل البيانات...'
            );

        }

    }
);


/* =====================================================
   STALLED
===================================================== */

video.addEventListener(
    'stalled',
    function(){

        log(
            'VIDEO STALLED'
        );

    }
);


/* =====================================================
   CAN PLAY
===================================================== */

video.addEventListener(
    'canplay',
    function(){

        hideLoading();

    }
);


/* =====================================================
   NATIVE ERROR
===================================================== */

video.addEventListener(
    'error',
    function(){

        const error =
            video.error;


        log(
            'NATIVE VIDEO ERROR: ' +
            (
                error
                    ? error.code
                    : 'unknown'
            )
        );


        /*
        Don't repeatedly restart.
        */

        if (
            currentMode ===
            'direct'
        ){

            switchToProxy(
                'NATIVE ERROR'
            );

        }
        else{

            setStatus(
                '❌ تعذر تشغيل المصدر'
            );

            hideLoading();
        }

    }
);


/* =====================================================
   BUFFER MONITOR
===================================================== */

setInterval(
    function(){

        if (
            !IS_LIVE ||
            !video ||
            video.paused
        ){

            return;
        }


        try{

            if (
                video.buffered.length
            ){

                const end =
                    video.buffered.end(
                        video.buffered.length - 1
                    );


                const pos =
                    video.currentTime;


                const buffer =
                    end -
                    pos;


                if (
                    buffer > 12
                ){

                    setStatus(
                        '📺 <span class="live-dot"></span> LIVE • Buffer ' +
                        buffer.toFixed(1) +
                        's'
                    );

                }

            }

        }catch(e){}

    },
    1000
);


/* =====================================================
   PICTURE IN PICTURE
===================================================== */

async function pipPlayer()
{

    try{

        if (
            document.pictureInPictureElement
        ){

            await document.exitPictureInPicture();

            return;
        }


        if (
            video.requestPictureInPicture
        ){

            await video.requestPictureInPicture();

            return;
        }


        setStatus(
            '❌ Picture in Picture غير مدعوم'
        );

    }catch(e){

        log(
            'PiP ERROR: ' +
            e.message
        );

    }
}


/* =====================================================
   FULLSCREEN
===================================================== */

function fullscreenPlayer()
{

    try{

        if (
            video.requestFullscreen
        ){

            video.requestFullscreen();

            return;
        }


        if (
            video.webkitEnterFullscreen
        ){

            video.webkitEnterFullscreen();

            return;
        }


        if (
            video.webkitRequestFullscreen
        ){

            video.webkitRequestFullscreen();

            return;
        }

    }catch(e){

        log(
            'FULLSCREEN ERROR'
        );
    }
}


/* =====================================================
   COPY
===================================================== */

async function copyText(text)
{

    try{

        await navigator.clipboard.writeText(
            text
        );

    }catch(e){

        const ta =
            document.createElement(
                'textarea'
            );


        ta.value =
            text;


        document.body.appendChild(
            ta
        );


        ta.select();


        document.execCommand(
            'copy'
        );


        ta.remove();
    }


    setStatus(
        '✅ تم نسخ الرابط'
    );
}


function copySource()
{

    copyText(
        SOURCE
    );
}


function copyProxy()
{

    copyText(
        PROXY
    );
}


/* =====================================================
   DIAGNOSTIC
===================================================== */

function toggleDiagnostic()
{

    diag.classList.toggle(
        'show'
    );
}


/* =====================================================
   EPISODE NEXT
===================================================== */

video.addEventListener(
    'ended',
    function(){

        log(
            'VIDEO ENDED'
        );


        if (
            NEXT_URL
        ){

            nextTitleText.textContent =
                NEXT_TITLE ||
                'الحلقة التالية';


            nextBtnLink.href =
                NEXT_URL;


            nextOverlay.classList.add(
                'show'
            );


            let seconds =
                5;


            if (
                countdownTimer
            ){

                clearInterval(
                    countdownTimer
                );
            }


            countdownTimer =
                setInterval(
                    function(){

                        seconds--;


                        if (
                            seconds <= 0
                        ){

                            clearInterval(
                                countdownTimer
                            );


                            window.location.href =
                                NEXT_URL;

                        }

                    },
                    1000
                );

        }
        else{

            setStatus(
                '⏹️ انتهى الفيديو'
            );

        }

    }
);


/* =====================================================
   EXTERNAL PLAYERS
===================================================== */

document.getElementById(
    'mx'
).href =
    MX;


document.getElementById(
    'vlc'
).href =
    VLC;


/* =====================================================
   DIAGNOSTIC INFO
===================================================== */

log(
    'Browser: ' +
    navigator.userAgent
);


log(
    'HLS.js: ' +
    (
        window.Hls
            ? 'YES'
            : 'WAITING'
    )
);


log(
    'mpegts.js: ' +
    (
        window.mpegts
            ? 'YES'
            : 'WAITING'
    )
);


log(
    'dash.js: ' +
    (
        window.dashjs
            ? 'YES'
            : 'WAITING'
    )
);


log(
    'Detected source type: ' +
    SOURCE_TYPE
);


log(
    'START MODE: DIRECT'
);


/* =====================================================
   START
===================================================== */

window.addEventListener(
    'load',
    function(){

        waitForLibraries();

    }
);

</script>

</body>

</html>