<?php
function checkLoginAttempts($ip) {

    $file = sys_get_temp_dir() . "/login_" . md5($ip);

    if (!file_exists($file)) {
        file_put_contents($file, json_encode([
            "count" => 1,
            "time" => time()
        ]));
        return true;
    }

    $data = json_decode(file_get_contents($file), true);

    if (time() - $data['time'] > 300) {
        unlink($file);
        return true;
    }

    if ($data['count'] >= 5) {
        error_log("Login rate limit exceeded | ip={$ip}");
        return false;
    }

    $data['count']++;

    file_put_contents($file, json_encode($data));

    return true;
}

?>