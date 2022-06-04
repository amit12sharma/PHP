<?php

$arr1 = array('a', 'b', 'c');
$arr2 = array('b', 'd', 'e');
$fruits = array("d" => "lemon", "a" => "orange", "b" => "banana", "c" => "apple");
$operation = $_POST['operation'];
switch ($operation) {

    case '1':

        echo "Original Array Elements : <PRE>";
        print_r($fruits);
        echo "</PRE>";
        ksort($fruits);
        echo " <font color=red> KSort </font><PRE>";
        foreach ($fruits as $key => $val) {

            echo "$key = $val\n";
        }
        krsort($fruits);
        echo " <font color=red> KRSort </font><PRE>";
        foreach ($fruits as $key => $val) {

            echo "$key = $val\n";
        }
        break;
    case '2':

        echo "Original Array Elements : <PRE>";
        print_r($fruits);
        echo "</PRE>";
        sort($fruits);
        echo " <font color=red> kSort </font><PRE>";
        foreach ($fruits as $key => $val) {
            echo "$key = $val\n";
        }
        break;
    case '3':

        echo "Original Array Elements : <PRE>";
        function test_odd($var)
        {

            return ($var & 1);
        }
        $a1 = array(1, 2, 3, 4);
        print_r($a1);
        echo "</PRE>";
        echo " <font color=red> test_odd </font><PRE>";
        print_r(array_filter($a1, "test_odd"));
        break;

    case '4':

        echo "Original Array Elements : <PRE>";
        print_r($arr1);
        echo "</PRE>";
        print_r($arr2);
        echo "</br>";
        $mer = array_merge($arr1, $arr2);
        echo " <font color=red> Merge </font><PRE>";
        print_r($mer);
        echo "</br>";
        break;

    case '5':

        echo "Original Array Elements : <PRE>";
        print_r($arr1);
        echo "</PRE>";
        print_r($arr2);
        echo "</br>";
        $interset = array_intersect($arr1, $arr2);
        echo " <font color=red> Intersection </font><PRE>";
        print_r($interset);
        echo "</br>";
        break;

    case '6':
        echo "Original Array Elements : <PRE>";
        print_r($arr1);
        echo "</PRE>";
        print_r($arr2);
        echo "</br>";
        $uni = array_unique(array_merge($arr1, $arr2));
        echo " <font color=red>>UNION</font><br>";
        print_r($uni);
        echo "</br>";
        break;

    case '7':
        echo "Original Array Elements : <PRE>";
        print_r($arr1);
        echo "</PRE>";
        print_r($arr2);
        echo "</PRE>";
        $diff = array_diff($arr1, $arr2);
        echo "DiFFERENCE<br>";
        print_r($diff);
        echo "</br>";

        break;
}
