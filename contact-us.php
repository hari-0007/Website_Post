<?php
// session_start(); // If needed
// require_once 'admin/includes/config.php'; // If you need $feedbackFilename for a form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us - UAE Job Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="path/to/your/main_styles.css">
    <style>
        .content-section { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .content-section h1 { color: #005fa3; }
        .content-section p, .content-section li { line-height: 1.6; }
        .contact-form label { display: block; margin-top: 10px; font-weight: bold; }
        .contact-form input[type="text"], .contact-form input[type="email"], .contact-form textarea {
            width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .contact-form button { background-color: #005fa3; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; }
        .contact-form button:hover { background-color: #004a80; }
        .feedback-message { margin-top: 10px; padding: 10px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content-section">
            <h1>Contact Us</h1>
            <p>We'd love to hear from you! Whether you have a question, feedback, or need support, please feel free to reach out to us through one of the methods below.</p>

            <h2>General Inquiries & Support</h2>
            <p>For general questions, technical support, or feedback about the website, please email us at:</p>
            <p><strong>Email:</strong> <a href="mailto:support@yourjobportaldomain.com">support@yourjobportaldomain.com</a></p> <?php // Replace with your actual support email ?>

            <h2>Employers & Job Posters</h2>
            <p>If you are an employer looking to post jobs or have questions about our services for businesses, please contact:</p>
            <p><strong>Email:</strong> <a href="mailto:employers@yourjobportaldomain.com">employers@yourjobportaldomain.com</a></p> <?php // Replace with your actual employer contact email ?>
            
            <h2>Contact Form</h2>
            <p>Alternatively, you can use the form below to send us a message directly:</p>
            <form id="contactPageFeedbackForm" class="contact-form">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>

                <label for="message">Message:</label>
                <textarea id="message" name="message" rows="5" required></textarea>

                <div id="contactResponseMsg" class="feedback-message" style="display: none;"></div>
                <button type="submit">Send Message</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('contactPageFeedbackForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const responseBox = document.getElementById('contactResponseMsg');

            fetch('feedback.php', { // Assuming your feedback.php script can handle this
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                responseBox.innerText = data.message;
                responseBox.className = 'feedback-message ' + (data.success ? 'success' : 'error');
                responseBox.style.display = 'block';
                if (data.success) {
                    form.reset();
                }
                setTimeout(() => { responseBox.style.display = 'none'; }, 5000);
            })
            .catch(err => {
                console.error("Feedback form error:", err);
                responseBox.innerText = 'An error occurred. Please try again.';
                responseBox.className = 'feedback-message error';
                responseBox.style.display = 'block';
                setTimeout(() => { responseBox.style.display = 'none'; }, 5000);
            });
        });
    </script>
</body>
</html>
