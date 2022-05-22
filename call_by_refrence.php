<?php
function add(&$num)
{
return $num=$num*2;
}
$num=10;
echo add($num);
echo "<br/>";
echo $num;
?>