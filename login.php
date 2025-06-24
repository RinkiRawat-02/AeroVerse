 <?php
session_start();
include("db.php");
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['form_type'])) {
    $form_type = $_POST['form_type'];
    
    if ($form_type === "register") {
        $username = $_POST['uname'];
        $gmail = $_POST['mail'];
        $password = $_POST['pass'];

        if (!empty($gmail) && !empty($password) && !is_numeric($gmail)) {
            $query = "INSERT INTO reg (uname, mail, pass) VALUES ('$username', '$gmail', '$password')";
            mysqli_query($con, $query);
            echo "<script>alert('Successfully Registered');</script>";
            $_SESSION['uname'] = $username;
            echo "Session uname set as: " . $_SESSION['uname'];
        } else {
            echo "<script>alert('Please enter some valid information');</script>";
        }
        
    }
    if ($form_type === "login") {
        $gmail = $_POST['mail'];
        $password = $_POST['pass'];
        if (!empty($gmail) && !empty($password) && !is_numeric($gmail)) {
            $query = "SELECT * FROM reg WHERE mail = '$gmail' LIMIT 1";
            $result = mysqli_query($con, $query);

            if ($result && mysqli_num_rows($result) > 0) {
                $user_data = mysqli_fetch_assoc($result);

                if ($user_data['pass'] == $password) {
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['uname'] = $user_data['uname'];
                    header("Location: welcome.html");
                    exit;
                }
            }
            echo "<script>alert('Wrong email or password');</script>";
        } else {
            echo "<script>alert('Wrong email or password');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="container">
        <!-- Login Form -->
        <div class="form-box login">
            <form method="POST">
                <input type="hidden" name="form_type" value="login">
                <h1>Login</h1>
                <div class="input-box">
                    <label>Email</label>
                    <input type="email" name="mail" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <label>Password</label>
                    <input type="password" name="pass" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="forgot-link">
                    <a href="#">Forgot Password?</a>
                </div>
                <button type="submit" class="btn">Login</button>
                <p>or login with social platforms</p>
                <div class="social-icons">
                    <a href="#"><i class='bx bxl-google'></i></a>
                    <a href="#"><i class='bx bxl-facebook'></i></a>
                    <a href="#"><i class='bx bxl-github'></i></a>
                    <a href="#"><i class='bx bxl-linkedin'></i></a>
                </div>
            </form>
        </div>

        <!-- Registration Form -->
        <div class="form-box register">
            <form method="POST">
                <input type="hidden" name="form_type" value="register">
                <h1>Registration</h1>
                <div class="input-box">
                    <label>Username</label>
                    <input type="text" name="uname" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <label>Email</label>
                    <input type="email" name="mail" required>
                    <i class='bx bxs-envelope'></i>
                </div>
                <div class="input-box">
                    <label>Password</label>
                    <input type="password" name="pass" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <button type="submit" class="btn">Register</button>
                <p>or register with social platforms</p>
                <div class="social-icons">
                    <a href="#"><i class='bx bxl-google'></i></a>
                    <a href="#"><i class='bx bxl-facebook'></i></a>
                    <a href="#"><i class='bx bxl-github'></i></a>
                    <a href="#"><i class='bx bxl-linkedin'></i></a>
                </div>
            </form>
        </div>

        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1>Hello, Welcome</h1>
                <p>Don't have an account?</p>
                <button class="btn register-btn">Register</button>
            </div>

            <div class="toggle-panel toggle-right">
                <h1>Welcome back!</h1>
                <p>Already have an account?</p>
                <button class="btn login-btn">Login</button>
            </div>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>
