<?php
// c:\Users\Public\Job_Post\data\git_config.php
// Store Git configuration.
// IMPORTANT: Ensure this file is NOT publicly accessible if it contains sensitive tokens.
// Add this file to .gitignore if it contains a real Personal Access Token.

return [
    'repository_url' => '', // e.g., https://username:YOUR_PAT@github.com/username/repo.git OR git@github.com:username/repo.git
    'branch_name' => 'main',
    'git_username' => '', // Optional: For display or if needed by scripts
    'git_email' => '',    // Optional: For git commit config
    // For PAT authentication, the PAT is usually part of the repository_url.
    // For SSH, ensure the server's SSH key is configured with GitHub.
];
?>
