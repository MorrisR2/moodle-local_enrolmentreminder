<?php

function enrolmentreminder_processtemplate($string, array $params) {
    foreach($params as $name=>$param) {
         if ($param !== NULL) {
             if (is_array($param) or (is_object($param) && !($param instanceof lang_string))) {
                 $param = (array)$param;
                 $search = array();
                 $replace = array();
                 foreach ($param as $key=>$value) {
                     if (is_int($key)) {
                         // we do not support numeric keys - sorry!
                         continue;
                     }
                     if (is_array($value) or (is_object($value) && !($value instanceof lang_string))) {
                         // we support just string or lang_string as value
                         continue;
                     }
                     $search[]  = "{\$$name->".$key.'}';
                     $replace[] = (string)$value;
                 }
                 if ($search) {
                     $string = str_replace($search, $replace, $string);
                 }
             } else {
                 $string = str_replace("{\$$name}", (string)$param, $string);
             }
         }
    }
    return $string;
}


