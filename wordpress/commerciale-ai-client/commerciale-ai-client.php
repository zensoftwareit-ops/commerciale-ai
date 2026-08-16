<?php
/**
 * Plugin Name: Commerciale AI Client Area
 * Description: Registrazione, listino, Stripe Checkout, Customer Portal e provisioning licenze Commerciale AI.
 * Version: 0.1.0
 * Author: Zen Software
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

final class Commerciale_AI_Client_Area
{
    private const OPTION_KEYS = ['cai_api_base_url', 'cai_api_key', 'cai_stripe_secret_key', 'cai_stripe_webhook_secret', 'cai_software_url'];

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_shortcode('commerciale_ai_pricing', [self::class, 'pricing_shortcode']);
        add_shortcode('commerciale_ai_account', [self::class, 'account_shortcode']);
        add_action('admin_post_cai_checkout', [self::class, 'checkout']);
        add_action('admin_post_cai_portal', [self::class, 'portal']);
        add_action('admin_post_cai_profile', [self::class, 'profile']);
        add_action('admin_post_nopriv_cai_register', [self::class, 'register']);
        add_action('rest_api_init', function (): void {
            register_rest_route('commerciale-ai/v1', '/stripe-webhook', ['methods' => 'POST', 'callback' => [self::class, 'stripe_webhook'], 'permission_callback' => '__return_true']);
        });
    }

    public static function admin_menu(): void
    {
        add_options_page('Commerciale AI', 'Commerciale AI', 'manage_options', 'commerciale-ai-client', [self::class, 'settings_page']);
    }

    public static function register_settings(): void
    {
        foreach (self::OPTION_KEYS as $key) register_setting('cai_settings', $key, ['sanitize_callback' => $key === 'cai_api_base_url' || $key === 'cai_software_url' ? 'esc_url_raw' : 'sanitize_text_field']);
    }

    public static function settings_page(): void
    {
        if (! current_user_can('manage_options')) return;
        $webhook = rest_url('commerciale-ai/v1/stripe-webhook');
        echo '<div class="wrap"><h1>Commerciale AI · Area Clienti</h1><p>Webhook Stripe: <code>'.esc_html($webhook).'</code></p><form method="post" action="options.php">';
        settings_fields('cai_settings');
        $fields = ['cai_api_base_url' => 'URL API Commerciale AI', 'cai_api_key' => 'Chiave integrazione billing', 'cai_stripe_secret_key' => 'Stripe Secret Key', 'cai_stripe_webhook_secret' => 'Stripe Webhook Secret', 'cai_software_url' => 'URL accesso software'];
        echo '<table class="form-table">';
        foreach ($fields as $key => $label) echo '<tr><th><label for="'.$key.'">'.esc_html($label).'</label></th><td><input class="regular-text" type="'.(str_contains($key, 'key') || str_contains($key, 'secret') ? 'password' : 'url').'" id="'.$key.'" name="'.$key.'" value="'.esc_attr(get_option($key)).'" autocomplete="off"></td></tr>';
        echo '</table>'; submit_button(); echo '</form><p>Shortcode listino: <code>[commerciale_ai_pricing]</code><br>Shortcode area cliente: <code>[commerciale_ai_account]</code></p></div>';
    }

    public static function pricing_shortcode(): string
    {
        $response = self::api('GET', '/api/v1/billing/plans');
        if (is_wp_error($response)) return '<p>Il listino non è temporaneamente disponibile.</p>';
        $plans = $response['data'] ?? [];
        ob_start(); echo '<div class="cai-plans" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px">';
        foreach ($plans as $plan) {
            echo '<article style="border:1px solid #ddd;border-radius:12px;padding:22px"><h3>'.esc_html($plan['name']).'</h3><p>'.esc_html($plan['description'] ?? '').'</p><strong>'.esc_html(number_format(($plan['annual_price_cents'] ?? 0) / 100, 2, ',', '.')).' '.esc_html($plan['currency']).'/anno</strong><ul>';
            foreach ($plan['features'] ?? [] as $feature) echo '<li>'.esc_html($feature).'</li>';
            echo '<li>'.esc_html($plan['seat_limit']).' utenti inclusi</li></ul>';
            if (! is_user_logged_in()) echo '<a href="'.esc_url(wp_login_url(get_permalink())).'">Accedi per acquistare</a>';
            elseif (! empty($plan['purchasable'])) {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="cai_checkout"><input type="hidden" name="plan_slug" value="'.esc_attr($plan['slug']).'">';
                wp_nonce_field('cai_checkout_'.$plan['slug']); echo '<button type="submit">Scegli '.esc_html($plan['name']).'</button></form>';
            } else echo '<span>Non ancora acquistabile</span>';
            echo '</article>';
        }
        echo '</div>'; return (string) ob_get_clean();
    }

    public static function account_shortcode(): string
    {
        if (! is_user_logged_in()) return self::registration_form();
        $user = wp_get_current_user();
        $remote = self::api('GET', '/api/v1/billing/accounts/'.rawurlencode(self::external_id($user->ID)).'/license');
        if (! is_wp_error($remote) && ! empty($remote['data'])) {
            update_user_meta($user->ID, 'cai_license_status', $remote['data']['status'] ?? '');
            update_user_meta($user->ID, 'cai_plan_name', $remote['data']['plan']['name'] ?? '');
            update_user_meta($user->ID, 'cai_license_key', $remote['data']['key'] ?? '');
        }
        $status = get_user_meta($user->ID, 'cai_license_status', true);
        $plan = get_user_meta($user->ID, 'cai_plan_name', true);
        $key = get_user_meta($user->ID, 'cai_license_key', true);
        ob_start();
        echo '<section class="cai-account"><h2>Il tuo account</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="cai_profile">'; wp_nonce_field('cai_profile');
        echo '<p><label>Nome<br><input name="display_name" required value="'.esc_attr($user->display_name).'"></label></p><p><label>Azienda<br><input name="company" value="'.esc_attr(get_user_meta($user->ID, 'cai_company', true)).'"></label></p><p><label>Email<br><input disabled value="'.esc_attr($user->user_email).'"></label></p><button>Salva dati</button></form>';
        echo '<h3>Abbonamento</h3><p>Pacchetto: <strong>'.esc_html($plan ?: 'Nessuno').'</strong><br>Stato: <strong>'.esc_html($status ?: 'non attivo').'</strong></p>';
        if ($key) echo '<p>Licenza: <code>'.esc_html($key).'</code></p>';
        if (get_user_meta($user->ID, 'cai_stripe_customer_id', true)) echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="cai_portal">'.wp_nonce_field('cai_portal', '_wpnonce', true, false).'<button>Pagamenti, fatture e disdetta</button></form>';
        if ($status && in_array($status, ['active', 'trialing'], true)) echo '<p><a href="'.esc_url(get_option('cai_software_url')).'">Accedi a Commerciale AI</a></p>';
        echo '</section>'; return (string) ob_get_clean();
    }

    private static function registration_form(): string
    {
        return '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="cai_register">'.wp_nonce_field('cai_register', '_wpnonce', true, false).'<h2>Crea il tuo account</h2><p><label>Nome<br><input name="name" required></label></p><p><label>Azienda<br><input name="company"></label></p><p><label>Email<br><input type="email" name="email" required></label></p><p><label>Password<br><input type="password" name="password" minlength="12" required></label></p><button>Crea account</button></form>';
    }

    public static function register(): void
    {
        check_admin_referer('cai_register');
        $email = sanitize_email(wp_unslash($_POST['email'] ?? '')); $name = sanitize_text_field(wp_unslash($_POST['name'] ?? '')); $password = (string) ($_POST['password'] ?? '');
        if (! is_email($email) || mb_strlen($password) < 12 || email_exists($email)) wp_die('Dati non validi o account già esistente.');
        $id = wp_create_user($email, $password, $email); if (is_wp_error($id)) wp_die(esc_html($id->get_error_message()));
        wp_update_user(['ID' => $id, 'display_name' => $name, 'first_name' => $name]); update_user_meta($id, 'cai_company', sanitize_text_field(wp_unslash($_POST['company'] ?? '')));
        wp_set_current_user($id); wp_set_auth_cookie($id, true); wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit;
    }

    public static function profile(): void
    {
        if (! is_user_logged_in()) auth_redirect(); check_admin_referer('cai_profile'); $id = get_current_user_id();
        $name = sanitize_text_field(wp_unslash($_POST['display_name'] ?? '')); $company = sanitize_text_field(wp_unslash($_POST['company'] ?? ''));
        wp_update_user(['ID' => $id, 'display_name' => $name]); update_user_meta($id, 'cai_company', $company);
        if (get_user_meta($id, 'cai_license_key', true)) self::api('PATCH', '/api/v1/billing/accounts/'.rawurlencode(self::external_id($id)), ['name' => $name, 'company' => $company]);
        wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit;
    }

    public static function checkout(): void
    {
        if (! is_user_logged_in()) auth_redirect(); $slug = sanitize_key($_POST['plan_slug'] ?? ''); check_admin_referer('cai_checkout_'.$slug);
        $plans = self::api('GET', '/api/v1/billing/plans'); if (is_wp_error($plans)) wp_die('Listino non disponibile.');
        $plan = null; foreach ($plans['data'] ?? [] as $candidate) if (($candidate['slug'] ?? '') === $slug) $plan = $candidate;
        if (! $plan || empty($plan['stripe_price_id'])) wp_die('Pacchetto non acquistabile.');
        $user = wp_get_current_user(); $customer = get_user_meta($user->ID, 'cai_stripe_customer_id', true);
        $body = ['mode' => 'subscription', 'line_items[0][price]' => $plan['stripe_price_id'], 'line_items[0][quantity]' => 1, 'success_url' => add_query_arg('checkout', 'success', wp_get_referer() ?: home_url('/')), 'cancel_url' => add_query_arg('checkout', 'cancelled', wp_get_referer() ?: home_url('/')), 'client_reference_id' => self::external_id($user->ID), 'metadata[wp_user_id]' => $user->ID, 'metadata[plan_slug]' => $slug, 'subscription_data[metadata][wp_user_id]' => $user->ID, 'subscription_data[metadata][plan_slug]' => $slug];
        if ($customer) $body['customer'] = $customer; else $body['customer_email'] = $user->user_email;
        $session = self::stripe('POST', '/v1/checkout/sessions', $body); if (is_wp_error($session) || empty($session['url'])) wp_die('Impossibile avviare il pagamento.');
        wp_redirect(esc_url_raw($session['url'])); exit;
    }

    public static function portal(): void
    {
        if (! is_user_logged_in()) auth_redirect(); check_admin_referer('cai_portal'); $customer = get_user_meta(get_current_user_id(), 'cai_stripe_customer_id', true); if (! $customer) wp_die('Cliente Stripe non disponibile.');
        $session = self::stripe('POST', '/v1/billing_portal/sessions', ['customer' => $customer, 'return_url' => wp_get_referer() ?: home_url('/')]); if (is_wp_error($session) || empty($session['url'])) wp_die('Portale pagamenti non disponibile.'); wp_redirect(esc_url_raw($session['url'])); exit;
    }

    public static function stripe_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_body(); $signature = (string) $request->get_header('stripe-signature');
        if (! self::valid_stripe_signature($payload, $signature)) return new WP_REST_Response(['message' => 'Invalid signature'], 400);
        $event = json_decode($payload, true); if (! is_array($event) || empty($event['id'])) return new WP_REST_Response(['message' => 'Invalid payload'], 400);
        $type = $event['type'] ?? ''; $object = $event['data']['object'] ?? [];
        if ($type === 'checkout.session.completed' && ! empty($object['subscription'])) $object = self::stripe('GET', '/v1/subscriptions/'.rawurlencode($object['subscription']));
        if (str_starts_with($type, 'customer.subscription.') || $type === 'checkout.session.completed') {
            if (is_wp_error($object) || ! self::sync_subscription($object, $event['id'], $type)) return new WP_REST_Response(['message' => 'Provisioning failed'], 500);
        }
        return new WP_REST_Response(['received' => true]);
    }

    private static function sync_subscription(array $subscription, string $event_id, string $event_type): bool
    {
        $metadata = $subscription['metadata'] ?? []; $user_id = absint($metadata['wp_user_id'] ?? 0); $slug = sanitize_key($metadata['plan_slug'] ?? ''); $user = get_user_by('id', $user_id);
        if (! $user || ! $slug) return false;
        $status = $subscription['status'] ?? 'suspended'; if (! in_array($status, ['active', 'trialing', 'past_due', 'unpaid', 'canceled', 'paused'], true)) $status = 'suspended';
        $period_timestamp = $subscription['current_period_end'] ?? ($subscription['items']['data'][0]['current_period_end'] ?? null);
        $period_end = $period_timestamp ? gmdate('c', (int) $period_timestamp) : null;
        $customer_id = is_array($subscription['customer'] ?? null) ? ($subscription['customer']['id'] ?? null) : ($subscription['customer'] ?? null);
        $result = self::api('POST', '/api/v1/billing/provision', ['event_id' => $event_id, 'event_type' => $event_type, 'external_account_id' => self::external_id($user_id), 'email' => $user->user_email, 'name' => $user->display_name, 'company' => get_user_meta($user_id, 'cai_company', true), 'plan_slug' => $slug, 'stripe_price_id' => $subscription['items']['data'][0]['price']['id'] ?? null, 'stripe_customer_id' => $customer_id, 'stripe_subscription_id' => $subscription['id'] ?? null, 'status' => $status, 'starts_at' => ! empty($subscription['start_date']) ? gmdate('c', (int) $subscription['start_date']) : null, 'current_period_ends_at' => $period_end, 'ends_at' => ! empty($subscription['ended_at']) ? gmdate('c', (int) $subscription['ended_at']) : null, 'cancel_at_period_end' => ! empty($subscription['cancel_at_period_end'])]);
        if (is_wp_error($result) || empty($result['data'])) return false;
        update_user_meta($user_id, 'cai_license_status', $result['data']['status']); update_user_meta($user_id, 'cai_plan_name', $result['data']['plan']['name'] ?? $slug); update_user_meta($user_id, 'cai_license_key', $result['data']['key'] ?? ''); update_user_meta($user_id, 'cai_stripe_customer_id', $customer_id); update_user_meta($user_id, 'cai_stripe_subscription_id', $subscription['id']); return true;
    }

    private static function valid_stripe_signature(string $payload, string $header): bool
    {
        $secret = (string) get_option('cai_stripe_webhook_secret'); if ($secret === '') return false; $parts = [];
        foreach (explode(',', $header) as $part) { [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null); if ($key && $value) $parts[$key][] = $value; }
        $timestamp = (int) ($parts['t'][0] ?? 0); if (! $timestamp || abs(time() - $timestamp) > 300) return false; $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($parts['v1'] ?? [] as $signature) if (hash_equals($expected, $signature)) return true; return false;
    }

    private static function external_id(int $user_id): string { return 'wp:'.substr(hash('sha256', home_url('/')), 0, 12).':'.$user_id; }

    private static function api(string $method, string $path, ?array $body = null): array|WP_Error
    {
        $base = untrailingslashit((string) get_option('cai_api_base_url')); $args = ['method' => $method, 'timeout' => 20, 'headers' => ['Authorization' => 'Bearer '.get_option('cai_api_key'), 'Accept' => 'application/json']];
        if ($body !== null) { $args['headers']['Content-Type'] = 'application/json'; $args['body'] = wp_json_encode($body); }
        $response = wp_remote_request($base.$path, $args); return self::decode($response);
    }

    private static function stripe(string $method, string $path, ?array $body = null): array|WP_Error
    {
        $args = ['method' => $method, 'timeout' => 20, 'headers' => ['Authorization' => 'Bearer '.get_option('cai_stripe_secret_key')]]; if ($body !== null) $args['body'] = $body;
        return self::decode(wp_remote_request('https://api.stripe.com'.$path, $args));
    }

    private static function decode(array|WP_Error $response): array|WP_Error
    {
        if (is_wp_error($response)) return $response; $status = wp_remote_retrieve_response_code($response); $data = json_decode(wp_remote_retrieve_body($response), true);
        return $status >= 200 && $status < 300 && is_array($data) ? $data : new WP_Error('cai_remote_error', is_array($data) ? ($data['message'] ?? $data['error']['message'] ?? 'Errore remoto') : 'Risposta remota non valida');
    }
}

Commerciale_AI_Client_Area::boot();

