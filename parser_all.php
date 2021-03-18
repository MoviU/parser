<?php
    require_once 'vendor/autoload.php';
    require_once 'multi_curl.php';
    require_once 'mysql.php';

    $pages = intval($_POST['pages']);
    if ($pages > 1690) {
        setcookie('error', 'Неправильные данные', microtime(true) + 1);
        header('Location: /');
        exit();
    }
    $urls = [];
    $result = [];
    $info = [];
    $name = [];
    for ($i = 1; $i != $pages + 1 ; $i++) {
        $urls[] = "https://hyiplogs.com/hyips/?page=" . $i;
    }

    // MULTI CURL
    $info_curl = multi_curl_parser($urls, array(
        "TIMEOUT" => "15",
        "CONNECTTIMEOUT" => "15",
        "HEADER" => false, 
        "COOKIE" => '/tmp/cookies.txt',
        "USERAGENT" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36",
        "ENCODING" => 'utf-8',
    ));
    echo '<pre>';
    // ----------------------------
    // --[Парсинг]-----------------
    // ----------------------------
    foreach ($info_curl as $id => $html) {
        preg_match_all('@<div class="name-box">(.*?)</div>@su', $html, $info[]);
        preg_match_all('@<div class="name-box">.*?</div>(.+?)<div class="mt2 mb3">@su', $html, $status[]);
    }
    foreach ($info as $item) {
        foreach ($item[0] as $content) {
            preg_match_all('@class="t-up t-b-color tdu-flh fw700 fs14 lh14">(.*?)</a>@u', $content, $name[]);
            preg_match_all('@<a href="(.*?)" class="grey-link tdu-flh fs13 lh13" target="_blank">@u', $content, $url[]);
        }
    }
    
    // Выборка статусов для их определения в бд
    foreach ($status as $item) {
        foreach ($item[0] as $content) {
            preg_match('@<div class="mt5">(.*?)</div>@su', $content, $out_m[]);
            preg_match('@<div class="s-t-sts bg-sts-4">(.*?)</div>@su', $content, $out_o[]);
        }
    }
    for ($i = 0; $i < count($out_m); $i++) {
        if ($out_m[$i] == null) {
            $out[] = $out_o[$i];
        } else if ($out_o[$i] == null) {
            $out[] = $out_m[$i];
        } else {
            $out[] = $out_m[$i];
        }
    }
    // Очищаем от тегов
    for ($i = 0; $i < count($name); $i++) {
        $key = $name[$i][1][0];
        $val = $url[$i][1][0];
        $stat = trim(strip_tags($out[$i][0]));
        $stats[$key] = $stat;
        $result[$key] = $val;
    }
    // // Работа с бд
    // foreach ($result as $name => $domain) {
    //     connect()->query("INSERT INTO `projects` (`name`, `domain`) VALUES ('$name', ' $domain');");
    // }
    // foreach ($stats as $name => $stat) {
    //     $domain = $result[$name];
    //     if ($stat == "HIGH RISK / DON'T INVEST") {
    //         $stat = 'HIGH RISK';
    //     }
    //     if ($stat == '') {
    //         $stat = 'Not tracked';
    //     }
    //     $row = connect()->query("SELECT * FROM `projects` WHERE `name` = '$name';");
    //     $now_stat = mysqli_fetch_assoc($row);
    //     if ($now_stat['status']) {
    //         if ($now_stat['status'] == 'Paying') {
    //             connect()->query("UPDATE `projects` SET `domain` = '$domain', `status` = '" .$stat . "' WHERE `name` = '$name';");
    //         } else if ($now_stat['status'] != 'Paying' && $stat == 'Paying') {
    //             connect()->query("UPDATE `projects` SET `domain` = '$domain', `status` = '" .$stat . "' WHERE `name` = '$name';");
    //         }
    //     } else {
    //         connect()->query("UPDATE `projects` SET `status` = '" . $stat . "' WHERE `name` = '$name';");
    //     }
    // }

    $rows = connect()->query("SELECT `name`, `domain`, `status`, `start` FROM `projects`;");
    while ($row = mysqli_fetch_assoc($rows)) {
        if ($row['status'] == 'Paying' || $row['start'] === null) {
            if (strpos($row['domain'], "https://") !== false) {
                $projects[$row['name']] = str_replace("https://", "", $row['domain']);
            } else {
                $projects[$row['name']] = str_replace("http://", "", $row['domain']);
            }
        }
    }
    // Парсинг всей полной инфы по каждому проекту отделтьно
    foreach ($projects as $name => $project) {
        $projects_urls[$name] = "https://hyiplogs.com/project/" . trim($project);
    }

    print_r($projects_urls);
    $info_curl = multi_curl_parser($projects_urls, array(
        "TIMEOUT" => "15",
        "CONNECTTIMEOUT" => "15",
        "HEADER" => false, 
        "COOKIE" => '/tmp/cookies.txt',
        "USERAGENT" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36",
        "ENCODING" => 'utf-8',
    ));
    foreach ($info_curl as $id => $html) {
        preg_match_all('@<div class="mt5 mb5 fl">(.+?)</div>@su', $html, $start);
        preg_match_all('@<div class="info-tit">About project</div>.*?<div class="info-box">(.*?)<div class="internal-info-box pp-hyip-data mb15">@su', $html, $about[], PREG_PATTERN_ORDER);
    }

    foreach ($start as $date) {
        $date = trim(strip_tags($date[0][0]));
        $date = explode(' ', $date);
        foreach ($date as $key => $val) {
            if ($val == '') {
                unset($date[$key]);
            }
        }
    }
    foreach ($about as $info) {
        foreach ($info[0] as $id => $val) {
            preg_match_all('@<div class="name">(.*?)</div>@su', $val, $project_info_keys[]);
            preg_match_all('@<div class="txt">(.*?)</div>@su', $val, $project_info_values[]);
        }
    }
    foreach ($projects_urls as $name => $value) {
        $names[] = $name;
    }

    $counter = 0;
    for ($i = 0; $i < count($project_info_keys); $i++) {
        $name = $names[$counter];
        for ($j = 0; $j < count($project_info_keys[$i][1]); $j++) {
            $key = trim(strip_tags($project_info_keys[$i][1][$j]));
            $value = trim(strip_tags($project_info_values[$i][1][$j]));
            $project_info[$name][$key] = $value;
        }
        $counter++;
    }
    
    
    print_r($project_info);
    echo '</pre>';
    connect()->close();
    // header('Location: /');