<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- DATABASE CONNECTION ---
$host = "localhost";
$dbname = "webspacemartialarts";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

// --- Setup martial arts and courses ---
$martial_arts = [
    "Karate" => null,
    "Judo" => null,
    "Taekwondo" => null,
    "Kickboxing" => null
];
$courses = [
    "Beginners’ Self-defence" => ["Six-week course (2 × 1-hour/week)", 180.00],
    "Fitness Room" => ["Per visit", 6.00],
    "Personal Fitness Training" => ["Per hour", 35.00]
];

// --- HANDLE REGISTRATION ---
$register_error = $register_success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $reg_username = trim($_POST['reg_username'] ?? '');
    $reg_email = trim($_POST['reg_email'] ?? '');
    $reg_phone = trim($_POST['reg_phone'] ?? '');
    $reg_password = $_POST['reg_password'] ?? '';
    if ($reg_username && $reg_email && $reg_phone && $reg_password) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $reg_username, $reg_email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $register_error = "Username or email already exists.";
        } else {
            $hashed = password_hash($reg_password, PASSWORD_DEFAULT);
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $reg_username, $reg_email, $reg_phone, $hashed);
            if ($stmt->execute()) {
                $register_success = "Registration successful! Please log in.";
            } else {
                $register_error = "Registration failed. Try again.";
            }
        }
        $stmt->close();
    } else {
        $register_error = "Please fill in all fields.";
    }
}

// --- HANDLE LOGIN ---
$login_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $login_useroremail = trim($_POST['login_useroremail'] ?? '');
    $login_password = $_POST['login_password'] ?? '';
    if ($login_useroremail && $login_password) {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login_useroremail, $login_useroremail);
        $stmt->execute();
        $stmt->bind_result($user_id, $username, $hash);
        if ($stmt->fetch() && password_verify($login_password, $hash)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            header("Location: accountandbooking.php");
            exit();
        } else {
            $login_error = "Invalid username/email or password.";
        }
        $stmt->close();
    } else {
        $login_error = "Please fill in all fields.";
    }
}

// --- HANDLE LOGOUT ---
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: accountandbooking.php");
    exit();
}

// --- HANDLE BOOKING (class or course) ---
$booking_msg = "";
if (
    isset($_SESSION['user_id']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'booking'
) {
    $booking_type = $_POST['booking_choice'] ?? '';
    $booking_date = $_POST['booking_date'] ?? null;
    $allowed_types = array_merge(array_keys($martial_arts), array_keys($courses));

    if (!$booking_type || !in_array($booking_type, $allowed_types)) {
        $booking_msg = "<span style='color:#e73827;font-weight:600;'>Invalid selection.</span>";
    } elseif (!$booking_date) {
        $booking_msg = "<span style='color:#e73827;font-weight:600;'>Date is required for booking.</span>";
    } else {
        $user_id = $_SESSION['user_id'];

        if (isset($martial_arts[$booking_type])) {
            // Class booking: fee is NULL, description empty string
            $type = "Class";
            $desc = "";
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, type, title, date, fee, description, booked_at) VALUES (?, ?, ?, ?, NULL, ?, NOW())");
            if (!$stmt) {
                $booking_msg = "<span style='color:#e73827;font-weight:600;'>Prepare failed: " . htmlspecialchars($conn->error) . "</span>";
            } else {
                $stmt->bind_param("isss", $user_id, $type, $booking_type, $booking_date, $desc);
                if (!$stmt->execute()) {
                    $booking_msg = "<span style='color:#e73827;font-weight:600;'>Execute failed: " . htmlspecialchars($stmt->error) . "</span>";
                } else {
                    $booking_msg = "<span style='color:#43a047;font-weight:600;'>Class booked: " . htmlspecialchars($booking_type) . " on $booking_date.</span>";
                }
                $stmt->close();
            }
        } elseif (isset($courses[$booking_type])) {
            list($desc, $fee) = $courses[$booking_type];
            $type = "Course";
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, type, title, date, fee, description, booked_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                $booking_msg = "<span style='color:#e73827;font-weight:600;'>Prepare failed: " . htmlspecialchars($conn->error) . "</span>";
            } else {
                $stmt->bind_param("isssds", $user_id, $type, $booking_type, $booking_date, $fee, $desc);
                if (!$stmt->execute()) {
                    $booking_msg = "<span style='color:#e73827;font-weight:600;'>Course booking failed: " . htmlspecialchars($stmt->error) . "</span>";
                } else {
                    $booking_msg = "<span style='color:#43a047;font-weight:600;'>Applied for course: " . htmlspecialchars($booking_type) . " on $booking_date (£" . number_format($fee, 2) . ").</span>";
                }
                $stmt->close();
            }
        }
    }
}

