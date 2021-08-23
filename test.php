<?php
    $data = [];
    array_map(static function($value) use(&$data) {$data[$value] = 1;},[1,2,3,4,6,6,] );
    var_dump($data);