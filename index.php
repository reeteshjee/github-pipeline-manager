<?php
// index.php - Main interface for pipeline management
session_start();
$message = '';

// Configuration
$dataDir = 'pipelines';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_pipeline') {
        $pipelineName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['pipeline_name']);
        
        if (empty($pipelineName)) {
            $message = '<div class="alert alert-danger">Pipeline name cannot be empty and must contain only alphanumeric characters, dashes, and underscores.</div>';
        } else {
            $pipelineData = [
                'name' => $pipelineName,
                'repo_directory' => $_POST['repo_directory'],
                'github_token' => $_POST['github_token'],
                'branch_name' => $_POST['branch_name'],
                'github_username' => $_POST['github_username'],
                'github_repo' => $_POST['github_repo'],
                'created_at' => date('Y-m-d H:i:s'),
                'last_run' => null,
                'secret_key' => bin2hex(random_bytes(16)) // Generate a unique secret key for webhook authentication
            ];
            
            $pipelineFilePath = "{$dataDir}/{$pipelineName}.json";

            if (file_exists($pipelineFilePath)) {
                $message = '<div class="alert alert-danger">Pipeline with this name already exists.</div>';
            } else {
                file_put_contents($pipelineFilePath, json_encode($pipelineData, JSON_PRETTY_PRINT));
                $message = '<div class="alert alert-success">Pipeline created successfully! Webhook URL: ' . getWebhookUrl($pipelineName) . '</div>';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_pipeline') {
        $pipelineName = $_POST['pipeline_name'];
        $pipelineFilePath = "{$dataDir}/{$pipelineName}.json";
        
        if (file_exists($pipelineFilePath)) {
            unlink($pipelineFilePath);
            $message = '<div class="alert alert-success">Pipeline deleted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Pipeline not found.</div>';
        }
    }
}

// Function to get all pipelines
function getPipelines() {
    global $dataDir;
    $pipelines = [];
    
    if ($handle = opendir($dataDir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != ".." && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $pipelineData = json_decode(file_get_contents("{$dataDir}/{$file}"), true);
                $pipelines[] = $pipelineData;
            }
        }
        closedir($handle);
    }
    
    return $pipelines;
}

// Function to generate webhook URL
function getWebhookUrl($pipelineName) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    
    return "{$protocol}://{$host}{$path}/webhook.php?pipeline={$pipelineName}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Pipeline Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            padding-top: 20px;
            padding-bottom: 20px;
            background-color: #f8f9fa;
        }
        .pipeline-card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .pipeline-info {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .webhook-url {
            word-break: break-all;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-family: monospace;
        }
        .token-display {
            font-family: monospace;
            font-size: 14px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-center">GitHub Pipeline Manager</h1>
                <p class="text-center text-muted">Create and manage GitHub webhook pipelines to automatically update your repositories</p>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="row">
                <div class="col-12">
                    <?php echo $message; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        Create New Pipeline
                    </div>
                    <div class="card-body">
                        <form id="pipeline-form" method="post">
                            <input type="hidden" name="action" value="create_pipeline">
                            
                            <div class="mb-3">
                                <label for="pipeline_name" class="form-label">Pipeline Name</label>
                                <input type="text" class="form-control" id="pipeline_name" name="pipeline_name" required placeholder="my-awesome-project">
                                <div class="form-text">Use only letters, numbers, dashes, and underscores.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="repo_directory" class="form-label">Repository Directory Path</label>
                                <input type="text" class="form-control" id="repo_directory" name="repo_directory" required placeholder="/path/to/repository">
                                <div class="form-text">Absolute path to the repository on your server.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="github_token" class="form-label">GitHub Access Token</label>
                                <input type="password" class="form-control" id="github_token" name="github_token" required placeholder="ghp_123456789abcdef">
                                <div class="form-text">Personal Access Token for GitHub authentication.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="branch_name" class="form-label">Branch Name</label>
                                <input type="text" class="form-control" id="branch_name" name="branch_name" required value="main">
                                <div class="form-text">Branch to pull from (e.g., master, main, dev).</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="github_username" class="form-label">GitHub Username</label>
                                <input type="text" class="form-control" id="github_username" name="github_username" required placeholder="octocat">
                            </div>
                            
                            <div class="mb-3">
                                <label for="github_repo" class="form-label">GitHub Repository Name</label>
                                <input type="text" class="form-control" id="github_repo" name="github_repo" required placeholder="my-repo">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Create Pipeline</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-7">
                <h3>Existing Pipelines</h3>
                
                <?php 
                $pipelines = getPipelines();
                if (empty($pipelines)): 
                ?>
                <div class="alert alert-info">No pipelines created yet. Create your first pipeline using the form.</div>
                <?php 
                else:
                    foreach ($pipelines as $pipeline): 
                ?>
                <div class="card pipeline-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><?php echo htmlspecialchars($pipeline['name']); ?></span>
                        <form method="post" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this pipeline?');">
                            <input type="hidden" name="action" value="delete_pipeline">
                            <input type="hidden" name="pipeline_name" value="<?php echo htmlspecialchars($pipeline['name']); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="pipeline-info"><strong>Repository:</strong> <?php echo htmlspecialchars($pipeline['github_username'] . '/' . $pipeline['github_repo']); ?></div>
                        <div class="pipeline-info"><strong>Local Directory:</strong> <?php echo htmlspecialchars($pipeline['repo_directory']); ?></div>
                        <div class="pipeline-info"><strong>Branch:</strong> <?php echo htmlspecialchars($pipeline['branch_name']); ?></div>
                        <div class="pipeline-info"><strong>Created:</strong> <?php echo htmlspecialchars($pipeline['created_at']); ?></div>
                        <?php if (!empty($pipeline['last_run'])): ?>
                        <div class="pipeline-info"><strong>Last Run:</strong> <?php echo htmlspecialchars($pipeline['last_run']); ?></div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <strong>Webhook URL:</strong>
                            <div class="webhook-url mt-1"><?php echo getWebhookUrl($pipeline['name']); ?></div>
                            <div class="form-text">Use this URL in your GitHub repository webhook settings.</div>
                        </div>
                        
                        <div class="mt-3">
                            <strong>Webhook Secret:</strong>
                            <div class="token-display"><?php echo htmlspecialchars($pipeline['secret_key']); ?></div>
                            <div class="form-text">Use this secret key in your GitHub webhook configuration for additional security.</div>
                        </div>
                        
                        <div class="mt-3">
                            <a href="webhook.php?pipeline=<?php echo urlencode($pipeline['name']); ?>&test=true" class="btn btn-sm btn-success" target="_blank">Test Pipeline</a>
                        </div>
                    </div>
                </div>
                <?php 
                    endforeach;
                endif; 
                ?>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Simple form validation
        $("#pipeline-form").submit(function(e) {
            var pipelineName = $("#pipeline_name").val();
            if(!/^[a-zA-Z0-9_-]+$/.test(pipelineName)) {
                alert("Pipeline name can only contain letters, numbers, dashes, and underscores.");
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>