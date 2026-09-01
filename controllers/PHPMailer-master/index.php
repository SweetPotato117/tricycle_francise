<?php
// 403.php - Forbidden Access Page
http_response_code(403);
$page_title = "403 - Access Forbidden";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | EzTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --primary-blue: #00167a;
            --secondary-blue: #84a9ff;
            --accent-gold: #ffd700;
            --error-red: #ef4444;
            --dark-red: #dc2626;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated background elements */
        .error-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .forbidden-symbol {
            position: absolute;
            font-size: 40px;
            color: rgba(239, 68, 68, 0.1);
            animation: floatSymbol 20s linear infinite;
        }

        @keyframes floatSymbol {
            0% {
                transform: translateY(100vh) rotate(0deg);
            }

            100% {
                transform: translateY(-100px) rotate(360deg);
            }
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: radial-gradient(circle, var(--error-red), transparent);
            border-radius: 50%;
            animation: particleFall 8s infinite linear;
            opacity: 0.7;
        }

        @keyframes particleFall {
            0% {
                transform: translateY(-100px) translateX(0);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(100vh) translateX(100px);
                opacity: 0;
            }
        }

        /* Main content container */
        .error-container {
            max-width: 800px;
            width: 90%;
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(239, 68, 68, 0.2);
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--error-red), var(--dark-red), #f97316, var(--accent-gold));
            background-size: 300% 100%;
            animation: gradientShift 3s ease infinite;
        }

        @keyframes gradientShift {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        /* Error icon */
        .error-icon {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            border: 5px solid rgba(239, 68, 68, 0.3);
            position: relative;
            animation: pulseIcon 2s ease-in-out infinite;
        }

        @keyframes pulseIcon {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }

            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 20px rgba(239, 68, 68, 0);
            }
        }

        .error-icon i {
            font-size: 100px;
            color: var(--error-red);
            filter: drop-shadow(0 5px 15px rgba(239, 68, 68, 0.5));
        }

        /* Error code */
        .error-code {
            font-size: 8rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--error-red), var(--dark-red), #f97316);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: -10px;
            text-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
            line-height: 1;
        }

        .error-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: white;
        }

        .error-message {
            font-size: 1.2rem;
            color: #cbd5e1;
            max-width: 600px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 40px 0 30px;
        }

        .btn {
            padding: 15px 35px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), #1a3a8a);
            color: white;
            box-shadow: 0 10px 25px rgba(0, 22, 122, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1a3a8a, var(--primary-blue));
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 22, 122, 0.4);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
        }

        /* Security message */
        .security-message {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error-red);
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: left;
        }

        .security-message h4 {
            color: #fecaca;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .security-message p {
            color: #fca5a5;
            font-size: 0.95rem;
            margin: 0;
        }

        /* Logo */
        .logo-container {
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            padding: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            border: 2px solid var(--accent-gold);
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 10px;
        }

        .logo-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--secondary-blue), var(--primary-blue));
            color: white;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 14px;
        }

        /* Countdown timer */
        .countdown {
            font-size: 1.1rem;
            color: #94a3b8;
            margin-top: 30px;
        }

        .countdown-number {
            font-weight: 700;
            color: var(--accent-gold);
            font-size: 1.3rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .error-container {
                padding: 30px 20px;
                width: 95%;
            }

            .error-code {
                font-size: 6rem;
            }

            .error-title {
                font-size: 2rem;
            }

            .error-message {
                font-size: 1rem;
            }

            .error-icon {
                width: 140px;
                height: 140px;
            }

            .error-icon i {
                font-size: 80px;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .error-code {
                font-size: 4.5rem;
            }

            .error-title {
                font-size: 1.7rem;
            }

            .error-icon {
                width: 120px;
                height: 120px;
            }

            .error-icon i {
                font-size: 60px;
            }
        }

        /* Access denied animation */
        .access-denied-text {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            font-size: 12px;
            color: rgba(239, 68, 68, 0.3);
            letter-spacing: 3px;
            text-transform: uppercase;
            animation: slideText 20s linear infinite;
        }

        @keyframes slideText {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }
    </style>
</head>

<body>
    <!-- Animated background -->
    <div class="error-background" id="errorBackground"></div>

    <!-- Access denied text animation -->
    <div class="access-denied-text">Access Denied • Forbidden • 403 Error • Access Denied • Forbidden • 403 Error</div>

    <!-- Main error content -->
    <div class="error-container">
        <!-- Logo -->
        <div class="logo-container">
            <div class="logo">
                <?php
                $logo_path = "../../view/img/logo.png";
                if (file_exists($logo_path)): ?>
                    <img src="<?php echo $logo_path; ?>" alt="EzTrack Logo">
                <?php else: ?>
                    <div class="logo-placeholder">
                        EzTrack
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Error icon -->
        <div class="error-icon">
            <i class="fas fa-ban"></i>
        </div>

        <!-- Error code and title -->
        <h1 class="error-code">403</h1>
        <h2 class="error-title">Access Forbidden</h2>

        <!-- Error message -->
        <div class="error-message">
            You don't have permission to access this page or resource.
            This area is restricted to authorized users only.
        </div>

        <!-- Security message -->
        <div class="security-message">
            <h4><i class="fas fa-shield-alt"></i> Security Notice</h4>
            <p>
                Unauthorized access attempts are logged and monitored.
                If you believe this is an error, please contact your system administrator.
            </p>
        </div>

        <!-- Action buttons -->
        <div class="action-buttons">
            <a href="../../index.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                Go to Homepage
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Go Back
            </a>
            <a href="../../login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i>
                Login Again
            </a>
        </div>

        <!-- Countdown timer -->
        <div class="countdown">
            <i class="fas fa-clock"></i>
            Redirecting to homepage in <span class="countdown-number" id="countdown">10</span> seconds
        </div>
    </div>

    <script>
        // Create animated background elements
        function createErrorBackground() {
            const background = document.getElementById('errorBackground');

            // Create forbidden symbols (circles with slash)
            for (let i = 0; i < 15; i++) {
                const symbol = document.createElement('div');
                symbol.className = 'forbidden-symbol';
                symbol.innerHTML = '⛔';
                symbol.style.left = Math.random() * 100 + '%';
                symbol.style.animationDelay = Math.random() * 5 + 's';
                symbol.style.animationDuration = (Math.random() * 10 + 15) + 's';
                symbol.style.fontSize = (Math.random() * 20 + 30) + 'px';
                symbol.style.opacity = Math.random() * 0.1 + 0.05;
                background.appendChild(symbol);
            }

            // Create falling particles
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 8 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 6) + 's';
                background.appendChild(particle);
            }
        }

        // Countdown timer for redirect
        function startCountdown() {
            let countdown = 10;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = '../../index.php';
                }
            }, 1000);
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to go back
            if (e.key === 'Escape') {
                window.history.back();
            }

            // Home key to go to homepage
            if (e.key === 'Home') {
                window.location.href = '../../index.php';
            }

            // F5 to refresh
            if (e.key === 'F5') {
                e.preventDefault();
                window.location.reload();
            }
        });

        // Add click sound effect for buttons
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                // Create a subtle click effect
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            createErrorBackground();
            startCountdown();

            // Add a subtle shake effect to the error code
            const errorCode = document.querySelector('.error-code');
            setInterval(() => {
                errorCode.style.transform = 'translateX(2px)';
                setTimeout(() => {
                    errorCode.style.transform = 'translateX(-2px)';
                }, 100);
                setTimeout(() => {
                    errorCode.style.transform = 'translateX(0)';
                }, 200);
            }, 5000);

            // Show page load animation
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });

        // Prevent right-click context menu (optional security feature)
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            // Show custom alert
            const alertDiv = document.createElement('div');
            alertDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: rgba(239, 68, 68, 0.9);
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                font-weight: bold;
                z-index: 10000;
                animation: fadeInOut 3s ease;
            `;
            alertDiv.textContent = '⚠️ Right-click is disabled on this page';
            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 3000);

            return false;
        });

        // Add CSS for fade animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInOut {
                0% { opacity: 0; transform: translateY(-20px); }
                10% { opacity: 1; transform: translateY(0); }
                90% { opacity: 1; transform: translateY(0); }
                100% { opacity: 0; transform: translateY(-20px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>