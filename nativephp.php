<?php
return [
 'app_id'=>env('NATIVEPHP_APP_ID','com.sonatv.app'),
 'app_version'=>env('NATIVEPHP_APP_VERSION','1.0.0'),
 'app_version_code'=>(int) env('NATIVEPHP_APP_VERSION_CODE',1),
 'start_url'=>env('NATIVEPHP_START_URL','/'),
];
