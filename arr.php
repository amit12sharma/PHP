<?php
echo"Amit<br>";
$a=array("cat"=>10,"dog"=>12,"blue"=>11,"sky"=>14,"red"=>13);
print_r(array_pad($a,7,"red"));
echo"<br><br>";
print_r(array_slice($a,2));
echo"<br><br>";
foreach($a as $x=>$x_value)
{ 
	echo"key ".$x." value=".$x_value;
	echo"<br><br>";
}
$a1=array("a"=>"red","b"=>"green","c"=>"blue","d"=>"yellow");
$a2=array("a"=>"purple","b"=>"orange");
array_splice($a1,0,2,$a2);
print_r($a1);
echo"<br><br>";
$z= array("Dog","Cat","Horse");
list($a, $b, $c) = $z;
echo "Animals:<br>  $a<br>$b<br>  $c";
?>
