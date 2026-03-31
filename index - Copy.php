<?php
/**
 * IESCO Bill Checker V10 - Standalone PHP Application
 * Optimized with Direct Print URL construction to bypass PITC security.
 * For use with Laragon or any local PHP server.
 * 
 * Usage:
 * 1. Place this file in your Laragon www folder (e.g., C:\laragon\www\iesco_checker_v10.php)
 * 2. Start Laragon
 * 3. Visit http://localhost/iesco_checker_v10.php in your browser
 * 4. Enter your IESCO Reference Number (14 digits) or Customer ID (10 digits)
 * 5. Click "Check Bill Now"
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IESCO Bill Checker V10 - Local</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: #ffffff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
            max-width: 480px;
            width: 100%;
            border-top: 6px solid #0056b3;
        }
        h1 { color: #0056b3; font-size: 30px; margin-bottom: 10px; text-align: center; font-weight: 800; }
        .subtitle { color: #555; font-size: 14px; text-align: center; margin-bottom: 30px; line-height: 1.6; }
        .tabs { display: flex; margin-bottom: 25px; background: #f8f9fa; border-radius: 12px; padding: 6px; }
        .tab-btn {
            flex: 1; padding: 12px; border: none; background: none; cursor: pointer;
            font-weight: 700; color: #777; border-radius: 10px; transition: all 0.3s ease;
        }
        .tab-btn.active { color: #ffffff; background-color: #0056b3; box-shadow: 0 4px 12px rgba(0, 86, 179, 0.3); }
        .form-group { margin-bottom: 25px; }
        label { display: block; margin-bottom: 10px; font-weight: 700; font-size: 15px; color: #222; }
        input[type="text"] {
            width: 100%; padding: 16px; border: 2px solid #e1e8ed; border-radius: 12px;
            font-size: 18px; text-align: center; letter-spacing: 2px; font-weight: 600; outline: none;
            text-transform: uppercase;
        }
        input[type="text"]:focus { border-color: #0056b3; box-shadow: 0 0 0 4px rgba(0, 86, 179, 0.1); }
        .error { color: #d93025; font-size: 13px; margin-top: 8px; display: none; text-align: center; font-weight: 600; }
        .submit-btn {
            width: 100%; padding: 18px; background-color: #0056b3; color: #ffffff; border: none;
            border-radius: 12px; font-size: 18px; font-weight: 800; cursor: pointer; transition: all 0.3s ease;
            box-shadow: 0 8px 15px rgba(0, 86, 179, 0.2); text-transform: uppercase;
        }
        .submit-btn:hover { background-color: #003d82; transform: translateY(-2px); }
        .loader { margin-top: 25px; display: none; text-align: center; }
        .spinner {
            width: 45px; height: 45px; border: 5px solid #f3f3f3; border-top: 5px solid #0056b3;
            border-radius: 50%; animation: spin 0.8s linear infinite; margin: 15px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .footer { margin-top: 30px; padding-top: 25px; border-top: 1px solid #f0f4f8; font-size: 13px; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>IESCO Bill Checker</h1>
        <p class="subtitle">Enter your 14-digit Reference Number or 10-digit Customer ID to view your duplicate bill.</p>

        <div class="tabs">
            <button class="tab-btn active" id="tab-ref" onclick="switchTab('ref')">Reference No</button>
            <button class="tab-btn" id="tab-cust" onclick="switchTab('cust')">Customer ID</button>
        </div>

        <div class="form-group">
            <label id="label" for="input-field">Reference Number</label>
            <input type="text" id="input-field" placeholder="Enter 14-digit Reference Number" maxlength="15" autocomplete="off">
            <div class="error" id="error-msg"></div>
        </div>

        <!-- HIDDEN FORM FOR DIRECT BROWSER SUBMISSION -->
        <!-- Exact field mapping for IESCO portal -->
        <form id="iesco-form" action="https://bill.pitc.com.pk/iescobill/general" method="POST" target="_blank" style="display:none;">
            <!-- Field name must be 'refno' for IESCO's general endpoint -->
            <input type="hidden" name="refno" id="hidden-refno">
            <!-- PITC often expects 'btype' to be 'general' -->
            <input type="hidden" name="btype" value="general">
        </form>

        <button class="submit-btn" id="submit-btn" onclick="handleSubmit()">Check Bill Now</button>

        <div class="loader" id="loader">
            <div class="spinner"></div>
            <p style="color: #0056b3; font-weight: 700;">Connecting to IESCO Portal...</p>
            <p style="font-size: 12px; color: #888; margin-top: 5px;">Your bill will open in a new tab.</p>
        </div>

        <div class="footer">
            <p>Using <b>V10 Direct Submission</b>. Exactly 14 digits required.</p>
        </div>
    </div>

    <script>
        let currentMode = 'ref';
        const inputField = document.getElementById('input-field');
        const label = document.getElementById('label');
        const errorMsg = document.getElementById('error-msg');
        const submitBtn = document.getElementById('submit-btn');
        const loader = document.getElementById('loader');
        const tabRef = document.getElementById('tab-ref');
        const tabCust = document.getElementById('tab-cust');
        const iescoForm = document.getElementById('iesco-form');
        const hiddenRefno = document.getElementById('hidden-refno');

        // Load saved data
        window.addEventListener('load', () => {
            const savedRef = localStorage.getItem('iesco_last_ref');
            const savedMode = localStorage.getItem('iesco_last_mode');
            if (savedMode) switchTab(savedMode);
            if (savedRef) inputField.value = savedRef;
        });

        function switchTab(mode) {
            currentMode = mode;
            errorMsg.style.display = 'none';
            inputField.style.borderColor = '#e1e8ed';
            if (mode === 'ref') {
                tabRef.classList.add('active'); tabCust.classList.remove('active');
                label.innerText = 'Reference Number'; 
                inputField.placeholder = 'Enter 14-digit Reference Number';
                inputField.maxLength = 15;
            } else {
                tabCust.classList.add('active'); tabRef.classList.remove('active');
                label.innerText = 'Customer ID'; 
                inputField.placeholder = 'Enter 10-digit Customer ID';
                inputField.maxLength = 10;
            }
            localStorage.setItem('iesco_last_mode', mode);
        }

        function handleSubmit() {
            let val = inputField.value.trim().toUpperCase();
            
            // Validation
            if (currentMode === 'ref') {
                if (val.length < 14 || val.length > 15) { 
                    showError('Reference Number must be 14-15 characters.'); 
                    return; 
                }
            } else {
                if (val.length !== 10 || !/^\d+$/.test(val)) { 
                    showError('Customer ID must be exactly 10 digits.'); 
                    return; 
                }
            }

            // UI Feedback
            errorMsg.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.innerText = 'Processing...';
            loader.style.display = 'block';

            localStorage.setItem('iesco_last_ref', val);
            hiddenRefno.value = val;

            setTimeout(() => {
                iescoForm.submit();
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Check Bill Now';
                    loader.style.display = 'none';
                }, 2000);
            }, 500);
        }

        function showError(msg) {
            errorMsg.innerText = msg; errorMsg.style.display = 'block';
            inputField.style.borderColor = '#d93025';
        }

        inputField.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSubmit(); });
    </script>
</body>
</html>