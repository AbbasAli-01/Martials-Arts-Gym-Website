<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: accountandbooking.php');
    exit();
}

// --- Database Connection ---
$host = "localhost";
$dbname = "webspacemartialarts";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

// Membership options and fees
$membership_types = [
    "Basic" => "1 martial art – 2 sessions/week",
    "Intermediate" => "1 martial art – 3 sessions/week",
    "Advanced" => "Any 2 martial arts – 5 sessions/week",
    "Elite" => "Unlimited classes",
    "Junior Membership" => "All kids martial arts sessions"
];
$membership_fees = [
    "Basic" => 25.00,
    "Intermediate" => 35.00,
    "Advanced" => 45.00,
    "Elite" => 60.00,
    "Junior Membership" => 25.00
];

$action_message = "";
$membership = null;
$user_email = null;

$user_id = $_SESSION['user_id'] ?? null;

// Fetch user's email by ID
if ($user_id) {
    $user_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_stmt->bind_result($user_email_val);
    if ($user_stmt->fetch()) {
        $user_email = $user_email_val;
    }
    $user_stmt->close();

    // Handle membership booking or update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_membership'])) {
        $selected_type = $_POST['membership_type'] ?? '';
        if (isset($membership_types[$selected_type]) && isset($membership_fees[$selected_type])) {
            $fee = $membership_fees[$selected_type];
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+1 month'));

            // Check if user already has a membership
            $check_q = $conn->prepare("SELECT MID FROM memberships WHERE user_id = ?");
            $check_q->bind_param("i", $user_id);
            $check_q->execute();
            $check_q->store_result();
            if ($check_q->num_rows > 0) {
                $check_q->close();
                // Update existing membership
                $update = $conn->prepare("UPDATE memberships SET type=?, fee=?, status='Active', start_date=?, end_date=?, payment_status='Paid' WHERE user_id=?");
                $update->bind_param("sddsi", $selected_type, $fee, $start_date, $end_date, $user_id);
                if ($update->execute()) {
                    $action_message = "<span style='color:#43a047;'>Membership updated to <b>" . htmlspecialchars($selected_type) . "</b> (Fee: £" . number_format($fee, 2) . ")!</span>";
                } else {
                    $action_message = "<span style='color:#e73827;'>Failed to update membership.</span>";
                }
                $update->close();
            } else {
                $check_q->close();
                // Insert new membership
                $insert = $conn->prepare("INSERT INTO memberships (user_id, type, fee, status, start_date, end_date, payment_status) VALUES (?, ?, ?, 'Active', ?, ?, 'Paid')");
                $insert->bind_param("isdss", $user_id, $selected_type, $fee, $start_date, $end_date);
                if ($insert->execute()) {
                    $action_message = "<span style='color:#43a047;'>Membership <b>" . htmlspecialchars($selected_type) . "</b> selected! (Fee: £" . number_format($fee, 2) . ")</span>";
                } else {
                    $action_message = "<span style='color:#e73827;'>Failed to select membership.</span>";
                }
                $insert->close();
            }
        } else {
            $action_message = "<span style='color:#e73827;'>Invalid membership type selected.</span>";
        }
    }

    // Fetch active membership for display
    $stmt = $conn->prepare("SELECT MID, user_id, type, fee, status, start_date, end_date, payment_status FROM memberships WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($mid, $uid, $type, $fee, $status, $start_date, $end_date, $payment_status);
    if ($stmt->fetch()) {
        $period = date("d M Y", strtotime($start_date)) . " to " . date("d M Y", strtotime($end_date));
        $membership = [
            'id' => $mid,
            'type' => $type,
            'fee' => $fee,
            'status' => $status,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'period' => $period,
            'payment_status' => $payment_status
        ];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Keep your head content as before -->
  <meta charset="UTF-8">
  <title>Prices & Membership - DoBu Martial Arts</title>
  <link rel="stylesheet" href="gymcss.css">
  <!-- Your styles and JS for membership page -->
  <style>
    /* Your existing styling */
    table {
        border-collapse: collapse;
        width: 100%;
        max-width: 720px;
        margin: 25px auto;
        color: #fff;
    }
    th, td {
        padding: 10px 14px;
        border-bottom: 1px solid rgba(149,124,61,0.3);
        text-align: left;
    }
    th {
        background-color: rgba(149,124,61,0.4);
        font-weight: 700;
    }
    select {
        padding: 8px 10px;
        font-size: 1em;
        border-radius: 6px;
        margin-right: 12px;
        border: none;
        min-width: 220px;
    }
    .contact-submit {
        background: linear-gradient(90deg, #957C3D 0%, #002349 100%);
        border: none;
        padding: 10px 24px;
        color: #fff;
        font-weight: 700;
        border-radius: 30px;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .contact-submit:hover {
        background: linear-gradient(90deg, #002349 0%, #957C3D 100%);
    }
    .logout-btn {
        margin-top: 15px;
        background: #e73827;
        padding: 8px 20px;
        border-radius: 30px;
        border: none;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .logout-btn:hover {
        background: #b32e1f;
    }
    .membership-section {
        max-width: 720px;
        margin: 0 auto 40px auto;
        padding: 20px 25px;
        background: rgba(0, 35, 73, 0.7);
        border-radius: 20px;
        color: #fff;
    }
  </style>
  <script>
  // Your existing script for fees update (optional)
  </script>
</head>
<body>
  <nav>
    <a href="index.html">Home</a>
    <a href="membership.php" class="active">Prices & Membership</a>
    <a href="timetable.html">Timetable</a>
    <a href="instructors.html">Instructors</a>
    <a href="accountandbooking.php">Account</a>
    <a href="contactpage.html">Contact Us</a>
  </nav>
  <div class="container">
    <h1>Prices and Membership Options</h1>
    <?php if ($user_id): ?>
      <section class="membership-section">
        <h2 style="text-align: center;">Your Membership</h2>
        <?php if ($action_message): ?>
          <div style="margin-bottom:10px; text-align:center;"><?= $action_message ?></div>
        <?php endif; ?>
        <?php if ($membership): ?>
          <table>
            <tr><th>Email</th><td><?= htmlspecialchars($user_email) ?></td></tr>
            <tr><th>Type</th><td><?= htmlspecialchars($membership['type']) ?></td></tr>
            <tr><th>Status</th><td><?= htmlspecialchars($membership['status']) ?></td></tr>
            <tr><th>Time Period</th><td><?= htmlspecialchars($membership['period']) ?></td></tr>
            <tr><th>Fee per Month</th><td>£<?= number_format($membership['fee'], 2) ?></td></tr>
            <tr><th>Payment Status</th><td><?= htmlspecialchars($membership['payment_status']) ?></td></tr>
          </table>
          <form method="post" style="text-align:center; margin-top: 15px;">
            <label for="membership_type" style="font-weight:600;">Change Membership:</label>
            <select name="membership_type" id="membership_type" required>
              <?php foreach ($membership_types as $type_key => $desc): ?>
                <option value="<?=htmlspecialchars($type_key)?>" <?= ($membership['type'] === $type_key ? 'selected' : '') ?>><?=htmlspecialchars($type_key)?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" name="select_membership" class="contact-submit">Update</button>
          </form>
          <form action="accountandbooking.php?logout=1" method="post" style="text-align:center; margin-top: 15px;">
            <button type="submit" class="logout-btn">Log Out</button>
          </form>
        <?php else: ?>
          <form method="post" style="text-align:center;">
            <label for="membership_type" style="font-weight:600;">Select Membership:</label>
            <select name="membership_type" id="membership_type" required>
              <option value="" disabled selected>Select...</option>
              <?php foreach ($membership_types as $type_key => $desc): ?>
                <option value="<?=htmlspecialchars($type_key)?>"><?=htmlspecialchars($type_key)?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" name="select_membership" class="contact-submit">Select</button>
          </form>
          <div style="color:#fff; opacity:0.8; font-size:1em; margin-top:10px; text-align:center;">
            You do not have a membership yet. Please select one above.
          </div>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <section class="membership-section" style="background:rgba(0,35,73,0.7);">
        <div style="color:#957C3D; font-weight:600; margin-bottom:10px; text-align:center;">
          <span style="font-size:1.13em;">Already a member?</span>
          <a href="accountandbooking.php" class="login-btn" style="color:#957C3D; font-weight:700;">Log In</a>
        </div>
        <div style="color:#fff; opacity:0.8; font-size:1em; text-align:center;">
          To view and manage your membership, please log in or register an account.
        </div>
      </section>
    <?php endif; ?>

    <!-- Static listing of membership options and fees -->
    <table>
      <tr>
        <th>Membership Type</th>
        <th>Details</th>
        <th>Monthly Fee</th>
      </tr>
      <?php foreach ($membership_types as $type => $desc): ?>
      <tr>
        <td><?=htmlspecialchars($type)?></td>
        <td><?=htmlspecialchars($desc)?></td>
        <td>£<?=number_format($membership_fees[$type], 2)?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td>Private Tuition</td>
        <td>Per hour</td>
        <td>£15.00</td>
      </tr>
    </table>
    <h2>Specialist Courses and Fitness Training</h2>
    <table>
      <tr>
        <th>Course/Service</th>
        <th>Details</th>
        <th>Fee</th>
      </tr>
      <tr>
        <td>Beginners’ Self-defence</td>
        <td>Six-week course (2 × 1-hour/week)</td>
        <td>£180.00</td>
      </tr>
      <tr>
        <td>Fitness Room</td>
        <td>Per visit</td>
        <td>£6.00</td>
      </tr>
      <tr>
        <td>Personal Fitness Training</td>
        <td>Per hour</td>
        <td>£35.00</td>
      </tr>
    </table>
  </div>
  <footer style="width:100vw; position:fixed; left:0; bottom:0; padding:8px 0; background:linear-gradient(90deg, rgba(255,255,255,0.32), rgba(0,0,0,0.17), rgba(255,255,255,0.32)); color:#fff; text-align:center; font-size:0.9em; letter-spacing:0.4px; backdrop-filter:blur(4px); z-index:100;">
    © 2025 DoBu Martial Arts. All rights reserved.
  </footer>
</body>
</html>
<?php