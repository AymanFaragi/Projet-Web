<?php
include 'config.php';

if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

if (!isset($_SESSION['signup_data'])) {
  $_SESSION['signup_data'] = [];
}

$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$totalSteps = 4;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  switch ($step) {
    case 1:
      $error = validateStep1($_POST);
      if (!$error) {
        $_SESSION['signup_data'] = array_merge($_SESSION['signup_data'], [
          'first_name' => trim($_POST['first_name']),
          'last_name' => trim($_POST['last_name']),
          'email' => trim($_POST['email']),
          'password' => password_hash($_POST['password'], PASSWORD_DEFAULT)
        ]);
        header("Location: signup.php?step=2");
        exit;
      }
      break;

    case 2:
      $uploadResult = handleProfilePictureUpload();
      if ($uploadResult['success']) {
        $_SESSION['signup_data']['profile_picture'] = $uploadResult['file_path'];
        header("Location: signup.php?step=3");
        exit;
      } else {
        $error = $uploadResult['error'];
      }
      break;

    case 3:
      $error = validateContactInfo($_POST);
      if (!$error) {
        $_SESSION['signup_data'] = array_merge($_SESSION['signup_data'], [
          'phone' => trim($_POST['phone']),
          'address' => trim($_POST['address']),
          'city' => trim($_POST['city']),
          'state' => trim($_POST['state']),
          'zip_code' => trim($_POST['zip_code']),
          'country' => trim($_POST['country'])
        ]);
        header("Location: signup.php?step=4");
        exit;
      }
      break;

    case 4:
      $error = validateAndCreateAccount();
      if (!$error) {
        unset($_SESSION['signup_data']);
        header("Location: account.php?signup_success=1");
        exit;
      }
      break;
  }
}

function validateStep1($data)
{
  if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['password'])) {
    return "All fields are required";
  }

  if ($data['password'] !== $data['confirm_password']) {
    return "Passwords do not match";
  }

  if (strlen($data['password']) < 8) {
    return "Password must be at least 8 characters";
  }

  $conn = getDBConnection();
  $stmt = $conn->prepare("SELECT email FROM users WHERE email = :email");
  $stmt->bindParam(':email', $data['email']);
  $stmt->execute();

  if ($stmt->rowCount() > 0) {
    return "Email already registered";
  }

  return false;
}

function handleProfilePictureUpload()
{
  if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    return ['success' => false, 'error' => 'Please select a valid image file'];
  }

  $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
  $fileType = $_FILES['profile_picture']['type'];

  if (!in_array($fileType, $allowedTypes)) {
    return ['success' => false, 'error' => 'Only JPG, PNG, and GIF images are allowed'];
  }

  $uploadDir = 'uploads/profile_pictures/';
  if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $fileName = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
  $targetPath = $uploadDir . $fileName;

  if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
    return ['success' => true, 'file_path' => $targetPath];
  }

  return ['success' => false, 'error' => 'Failed to upload image'];
}

function validateContactInfo($data)
{
  if (
    empty($data['phone']) || empty($data['address']) || empty($data['city']) ||
    empty($data['state']) || empty($data['zip_code']) || empty($data['country'])
  ) {
    return "All fields are required";
  }

  return false;
}

