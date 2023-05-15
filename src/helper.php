<?php

if (!function_exists('show_gspec')) {
    /**
     * 商品规格过滤显示
     * @param string $spec 原规格内容
     * @return string
     */
    function show_gspec(string $spec): string
    {
        $specs = [];
        foreach (explode(';;', $spec) as $sp) {
            $specs[] = explode('::', $sp)[1];
        }
        return join(' ', $specs);
    }
}