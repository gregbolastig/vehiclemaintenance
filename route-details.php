<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            --surface-color: #ffffff;
            --background-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --text-light: #a0aec0;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background-gradient);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
            font-weight: 400;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .route-details-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .back-btn {
            background: var(--text-secondary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            cursor: pointer;
            margin-bottom: 32px;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
        }

        .back-btn:hover {
            background: var(--text-primary);
            transform: translateX(-4px);
        }

        .card {
            background: var(--surface-color);
            border-radius: var(--radius-xl);
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .route-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 24px;
        }

        .route-title {
            font-size: 1.875rem;
            color: var(--text-primary);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .route-meta {
            display: flex;
            gap: 24px;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .route-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .route-meta i {
            font-size: 0.75rem;
            color: var(--text-light);
        }

        .section-title {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .vehicle-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .info-item {
            background: #f8fafc;
            padding: 16px;
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary-gradient);
        }

        .info-item h4 {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-item p {
            color: var(--text-primary);
            font-size: 1.125rem;
            font-weight: 600;
        }

        .checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .checklist-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #f8fafc;
            border-radius: var(--radius-md);
        }

        .checklist-item i {
            color: var(--success-gradient);
            font-size: 1.125rem;
        }

        .checklist-item.completed {
            background: rgba(79, 172, 254, 0.1);
        }

        .checklist-item.not-completed {
            background: rgba(255, 107, 107, 0.1);
        }

        .checklist-item.not-completed i {
            color: var(--danger-gradient);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: var(--text-light);
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--surface-color);
            font-family: 'Inter', sans-serif;
            font-weight: 500;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .submit-btn {
            background: var(--success-gradient);
            color: white;
            border: none;
            padding: 20px 40px;
            font-size: 1.125rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            width: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-family: 'Inter', sans-serif;
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(79, 172, 254, 0.4);
        }

        .submit-btn i {
            font-size: 1.125rem;
        }

        @media (max-width: 768px) {
            .route-details-container {
                padding: 24px 16px;
            }

            .route-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .route-meta {
                flex-direction: column;
                gap: 8px;
            }

            .vehicle-info-grid {
                grid-template-columns: 1fr;
            }

            .checklist-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="route-details-container">
        <button class="back-btn" onclick="goBack()">
            <i class="fas fa-arrow-left"></i>
            Back to History
        </button>

        <div class="card">
            <div class="route-header">
                <h1 class="route-title">
                    <i class="fas fa-route"></i>
                    Route Details
                </h1>
                <div class="route-meta">
                    <span id="route-date"><i class="fas fa-calendar"></i> June 15, 2023</span>
                    <span id="route-time"><i class="fas fa-clock"></i> 08:30 AM</span>
                </div>
            </div>

            <div class="section-title">
                <i class="fas fa-car"></i>
                Vehicle Information
            </div>

            <div class="vehicle-info-grid">
                <div class="info-item">
                    <h4>Vehicle Model</h4>
                    <p id="vehicle-model">Toyota Hiace</p>
                </div>
                <div class="info-item">
                    <h4>Plate Number</h4>
                    <p id="plate-number">ABC-1234</p>
                </div>
                <div class="info-item">
                    <h4>Initial Odometer</h4>
                    <p id="initial-odometer">45,231 km</p>
                </div>
            </div>

            <div class="section-title">
                <i class="fas fa-clipboard-check"></i>
                Pre-Trip Inspection
            </div>

            <div class="checklist-grid" id="inspection-checklist">
                <!-- Checklist items will be inserted here by JavaScript -->
            </div>

            <div class="section-title">
                <i class="fas fa-exclamation-triangle"></i>
                Reported Issues
            </div>

            <div class="card" id="reported-issues">
                <p>No issues reported during pre-trip inspection.</p>
            </div>

            <div class="section-title">
                <i class="fas fa-flag-checkered"></i>
                Complete Route
            </div>

            <form id="complete-route-form">
                <div class="form-group">
                    <label for="final-odometer">
                        <i class="fas fa-tachometer-alt"></i>
                        Final Odometer Reading (km)
                    </label>
                    <input type="number" id="final-odometer" placeholder="Enter final odometer reading" required>
                </div>

                <div class="form-group">
                    <label for="post-problems">
                        <i class="fas fa-exclamation-triangle"></i>
                        Post-Trip Issues (if any)
                    </label>
                    <textarea id="post-problems" rows="4" placeholder="Describe any new problems or issues found after the trip..."></textarea>
                </div>

                <div class="section-title">
                    <i class="fas fa-clipboard-check"></i>
                    Post-Trip Inspection
                </div>

                <div class="checklist-grid">
                    <div class="checklist-item">
                        <input type="checkbox" id="post-exterior" name="post-inspection">
                        <label for="post-exterior">
                            <i class="fas fa-car-side"></i>
                            Exterior Condition
                        </label>
                    </div>
                    <div class="checklist-item">
                        <input type="checkbox" id="post-lights" name="post-inspection">
                        <label for="post-lights">
                            <i class="fas fa-lightbulb"></i>
                            Lights & Signals
                        </label>
                    </div>
                    <div class="checklist-item">
                        <input type="checkbox" id="post-tires" name="post-inspection">
                        <label for="post-tires">
                            <i class="fas fa-circle"></i>
                            Tires & Pressure
                        </label>
                    </div>
                    <div class="checklist-item">
                        <input type="checkbox" id="post-fluids" name="post-inspection">
                        <label for="post-fluids">
                            <i class="fas fa-tint"></i>
                            Fluid Levels
                        </label>
                    </div>
                    <div class="checklist-item">
                        <input type="checkbox" id="post-brakes" name="post-inspection">
                        <label for="post-brakes">
                            <i class="fas fa-stop-circle"></i>
                            Brake System
                        </label>
                    </div>
                    <div class="checklist-item">
                        <input type="checkbox" id="post-engine" name="post-inspection">
                        <label for="post-engine">
                            <i class="fas fa-cog"></i>
                            Engine Condition
                        </label>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-check-circle"></i>
                    Complete Route
                </button>
            </form>
        </div>
    </div>

    <script>
        // Get route ID from URL parameters
        function getRouteIdFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('id');
        }

        // Load route details
        function loadRouteDetails() {
            const routeId = getRouteIdFromUrl();
            // In a real application, you would fetch this data from your backend
            // For this example, we'll use the routeHistory from the dashboard

            if (!routeId || routeId >= routeHistory.length) {
                alert('Invalid route ID');
                goBack();
                return;
            }

            const route = routeHistory[routeId];

            // Set basic information
            document.getElementById('vehicle-model').textContent = route.vehicleModel;
            document.getElementById('plate-number').textContent = route.plateNumber;
            document.getElementById('initial-odometer').textContent = route.odometer + ' km';

            // Format date and time
            const date = new Date(route.timestamp);
            const formattedDate = date.toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const formattedTime = date.toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

            document.getElementById('route-date').textContent = formattedDate;
            document.getElementById('route-time').textContent = formattedTime;

            // Set inspection checklist
            const checklistContainer = document.getElementById('inspection-checklist');
            checklistContainer.innerHTML = '';

            // Standard checklist items
            const standardItems = [
                { id: 'exterior', label: 'Exterior Condition', icon: 'fa-car-side' },
                { id: 'lights', label: 'Lights & Signals', icon: 'fa-lightbulb' },
                { id: 'tires', label: 'Tires & Pressure', icon: 'fa-circle' },
                { id: 'fluids', label: 'Fluid Levels', icon: 'fa-tint' },
                { id: 'brakes', label: 'Brake System', icon: 'fa-stop-circle' },
                { id: 'engine', label: 'Engine Condition', icon: 'fa-cog' },
                { id: 'mirrors', label: 'Mirrors & Windows', icon: 'fa-eye' },
                { id: 'safety', label: 'Safety Equipment', icon: 'fa-shield-alt' }
            ];

            standardItems.forEach(item => {
                const isCompleted = route.inspectionItems && route.inspectionItems.includes(item.label);
                const itemElement = document.createElement('div');
                itemElement.className = `checklist-item ${isCompleted ? 'completed' : 'not-completed'}`;
                itemElement.innerHTML = `
                    <i class="fas ${isCompleted ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                    <div>
                        <i class="fas ${item.icon}"></i>
                        ${item.label}
                    </div>
                `;
                checklistContainer.appendChild(itemElement);
            });

            // Set reported issues
            const issuesContainer = document.getElementById('reported-issues');
            if (route.problems && route.problems.trim() !== '') {
                issuesContainer.innerHTML = `
                    <p><strong>Issues reported during pre-trip inspection:</strong></p>
                    <p>${route.problems}</p>
                `;
            }
        }

        // Handle form submission for completing the route
        document.getElementById('complete-route-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const routeId = getRouteIdFromUrl();
            if (!routeId || routeId >= routeHistory.length) {
                alert('Invalid route ID');
                goBack();
                return;
            }

            // Get form data
            const finalOdometer = document.getElementById('final-odometer').value;
            const postProblems = document.getElementById('post-problems').value;
            const postInspectionItems = [];

            // Get checked post-inspection items
            const checkboxes = document.querySelectorAll('input[name="post-inspection"]:checked');
            checkboxes.forEach(checkbox => {
                postInspectionItems.push(checkbox.nextElementSibling.textContent.trim());
            });

            // Update route history
            routeHistory[routeId].finalOdometer = finalOdometer;
            routeHistory[routeId].postProblems = postProblems;
            routeHistory[routeId].postInspectionItems = postInspectionItems;
            routeHistory[routeId].completed = true;
            routeHistory[routeId].completionTimestamp = new Date().toISOString();

            // Show success message
            alert('Route completed successfully!');

            // Go back to history
            goBack();
        });

        // Go back to history
        function goBack() {
            window.location.href = 'dashboard.html'; // Change this to your actual dashboard page
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // In a real application, you would load the routeHistory from your backend
            // For this example, we'll assume it's available globally
            if (typeof routeHistory === 'undefined') {
                alert('Route history not available');
                goBack();
                return;
            }

            loadRouteDetails();
        });
    </script>
</body>
</html>
