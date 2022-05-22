<html>
<body>
<form action=<?php echo $_SERVER['PHP SELF']?> method= Request>
Name:<input type="text" name="Name">
Rollno:<input type="text" name="Rollno">
<input type="submit" name="submit">
</form>
</body>
<html>



<?php
if(isset($_REQUEST['submit']))
{
echo $_REQUEST['Name'];
echo $_REQUEST['Rollno'];
}
?>