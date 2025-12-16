<?php
require_once '_init.php';

// Must be admin to view this page
if (!isset($_SESSION['user_id_admin'])) {
    echo '<h2>Not logged in as admin</h2>';
    echo '<p>Please log in as an admin to access the expenses report.</p>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Expenses Page Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .debug-box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .success { border-left-color: #28a745; color: #28a745; }
        .error { border-left-color: #dc3545; color: #dc3545; }
        .warning { border-left-color: #ffc107; color: #ffc107; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Expenses Report - Debug Info</h1>
    
    <div class="debug-box success">
        <h3>✓ Authentication</h3>
        <p>You are logged in as admin (User ID: <?= $_SESSION['user_id_admin'] ?>)</p>
    </div>
    
    <div class="debug-box">
        <h3>Database Status</h3>
        <?php
        global $connection;
        
        // Check if we can connect
        try {
            $stmt = $connection->prepare("SELECT COUNT(*) as total FROM orders");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<div class="success">✓ Database connected. Orders found: ' . $result['total'] . '</div>';
        } catch (Exception $e) {
            echo '<div class="error">✗ Database error: ' . $e->getMessage() . '</div>';
        }
        ?>
    </div>
    
    <div class="debug-box">
        <h3>API Endpoint Test</h3>
        <p>Try calling the API directly:</p>
        <pre><code>POST /POS_SYSTEM/api/expenses_report.php
Content-Type: application/json

{
  "start_date": "2025-12-01",
  "end_date": "2025-12-15",
  "category_id": null
}</code></pre>
        <button onclick="testAPI()">Test API Now</button>
        <div id="apiResult" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 3px; display: none;"></div>
    </div>
    
    <div class="debug-box">
        <h3>Links</h3>
        <ul>
            <li><a href="admin_expenses.php">Go to Expenses Report</a></li>
            <li><a href="admin_dashboard.php">Go to Admin Dashboard</a></li>
            <li><a href="test_expenses_data.php">View Database Data</a></li>
        </ul>
    </div>
    
    <script>
        function testAPI() {
            const resultDiv = document.getElementById('apiResult');
            resultDiv.innerHTML = '<p style="color: #666;">Testing API...</p>';
            resultDiv.style.display = 'block';
            
            fetch('api/expenses_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    start_date: '2025-12-01',
                    end_date: '2025-12-15',
                    category_id: null
                }),
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                console.log('API Response:', data);
                if (data.error) {
                    resultDiv.innerHTML = '<p style="color: #dc3545;"><strong>Error:</strong> ' + data.error + '</p>';
                } else {
                    resultDiv.innerHTML = `
                        <p style="color: #28a745;"><strong>✓ API Working!</strong></p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<p style="color: #dc3545;"><strong>Error:</strong> ' + error.message + '</p>';
            });
        }
    </script>
</body>
</html>
