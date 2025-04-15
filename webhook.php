<?php
// webhook.php - Processes GitHub webhook requests
header('Content-Type: text/plain');

// Configuration
$dataDir = 'pipelines';

// Get pipeline name from URL parameter
$pipelineName = isset($_GET['pipeline']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['pipeline']) : '';
$isTest = isset($_GET['test']) && $_GET['test'] === 'true';

// Validate pipeline name
if (empty($pipelineName)) {
    http_response_code(400);
    echo "Error: Pipeline name is required.\n";
    exit;
}

// Check if pipeline exists
$pipelineFilePath = "{$dataDir}/{$pipelineName}.json";
if (!file_exists($pipelineFilePath)) {
    http_response_code(404);
    echo "Error: Pipeline '{$pipelineName}' not found.\n";
    exit;
}

// Load pipeline configuration
$pipeline = json_decode(file_get_contents($pipelineFilePath), true);
if (!$pipeline) {
    http_response_code(500);
    echo "Error: Failed to load pipeline configuration.\n";
    exit;
}

// Test mode - just display pipeline info
if ($isTest) {
    echo "TEST MODE - Pipeline Details\n";
    echo "==========================\n";
    echo "Name: {$pipeline['name']}\n";
    echo "Repository: {$pipeline['github_username']}/{$pipeline['github_repo']}\n";
    echo "Branch: {$pipeline['branch_name']}\n";
    echo "Directory: {$pipeline['repo_directory']}\n";
    echo "==========================\n\n";
    
    echo "Running test deployment...\n\n";
    
    // Execute git pull
    $result = pullFromGitHub($pipeline);
    echo $result;
    
    // Update last run timestamp
    $pipeline['last_run'] = date('Y-m-d H:i:s');
    file_put_contents($pipelineFilePath, json_encode($pipeline, JSON_PRETTY_PRINT));
    
    exit;
}

// Process webhook
$githubEvent = isset($_SERVER['HTTP_X_GITHUB_EVENT']) ? $_SERVER['HTTP_X_GITHUB_EVENT'] : '';
$signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';

// Get request body
$payload = file_get_contents('php://input');
$payloadJson = json_decode($payload, true);

// Verify secret if provided (recommended for production)
if (!empty($pipeline['secret_key']) && !empty($signature)) {
    $computedSignature = 'sha256=' . hash_hmac('sha256', $payload, $pipeline['secret_key']);
    if (!hash_equals($signature, $computedSignature)) {
        http_response_code(401);
        echo "Error: Invalid signature.\n";
        exit;
    }
}

// Check if this is a push event
if ($githubEvent !== 'push' && !empty($githubEvent)) {
    echo "Ignored event: {$githubEvent}\n";
    exit;
}

// Check if the push is to the configured branch
$ref = isset($payloadJson['ref']) ? $payloadJson['ref'] : '';
$expectedRef = "refs/heads/{$pipeline['branch_name']}";

if ($ref !== $expectedRef && !empty($ref)) {
    echo "Ignored push to branch {$ref} (configured for {$expectedRef})\n";
    exit;
}

// Execute git pull
$result = pullFromGitHub($pipeline);
echo $result;

// Update last run timestamp
$pipeline['last_run'] = date('Y-m-d H:i:s');
file_put_contents($pipelineFilePath, json_encode($pipeline, JSON_PRETTY_PRINT));

/**
 * Executes git pull for the specified pipeline
 */
function pullFromGitHub($pipeline) {
    // Validate that directory exists
    if (!file_exists($pipeline['repo_directory'])) {
        return "Error: Repository directory '{$pipeline['repo_directory']}' does not exist.\n";
    }
    
    // Format GitHub repository URL with token
    $githubRepoUrl = "https://{$pipeline['github_token']}:x-oauth-basic@github.com/{$pipeline['github_username']}/{$pipeline['github_repo']}.git";
    
    // Change to the repository directory
    $originalDir = getcwd();
    if (!chdir($pipeline['repo_directory'])) {
        return "Error: Failed to change directory to '{$pipeline['repo_directory']}'.\n";
    }
    
    // Run git pull command
    $branch = escapeshellarg($pipeline['branch_name']);
    $command = "git pull " . escapeshellarg($githubRepoUrl) . " {$branch}";
    echo $command;die;
    
    // Execute command and capture output
    exec($command . " 2>&1", $output, $returnCode);
    $outputText = implode("\n", $output);
    
    // Return to original directory
    chdir($originalDir);
    
    // Format response
    $timestamp = date('Y-m-d H:i:s');
    $status = $returnCode === 0 ? "SUCCESS" : "FAILED";
    
    return "==== Deployment Log: {$timestamp} ====\n" .
           "Status: {$status}\n" .
           "Output:\n{$outputText}\n" .
           "====================================\n";
}
?>