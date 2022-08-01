<?php
//網頁初始引入檔
include_once(__DIR__ . '/../config/config.php');
include_once(__DIR__ . '/pdo.php');
include_once(__DIR__ . '/Works.php');

//header初始宣告
header("Content-Type:text/html; charset=utf-8");
//SESSION功能開啟
if (!isset($_SESSION)) {
    BaseWork::start_session(SESSION_TIMEOUT); // 代表 1小時後會過期 (取代原本 session_start())
    header("Cache-control: private");
}
//連線資訊(可省略,可在檔案內寫好配置)
MYPDO::$user = USERNAME_SQL;
MYPDO::$pwd = PASSWORD_SQL;
MYPDO::$db = DATABASE_SQL;
