#!/bin/sh
set -eu

cd /var/www/html

attempt=0
until wp db check --path=/var/www/html --quiet; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 40 ]; then
        echo "Database non raggiungibile dopo 40 tentativi." >&2
        exit 1
    fi
    sleep 3
done

if ! wp core is-installed --path=/var/www/html; then
    wp core install \
        --path=/var/www/html \
        --url="$WP_HOME" \
        --title="$WP_SITE_TITLE" \
        --admin_user="$WP_ADMIN_USER" \
        --admin_password="$WP_ADMIN_PASSWORD" \
        --admin_email="$WP_ADMIN_EMAIL" \
        --skip-email
fi

wp theme activate commerciale-ai-theme --path=/var/www/html
wp plugin activate commerciale-ai-client --path=/var/www/html
wp plugin activate commerciale-ai-forms --path=/var/www/html
wp rewrite structure '/%postname%/' --hard --path=/var/www/html
wp option update timezone_string 'Europe/Rome' --path=/var/www/html
wp option update default_comment_status 'closed' --path=/var/www/html
wp option update default_ping_status 'closed' --path=/var/www/html

wp eval '
$options = [
    "cai_api_base_url" => getenv("CAI_API_BASE_URL"),
    "cai_api_key" => getenv("CAI_API_KEY"),
    "cai_stripe_secret_key" => getenv("CAI_STRIPE_SECRET_KEY"),
    "cai_stripe_webhook_secret" => getenv("CAI_STRIPE_WEBHOOK_SECRET"),
    "cai_software_url" => getenv("CAI_SOFTWARE_URL"),
];
foreach ($options as $key => $value) {
    if (is_string($value) && $value !== "") update_option($key, $value, false);
}
' --path=/var/www/html

echo "Commerciale AI WordPress è pronto su $WP_HOME"
