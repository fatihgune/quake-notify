<?php


namespace App\Traits;


trait UtilityTrait
{
    /**
     * Get the partition of a string between two positions.
     *
     * @param $string
     * @param $start
     * @param $end
     * @return false|string
     */
    public function stringBetween($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini === 0) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    /**
     * Find the array element that contains given string and return its key.
     *
     * @param $arr
     * @param $keyword
     * @return int|string
     */
    private function returnKeyFromArrayElementThatContainsGivenString($arr, $keyword)
    {
        foreach ($arr as $index => $string) {
            if (strpos($string, $keyword) !== FALSE) {
                return $index;
            }
        }

    }



}
