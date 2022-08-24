<?php
    //Путь до дирректории с GIT
    const GIT_PATH = '/usr/share/nginx/html/snab.artelvitim.ru/';

    //Выполнение консольной команды
    function execCommand($cmd, $workdir = null) {
        if (is_null($workdir)) {$workdir = GIT_PATH;}

        $descriptorspec = array(
            0 => array("pipe", "r"), //stdin
            1 => array("pipe", "w"), //stdout
            2 => array("pipe", "w"), //stderr
            );

        $process = proc_open($cmd, $descriptorspec, $pipes, $workdir, null);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return $stdout;
    }

    //Функция получения версии
    function getAppVersion($is_admin) {
        $info = execCommand('cd '.GIT_PATH.' && git describe --long');
        $parts = preg_split('/-/', $info);

        if (!is_array($parts) || sizeof($parts) < 3) {return 'Версия приложения: n/a';}
        $version = 'Версия приложения: '.$parts[0].'.'.$parts[1];

        $date = date('d.m.Y', execCommand('cd '.GIT_PATH.' && git show -s --format="%ct"'));
        $version .= ' от '.$date;

        if ($is_admin) {
            $hash = execCommand('cd '.GIT_PATH.' && git show -s --format="%H"');
            $version .= ' (<a href="https://github.com/669293/snab.artelvitim.ru/commit/'.trim($hash).'" target="_blank">'.trim($hash).'</a>)';
        }

        return $version;
    }

    echo getAppVersion($is_admin);
?>