<?php
    $config = require_once('App/config.php');
    function connect() {
        global $config; 
        global $mysqli;

        $db_host = !isset($config['db_host']) ? $config['db_host'] : 'localhost';

        if (empty($config['db_name']) || empty($config['db_passwd']) || empty($config['db_login'])) {
            setcookie('error', 'Заполните данные в App/config.php', microtime(true) + 1);
            header('Location: /');
            exit;
        } 

        else {
            $db_passwd = $config['db_passwd'];
            $db_login = $config['db_login'];
            $db_name = $config['db_name'];
            $mysqli = new mysqli($db_host, $db_login, $db_passwd, $db_name);
            $mysqli->set_charset('utf8');
            if ($mysqli->connect_errno) {
                printf("Соединение не удалось: %s\n", $mysqli->connect_error);
                exit();
            }          
            return $mysqli;
        }
        
    }  