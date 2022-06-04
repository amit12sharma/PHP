<?php
$name = $_POST['name'];
$age = $_POST['age'];
$email = $_POST['email'];

$servername = "localhost";
$username = "root";
$password = "";
$db = "bca";

$conn = new mysqli($servername, $username, $password, $db);

if ($conn->connect_error){
	die("Connection failed: ". $conn->connect_error);
}

if (filter_var($email,FILTER_VALIDATE_EMAIL) === FALSE) {
	print "Bad Email";

} elseif (filter_var($age,FILTER_VALIDATE_INT) === FALSE) {
	print "Bad Age";
} else {

$sql = "insert into emailSignup(name,age,email) values('$name','$age','$email')";

if ($conn->query($sql) === TRUE) {
	echo "ADDED: ".$name.", ".$age.", ".$email;
} else {
	echo "Error: ".$sql."<br>".$conn->error;
}
}
$conn->close();

?>
