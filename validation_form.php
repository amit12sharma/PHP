 <?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <style>
    li:hover{
background-color:Purple;
border:1px solid black;
border-radius: 20PX;
    }
    </style>
</head>
<body>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-info ">
    <div class="container-fluid">
      <div class="nav-bar-header">
        <a class="navbar-brand" …
[4:09 PM, 6/1/2022] +91 97179 32760: <?php
session_start();
?>
<!doctype html>

<head>
    <title>login</title>
    <style>
      *{
        box-sizing: border-box;
      }
      span.error{
        color:crimson;
      }
        div.form {
            text-transform: uppercase;
            display: block;
            border: 2px solid black;
            text-align: center;
            background-color: white;
            padding: 10px;
        }

        div.form input {
            margin: 10px;
        }

        div.button {
            padding: 8px;
            text-align: center;
            margin-top: 5px;
            background-color: black;
        }

        button {
            padding: 8px;
            background-color: azure;
            font-size: medium;
            border: 1px solid black;
            border-radius: 10px;
            margin-left: 20px;
            cursor: pointer;
        }

        h1 {
          border:5px solid black;
            text-align: center;
        }
    </style>
</head>

<body bgcolor="Azure">
<?php
// define variables and set to empty values
$nameErr = $emailErr = $genderErr = $mobilenoErr = $websiteErr =  "";
$name = $email = $gender = $mobileno = $website = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (empty($_POST["name"])) {
    $nameErr = "required";
  } else {
    $name =input_data($_POST["name"]);
  }
  
  if (empty($_POST["email"])) {
    $emailErr = "required";
  } else {
    $email =input_data($_POST["email"]);
  }
    
  if (empty($_POST["website"])) {
    $websiteErr = "";
  } else {
    $website =input_data($_POST["website"]);
  }

  if (empty($_POST["mobileno"])) {
    $mobilenoErr = "";
  } else {
    $mobileno =input_data($_POST["mobileno"]);
  }

  if (empty($_POST["gender"])) {
    $genderErr = "required";
  } else {
    $gender =input_data($_POST["gender"]);
  }
}

function input_data($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

?>

<h1><marquee direction=right>Signup</marquee></h1>
<p><span class="error">* required field</span></p>
<form method="post" action="<?php echo ($_SERVER["PHP_SELF"]);?>">  
  <div class=form>
  <span>Name</span>
  <br>
  <input type="text" name="name">
  <span class="error">* <?php echo $nameErr;?></span>
  <br>
  <span>E-mali</span>
  <br>
  <input type="text" name="email">
  <span class="error">* <?php echo $emailErr;?></span>
  <br>
  <span>Website</span>
  <br>
  <input type="text" name="website">
  <span class="error"><?php echo $websiteErr;?></span>
  <br>
  <span>Mobile no.</span>
  <br>
  <input type="mobileno" name="mobileno">
  <span class="error"><?php echo $mobilenoErr;?></span>
  <br>
  Gender:
  <input type="radio" name="gender" value="female">Female
  <input type="radio" name="gender" value="male">Male
  <input type="radio" name="gender" value="other">Other
  <span class="error">* <?php echo $genderErr;?></span>
  <br></div>
  <div class="button">
  <button type="submit" name="submit">Signup</button> 
  <button type="submit" name="submit">Reset</button> 
</div> 
</form>

<?php
echo "<h2>Your Input:</h2>";
$_session["Signup"] = "Signup succesful";
$_SESSION["name"] = $_POST['name'];
$_SESSION["email"] = $_POST['email'];

 if (!isset($_SESSION["Signup"])) {
     echo "<br>";
    //  if ($_POST() == empty($_POST())) {
    //      echo "Please Fill the required field";
        echo $_session['Signup'];
        echo "<br>";
        echo "Name : " . $_POST['name'];
        echo "<br>";
        echo "E-mail : " . $_POST['email'];
        echo "<br>";
        echo $website;
        echo "<br>";
        echo $mobileno;
        echo "<br>";
        echo "Gender : " . $_POST['gender'];
        echo "<br>";
        echo "<a href='http://localhost/website/gadget.php'><button type='button' name='confirm'>Confirm</button></a>";

}
?>

</body>
</html>