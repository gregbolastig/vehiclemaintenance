<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>New Route</title>
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

    .section {
      background: #f2f2f2;
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 10px;
    }
    .section i {
        color: #299149;
    }

    .section-title {
      text-align: center;
      font-weight: 600;
      color: #2f9e44;
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
      border: 1px solid #299149;
      border-left: 3px solid #299149;
      border-radius: 6px;
      font-size: 14px;
      color: #333;
      font-family: 'Inter', sans-serif;
      background: #fff;
      box-sizing: border-box;
    }

    .form-group input:focus {
      outline: none;
      border-color: #2f9e44;
    }

    .form-group input:disabled {
      background: #f8f9fa;
      color: #999;
    }

    .form-group input::placeholder {
      color: #999;
      font-style: italic;
    }

    .checklist-container {
        border-radius: 6px;
        border: 1px solid #299149;
        border-left: 3px solid #299149;
      margin-bottom: 15px;
    }

    .checklist-title {
        text-align: center;
      font-weight: 300;
      color: #333;
      margin-bottom: 10px;
      font-size: 14px;
    }

    .checklist {
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 10px;
      background: #fff;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
    }

    .checkbox-item:last-child {
      margin-bottom: 0;
    }

    .checkbox-item input[type="checkbox"] {
      width: 18px;
      height: 18px;
      margin-right: 10px;
      cursor: pointer;
    }

    .checkbox-item label {
      font-size: 14px;
      color: #333;
      cursor: pointer;
      flex: 1;
      margin: 0;
      font-weight: 400;
    }

    .submit-btn {
      width: 100%;
      padding: 12px;
      background-color: #2f9e44;
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
      background-color: #268a3c;
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
      background-color: white;
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
      color: #ccc;
      padding: 15px 0;
      border-top: 1px solid rgba(255, 255, 255, 0.2);
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
      
      .sidebar {
        height: 100vh;
        height: 100dvh; /* Dynamic viewport height for mobile */
        overflow-y: auto;
      }
      
      .sidebar-footer {
        font-size: 11px;
        padding: 10px 0;
      }
    }

    /* Additional mobile-specific adjustments */
    @media (max-height: 600px) {
      .sidebar-logo {
        margin-bottom: 15px;
      }
      
      .sidebar-logo img {
        width: 80px;
      }
      
      .sidebar a {
        padding: 8px 0;
        font-size: 15px;
      }
      
      .sidebar-footer {
        font-size: 10px;
        padding: 8px 0;
      }
    }
  </style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="img/logo1.png" alt="Company Logo">
  </div>
  
  <div class="sidebar-content">
    <div class="sidebar-nav">
      <a href="driver">Home</a>
      <a href="new_route">New Route</a>
      <a href="#">History</a>
      <a href="#">Report Problem</a>
      <br><br>
      <a href="#">Logout</a>
    </div>
    
    <div class="sidebar-footer">
      All rights reserved © <span id="current-year"></span>
    </div>
  </div>
</div>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

  <div class="container">
    <div class="header">
      <i class="fas fa-bars" onclick="toggleSidebar()"></i>
      NEW ROUTE
    </div>

    <div class="profile-card">
      <div class="profile-circle">CB</div>
      <div class="profile-name">Christian Greg Bolastig</div>
      <div class="profile-info">Employee ID: 2025-0703</div>
      <div class="profile-info">Email: gregbolastig@gmail.com</div>
    </div>  
<br>
    <div class="section">
        <i class="fas fa-arrow-left" onclick="window.location.href='driver'"></i>
      <div class="section-title">Vehicle Details</div>

      <div class="form-group">
        <label>Model</label>
        <input type="text" placeholder="// ex. Toyota HI-ACE" />
      </div>
      
      <div class="form-group">
        <label>Plate no.</label>
        <input type="text" placeholder="// follow this format: ABC-6556" />
      </div>
      
      <div class="form-group">
        <label>Start Odometer Reading (km)</label>
        <input type="text" placeholder="// follow this format: 10293" />
      </div>
      
      <div class="form-group">
        <label>Time and Date (automatic)</label>
        <input type="text" placeholder="// Automatic (uneditable)" disabled />
      </div>
<br>
<div class="checklist-title">Vehicle Inspection Checklist</div>
      <div class="checklist-container">
        
        <div class="checklist">
          <div class="checkbox-item">
            <input type="checkbox" id="exterior" />
            <label for="exterior">Exterior Condition</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="tires" />
            <label for="tires">Tires & Pressure</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="brake" />
            <label for="brake">Brake System</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="mirrors" />
            <label for="mirrors">Mirrors & Windows</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="lights" />
            <label for="lights">Lights & Signals</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="fluid" />
            <label for="fluid">Fluid Levels</label>
          </div>
        </div>
      </div>

      <button class="submit-btn">SUBMIT</button>
    </div><br><br><br><br>
  </div>

  <script>
    // Auto-populate current date and time
    const timeInput = document.querySelector('input[disabled]');
    const now = new Date();
    const formattedDateTime = now.toLocaleString('en-US', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });
    timeInput.value = formattedDateTime;

    // Handle form submission
    document.querySelector('.submit-btn').addEventListener('click', function() {
      const checkboxes = document.querySelectorAll('input[type="checkbox"]');
      const checkedItems = Array.from(checkboxes).filter(cb => cb.checked);
      
      if (checkedItems.length === 0) {
        alert('Please complete the vehicle inspection checklist before submitting.');
        return;
      }
      
      // Here you would typically send the data to your server
      console.log('Form submitted with', checkedItems.length, 'inspection items checked');
      alert('Route details submitted successfully!');
    });
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