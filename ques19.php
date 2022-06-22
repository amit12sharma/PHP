<?php
echo "Amit</br>";
$myfile = fopen("new.txt", "r") or die("Unable to open file!");
echo fread($myfile,filesize("new.txt"));
fclose($myfile);
?>