# GitHub Pipeline Manager

A simple PHP-based tool to create and manage GitHub webhook pipelines for automated deployments.

## Features

- Create multiple deployment pipelines with unique webhook URLs
- Support for different GitHub repositories and branches
- Secure webhook authentication with secret keys
- Test deployments from the web interface
- View deployment history and status

## Installation

1. Upload the two PHP files (`index.php` and `webhook.php`) to your web server
2. Create a `pipelines` directory in the same location and ensure it's writable by the web server:
   ```
   mkdir pipelines
   chmod 755 pipelines
   ```
3. Access the tool through your web browser (e.g., `https://yourdomain.com/pipeline-manager/`)

## Usage

### Creating a Pipeline

1. Fill out the form with the following information:
   - **Pipeline Name**: A unique identifier for your pipeline (letters, numbers, dashes, and underscores only)
   - **Repository Directory Path**: The absolute path on your server where the Git repository is located
   - **GitHub Access Token**: Your GitHub Personal Access Token (PAT) with repo access
   - **Branch Name**: The branch to pull from (e.g., master, main, develop)
   - **GitHub Username**: Your GitHub username or organization name
   - **GitHub Repository Name**: The name of the repository (without username)

2. Click "Create Pipeline"

### Setting Up GitHub Webhook

1. Go to your GitHub repository
2. Navigate to Settings > Webhooks > Add webhook
3. Set the Payload URL to the webhook URL provided by the pipeline manager
4. Set Content type to `application/json`
5. Enter the Secret key provided by the pipeline manager
6. Select "Just the push event"
7. Ensure "Active" is checked
8. Click "Add webhook"

### Testing a Pipeline

Click the "Test Pipeline" button to manually trigger a deployment without waiting for a GitHub webhook.

## Security Considerations

- Keep your pipeline configuration directory (`pipelines`) secure and not publicly accessible
- Use HTTPS for all webhook URLs
- Always use secret keys for webhook authentication
- Restrict access to the pipeline manager interface using server authentication

## Troubleshooting

If you encounter issues with deployments:

1. Check that the repository path is correct and accessible by the web server user
2. Verify that your GitHub token has the necessary permissions
3. Ensure the web server user has permission to execute git commands
4. Check the webhook delivery logs in GitHub for any errors