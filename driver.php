<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Driver Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f5f5f5;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 0;
    }

    .container {
      width: 360px;
      background-color: #fff;
      padding: 10px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
      min-height: 100vh;
    }

    .header {
      text-align: center;
      font-weight: bold;
      color: #2f9e44;
      padding: 10px 0;
      position: relative;
    }

    .header i {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #333;
    }

    .profile-card {
      background: #f5f5f5;
      border-radius: 16px;
      padding: 20px;
      text-align: center;
      margin-top: 10px;
    }

    .profile-circle {
      width: 60px;
      height: 60px;
      background: #ccc;
      border-radius: 50%;
      margin: 0 auto 10px;
      font-weight: 600;
      font-size: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #333;
    }

    .profile-name {
      font-weight: 600;
      font-size: 18px;
      margin-bottom: 4px;
    }

    .profile-info {
      font-size: 13px;
      color: #666;
      font-weight: 400;
    }

    .buttons {
      display: flex;
      gap: 10px;
      margin: 15px 0;
      justify-content: center;
    }

    .btn {
      flex: 1;
      padding: 10px;
      border-radius: 6px;
      font-weight: 600;
      color: #fff;
      border: none;
      cursor: pointer;
      font-family: 'Inter', sans-serif;
    }

    .btn.new-route {
      background-color: #2f9e44;
    }

    .btn.report-problem {
      background-color: #a8432f;
    }

    .section {
      background: #f2f2f2;
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 10px;
    }

    .section-title {
      text-align: center;
      font-weight: 600;
      color: #2f9e44;
      margin-bottom: 10px;
    }

    .card {
      background-color: #ffa726;
      color: #000;
      padding: 10px;
      border-radius: 10px;
      margin-bottom: 5px;
    }

    .card.green {
      background-color: #8ef58e;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      font-weight: 600;
    }

    .card-details {
      font-size: 13px;
      margin-top: 6px;
      font-weight: 400;
    }

    .completed {
      color: green;
      font-weight: 600;
      float: right;
      font-size: 12px;
    }

    hr {
      border: none;
      border-top: 1px solid #ddd;
      margin: 20px 0;
    }
    .sidebar {
  position: fixed;
  top: 0;
  left: -250px;
  width: 220px;
  height: 100%;
  background-color: #2f9e44;
  color: #fff;
  padding: 20px 15px;
  transition: left 0.3s ease-in-out;
  z-index: 1000;
  box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}

.sidebar-footer {
  position: absolute;
  bottom: 15px;
  left: 0;
  width: 100%;
  text-align: center;
  font-size: 12px;
  color: #ccc;
  padding: 10px 0;
}

.sidebar.active {
  left: 0;
}

.sidebar a {
  display: block;
  color: #fff;
  padding: 10px 0;
  text-decoration: none;
  font-weight: 500;
  font-size: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}
.sidebar-logo {
  text-align: center;
  
  margin-bottom: 20px;
}

.sidebar-logo img {
  width: 100px; /* Adjust for your design */
  max-width: 80%;
  background-color: white;
  height: auto;
  border-radius: 8px; /* Optional for rounded style */
}


.sidebar a:hover {
  background-color: #276c34;
}

.overlay {
  position: fixed;
  top: 0;
  left: 0;
  height: 100%;
  width: 100%;
  background: rgba(0,0,0,0.4);
  z-index: 999;
  display: none;
}

.overlay.active {
  display: block;
}

     @media (max-width: 768px) {
        body {
            background-color: #fff;
            
        }
    .container {
      width: 360px;
      background-color: #fff;
      padding: 25px 5px 5px 5px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0);
      min-height: 100vh;
    }
        }
  </style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="img/logo1.png" alt="Company Logo">
  </div>
  <a href="driver">Home</a>
  <a href="new_route">New Route</a>
  <a href="#">History</a>
  <a href="#">Report Problem</a>
  <br><br>
  <a href="#">Logout</a>

  <div class="sidebar-footer">
    All rights reserved © <span id="current-year"></span>
  </div>
</div>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>


  <div class="container">
    <div class="header">
      <i class="fas fa-bars" onclick="toggleSidebar()"></i>

      DRIVER DASHBOARD
    </div>

    <div class="profile-card">
      <div class="profile-circle">CB</div>
      <div class="profile-name">Christian Greg Bolastig</div>
      <div class="profile-info">Employee ID: 2025-0703</div>
      <div class="profile-info">Email: gregbolastig@gmail.com</div>
    </div>

    <div class="buttons">
      <button class="btn new-route" onclick="window.location.href='new_route'"><i class="fas fa-plus"></i> New Route</button>
      <button class="btn report-problem"><i class="fas fa-exclamation-triangle"></i> Report Problem</button>
    </div>

    <div class="section">
      <div class="section-title">IN PROGRESS</div>
      <div class="card">
        <div class="card-header">
          <span>Toyota Hiace</span>
          <span>Time: 6:45 AM</span>
        </div>
        <div class="card-details">
          Plate Number: ABG-6556<br />
          Date: 07/07/2025<br />
          Start odometer: 20172<br />
          End odometer: -
        </div>
      </div>
    </div>

    <hr />

    <div class="section">
      <div class="section-title">ROUTE HISTORY</div>
      <div class="card green">
        <div class="card-header">
          <span>Toyota Hiace</span>
          <span>Time: 5:47 AM</span>
        </div>
        <div class="card-details">
          <span class="completed">Completed</span>
          Plate Number: ABG-6556<br />
          Date: 07/05/2025<br />
          Start odometer: 20022<br />
          End odometer: 20169
        </div>
      </div>

      <div class="card green">
        <div class="card-header">
          <span>Toyota Ace</span>
          <span>Time: 6:45 AM</span>
        </div>
        <div class="card-details">
          <span class="completed">Completed</span>
          Plate Number: FBY-6556<br />
          Date: 07/07/2025<br />
          Start odometer: 19172<br />
          End odometer: 19306
        </div>
      </div>
    </div>
  </div>
  <script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('current-year').textContent = new Date().getFullYear();
  });
</script>

</body>
</html>