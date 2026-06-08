#!/bin/bash
set -e

# Run database migrations
php /var/www/html/migrate.php || true

# Start Apache
exec apache2-foreground
