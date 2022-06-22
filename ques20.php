<?php
echo "Amit</br>";
$file=fopen("new.txt","r+");
echo fwrite($file,"Hello World");
?>