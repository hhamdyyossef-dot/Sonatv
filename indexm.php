<?php
/*
=========================================================
 SONA TV - index.php
 InfinityFree Compatible
 --------------------------------------------------------
 - Home Landing Page
 - Live TV
 - Movies
 - Series
 - GLOBAL SEARCH
 - Search from ANY section
 - Xtream Categories
 - Movie Player
 - Episode Player
 - Direct Download
 - Fixed IPTV Images
=========================================================
*/

ini_set('display_errors', '0');
error_reporting(0);
set_time_limit(60);

date_default_timezone_set('Africa/Cairo');


/* =====================================================
   CONFIG
===================================================== */

$SERVER   = 'http://rechahd.xyz:80';
$USERNAME = '2487040066';
$PASSWORD = '55964135020A';


/* =====================================================
   HELPERS
===================================================== */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function get_real_client_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

        $parts = explode(
            ',',
            $_SERVER['HTTP_X_FORWARDED_FOR']
        );

        return trim($parts[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '';
}


/* =====================================================
   IP BLOCK
===================================================== */

$visitor_ip = get_real_client_ip();

$blockedFile = __DIR__ . '/blocked_ips.json';

if (file_exists($blockedFile)) {

    $blocked = json_decode(
        @file_get_contents($blockedFile),
        true
    );

    if (!is_array($blocked)) {
        $blocked = [];
    }

    if (in_array($visitor_ip, $blocked, true)) {

        http_response_code(403);

        exit('
        <div style="
            text-align:center;
            margin-top:100px;
            font-family:Arial;
            direction:rtl;
        ">
            <h1 style="color:#e50914">
                403 - Access Denied
            </h1>
            <p>تم حظر عنوان IP الخاص بك.</p>
        </div>
        ');
    }
}


/* =====================================================
   MAINTENANCE
===================================================== */

$maintenanceFile = __DIR__ . '/maintenance.txt';

if (file_exists($maintenanceFile)) {

    exit('
    <div style="
        text-align:center;
        margin-top:100px;
        font-family:Arial;
        direction:rtl;
    ">
        <h2>🛠️ الموقع في وضع الصيانة حالياً</h2>
    </div>
    ');
}


/* =====================================================
   CACHE
===================================================== */

$cacheDir = __DIR__ . '/cache';

if (!is_dir($cacheDir)) {

    @mkdir(
        $cacheDir,
        0755,
        true
    );
}


/* =====================================================
   XTREAM API
===================================================== */

function xtream_api(
    $action = '',
    $extra = [],
    $cacheTtl = 1800
) {

    global $SERVER;
    global $USERNAME;
    global $PASSWORD;
    global $cacheDir;

    if (
        empty($SERVER) ||
        empty($USERNAME) ||
        empty($PASSWORD)
    ) {
        return [];
    }

    $cacheKey = md5(
        $SERVER .
        $USERNAME .
        $PASSWORD .
        $action .
        serialize($extra)
    );

    $cacheFile =
        $cacheDir .
        '/xtream_' .
        $cacheKey .
        '.json';

    if (
        $cacheTtl > 0 &&
        file_exists($cacheFile) &&
        (
            time() -
            filemtime($cacheFile)
        ) < $cacheTtl
    ) {

        $cached = json_decode(
            @file_get_contents($cacheFile),
            true
        );

        if (is_array($cached)) {
            return $cached;
        }
    }

    $params = array_merge(
        [
            'username' => $USERNAME,
            'password' => $PASSWORD,
            'action'   => $action
        ],
        $extra
    );

    $url =
        rtrim($SERVER, '/') .
        '/player_api.php?' .
        http_build_query($params);

    $ch = curl_init($url);

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'SONA-TV/6.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]
    );

    $result = curl_exec($ch);

    curl_close($ch);

    if (!$result) {
        return [];
    }

    $data = json_decode(
        $result,
        true
    );

    if (!is_array($data)) {
        return [];
    }

    if (!empty($data)) {

        @file_put_contents(
            $cacheFile,
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )
        );
    }

    return $data;
}


/* =====================================================
   IMAGE
===================================================== */

function fix_image_url($url)
{
    $url = trim((string)$url);

    if ($url === '') {
        return '';
    }

    if (!preg_match('~^https?://~i', $url)) {
        return '';
    }

    return $url;
}


/* =====================================================
   SAFE FILENAME
===================================================== */

function safe_filename(
    $name,
    $extension = ''
) {

    $name = trim($name);

    $name = preg_replace(
        '/[\/\\\\:*?"<>|]+/u',
        '_',
        $name
    );

    $name = preg_replace(
        '/\s+/u',
        ' ',
        $name
    );

    $name = trim(
        $name,
        ". "
    );

    if ($name === '') {
        $name = 'SONA-TV';
    }

    if ($extension !== '') {

        $name .=
            '.' .
            ltrim(
                $extension,
                '.'
            );
    }

    return $name;
}


/* =====================================================
   DOWNLOAD
===================================================== */

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'download'
) {

    $fileUrl =
        $_GET['url'] ?? '';

    $fileName =
        $_GET['name'] ?? 'video.mp4';

    if (empty($fileUrl)) {

        http_response_code(400);

        exit('رابط غير صالح');
    }

    $allowedHost =
        parse_url(
            $SERVER,
            PHP_URL_HOST
        );

    $targetHost =
        parse_url(
            $fileUrl,
            PHP_URL_HOST
        );

    if (
        !$targetHost ||
        !$allowedHost ||
        strtolower($targetHost) !==
        strtolower($allowedHost)
    ) {

        http_response_code(403);

        exit(
            'غير مسموح بتحميل هذا الرابط.'
        );
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    header(
        'Content-Description: File Transfer'
    );

    header(
        'Content-Type: application/octet-stream'
    );

    header(
        'Content-Disposition: attachment; filename="' .
        safe_filename($fileName) .
        '"'
    );

    header(
        'Cache-Control: must-revalidate'
    );

    header(
        'Pragma: public'
    );

    $ch = curl_init($fileUrl);

    curl_setopt_array(
        $ch,
        [

            CURLOPT_RETURNTRANSFER => false,

            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_SSL_VERIFYPEER => false,

            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_USERAGENT => 'SONA-TV/6.0',

            CURLOPT_WRITEFUNCTION =>
                function (
                    $ch,
                    $chunk
                ) {

                    echo $chunk;

                    flush();

                    return strlen($chunk);
                }
        ]
    );

    curl_exec($ch);

    curl_close($ch);

    exit;
}


/* =====================================================
   MANIFEST
===================================================== */

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'manifest'
) {

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'name' => 'SONA TV',
            'short_name' => 'SONA',
            'start_url' => './',
            'scope' => './',
            'display' => 'standalone',
            'background_color' => '#080910',
            'theme_color' => '#ff1744'
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/* =====================================================
   SERVICE WORKER
===================================================== */

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'sw'
) {

    header(
        'Content-Type: application/javascript; charset=utf-8'
    );

    echo <<<JS

self.addEventListener(
    'install',
    event => self.skipWaiting()
);

self.addEventListener(
    'activate',
    event => event.waitUntil(
        clients.claim()
    )
);

JS;

    exit;
}


