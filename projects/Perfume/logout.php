<?php
// 1. Destroy the session immediately
session_start();
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out...</title>
    <meta http-equiv="refresh" content="3;url=index.php">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #F5F1E9; /* Your theme background */
            font-family: 'Poppins', sans-serif;
            color: #5E5343; /* Your theme text color */
            flex-direction: column;
        }

        .logout-container {
            text-align: center;
            opacity: 0;
            animation: fadeIn 1s ease-in-out forwards;
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        p {
            font-size: 1.1rem;
            color: #888;
        }

        /* Spinner Animation */
        .spinner {
            margin: 20px auto;
            width: 50px;
            height: 50px;
            border: 5px solid #B2A48C;
            border-top: 5px solid #5E5343; /* Darker theme color */
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Animations */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="logout-container">
        <div class="spinner"></div>
        <h2>Logging Out...</h2>
        <p>See you soon! Redirecting you to the home page.</p>
    </div>

    <script>
        // Wait 3 seconds (3000 milliseconds) then redirect
        setTimeout(function() {
            window.location.href = './index.php';
        }, 1000);
    </script>

</body>
</html>