<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Signup</title>
  <link rel="stylesheet" href="/MVC/views/public/assets/app.css">
  <style>
    .choose {
      display: flex;
      justify-content: center;
      gap: 40px;
      margin-top: 40px;
      flex-wrap: wrap;
    }
    .choose a {
      display: block;
      width: 240px;
      text-align: center;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 20px;
      text-decoration: none;
      color: #1e293b;
      transition: transform .2s, box-shadow .2s;
    }
    .choose a:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    }
    .choose img {
      width: 200px;
      height: 200px;
      object-fit: contain;
      margin-bottom: 12px;
    }
    .choose strong {
      font-size: 18px;
      color: #0f172a;
    }
  </style>
</head>
<body class="container">
  <div class="card" style="text-align:center; margin-top:40px;">
    <h2>Choose Account Type</h2>
    <div class="choose">
      <a href="signup_student.php">
        <img src="/MVC/views/public/assets/student.jpg" alt="Student">
        <strong>Student</strong>
      </a>
      <a href="signup_faculty.php">
        <img src="/MVC/views/public/assets/faculty.jpg" alt="Faculty">
        <strong>Faculty</strong>
      </a>
    </div>
    <div style="margin-top:20px">
      <a href="/MVC/views/public/index.php" class="btn secondary">Back to Login</a>
    </div>
  </div>
</body>
</html>