/* =====================================================
   PARAMETERS
===================================================== */

$tab =
    $_GET['tab']
    ?? '';

$catID =
    $_GET['cat_id']
    ?? '';

$seriesID =
    (int)(
        $_GET['series_id']
        ?? 0
    );

$limit =
    (int)(
        $_GET['limit']
        ?? 30
    );

$searchQuery =
    trim(
        $_GET['q']
        ?? ''
    );

if ($limit < 1) {
    $limit = 30;
}

if ($limit > 300) {
    $limit = 300;
}


/* =====================================================
   HOME DETECTION
===================================================== */

$isHome =
    $tab === '' &&
    $catID === '' &&
    $seriesID === 0 &&
    $searchQuery === '';


/* =====================================================
   DATA
===================================================== */

$categories = [];

$items = [];

$seriesInfo = null;


/* =====================================================
   GLOBAL SEARCH
===================================================== */

$searchResults = [
    'live'   => [],
    'movies' => [],
    'series' => []
];

if ($searchQuery !== '') {

    $liveData =
        xtream_api(
            'get_live_streams',
            [],
            300
        );

    $movieData =
        xtream_api(
            'get_vod_streams',
            [],
            300
        );

    $seriesData =
        xtream_api(
            'get_series',
            [],
            300
        );

    $needle =
        function_exists('mb_strtolower')
        ? mb_strtolower(
            $searchQuery,
            'UTF-8'
        )
        : strtolower(
            $searchQuery
        );

    /* LIVE */

    if (is_array($liveData)) {

        foreach ($liveData as $item) {

            if (!is_array($item)) {
                continue;
            }

            $name =
                $item['name']
                ?? '';

            $haystack =
                function_exists('mb_strtolower')
                ? mb_strtolower(
                    $name,
                    'UTF-8'
                )
                : strtolower(
                    $name
                );

            if (
                $needle !== '' &&
                strpos(
                    $haystack,
                    $needle
                ) !== false
            ) {

                $searchResults['live'][] =
                    $item;
            }
        }
    }

    /* MOVIES */

    if (is_array($movieData)) {

        foreach ($movieData as $item) {

            if (!is_array($item)) {
                continue;
            }

            $name =
                $item['name']
                ?? '';

            $haystack =
                function_exists('mb_strtolower')
                ? mb_strtolower(
                    $name,
                    'UTF-8'
                )
                : strtolower(
                    $name
                );

            if (
                $needle !== '' &&
                strpos(
                    $haystack,
                    $needle
                ) !== false
            ) {

                $searchResults['movies'][] =
                    $item;
            }
        }
    }

    /* SERIES */

    if (is_array($seriesData)) {

        foreach ($seriesData as $item) {

            if (!is_array($item)) {
                continue;
            }

            $name =
                $item['name']
                ?? '';

            $haystack =
                function_exists('mb_strtolower')
                ? mb_strtolower(
                    $name,
                    'UTF-8'
                )
                : strtolower(
                    $name
                );

            if (
                $needle !== '' &&
                strpos(
                    $haystack,
                    $needle
                ) !== false
            ) {

                $searchResults['series'][] =
                    $item;
            }
        }
    }

    $searchResults['live'] =
        array_slice(
            $searchResults['live'],
            0,
            50
        );

    $searchResults['movies'] =
        array_slice(
            $searchResults['movies'],
            0,
            50
        );

    $searchResults['series'] =
        array_slice(
            $searchResults['series'],
            0,
            50
        );
}


/* =====================================================
   SERIES DETAILS
===================================================== */

if ($seriesID > 0) {

    $seriesInfo =
        xtream_api(
            'get_series_info',
            [
                'series_id' =>
                    $seriesID
            ],
            600
        );
}


/* =====================================================
   NORMAL ROUTING
===================================================== */

elseif (
    !$isHome &&
    $searchQuery === '' &&
    $tab === 'movie'
) {

    $categories =
        xtream_api(
            'get_vod_categories',
            [],
            3600
        );

    $params =
        $catID !== ''
        ? [
            'category_id' =>
                $catID
        ]
        : [];

    $raw =
        xtream_api(
            'get_vod_streams',
            $params,
            1800
        );

    if (is_array($raw)) {

        $items =
            array_slice(
                $raw,
                0,
                $limit
            );
    }
}


elseif (
    !$isHome &&
    $searchQuery === '' &&
    $tab === 'series'
) {

    $categories =
        xtream_api(
            'get_series_categories',
            [],
            3600
        );

    $params =
        $catID !== ''
        ? [
            'category_id' =>
                $catID
        ]
        : [];

    $raw =
        xtream_api(
            'get_series',
            $params,
            1800
        );

    if (is_array($raw)) {

        $items =
            array_slice(
                $raw,
                0,
                $limit
            );
    }
}


