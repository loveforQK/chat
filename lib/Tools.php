<?php
namespace lib;

class Tools{
    public static function checkLetterNumber($value,$preg = null){
        if($preg === null){
            $preg = "/^[0-9a-zA-Z]+$/";
        }
        if(preg_match($preg,$value)){
            return true;
        }else{
            return false;
        }
    }

    public static function replaceString($value,$preg = null,$replace = ''){
        if($preg === null){
            $preg = '/[^\x{00}-\x{ff}A-Za-z0-9| |,|，|。|！|.|!|]/';
        }
        return preg_replace($preg,$replace,$value);
    }

}