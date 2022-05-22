<?php
echo"Amit<br/>";
$arr=array("Hello=>15","Hey=>9","Class=>7");
asort($arr);
ksort($arr);
//$len=count($arr);
foreach($arr as $values)
{
echo "value=".$values;
echo "<br>";
}
?>