elseif (
    !$isHome &&
    $searchQuery === ''
) {

    $categories =
        xtream_api(
            'get_live_categories',
            [],
            3600
        );

    $params =
        $catID !== ''
        ? [
            'category_id' =>
                $catID
        ]
        : [];

    $raw =
        xtream_api(
            'get_live_streams',
            $params,
            1800
        );

    if (is_array($raw)) {

        $items =
            array_slice(
                $raw,
                0,
                $limit
            );
    }
}


/* =====================================================
   CATEGORY NAMES
===================================================== */

$categoryNames = [];

if (is_array($categories)) {

    foreach ($categories as $cat) {

        if (
            isset(
                $cat['category_id'],
                $cat['category_name']
            )
        ) {

            $categoryNames[
                (string)
                $cat['category_id']
            ] =
                $cat['category_name'];
        }
    }
}


/* =====================================================
   SEARCH COUNT
===================================================== */

$totalSearch =
    count($searchResults['live']) +
    count($searchResults['movies']) +
    count($searchResults['series']);

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
    content="width=device-width,initial-scale=1,viewport-fit=cover"
>

<title>SONA TV</title>

<link
    rel="manifest"
    href="?action=manifest"
>

<meta
    name="theme-color"
    content="#ff1744"
>

<style>

/* =====================================================
   VARIABLES
===================================================== */

:root{

    --bg:#080910;

    --bg2:#11131d;

    --card:#151824;

    --card2:#1d2030;

    --border:rgba(255,255,255,.09);

    --text:#ffffff;

    --muted:#a8adba;

    --red:#ff1744;

    --pink:#ff4081;

    --purple:#8b5cf6;

    --cyan:#22d3ee;

}


/* =====================================================
   RESET
===================================================== */

*{
    box-sizing:border-box;
}

html{
    scroll-behavior:smooth;
}

body{

    margin:0;

    min-height:100vh;

    color:var(--text);

    font-family:
        Arial,
        system-ui,
        sans-serif;

    background:
        radial-gradient(
            circle at 15% 10%,
            rgba(255,23,68,.24),
            transparent 28%
        ),
        radial-gradient(
            circle at 85% 15%,
            rgba(139,92,246,.22),
            transparent 30%
        ),
        radial-gradient(
            circle at 50% 100%,
            rgba(34,211,238,.10),
            transparent 35%
        ),
        var(--bg);

    padding-bottom:90px;

    overflow-x:hidden;
}


/* =====================================================
   LIGHT EFFECTS
===================================================== */

body::before{

    content:"";

    position:fixed;

    width:420px;

    height:420px;

    top:-220px;

    right:-160px;

    background:
        radial-gradient(
            circle,
            rgba(255,23,68,.18),
            transparent 68%
        );

    filter:blur(30px);

    pointer-events:none;

    z-index:-1;
}

body::after{

    content:"";

    position:fixed;

    width:350px;

    height:350px;

    bottom:-180px;

    left:-140px;

    background:
        radial-gradient(
            circle,
            rgba(139,92,246,.15),
            transparent 68%
        );

    filter:blur(35px);

    pointer-events:none;

    z-index:-1;
}


/* =====================================================
   HEADER
===================================================== */

header{

    position:sticky;

    top:0;

    z-index:1000;

    padding:12px 15px;

    background:
        rgba(8,9,16,.76);

    backdrop-filter:
        blur(22px);

    border-bottom:
        1px solid
        rgba(255,255,255,.07);
}


.header-row{

    display:flex;

    align-items:center;

    gap:12px;

    max-width:1200px;

    margin:auto;
}


.logo{

    font-size:24px;

    font-weight:1000;

    color:#fff;

    text-shadow:
        0 0 12px
        rgba(255,23,68,.7);

    letter-spacing:1px;
}


.logo::first-letter{

    color:var(--red);
}


.search-box{

    flex:1;

    position:relative;
}


.search{

    width:100%;

    height:45px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.10);

    border-radius:15px;

    color:#fff;

    padding:
        0 16px;

    outline:none;

    font-size:13px;

    transition:.25s;
}


.search::placeholder{

    color:#858b99;
}


.search:focus{

    border-color:
        rgba(255,23,68,.75);

    box-shadow:
        0 0 0 3px
        rgba(255,23,68,.09),
        0 0 25px
        rgba(255,23,68,.12);
}


.search-button{

    position:absolute;

    left:6px;

    top:6px;

    width:33px;

    height:33px;

    border:0;

    border-radius:10px;

    background:
        linear-gradient(
            135deg,
            var(--red),
            var(--pink)
        );

    color:#fff;

    cursor:pointer;

    box-shadow:
        0 4px 14px
        rgba(255,23,68,.25);
}


/* =====================================================
   HOME
===================================================== */

.home{

    min-height:
        calc(
            100vh - 75px
        );

    display:flex;

    align-items:center;

    justify-content:center;

    padding:
        35px 18px 110px;

    position:relative;

    overflow:hidden;
}


.home-content{

    width:100%;

    max-width:1050px;

    text-align:center;

    position:relative;

    z-index:2;
}


.home-badge{

    display:inline-flex;

    align-items:center;

    gap:8px;

    padding:
        8px 14px;

    border:
        1px solid
        rgba(255,255,255,.12);

    background:
        rgba(255,255,255,.055);

    backdrop-filter:
        blur(15px);

    border-radius:30px;

    color:#ddd;

    font-size:11px;

    margin-bottom:20px;

    box-shadow:
        0 8px 35px
        rgba(0,0,0,.25);
}


.live-dot{

    width:7px;

    height:7px;

    border-radius:50%;

    background:#ff1744;

    box-shadow:
        0 0 12px
        #ff1744;

    animation:
        pulse 1.5s infinite;
}


@keyframes pulse{

    0%,100%{
        opacity:1;
        transform:scale(1);
    }

    50%{
        opacity:.4;
        transform:scale(.7);
    }
}


.home-logo{

    font-size:
        clamp(
            62px,
            13vw,
            145px
        );

    line-height:.9;

    font-weight:1000;

    letter-spacing:
        -5px;

    margin:0;

    background:
        linear-gradient(
            135deg,
            #fff 15%,
            #fff 35%,
            #ff1744 58%,
            #ff4081 75%,
            #8b5cf6 100%
        );

    -webkit-background-clip:text;

    background-clip:text;

    color:transparent;

    filter:
        drop-shadow(
            0 0 25px
            rgba(255,23,68,.20)
        );

    animation:
        logoFloat 4s ease-in-out infinite;
}


