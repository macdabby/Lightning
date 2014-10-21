<?php

namespace Lightning\View\Field;

use Lightning\Tools\Request;
use Lightning\View\Field;

class Time extends Field {
    public static function today() {
        return gregoriantojd(date('m'), date('d'), date('Y'));
    }

    public static function getDate($id, $allow_blank = true) {
        $m = Request::get($id ."_m");
        $d = Request::get($id ."_d");
        $y = Request::get($id ."_y");
        if($m > 0 && $d > 0){
            if($y == 0) $y = date("Y");
            return gregoriantojd($m, $d, $y);
        } elseif (!$allow_blank) {
            return gregoriantojd(date("m"),date("d"),date("Y"));
        } else {
            return 0;
        }
    }

    public static function getTime($id, $allow_blank = true) {
        $h = Request::get($id .'_h', 'int');
        $i = Request::get($id .'_i');
        $a = Request::get($id .'_a');
        if (empty($h)) {
            if ($allow_blank) {
                return 0;
            } else {
                $time = explode("/",date("h/i/a",time()));
                $h = $time[0];
                $i = $time[1];
                $a = $time[2];
            }
        }
        if ($a == "PM") {
            $h += 12;
        }
        return ($h * 60) + $i;
    }

    public static function getDateTime($id, $allow_blank = true) {
        $m = Request::get($id .'_m', 'int');
        $d = Request::get($id .'_d', 'int');
        $y = Request::get($id .'_y', 'int');
        $h = Request::get($id .'_h', 'int');
        $i = str_pad(Request::get($id .'_i', 'int'), 2, 0, STR_PAD_LEFT);
        $a = Request::get($id . '_a');

        if ($allow_blank && (empty($m) || empty($d) || empty($y) || empty($h) || empty($i) || empty($s))) {
            return 0;
        }

        return strtotime("{$m}/{$d}/{$y} {$h}:{$i} {$a}");
    }

    public static function printDate($value) {
        if($value == 0) return '';
        $date = explode('/',jdtogregorian($value));
        return "{$date[0]}/{$date[1]}/{$date[2]}";
    }

    public static function printTime($value) {
        if ($value == 0) {
            return '';
        }
        $i = str_pad($value % 60, 2, 0, STR_PAD_LEFT);
        $h = ($value - $i) / 60;
        if ($h > 12) {
            $a = "PM";
            $h -= 12;
        } else {
            $a = "AM";
        }
        return "{$h}:{$i} {$a}";
    }

    public static function printDateTime($value) {
        if(empty($value)) {
            return "";
        } else {
            return date('m/d/Y h:ia', $value);
        }
    }

    public static function datePop($field, $value, $allow_zero, $first_year = 0){
        if(!$allow_zero && ($value == 0 || $value == '')){
            $date = array(date('m'), date('d'), date('Y'));
        } else $date = explode('/', jdtogregorian($value));
        $output = self::monthPop($field . '_m', $date[0], $allow_zero);
        $output .= ' / ';
        $output .= self::dayPop($field . '_d', $date[1], $allow_zero);
        $output .= ' / ';
        $output .= self::yearPop($field . '_y', $date[2], $allow_zero, $first_year);
        return $output;
    }

    public static function timePop($field, $value, $allow_zero){
        if(!$allow_zero && empty($value)){
            $time = explode("/", date("h/i/a", time()));
            $h = $time[0];
            $i = $time[1];
            $a = $time[2];
            if($a == 'PM') $h += 12;
            $value = ($h*60)+$i;
        } else {
            $i = $value%60;
            $h = ($value-$i)/60;
            if($h > 12){
                $a = "PM";
                $h -= 12;
            } else {
                $a = "AM";
            }
        }

        $output = self::hourPop($field."_h", $h, $allow_zero) . ':';
        $output .= self::minutePop($field . '_i', ($value==0 || $value='') ? '' : $i, $allow_zero);
        $output .= ' ' . self::APPop($field . '_a', $a, $allow_zero);
        return $output;
    }

