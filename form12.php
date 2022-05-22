<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>
		Build a Survey Form using HTML and CSS
	</title>
      <style>
		body {background-image:url(https://cdn1.byjus.com/byjusweb/img/complaints-resolution/TopBanner_Complaints_Resolution_5120x1539.jpg); 
background-repeat:no-repeat; 
background-attachment:Fixed; 
background-size:cover; 
background-clip:border-box; 
			font-family: Times New Roman;
			text-align: center;
} 
		
		form {
			background-color: white;
			max-width: 500px;
			margin: 50px auto;
			padding: 30px 20px;
			box-shadow: 2px 5px 10px rgba(0, 0, 0, 0.5);
		}
		.form-control {
			text-align: left;
			margin-bottom: 25px;
		}
		.form-control label {
			display: block;
			margin-bottom: 10px;
		}
		.form-control input,
		.form-control select,
		.form-control textarea {
			border: 1px solid #777;
			border-radius: 2px;
			font-family: inherit;
			padding: 10px;
			display: block;
			width: 95%;
		}
		.form-control input[type="radio"],
		.form-control input[type="checkbox"] {
			display: inline-block;
			width: auto;
		}
		button {
			background-color: #05c46b;
			border: 1px solid #777;
			border-radius: 2px;
			font-family: inherit;
			font-size: 21px;
			display: block;
			width: 100%;
			margin-top: 50px;
			margin-bottom: 20px;
		}{
  margin: 0;
}
</style>
	
</head>
<body>
	<h1>BYJU'S LEARNING PROGRAMME</h1>
	<form id="form" >
		<div class="form-control">
			<label for="name" id="label-name">
				Name
			</label>
			<input type="text"
				id="name"
				placeholder="Enter your name" />
            </div>
            <div class="form-control">
			<label for="email" id="label-email">
				Email
			</label>
			<input type="email"
				id="email"
				placeholder="Enter your email" />
		</div>
            <div class="form-control">
			<label for="age" id="label-age">
				Age
			</label>
			<input type="text"
				id="age"
				placeholder="Enter your age" />
		</div>
              <div class="form-control">
			<label for="role" id="label-role">
				Gender
			</label>
			<select name="role" id="role">
				<option value="Male">Male</option>
				<option value="Female">Female</option>
				<option value="other">Other</option>
			</select>
		</div>
            <div class="form-control">
			<label for="role" id="label-role">
				City
			</label>
			<select name="role" id="role">
				<option value="Delhi">Delhi</option>
				<option value="Punjab">Punjab</option>
				<option value="Haryana">Haryana</option>
				<option value="Uttar Pradesh">Other</option>
                        <option value="Other">Other</option>
			</select>
		</div>
            <div class="form-control">
			<label for="role" id="label-role">
				Grade/Exam
			</label>
			<select name="role" id="role">
				<option value="class 1">class 1</option>
				<option value="class 2">class 2</option>
				<option value="class 3">class 3</option>
				<option value="IAS">IAS</option>
                        <option value="CAT">CAT</option>
                        <option value="Bank Exam">Bank Exam</option>
                        <option value="Other">Other</option>
			</select>
		</div>
            <div class="form-control">
			<label>
				HOw did you come to know about BYJU'S
			</label>
			<label for="recommed-1">
				<input type="radio"
					id="recommed-1"
					name="recommed">Advertisment</input>
			</label>
			<label for="recommed-2">
				<input type="radio"
					id="recommed-2"
					name="recommed">Friends</input>
			</label>
			<label for="recommed-3">
				<input type="radio"
					id="recommed-3"
					name="recommed">Family</input>
			</label>
                  <label for="recommed-4">
				<input type="radio"
					id="recommed-4"
					name="recommed">Other</input>
			</label>
		</div>
             <div class="form-control">
			<label>
				Your expectation from BYJU'S
			</label>
			<label for="recommed-1">
				<input type="radio"
					id="recommed-1"
					name="recommed">High</input>
			</label>
			<label for="recommed-2">
				<input type="radio"
					id="recommed-2"
					name="recommed">Moderate</input>
			</label>
			<label for="recommed-3">
				<input type="radio"
					id="recommed-3"
					name="recommed">Low</input>
			</label>
		</div>
          
			<label for="comment">
				Any comments or suggestions
			</label>
<br>
<br>
			<textarea name="comment" id="comment"
				placeholder="Enter your comment here">
			</textarea>
		</div>
<br>
<br>        
		<input type="submit" value="submit">
		<br>
		<br>
		</form>
		
</body>
</html>
<!doctype html>
<?php
$cookie_name="user";
$cookie_value="google";
setcookie($cookie_name,$cookie_value,time()+(86400),"/");
?>
<html>
<body>
<?php
if(!isset($_COOKIE[$cookie_name]))
{
	echo"cookie is not set";
}
else
{
	echo $_COOKIE[$cookie_name];
}
?>
</html>