function validateAndCreateAccount()
{
  if (empty($_SESSION['signup_data'])) {
    return "Session expired. Please start over.";
  }

  $conn = getDBConnection();
  try {
    $stmt = $conn->prepare("
            INSERT INTO users (
                first_name, last_name, email, password, profile_picture,
                phone, address, city, state, zip_code, country
            ) VALUES (
                :first_name, :last_name, :email, :password, :profile_picture,
                :phone, :address, :city, :state, :zip_code, :country
            )
        ");

    $stmt->execute($_SESSION['signup_data']);
    $userId = $conn->lastInsertId();

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $_SESSION['signup_data']['first_name'] . ' ' . $_SESSION['signup_data']['last_name'];

    return false;
  } catch (PDOException $e) {
    return "Registration failed. Please try again.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>sKYS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="signup.css">
  <link rel="stylesheet" href="index.css">
</head>

<body>
  <div class="signup-container">
    <div class="signup-progress">
      <div class="progress-steps">
        <?php for ($i = 1; $i <= $totalSteps; $i++): ?>
          <div class="step <?= $i < $step ? 'completed' : '' ?> <?= $i == $step ? 'active' : '' ?>">
            <span><?= $i ?></span>
            <div class="step-label">
              <?php
              switch ($i) {
                case 1:
                  echo 'Account';
                  break;
                case 2:
                  echo 'Profile';
                  break;
                case 3:
                  echo 'Contact';
                  break;
                case 4:
                  echo 'Complete';
                  break;
              }
              ?>
            </div>
          </div>
          <?php if ($i < $totalSteps): ?>
            <div class="step-connector <?= $i < $step ? 'completed' : '' ?>"></div>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    </div>

    <div class="signup-form-container">
      <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="signup.php?step=<?= $step ?>"
        enctype="<?= $step == 2 ? 'multipart/form-data' : 'application/x-www-form-urlencoded' ?>">
        <?php switch ($step):
          case 1: ?>
            <h2>Create Your Account</h2>
            <div class="form-group">
              <label for="first_name">First Name</label>
              <input type="text" id="first_name" name="first_name" class="form-control"
                value="<?= htmlspecialchars($_SESSION['signup_data']['first_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label for="last_name">Last Name</label>
              <input type="text" id="last_name" name="last_name" class="form-control"
                value="<?= htmlspecialchars($_SESSION['signup_data']['last_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" class="form-control"
                value="<?= htmlspecialchars($_SESSION['signup_data']['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label for="password">Password (min 8 characters)</label>
              <input type="password" id="password" name="password" class="form-control" required minlength="8">
            </div>

            <div class="form-group">
              <label for="confirm_password">Confirm Password</label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                minlength="8">
            </div>
            <?php break; ?>
          <?php case 2: ?>
            <h2>Add Profile Picture</h2>
            <div class="profile-picture-upload">
              <div class="preview-container">
                <img id="profile-preview"
                  src="<?= $_SESSION['signup_data']['profile_picture'] ?? 'images/default-profile.png' ?>"
                  alt="Profile Preview" class="profile-preview">
                <div class="upload-overlay">
                  <i class="fas fa-camera"></i>
                  <span>Choose Image</span>
                </div>
              </div>
              <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="file-input">
            </div>
            <div class="form-note">
              Recommended size: 500x500px. Max file size: 2MB.
            </div>
            <?php break; ?>

          <?php case 3: ?>
            <h2>Contact Information</h2>
            <div class="form-group">
              <label for="phone">Phone Number</label>
              <input type="tel" id="phone" name="phone" class="form-control"
                value="<?= htmlspecialchars($_SESSION['signup_data']['phone'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label for="address">Street Address</label>
              <input type="text" id="address" name="address" class="form-control"
                value="<?= htmlspecialchars($_SESSION['signup_data']['address'] ?? '') ?>" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" class="form-control"
                  value="<?= htmlspecialchars($_SESSION['signup_data']['city'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label for="state">State/Province</label>
                <input type="text" id="state" name="state" class="form-control"
                  value="<?= htmlspecialchars($_SESSION['signup_data']['state'] ?? '') ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="zip_code">ZIP/Postal Code</label>
                <input type="text" id="zip_code" name="zip_code" class="form-control"
                  value="<?= htmlspecialchars($_SESSION['signup_data']['zip_code'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label for="country">Country</label>
                <input type="text" id="country" name="country" class="form-control"
                  value="<?= htmlspecialchars($_SESSION['signup_data']['country'] ?? '') ?>" required>
              </div>
            </div>
            <?php break; ?>

          <?php case 4: ?>
            <h2>Review Your Information</h2>
            <div class="review-section">
              <div class="review-item">
                <h3>Account Details</h3>
                <p><strong>Name:</strong>
                  <?= htmlspecialchars($_SESSION['signup_data']['first_name'] . ' ' . $_SESSION['signup_data']['last_name']) ?>
                </p>
                <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['signup_data']['email']) ?></p>
              </div>

              <div class="review-item">
                <h3>Profile Picture</h3>
                <img src="<?= $_SESSION['signup_data']['profile_picture'] ?? 'images/default-profile.png' ?>"
                  alt="Profile Preview" class="profile-review-image">
              </div>

              <div class="review-item">
                <h3>Contact Information</h3>
                <p><strong>Phone:</strong> <?= htmlspecialchars($_SESSION['signup_data']['phone']) ?></p>
                <p><strong>Address:</strong> <?= htmlspecialchars($_SESSION['signup_data']['address']) ?></p>
                <p>
                  <?= htmlspecialchars($_SESSION['signup_data']['city'] . ', ' . $_SESSION['signup_data']['state'] . ' ' . $_SESSION['signup_data']['zip_code']) ?>
                </p>
                <p><?= htmlspecialchars($_SESSION['signup_data']['country']) ?></p>
              </div>
            </div>

            <?php break; ?>
        <?php endswitch; ?>

        <div class="form-actions">
          <?php if ($step > 1): ?>
            <a href="signup.php?step=<?= $step - 1 ?>" class="btn btn-secondary">Back</a>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary">
            <?= $step == $totalSteps ? 'Complete Registration' : 'Continue' ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.getElementById('profile_picture')?.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (event) {
          document.getElementById('profile-preview').src = event.target.result;
        }
        reader.readAsDataURL(file);
      }
    });

    document.querySelector('.upload-overlay')?.addEventListener('click', function () {
      document.getElementById('profile_picture').click();
    });
  </script>
</body>

</html>