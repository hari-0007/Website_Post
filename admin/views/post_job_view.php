<?php

// admin/views/post_job_view.php - Displays the form for posting a new job

// This file is included by dashboard.php when $requestedView is 'post_job'.
// It assumes $formData is available (for pre-filling on validation errors).

?>
<style>
    /* General Page Styles */
    #postJobPage {
        max-width: 500px;
        margin: 30px auto;
        background: #fff;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    #postJobPage h1 {
        text-align: center;
        font-size: 1.5rem;
        color: #333;
        margin-bottom: 15px;
    }

    #postJobPage form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    #postJobPage label {
        font-weight: 600;
        color: #444;
        font-size: 0.9rem;
        margin-bottom: 3px;
    }

    #postJobPage input[type="text"],
    #postJobPage input[type="email"],
    #postJobPage input[type="number"],
    #postJobPage textarea,
    #postJobPage select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 0.85rem;
        color: #333;
        background-color: #f9f9f9;
        transition: border-color 0.3s ease;
    }

    #postJobPage input[type="text"]:focus,
    #postJobPage input[type="email"]:focus,
    #postJobPage input[type="number"]:focus,
    #postJobPage textarea:focus,
    #postJobPage select:focus {
        border-color: #007bff;
        outline: none;
        background-color: #fff;
    }

    #postJobPage textarea {
        resize: vertical;
        min-height: 80px;
    }

    #postJobPage button {
        padding: 8px 12px;
        background-color: #007bff;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    #postJobPage button:hover {
        background-color: #0056b3;
        transform: translateY(-1px);
    }

    #postJobPage button:active {
        background-color: #004085;
        transform: translateY(0);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        #postJobPage {
            margin: 15px;
            padding: 10px;
        }

        #postJobPage form {
            gap: 8px;
        }

        #postJobPage button {
            font-size: 0.85rem;
            padding: 6px 10px;
        }
    }
</style>

<div id="postJobPage" class="post-job-container">
    <form method="POST" action="post_jobs.php">
        <label for="title">Job Title:</label>
        <input type="text" id="title" name="title" required>

        <label for="company">Company:</label>
        <input type="text" id="company" name="company" required>

        <label for="location">Location:</label>
        <input type="text" id="location" name="location" required>

        <label for="description">Description:</label>
        <textarea id="description" name="description" required></textarea>

        <label for="experience">Experience:</label>
        <select id="experience" name="experience" required>
            <option value="0">Select Experience</option>
            <option value="Fresher">Fresher</option>
            <option value="Internship">Internship</option>
            <option value="1">1 Year</option>
            <option value="2">2 Years</option>
            <option value="3">3 Years</option>
            <option value="4">4 Years</option>
            <option value="5">5 Years</option>
            <option value="7">7 Years</option>
            <option value="8">8 Years</option>
            <option value="9">9 Years</option>
            <option value="10">10 Years</option>
            <option value="15+">15+ Years</option>
        </select>

        <label for="salary">Salary:</label>
        <input type="text" id="salary" name="salary" value="0" placeholder="e.g., $50,000 - $60,000 per year" required>

        <label for="phones">Phone:</label>
        <input type="text" id="phones" name="phones" required>

        <label for="emails">Email:</label>
        <input type="email" id="emails" name="emails" required>

        <label for="vacant_positions">Vacant Positions:</label>
        <input type="number" id="vacant_positions" name="vacant_positions" value="1" min="1" required>

        <button type="submit">Post Job</button>
    </form>
</div>
