<?php
    require_once 'vendor/autoload.php';
    require_once 'multi_curl.php';
    require_once 'mysql.php';

    $pages = intval($_POST['pages']);
    if ($pages > 1690 || $pages == null) {
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
    // Цикл для получения ссылок по шаблону для каждого поректа отдельно
    // foreach ($projects as $name => $project) {
    //     $projects_urls[$name] = "https://hyiplogs.com/project/" . trim($project);
    // }
    $projects_urls = [
        'Teros' => 'https://hyiplogs.com/project/teros.biz/',
        'Rapidclaimer' => 'https://hyiplogs.com/project/rapidclaimer.com/'
    ];
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
        preg_match_all('@<div class="mt5 mb5 fl">(.+?)</div>@su', $html, $start[]);
        preg_match_all('@<div class="info-tit">About project</div>.*?<div class="info-box">(.*?)<div class="internal-info-box pp-hyip-data mb15">@su', $html, $about[], PREG_PATTERN_ORDER);
        preg_match_all('@<div class="info-tit">Technical details</div>.*?<div class="info-box">(.*?)<div class="col-xs-12 col-sm-5">@su', $html, $technical[], PREG_PATTERN_ORDER);
        preg_match_all('@<div class="info-tit">Domain Information</div>.*?<div class="info-box">(.*?)<div class="internal-info-box pp-last-events-box mb15">@su', $html, $domain[], PREG_PATTERN_ORDER);
        preg_match_all('@<div class="info-tit">Attendance</div>.*?<div class="info-box">(.*?)<div class="internal-info-box pp-last-events-box mb15">@su', $html, $attendance[], PREG_PATTERN_ORDER);
        preg_match_all('@<div class="info-tit">Publications</div>.*?<div class="info-box">(.*?)<div id="widget_box" class="dn">@su', $html, $publications[], PREG_PATTERN_ORDER);
        preg_match_all('@<td class="tal">(.*?)<td class="tac lastwtd">@su', $html, $table[], PREG_PATTERN_ORDER);
    }
    // print_r($table);
    // Ключи для таблицы информации о блогах
    $key = $table[0][0][0];
    $key = strip_tags($key);
    $key = preg_replace('/\s+/', ' ', $key);
    $keys = explode(' ', $key);
    $table_keys[] = $keys;
    // Чистим список ключей
    foreach ($table_keys[0] as $id => $key) {
        if ($key == '') {
            unset($table_keys[0][$id]);
        }
    }
    // Определение названия каждого проекта
    foreach ($projects_urls as $name => $value) {
        $names[] = $name;
    }
    // Продолжаем работать с таблицей
    foreach ($table as $id => $html) {
        $name = $names[$id];
        $i = 0;
        foreach ($html[$i] as $val) {
            $val = preg_replace('/\s+/', ' ', $val);
            $val = explode('<td class="tac">', $val);
            $table_values_raw[$name][] = $val;
            $i++;
        }
    }
    // print_r($table_values_raw);
    $j = 0; 
    // Делаем полученые данные болле вменяемыми
    foreach ($table_values_raw[$names[$j]] as $key => $value) {
        $name = $names[$j];
        // Ключи, по индексу, 0 не трогаем
        if ($key >= 1) {
            // Циклично переоразовываем все данные
            foreach ($table_values_raw[$name] as $column => $data) {
                $data = preg_replace('/\s+/', ' ', $data);
                $data = strip_tags($data);
                if (strpos($data, '#')) {
                    $data = explode('#', $data);
                    $table_values[$keys[$column]][] = $data[0];
                } else if (strpos($data, 'from')) {
                    $data = explode('from', $data);
                    $table_values[$keys[$column]][] = $data[0];
                } else {
                    $table_values[$keys[$column]][] = $data;
                }
            }
        }
 
    }

    print_r($table_values);
    foreach ($start as $html) {
        for ($i = 0; $i < count($start); $i++) {
            $date = $start[$i][0][0];
            $name = $names[$i];
            $date = strip_tags($date);
            $date = preg_replace('/\s+/', ' ', $date);
            $project_info[$name]['Start'] = $date;
        }
    }

    foreach ($about as $info) {
        foreach ($info[0] as $id => $val) {
            preg_match_all('@<div class="name">(.*?)</div>@su', $val, $project_info_keys[]);
            preg_match_all('@<div class="txt">(.*?)</div>@su', $val, $project_info_values[]);
        }
    }

    $counter = 0;
    for ($i = 0; $i < count($project_info_keys); $i++) {
        $name = $names[$counter];
        for ($j = 0; $j < count($project_info_keys[$i][1]); $j++) {
            $key = trim(strip_tags($project_info_keys[$i][1][$j]));
            $value = strip_tags($project_info_values[$i][1][$j]);
            $value = preg_replace('/\s+/', ' ', $value);
            $project_info[$name][$key] = $value;
        }
        $counter++;
    }

    foreach ($technical as $info) {
        foreach ($info[0] as $id => $val) {
            preg_match_all('@<div class="name">(.*?)</div>@su', $val, $project_technical_keys[]);
            preg_match_all('@<div class="txt">(.*?)</div>@su', $val, $project_technical_values[]);
        }
    }
    $counter = 0;
    for ($i = 0; $i < count($project_technical_keys); $i++) {
        $name = $names[$counter];
        for ($j = 0; $j < count($project_technical_keys[$i][1]); $j++) {
            $key = trim(strip_tags($project_technical_keys[$i][1][$j]));
            if ($key == 'IP-address') {
                $value = strip_tags($project_technical_values[$i][1][$j]);
                $value = preg_replace('/\s+/', ' ', $value);
                $value = explode('(', $value);
                $value[1] = explode(')', $value[1]);
                $project_info[$name]['IP-address'] = $value[0];
                $project_info[$name]['Location-country'] = $value[1][0];
            } else {
                $value = strip_tags($project_technical_values[$i][1][$j]);
                $value = preg_replace('/\s+/', ' ', $value);
                $project_info[$name][$key] = $value;
            }           
        }
        $counter++;
    }
    foreach ($domain as $info) {
        foreach ($info[0] as $id => $val) {
            preg_match_all('@<div class="name">(.*?)</div>@su', $val, $project_domain_keys[]);
            preg_match_all('@<div class="txt">(.*?)</div>@su', $val, $project_domain_values[]);
        }
    }
    $counter = 0;
    for ($i = 0; $i < count($project_domain_keys); $i++) {
        $name = $names[$counter];
        for ($j = 0; $j < count($project_domain_keys[$i][1]); $j++) {
            $key = trim(strip_tags($project_domain_keys[$i][1][$j]));
            if ($key == 'Registrar') {
                preg_match('@<span class="of-get-data dib mr10" data-type="domain_registrar" data-val="2">(.*?)</span>@su', $project_domain_values[$i][1][$j], $value);
                $project_info_values[$name][$key] = $value[0];
            } else {
                $value = strip_tags($project_domain_values[$i][1][$j]);
                $value = preg_replace('/\s+/', ' ', $value);
                $project_info[$name][$key] = $value;
            }
        }
        $counter++;
    }
    foreach ($attendance as $info) {
        foreach ($info[0] as $id => $val) {
            preg_match_all('@<div class="name">(.*?)</div>@su', $val, $project_attendance_keys[]);
            preg_match_all('@<div class="txt"><span class="fs13 vam">(.*?)</span></div>@su', $val, $project_attendance_values[]);
        }
    }

    $counter = 0;
    for ($i = 0; $i < count($project_attendance_keys); $i++) {
        $name = $names[$counter];
        for ($j = 0; $j < count($project_attendance_keys[$i][1]); $j++) {
            $key = trim(strip_tags($project_attendance_keys[$i][1][$j]));   
            $value = strip_tags($project_attendance_values[$i][1][$j]);
            $value = preg_replace('/\s+/', ' ', $value);
            if ($value = '') {
                $value = '---';
            }
            $project_info[$name][$key] = $value;
        }
        $counter++;
    }

    foreach ($publications as $info) {
        foreach ($info[0] as $id => $val) {
            preg_match_all('@<div class="name mt3">(.*?)</div>@su', $val, $project_publications_forum_keys[], PREG_PATTERN_ORDER);
            preg_match_all('@<div class="txt" style="font-size: 0;">(.*?)</div>@su', $val, $project_publications_forum_values[], PREG_PATTERN_ORDER);
            preg_match_all('@<div class="name mt4">(.*?)</div>@su', $val, $project_publications_rewiews_keys[], PREG_PATTERN_ORDER);
            preg_match_all('@<div class="txt">(.*?)</div>@su', $val, $project_publications_rewiews_values[], PREG_PATTERN_ORDER);
        }
    }
    foreach ($project_publications_rewiews_values as $html) {
        foreach ($html[0] as $val) {
            preg_match_all('@<a href="(.*?)" target="_blank" class="dib vam mr5">@su', $val, $project_review[]);
        };
    }

    $counter = 0;
    for ($i = 0; $i < count($project_publications_forum_keys); $i++) {
        $name = $names[$counter];
        for ($j = 0; $j < count($project_publications_forum_keys[$i][1]); $j++) {
            $key = trim(strip_tags($project_publications_forum_keys[$i][1][$j]));
            $value = strip_tags($project_publications_forum_values[$i][1][$j]);
            $value = preg_replace('/\s+/', ' ', $value);
            $project_info[$name][$key] = $value;
        }
        $counter++;
    }
    $counter = 0;
    for ($i = 0; $i < count($project_publications_rewiews_keys); $i++) {
        $name = $names[$counter];
        for ($j = 0; $j < count($project_review[$i][1]); $j++) {
            $key = 'Reviews';
            $value = strip_tags($project_review[$i][1][$j]);
            $value = preg_replace('/\s+/', ' ', $value);
            
            $project_info[$name][$key][] = $value;
        }
        $counter++;
    }
    echo '</pre>';
    connect()->close();
    // header('Location: /');