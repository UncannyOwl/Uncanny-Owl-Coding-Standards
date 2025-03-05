# Uncanny Owl Coding Standards (UOCS)

PHP_CodeSniffer rules and sniffs to enforce Uncanny Owl coding conventions.

## Requirements

- PHP 7.4 or higher
- Composer

## Installation

### Global Installation (Recommended)

```bash
composer global require uocs/uncanny-owl-coding-standards
```

### Project Installation

```bash
composer require --dev uocs/uncanny-owl-coding-standards
```

## Usage

After installation, you can use the standards in several ways:

### Using Composer Scripts (Recommended)

```bash
# Check coding standards
composer uocs [path]

# Fix coding standards
composer uocbf [path]

# Use strict mode
composer uocs-strict [path]
composer uocbf-strict [path]
```

### Using PHPCS Directly

```bash
# Check coding standards
vendor/bin/phpcs --standard=Uncanny-Owl [path]

# Fix coding standards
vendor/bin/phpcbf --standard=Uncanny-Owl [path]
```

## Standards

The UOCS includes and extends the following standards:
- WordPress Coding Standards
- PHP Compatibility
- PHPCSExtra
- Custom Uncanny Owl rules

### Available Standards

- `Uncanny-Owl`: Default standard with common rules
- `Uncanny-Owl-Strict`: Stricter version with additional checks

### Custom Ruleset

You can override the default ruleset for your project in two ways:

1. **Project-specific ruleset** (Recommended):
   Create a `phpcs.xml` or `phpcs.xml.dist` in your project root:

```xml
<?xml version="1.0"?>
<ruleset name="Custom Project Standard">
    <!-- Use Uncanny-Owl as base -->
    <rule ref="Uncanny-Owl">
        <!-- Exclude rules you don't want -->
        <exclude name="WordPress.Files.FileName"/>
    </rule>

    <!-- Add your own custom rules -->
    <rule ref="Generic.Arrays.DisallowLongArraySyntax"/>
    
    <!-- Configure specific rules -->
    <rule ref="Generic.Files.LineLength">
        <properties>
            <property name="lineLimit" value="120"/>
        </properties>
    </rule>

    <!-- Define your paths -->
    <file>src/</file>
    <file>tests/</file>

    <!-- Define exclusions -->
    <exclude-pattern>/vendor/*</exclude-pattern>
    <exclude-pattern>/node_modules/*</exclude-pattern>
</ruleset>
```

2. **Command-line Override**:
```bash
# Using composer script
composer uocs --standard=/path/to/your/custom-ruleset.xml [path]

# Or directly with PHPCS
vendor/bin/phpcs --standard=/path/to/your/custom-ruleset.xml [path]
```

## IDE Integration

### Visual Studio Code

1. Install the [PHP Sniffer & Beautifier](https://marketplace.visualstudio.com/items?itemName=ValeryanM.vscode-phpsab) extension
2. Configure settings.json:
```json
{
    "phpSniffer.standard": "Uncanny-Owl",
    "phpSniffer.run": "onType"
}
```

### PhpStorm

1. Go to Settings → PHP → Quality Tools → PHP_CodeSniffer
2. Set PHP_CodeSniffer path to your vendor/bin/phpcs
3. Go to Settings → Editor → Inspections
4. Enable PHP → Quality Tools → PHP_CodeSniffer validation
5. Set 'Coding Standard' to Uncanny-Owl

## CI/CD Integration

### Buddy

```yaml
- pipeline: "Code Standards"
  trigger_mode: "ON_EVERY_PUSH"
  ref_name: "refs/heads/*"
  actions:
    - action: "Install Dependencies"
      type: "BUILD"
      working_directory: "/buddy/app"
      docker_image_name: "library/php"
      docker_image_tag: "8.1"
      execute_commands:
        - composer install
    - action: "Check Coding Standards"
      type: "BUILD"
      working_directory: "/buddy/app"
      docker_image_name: "library/php"
      docker_image_tag: "8.1"
      execute_commands:
        - composer uocs
```

### GitHub Actions

```yaml
name: PHP_CodeSniffer

on: [push, pull_request]

jobs:
  phpcs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install Dependencies
        run: composer install
      - name: Run PHPCS
        run: composer uocs
```

## Troubleshooting

### Common Issues

1. **Standards Not Found**
   - Ensure Composer installation was successful
   - Try running `composer install` again
   - Check if the standard is listed in `vendor/bin/phpcs -i`

2. **Path Issues**
   - Use relative paths from your project root
   - Ensure the path exists and is readable

### Debug Mode

For detailed output, add `-v` to the composer command:
```bash
composer uocs -v [path]
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please open an issue in the GitHub repository or contact the Uncanny Owl development team.

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
