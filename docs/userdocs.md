# MChef Technical Documentation

## Overview

MChef is a sophisticated command-line tool designed to automate the creation and management of Moodle development environments using Docker containers. It provides a comprehensive solution for Moodle plugin development, testing, and deployment workflows.

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Core Components](#core-components)
- [Service Layer](#service-layer)
- [Command System](#command-system)
- [Model Layer](#model-layer)
- [Docker Integration](#docker-integration)
- [Plugin Management](#plugin-management)
- [Configuration System](#configuration-system)
- [Development Workflow](#development-workflow)
- [Error Handling](#error-handling)
- [Security Considerations](#security-considerations)
- [Performance Optimization](#performance-optimization)
- [Extensibility](#extensibility)

## Architecture Overview

MChef follows a layered architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────┐
│                     User Interface Layer                    │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐      │
│  │CLI Commands │    │  Main Entry │    │  Error      │      │
│  │             │    │  Point      │    │  Handling   │      │
│  └─────────────┘    └─────────────┘    └─────────────┘      │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                     Service Layer                           │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐      │
│  │  Main       │    │  Docker     │    │  Plugins    │      │
│  │  Service    │    │  Service    │    │  Service    │      │
│  └─────────────┘    └─────────────┘    └─────────────┘      │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                     Model Layer                             │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐      │
│  │  Recipe     │    │  Plugin     │    │  Docker     │      │
│  │  Model      │    │  Models     │    │  Models     │      │
│  └─────────────┘    └─────────────┘    └─────────────┘      │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                     Infrastructure Layer                    │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐      │
│  │  Docker     │    │  File       │    │  Template   │      │
│  │  Engine     │    │  System     │    │  Engine     │      │
│  └─────────────┘    └─────────────┘    └─────────────┘      │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### 1. Entry Point (mchef.php)

The main entry point `mchef.php` is responsible for:

- **Dependency Management**: Checks for required dependencies (PHP, Docker, etc.)
- **Command Registration**: Dynamically registers all available commands
- **Option Parsing**: Handles command-line arguments and options
- **Service Initialization**: Sets up core services and dependencies
- **Command Routing**: Routes commands to appropriate handlers

Key features:
- Uses `splitbrain/phpcli` library for CLI interface
- Implements singleton pattern for service management
- Provides interactive prompts for user confirmation
- Handles version information and help display

### 2. Main Service (src/Service/Main.php)

The `Main` service is the core orchestration layer that handles:

- **Recipe Parsing**: Validates and processes recipe configuration files
- **Docker Configuration**: Generates Dockerfiles and compose files
- **Container Management**: Starts, stops, and manages container lifecycle
- **Network Configuration**: Sets up Docker networks for container communication
- **Asset Generation**: Creates configuration files and scripts
- **Database Management**: Handles Moodle database installation and setup
- **Host Configuration**: Updates system hosts file for custom domains

Key methods:
- `create()`: Main workflow for creating a new Moodle environment
- `startContainers()`: Starts all containers defined in the recipe
- `stopContainers()`: Stops all running containers
- `configureDockerNetwork()`: Sets up network connectivity
- `updateHostHosts()`: Manages system host file entries
- `populateAssets()`: Generates configuration files using Twig templates

## Service Layer

### 1. Docker Service (src/Service/Docker.php)

The Docker service provides comprehensive Docker management capabilities:

**Container Management:**
- `getDockerContainers()`: Lists running containers
- `startDockerContainer()`: Starts specific containers
- `stopDockerContainer()`: Stops containers gracefully
- `checkPortAvailable()`: Verifies port availability

**Network Management:**
- `networkExists()`: Checks if network exists
- `createNetwork()`: Creates Docker networks
- `connectContainerToNetwork()`: Manages network connectivity

**Image Management:**
- `buildImage()`: Builds Docker images
- `pullImage()`: Pulls images from registries
- `removeImage()`: Cleans up unused images

**Volume Management:**
- `createVolume()`: Creates Docker volumes
- `listVolumes()`: Lists available volumes
- `removeVolume()`: Cleans up volumes

**Parsing Utilities:**
- Advanced table parsing for Docker CLI output
- Model-based data transformation
- Error handling and validation

### 2. Plugins Service (src/Service/Plugins.php)

The Plugins service handles Moodle plugin management:

**Plugin Discovery:**
- `getPluginsInfoFromRecipe()`: Extracts plugin information from recipes
- `discoverPluginType()`: Determines plugin type (mod, block, qtype, etc.)
- `validatePluginRepository()`: Validates plugin sources

**Plugin Operations:**
- `clonePlugins()`: Clones plugin repositories locally
- `copyPluginsToContainer()`: Copies plugins to Docker containers
- `removePluginsFromContainer()`: Removes plugins from containers
- `updatePlugins()`: Updates plugins to latest versions

**Plugin Metadata:**
- `getPluginInfo()`: Retrieves plugin metadata
- `parsePluginVersion()`: Extracts version information
- `getPluginDependencies()`: Resolves plugin dependencies

### 3. Database Service (src/Service/Database.php)

The Database service manages database operations:

**Database Setup:**
- `installMoodleDatabase()`: Installs Moodle database schema
- `configureDatabase()`: Sets up database connections
- `createDatabaseUser()`: Manages database users

**Database Operations:**
- `backupDatabase()`: Creates database backups
- `restoreDatabase()`: Restores from backups
- `executeQuery()`: Runs SQL queries
- `importData()`: Imports data from files

**Database Health:**
- `checkDatabaseConnection()`: Tests connectivity
- `verifySchema()`: Validates database structure
- `optimizeDatabase()`: Performs maintenance tasks

### 4. File Service (src/Service/File.php)

The File service provides filesystem utilities:

**File Operations:**
- `findFileInOrAboveDir()`: Locates files in directory hierarchy
- `copyDirectory()`: Recursive directory copying
- `createDirectory()`: Directory creation with permissions
- `cleanDirectory()`: Directory cleanup

**File Management:**
- `generateConfigFiles()`: Creates configuration files
- `managePermissions()`: Handles file permissions
- `validatePaths()`: Path validation

### 5. Recipe Parser Service (src/Service/RecipeParser.php)

The Recipe Parser service handles configuration parsing:

**Recipe Processing:**
- `parse()`: Parses recipe JSON files
- `validate()`: Validates recipe structure
- `normalize()`: Normalizes recipe data
- `resolveDefaults()`: Sets default values

**Configuration Management:**
- `mergeConfigurations()`: Combines multiple configurations
- `handleIncludes()`: Processes include directives
- `validateDependencies()`: Checks for required dependencies

## Command System

MChef implements a command-based architecture with the following commands:

### 1. Behat Command (src/Command/Behat.php)

Handles Behat testing functionality:

**Features:**
- `run()`: Executes Behat test suites
- `init()`: Initializes Behat environment
- `configure()`: Sets up Behat configuration
- `profileManagement()`: Manages test profiles

**Options:**
- `--profile`: Specifies test profile (chrome, firefox, etc.)
- `--plugins`: Tests specific plugins
- `--tags`: Runs tests with specific tags
- `--features`: Runs specific feature files

### 2. PHPUnit Command (src/Command/PHPUnit.php)

Manages PHPUnit testing:

**Features:**
- `run()`: Executes PHPUnit tests
- `configure()`: Sets up PHPUnit configuration
- `coverage()`: Generates code coverage reports
- `filter()`: Filters tests by namespace or class

### 3. Database Command (src/Command/Database.php)

Provides database management:

**Features:**
- `backup()`: Creates database backups
- `restore()`: Restores from backups
- `access()`: Provides database shell access
- `status()`: Shows database status

### 4. CopySrc Command (src/Command/CopySrc.php)

Handles source code management:

**Features:**
- `copy()`: Copies source code to containers
- `sync()`: Synchronizes local and container sources
- `pluginManagement()`: Manages plugin sources

### 5. RemoveSrc Command (src/Command/RemoveSrc.php)

Manages source code removal:

**Features:**
- `remove()`: Removes source code from containers
- `cleanup()`: Cleans up temporary files

## Model Layer

### 1. Recipe Model (src/Model/Recipe.php)

The Recipe model represents the configuration for a Moodle environment:

**Core Properties:**
- `moodleTag`: Moodle version (e.g., "v4.1.0")
- `phpVersion`: PHP version (e.g., "8.0")
- `name`: Project name
- `version`: Recipe version
- `vendor`: Vendor information

**Plugin Configuration:**
- `plugins`: Array of plugin repositories
- `cloneRepoPlugins`: Whether to clone plugins locally

**Web Server Configuration:**
- `host`: Web host name
- `hostProtocol`: HTTP/HTTPS protocol
- `port`: Web server port
- `updateHostHosts`: Whether to update system hosts file
- `maxUploadSize`: Maximum upload size
- `maxExecTime`: Maximum execution time

**Database Configuration:**
- `dbType`: Database type (pgsql, mysql)
- `dbVersion`: Database version
- `dbUser`: Database username
- `dbPassword`: Database password
- `dbRootPassword`: Root database password
- `dbName`: Database name
- `dbHostPort`: Database host port
- `installMoodledb`: Whether to install Moodle database

**Container Configuration:**
- `containerPrefix`: Prefix for container names

**Development Features:**
- `debug`: Debug mode
- `developer`: Developer mode with additional tools
- `includePhpUnit`: Include PHPUnit configuration
- `includeBehat`: Include Behat configuration
- `includeXdebug`: Include Xdebug
- `xdebugMode`: Xdebug mode settings
- `behatHost`: Behat test host

**Computed Properties:**
- `wwwRoot`: Computed web root URL
- `behatWwwRoot`: Behat web root URL

### 2. Plugin Models

**Plugin Model (src/Model/Plugin.php):**
- Represents individual Moodle plugins
- Handles plugin metadata and configuration
- Manages plugin dependencies

**PluginSrcGit Model (src/Model/PluginSrcGit.php):**
- Handles Git-based plugin sources
- Manages repository cloning and updates
- Tracks plugin versions and branches

### 3. Docker Models

**DockerContainer Model (src/Model/DockerContainer.php):**
- Represents Docker containers
- Tracks container status and configuration
- Manages container lifecycle

**DockerNetwork Model (src/Model/DockerNetwork.php):**
- Represents Docker networks
- Manages network configuration
- Handles network connectivity

**DockerData Model (src/Model/DockerData.php):**
- Aggregates Docker configuration data
- Manages volume and network configurations
- Handles container specifications

## Docker Integration

### Dockerfile Generation

MChef generates Dockerfiles using Twig templates:

**Template Location:** `templates/docker/main.dockerfile.twig`

**Key Features:**
- Base image selection based on PHP version
- Moodle installation and configuration
- Plugin integration
- Development tool installation
- Xdebug configuration
- Custom scripts and utilities

### Docker Compose Generation

**Template Location:** `templates/docker/main.compose.yml.twig`

**Key Features:**
- Service definitions (web, database, behat)
- Volume management
- Network configuration
- Port mapping
- Environment variables
- Health checks

### Container Management

**Container Types:**
- **Moodle Container**: Main Moodle application
- **Database Container**: PostgreSQL or MySQL database
- **Behat Container**: Testing environment
- **Development Container**: Additional development tools

**Container Naming:**
- Uses `containerPrefix` from recipe
- Format: `{prefix}-{service}` (e.g., `mc-moodle`, `mc-db`)

## Plugin Management

### Plugin Discovery

MChef automatically discovers and categorizes plugins:

**Supported Plugin Types:**
- `mod_*` (Activity modules)
- `block_*` (Blocks)
- `qtype_*` (Question types)
- `filter_*` (Filters)
- `theme_*` (Themes)
- `auth_*` (Authentication)
- `enrol_*` (Enrollment)
- `report_*` (Reports)
- And 40+ other Moodle plugin types

### Plugin Installation Workflow

1. **Repository Cloning**: Clones plugin repositories locally
2. **Type Detection**: Determines plugin type from repository structure
3. **Volume Creation**: Creates Docker volumes for plugin sources
4. **Container Integration**: Mounts plugin volumes in containers
5. **Moodle Registration**: Registers plugins with Moodle installation

### Plugin Configuration

**Simple Format:**
```json
{
  "plugins": [
    "https://github.com/user/plugin-repo.git"
  ]
}
```

**Advanced Format:**
```json
{
  "plugins": [
    {
      "repo": "https://github.com/user/plugin-repo.git",
      "branch": "MOODLE_401_STABLE",
      "commit": "abc123",
      "type": "qtype"
    }
  ]
}
```

## Configuration System

### Recipe Files

Recipe files are JSON-based configuration files that define the Moodle environment:

**Example Recipe:**
```json
{
  "name": "my-moodle-project",
  "moodleTag": "v4.1.0",
  "phpVersion": "8.0",
  "plugins": [
    "https://github.com/marcusgreen/moodle-qtype_gapfill.git",
    {
      "repo": "https://github.com/theme-snap.git",
      "branch": "MOODLE_401_STABLE"
    }
  ],
  "host": "moodle.local",
  "port": 8080,
  "dbType": "pgsql",
  "developer": true,
  "includeBehat": true,
  "includeXdebug": true,
  "cloneRepoPlugins": true,
  "updateHostHosts": true
}
```

### Configuration Processing

1. **File Loading**: Loads JSON recipe file
2. **Validation**: Validates JSON structure and required fields
3. **Normalization**: Converts data types and sets defaults
4. **Dependency Resolution**: Checks for required dependencies
5. **Template Rendering**: Generates configuration files using Twig

### Template Engine

MChef uses Twig for template rendering:

**Template Types:**
- Dockerfile templates
- Docker Compose templates
- Moodle configuration templates
- PHP configuration templates
- Xdebug configuration templates

**Template Features:**
- Variable substitution
- Conditional rendering
- Loop constructs
- Filter and function support
- Template inheritance

## Development Workflow

### Typical Development Process

1. **Project Setup:**
   ```bash
   mkdir my-moodle-project
   cd my-moodle-project
   ```

2. **Recipe Creation:**
   ```bash
   # Create recipe.json with desired configuration
   ```

3. **Environment Creation:**
   ```bash
   mchef.php recipe.json
   ```

4. **Plugin Development:**
   ```bash
   # Make changes to local plugin files
   # Changes are automatically reflected in container via volumes
   ```

5. **Testing:**
   ```bash
   # Run Behat tests
   mchef.php behat

   # Run PHPUnit tests
   mchef.php phpunit
   ```

6. **Container Management:**
   ```bash
   # Start containers
   mchef.php -s

   # Stop containers
   mchef.php -h
   ```

### Advanced Workflows

**Multi-Plugin Development:**
```bash
# Copy specific plugins to container
mchef.php copysrc --plugins=qtype_gapfill,filter_imageopt

# Remove plugins from container
mchef.php removesrc --plugins=qtype_gapfill
```

**Database Operations:**
```bash
# Backup database
mchef.php database --backup

# Restore database
mchef.php database --restore

# Access database shell
mchef.php database --access
```

**Behat Testing:**
```bash
# Run tests with specific profile
mchef.php behat --profile=chrome

# Run tests for specific plugins
mchef.php behat --plugins=qtype_gapfill

# Run tests with specific tags
mchef.php behat --tags=@javascript
```

## Error Handling

### Exception Handling

MChef implements comprehensive error handling:

**Exception Types:**
- `ExecFailed`: Command execution failures
- `ValidationException`: Configuration validation errors
- `DependencyException`: Missing dependency errors
- `DockerException`: Docker-related errors

**Error Handling Features:**
- Detailed error messages with context
- Stack trace preservation
- User-friendly error display
- Recovery suggestions
- Logging capabilities

### Validation

**Recipe Validation:**
- Required field validation
- Data type validation
- Value range validation
- Dependency validation

**Docker Validation:**
- Port availability checking
- Container name validation
- Network configuration validation
- Volume validation

## Security Considerations

### Security Features

1. **Container Isolation**: Each project runs in isolated containers
2. **Network Segmentation**: Custom Docker networks for security
3. **Permission Management**: Proper file permissions
4. **Database Security**: Secure database credentials
5. **Host File Management**: Safe hosts file updates

### Security Best Practices

1. **Password Management**:
   - Change default database passwords in production
   - Use strong, unique passwords
   - Avoid hardcoding credentials

2. **Network Security**:
   - Use custom network prefixes
   - Limit port exposure
   - Use firewall rules

3. **File Permissions**:
   - Set appropriate file permissions
   - Avoid running as root when possible
   - Use volume permissions carefully

4. **Update Management**:
   - Regularly update base images
   - Keep dependencies current
   - Monitor for security vulnerabilities

## Performance Optimization

### Performance Features

1. **Caching**:
   - Docker layer caching
   - Template rendering caching
   - Configuration caching

2. **Resource Management**:
   - Container resource limits
   - Memory optimization
   - CPU allocation

3. **Build Optimization**:
   - Multi-stage builds
   - Layer optimization
   - Image size reduction

### Performance Best Practices

1. **Recipe Optimization**:
   - Use specific plugin versions
   - Minimize unnecessary plugins
   - Optimize container configurations

2. **Container Management**:
   - Clean up unused containers
   - Prune unused images
   - Monitor resource usage

3. **Development Workflow**:
   - Use volume mounts for active development
   - Minimize container restarts
   - Use appropriate caching strategies

## Extensibility

### Customization Points

1. **Custom Commands**:
   - Extend `AbstractCommand` class
   - Implement `register()` and `execute()` methods
   - Add to command directory for auto-discovery

2. **Custom Services**:
   - Extend `AbstractService` class
   - Implement singleton pattern
   - Register with dependency injection

3. **Custom Models**:
   - Extend `AbstractModel` class
   - Add custom properties and methods
   - Integrate with existing services

4. **Custom Templates**:
   - Add new Twig templates
   - Extend existing templates
   - Create template overrides

### Plugin Development

MChef is designed to be extended with custom plugins:

1. **Plugin Types**:
   - Create custom plugin types
   - Extend plugin discovery
   - Add plugin-specific features

2. **Plugin Hooks**:
   - Pre-installation hooks
   - Post-installation hooks
   - Configuration hooks

3. **Plugin APIs**:
   - Plugin metadata APIs
   - Plugin management APIs
   - Plugin testing APIs

## Technical Details

### Dependency Management

MChef uses Composer for dependency management:

**Key Dependencies:**
- `splitbrain/phpcli`: CLI interface library
- `twig/twig`: Template engine
- `symfony/process`: Process management
- `symfony/console`: Console utilities

**Development Dependencies:**
- `phpunit/phpunit`: Testing framework
- `mockery/mockery`: Mocking library
- `friendsofphp/php-cs-fixer`: Code style tools

### File Structure

```
mchef/
├── bin/                  # Binary files and scripts
├── src/                  # Source code
│   ├── Command/          # CLI commands
│   ├── Model/            # Data models
│   ├── Service/          # Service classes
│   ├── Traits/           # Reusable traits
│   └── Tests/            # Test files
├── templates/            # Twig templates
│   ├── docker/           # Docker templates
│   └── moodle/           # Moodle templates
├── filter/               # Filter plugins
├── question/             # Question type plugins
├── theme/                # Theme plugins
├── .mchef/               # Generated files (hidden)
│   └── docker/           # Docker configuration
└── vendor/               # Composer dependencies
```

### Build Process

1. **Dependency Installation**:
   ```bash
   composer install
   ```

2. **Executable Installation**:
   ```bash
   php mchef.php -i
   ```

3. **Recipe Execution**:
   ```bash
   mchef.php recipe.json
   ```

### Development Environment

**Requirements:**
- PHP 8.0+
- Docker 20.10+
- Docker Compose 1.29+
- Composer 2.0+

**Recommended Tools:**
- Git for version control
- IDE with PHP support
- Docker Desktop for GUI management
- Postman for API testing

## Troubleshooting Guide

### Common Issues and Solutions

**1. Port Already in Use:**
```bash
# Check which process is using the port
lsof -i :8080

# Change port in recipe.json
{
  "port": 8081
}
```

**2. Docker Container Issues:**
```bash
# Stop all containers
mchef.php -h

# Remove containers
docker compose -f .mchef/docker/main.compose.yml down

# Restart
mchef.php recipe.json
```

**3. Plugin Not Found:**
- Verify plugin repository URL is correct
- Check plugin component name matches repository
- Ensure plugin has valid `version.php` file

**4. Database Connection Issues:**
```bash
# Access database container
mchef.php database --access

# Check database status
docker exec [container-name] pg_isready
```

**5. Permission Issues:**
```bash
# Check file permissions
ls -la .mchef/docker/

# Fix permissions
chmod -R 755 .mchef/
```

### Debugging Techniques

**Enable Debug Mode:**
```json
{
  "debug": true
}
```

**Verbose Output:**
```bash
# Run with verbose flag
mchef.php recipe.json -v
```

**Log Files:**
- **Apache Logs**: Inside container at `/var/log/apache2/`
- **PHP Logs**: System logs via syslog
- **Moodle Logs**: In Moodle data directory
- **Docker Logs**: `docker logs [container-name]`

## Best Practices

### Recipe Management
- Use version control for recipe files
- Document plugin sources and versions
- Use consistent naming conventions
- Store recipes in project root

### Plugin Development
- Use `cloneRepoPlugins: true` for active development
- Mount local directories as volumes
- Test changes in isolated environment
- Use specific branches for stability

### Performance Optimization
- Use appropriate resource limits
- Monitor container resource usage
- Clean up unused containers and images
- Optimize Dockerfile layers

### Security Practices
- Change default passwords in production
- Use HTTPS in production environments
- Regularly update base images
- Limit container permissions
- Use network segmentation

## Advanced Topics

### Custom Container Configuration

Modify `.mchef/docker/Dockerfile` for custom requirements:
```dockerfile
# Add after base image
RUN apt-get install -y custom-package
COPY custom-config.conf /etc/
```

### Environment Variables

Access environment variables in your code:
```php
$dbHost = getenv('DB_HOST');
$wwwRoot = getenv('WWWROOT');
```

### Custom Behat Profiles

Add custom profiles in `config.php`:
```php
$CFG->behat_profiles['custom'] = [
    'browser' => 'firefox',
    'tags' => '@javascript',
    'wd_host' => 'custom-selenium:4444'
];
```

### Multi-Container Architectures

MChef supports complex architectures:
- Multiple database containers
- Load-balanced web containers
- Separate testing containers
- Development vs production containers

### CI/CD Integration

Integrate MChef with CI/CD pipelines:
```yaml
# Example GitHub Actions workflow
- name: Setup MChef
  run: |
    git clone https://github.com/gthomas2/mchef.git
    cd mchef
    composer install
    php mchef.php -i

- name: Run Tests
  run: |
    mchef.php recipe.json
    mchef.php behat
```

## Architecture Decisions

### Why Docker?

1. **Isolation**: Each project runs in isolated environment
2. **Reproducibility**: Consistent environments across teams
3. **Portability**: Works on any Docker-supported platform
4. **Resource Management**: Efficient resource utilization
5. **Dependency Management**: Handles complex dependencies

### Why CLI?

1. **Automation**: Easy integration with scripts and CI/CD
2. **Performance**: Lower overhead than GUI applications
3. **Flexibility**: Supports complex workflows
4. **Remote Access**: Works well with SSH and remote servers
5. **Scriptability**: Easy to integrate with other tools

### Why Twig Templates?

1. **Separation of Concerns**: Separates logic from presentation
2. **Maintainability**: Easy to update templates
3. **Flexibility**: Supports complex configurations
4. **Security**: Automatic escaping and security features
5. **Extensibility**: Easy to add new template types

## Future Enhancements

### Planned Features

1. **Kubernetes Support**: Orchestration for production environments
2. **Multi-Host Support**: Distributed architectures
3. **Cloud Integration**: AWS, Azure, GCP support
4. **Enhanced Monitoring**: Container health monitoring
5. **Autoscaling**: Automatic resource scaling

### Community Contributions

MChef welcomes community contributions:
- Bug fixes and enhancements
- New plugin types and features
- Documentation improvements
- Test case contributions
- Performance optimizations

## Support and Resources

### Official Resources
- **GitHub Repository**: https://github.com/gthomas2/mchef
- **Issue Tracker**: GitHub Issues
- **Documentation**: This file and userdocs.md
- **Example Recipes**: example-mrecipe.json, mdl41.json

### Community Resources
- **Moodle Developer Documentation**: https://docs.moodle.org/dev/
- **Docker Documentation**: https://docs.docker.com/
- **Behat Documentation**: https://behat.org/
- **PHPUnit Documentation**: https://phpunit.de/

### Getting Help

1. **Check Documentation**: Review this file and userdocs.md
2. **Search Issues**: Look for similar problems in GitHub issues
3. **Create Issue**: Submit detailed bug reports
4. **Community Support**: Engage with Moodle developer community
5. **Contribute**: Submit pull requests with fixes and enhancements

## Conclusion

MChef provides a comprehensive solution for Moodle development, offering:

- **Rapid Environment Setup**: Quick creation of development environments
- **Plugin Management**: Easy plugin integration and testing
- **Testing Infrastructure**: Built-in Behat and PHPUnit support
- **Container Management**: Comprehensive Docker orchestration
- **Development Tools**: Xdebug, PHPUnit, and other developer tools
- **Extensibility**: Customizable and extensible architecture

This technical documentation provides a deep dive into MChef's architecture, components, and implementation details, serving as a comprehensive guide for developers, contributors, and advanced users.