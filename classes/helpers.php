<?php

if (!function_exists('mypa_stringify_url')) {
    function mypa_stringify_url($parsedUrl) {
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'].'://' : '';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $port = isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : '';
        $user = isset($parsedUrl['user']) ? $parsedUrl['user'] : '';
        $pass = isset($parsedUrl['pass']) ? ':'.$parsedUrl['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        $query = isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '';
        $fragment = isset($parsedUrl['fragment']) ? '#'.$parsedUrl['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }
}

if (!function_exists('mypa_parse_url')) {
    function mypa_parse_url($urlString, $component = -1) {
        return call_user_func_array('parse_url', func_get_args());
    }
}
