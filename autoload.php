<?php
spl_autoload_register(function ($class) {
    if (false !== stripos($class, 'Liuhan\WeChatPayJs')) {
        require_once __DIR__.'/'.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 7)).'.php';
    }
});
