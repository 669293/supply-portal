<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('highlight', [$this, 'highlight']),
        ];
    }

    public function highlight($string, $pattern = '')
    {
        $pattern = str_replace('/', '\/', preg_quote($pattern));
        return preg_replace("/(\p{L}*?)(".$pattern.")(\p{L}*)/ui", "$1<mark>$2</mark>$3", $string);
    }
}