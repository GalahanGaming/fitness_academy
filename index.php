<?php
$msg = "";
$msg_type = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'registered') {
        $msg = "Registration successful! You can now login.";
        $msg_type = "success";
    } elseif ($_GET['msg'] === 'invalid') {
        $msg = "Invalid email or password. Please try again.";
        $msg_type = "error";
    } elseif ($_GET['msg'] === 'unauthorized') {
        $msg = "Please login to access that page.";
        $msg_type = "error";
    } elseif ($_GET['msg'] === 'deactivated') {
        $msg = "Your account has been deactivated. Please contact the gym for assistance.";
        $msg_type = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fitness Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            overflow: hidden;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            width: 42%;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
        }

        .left-video {
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }

        /* Dark overlay so text is readable over video */
        .left-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to bottom,
                rgba(0,0,0,0.6) 0%,
                rgba(0,0,0,0.4) 50%,
                rgba(0,0,0,0.8) 100%
            );
            z-index: 1;
        }

        .left-content {
            position: relative;
            z-index: 3;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem 2.5rem;
        }

        .logo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem;
            color: #e8ff47;
            letter-spacing: 3px;
            line-height: 1;
        }

        .logo-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            letter-spacing: 4px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .tagline {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3.5rem;
            color: #fff;
            line-height: 1.05;
            letter-spacing: 1px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }

        .tagline em { color: #e8ff47; font-style: normal; }

        .tagline-sub {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            margin-top: 1rem;
            line-height: 1.6;
            max-width: 280px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .stat-box {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            padding: 1rem;
            backdrop-filter: blur(8px);
            transition: border-color 0.3s, background 0.3s;
        }

        .stat-box:hover {
            border-color: #e8ff47;
            background: rgba(232,255,71,0.08);
        }

        .stat-num {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            color: #e8ff47;
            line-height: 1;
        }

        .stat-label {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            margin-top: 3px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── RIGHT PANEL ── */
        .right-panel {
            flex: 1;
            background: #0a0a0a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative; /* needed for ::after watermark */
        }

        /* ── LOGO WATERMARK ON RIGHT PANEL ── */
        /* To adjust: change background-size (line A) and opacity (line B) */
        .right-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background: url('fa_logo.png') center center no-repeat;
            background-size: 100%;   /* LINE A — increase = bigger, decrease = smaller */
            opacity: 0.5;          /* LINE B — increase = more visible, decrease = more subtle */
            pointer-events: none;
            z-index: 0;
        }

        .form-card {
            width: 100%;
            max-width: 420px;
            animation: fadeUp 0.5s ease both;
            position: relative; /* sits above the watermark */
            z-index: 1;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── TABS ── */
        .tabs {
            display: flex;
            border-bottom: 1px solid #222;
            margin-bottom: 2rem;
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            color: #444;
            cursor: pointer;
            border: none;
            background: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.2s;
        }

        .tab-btn.active { color: #e8ff47; border-bottom-color: #e8ff47; }
        .tab-btn:hover:not(.active) { color: #888; }

        /* ── FORMS ── */
        .form-section { display: none; }
        .form-section.active {
            display: block;
            animation: fadeIn 0.3s ease both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            color: #fff;
            letter-spacing: 1px;
            margin-bottom: 0.25rem;
        }

        .form-subtitle { font-size: 13px; color: #555; margin-bottom: 1.5rem; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .form-group { margin-bottom: 12px; }

        .form-group label {
            display: block;
            font-size: 11px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            color: #fff;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #e8ff47;
            box-shadow: 0 0 0 3px rgba(232,255,71,0.08);
        }

        .form-group input.error { border-color: #f44336; }
        .form-group select option { background: #1a1a1a; }

        input[type="password"]::-ms-reveal,
        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-credentials-auto-fill-button { display: none; }

        .input-wrap { position: relative; }
        .input-wrap input { padding-right: 40px; }

        .eye-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #555;
            font-size: 16px;
            user-select: none;
            transition: color 0.2s;
            line-height: 1;
        }

        .eye-toggle:hover { color: #888; }
        .eye-toggle.visible { color: #e8ff47; }

        .field-error { font-size: 11px; color: #f44336; margin-top: 4px; display: none; }
        .field-error.visible { display: block; }

        .submit-btn {
            width: 100%;
            background: #e8ff47;
            color: #0a0a0a;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            margin-top: 6px;
            transition: opacity 0.2s, transform 0.1s;
            letter-spacing: 0.5px;
        }

        .submit-btn:hover { opacity: 0.9; }
        .submit-btn:active { transform: scale(0.98); }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 1.25rem;
            animation: fadeIn 0.3s ease both;
        }

        .alert-success { background: #1a3a1a; color: #4caf50; border: 1px solid #2a5a2a; }
        .alert-error   { background: #3a1a1a; color: #f44336; border: 1px solid #5a2a2a; }

        .form-note { text-align: center; font-size: 12px; color: #444; margin-top: 1rem; }
        .form-note a { color: #e8ff47; cursor: pointer; text-decoration: none; }
        .form-note a:hover { text-decoration: underline; }

        .strength-bar { height: 3px; border-radius: 2px; background: #222; margin-top: 6px; overflow: hidden; }
        .strength-fill { height: 100%; width: 0%; border-radius: 2px; transition: width 0.3s, background 0.3s; }
        .strength-label { font-size: 11px; color: #555; margin-top: 3px; }
    </style>
</head>
<body>

<!-- LEFT PANEL WITH VIDEO -->
<div class="left-panel">
    <video class="left-video" autoplay muted loop playsinline>
        <source src="background_1.mp4" type="video/mp4">
    </video>
    <div class="left-overlay"></div>
    <div class="left-content">
        <div>
            <div class="logo">Fitness Academy</div>
            <div class="logo-sub">Membership Portal</div>
        </div>

        <div>
            <div class="tagline">Train.<br><em>Track.</em><br>Dominate.</div>
            <div class="tagline-sub">Your complete gym membership management system. Track attendance, manage plans, and stay on top of your fitness journey.</div>
        </div>

        <div class="stats-grid">
            <div class="stat-box"><div class="stat-num">3</div><div class="stat-label">Plan Options</div></div>
            <div class="stat-box"><div class="stat-num">24/7</div><div class="stat-label">Online Access</div></div>
            <div class="stat-box"><div class="stat-num">100%</div><div class="stat-label">Digital Records</div></div>
            <div class="stat-box"><div class="stat-num">0%</div><div class="stat-label">Paper Records</div></div>
        </div>
    </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
    <div class="form-card">

        <?php if ($msg !== ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" id="tab-signup" onclick="switchTab('signup')">Create Account</button>
            <button class="tab-btn" id="tab-login" onclick="switchTab('login')">Sign In</button>
        </div>

        <!-- SIGN UP FORM -->
        <div class="form-section active" id="form-signup">
            <div class="form-title">Join the Academy</div>
            <div class="form-subtitle">Start your fitness journey today</div>

            <form method="POST" action="signup.php" id="signupForm" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="first_name" placeholder="Juan" required>
                        <div class="field-error" id="err-first">Required</div>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="last_name" placeholder="Dela Cruz" required>
                        <div class="field-error" id="err-last">Required</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="signup_email" placeholder="juan@email.com" required>
                    <div class="field-error" id="err-email">Enter a valid email</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact" id="contact" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <input type="password" name="password" id="signup_password"
                               placeholder="Min. 8 characters" required
                               oninput="checkStrength(this.value)">
                        <span class="eye-toggle" onclick="togglePassword('signup_password', this)">👁</span>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                    <div class="strength-label" id="strength-label"></div>
                    <div class="field-error" id="err-password">Must be at least 8 characters</div>
                </div>

                <button type="submit" class="submit-btn" onclick="return validateSignup()">Create Account</button>
            </form>

            <div class="form-note">
                Already have an account? <a onclick="switchTab('login')">Sign in here</a>
            </div>
        </div>

        <!-- LOGIN FORM -->
        <div class="form-section" id="form-login">
            <div class="form-title">Welcome Back</div>
            <div class="form-subtitle">Sign in to your account</div>

            <form method="POST" action="login.php" id="loginForm" novalidate>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="login_email" placeholder="juan@email.com" required>
                    <div class="field-error" id="err-login-email">Enter a valid email</div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <input type="password" name="password" id="login_password"
                               placeholder="Your password" required>
                        <span class="eye-toggle" onclick="togglePassword('login_password', this)">👁</span>
                    </div>
                    <div class="field-error" id="err-login-password">Password is required</div>
                </div>

                <button type="submit" class="submit-btn" onclick="return validateLogin()">Sign In</button>
            </form>

            <div class="form-note">
                New here? <a onclick="switchTab('signup')">Create an account</a>
            </div>
        </div>

    </div>
</div>

<script>
    function switchTab(name) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        document.getElementById('form-' + name).classList.add('active');
    }

    <?php if ($msg_type === 'error' && isset($_GET['msg']) && ($_GET['msg'] === 'invalid' || $_GET['msg'] === 'deactivated')): ?>
        switchTab('login');
    <?php endif; ?>

    function togglePassword(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.add('visible');
        } else {
            input.type = 'password';
            icon.classList.remove('visible');
        }
    }

    function validateSignup() {
        let valid = true;
        document.querySelectorAll('.field-error').forEach(e => e.classList.remove('visible'));
        document.querySelectorAll('input').forEach(i => i.classList.remove('error'));
        const first    = document.getElementById('first_name');
        const last     = document.getElementById('last_name');
        const email    = document.getElementById('signup_email');
        const password = document.getElementById('signup_password');
        if (!first.value.trim())                              { showError('err-first',    'first_name');      valid = false; }
        if (!last.value.trim())                               { showError('err-last',     'last_name');       valid = false; }
        if (!email.value.trim() || !email.value.includes('@')){ showError('err-email',    'signup_email');    valid = false; }
        if (password.value.length < 8)                        { showError('err-password', 'signup_password'); valid = false; }
        return valid;
    }

    function validateLogin() {
        let valid = true;
        document.querySelectorAll('.field-error').forEach(e => e.classList.remove('visible'));
        document.querySelectorAll('input').forEach(i => i.classList.remove('error'));
        const email    = document.getElementById('login_email');
        const password = document.getElementById('login_password');
        if (!email.value.trim() || !email.value.includes('@')) { showError('err-login-email',    'login_email');    valid = false; }
        if (!password.value.trim())                            { showError('err-login-password', 'login_password'); valid = false; }
        return valid;
    }

    function showError(errId, inputId) {
        document.getElementById(errId).classList.add('visible');
        document.getElementById(inputId).classList.add('error');
    }

    function checkStrength(value) {
        const fill  = document.getElementById('strength-fill');
        const label = document.getElementById('strength-label');
        let strength = 0;
        if (value.length >= 8)          strength++;
        if (/[A-Z]/.test(value))        strength++;
        if (/[0-9]/.test(value))        strength++;
        if (/[^A-Za-z0-9]/.test(value)) strength++;
        const levels = [
            { width: '0%',   color: '#333',    text: '' },
            { width: '25%',  color: '#f44336', text: 'Weak' },
            { width: '50%',  color: '#ff9800', text: 'Fair' },
            { width: '75%',  color: '#e8ff47', text: 'Good' },
            { width: '100%', color: '#4caf50', text: 'Strong' },
        ];
        fill.style.width      = levels[strength].width;
        fill.style.background = levels[strength].color;
        label.style.color     = levels[strength].color;
        label.textContent     = levels[strength].text;
    }
</script>

</body>
</html>
