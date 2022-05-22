<?php
echo"Amit<br/>";
function myfunction()
{
static $a=10;
$a++;
echo"$a<br/>";
}
myfunction();
myfunction();
myfunction();
?>