@keyframes logoFloat{

    0%,100%{
        transform:translateY(0);
    }

    50%{
        transform:translateY(-5px);
    }
}


.home-subtitle{

    margin:
        20px auto 10px;

    font-size:
        clamp(
            18px,
            3vw,
            28px
        );

    font-weight:800;

    color:#fff;
}


.home-description{

    max-width:650px;

    margin:
        0 auto 28px;

    color:#9ea4b2;

    font-size:13px;

    line-height:1.8;
}


/* =====================================================
   HOME BUTTONS
===================================================== */

.home-actions{

    display:flex;

    justify-content:center;

    flex-wrap:wrap;

    gap:11px;

    margin-bottom:40px;
}


.home-button{

    min-width:150px;

    height:48px;

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    border-radius:14px;

    text-decoration:none;

    color:#fff;

    font-size:13px;

    font-weight:bold;

    transition:
        transform .2s,
        box-shadow .2s;
}


.home-button:hover{

    transform:
        translateY(-3px);
}


.home-primary{

    background:
        linear-gradient(
            135deg,
            #ff1744,
            #ff4081
        );

    box-shadow:
        0 10px 30px
        rgba(255,23,68,.28);
}


.home-secondary{

    background:
        rgba(255,255,255,.065);

    border:
        1px solid
        rgba(255,255,255,.11);

    backdrop-filter:
        blur(12px);
}


/* =====================================================
   HOME CARDS
===================================================== */

.home-sections{

    display:grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap:14px;

    max-width:850px;

    margin:auto;
}


.home-card{

    position:relative;

    min-height:150px;

    padding:20px;

    display:flex;

    flex-direction:column;

    justify-content:center;

    align-items:center;

    gap:8px;

    text-decoration:none;

    color:#fff;

    overflow:hidden;

    border:
        1px solid
        rgba(255,255,255,.09);

    border-radius:20px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.08),
            rgba(255,255,255,.025)
        );

    backdrop-filter:
        blur(18px);

    box-shadow:
        0 15px 45px
        rgba(0,0,0,.25);

    transition:
        transform .25s,
        border-color .25s,
        box-shadow .25s;
}


.home-card::before{

    content:"";

    position:absolute;

    width:100px;

    height:100px;

    top:-45px;

    right:-30px;

    border-radius:50%;

    background:
        rgba(255,23,68,.20);

    filter:blur(25px);
}


.home-card:hover{

    transform:
        translateY(-6px);

    border-color:
        rgba(255,255,255,.2);

    box-shadow:
        0 20px 55px
        rgba(0,0,0,.35);
}


.home-icon{

    font-size:38px;

    filter:
        drop-shadow(
            0 0 12px
            rgba(255,255,255,.16)
        );
}


.home-card-title{

    font-size:14px;

    font-weight:900;
}


.home-card-text{

    font-size:10px;

    color:#9298a7;
}


/* =====================================================
   TOP TABS
===================================================== */

.top-tabs{

    max-width:1200px;

    margin:0 auto;

    padding:
        12px 15px 5px;

    display:flex;

    gap:8px;
}


.top-tab{

    flex:1;

    text-align:center;

    text-decoration:none;

    color:var(--muted);

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        var(--border);

    padding:
        11px 8px;

    border-radius:13px;

    font-size:12px;

    transition:.2s;
}


.top-tab:hover{

    transform:
        translateY(-1px);
}


.top-tab.active{

    background:
        linear-gradient(
            135deg,
            var(--red),
            var(--pink)
        );

    border-color:transparent;

    color:#fff;

    font-weight:bold;

    box-shadow:
        0 7px 22px
        rgba(255,23,68,.18);
}


/* =====================================================
   CATEGORIES
===================================================== */

.categories-wrap{

    max-width:1200px;

    margin:auto;

    padding:
        5px 15px 10px;
}


.categories{

    display:flex;

    gap:7px;

    overflow-x:auto;

    scrollbar-width:none;

    padding:
        2px 0 4px;
}


.categories::-webkit-scrollbar{
    display:none;
}


.category{

    flex:none;

    white-space:nowrap;

    text-decoration:none;

    color:var(--muted);

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        var(--border);

    padding:
        8px 13px;

    border-radius:20px;

    font-size:11px;

    transition:.2s;
}


.category.active{

    color:#fff;

    background:
        linear-gradient(
            135deg,
            var(--red),
            var(--pink)
        );

    border-color:transparent;

    box-shadow:
        0 5px 18px
        rgba(255,23,68,.18);
}


/* =====================================================
   CONTENT
===================================================== */

.container{

    max-width:1200px;

    margin:auto;

    padding:
        8px 15px;
}


.section-title{

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin:
        14px 0 10px;
}


.section-title h2{

    margin:0;

    font-size:17px;
}


.section-title span{

    color:var(--muted);

    font-size:11px;
}


/* =====================================================
   GRID
===================================================== */

.grid{

    display:grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(
                125px,
                1fr
            )
        );

    gap:12px;
}


.card{

    display:block;

    text-decoration:none;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.055),
            rgba(255,255,255,.025)
        );

    border:
        1px solid
        var(--border);

    border-radius:15px;

    overflow:hidden;

    transition:
        transform .2s,
        border-color .2s,
        box-shadow .2s;
}


.card:hover{

    transform:
        translateY(-3px);

    border-color:
        rgba(255,23,68,.45);

    box-shadow:
        0 12px 30px
        rgba(0,0,0,.25);
}


.poster{

    position:relative;

    aspect-ratio:2/3;

    background:
        linear-gradient(
            145deg,
            #1b1e29,
            #090a10
        );

    overflow:hidden;
}


.poster img{

    position:absolute;

    inset:0;

    width:100%;

    height:100%;

    object-fit:cover;

    transition:
        transform .35s;
}


.card:hover .poster img{

    transform:scale(1.04);
}


