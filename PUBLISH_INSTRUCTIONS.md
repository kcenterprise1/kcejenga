# Publishing kcejenga Package to GitHub

## Steps to Publish

1. **Create GitHub Repository**
   ```bash
   # If you haven't created the repo yet, create it on GitHub first
   # Repository URL: https://github.com/kcenterprise/kcejenga
   ```

2. **Add Remote and Push**
   ```bash
   cd packages/kce/kcejenga
   git remote add origin https://github.com/kcenterprise/kcejenga.git
   git add .
   git commit -m "Release v1.0.0 - Jenga Payment Gateway Laravel Package"
   git tag v1.0.0
   git push -u origin main
   git push origin v1.0.0
   ```

3. **Update Root Project**
   ```bash
   cd ../../..  # Back to project root
   composer update kce/kcejenga
   ```

4. **Remove Local Package (Optional)**
   Once the package is installed from GitHub, you can remove the local package:
   ```bash
   # Remove the local package directory
   rm -rf packages/kce/kcejenga
   ```

## Alternative: Use Packagist

If you want to publish to Packagist (public package registry):

1. Push to GitHub (steps above)
2. Go to https://packagist.org
3. Submit your package: `https://github.com/kcenterprise/kcejenga`
4. Once approved, you can remove the `repositories` section from root `composer.json`
5. Update requirement to: `"kce/kcejenga": "^1.0"`

## Current Configuration

- **Package Name**: `kce/kcejenga`
- **Version**: `1.0.0`
- **Repository**: `https://github.com/kcenterprise/kcejenga.git`
- **Root composer.json**: Configured to use VCS repository

## After Publishing

The package will be installed in `vendor/kce/kcejenga/` and you won't need to keep the local `packages/kce/kcejenga` directory.

