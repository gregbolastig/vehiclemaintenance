<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - IslandStar Express VMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
    body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #E5E5E5;
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            padding: 30px;
            border: 1px solid #E0E0E0;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            margin-bottom: 20px;
        }
        
        .logo img {
            width: 120px;
            max-width: 80%;
            height: auto;
            border-radius: 8px;
        }
        
        .logo-fallback {
            display: none;
            font-size: 18px;
            font-weight: 600;
            color: #2E7D32;
            line-height: 1.2;
        }
        
        .title {
            font-size: 20px;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 10px;
        }
        
        .subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .content {
            line-height: 1.6;
            color: #333;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #E8F5E8;
        }
        
        .section-content {
            font-size: 14px;
            color: #424242;
            margin-bottom: 15px;
        }
        
        .subsection {
            margin-bottom: 15px;
        }
        
        .subsection-title {
            font-size: 14px;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 8px;
        }
        
        .list {
            margin-left: 0;
            padding-left: 0;
        }
        
        .list-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 8px;
            font-size: 14px;
            color: #424242;
        }
        
        .list-item::before {
            content: "•";
            color: #2E7D32;
            font-weight: bold;
            margin-right: 10px;
            margin-top: 2px;
        }
        
        .highlight {
            background-color: #FFF3E0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #FF9800;
            margin: 20px 0;
        }
        
        .highlight-title {
            font-size: 14px;
            font-weight: 600;
            color: #E65100;
            margin-bottom: 8px;
        }
        
        .highlight-content {
            font-size: 13px;
            color: #BF360C;
            line-height: 1.5;
        }
        
        .acceptance-section {
            background-color: #E8F5E8;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            border: 1px solid #C8E6C9;
        }
        
        .acceptance-title {
            font-size: 16px;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 10px;
        }
        
        .acceptance-content {
            font-size: 14px;
            color: #1B5E20;
            line-height: 1.5;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E0E0E0;
        }
        
        .footer-text {
            font-size: 12px;
            color: #757575;
            line-height: 1.4;
        }
        
        .back-button {
            display: inline-block;
            background: #2E7D32;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-top: 20px;
            transition: background-color 0.2s ease;
        }
        
        .back-button:hover {
            background: #1B5E20;
        }
        
        .version-info {
            font-size: 12px;
            color: #9E9E9E;
            text-align: center;
            margin-top: 10px;
        }
        
        .contact-info {
            background-color: #F5F5F5;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .contact-title {
            font-size: 14px;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 8px;
        }
        
        .contact-content {
            font-size: 13px;
            color: #424242;
            line-height: 1.5;
        }
        
        @media (max-width: 480px) {
                body {
            padding: 0px 0;
        }
        
            .container {
                padding: 25px 20px;
                border: none;
                border-radius: 0px;
            }
            
            .title {
                font-size: 18px;
            }
            
            .section-title {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="img/logo1.png" alt="IslandStar Express Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <div class="logo-fallback">
                    IslandStar<br>Express
                </div>
            </div>
            <h1 class="title">Terms and Conditions</h1>
            <p class="subtitle">IsVehicle Maintenance System (VMS)</p>
        </div>
        
        <div class="content">
            <div class="acceptance-section">
                <h3 class="acceptance-title">Agreement to Terms</h3>
                <p class="acceptance-content">By accessing and using the IslandStar Express Vehicle Maintenance System (ISE-VMS), you acknowledge that you have read, understood, and agree to be bound by these terms and conditions.</p>
            </div>
            
            <div class="section">
                <h3 class="section-title">1. System Overview</h3>
                <p class="section-content">
                    The ISE-VMS is a comprehensive vehicle management platform designed to facilitate efficient fleet operations, driver management, route tracking, and vehicle maintenance for IslandStar Express operations.
                </p>
            </div>
            
            <div class="section">
                <h3 class="section-title">2. User Responsibilities</h3>
                <div class="subsection">
                    <h4 class="subsection-title">For Drivers:</h4>
                    <div class="list">
                        <div class="list-item">Maintain accurate odometer readings and route information</div>
                        <div class="list-item">Report vehicle problems immediately through the system</div>
                        <div class="list-item">Ensure valid driver's license and timely renewals</div>
                        <div class="list-item">Complete assigned routes safely and efficiently</div>
                        <div class="list-item">Protect login credentials and account security</div>
                    </div>
                </div>
                
                <div class="subsection">
                    <h4 class="subsection-title">For Supervisors:</h4>
                    <div class="list">
                        <div class="list-item">Monitor driver performance and route compliance</div>
                        <div class="list-item">Review and respond to vehicle maintenance reports</div>
                        <div class="list-item">Ensure proper vehicle assignment and utilization</div>
                        <div class="list-item">Maintain system data accuracy and integrity</div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h3 class="section-title">3. Data Privacy & Security</h3>
                <p class="section-content">
                    ISE-VMS collects and processes personal information including driver details, contact information, license data, and operational records. This information is used solely for fleet management purposes and is protected under applicable data privacy laws.
                </p>
                
                <div class="highlight">
                    <h4 class="highlight-title">Data Protection Commitment</h4>
                    <p class="highlight-content">
                        Your personal information is encrypted, securely stored, and accessed only by authorized personnel. We do not share your data with third parties without explicit consent, except as required by law.
                    </p>
                </div>
            </div>
            
            <div class="section">
                <h3 class="section-title">4. System Usage Guidelines</h3>
                <div class="list">
                    <div class="list-item">Use the system only for authorized business purposes</div>
                    <div class="list-item">Do not share login credentials with unauthorized individuals</div>
                    <div class="list-item">Report system issues or suspicious activities immediately</div>
                    <div class="list-item">Ensure all entered data is accurate and truthful</div>
                    <div class="list-item">Log out properly after each session</div>
                </div>
            </div>
            
            <div class="section">
                <h3 class="section-title">5. Prohibited Activities</h3>
                <div class="list">
                    <div class="list-item">Unauthorized access to system data or accounts</div>
                    <div class="list-item">Manipulation or falsification of records</div>
                    <div class="list-item">Use of the system for personal or non-business purposes</div>
                    <div class="list-item">Attempts to breach system security or integrity</div>
                    <div class="list-item">Sharing sensitive operational information with competitors</div>
                </div>
            </div>
            
            <div class="section">
                <h3 class="section-title">6. Vehicle and Safety Compliance</h3>
                <p class="section-content">
                    Users are responsible for ensuring all vehicle operations comply with local traffic laws, safety regulations, and company policies. Any incidents or violations must be reported immediately through the system.
                </p>
            </div>
            
            <div class="section">
                <h3 class="section-title">7. System Availability</h3>
                <p class="section-content">
                    While we strive for 24/7 system availability, maintenance periods and technical issues may occasionally affect access. Users will be notified of planned maintenance in advance when possible.
                </p>
            </div>
            
            <div class="section">
                <h3 class="section-title">8. Disciplinary Actions</h3>
                <p class="section-content">
                    Violations of these terms may result in system access suspension, disciplinary action, or termination of employment, depending on the severity and nature of the violation.
                </p>
            </div>
            
            <div class="section">
                <h3 class="section-title">9. Terms Updates</h3>
                <p class="section-content">
                    These terms may be updated periodically. Users will be notified of significant changes and continued use of the system constitutes acceptance of updated terms.
                </p>
            </div>
            
            <div class="contact-info">
                <h4 class="contact-title">Questions or Concerns?</h4>
                <p class="contact-content">
                    For questions about these terms or the ISE-VMS system, please contact your supervisor or the IT support team.
                </p>
            </div>
        </div>
        
        <div class="footer">
            <a href="index.php" class="back-button">Back to Login</a> <br><br>
            <div class="version-info">
                Version 1.0 | Effective Date: July 2025
            </div>
            <p class="footer-text">
                © 2025 IslandStar Express. All rights reserved.<br>
                This document is confidential and proprietary.
            </p>
        </div>
    </div>
</body>
</html>