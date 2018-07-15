<?php
/**
 * 设置数据库参数
 */
// 数据库服务器
define("DB_HOST", '127.0.0.1');
// 数据库用户名
define("DB_USER", 'root');
// 数据库密码
define("DB_PASSWORD", 'topsts');
// 数据库名
define("DB_NAME", 'myoa');
// 数据库表
define("TABLES", '*'); // 全部表
// define("TABLES", 'table1, table2, table3'); // 指定表

// 字符集
define("CHARSET", 'utf8');
// 为True时备份gzip文件，为False时备份sql文件
define("GZIP_BACKUP_FILE", true);
// 备份目录
define("BACKUP_DIR", 'backup'); // Comment this line to use same script's directory ('.')
// 恢复文件（备份时用不到）
// define("BACKUP_FILE", 'your-backup-file.sql.gz'); // Script will autodetect if backup file is gzipped based on .gz extension

// 填写七牛云的 Access Key 、 Secret Key 在 https://portal.qiniu.com/user/key 获取
define('QINIU_ACCESS_KEY', 'V0d7Xu9Dk70000000000uXPFTNt4lP0000000000');
define('QINIU_SECRET_KEY', 'zh2kmpMWgN00000000009KF2ENAjjI0000000000');
// 填写七牛云BUCKET
define('QINIU_BUCKET', 'backup');
