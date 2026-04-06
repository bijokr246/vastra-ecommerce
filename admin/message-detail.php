<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// 3. VALIDATE MESSAGE ID & FETCH DATA
$message_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$message_id) {
    header('Location: view-messages.php'); // Redirect if ID is invalid
    exit();
}

$message = null;
$sql = "SELECT cm.message_id, cm.subject, cm.message, cm.status, cm.created_at, u.fname, u.lname, u.email
        FROM contact_messages cm
        JOIN users u ON cm.user_id = u.user_id
        WHERE cm.message_id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $message = $result->fetch_assoc();

        // **IMPORTANT**: If the message was 'unread', update its status to 'read'.
        if ($message['status'] === 'unread') {
            $update_sql = "UPDATE contact_messages SET status = 'read' WHERE message_id = ?";
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("i", $message_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
    }
    $stmt->close();
}

// --- Fetch Admin's Name for the header ---
$result_admin_name = $conn->query("SELECT fname FROM users WHERE user_id = {$_SESSION['admin_id']}");
$admin_name = $result_admin_name->fetch_assoc()['fname'];

$conn->close();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Message Details | Vastra Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <!-- EMBEDDED CSS FOR THIS PAGE -->
  <style>
    .toolbar {
      margin-bottom: 20px;
    }
    .back-button {
      display: inline-block;
      background-color: #6c757d;
      color: #fff !important; /* Ensure text is white */
      padding: 8px 15px;
      border-radius: 5px;
      text-decoration: none;
      transition: background-color 0.3s;
    }
    .back-button:hover {
      background-color: #5a6268;
    }
    .back-button i {
      margin-right: 8px;
    }
    .message-detail-card {
      padding: 25px;
    }
    .detail-header h2 {
      margin-top: 0;
      margin-bottom: 15px;
      font-size: 1.8rem;
      color: #333;
    }
    .detail-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      color: #666;
      font-size: 0.9rem;
      margin-bottom: 15px;
    }
    .detail-meta a {
      color: #007bff;
      text-decoration: none;
    }
    .detail-meta a:hover {
      text-decoration: underline;
    }
    .message-body {
      margin-top: 20px;
      line-height: 1.7;
      font-size: 1rem;
      color: #444;
      white-space: pre-wrap; /* Preserves whitespace and line breaks from textarea */
      word-wrap: break-word; /* Prevents long text from overflowing */
    }
  </style>
</head>
<body>
  <aside class="sidebar">
    <?php require "header.php"; ?>
  </aside>

  <div class="main-content">
    <header class="header">
      <h1>Message Details</h1>
      <div class="admin-profile"><a href="#"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($admin_name); ?></a></div>
    </header>

    <main>
      <div class="toolbar">
        <a href="view-messages.php" class="back-button">
          <i class="fas fa-arrow-left"></i> Back to All Messages
        </a>
      </div>
      
      <?php if ($message): ?>
        <section class="content-card message-detail-card">
          <div class="detail-header">
            <h2><?php echo htmlspecialchars($message['subject']); ?></h2>
          </div>
          <div class="detail-meta">
            <span><strong>From:</strong> <?php echo htmlspecialchars($message['fname'] . ' ' . $message['lname']); ?></span>
            <span><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>"><?php echo htmlspecialchars($message['email']); ?></a></span>
            <span><strong>Received:</strong> <?php echo date('M d, Y, h:i A', strtotime($message['created_at'])); ?></span>
          </div>
          <hr>
          <div class="message-body">
            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
          </div>
        </section>
      <?php else: ?>
        <section class="content-card">
          <p>Message not found. It may have been deleted.</p>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>