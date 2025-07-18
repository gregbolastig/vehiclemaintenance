<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Report Problem</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      zoom: 1.2;
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
      color: #a8432f;
      padding: 10px 0;
      position: relative;
      font-size: 16px;
    }

    .header i {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #333;
      cursor: pointer;
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

    .section {
      background: #f2f2f2;
      border-radius: 10px;
      padding: 20px;

      margin-bottom: 10px;
    }

    .section i {
      color: #e67e22;
      cursor: pointer;
      margin-bottom: 10px;
    }

    .section-title {
      text-align: center;
      font-weight: 600;
      color: #e67e22;
      margin-top: -10px;
      margin-bottom: 10px;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-size: 14px;
      color: #333;
      font-weight: 300;
    }

    .form-group input {
      width: 100%;
      padding: 10px;
         border: 1px solid #e67e22;
        border-left: 3px solid #e67e22;
      border-radius: 6px;
      font-size: 14px;
      color: #333;
      font-family: 'Inter', sans-serif;
      background: #fff;
      box-sizing: border-box;
    }

    .form-group input:focus {
      outline: none;
      border-color: #d35400;
    }

    .form-group input::placeholder {
      color: #bbb;
      font-style: italic;
    }

    .form-group textarea {
      width: 100%;
      padding: 12px;
               border: 1px solid #e67e22;
        border-left: 3px solid #e67e22;
      border-radius: 6px;
      font-size: 14px;
      color: #333;
      font-family: 'Inter', sans-serif;
      background: #fff;
      box-sizing: border-box;
      resize: vertical;
      min-height: 100px;
    }

    .form-group textarea:focus {
      outline: none;
      border-color: #d35400;
    }

    .form-group textarea::placeholder {
      color: #bbb;
      font-style: italic;
    }

    .submit-btn {
      width: 100%;
      padding: 12px;
      background-color: #e67e22;
      color: white;
      border: none;
      font-weight: 600;
      border-radius: 6px;
      font-size: 14px;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      margin-top: 10px;
    }

    .submit-btn:hover {
      background-color: #d35400;
      transform: scale(0.98);
    }

    .submit-btn:active {
      transform: scale(0.95);
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: -250px;
      width: 220px;
      height: 100%;
      background-color: white;
      color: #2f9e44;
      padding: 20px 15px;
      transition: left 0.3s ease-in-out;
      z-index: 1000;
      box-shadow: 2px 0 5px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }

    .sidebar-logo {
      text-align: center;
      margin-bottom: 20px;
      flex-shrink: 0;
    }

    .sidebar-logo img {
      width: 100px;
      max-width: 80%;
      height: auto;
      border-radius: 8px;
    }

    .sidebar-content {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .sidebar-nav {
      flex: 1;
    }

    .sidebar-footer {
      flex-shrink: 0;
      margin-top: auto;
      text-align: center;
      font-size: 12px;
      color: rgba(30, 30, 30, 0.64);
      padding: 100px 0;
      border-top: 1px solid rgba(63, 61, 61, 0.2);
    }

    .sidebar.active {
      left: 0;
    }

    .sidebar a {
      display: block;
      color: #2f9e44;
      padding: 10px 0;
      text-decoration: none;
      font-weight: 500;
      font-size: 13px;
    }

    .sidebar a:hover {
      background-color: rgba(180, 185, 181, 0.28);
      color: #299149;
    }

    .sidebar a i {
      margin-right: 10px;
      width: 16px;
      text-align: center;
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

    #bars:hover {
      transform: translateY(-10px);
    }

    @media (max-width: 768px) {
      body {
        background-color: #fff;
      }
      
      .container {
        width: 360px;
        background-color: #fff;
        padding: 5px 5px 5px 5px;
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
  <a href="driver"><i class="fas fa-home"></i> Home</a>
  <a href="new_route"><i class="fas fa-plus"></i> New Route</a>
  <a href="driver_history"><i class="fas fa-history"></i> History</a>
  <a href="report_vehicle_issue"><i class="fas fa-exclamation-triangle"></i> Report Vehicle Issue</a>
  <a href="index"><i class="fas fa-sign-out-alt"></i> Logout</a>
  <br><br>
  <a href="#"><i class="fas fa-info-circle"></i> Report System Issue</a>

  <div class="sidebar-footer">
    All rights reserved © <span id="current-year"></span>
  </div>
</div>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<div class="container">
  <div class="header">
    <i class="fas fa-bars" id="bars" onclick="toggleSidebar()"></i>
    REPORT PROBLEM
  </div>

  <div class="profile-card">
      <div class="profile-circle">CB</div>
      <div class="profile-name">Christian Greg Bolastig</div>
      <div class="profile-info">Employee ID: 2025-0703</div>
      <div class="profile-info">Email: gregbolastig@gmail.com</div>
  </div>

  <br>

  <div class="section">
    <i class="fas fa-arrow-left" onclick="history.back(); return false;"></i>
    <div class="section-title">Report Details</div>

    <div class="form-group">
      <label>Problem Name</label>
      <input type="text" placeholder="// Short title" />
    </div>

    <div class="form-group">
      <label>Description</label>
      <textarea placeholder="// Enter the problem description"></textarea>
    </div>
    <div class="form-group">
      <label>Plate no.</label>
      <input type="text" placeholder="// ex. ABG6556 (no spacing)" />
    </div>
    <div class="form-group">
      <label>Model</label>
      <input type="text" placeholder="// ex. Toyota HI-ACE" />
    </div>

    <div class="form-group">
      <label>Body no.</label>
      <input type="text" placeholder="// follow this format: ABC-6556" />
    </div>

    <div class="form-group">
      <label>Odometer Reading (km)</label>
      <input type="text" placeholder="// follow this format: 10293" />
    </div>

    <button class="submit-btn">SUBMIT</button>
  </div>

  <br><br>
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

  // Handle form submission
  document.querySelector('.submit-btn').addEventListener('click', function() {
    const inputs = document.querySelectorAll('input[type="text"], textarea');
    const emptyFields = Array.from(inputs).filter(input => !input.value.trim());
    
    if (emptyFields.length > 0) {
      alert('Please fill in all required fields before submitting.');
      return;
    }
    
    // Here you would typically send the data to your server
    console.log('Problem report submitted');
    alert('Problem report submitted successfully!');
  });
</script>
</body>
</html>