<?php

// 报告所有错误
error_reporting(E_ALL);
// 设置最大运行时间(s)
set_time_limit(900);

// 加载配置
require_once  __DIR__ . '/config.php';

// 自动加载
spl_autoload_register(function ($class){
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . '/src/' . $path . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
// 加载七牛云用到的函数
require_once  __DIR__ . '/src/Qiniu/functions.php';

// 引入数据库备份
use Database\Backup;
// 引入鉴权类
use Qiniu\Auth;
// 引入上传类
use Qiniu\Storage\UploadManager;


// 备份数据库
if (php_sapi_name() != "cli") {
    echo '<div style="font-family: monospace;">';
}

$backupDatabase = new Backup(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
$backupFile = $backupDatabase->backupTables(TABLES, BACKUP_DIR);
// $backupDatabase->obfPrint('Backup result: ' . $backupFile, 1);

// Use $output variable for further processing, for example to send it by email
$output = $backupDatabase->getOutput();

if (!$backupFile) {
	die('Backup Error!');
}

// 上传到七牛云

// 构建鉴权对象
$auth = new Auth(QINIU_ACCESS_KEY, QINIU_SECRET_KEY);

// 生成上传 Token
$token = $auth->uploadToken(QINIU_BUCKET);

// 要上传文件的本地路径
$filePath = BACKUP_DIR . '/' . $backupFile;

// 上传到七牛后保存的文件名
$key = $backupFile;

// 初始化 UploadManager 对象并进行文件的上传。
$uploadMgr = new UploadManager();

// 调用 UploadManager 的 putFile 方法进行文件的上传。
list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
echo "\nUpload result: \n";
if ($err !== null) {
    var_dump($err);
} else {
    echo "Upload Success!\n";
    echo "hash: ".$ret['hash'];
    echo "key:  ".$ret['key'];
}

if (php_sapi_name() != "cli") {
    echo '</div>';
}