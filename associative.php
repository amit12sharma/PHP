<?php
echo"Amit<br/>";
$arr=array("Hello"=>"8","Hey"=>"9","Class"=>"10");
echo $arr['Hello'];
echo "<br/>";
for($i=1;$i<=count($arr);$i++){
echo $arr[$i];
}
foreach($arr as $key)
{
	echo $key;
}
?>