    public static function dateTimePop($field, $value, $allow_zero, $first_year = 0){
        if(!$allow_zero && empty($value)) {
            $value = time();
        }

        $time = ($value == 0) ? array(0,0,0,0,0,0,0) : explode("/",date("m/d/Y/h/i/s/a", $value));
        $output = self::monthPop($field."_m", $time[0], $allow_zero, '', 'dateTimePop') . ' / ';
        $output .= self::dayPop($field."_d", $time[1], $allow_zero, 'dateTimePop') . ' / ';
        $output .= self::yearPop($field."_y", $time[2], $allow_zero, $first_year, '', 'dateTimePop') . ' at ';
        $output .= self::hourPop($field."_h", $time[3], $allow_zero, 'dateTimePop') . ':';
        $output .= self::minutePop($field."_i", $time[4], $allow_zero, 'dateTimePop') . ' ';
        $output .= self::APPop($field."_a", $time[6], $allow_zero, 'dateTimePop');
        return $output;
    }

    public static function hourPop($field, $value = '', $allow_zero = false, $class = 'timePop'){
        $output = "<select name='$field' class='{$class}'>";
        if($allow_zero)
            $output .= "<option value=''></option>";
        for($i = 1; $i <= 12; $i++){
            $output .= "<option value='{$i}'";
            if($value == $i)
                $output .= " SELECTED";
            $output .= ">{$i}</option>";
        }
        $output .= "</select>";
        return $output;
    }

    /**
     * Build a popup selector for minutes.
     *
     * @param string $field
     *   The field name.
     * @param string $value
     *   The default value.
     * @param boolean $allow_zero
     *   Whether to allow the field to be blank.
     * @param string $class
     *   A class to add to the element.
     *
     * @return string
     *   The rendered HTML.
     */
    public static function minutePop($field, $value = '', $allow_zero = false, $class = 'timePop'){
        $output = "<select name='{$field}' class='{$class}'>";
        if ($allow_zero) {
            $output .= '<option value=""></option>';
        }
        for ($i = 0; $i <= 45; $i += 15) {
            $output .= '<option value="' . $i . '"';
            if($value >= $i && $value < $i+15)
                $output .= ' SELECTED';
            $output .= '>' . str_pad($i,2,0,STR_PAD_LEFT) . '</option>';
        }
        $output .= '</select>';
        return $output;
    }

    /**
     * Build a popup to select AM/PM
     *
     * @param string $field
     *   The field name.
     * @param string $value
     *   The default value.
     * @param boolean $allow_zero
     *   Whether to allow the field to be blank.
     * @param array $attributes
     *   A class to add to the element.
     *
     * @return string
     *   The rendered HTML
     */
    public static function APPop($field, $value = '', $allow_zero = false, $attributes = array()){
        $values = array();
        if ($allow_zero) {
            $values[''] = '';
        }
        $values = array_merge($values, array('AM' => 'AM', 'PM' => 'PM'));

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'timePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, strtoupper($value), $attributes);
    }

    public static function dayPop($field, $day=0, $allow_zero = false, $attributes = array()){
        $values = array();
        if ($allow_zero) {
            $values[''] = '';
        }
        $values = array_merge($values, array_combine(range(1, 31), range(1, 31)));

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'datePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, $day, $attributes);
    }

    public static function monthPop($field, $month=0, $allow_zero = false, $attributes = array()){
        $values = array();
        if ($allow_zero) {
            $values[''] = '';
        }
        $info = cal_info(0);
        $values = array_merge($values, $info['months']);

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'datePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, $month, $attributes);
    }

    public static function yearPop($field, $year=0, $allow_zero = false, $first_year=0, $js = '', $class='datePop'){
        $output = "<select name='$field' id='$field' {$js} class='{$class}'>";
        if($allow_zero)
            $output .= "<option value=''></option>";
        else if ($year==0)
            $year = date("Y");
        $start_year = date("Y")-1;
        if($year>0) $start_year = min($year, $start_year);
        if($first_year > 0)$start_year = min($first_year, $start_year);
        for($i = $start_year; $i <= date("Y", time())+10; $i++){
            $output .= "<option value='{$i}'";
            if($year == $i)
                $output .= " SELECTED";
            $output .= ">{$i}</option>";
        }
        $output .= "</select>";
        return $output;
    }
}