// --- FETCH user info ---
$user_info = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT username, email, phone, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($info_username, $info_email, $info_phone, $info_created_at);
    if ($stmt->fetch()) {
        $user_info = [
            'username' => $info_username,
            'email' => $info_email,
            'phone' => $info_phone,
            'created_at' => $info_created_at
        ];
    }
    $stmt->close();
}

// --- FETCH recent bookings ---
$recent_bookings = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT type, title, date, fee, description, booked_at FROM bookings WHERE user_id = ? ORDER BY booked_at DESC LIMIT 5");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($btype, $btitle, $bdate, $bfee, $bdesc, $bwhen);
    while ($stmt->fetch()) {
        $recent_bookings[] = [
            'type' => $btype,
            'title' => $btitle,
            'date' => $bdate,
            'fee' => $bfee,
            'desc' => $bdesc,
            'booked_at' => $bwhen
        ];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Account - DoBu Martial Arts</title>
  <link rel="stylesheet" href="gymcss.css" />
  <style>
    /* Your existing styles... (keep your CSS as needed) */
    h1 { text-align: center; }
    .account-flex {
      display: flex;
      gap: 32px;
      justify-content: center;
      align-items: flex-start;
      flex-wrap: wrap;
      margin-top: 24px;
    }
    .account-card {
      background: linear-gradient(135deg, rgba(0,35,73,0.92) 0%, rgba(149,124,61,0.80) 100%);
      padding: 36px 32px 28px 32px;
      border-radius: 22px;
      box-shadow: 0 6px 32px #00234944, 0 2px 20px #957c3d33;
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 100%;
      max-width: 350px;
      min-width: 260px;
      gap: 14px;
      border: 1.5px solid #957c3d88;
      transition: box-shadow 0.23s, border 0.23s;
    }
    .account-card h2 {
      margin: 0 0 18px 0;
      font-size: 1.17em;
      letter-spacing: 0.5px;
      color: #fff;
      background: none;
      text-align: center;
      font-weight: 700;
      text-shadow: 0 2px 10px #00234999, 0 1px 2px #957c3d99;
    }
    .account-card label,
    .account-card select,
    .account-card input,
    .account-card button {
      display: block;
      width: 100%;
      margin-top: 0.7em;
    }
    .account-card label:first-of-type {
      margin-top: 0;
    }
    .account-card input {
      text-align: center;
      background: rgba(255,255,255,0.93);
      color: #002349;
      border: 1.5px solid #b0b8c1;
      border-radius: 10px;
      padding: 12px;
      font-size: 1.05em;
      font-family: inherit;
      box-shadow: 0 1px 4px #00234922;
      transition: border 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .account-card input:focus {
      border: 2px solid #957c3d;
      background: #fff;
      color: #002349;
      outline: none;
      box-shadow: 0 0 10px #957c3d44;
    }
    .account-card button {
      margin-bottom: 6px;
      font-size: 1.09em;
      background: linear-gradient(90deg, #957C3D 0%, #002349 100%);
      color: #fff;
      font-weight: 700;
      border: none;
      border-radius: 22px;
      padding: 13px 0;
      letter-spacing: 0.5px;
      box-shadow: 0 4px 18px #00234922, 0 2px 12px #957c3dcc;
      text-shadow: 0 2px 8px #002349b0;
      cursor: pointer;
      font-family: inherit;
      transition: background 0.21s, color 0.18s, transform 0.15s, box-shadow 0.17s;
    }
    .account-card button:hover, .account-card button:focus {
      background: linear-gradient(90deg, #002349 0%, #957C3D 100%);
      color: #fff;
      transform: scale(1.04) translateY(-2px);
      box-shadow: 0 8px 32px #00234955, 0 2px 16px #957c3d33;
      outline: none;
    }
    .account-divider {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      min-width: 1px;
      background: linear-gradient(to bottom, #957c3d 10%, #002349 90%);
      width: 2.5px;
      border-radius: 3px;
      margin: 0;
      opacity: 0.85;
    }
    label, select, input {
      display: block;
      margin-bottom: 12px;
    }
    .center-status-logout {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      margin-bottom: 18px;
      width: 100%;
    }
    #logout-btn {
      margin: 10px 0 0 0;
      font-size: 1.09em;
      background: linear-gradient(90deg, #002349 0%, #957C3D 100%);
      color: #fff;
      font-weight: 700;
      border: none;
      border-radius: 22px;
      padding: 13px 32px;
      letter-spacing: 0.5px;
      box-shadow: 0 4px 18px #00234922, 0 2px 12px #957c3dcc;
      text-shadow: 0 2px 8px #002349b0;
      cursor: pointer;
      font-family: inherit;
      display: inline-block;
      transition: background 0.21s, color 0.18s, transform 0.15s, box-shadow 0.17s;
    }
    #account-status {
      margin-bottom: 0;
      font-size: 1.08em;
      text-align: center;
      color: #957C3D;
      font-weight: 700;
      text-shadow: 0 2px 8px #002349b0;
    }
    .account-help {
      margin-top: 28px;
      text-align: center;
      max-width: 420px;
      margin-left: auto;
      margin-right: auto;
      font-size: 1.04em;
      opacity: 0.88;
      color: #fff;
      text-shadow: 0 2px 8px #002349b0;
    }
    .register-headline {
      color: #ffe27a;
      font-weight: 600;
      font-size: 1.13em;
      text-align: center;
      margin-bottom: 0.7em;
      text-shadow: 0 1px 4px #002349bb;
    }
    @media (max-width: 800px) {
      .account-flex {
        flex-direction: column;
        gap: 24px;
        align-items: stretch;
      }
      .account-divider {
        display: none;
      }
      .account-card {
        max-width: 98vw;
        padding: 18px;
        min-width: 0;
      }
      .center-status-logout {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <nav>
    <a href="index.html">Home</a>
    <a href="pricing.html">Prices & Membership</a>
    <a href="timetable.html">Timetable</a>
    <a href="instructors.html">Instructors</a>
    <a href="accountandbooking.php">Account</a>
    <a href="contactsocialpage.html">Contact & Community</a>
  </nav>

  <div class="container">
    <h1>Account Management</h1>

    <?php if (isset($_SESSION['user_id'])): ?>
      <div class="center-status-logout">
        <div id="account-status">You are now logged in as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></div>
        <form action="accountandbooking.php?logout=1" method="post" style="margin: 0;">
          <button id="logout-btn" type="submit">Logout</button>
        </form>
      </div>

      <?php if (!empty($user_info)): ?>
        <table class="user-info-table" aria-label="User information" style="margin: 0 auto 24px auto; color:#ffe27a; background: rgba(0,35,73,0.75); padding: 15px; border-radius: 16px; max-width: 370px;">
          <tr><th>Email</th><td><?= htmlspecialchars($user_info['email']) ?></td></tr>
          <tr><th>Phone</th><td><?= htmlspecialchars($user_info['phone']) ?></td></tr>
          <tr><th>Account Created</th><td><?= htmlspecialchars($user_info['created_at']) ?></td></tr>
        </table>
      <?php endif; ?>

      <p class="account-help">You can now manage your membership and bookings.</p>

      <?php if ($booking_msg): ?>
        <div class="<?= strpos($booking_msg, '#43a047') !== false ? 'booking-confirmed' : 'booking-error' ?>" style="text-align:center; margin-bottom:15px;"><?= $booking_msg ?></div>
      <?php endif; ?>

      <div class="booking-section">
        <h2>Book a Martial Arts Class or Apply for a Course</h2>
        <form id="booking-form" method="post" autocomplete="off" novalidate>
          <input type="hidden" name="action" value="booking" />

          <label for="booking-choice">Choose Class or Course</label>
          <select id="booking-choice" name="booking_choice" required>
            <option value="" disabled selected>-- Select --</option>
            <?php foreach ($martial_arts as $art => $_): ?>
              <option value="<?= htmlspecialchars($art) ?>"><?= htmlspecialchars($art) ?> (Class)</option>
            <?php endforeach; ?>
            <?php foreach ($courses as $course => $data): ?>
              <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?> (<?= htmlspecialchars($data[0]) ?>, £<?= number_format($data[1], 2) ?>)</option>
            <?php endforeach; ?>
          </select>

          <label for="booking-date">Date (required)</label>
          <input type="date" id="booking-date" name="booking_date" min="<?= date('Y-m-d') ?>" required />

          <button type="submit">Book or Apply</button>
        </form>
      </div>

      <?php if ($recent_bookings): ?>
        <h2 style="text-align:center; margin-top: 30px; color:#fff;">Recent Bookings</h2>
        <table class="recent-bookings-table" aria-label="Recent bookings" style="margin: auto; max-width: 700px; background: rgba(255,255,255,0.07); border-radius: 13px; color: #fff; font-size: 1em;">
          <thead>
            <tr>
              <th>Type</th><th>Title</th><th>Date</th><th>Fee</th><th>Booked At</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_bookings as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['type']) ?></td>
                <td><?= htmlspecialchars($b['title']) ?></td>
                <td><?= htmlspecialchars($b['date'] ?: '—') ?></td>
                <td><?= is_null($b['fee']) ? '—' : '£' . number_format($b['fee'], 2) ?></td>
                <td><?= htmlspecialchars($b['booked_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    <?php else: ?>
      <div class="center-status-logout"><div id="account-status"></div></div>
      <div class="account-flex">
        <form class="account-card" method="post" autocomplete="off" novalidate>
          <div class="register-headline">No account yet? Create one here!</div>
          <h2>Register</h2>
          <?php if ($register_error): ?>
            <div style="color:#e73827; font-weight:600; margin-bottom:10px;"><?= htmlspecialchars($register_error) ?></div>
          <?php elseif ($register_success): ?>
            <div style="color:#43a047; font-weight:600; margin-bottom:10px;"><?= htmlspecialchars($register_success) ?></div>
          <?php endif; ?>
          <input type="hidden" name="action" value="register" />
          <input type="text" name="reg_username" placeholder="Username" autocomplete="username" required>
          <input type="email" name="reg_email" placeholder="Email" autocomplete="email" required>
          <input type="tel" name="reg_phone" placeholder="Phone Number" autocomplete="tel" required>
          <input type="password" name="reg_password" placeholder="Password" autocomplete="new-password" required>
          <button type="submit">Register Now</button>
        </form>

        <div class="account-divider"></div>

        <form class="account-card" method="post" autocomplete="off" novalidate>
          <h2>Sign In</h2>
          <?php if ($login_error): ?>
            <div style="color:#e73827; font-weight:600; margin-bottom:10px;"><?= htmlspecialchars($login_error) ?></div>
          <?php endif; ?>
          <input type="hidden" name="action" value="login" />
          <input type="text" name="login_useroremail" placeholder="Username or Email" autocomplete="username" required>
          <input type="password" name="login_password" placeholder="Password" autocomplete="current-password" required>
          <button type="submit">Login Now</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <footer style="width:100vw; position:fixed; left:0; bottom:0; padding:8px 0; background:linear-gradient(90deg, rgba(255,255,255,0.32), rgba(0,0,0,0.17), rgba(255,255,255,0.32)); color:#fff; text-align:center; font-size:0.9em; letter-spacing:0.4px; backdrop-filter:blur(4px); z-index:100;">
    © 2025 DoBu Martial Arts. All rights reserved.
  </footer>
</body>
</html>
