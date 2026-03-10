<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguageTag(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - Student Record System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .maintenance-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }
        .maintenance-icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 20px;
        }
        .maintenance-title {
            color: #333;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .maintenance-message {
            color: #666;
            margin-bottom: 30px;
        }
        .maintenance-contact {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1 class="maintenance-title">System Under Maintenance</h1>
        <p class="maintenance-message">
            The Student Record System is currently undergoing scheduled maintenance. 
            We apologize for any inconvenience and appreciate your patience.
        </p>
        
        <div class="maintenance-contact">
            <h5>Need Assistance?</h5>
            <p class="mb-0">
                <strong>Email:</strong> admin@school.edu<br>
                <strong>System Administrator:</strong> Available during business hours
            </p>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                We'll be back shortly. Thank you for your understanding.
            </small>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
