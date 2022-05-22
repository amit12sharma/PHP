<?php
echo"Amit<br/>";
$str="Hello How Are You";
$exp="/Hello/i";
echo preg_match($exp,$str);;
echo"<br/>";
echo preg_match_all($exp,$str);
?>