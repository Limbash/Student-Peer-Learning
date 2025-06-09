<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Student Peer-to-Peer Learning Network</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/register.css">
</head>
<body>
  <div class="register-card col-md-6">
    <h3 class="text-center mb-4">Create an Account</h3>
    <form action="welcome.php" method="POST">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="firstName" class="form-label">First Name</label>
          <input type="text" class="form-control" id="firstName" name="firstName" required>
        </div>
        <div class="col-md-6 mb-3">
          <label for="lastName" class="form-label">Last Name</label>
          <input type="text" class="form-control" id="lastName" name="lastName" required>
        </div>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="phone" class="form-label">Phone Number</label>
          <div class="input-group">
            <select class="form-select" id="countryCode" name="countryCode" required></select>
            <input type="tel" class="form-control" id="phone" name="phone" placeholder="1234567890" required>
          </div>
        </div>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
        <span class="toggle-password position-absolute end-0 top-50 translate-middle-y me-3" style="cursor: pointer;">
          <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
        </span>
      </div>
      <div class="mb-3">
        <label for="confirmPassword" class="form-label">Confirm Password</label>
        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
        <span class="toggle-password position-absolute end-0 top-50 translate-middle-y me-3" style="cursor: pointer;">
          <i class="bi bi-eye-slash" id="toggleConfirmPasswordIcon"></i>
        </span>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-success">Register</button>
      </div>
      <p class="text-center mt-3">Already have an account? <a href="login.html">Login here</a></p>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.js"></script>
  <script>
    // Password visibility toggle
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');
    const passwordField = document.getElementById('password');

    const toggleConfirmPasswordIcon = document.getElementById('toggleConfirmPasswordIcon');
    const confirmPasswordField = document.getElementById('confirmPassword');

    togglePasswordIcon.addEventListener('click', function () {
      const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordField.setAttribute('type', type);
      this.classList.toggle('bi-eye');
      this.classList.toggle('bi-eye-slash');
    });

    toggleConfirmPasswordIcon.addEventListener('click', function () {
      const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
      confirmPasswordField.setAttribute('type', type);
      this.classList.toggle('bi-eye');
      this.classList.toggle('bi-eye-slash');
    });

    // Fetch and populate country codes
    async function loadCountryCodes() {
      try {
        const response = await fetch('https://restcountries.com/v3.1/all');
        const countries = await response.json();

        const selectElement = document.getElementById('countryCode');
        countries.sort((a, b) => a.name.common.localeCompare(b.name.common));

        countries.forEach(country => {
          if (country.idd && country.idd.root) {
            const code = country.idd.root + (country.idd.suffixes ? country.idd.suffixes[0] : '');
            const option = document.createElement('option');
            option.value = code;
            option.textContent = `${country.name.common} (${code})`;
            selectElement.appendChild(option);
          }
        });
      } catch (error) {
        console.error('Error fetching country codes:', error);
      }
    }

    loadCountryCodes();
  </script>
</body>
</html>