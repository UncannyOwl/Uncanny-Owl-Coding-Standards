# Uncanny Owl Coding Standards

PHP_CodeSniffer rules (sniffs) to enforce Uncanny Owl coding conventions. These standards are based on WordPress Coding Standards with customizations specific to Uncanny Owl development practices.

## Requirements

- PHP 7.4 or higher
- [Composer](https://getcomposer.org/)
- [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) 3.0.0 or higher

## Installation

### Local Installation (Recommended)

1. Clone the repository:
```bash
git clone https://github.com/UncannyOwl/UOCS.git
cd UOCS
```

2. Install using Make:
```bash
make install
```

This will:
- Install all dependencies
- Set up the coding standards
- Create necessary symlinks
- Configure PHPCS

### Manual Installation

If you prefer not to use Make, you can install manually:

```bash
composer install
./vendor/bin/phpcs --config-set installed_paths ./Uncanny-Owl
chmod +x ./bin/uocs
chmod +x ./bin/uocbf
```

## Usage

The package provides two command-line tools:

### `uocs` - Check Code Style

Checks your code against the Uncanny Owl Coding Standards and reports violations.

```bash
# Basic usage
./bin/uocs <path>

# Example: Check a specific plugin directory
./bin/uocs /path/to/wp-content/plugins/your-plugin/src

# Example: Check a specific file
./bin/uocs /path/to/wp-content/plugins/your-plugin/src/file.php

# Use strict mode
./bin/uocs --strict /path/to/wp-content/plugins/your-plugin/src

# Enable debug output
DEBUG=1 ./bin/uocs /path/to/wp-content/plugins/your-plugin/src
```

### `uocbf` - Fix Code Style

Automatically fixes coding standard violations where possible.

```bash
# Basic usage
./bin/uocbf <path>

# Example: Fix a specific plugin directory
./bin/uocbf /path/to/wp-content/plugins/your-plugin/src

# Example: Fix a specific file
./bin/uocbf /path/to/wp-content/plugins/your-plugin/src/file.php

# Use strict mode
./bin/uocbf --strict /path/to/wp-content/plugins/your-plugin/src

# Enable debug output (useful for troubleshooting path issues)
DEBUG=1 ./bin/uocbf /path/to/wp-content/plugins/your-plugin/src
```

## Configuration

### Ruleset

The standards are defined in `Uncanny-Owl/ruleset.xml`. The ruleset extends WordPress-Extra standards with customizations:

- Security rules from WordPress-Security
- Database handling rules from WordPress-DB
- PHP compatibility checks
- Custom rules for Uncanny Owl specific conventions

### Ignored Patterns

By default, the following patterns are ignored:
- `*/build/*`
- `*/node_modules/*`
- `*/vendor/*`
- `.git`
- `.idea`
- `.vscode`
- `*.min.js`

### Reports

When using `uocs`, reports are generated in the `phpcs-reports` directory:
- `report-full.txt`: Detailed report of all violations
- `report-summary.txt`: Summary of violations by type

## Command Options

Both `uocs` and `uocbf` support the following options:

### Common Options
- `--strict`: Use stricter coding standards
- `--debug`: Enable debug output (useful for troubleshooting)
- `-h` or `--h`: Show help information

### Additional PHPCS/PHPCBF Options
All standard PHP_CodeSniffer options are supported. Common ones include:
- `--standard=<standard>`: Specify a different coding standard
- `--extensions=<extensions>`: Specify which file extensions to check
- `--ignore=<patterns>`: Specify additional patterns to ignore
- `--report=<report>`: Specify the report format

## Troubleshooting

### Path Issues
If you encounter path-related errors:
1. Use absolute paths to your target directory
2. Enable debug mode to see what paths are being used:
```bash
DEBUG=1 ./bin/uocbf /path/to/your/code
```

### Permission Issues
If you get permission denied errors:
```bash
chmod +x bin/uocs
chmod +x bin/uocbf
```

### Standards Not Found
If the standards aren't found:
1. Make sure you're running the commands from the UOCS directory
2. Verify that `Uncanny-Owl/ruleset.xml` exists
3. Run with debug mode to see the paths being checked:
```bash
DEBUG=1 ./bin/uocs --version
```

## Integration

### IDE Integration

#### Visual Studio Code
1. Install the [PHP_CodeSniffer extension](https://marketplace.visualstudio.com/items?itemName=ikappas.phpcs)
2. Configure settings.json:
```json
{
    "phpcs.standard": "/absolute/path/to/UOCS/Uncanny-Owl/ruleset.xml",
    "phpcs.executablePath": "/absolute/path/to/UOCS/vendor/bin/phpcs"
}
```

#### PhpStorm
1. Go to Settings → PHP → Quality Tools → PHP_CodeSniffer
2. Configure the PHP_CodeSniffer path to point to your UOCS installation
3. Set coding standard to the absolute path of your ruleset.xml

### Git Pre-commit Hook

Add to your `.git/hooks/pre-commit`:

```bash
#!/bin/bash
/path/to/UOCS/bin/uocs $(git diff --cached --name-only --diff-filter=ACMR | grep .php)
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please open an issue in the GitHub repository or contact the Uncanny Owl development team.

## Make Commands

The project includes several Make targets for easy management:

### Main Commands

- `make install`: Install coding standards and set up environment
- `make reinstall`: Clean and reinstall everything
- `make update`: Update to the latest version
- `make clean`: Remove installed symlinks and configuration

### Additional Commands

- `make check-standards`: Display installed coding standards and configuration
- `make add-sniffs`: Set up directory for additional custom sniffs

### Examples

Install everything fresh:
```bash
make reinstall
```

Add custom sniffs:
```bash
make add-sniffs
# Add your sniffs to the created directory
make reinstall
```

Check installation:
```bash
make check-standards
```

## Adding Custom Sniffs

1. Create the additional sniffs directory:
```bash
make add-sniffs
```

2. Add your custom sniffs to:
```
./Uncanny-Owl/additional-sniffs/Uncanny_Automator/
```

3. Reinstall to apply changes:
```bash
make reinstall
```

## IDE Integration

### Visual Studio Code

1. Install the [PHP Sniffer & Beautifier](https://marketplace.visualstudio.com/items?itemName=ValeryanM.vscode-phpsab) extension
2. Configure settings.json:
```json
{
    "phpsab.standard": "Uncanny-Owl",
    "phpsab.executablePathCS": "/usr/local/bin/uocs",
    "phpsab.executablePathCBF": "/usr/local/bin/uocbf"
}
```

### PhpStorm

1. Go to Settings → PHP → Quality Tools → PHP_CodeSniffer
2. Set PHP_CodeSniffer path to the `uocs` binary
3. In Editor → Inspections → PHP → Quality Tools
4. Enable PHP_CodeSniffer and select "Uncanny-Owl" standard

## Troubleshooting

### Common Issues

1. **Standards Not Found**
```bash
make reinstall
```

2. **Permission Issues**
```bash
sudo chmod +x ./bin/uocs ./bin/uocbf
```

3. **Path Issues**
```bash
./bin/uocs --debug <path>
```

### Debug Mode

For detailed output about paths and configuration:
```bash
./bin/uocs --debug <path>
./bin/uocbf --debug <path>
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Make your changes
4. Run tests and ensure coding standards:
```bash
make check-standards
./bin/uocs .
```
5. Submit a pull request

## License

MIT License - see LICENSE file for details.

## Support