.noimg{

    position:absolute;

    inset:0;

    display:flex;

    flex-direction:column;

    justify-content:center;

    align-items:center;

    text-align:center;

    padding:10px;

    color:var(--muted);

    font-size:11px;

    gap:5px;
}


.live-badge{

    position:absolute;

    top:7px;

    right:7px;

    background:
        linear-gradient(
            135deg,
            #ff1744,
            #ff4081
        );

    color:#fff;

    padding:
        4px 7px;

    border-radius:6px;

    font-size:9px;

    font-weight:bold;

    z-index:2;

    box-shadow:
        0 3px 12px
        rgba(255,23,68,.3);
}


.type-badge{

    position:absolute;

    bottom:7px;

    right:7px;

    background:
        rgba(0,0,0,.72);

    color:#fff;

    padding:
        4px 7px;

    border-radius:6px;

    font-size:9px;

    z-index:2;
}


.name{

    padding:
        9px 7px;

    text-align:center;

    font-size:11px;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;
}


/* =====================================================
   SEARCH
===================================================== */

.search-page{

    max-width:1200px;

    margin:auto;

    padding:
        10px 15px;
}


.search-title{

    margin:
        10px 0 16px;

    font-size:17px;
}


.search-title strong{

    color:var(--red);
}


.search-section{

    margin-bottom:25px;
}


.search-section-title{

    display:flex;

    align-items:center;

    gap:8px;

    margin-bottom:10px;

    font-size:15px;
}


.search-count{

    color:var(--muted);

    font-size:10px;

    background:var(--card);

    padding:
        4px 7px;

    border-radius:8px;
}


.search-empty{

    text-align:center;

    padding:60px 20px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        var(--border);

    border-radius:18px;

    color:var(--muted);

    font-size:13px;
}


/* =====================================================
   SERIES
===================================================== */

.page{

    max-width:850px;

    margin:auto;

    padding:15px;
}


.back{

    display:inline-block;

    color:#fff;

    text-decoration:none;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        var(--border);

    padding:
        9px 14px;

    border-radius:10px;

    margin-bottom:15px;

    font-size:12px;
}


.series-title{

    color:#fff;

    font-size:20px;

    margin-bottom:15px;

    font-weight:bold;
}


.episodes{

    display:flex;

    flex-direction:column;

    gap:8px;
}


.episode{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        var(--border);

    padding:11px;

    border-radius:10px;
}


.episode-title{

    color:#fff;

    text-decoration:none;

    font-size:13px;

    flex:1;
}


.download-episode{

    display:inline-block;

    background:
        linear-gradient(
            135deg,
            var(--red),
            var(--pink)
        );

    color:#fff;

    text-decoration:none;

    padding:
        8px 12px;

    border-radius:8px;

    font-size:11px;

    white-space:nowrap;
}


.season{

    color:var(--muted);

    font-size:13px;

    margin-top:15px;

    margin-bottom:5px;

    font-weight:bold;
}


/* =====================================================
   EMPTY
===================================================== */

.empty{

    grid-column:1/-1;

    text-align:center;

    color:var(--muted);

    padding:55px 15px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        var(--border);

    border-radius:16px;

    font-size:13px;
}


/* =====================================================
   MORE
===================================================== */

.more{

    display:block;

    width:max-content;

    margin:
        20px auto;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid
        var(--border);

    color:#fff;

    padding:
        10px 20px;

    border-radius:10px;

    text-decoration:none;

    font-size:12px;
}


/* =====================================================
   BOTTOM NAV
===================================================== */

.bottom{

    position:fixed;

    bottom:0;

    left:0;

    right:0;

    height:68px;

    background:
        rgba(10,11,17,.88);

    backdrop-filter:
        blur(20px);

    border-top:
        1px solid
        rgba(255,255,255,.08);

    display:flex;

    justify-content:space-around;

    align-items:center;

    z-index:2000;

    padding:
        0 5px;
}


.bottom a{

    min-width:60px;

    text-decoration:none;

    color:var(--muted);

    font-size:10px;

    text-align:center;

    padding:
        6px 8px;

    border-radius:10px;
}


.bottom a .icon{

    display:block;

    font-size:19px;

    line-height:22px;

    margin-bottom:2px;
}


.bottom a.active{

    color:#fff;

    background:
        rgba(255,23,68,.12);
}


.bottom a.active .icon{

    color:var(--red);
}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width:650px){

    .home{

        min-height:
            calc(
                100vh - 65px
            );

        padding:
            25px 12px 105px;
    }


    .home-logo{

        letter-spacing:
            -3px;
    }


    .home-description{

        font-size:11px;

        line-height:1.7;

        padding:
            0 10px;
    }


    .home-actions{

        gap:8px;

        margin-bottom:27px;
    }


    .home-button{

        min-width:
            calc(
                50% - 5px
            );

        height:45px;

        font-size:11px;
    }


    .home-sections{

        grid-template-columns:
            repeat(
                3,
                1fr
            );

        gap:7px;
    }


    .home-card{

        min-height:125px;

        padding:10px 5px;

        border-radius:16px;
    }


    .home-icon{

        font-size:30px;
    }


    .home-card-title{

        font-size:11px;
    }


    .home-card-text{

        font-size:8px;
    }


    .grid{

        grid-template-columns:
            repeat(
                3,
                1fr
            );

        gap:8px;
    }


    .name{

        font-size:10px;

        padding:
            8px 4px;
    }


    .header-row{

        gap:8px;
    }


    .logo{

        font-size:19px;
    }


    .search{

        height:42px;

        font-size:11px;
    }


    .top-tabs{

        padding:
            9px 10px 4px;
    }


    .top-tab{

        padding:
            9px 5px;

        font-size:11px;
    }


    .categories-wrap{

        padding:
            4px 10px 7px;
    }


    .container{

        padding:
            7px 10px;
    }


    .search-page{

        padding:
            8px 10px;
    }


    .episode{

        padding:9px;
    }


    .episode-title{

        font-size:12px;
    }


    .download-episode{

        padding:
            7px 9px;

        font-size:10px;
    }
}


