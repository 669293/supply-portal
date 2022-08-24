<?php
    //Выполнение консольной команды
    function execCommand($cmd) {
        $proc = popen($cmd, 'r');
        $live_output = ''; $output = '';

        while (!feof($proc)) {
            $live_output = fread($proc, 4096);
            $output = $output.$live_output;
        }

        pclose($proc);

        return $output;;
    }

    //Функция получения версии
    function getAppVersion() {
        $info = execCommand('cd .. && cd .. && git describe --long');
        $parts = preg_split('/-/', $info);

        if (!is_array($parts) || sizeof($parts) < 3) {return 'Версия приложения: n/a';}
        $version = 'Версия приложения: '.$parts[0].'.'.$parts[1];

        $date = date('d.m.Y', execCommand('cd .. && cd .. && git show -s --format="%ct"'));
        $version .= ' от '.$date;

        $hash = execCommand('cd .. && cd .. && git show -s --format="%H"');
        $version .= ' (<a href="https://github.com/669293/snab.artelvitim.ru/commit/'.trim($hash).'" target="_blank">'.trim($hash).'</a>)';

        return $version;
    }

    echo getAppVersion();
?>