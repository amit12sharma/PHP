<?php
echo "Amit</br>";

class cal
{
public $a,$b,$c;
function sum()
{
$this->c = $this->a + $this->b;
return $this -> c;
}
function mul()
{
	$this->c = $this->a * $this->b;
return $this -> c;
}
}
$c1=new cal();
$c1-> a=10;
$c1-> b=20;
echo "sum:",$c1->sum();
echo "<br/>";
echo $c1->mul();
echo "<br/>";
$c2=new cal();
$c2->a=20;
$c2->b=30;
echo $c2->sum();
$c2->mul();
?>