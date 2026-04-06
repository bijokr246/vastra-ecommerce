<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Failed</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; font-family: 'Segoe UI', sans-serif; background: #f5f5f6; }
        .message-box { text-align: center; padding: 40px 60px; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .message-box i { font-size: 60px; color: #dc3545; margin-bottom: 20px; }
        h1 { margin-bottom: 15px; }
        p { margin-bottom: 25px; color: #696b79; }
        .btn-primary { display: inline-block; padding: 10px 25px; background-color: #ff3f6c; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
    <script src="../refresh.js"></script>
</head>
<body>
    <div class="message-box">
        <i class="fas fa-times-circle"></i>
        <h1>Payment Failed</h1>
        <p>Unfortunately, your payment could not be processed.</p>
        <?php if (!empty($_GET['reason'])): ?>
            <p style="font-size:0.9em; color:#888;">Reason: <?php echo htmlspecialchars(urldecode($_GET['reason'])); ?></p>
        <?php endif; ?>
        <a href="../checkout.php" class="btn-primary">Try Again</a>
    </div>
</body>
</html>