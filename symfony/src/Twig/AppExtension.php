<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('highlight', [$this, 'highlight']),
            new TwigFilter('strpad', [$this, 'strpad']),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('version', array($this, 'getVersion'))
        ];
    }

    public function highlight($string, $pattern = '')
    {
        $pattern = str_replace('/', '\/', preg_quote($pattern));
        return preg_replace("/(\p{L}*?)(".$pattern.")(\p{L}*)/ui", "$1<mark>$2</mark>$3", $string);
    }

    public function strpad($number, $pad_length, $pad_string) {
        return str_pad($number, $pad_length, $pad_string, STR_PAD_LEFT);
    }

    public function getVersion($is_admin = false)
    {
        ob_start();
        include 'version.php';
        return ob_get_clean();
    }
}