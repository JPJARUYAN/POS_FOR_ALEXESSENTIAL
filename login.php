<?php 

//Guard
require_once '_guards.php';
// Allow the login form to be accessed even when another role is authenticated
// so a user can sign in as both ADMIN and CASHIER in the same browser.
// Previously `Guard::guestOnly()` redirected to the current user's home,
// preventing signing in to an additional role. We intentionally skip that
// redirect to support concurrent role sessions.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System of Alex Essential - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0f1a 0%, #1a1f35 100%);
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100vh;
            width: 100%;
            overflow: hidden;
        }

        .login-container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 50px 45px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6);
        }

        .login-header {
            text-align: center;
            margin-bottom: 45px;
        }

        .logo-emoji {
            font-size: 3.5em;
            margin-bottom: 20px;
            display: block;
        }

        .login-title {
            font-size: 2em;
            font-weight: 800;
            color: #e2e8f0;
            margin-bottom: 10px;
        }

        .login-brand {
            font-size: 1.2em;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 1em;
            color: #94a3b8;
            font-weight: 500;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-label {
            font-size: 0.9em;
            font-weight: 600;
            color: #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: #1e293b;
            border: 2px solid #334155;
            border-radius: 10px;
            color: #e2e8f0;
            font-size: 0.95em;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }

        .form-input::placeholder {
            color: #94a3b8;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 12px;
            padding-right: 40px;
            cursor: pointer;
        }

        .form-select option {
            background: #1e293b;
            color: #e2e8f0;
        }

        .login-btn {
            padding: 15px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #334155;
            text-align: center;
        }

        .login-footer-text {
            font-size: 0.85em;
            color: #94a3b8;
            margin: 0;
        }

        .feedback-error {
            padding: 10px 12px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .flash-message {
            padding: 12px 14px;
            margin-bottom: 16px;
            border-radius: 8px;
            font-weight: 500;
        }

        .flash-message.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        .flash-message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        @media (max-width: 600px) {
            .login-card {
                padding: 40px 30px;
            }

            .logo-emoji {
                font-size: 2.5em;
            }

            .login-title {
                font-size: 1.5em;
            }

            .login-brand {
                font-size: 1em;
            }

            .form-input {
                padding: 12px 14px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header Section -->
            <div class="login-header">
                <div class="logo-emoji">🏪</div>
                <h1 class="login-title">POS System</h1>
                <p class="login-brand">Alex Essential</p>
                <p class="login-subtitle">Secure Login Portal</p>
            </div>

            <!-- Form Section -->
            <form method="POST" action="api/login_controller.php" class="login-form">
                <?php displayFlashMessage('login') ?>

                <!-- Email Input -->
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input 
                        id="email"
                        type="email" 
                        name="email" 
                        placeholder="Enter your email" 
                        required
                        class="form-input"
                    />
                    <div id="email-feedback" class="feedback-error" style="display:none;">Unknown user</div>
                </div>

                <!-- Password Input -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        id="password"
                        type="password" 
                        name="password" 
                        placeholder="Enter your password" 
                        required
                        class="form-input"
                    />
                </div>

                <!-- User Type Selector -->
                <div class="form-group">
                    <label for="role-select" class="form-label">Login As</label>
                    <select id="role-select" name="role" required class="form-input form-select">
                        <option value="">-- Select your role --</option>
                        <option value="ADMIN">Administrator</option>
                        <option value="CASHIER">Cashier</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <button class="login-btn" type="submit">Sign In</button>
            </form>

            <!-- Footer -->
            <div class="login-footer">
                <p class="login-footer-text">Need help? Contact admin if you don't have credentials</p>
            </div>
        </div>
    </div>

</body>
</html>

<script>
// Auto-select role when email exists in DB
(function(){
    const emailInput = document.querySelector('input[name="email"]');
    const roleSelect = document.getElementById('role-select');
    const emailFeedback = document.getElementById('email-feedback');
    let timeout = null;

    if (!emailInput || !roleSelect || !emailFeedback) return;

    function lookupRole(email) {
        if (!email) {
            emailFeedback.style.display = 'none';
            roleSelect.value = '';
            return;
        }
        fetch(`api/get_user_role.php?email=${encodeURIComponent(email)}`)
            .then(r => r.json())
            .then(data => {
                if (data && data.role) {
                    roleSelect.value = data.role;
                    emailFeedback.style.display = 'none';
                } else {
                    roleSelect.value = '';
                    emailFeedback.style.display = 'block';
                }
            }).catch(()=>{
                roleSelect.value = '';
                emailFeedback.style.display = 'none';
            });
    }

    // Debounce input so we don't spam server
    emailInput.addEventListener('input', function(){
        clearTimeout(timeout);
        timeout = setTimeout(()=> lookupRole(emailInput.value.trim()), 400);
    });

    // Also lookup on blur
    emailInput.addEventListener('blur', function(){
        lookupRole(emailInput.value.trim());
    });
})();
</script>


</html>