@media(max-width:380px){

    .home-card{

        min-height:112px;
    }

    .home-card-text{

        display:none;
    }

    .home-icon{

        font-size:27px;
    }

    .home-card-title{

        font-size:10px;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header>

    <div class="header-row">

        <div class="logo">
            SONA
        </div>

        <form
            class="search-box"
            method="get"
            action=""
        >

            <input
                class="search"
                type="search"
                name="q"
                value="<?= h($searchQuery) ?>"
                placeholder="ابحث في القنوات والأفلام والمسلسلات..."
                autocomplete="off"
            >

            <button
                class="search-button"
                type="submit"
            >
                🔍
            </button>

        </form>

    </div>

</header>


<?php if ($isHome): ?>


<!-- =====================================================
     HOME LANDING PAGE
===================================================== -->

<section class="home">

    <div class="home-content">

        <div class="home-badge">

            <span class="live-dot"></span>

            أهلاً بيك في SONA TV

        </div>


        <h1 class="home-logo">
            SONA
        </h1>


        <div class="home-subtitle">

            عالمك للترفيه في مكان واحد 🎬

        </div>


        <div class="home-description">

            اتفرج على الأفلام والمسلسلات والقنوات
            المباشرة بسهولة، بتصميم سريع ومناسب للموبايل.

        </div>


        <div class="home-actions">

            <a
                class="home-button home-primary"
                href="?tab=movie"
            >

                ▶️ ابدأ المشاهدة

            </a>


            <a
                class="home-button home-secondary"
                href="?tab=live"
            >

                📺 البث المباشر

            </a>

        </div>


        <div class="home-sections">


            <a
                class="home-card"
                href="?tab=movie"
            >

                <div class="home-icon">
                    🎬
                </div>

                <div class="home-card-title">
                    الأفلام
                </div>

                <div class="home-card-text">
                    أحدث الأفلام المتاحة
                </div>

            </a>


            <a
                class="home-card"
                href="?tab=series"
            >

                <div class="home-icon">
                    🍿
                </div>

                <div class="home-card-title">
                    المسلسلات
                </div>

                <div class="home-card-text">
                    حلقات ومواسم كاملة
                </div>

            </a>


            <a
                class="home-card"
                href="?tab=live"
            >

                <div class="home-icon">
                    📺
                </div>

                <div class="home-card-title">
                    مباشر
                </div>

                <div class="home-card-text">
                    القنوات المباشرة
                </div>

            </a>


        </div>

    </div>

</section>


<?php elseif ($seriesID > 0): ?>


<!-- =====================================================
     SERIES DETAILS
===================================================== -->

<?php

$info =
    $seriesInfo['info']
    ?? [];

$episodes =
    $seriesInfo['episodes']
    ?? [];

$seriesName =
    $info['name']
    ?? 'المسلسل';

?>


<div class="page">


    <a
        href="?tab=series"
        class="back"
    >
        ← المسلسلات
    </a>


    <div class="series-title">

        <?= h($seriesName) ?>

    </div>


    <?php if (empty($episodes)): ?>

        <div class="empty">

            لا توجد حلقات متاحة.

        </div>

    <?php else: ?>


    <div class="episodes">


    <?php foreach (
        $episodes as $season =>
        $seasonEpisodes
    ): ?>


        <div class="season">

            الموسم
            <?= h($season) ?>

        </div>


        <?php if (
            is_array($seasonEpisodes)
        ): ?>


        <?php foreach (
            array_values(
                $seasonEpisodes
            ) as $ep
        ): ?>


        <?php

        if (!is_array($ep)) {
            continue;
        }

        $episodeID =
            (int)(
                $ep['id']
                ?? 0
            );

        if (!$episodeID) {
            continue;
        }

        $episodeName =
            $ep['title']
            ?? 'الحلقة';

        $extension =
            $ep['container_extension']
            ?? 'mp4';

        $episodeURL =
            rtrim(
                $SERVER,
                '/'
            ) .
            '/series/' .
            rawurlencode($USERNAME) .
            '/' .
            rawurlencode($PASSWORD) .
            '/' .
            $episodeID .
            '.' .
            $extension;

        $playerLink =
            'player.php?url=' .
            urlencode($episodeURL) .
            '&title=' .
            urlencode(
                $seriesName .
                ' - ' .
                $episodeName
            );

        $downloadLink =
            '?action=download&url=' .
            urlencode($episodeURL) .
            '&name=' .
            urlencode(
                safe_filename(
                    $seriesName .
                    ' - ' .
                    $episodeName,
                    $extension
                )
            );

        ?>


        <div class="episode">

            <a
                class="episode-title"
                href="<?= h($playerLink) ?>"
            >

                ▶️
                <?= h($episodeName) ?>

            </a>


            <a
                class="download-episode"
                href="<?= h($downloadLink) ?>"
            >

                ⬇️ تحميل

            </a>

        </div>


        <?php endforeach; ?>

        <?php endif; ?>


    <?php endforeach; ?>


    </div>


    <?php endif; ?>


</div>


<?php elseif ($searchQuery !== ''): ?>


<!-- =====================================================
     SEARCH RESULTS
===================================================== -->

<div class="search-page">


    <div class="search-title">

        نتائج البحث عن:

        <strong>
            <?= h($searchQuery) ?>
        </strong>

    </div>


    <?php if ($totalSearch === 0): ?>


        <div class="search-empty">

            🔍

            <br><br>

            مفيش نتائج مطابقة للبحث.

            <br>

            جرب اسم فيلم أو مسلسل أو قناة تاني.

        </div>


    <?php else: ?>


        <!-- MOVIES -->

        <?php if (
            !empty(
                $searchResults['movies']
            )
        ): ?>


        <section class="search-section">

            <div class="search-section-title">

                🎬 الأفلام

                <span class="search-count">

                    <?= count(
                        $searchResults['movies']
                    ) ?>

                </span>

            </div>


            <div class="grid">


            <?php foreach (
                $searchResults['movies']
                as $item
            ): ?>


            <?php

            $id =
                (int)(
                    $item['stream_id']
                    ?? 0
                );

            $name =
                $item['name']
                ?? '';

            $rawImage =
                $item['stream_icon']
                ??
                $item['cover']
                ??
                $item['cover_big']
                ??
                '';

            $image =
                fix_image_url(
                    $rawImage
                );

            $extension =
                $item['container_extension']
                ??
                'mp4';

            $movieURL =
                rtrim($SERVER, '/') .
                '/movie/' .
                rawurlencode($USERNAME) .
                '/' .
                rawurlencode($PASSWORD) .
                '/' .
                $id .
                '.' .
                $extension;

            $link =
                'player.php?url=' .
                urlencode($movieURL) .
                '&title=' .
                urlencode($name);

            ?>


            <a
                class="card"
                href="<?= h($link) ?>"
            >

                <div class="poster">

                    <div
                        class="noimg"
                        style="<?= $image !== '' ? 'display:none' : 'display:flex' ?>"
                    >

                        🎬

                        <span>
                            <?= h($name) ?>
                        </span>

                    </div>


                    <?php if ($image !== ''): ?>

                        <img
                            src="<?= h($image) ?>"
                            loading="lazy"
                            decoding="async"
                            referrerpolicy="no-referrer"
                            alt="<?= h($name) ?>"
                            onerror="
                                this.style.display='none';
                                var f=this.parentNode.querySelector('.noimg');
                                if(f){f.style.display='flex';}
                            "
                        >

                    <?php endif; ?>


                    <span class="type-badge">
                        فيلم
                    </span>

                </div>


                <div class="name">
                    <?= h($name) ?>
                </div>

            </a>


            <?php endforeach; ?>


            </div>

        </section>


        <?php endif; ?>


        <!-- SERIES -->

        <?php if (
            !empty(
                $searchResults['series']
            )
        ): ?>


        <section class="search-section">

            <div class="search-section-title">

                🍿 المسلسلات

                <span class="search-count">

                    <?= count(
                        $searchResults['series']
                    ) ?>

                </span>

            </div>


            <div class="grid">


            <?php foreach (
                $searchResults['series']
                as $item
            ): ?>


            <?php

            $id =
                (int)(
                    $item['series_id']
                    ?? 0
                );

            $name =
                $item['name']
                ?? '';

            $rawImage =
                $item['cover']
                ??
                $item['cover_big']
                ??
                $item['stream_icon']
                ??
                '';

            $image =
                fix_image_url(
                    $rawImage
                );

            $link =
                '?series_id=' .
                $id;

            ?>


            <a
                class="card"
                href="<?= h($link) ?>"
            >

                <div class="poster">

                    <div
                        class="noimg"
                        style="<?= $image !== '' ? 'display:none' : 'display:flex' ?>"
                    >

                        🍿

                        <span>
                            <?= h($name) ?>
                        </span>

                    </div>


                    <?php if ($image !== ''): ?>

                        <img
                            src="<?= h($image) ?>"
                            loading="lazy"
                            decoding="async"
                            referrerpolicy="no-referrer"
                            alt="<?= h($name) ?>"
                            onerror="
                                this.style.display='none';
                                var f=this.parentNode.querySelector('.noimg');
                                if(f){f.style.display='flex';}
                            "
                        >

                    <?php endif; ?>


                    <span class="type-badge">
                        مسلسل
                    </span>

                </div>


                <div class="name">
                    <?= h($name) ?>
                </div>

            </a>


            <?php endforeach; ?>


            </div>

        </section>


        <?php endif; ?>


        <!-- LIVE -->

        <?php if (
            !empty(
                $searchResults['live']
            )
        ): ?>


        <section class="search-section">

            <div class="search-section-title">

                📺 البث المباشر

                <span class="search-count">

                    <?= count(
                        $searchResults['live']
                    ) ?>

                </span>

            </div>


            <div class="grid">


            <?php foreach (
                $searchResults['live']
                as $item
            ): ?>


            <?php

            $id =
                (int)(
                    $item['stream_id']
                    ?? 0
                );

            $name =
                $item['name']
                ?? '';

            $rawImage =
                $item['stream_icon']
                ??
                $item['logo']
                ??
                '';

            $image =
                fix_image_url(
                    $rawImage
                );

            $liveURL =
                rtrim($SERVER, '/') .
                '/live/' .
                rawurlencode($USERNAME) .
                '/' .
                rawurlencode($PASSWORD) .
                '/' .
                $id .
                '.ts';

            $link =
                'player.php?url=' .
                urlencode($liveURL) .
                '&title=' .
                urlencode($name);

            ?>


            <a
                class="card"
                href="<?= h($link) ?>"
            >

                <div class="poster">

                    <div
                        class="noimg"
                        style="<?= $image !== '' ? 'display:none' : 'display:flex' ?>"
                    >

                        📺

                        <span>
                            <?= h($name) ?>
                        </span>

                    </div>


                    <?php if ($image !== ''): ?>

                        <img
                            src="<?= h($image) ?>"
                            loading="lazy"
                            decoding="async"
                            referrerpolicy="no-referrer"
                            alt="<?= h($name) ?>"
                            onerror="
                                this.style.display='none';
                                var f=this.parentNode.querySelector('.noimg');
                                if(f){f.style.display='flex';}
                            "
                        >

                    <?php endif; ?>


                    <span class="live-badge">
                        LIVE
                    </span>


                    <span class="type-badge">
                        مباشر
                    </span>

                </div>


                <div class="name">
                    <?= h($name) ?>
                </div>

            </a>


            <?php endforeach; ?>


            </div>

        </section>


        <?php endif; ?>


    <?php endif; ?>


</div>


<?php else: ?>


<!-- =====================================================
     NORMAL CONTENT
===================================================== -->

<div class="top-tabs">


    <a
        href="?tab=live"
        class="top-tab <?= $tab === 'live' ? 'active' : '' ?>"
    >
        📺 مباشر
    </a>


    <a
        href="?tab=movie"
        class="top-tab <?= $tab === 'movie' ? 'active' : '' ?>"
    >
        🎬 أفلام
    </a>


    <a
        href="?tab=series"
        class="top-tab <?= $tab === 'series' ? 'active' : '' ?>"
    >
        🍿 مسلسلات
    </a>


</div>


<!-- CATEGORIES -->

<div class="categories-wrap">

    <div class="categories">


        <a
            class="category <?= $catID === '' ? 'active' : '' ?>"
            href="?tab=<?= h($tab) ?>"
        >
            الكل
        </a>


        <?php foreach (
            $categoryNames as $cid => $cname
        ): ?>


            <a
                class="category <?= $catID === (string)$cid ? 'active' : '' ?>"
                href="?tab=<?= h($tab) ?>&cat_id=<?= h($cid) ?>"
            >

                <?= h($cname) ?>

            </a>


        <?php endforeach; ?>


    </div>

</div>


<!-- CONTENT -->

<div class="container">


    <div class="section-title">

        <h2>

            <?php if ($tab === 'live'): ?>

                📺 القنوات

            <?php elseif ($tab === 'movie'): ?>

                🎬 الأفلام

            <?php else: ?>

                🍿 المسلسلات

            <?php endif; ?>

        </h2>


        <span>

            <?= count($items) ?> عنصر

        </span>

    </div>


    <div
        id="grid"
        class="grid"
    >


    <?php if (empty($items)): ?>


        <div class="empty">

            لا توجد عناصر متاحة حالياً.

            <br><br>

            تأكد من اتصال سيرفر IPTV.

        </div>


    <?php endif; ?>


    <?php foreach ($items as $item): ?>


    <?php

    if (!is_array($item)) {
        continue;
    }

    $id =
        (int)(
            $item['stream_id']
            ??
            $item['series_id']
            ??
            0
        );

    $name =
        $item['name']
        ?? '';

    if ($tab === 'series') {

        $rawImage =
            $item['cover']
            ??
            $item['cover_big']
            ??
            $item['stream_icon']
            ??
            '';

    } else {

        $rawImage =
            $item['stream_icon']
            ??
            $item['logo']
            ??
            $item['cover']
            ??
            $item['cover_big']
            ??
            '';
    }

    $image =
        fix_image_url(
            $rawImage
        );

    $extension =
        $item['container_extension']
        ??
        'mp4';


    if ($tab === 'series') {

        $link =
            '?series_id=' .
            $id;

    } elseif ($tab === 'movie') {

        $movieURL =
            rtrim($SERVER, '/') .
            '/movie/' .
            rawurlencode($USERNAME) .
            '/' .
            rawurlencode($PASSWORD) .
            '/' .
            $id .
            '.' .
            $extension;

        $link =
            'player.php?url=' .
            urlencode($movieURL) .
            '&title=' .
            urlencode($name);

    } else {

        $liveURL =
            rtrim($SERVER, '/') .
            '/live/' .
            rawurlencode($USERNAME) .
            '/' .
            rawurlencode($PASSWORD) .
            '/' .
            $id .
            '.ts';

        $link =
            'player.php?url=' .
            urlencode($liveURL) .
            '&title=' .
            urlencode($name);
    }

    ?>


    <a
        class="card"
        href="<?= h($link) ?>"
    >

        <div class="poster">


            <div
                class="noimg"
                style="<?= $image !== '' ? 'display:none' : 'display:flex' ?>"
            >

                <?php if ($tab === 'live'): ?>

                    📺

                <?php elseif ($tab === 'movie'): ?>

                    🎬

                <?php else: ?>

                    🍿

                <?php endif; ?>


                <span>

                    <?= h($name) ?>

                </span>

            </div>


            <?php if ($image !== ''): ?>

                <img
                    src="<?= h($image) ?>"
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer"
                    alt="<?= h($name) ?>"
                    onerror="
                        this.style.display='none';
                        var f=this.parentNode.querySelector('.noimg');
                        if(f){f.style.display='flex';}
                    "
                >

            <?php endif; ?>


            <?php if ($tab === 'live'): ?>

                <span class="live-badge">
                    LIVE
                </span>

            <?php endif; ?>


            <span class="type-badge">

                <?php if ($tab === 'live'): ?>

                    مباشر

                <?php elseif ($tab === 'movie'): ?>

                    فيلم

                <?php else: ?>

                    مسلسل

                <?php endif; ?>

            </span>


        </div>


        <div class="name">

            <?= h($name) ?>

        </div>


    </a>


    <?php endforeach; ?>


    </div>


    <?php if (!empty($items)): ?>

        <a
            class="more"
            href="?tab=<?= h($tab) ?>&cat_id=<?= h($catID) ?>&limit=<?= $limit + 30 ?>"
        >

            ➕ تحميل المزيد

        </a>

    <?php endif; ?>


</div>


<?php endif; ?>


<!-- =====================================================
     BOTTOM NAV
===================================================== -->

<nav class="bottom">


    <a
        href="?tab=movie"
        class="<?= $tab === 'movie' && $searchQuery === '' ? 'active' : '' ?>"
    >

        <span class="icon">
            🎬
        </span>

        أفلام

    </a>


    <a
        href="?tab=series"
        class="<?= $tab === 'series' && $searchQuery === '' ? 'active' : '' ?>"
    >

        <span class="icon">
            🍿
        </span>

        مسلسلات

    </a>


    <a
        href="?tab=live"
        class="<?= $tab === 'live' && $searchQuery === '' ? 'active' : '' ?>"
    >

        <span class="icon">
            📺
        </span>

        مباشر

    </a>


</nav>


<script>

/* =====================================================
   SERVICE WORKER
===================================================== */

if ('serviceWorker' in navigator) {

    navigator.serviceWorker
        .register('?action=sw')
        .catch(function(){});

}


/* =====================================================
   SEARCH
===================================================== */

const searchInput =
    document.querySelector('.search');


if (searchInput) {

    searchInput.addEventListener(
        'keydown',
        function(event){

            if (
                event.key === 'Enter'
            ) {

                const value =
                    this.value.trim();

                if (value !== '') {

                    const url =
                        new URL(
                            window.location.href
                        );

                    url.search = '';

                    url.searchParams.set(
                        'q',
                        value
                    );

                    window.location.href =
                        url.toString();
                }
            }

        }
    );

}

</script>


</body>

</html>